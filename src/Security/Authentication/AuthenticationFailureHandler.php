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

namespace Markocupic\ContaoOAuth2Client\Security\Authentication;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Message;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RouterInterface $router,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.debug%')]
        private readonly bool $isDebug,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * Logs the security exception.
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Do not use Contao Core's onAuthenticationFailure handler
        // because this leads to a redirection loop.
        $targetPath = $this->determineTargetPath($request);

        $sessionBag = $this->getSessionBag($request);
        $sessionBag->clear();

        if ($this->isDebug) {
            // Show message in debug mode
            $this->getMessageAdapter()->addError($exception->getMessage());
        }

        $this->logger?->info($exception->getMessage());

        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($targetPath);
    }

    protected function getMessageAdapter(): Adapter
    {
        return $this->framework->getAdapter(Message::class);
    }

    protected function determineTargetPath(Request $request): string
    {
        // Do not use Contao Core's onAuthenticationFailure handler
        // because this leads to a redirection loop.
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

        return $targetPath;
    }

    protected function getSessionBag(Request $request): SessionBagInterface
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return $request->getSession()->getBag('markocupic_contao_oauth2_client_attr_backend');
        }

        return $request->getSession()->getBag('markocupic_contao_oauth2_client_attr_frontend');
    }
}
