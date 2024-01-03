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

namespace Markocupic\ContaoOAuth2Client\Security\UserMatcher;

use Contao\User;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('contao_oauth2_client.user_matcher')]
interface UserMatcherInterface
{
    /**
     * Return all supported oauth clients
     * e.g. ['github_backend','github_frontend'].
     *
     * @return array<string>
     */
    public function supports(): array;

    /**
     * Return the Contao User or null
     * if the user does not exist.
     */
    public function getContaoUserFromResourceOwner(ResourceOwnerInterface $resourceOwner, Request $request): User|null;

    /**
     * Returns the key where the resource owner identifier is saved.
     */
    public function getResourceOwnerIdentifierKey(): string;

    /**
     * Returns the email address or username that matches with the Contao User.
     */
    public function getResourceOwnerIdentifier(ResourceOwnerInterface $resourceOwner): string;

    /**
     * Returns the Contao identifier key
     * that matches with the resource owner identifier
     * (User::email or User::email).
     */
    public function getContaoUserIdentifierKey(): string;
}
