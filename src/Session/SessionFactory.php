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

namespace Markocupic\ContaoOAuth2Client\Session;

use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionFactory implements SessionFactoryInterface
{
    public function __construct(
        readonly private SessionFactoryInterface $inner,
        readonly private SessionBagInterface $sessionBagBackend,
        readonly private SessionBagInterface $sessionBagFrontend,
    ) {
    }

    public function createSession(): SessionInterface
    {
        $session = $this->inner->createSession();
        $session->registerBag($this->sessionBagBackend);
        $session->registerBag($this->sessionBagFrontend);

        return $session;
    }
}
