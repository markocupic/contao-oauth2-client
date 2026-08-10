<?php

declare(strict_types=1);

/*
 * This file is part of Contao OAuth2 Client.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-oauth2-client
 */

namespace Markocupic\ContaoOAuth2Client\Security\Authenticator;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Security\Authentication\AuthenticationSuccessHandler;
use Contao\User;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Markocupic\ContaoOAuth2Client\Event\BeforeAuthorizationRequestEvent;
use Markocupic\ContaoOAuth2Client\Event\GetAccessTokenEvent;
use Markocupic\ContaoOAuth2Client\Event\GetResourceOwnerEvent;
use Markocupic\ContaoOAuth2Client\OAuth2\Client\ClientFactoryManager;
use Markocupic\ContaoOAuth2Client\OAuth2\Token\TokenHandlerManager;
use Markocupic\ContaoOAuth2Client\Security\Authentication\AuthenticationFailureHandler;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception\AbstractAuthenticationException;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception\ClientNotActivatedAuthenticationException;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception\InvalidStateAuthenticationException;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception\NoAuthCodeAuthenticationException;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception\NoContaoMemberFoundAuthenticationException;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception\NoContaoUserFoundAuthenticationException;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\TwoFactorAuthenticator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class OAuth2Authenticator extends AbstractAuthenticator
{
    public const NAME = 'CONTAO_OAUTH2_AUTHENTICATOR';

    public function __construct(
        private readonly AuthenticationFailureHandler $authenticationFailureHandler,
        #[Autowire(service: 'contao.security.authentication_success_handler')]
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly ClientFactoryManager $clientFactoryManager,
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly TokenHandlerManager $tokenHandlerManager,
        private readonly LoggerInterface|null $contaoErrorLogger,
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
     * Call the /authorize endpoint from the identity provider.
     * This returns the urlAuthorize option
     * and generates and applies any necessary parameters
     * (e.g. code, state, ...).
     */
    public function authorize(Request $request): RedirectResponse|Response
    {
        $clientName = $request->attributes->get('markocupic_contao_oauth2_client::client_name');

        $clientFactory = $this->clientFactoryManager->getClientFactory($clientName);

        if (!$clientFactory->isEnabled()) {
            throw new ClientNotActivatedAuthenticationException('Authentication failed! Client not activated.');
        }

        $client = $clientFactory->createClient($request);

        $authorizationUrl = $client->getAuthorizationUrl();

        $sessionBag = $this->getSessionBag($request);
        $sessionBag->set('oauth2state', $client->getState());

        // PKCE support: Store the PKCE code after the `getAuthorizationUrl()` call.
        $pkceCode = $client->getPkceCode();

        if (!empty($pkceCode)) {
            $sessionBag->set('pkceCode', $pkceCode);
        }

        // Use an event listener to e.g.
        // write the state to the database if there is no session,
        // or use it to modify the authorization url.
        $event = new BeforeAuthorizationRequestEvent($request, $client, $authorizationUrl);
        $this->eventDispatcher->dispatch($event);

        return new RedirectResponse($event->getAuthorizationUrl());
    }

    public function authenticate(Request $request): Passport
    {
        $this->framework->initialize();

        // Restore the state
        $sessionBag = $this->getSessionBag($request);
        $request->request->set('_target_path', $sessionBag->get('_target_path'));
        $request->request->set('_always_use_target_path', $sessionBag->get('_always_use_target_path'));

        // Retrieve the oauth2 client name from request.
        $clientName = $request->attributes->get('_oauth2_client');

        $clientFactory = $this->clientFactoryManager->getClientFactory($clientName);
        $firewallName = $clientFactory->getContaoFirewall();
        $client = $clientFactory->createClient($request);

        try {
            if (!$clientFactory->isEnabled()) {
                throw new ClientNotActivatedAuthenticationException('Authentication failed! Client not activated.');
            }

            // At your own risk, you can skip the oidc flow here
            // and let your custom access token handler
            // return a self validating passport
            // to log in Contao users.
            $event = new GetAccessTokenEvent($client, $request);
            $this->eventDispatcher->dispatch($event);

            if ($event->hasSelfValidatingPassport()) {
                return $event->getSelfValidatingPassport();
            }

            if (empty($request->query->get('code'))) {
                throw new NoAuthCodeAuthenticationException('No auth code parameter found in callback URL.');
            }

            if (!$this->checkState($request)) {
                throw new InvalidStateAuthenticationException('Invalid state parameter passed in callback URL.');
            }

            // PKCE support: Restore the PKCE code before the `getAccessToken()` call.
            $pkceCode = $sessionBag->get('pkceCode');

            if (!empty($pkceCode)) {
                $client->setPkceCode($pkceCode);
            }

            // Call the /token endpoint and
            // obtain an access token by presenting the authorization code grant.
            $accessToken = $client->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            // Call the /userinfo endpoint using the access token.
            // This returns a JSON response (JWT) with claims
            // about the currently authenticated end user.
            $resourceOwner = $client->getResourceOwner($accessToken);

            // Write your own custom subscriber or event listener
            // to e.g. create a missing Contao backend or frontend user.
            $event = new GetResourceOwnerEvent($resourceOwner, $accessToken, $client, $request);
            $this->eventDispatcher->dispatch($event);

            // Extract the Contao user from token (claims)
            $tokenHandler = $this->tokenHandlerManager->getTokenHandler($clientName);
            $userBadge = $tokenHandler->getUserBadgeFromResourceOwner($resourceOwner, $firewallName);

            if (null === $userBadge) {
                if ($this->scopeMatcher->isBackendRequest($request)) {
                    throw new NoContaoUserFoundAuthenticationException('No matching Contao Backend User found in the Database.');
                }

                throw new NoContaoMemberFoundAuthenticationException('No matching Contao Frontend User found in the Database.');
            }

            return new SelfValidatingPassport($userBadge);
        } catch (\Throwable $e) {
            $error = match (true) {
                $e instanceof AbstractAuthenticationException => sprintf('OAuth Login with APP "%s" (%s) failed with code "%s".', $clientFactory->getName(), $clientFactory->getProviderType(), $e->getMessageKey()),
                $e instanceof IdentityProviderException => sprintf('OAuth Login with APP "%s" (%s) failed with code "%s".', $clientFactory->getName(), $clientFactory->getProviderType(), 'identityProviderAuth'),
                default => sprintf('OAuth Login with APP "%s" (%s) failed with error "%s".', $clientFactory->getName(), $clientFactory->getProviderType(), $e->getMessage()),
            };

            $this->contaoErrorLogger?->error($e->getMessage());

            throw new AuthenticationException($error);
        }
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
        return $this->authenticationFailureHandler->onAuthenticationFailure($request, $exception);
    }

    /**
     * Bypass 2FA for this authenticator.
     */
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);

        $token->setAttribute('AUTHENTICATOR', self::NAME);

        $user = $token->getUser();

        if (!$user instanceof User) {
            return $token;
        }

        if ($user->useTwoFactor) {
            $token->setAttribute(TwoFactorAuthenticator::FLAG_2FA_COMPLETE, true);
        }

        return $token;
    }

    protected function getSessionBag(Request $request): SessionBagInterface
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return $request->getSession()->getBag('markocupic_contao_oauth2_client_attr_backend');
        }

        return $request->getSession()->getBag('markocupic_contao_oauth2_client_attr_frontend');
    }

    protected function checkState(Request $request): bool
    {
        $sessionBag = $this->getSessionBag($request);

        return match (true) {
            empty($request->query->get('state')), empty($sessionBag->get('oauth2state')) => false,
            $request->query->get('state') !== $sessionBag->get('oauth2state') => false,
            default => true,
        };
    }
}
