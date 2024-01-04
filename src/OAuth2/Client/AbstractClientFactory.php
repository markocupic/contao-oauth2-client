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

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\User;
use Contao\UserModel;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Markocupic\ContaoOAuth2Client\Controller\OAuth2RedirectController;

abstract class AbstractClientFactory implements ClientFactoryInterface
{
    protected array $config = [];

    public function __construct(
        protected readonly ContaoFramework $framework,
    ) {
    }

    public function getName(): string
    {
        return static::NAME;
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

    public function getContaoUserFromResourceOwner(ResourceOwnerInterface $resourceOwner, string $key = 'email'): User|null
    {
        $response = $resourceOwner->toArray();

        if (empty($response[$key])) {
            return null;
        }

        $value = $response[$key];

        if ('contao_backend' === $this->getContaoFirewall()) {
            $userModel = $this->framework->getAdapter(UserModel::class);
            $user = $userModel->findOneBy($key, $value);

            // Test if login as a backend user is permitted
            if ($user->disable || ('' !== $user->start && (int) $user->start > time()) || ('' !== $user->stop && (int) $user->stop < time())) {
                return null;
            }
        } else {
            $memberModel = $this->framework->getAdapter(MemberModel::class);
            $user = $memberModel->findOneBy($key, $value);

            // Test if login as a frontend user is permitted
            if (!$user->login || $user->disable || ('' !== $user->start && (int) $user->start > time()) || ('' !== $user->stop && (int) $user->stop < time())) {
                return null;
            }
        }

        if (null === $user) {
            return null;
        }

        if ('contao_backend' === $this->getContaoFirewall()) {
            $backendUser = $this->framework->getAdapter(BackendUser::class);
            $user = $backendUser->loadUserByIdentifier($user->username);
        } else {
            $frontendUser = $this->framework->getAdapter(FrontendUser::class);
            $user = $frontendUser->loadUserByIdentifier($user->username);
        }

        return $user;
    }
}
