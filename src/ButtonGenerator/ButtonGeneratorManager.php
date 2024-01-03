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

readonly final class ButtonGeneratorManager
{
    public function __construct(
        private ButtonGeneratorCollection $buttonGeneratorCollection,
    ) {
    }

    public function getButtonGeneratorForClient(string $clientName): ButtonGeneratorInterface|null
    {
        /** @var ButtonGeneratorInterface $buttonGenerator */
        foreach ($this->buttonGeneratorCollection->getButtonGenerators() as $buttonGenerator) {
            if (\in_array($clientName, $buttonGenerator->supports(), true)) {
                return $buttonGenerator;
            }
        }

        return null;
    }
}
