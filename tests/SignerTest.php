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

namespace BaBeuloula\CdnPhpBundle\Tests;

use BaBeuloula\CdnPhpBundle\Options;
use BaBeuloula\CdnPhpBundle\Signer;
use PHPUnit\Framework\TestCase;

final class SignerTest extends TestCase
{
    public function testIsEnabledWithNull(): void
    {
        self::assertFalse((new Signer(null))->isEnabled());
    }

    public function testIsEnabledWithEmptyString(): void
    {
        self::assertFalse((new Signer(''))->isEnabled());
    }

    public function testIsEnabledWithKey(): void
    {
        self::assertTrue((new Signer('secret'))->isEnabled());
    }

    public function testCalcSignatureIsDeterministic(): void
    {
        $signer = new Signer('my-secret');
        $options = new Options(200, 100);

        self::assertSame($signer->calcSignature($options), $signer->calcSignature($options));
    }

    public function testCalcSignatureMatchesExpectedHmac(): void
    {
        $signer = new Signer('my-secret');
        $options = new Options(200, 100);
        $expected = hash_hmac('sha256', $options->buildQuery(false), 'my-secret');

        self::assertSame($expected, $signer->calcSignature($options));
    }

    public function testSignWithDisabledSignerReturnsRawQuery(): void
    {
        $query = (new Signer(null))->sign(new Options(200));
        self::assertStringNotContainsString('signature=', $query);
        self::assertSame('w=200', $query);
    }

    public function testSignWithEnabledSignerAddsSignature(): void
    {
        $query = (new Signer('key'))->sign(new Options(200));
        self::assertStringContainsString('signature=', $query);
    }

    public function testIsValidReturnsTrueForCorrectSignature(): void
    {
        $signer = new Signer('key');
        $options = new Options(200, 100);
        $signature = $signer->calcSignature($options);
        $signed = new Options(200, 100, signature: $signature);

        self::assertTrue($signer->isValid($signed));
    }

    public function testIsValidReturnsFalseForWrongSignature(): void
    {
        $signer = new Signer('key');
        $options = new Options(200, 100, signature: 'wrong-signature');

        self::assertFalse($signer->isValid($options));
    }

    public function testIsCdnSigningEnabledWithNull(): void
    {
        self::assertFalse((new Signer(null, null))->isCdnSigningEnabled());
    }

    public function testIsCdnSigningEnabledWithKey(): void
    {
        self::assertTrue((new Signer(null, 'cdn-key'))->isCdnSigningEnabled());
    }

    public function testSignCdnRequestReturnsExpiresAndSig(): void
    {
        $result = (new Signer(null, 'cdn-key'))->signCdnRequest('images/foo.jpg');

        self::assertArrayHasKey('expires', $result);
        self::assertArrayHasKey('sig', $result);
        self::assertIsInt($result['expires']);
        self::assertIsString($result['sig']);
    }

    public function testSignCdnRequestExpiresApproximatesNowPlusTtl(): void
    {
        $ttl = 3600;
        $result = (new Signer(null, 'cdn-key', $ttl))->signCdnRequest('img.jpg');
        $expected = time() + $ttl;

        self::assertEqualsWithDelta($expected, $result['expires'], 5.0);
    }

    public function testSignCdnRequestNormalizesLeadingSlash(): void
    {
        $signer = new Signer(null, 'cdn-key', 3600);
        $withSlash = $signer->signCdnRequest('/images/foo.jpg');
        $withoutSlash = $signer->signCdnRequest('images/foo.jpg');

        self::assertSame($withSlash['sig'], $withoutSlash['sig']);
    }
}
