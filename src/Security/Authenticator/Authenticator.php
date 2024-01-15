<?php

declare(strict_types=1);

/*
 * This file is part of Contao OAuth2 Client.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-oauth2-client
 */

namespace Markocupic\ContaoOAuth2Client\Security\Authenticator;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Security\Authentication\AuthenticationSuccessHandler;
use Contao\MemberModel;
use Contao\Message;
use Contao\UserModel;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Markocupic\ContaoOAuth2Client\Event\GetAccessTokenEvent;
use Markocupic\ContaoOAuth2Client\OAuth2\Client\ClientFactoryManager;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception\ClientNotActivatedAuthenticationException;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception\InvalidStateAuthenticationException;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception\NoAuthCodeAuthenticationException;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception\NoContaoMemberFoundAuthenticationException;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception\NoContaoUserFoundAuthenticationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Translation\TranslatorInterface;

class Authenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly ClientFactoryManager $clientFactoryManager,
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RouterInterface $router,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoAccessLogger = null,
    ) {
    }

    public function supports(Request $request): bool
    {
        if (!$request->attributes->has('_scope')) {
            return false;
        }

        $clientName = $request->attributes->get('_oauth2_client');

        if (empty($clientName)) {
            return false;
        }

        $clientFactory = $this->clientFactoryManager->getClientFactory($clientName);

        if ($request->attributes->get('_route') !== $clientFactory->getRedirectRoute()) {
            return false;
        }

        return true;
    }

    /**
     * @param AuthenticationException|null $authException
     */
    public function start(Request $request, string $clientName, AuthenticationException|null $authException = null): RedirectResponse|Response
    {
        $clientFactory = $this->clientFactoryManager->getClientFactory($clientName);

        if (!$clientFactory->isEnabled()) {
            throw new ClientNotActivatedAuthenticationException('Authentication failed! Client not activated.');
        }
        $client = $clientFactory->createClient();

        // Fetch the authorization URL from the provider;
        // this returns the urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $client->getAuthorizationUrl();

        $sessionBag = $this->getSessionBag($request);
        $sessionBag->set('oauth2state', $client->getState());

        return new RedirectResponse($authorizationUrl);
    }

    public function authenticate(Request $request): Passport
    {
        $this->framework->initialize();

        // Get the message adapter
        $message = $this->framework->getAdapter(Message::class);

        $sessionBag = $this->getSessionBag($request);
        $request->request->set('_target_path', $sessionBag->get('_target_path'));
        $request->request->set('_always_use_target_path', $sessionBag->get('_always_use_target_path'));

        // Get the oauth2 client name from attributes
        $clientName = $request->attributes->get('_oauth2_client');

        $clientFactory = $this->clientFactoryManager->getClientFactory($clientName);
        $client = $clientFactory->createClient();

        try {
            if (!$clientFactory->isEnabled()) {
                throw new ClientNotActivatedAuthenticationException('Authentication failed! Client not activated.');
            }

            if (empty($request->query->get('code'))) {
                throw new NoAuthCodeAuthenticationException('Authentication failed! Did you authorize our app?');
            }

            if (empty($request->query->get('state')) || empty($this->getSessionBag($request)->get('oauth2state')) || $request->query->get('state') !== $this->getSessionBag($request)->get('oauth2state')) {
                throw new InvalidStateAuthenticationException('Authentication failed! Invalid state parameter passed in callback URL.');
            }

            // Try to get an access token using the authorization code grant.
            $accessToken = $client->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            // Get the resource owner object.
            $resourceOwner = $client->getResourceOwner($accessToken);

            // Dispatch markocupic_contao_oauth2_client.get_access_token event
            // use a subscriber to e.g. generate missing Contao user
            $event = new GetAccessTokenEvent($accessToken, $request);
            $this->eventDispatcher->dispatch($event, GetAccessTokenEvent::NAME);

            $contaoUser = $clientFactory->createContaoUserFromResourceOwner($resourceOwner);

            if (null === $contaoUser) {
                if ($this->scopeMatcher->isBackendRequest($request)) {
                    throw new NoContaoUserFoundAuthenticationException('No matching Contao Backend User found in the Database.');
                }

                throw new NoContaoMemberFoundAuthenticationException('No matching Contao Frontend User found in the Database.');
            }

            // The user exists and is not disabled. Get the correct Contao user model.
            if ($this->scopeMatcher->isBackendRequest($request)) {
                $userAdapter = $this->framework->getAdapter(UserModel::class);
            } else {
                $userAdapter = $this->framework->getAdapter(MemberModel::class);
            }

            $t = $userAdapter->getTable();
            $where = ["$t.username = ?"];

            $contaoUser = $userAdapter->findOneBy($where, [$contaoUser->username]);

            if (null === $contaoUser) {
                if ($this->scopeMatcher->isBackendRequest($request)) {
                    throw new NoContaoUserFoundAuthenticationException('No matching Contao Backend User found in the Database.');
                }

                throw new NoContaoMemberFoundAuthenticationException('No matching Contao Frontend User found in the Database.');
            }
        } catch (NoAuthCodeAuthenticationException|InvalidStateAuthenticationException|NoContaoUserFoundAuthenticationException|NoContaoMemberFoundAuthenticationException|IdentityProviderException $e) {
            $messageKey = $e instanceof IdentityProviderException ? 'identityProviderAuth' : $e->getMessageKey();

            // Notify user
            $message->addError($this->translator->trans('OAUTH_CLIENT_ERR.'.$messageKey, [], 'contao_default'));

            // Log txt
            $errorLog = sprintf('OAuth Login with APP "%s" (%s) failed with code "%s".', $clientFactory->getName(), $clientFactory->getProviderType(), $messageKey);

            throw new AuthenticationException($errorLog);
        } catch (\Exception $e) {
            // Notify user
            $message->addError($this->translator->trans('OAUTH_CLIENT_ERR.unexpectedAuth', [], 'contao_default'));

            // Log txt
            $errorLog = sprintf('OAuth Login with APP "%s" (%s) failed with message "%s".', $clientFactory->getName(), $clientFactory->getProviderType(), $e->getMessage());

            throw new AuthenticationException($errorLog);
        }

        return new SelfValidatingPassport(new UserBadge($contaoUser->username));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $firewallName): Response|null
    {
        // Clear the session.
        $this->getSessionBag($request)->clear();

        // Trigger the on authentication success handler from the Contao Core.
        return $this->authenticationSuccessHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response|null
    {
        // Do not use Contao Core's onAuthenticationFailure handler
        // because this leads to an endless redirection loop.
        $sessionBag = $this->getSessionBag($request);
        $targetPath = $request->get('_target_path');

        // Let's play it safe and make sure we always have a redirect URL.
        if ($this->scopeMatcher->isFrontendRequest($request) && $sessionBag->has('_failure_path')) {
            $targetPath = $sessionBag->get('_failure_path');
        }

        if (\is_string($targetPath)) {
            $targetPath = base64_decode($targetPath, true);
        }

        if (empty($targetPath)) {
            if ($this->scopeMatcher->isBackendRequest($request)) {
                $targetPath = $this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL);
            } else {
                $targetPath = $request->getSchemeAndHttpHost();
            }
        }

        $sessionBag->clear();

        $this->contaoAccessLogger?->info($exception->getMessage());

        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($targetPath);
    }

    private function getSessionBag(Request $request): SessionBagInterface
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return $request->getSession()->getBag('markocupic_contao_oauth2_client_attr_backend');
        }

        return $request->getSession()->getBag('markocupic_contao_oauth2_client_attr_frontend');
    }
}
