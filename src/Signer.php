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

    private function getSecretKey(): string
    {
        return $this->secretKey ?? '';
    }
}
