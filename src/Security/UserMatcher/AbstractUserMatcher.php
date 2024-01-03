<?php

declare(strict_types=1);

/*
* This file is part of Contao GitHub Authenticator.
*
* (c) Marko Cupic 2024 <m.cupic@gmx.ch>
* @license GPL-3.0-or-later
* For the full copyright and license information,
* please view the LICENSE file that was distributed with this source code.
* @link https://github.com/markocupic/contao-github-login
*/

namespace Markocupic\ContaoOAuth2Client\Security\UserMatcher;

use Contao\BackendUser;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\User;
use Contao\UserModel;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractUserMatcher implements UserMatcherInterface
{
    /**
     * Return the Contao User or null
     * if the user does not exist.
     */
    public function getContaoUserFromResourceOwner(ResourceOwnerInterface $resourceOwner, Request $request): User|null
    {
        $identifier = $resourceOwner->toArray()[$this->getResourceOwnerIdentifierKey()];

        if ($this->scopeMatcher->isBackendRequest($request)) {
            $user = UserModel::findOneBy($this->getContaoUserIdentifierKey(), $identifier);

            if (null !== $user) {
                return BackendUser::loadUserByIdentifier($user->username);
            }
        } else {
            $user = MemberModel::findOneBy($this->getContaoUserIdentifierKey(), $identifier);

            if (null !== $user) {
                return FrontendUser::loadUserByIdentifier($user->username);
            }
        }

        return null;
    }

    /**
     * Returns the email address or username that matches with the Contao User.
     */
    public function getResourceOwnerIdentifier(ResourceOwnerInterface $resourceOwner): string
    {
        return $resourceOwner->toArray()[$this->getResourceOwnerIdentifierKey()];
    }
}
