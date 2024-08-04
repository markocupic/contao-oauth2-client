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

namespace Markocupic\ContaoOAuth2Client\OAuth2\Token;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\User;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;

final class DefaultTokenHandler implements TokenHandlerInterface
{
    /**
     * The claim where the user identifier is stored
     */
    protected string $claim = 'email';

    /**
     * tl_user.email or tl_member.email.
     */
    protected string $contaoIdentifierFieldName = 'email';

    public function __construct(
        protected readonly ContaoFramework $framework,
        protected readonly Connection $connection,
    ) {
    }

    /**
     * Return all supported oauth clients
     * e.g. ['github_backend', 'github_frontend'].
     *
     * @return array<string>
     */
    public function supports(): array
    {
        return ['default'];
    }

    public function getUserFromResourceOwner(ResourceOwnerInterface $resourceOwner, string $firewall): User|null
    {
        $payload = $resourceOwner->toArray();

        if (empty($payload[$this->getClaim()])) {
            return null;
        }

        $identifier = $payload[$this->getClaim()];

        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        // Contao backend login
        if ('contao_backend' === $firewall) {
            $userModel = $this->framework->getAdapter(UserModel::class);

            // email should not be treated case-insensitive
            if ('email' === $this->getContaoIdentifierFieldName() && $validatorAdapter->isEmail($identifier)) {
                $email = $this->connection->fetchOne('SELECT email FROM tl_user WHERE email LIKE ?', [$identifier], [Types::STRING]);

                if (false === $email) {
                    return null;
                }

                $identifier = $email;
            }

            $user = $userModel->findOneBy($this->getContaoIdentifierFieldName(), $identifier);

            // Test if login as a backend user is permitted
            if ($user->disable || ('' !== $user->start && (int) $user->start > time()) || ('' !== $user->stop && (int) $user->stop < time())) {
                return null;
            }
        } else {
            // Contao frontend login
            $memberModel = $this->framework->getAdapter(MemberModel::class);

            // email should not be treated case-insensitive
            if ('email' === $this->getContaoIdentifierFieldName() && $validatorAdapter->isEmail($identifier)) {
                $email = $this->connection->fetchOne('SELECT email FROM tl_member WHERE email LIKE ?', [$identifier], [Types::STRING]);

                if (false === $email) {
                    return null;
                }

                $identifier = $email;
            }

            $user = $memberModel->findOneBy($this->getContaoIdentifierFieldName(), $identifier);

            // Test if login as a frontend user is permitted
            if (!$user->login || $user->disable || ('' !== $user->start && (int) $user->start > time()) || ('' !== $user->stop && (int) $user->stop < time())) {
                return null;
            }
        }

        if (null === $user) {
            return null;
        }

        if ('contao_backend' === $firewall) {
            $backendUser = $this->framework->getAdapter(BackendUser::class);
            $user = $backendUser->loadUserByIdentifier($user->username);
        } else {
            $frontendUser = $this->framework->getAdapter(FrontendUser::class);
            $user = $frontendUser->loadUserByIdentifier($user->username);
        }

        return $user;
    }

    protected function getClaim(): string
    {
        return $this->claim;
    }

    protected function getContaoIdentifierFieldName(): string
    {
        return $this->contaoIdentifierFieldName;
    }
}
