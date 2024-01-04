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
$GLOBALS['TL_LANG']['OAUTH_CLIENT_MSC']['or'] = 'oder';

/*
 * Errors
 */
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['noContaoUserFoundAuth'] = 'Login-Versuch gescheitert. Sie wurden nicht in der User-Datenbank gefunden.';
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['noContaoMemberFoundAuth'] = 'Login-Versuch gescheitert Sie wurden nicht in der Member-Datenbank gefunden.';
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['identityProviderAuth'] = 'Login-Versuch gescheitert. Kein Zugriff auf die vom Provider übermittelten Benutzerdaten möglich.';
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['unexpectedAuth'] = 'Login-Versuch gescheitert. Es ist ein unerwarteter Fehler aufgetreten.';
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['noAuthCodeAuth'] = 'Login-Versuch gescheitert. Haben Sie die App autorisiert?';
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['invalidStateAuth'] = 'Login-Versuch gescheitert. Es wurde kein gültiger State-Parameter in der Callback-URL gefunden.';
$GLOBALS['TL_LANG']['OAUTH_CLIENT_ERR']['clientNotActivatedAuth'] = 'Login-Versuch mit dem ausgewählten Login Provider gescheitert. Der Client ist nicht aktiviert.';
