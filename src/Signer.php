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

use Defuse\Crypto\Crypto;

final class Signer
{
    public function __construct(
        private readonly ?string $secretKey = null,
        private readonly ?string $cdnSecretKey = null,
        private readonly int $cdnExpiresTtl = 3600,
    ) {
    }

    public function isEnabled(): bool
    {
        return \strlen($this->getSecretKey()) > 0;
    }

    public function sign(Options $options): string
    {
        if (true === $this->isEnabled()) {
            $options = $options->setSignature($this->calcSignature($options));
        }

        return $options->buildQuery();
    }

    public function isValid(Options $options): bool
    {
        return $this->calcSignature($options) === $options->signature;
    }

    public function calcSignature(Options $options): string
    {
        return sha1($options->buildQuery(false) . $this->getSecretKey());
    }

    public function isCdnSigningEnabled(): bool
    {
        return \strlen($this->cdnSecretKey ?? '') > 0;
    }

    /**
     * Generates expires + sig parameters for a CDN PHP request.
     * Replicates the URL normalization of UriDecoder::getUri() so the
     * computed imageUrl matches what the CDN will use for verification.
     *
     * @return array{expires: int, sig: string}
     */
    public function signCdnRequest(string $file): array
    {
        $path = ltrim($file, '/');
        $path = str_replace(['www.', 'http://', 'http:/', 'https://', 'https:/'], '', $path);
        $imageUrl = 'https://' . $path;

        $expires = time() + $this->cdnExpiresTtl;
        $sig = hash_hmac('sha256', $imageUrl . ':' . $expires, (string) $this->cdnSecretKey);

        return ['expires' => $expires, 'sig' => $sig];
    }

    private function getSecretKey(): string
    {
        return $this->secretKey ?? '';
    }
}
