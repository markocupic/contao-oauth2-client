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

namespace Markocupic\ContaoOAuth2Client\Security\Authenticator\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class NoContaoUserFoundAuthenticationException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'noContaoUserFoundAuth';
    }
}
