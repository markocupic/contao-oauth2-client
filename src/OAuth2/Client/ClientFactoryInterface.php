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

use Contao\User;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('contao_oauth2_client.client_factory')]
interface ClientFactoryInterface
{
    /**
     * Returns the client name.
     * e.g. github_backend, github_frontend, facebook_backend, ...
     */
    public function getName(): string;

    /**
     * Returns true/false if the client is enabled/disabled in your app configuration.
     */
    public function isEnabled(): bool;

    /**
     * Returns the provider type stored in the configuration array.
     * e.g. github, facebook, google, ...
     */
    public function getProviderType(): string;

    /**
     * Returns the contao firewall name: "contao_backend" or "contao_frontend".
     */
    public function getContaoFirewall(): string;

    /**
     * Returns the field used as identifier to load a user from payload.
     */
    public function getUserIdentifier(): string;

    /**
     * Returns the configuration array (Symfony Configuration).
     * Array(
     *   'enable_login' => 1,
     *   'client_id' => 'my_client_id',
     *   'client_secret' => 'topsecret!',
     * ).
     */
    public function getConfig(): array;

    /**
     * Returns a configuration value.
     */
    public function getConfigByKey(string $key): mixed;

    /**
     * Returns the redirect (symfony) route.
     * Matches with the url stored by the provider.
     */
    public function getRedirectRoute(): string;

    /**
     * Sets the field used as identifier to load a user from payload.
     */
    public function setUserIdentifier(string $userIdentifier): void;

    /**
     * Returns the provider object.
     */
    public function createClient(array $options): AbstractProvider;

    /**
     * Returns the Contao Backend- or Frontend user from resource owner.
     */
    public function createContaoUserFromResourceOwner(ResourceOwnerInterface $resourceOwner): User|null;
}
