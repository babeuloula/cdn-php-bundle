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

final class Options
{
    public const SIGNATURE_KEY = 'signature';

    private const DEFAULT_WIDTH = null;
    private const DEFAULT_HEIGHT = null;
    private const DEFAULT_WATERMARK_URL = null;
    private const DEFAULT_WATERMARK_POSITION = 'center';
    private const DEFAULT_WATERMARK_SCALE = 75;
    private const DEFAULT_WATERMARK_OPACITY = 50;
    private const DEFAULT_SIGNATURE = null;

    public function __construct(
        public readonly null|int|string $width = self::DEFAULT_WIDTH,
        public readonly null|int|string $height = self::DEFAULT_HEIGHT,
        public readonly ?string $watermarkUrl = self::DEFAULT_WATERMARK_URL,
        public readonly string $watermarkGravity = self::DEFAULT_WATERMARK_POSITION,
        public readonly int $watermarkScale = self::DEFAULT_WATERMARK_SCALE,
        public readonly int $watermarkOpacity = self::DEFAULT_WATERMARK_OPACITY,
        public readonly ?string $signature = self::DEFAULT_SIGNATURE,
    ) {
    }

    public function buildQuery(bool $withSignature = true): string
    {
        return http_build_query($this->toArray($withSignature));
    }

    /** @return array<int|string, mixed> */
    public function toArray(bool $withSignature = true): array
    {
        $options = [
            'w' => $this->width,
            'h' => $this->height,
        ];

        if (true === $withSignature) {
            $options[self::SIGNATURE_KEY] = $this->signature;
        }

        if (true === \is_string($this->watermarkUrl)) {
            $options = array_merge(
                $options,
                [
                    'wu' => $this->watermarkUrl,
                    'wp' => $this->watermarkGravity,
                    'ws' => $this->watermarkScale,
                    'wo' => $this->watermarkOpacity,
                ],
            );
        }

        return array_filter($options);
    }

    public function hasSignature(): bool
    {
        return true === \is_string($this->signature);
    }

    public function setSignature(string $signature): self
    {
        return self::fromArray(
            array_merge(
                $this->toArray(),
                [self::SIGNATURE_KEY => $signature],
            )
        );
    }

    /** @param array<int|string, mixed> $options */
    public static function fromArray(array $options): self
    {
        return new self(
            $options['width'] ?? $options['w'] ?? self::DEFAULT_WIDTH,
            $options['height'] ?? $options['h'] ?? self::DEFAULT_HEIGHT,
            $options['watermarkUrl'] ?? $options['wu'] ?? $options['wat_url'] ?? self::DEFAULT_WATERMARK_URL,
            $options['watermarkPosition'] ?? $options['wp'] ?? $options['wat_position'] ?? self::DEFAULT_WATERMARK_POSITION,
            (int) ($options['watermarkScale'] ?? $options['ws'] ?? $options['wat_scale'] ?? self::DEFAULT_WATERMARK_SCALE),
            (int) ($options['watermarkOpacity'] ?? $options['wo'] ?? $options['wat_opacity'] ?? self::DEFAULT_WATERMARK_OPACITY),
            $options[self::SIGNATURE_KEY] ?? self::DEFAULT_SIGNATURE,
        );
    }
}
