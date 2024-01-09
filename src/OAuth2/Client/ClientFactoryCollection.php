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

readonly final class ClientFactoryCollection
{
    /**
     * @param array<ClientFactoryInterface> $clientFactoryCollection
     */
    public function __construct(
        private iterable $clientFactoryCollection = [],
    ) {
    }

    /**
     * @return iterable<ClientFactoryInterface>
     */
    public function getClientFactories(): iterable
    {
        return $this->clientFactoryCollection;
    }
}
