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

use BaBeuloula\CdnPhpBundle\AbstractHandler;
use BaBeuloula\CdnPhpBundle\Exception\FileNotFoundException;
use BaBeuloula\CdnPhpBundle\Options;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class InterventionImageFallbackHandler extends AbstractHandler implements FallbackHandlerInterface
{
    private ImageManager $imageManager;

    public function __construct(
        DriverInterface $driver,
        string $assetsPath,
        private readonly CacheInterface $cache,
        private readonly int $cacheLifetime = 60 * 60 * 24 * 14, // 14 days
    ) {
        parent::__construct($assetsPath);

        $this->imageManager = new ImageManager($driver);
    }

    /** @param array<string, mixed> $headers */
    public function response(string $file, ?Options $options = null, array $headers = []): Response
    {
        $file = $this->normalizeFile($file);

        try {
            $image = $this->imageManager->read($this->assetsPath . $file);
        } catch (DecoderException $e) {
            throw new FileNotFoundException($file, previous: $e);
        }

        $cacheKey = sha1($file . '?' . $options?->buildQuery());

        /** @var array{content: string, mimetype: string} $encodedImage */
        $encodedImage = $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($options, $image, $headers): array {
                if (null !== $options?->width && null !== $options->height) {
                    $image->cover((int) $options->width, (int) $options->height);
                } elseif (null !== $options?->width) {
                    $image->scale(width: (int) $options->width);
                } elseif (null !== $options?->height) {
                    $image->scale(height: (int) $options->height);
                }

                $item->expiresAfter($this->cacheLifetime);

                $encodedImage = (true === $this->supportWebp($headers))
                    ? $image->toWebp()
                    : $image->encodeByPath()
                ;

                return [
                    'content' => $encodedImage->toString(),
                    'mimetype' => $encodedImage->mimetype(),
                ];
            },
        );

        return new Response(
            $encodedImage['content'],
            Response::HTTP_OK,
            array_merge_recursive(
                $headers,
                ['Content-Type' => $encodedImage['mimetype']],
            ),
        );
    }

    /** @param array<string, mixed> $headers */
    private function supportWebp(array $headers): bool
    {
        if (false === \array_key_exists('accept', $headers)) {
            return false;
        }

        return str_contains($headers['accept'], 'image/webp');
    }
}
