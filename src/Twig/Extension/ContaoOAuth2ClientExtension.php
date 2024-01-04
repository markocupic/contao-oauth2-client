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

namespace Markocupic\ContaoOAuth2Client\Twig\Extension;

use Markocupic\ContaoOAuth2Client\Controller\OAuth2StartController;
use Markocupic\ContaoOAuth2Client\OAuth2\Client\ClientFactoryManager;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ContaoOAuth2ClientExtension extends AbstractExtension
{
    public function __construct(
        private readonly ClientFactoryManager $clientFactoryManager,
        private readonly RouterInterface $router,
        private readonly UriSigner $uriSigner,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('generate_oauth2_start_url_for', [$this, 'generateStartUrlFor']),
        ];
    }

    public function generateStartUrlFor(string $clientName): string
    {
        $clientFactory = $this->clientFactoryManager->getClientFactory($clientName);
        $route = 'contao_backend' === $clientFactory->getContaoFirewall() ? OAuth2StartController::LOGIN_ROUTE_BACKEND : OAuth2StartController::LOGIN_ROUTE_FRONTEND;

        return $this->uriSigner->sign($this->router->generate($route, ['_oauth2_client' => $clientName], UrlGeneratorInterface::ABSOLUTE_URL));
    }
}
