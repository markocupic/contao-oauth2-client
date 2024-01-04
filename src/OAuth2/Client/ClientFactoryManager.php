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

use Markocupic\ContaoOAuth2Client\OAuth2\Client\Exception\ClientFactoryNotFoundException;

readonly class ClientFactoryManager
{
    public function __construct(
        private ClientFactoryCollection $clientFactoryCollection,
    ) {
    }

    public function getClientFactory(string $clientName): ClientFactoryInterface
    {
        $clientFactory = $this->getMatchingClientFactory($clientName);

        if (null === $clientFactory) {
            throw new ClientFactoryNotFoundException(sprintf('Could not find a matching client factory for the oauth type "%s".', $clientName));
        }

        return $clientFactory;
    }

    /**
     * @return array<ClientFactoryInterface>
     */
    public function getAvailableClientFactories(): array
    {
        $clientFactories = [];

        foreach ($this->clientFactoryCollection->gtClientFactories() as $clientFactory) {
            $clientFactories[] = $clientFactory;
        }

        return $clientFactories;
    }

    /**
     * @return array<ClientFactoryInterface>
     */
    public function getAvailableAndActiveClientFactories(): array
    {
        $clientFactories = [];

        foreach ($this->getAvailableClientFactories() as $clientFactory) {
            if ($clientFactory->getConfigByKey('enable_login')) {
                $clientFactories[] = $clientFactory;
            }
        }

        return $clientFactories;
    }

    /**
     * @throws \Exception
     *
     * @return array<ClientFactoryInterface>
     */
    public function getAvailableClientsByFirewallName(string $firewall): array
    {
        if (!\in_array($firewall, ['contao_backend', 'contao_frontend'], true)) {
            throw new \Exception(sprintf('Argument #1 contains an invalid firewall name (%s). Did you mean one of these "%s","%s"?', $firewall, 'contao_backend', 'contao_frontend'));
        }

        $clientFactories = [];

        /** @var ClientFactoryInterface $clientFactory */
        foreach ($this->clientFactoryCollection->gtClientFactories() as $clientFactory) {
            if ($clientFactory->getContaoFirewall() === $firewall) {
                $clientFactories[] = $clientFactory;
            }
        }

        return $clientFactories;
    }

    private function getMatchingClientFactory(string $clientName): ClientFactoryInterface|null
    {
        /** @var ClientFactoryInterface $clientFactory */
        foreach ($this->clientFactoryCollection->gtClientFactories() as $clientFactory) {
            if ($clientName === $clientFactory->getName()) {
                return $clientFactory;
            }
        }

        return null;
    }
}
