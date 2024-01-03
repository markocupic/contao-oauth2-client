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

namespace Markocupic\ContaoOAuth2Client\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Markocupic\ContaoOAuth2Client\Controller\OAuth2StartController;
use Markocupic\ContaoOAuth2Client\OAuth2\Client\ClientFactoryManager
;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[AsHook('replaceInsertTags')]
class LoginRouteInsertTagListener
{
    public const TAG = 'oauth_login_url';

    public function __construct(
        private readonly ClientFactoryManager $clientFactoryManager,
        private readonly RouterInterface $router,
        private readonly UriSigner $uriSigner,
    ) {
    }

    public function __invoke(string $tag): string|false
    {
        $chunks = explode('::', $tag);

        if (self::TAG !== $chunks[0]) {
            return false;
        }

        $clientFactories = $this->clientFactoryManager->getAvailableAndActiveClientFactories();

        if (empty($chunks[1]) || !\in_array($chunks[1], array_map(static fn ($clientFactories) => $clientFactories->getName(), $clientFactories), true)) {
            return false;
        }

        $clientName = $chunks[1];
        $clientFactory = $this->clientFactoryManager->getClientFactory($clientName);
        $route = 'contao_backend' === $clientFactory->getContaoFirewall() ? OAuth2StartController::LOGIN_ROUTE_BACKEND : OAuth2StartController::LOGIN_ROUTE_FRONTEND;

        return $this->uriSigner->sign($this->router->generate($route, ['_oauth2_client' => $clientName], UrlGeneratorInterface::ABSOLUTE_URL));
    }
}
