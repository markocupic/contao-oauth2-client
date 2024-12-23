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

use Contao\BackendUser;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\User;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class DefaultTokenHandler implements TokenHandlerInterface
{
    /**
     * The claim where the user identifier is stored.
     */
    private string $claim = 'email';

    /**
     * tl_user.email or tl_member.email.
     */
    private string $contaoIdentifierFieldName = 'email';

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

    public function getUserBadgeFromResourceOwner(ResourceOwnerInterface $resourceOwner, string $firewall): UserBadge|null
    {
        $claims = $resourceOwner->toArray();

        [$userClass, $table] = match ($firewall) {
            'contao_backend' => [BackendUser::class, 'tl_user'],
            default => [FrontendUser::class, 'tl_member'],
        };

        $user = $this->getContaoUserFromClaims($claims, $table, $userClass);

        // Test if login is allowed
        if (empty($user) || !$this->canLogin($user)) {
            return null;
        }

        return new UserBadge($user->getUserIdentifier());
    }

    protected function getContaoUserFromClaims(array $claims, string $table, string $userClass): User|null
    {
        if (empty($claims[$this->claim])) {
            return null;
        }

        $identifierClaim = $claims[$this->claim];

        if (!$this->getValidator()->isEmail($identifierClaim)) {
            return null;
        }

        $qb = $this->connection->createQueryBuilder();

        $qb->select('t.username')
            ->from($table, 't')
            ->where($qb->expr()->like("t.$this->contaoIdentifierFieldName", $qb->expr()->literal($identifierClaim)))
        ;

        $userIdentifier = $qb->fetchOne();

        if (!$userIdentifier) {
            return null;
        }

        /** @var User $userProvider */
        $userProvider = $this->framework->getAdapter($userClass);

        return $userProvider->loadUserByIdentifier($userIdentifier);
    }

    protected function canLogin(User $user): bool
    {
        if ($user->disable) {
            return false;
        }

        if ('' !== $user->start && (int) $user->start > time()) {
            return false;
        }

        if ('' !== $user->stop && (int) $user->stop < time()) {
            return false;
        }

        if ($user instanceof FrontendUser) {
            if (!$user->login) {
                return false;
            }
        }

        return true;
    }

    protected function getValidator(): Adapter
    {
        return $this->framework->getAdapter(Validator::class);
    }
}
