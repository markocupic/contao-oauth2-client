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

namespace Markocupic\ContaoOAuth2Client\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsHook('replaceInsertTags')]
class FailurePathInsertTagListener
{
    public const TAG = 'oauth_login_failure_path';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(string $tag): string|false
    {
        $chunks = explode('::', $tag);

        if (empty($chunks) || self::TAG !== $chunks[0]) {
            return false;
        }

        return base64_encode($this->requestStack->getCurrentRequest()->getUri());
    }
}
