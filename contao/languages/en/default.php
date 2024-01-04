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

/*
 * Miscellaneous
 */
$GLOBALS['TL_LANG']['OAUTH_CLIENT_MSC']['or'] = 'or';

/*
 * Errors
 */
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['noContaoUserFoundAuth'] = 'Login attempt failed. You were not found in the user database.';
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['noContaoMemberFoundAuth'] = 'Login attempt failed. You were not found in the member database.';
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['identityProviderAuth'] = 'Login attempt failed. No access to the user data transmitted by the provider possible.';
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['unexpectedAuth'] = 'Login attempt failed. There has been an unexpected error.';
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['noAuthCodeAuth'] = 'Login attempt failed. Did you authorize our app?';
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['invalidStateAuth'] = 'Login attempt failed. Invalid state parameter passed in callback URL.';
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['clientNotActivatedAuth'] = 'Login attempt with the selected login provider failed. The client is not activated.';
