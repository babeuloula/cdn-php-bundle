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

namespace BaBeuloula\CdnPhpBundle;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractHandler
{
    protected string $assetsPath;

    public function __construct(
        string $assetsPath,
    ) {
        $this->assetsPath = rtrim($assetsPath, '/') . '/';
    }

    /** @return array<string, mixed> */
    public function parseHeaders(Request $request): array
    {
        $requestedHeaders = [
            'accept-language',
            'accept-encoding',
            'accept',
            'user-agent',
        ];

        $headers = [];

        foreach ($requestedHeaders as $header) {
            if (true === $request->headers->has($header)) {
                $headers[$header] = $request->headers->get($header, '');
            }
        }

        return $headers;
    }

    /** @param array<string, mixed> $headers */
    abstract public function response(string $file, ?Options $options = null, array $headers = []): Response;

    protected function normalizeFile(string $file): string
    {
        return ltrim($file, '/');
    }
}
