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

namespace Markocupic\ContaoOAuth2Client\Event;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class GetResourceOwnerEvent extends Event
{
    public function __construct(
        private ResourceOwnerInterface $resourceOwner,
        private readonly AccessTokenInterface $accessToken,
        private readonly AbstractProvider $client,
        private readonly Request $request,
    ) {
    }

    public function getResourceOwner(): ResourceOwnerInterface
    {
        return $this->resourceOwner;
    }

    public function setResourceOwner(ResourceOwnerInterface $resourceOwner): void
    {
        $this->resourceOwner = $resourceOwner;
    }

    public function getAccessToken(): AccessTokenInterface
    {
        return $this->accessToken;
    }

    public function getClient(): AbstractProvider
    {
        return $this->client;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getContaoScope(): string
    {
        return $this->request->attributes->get('_scope');
    }
}
