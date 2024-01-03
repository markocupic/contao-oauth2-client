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
use Markocupic\ContaoOAuth2Client\Controller\OAuth2RedirectController;
use Markocupic\ContaoOAuth2Client\Event\GetAccessTokenEvent;
use Markocupic\ContaoOAuth2Client\OAuth2\Client\ClientFactoryManager;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception\OAuth2AuthenticationException;
use Markocupic\ContaoOAuth2Client\Security\UserMatcher\UserMatcherCollection;
use Markocupic\ContaoOAuth2Client\Security\UserMatcher\UserMatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
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
    public const ALLOWED_ROUTES = [
        'backend' => OAuth2RedirectController::LOGIN_ROUTE_BACKEND,
        'frontend' => OAuth2RedirectController::LOGIN_ROUTE_FRONTEND,
    ];

    public function __construct(
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly ClientFactoryManager $clientFactoryManager,
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly TranslatorInterface $translator,
        private readonly UserMatcherCollection $userMatcherCollection,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function supports(Request $request): bool
    {
        if (!$request->attributes->has('_scope')) {
            return false;
        }

        $scope = $request->attributes->get('_scope');

        if ($request->attributes->get('_route') !== self::ALLOWED_ROUTES[$scope]) {
            return false;
        }

        if (empty($request->query->get('code'))) {
            return false;
        }

        if (!$request->attributes->has('_oauth2_client')) {
            return false;
        }

        $clientName = $request->attributes->get('_oauth2_client');

        $clientFactory = $this->clientFactoryManager->getClientFactory($clientName);

        if (!$clientFactory->getConfigByKey('enable_login')) {
            return false;
        }

        return true;
    }

    public function start(Request $request, string $clientName, AuthenticationException|null $authException = null): RedirectResponse|Response
    {
        $clientFactory = $this->clientFactoryManager->getClientFactory($clientName);
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

        if (empty($request->query->get('state')) || empty($this->getSessionBag($request)->get('oauth2state')) || $request->query->get('state') !== $this->getSessionBag($request)->get('oauth2state')) {
            // Exceptions will automatically trigger self::onAuthenticationFailure()
            $this->throwAuthenticationException(OAuth2AuthenticationException::ERROR_INVALID_OAUTH_STATE);
        }

        try {
            // Get the oauth2 client name from attributes
            $clientName = $request->attributes->get('_oauth2_client');

            $client = $this->clientFactoryManager
                ->getClientFactory($clientName)
                ->createClient()
            ;

            // Try to get an access token using the authorization code grant.
            $accessToken = $client->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            // Dispatch the GetAccessTokenEvent event
            $event = new GetAccessTokenEvent($accessToken, $request);
            $this->eventDispatcher->dispatch($event, GetAccessTokenEvent::NAME);

            // Get the resource owner object.
            $resourceOwner = $client->getResourceOwner($accessToken);
        } catch (IdentityProviderException $e) {
            // Exceptions will automatically trigger self::onAuthenticationFailure()
            $message->addError($this->translator->trans('OAUTH_CLIENT_ERR.identityProviderException', [$e->getMessage()], 'contao_default'));

            if ($this->scopeMatcher->isBackendRequest($request)) {
                $this->throwAuthenticationException(OAuth2AuthenticationException::ERROR_CONTAO_BACKEND_USER_NOT_FOUND);
            } else {
                $this->throwAuthenticationException(OAuth2AuthenticationException::ERROR_CONTAO_FRONTEND_USER_NOT_FOUND);
            }
        } catch (\Exception $e) {
            // Exceptions will automatically trigger self::onAuthenticationFailure()
            $this->throwAuthenticationException(OAuth2AuthenticationException::ERROR_UNEXPECTED);
        }

        $userMatcher = $this->findUserMatcher($clientName);

        if (null === $userMatcher) {
            throw new \Exception(sprintf('No supported user matcher class found for OAuth2 client "%s".', $clientName));
        }

        $contaoUser = $userMatcher->getContaoUserFromResourceOwner($resourceOwner, $request);

        if (null === $contaoUser) {
            $message->addError($this->translator->trans('OAUTH_CLIENT_ERR.userNotFound', [$userMatcher->getResourceOwnerIdentifier($resourceOwner)], 'contao_default'));

            if ($this->scopeMatcher->isBackendRequest($request)) {
                // This will again trigger self::onAuthenticationFailure()
                $this->throwAuthenticationException(OAuth2AuthenticationException::ERROR_CONTAO_BACKEND_USER_NOT_FOUND);
            } else {
                // This will again trigger self::onAuthenticationFailure()
                $this->throwAuthenticationException(OAuth2AuthenticationException::ERROR_CONTAO_FRONTEND_USER_NOT_FOUND);
            }
        }

        // Get the correct Contao user model.
        if ($this->scopeMatcher->isBackendRequest($request)) {
            $userAdapter = $this->framework->getAdapter(UserModel::class);
        } else {
            $userAdapter = $this->framework->getAdapter(MemberModel::class);
        }

        $t = $userAdapter->getTable();
        $where = ["$t.username = '$contaoUser->username'"];

        $contaoUser = $userAdapter->findBy($where, []);

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

        if ($this->scopeMatcher->isFrontendRequest($request) && $sessionBag->has('_failure_path')) {
            $targetPath = $sessionBag->get('_failure_path');
        }

        $sessionBag->clear();

        $this->logger?->info($exception->getMessage());

        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse(base64_decode($targetPath, true));
    }

    private function getSessionBag(Request $request): SessionBagInterface
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return $request->getSession()->getBag('markocupic_contao_oauth2_client_attr_backend');
        }

        return $request->getSession()->getBag('markocupic_contao_oauth2_client_attr_frontend');
    }

    private function throwAuthenticationException(int $code): void
    {
        throw new OAuth2AuthenticationException(OAuth2AuthenticationException::ERROR_MAP[$code], $code);
    }

    private function findUserMatcher(string $clientName): UserMatcherInterface|null
    {
        /** @var UserMatcherInterface $userMatcher */
        foreach ($this->userMatcherCollection->getMatchers() as $userMatcher) {
            if (\in_array($clientName, $userMatcher->supports(), true)) {
                return $userMatcher;
            }
        }

        return null;
    }
}
