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

namespace Markocupic\ContaoOAuth2Client\ButtonGenerator;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('contao_oauth2_client.button_generator')]
interface ButtonGeneratorInterface
{
    /**
     * Returns all supported oauth clients
     * e.g. ['github_backend','github_frontend'].
     *
     * @return array<string>
     */
    public function supports(): array;

    public function renderButton(string $clientName): string;
}
