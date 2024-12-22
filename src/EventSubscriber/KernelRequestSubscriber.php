<?php

declare(strict_types=1);

/*
 * This file is part of Contao OAuth2 Client.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-oauth2-client
 */

namespace Markocupic\ContaoOAuth2Client\EventSubscriber;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class KernelRequestSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = -100;

    public function __construct(
        private readonly Packages $packages,
        private readonly ScopeMatcher $scopeMatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['loadAssets', self::PRIORITY]];
    }

    public function loadAssets(RequestEvent $e): void
    {
        $request = $e->getRequest();

        if ($this->scopeMatcher->isBackendRequest($request)) {
            if ('contao_backend_login' === $request->attributes->get('_route')) {
                // Setting the markocupic_contao_oauth2_client::disable_backend_assets request attribute
                // will stop loading the backend assets.
                if (!$request->attributes->has('markocupic_contao_oauth2_client::disable_backend_assets')) {
                    $GLOBALS['TL_CSS'][] = $this->packages->getUrl('css/login_button.css', 'markocupic_contao_o_auth2_client');
                    $GLOBALS['TL_CSS'][] = $this->packages->getUrl('css/backend.css', 'markocupic_contao_o_auth2_client');
                    $GLOBALS['TL_JAVASCRIPT'][] = $this->packages->getUrl('js/login_button_animation.js', 'markocupic_contao_o_auth2_client');
                }
            }
        }
    }
}
