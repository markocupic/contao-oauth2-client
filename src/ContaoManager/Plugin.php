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

namespace Markocupic\ContaoOAuth2Client\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ContainerBuilder;
use Contao\ManagerPlugin\Config\ExtensionPluginInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Markocupic\ContaoOAuth2Client\MarkocupicContaoOAuth2Client;
use Markocupic\ContaoOAuth2Client\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

class Plugin implements BundlePluginInterface, RoutingPluginInterface, ExtensionPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(MarkocupicContaoOAuth2Client::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): RouteCollection|null
    {
        return $resolver
            ->resolve(__DIR__.'/../Controller')
            ->load(__DIR__.'/../Controller')
            ;
    }

    public function getExtensionConfig($extensionName, array $extensionConfigs, ContainerBuilder $container): array
    {
        if ('security' !== $extensionName) {
            return $extensionConfigs;
        }

        foreach ($extensionConfigs as &$extensionConfig) {
            if (isset($extensionConfig['firewalls'], $extensionConfig['firewalls']['contao_frontend'])) {
                $extensionConfig['firewalls']['contao_frontend']['custom_authenticators'][] = OAuth2Authenticator::class;

                if (empty($extensionConfig['firewalls']['contao_frontend']['entry_point'])) {
                    $extensionConfig['firewalls']['contao_frontend']['entry_point'] = 'contao_login';
                }
            }

            if (isset($extensionConfig['firewalls'], $extensionConfig['firewalls']['contao_backend'])) {
                $extensionConfig['firewalls']['contao_backend']['custom_authenticators'][] = OAuth2Authenticator::class;

                if (empty($extensionConfig['firewalls']['contao_backend']['entry_point'])) {
                    $extensionConfig['firewalls']['contao_backend']['entry_point'] = 'contao_login';
                }
            }
        }

        return $extensionConfigs;
    }
}
