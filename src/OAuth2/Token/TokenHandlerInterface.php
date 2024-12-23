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

namespace Markocupic\ContaoOAuth2Client\OAuth2\Token;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

#[AutoconfigureTag('contao_oauth2_client.token_handler')]
interface TokenHandlerInterface
{
    /**
     * @return array<string>
     */
    public function supports(): array;

    public function getUserBadgeFromResourceOwner(ResourceOwnerInterface $resourceOwner, string $firewall): UserBadge|null;
}
