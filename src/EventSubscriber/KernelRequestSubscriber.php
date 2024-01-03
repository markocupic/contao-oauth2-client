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

namespace Markocupic\ContaoOAuth2Client\EventSubscriber;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class KernelRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ScopeMatcher $scopeMatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'loadAssets'];
    }

    public function loadAssets(RequestEvent $e): void
    {
        $request = $e->getRequest();

        if ($this->scopeMatcher->isBackendRequest($request)) {
            if ('contao_backend_login' === $request->attributes->get('_route')) {
                $GLOBALS['TL_CSS'][] = 'bundles/markocupiccontaooauth2client/css/login_button.css|static';
                $GLOBALS['TL_CSS'][] = 'bundles/markocupiccontaooauth2client/css/backend.css|static';
                $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupiccontaooauth2client/js/login_button_animation.js|static';
            }
        }
    }
}
