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
    public function getName(): string;

    public function getProviderType(): string;

    public function getContaoFirewall(): string;

    public function getConfig(): array;

    public function getConfigByKey(string $key): mixed;

    public function getRedirectRoute(): string;

    public function createClient(array $options): AbstractProvider;

    public function getContaoUserFromResourceOwner(ResourceOwnerInterface $resourceOwner, string $key = 'email'): User|null;
}
