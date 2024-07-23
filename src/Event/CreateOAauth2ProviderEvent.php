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
use Symfony\Contracts\EventDispatcher\Event;

class CreateOAauth2ProviderEvent extends Event
{
    public function __construct(
        private Request $request,
        private AbstractProvider $client,
        private array $options,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getContaoScope(): string
    {
        return $this->request->attributes->get('_scope');
    }

    public function getClient(): AbstractProvider
    {
        return $this->client;
    }

    public function getOptions(): array
    {
        return $this->options;
    }



}
