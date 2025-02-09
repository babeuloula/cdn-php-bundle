<?php

/**
 * @author      BaBeuloula <info@babeuloula.fr>
 * @copyright   Copyright (c) BaBeuloula
 * @license     MIT
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace BaBeuloula\CdnPhpBundle\FallbackHandler;

use BaBeuloula\CdnPhpBundle\Options;
use Symfony\Component\HttpFoundation\Response;

interface FallbackHandlerInterface
{
    /** @param array<string, mixed> $headers */
    public function response(string $file, ?Options $options = null, array $headers = []): Response;
}
