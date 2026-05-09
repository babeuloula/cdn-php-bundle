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

use BaBeuloula\CdnPhpBundle\Exception\FetchAssetException;
use BaBeuloula\CdnPhpBundle\Exception\FileNotFoundException;
use BaBeuloula\CdnPhpBundle\FallbackHandler\FallbackHandlerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class Proxy extends AbstractHandler
{
    public function __construct(
        string $assetsPath,
        private readonly bool $checkAssets,
        private readonly Filesystem $filesystem,
        private readonly HttpClientInterface $client,
        private readonly string $cdnPhpUrl,
        private readonly Signer $signer,
        private readonly ?FallbackHandlerInterface $fallbackHandler = null,
    ) {
        parent::__construct($assetsPath);
    }

    /** @param array<string, mixed> $headers */
    public function response(string $file, ?Options $options = null, array $headers = []): Response
    {
        $file = $this->normalizeFile($file);

        if (true === $this->checkAssets && false === $this->exists($file)) {
            throw new FileNotFoundException($file);
        }

        $newResponse = new Response();

        try {
            $queryParts = $options?->buildQuery(false) ?? '';

            if (true === $this->signer->isCdnSigningEnabled()) {
                $cdnSignature = $this->signer->signCdnRequest($file);
                $signatureQuery = http_build_query($cdnSignature);
                $queryParts = '' !== $queryParts ? $queryParts . '&' . $signatureQuery : $signatureQuery;
            }

            $response = $this->client->request(
                Request::METHOD_GET,
                $this->cdnPhpUrl . $file . '?' . $queryParts,
                [
                    'headers' => $headers,
                    'timeout' => 25,
                ],
            );

            $newResponse->setContent($response->getContent());

            $standardHeaders = [
                'last-modified',
                'expires',
                'etag',
                'cache-control',
                'content-type',
                'vary',
            ];

            $responseHeaders = $response->getHeaders();

            foreach ($standardHeaders as $header) {
                if (false === \array_key_exists($header, $responseHeaders)) {
                    continue;
                }

                $newResponse->headers->set($header, $responseHeaders[$header]);
            }

            foreach ($responseHeaders as $header => $values) {
                if (true === str_starts_with($header, 'x-')) {
                    $newResponse->headers->set($header, $values);
                }
            }

            $newResponse->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        } catch (\Throwable $e) {
            if ($this->fallbackHandler instanceof FallbackHandlerInterface) {
                return $this->fallbackHandler->response($file, $options, $headers);
            }

            if (Response::HTTP_NOT_FOUND === $e->getCode()) {
                throw new NotFoundHttpException(previous: $e);
            }

            throw new FetchAssetException(previous: $e);
        }

        return $newResponse;
    }

    private function exists(string $file): bool
    {
        return $this->filesystem->exists($this->assetsPath . $file);
    }
}
