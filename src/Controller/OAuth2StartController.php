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

namespace Markocupic\ContaoOAuth2Client\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\InvalidRequestTokenException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\System;
use Markocupic\ContaoOAuth2Client\OAuth2\Client\ClientFactoryManager;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\Authenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

#[Route('/_start_oauth2_login/{_oauth2_client}/backend', name: self::LOGIN_ROUTE_BACKEND, defaults: ['_scope' => 'backend', '_token_check' => false])]
#[Route('/_start_oauth2_login/{_oauth2_client}/frontend', name: self::LOGIN_ROUTE_FRONTEND, defaults: ['_scope' => 'frontend', '_token_check' => false])]
class OAuth2StartController extends AbstractController
{
    public const LOGIN_ROUTE_BACKEND = 'markocupic_contao_oauth2_client_backend_login';
    public const LOGIN_ROUTE_FRONTEND = 'markocupic_contao_oauth2_client_frontend_login';

    public function __construct(
        private readonly Authenticator $authenticator,
        private readonly ClientFactoryManager $clientFactoryManager,
        private readonly ContaoCsrfTokenManager $tokenManager,
        private readonly ContaoFramework $framework,
        private readonly RouterInterface $router,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly UriSigner $uriSigner,
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
            return new JsonResponse(['message' => 'Bad Request: OAuth2Login is not activated.'], Response::HTTP_BAD_REQUEST);
        }

        $system = $this->framework->getAdapter(System::class);

        // Check CSRF token
        if ($system->getContainer()->getParameter('markocupic_contao_oauth2_client.enable_csrf_token_check')) {
            $csrfTokenName = $system->getContainer()->getParameter('contao.csrf_token_name');
            $this->validateCsrfToken($request->get('REQUEST_TOKEN'), $this->tokenManager, $csrfTokenName);
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

        return $this->authenticator->start($request, $clientName);
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
