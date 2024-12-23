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

namespace Markocupic\ContaoOAuth2Client\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Markocupic\ContaoOAuth2Client\OAuth2\Client\ClientFactoryManager;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\OAuth2Authenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

#[Route('/_start_oauth2_login/{_oauth2_client}/backend', name: self::LOGIN_ROUTE_BACKEND, defaults: ['_scope' => 'backend', '_token_check' => false])]
#[Route('/_start_oauth2_login/{_oauth2_client}/frontend', name: self::LOGIN_ROUTE_FRONTEND, defaults: ['_scope' => 'frontend', '_token_check' => false])]
class OAuth2StartController extends AbstractController
{
    public const LOGIN_ROUTE_BACKEND = 'markocupic_contao_oauth2_client_backend_login';
    public const LOGIN_ROUTE_FRONTEND = 'markocupic_contao_oauth2_client_frontend_login';

    public function __construct(
        #[Autowire('%markocupic_contao_oauth2_client.enable_csrf_token_check%')]
        private bool $enableCsrfTokenCheck,
        private readonly ClientFactoryManager $clientFactoryManager,
        private readonly ContaoCsrfTokenManager $tokenManager,
        // The custom authenticator has to be autowired and should not be type hinted explicitly
        // (See: https://github.com/symfony/symfony/issues/59091#issuecomment-2539293444)
        #[Autowire(service: OAuth2Authenticator::class)]
        private readonly AuthenticatorInterface $authenticator,
        private readonly RouterInterface $router,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly UriSigner $uriSigner,
        private readonly Security $security,
        #[Autowire('%contao.csrf_token_name%')]
        private readonly string|null $defaultTokenName = null,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, string $_oauth2_client, string $_scope): Response|null
    {
        if (!$this->uriSigner->checkRequest($request)) {
            return new JsonResponse(['message' => 'Access denied.'], Response::HTTP_BAD_REQUEST);
        }

        $clientName = $_oauth2_client;

        $clientFactory = $this->clientFactoryManager->getClientFactory($clientName);

        if (!$clientFactory->isEnabled()) {
            return new JsonResponse(['message' => 'Bad Request: OAuth2Login is not enabled.'], Response::HTTP_BAD_REQUEST);
        }

        // Pass the client name to the authenticator via request attribute
        $request->attributes->set('markocupic_contao_oauth2_client::client_name', $clientName);

        // Check CSRF token
        if ($this->defaultTokenName && $this->enableCsrfTokenCheck) {
            $this->validateCsrfToken($request->get('REQUEST_TOKEN'), $this->tokenManager, $this->defaultTokenName);
        }

        if ($this->scopeMatcher->isBackendRequest($request)) {
            $targetPath = $request->get('_target_path', base64_encode($this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL)));
        } else {
            // Frontend: If there is an authentication error, Contao will redirect the user back to the login form
            $failurePath = $request->get('_failure_path', null);
            $targetPath = $request->get('_target_path', base64_encode($request->getSchemeAndHttpHost()));
        }

        // Write _target_path, _always_use_target_path and _failure_path to the session
        $sessionBag = $this->getSessionBag($request);
        $sessionBag->set('_target_path', $targetPath);
        $sessionBag->set('_always_use_target_path', $request->get('_always_use_target_path', '0'));

        if (!empty($failurePath)) {
            $sessionBag->set('_failure_path', $failurePath);
        }

        // Redirect the user to the authorization endpoint of the identity provider
        return $this->authenticator->authorize($request);
    }

    private function getSessionBag(Request $request): SessionBagInterface
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return $request->getSession()->getBag('markocupic_contao_oauth2_client_attr_backend');
        }

        return $request->getSession()->getBag('markocupic_contao_oauth2_client_attr_frontend');
    }

    private function validateCsrfToken(string $strToken, ContaoCsrfTokenManager $tokenManager, string $csrfTokenName): void
    {
        $token = new CsrfToken($csrfTokenName, $strToken);

        if (!$tokenManager->isTokenValid($token)) {
            throw new InvalidRequestTokenException('Invalid CSRF token. Please reload the page and try again.');
        }
    }
}
