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

namespace Markocupic\ContaoOAuth2Client\OAuth2\Client;

use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Markocupic\ContaoOAuth2Client\Controller\OAuth2RedirectController;

abstract class AbstractClientFactory implements ClientFactoryInterface
{
    protected array $config = [];

    public function __construct(
        protected readonly ContaoFramework $framework,
        protected readonly Connection $connection,
    ) {
    }

    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * @throws \Exception
     */
    public function isEnabled(): bool
    {
        return $this->getConfigByKey('enable_login');
    }

    public function getProviderType(): string
    {
        return static::PROVIDER;
    }

    public function getContaoFirewall(): string
    {
        return static::CONTAO_FIREWALL;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @throws \Exception
     */
    public function getConfigByKey(string $key): mixed
    {
        if (!isset($this->config[$key])) {
            throw new \Exception(sprintf('Invalid key "%s" selected. Did you mean one of these: "%s"?', $key, implode('", "', array_keys($this->config))));
        }

        return $this->config[$key];
    }

    public function getRedirectRoute(): string
    {
        return 'contao_backend' === $this->getContaoFirewall() ? OAuth2RedirectController::LOGIN_ROUTE_BACKEND : OAuth2RedirectController::LOGIN_ROUTE_FRONTEND;
    }
}
