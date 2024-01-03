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

use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class GetAccessTokenEvent extends Event
{
    public const NAME = 'markocupic_contao_oauth2_client.get_access_token';

    public function __construct(
        private readonly AccessTokenInterface $accessToken,
        private readonly Request $request,
        private readonly string $contaoScope,
    ) {
    }

    public function getAccessToken(): AccessTokenInterface
    {
        return $this->accessToken;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getContaoScope(): string
    {
        return $this->contaoScope;
    }
}
