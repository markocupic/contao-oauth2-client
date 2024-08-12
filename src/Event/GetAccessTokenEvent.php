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

namespace Markocupic\ContaoOAuth2Client\Event;

use League\OAuth2\Client\Provider\AbstractProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\EventDispatcher\Event;

class GetAccessTokenEvent extends Event
{
    protected null|SelfValidatingPassport $selfValidatingPassport = null;

    public function __construct(
        private readonly AbstractProvider $client,
        private readonly Request $request,
    ) {
    }

    public function hasSelfValidatingPassport(): bool
    {
        return $this->selfValidatingPassport !== null;
    }

    public function getSelfValidatingPassport(): ?SelfValidatingPassport
    {
        return $this->selfValidatingPassport;
    }

    public function setSelfValidatingPassport(SelfValidatingPassport $selfValidatingPassport): void
    {
        $this->selfValidatingPassport = $selfValidatingPassport;
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
