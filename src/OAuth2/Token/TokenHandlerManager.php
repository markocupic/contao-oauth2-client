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

class TokenHandlerManager
{
    public function __construct(
        private readonly TokenHandlerCollection $tokenHandlerCollection,
    ) {
    }

    public function getTokenHandler(string $clientName): TokenHandlerInterface|null
    {
        /** @var TokenHandlerInterface $tokenHandler */
        foreach ($this->tokenHandlerCollection->getTokenHandlers() as $tokenHandler) {
            if (\in_array($clientName, $tokenHandler->supports(), true)) {
                return $tokenHandler;
            }
        }

        /** @var TokenHandlerInterface $tokenHandler */
        foreach ($this->tokenHandlerCollection->getTokenHandlers() as $tokenHandler) {
            if (\in_array('default', $tokenHandler->supports(), true)) {
                return $tokenHandler;
            }
        }

        return null;
    }
}
