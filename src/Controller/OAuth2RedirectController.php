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

namespace Markocupic\ContaoOAuth2Client\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/_oauth2_login/{_oauth2_client}/backend', name: self::LOGIN_ROUTE_BACKEND, defaults: ['_scope' => 'backend'])]
#[Route('/_oauth2_login/{_oauth2_client}/frontend', name: self::LOGIN_ROUTE_FRONTEND, defaults: ['_scope' => 'frontend'])]
class OAuth2RedirectController extends AbstractController
{
    public const LOGIN_ROUTE_BACKEND = 'markocupic_contao_oauth2_client_redirect_backend';
    public const LOGIN_ROUTE_FRONTEND = 'markocupic_contao_oauth2_client_redirect_frontend';

    public function __invoke(Request $request, string $_oauth2_client): Response
    {
        // This point should never be reached.
        return new Response('');
    }
}
