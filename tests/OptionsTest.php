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
use PHPUnit\Framework\TestCase;

final class OptionsTest extends TestCase
{
    public function testToArrayEmpty(): void
    {
        self::assertSame([], (new Options())->toArray());
    }

    public function testToArrayWithDimensions(): void
    {
        $result = (new Options(200, 100))->toArray();
        self::assertSame(['w' => 200, 'h' => 100], $result);
    }

    public function testToArrayExcludesNullValues(): void
    {
        $result = (new Options(width: 200))->toArray();
        self::assertArrayHasKey('w', $result);
        self::assertArrayNotHasKey('h', $result);
    }

    public function testToArrayWithWatermarkIncludesWatermarkKeys(): void
    {
        $result = (new Options(watermarkUrl: 'http://example.com/wm.png'))->toArray();
        self::assertArrayHasKey('wu', $result);
        self::assertArrayHasKey('wp', $result);
        self::assertArrayHasKey('ws', $result);
        self::assertArrayHasKey('wo', $result);
    }

    public function testToArrayWithoutWatermarkExcludesWatermarkKeys(): void
    {
        $result = (new Options(200))->toArray();
        self::assertArrayNotHasKey('wu', $result);
        self::assertArrayNotHasKey('wp', $result);
        self::assertArrayNotHasKey('ws', $result);
        self::assertArrayNotHasKey('wo', $result);
    }

    public function testToArrayIncludesSignatureWhenRequested(): void
    {
        $result = (new Options(signature: 'abc123'))->toArray(true);
        self::assertArrayHasKey(Options::SIGNATURE_KEY, $result);
        self::assertSame('abc123', $result[Options::SIGNATURE_KEY]);
    }

    public function testToArrayExcludesSignatureWhenFalse(): void
    {
        $result = (new Options(signature: 'abc123'))->toArray(false);
        self::assertArrayNotHasKey(Options::SIGNATURE_KEY, $result);
    }

    public function testBuildQueryEmpty(): void
    {
        self::assertSame('', (new Options())->buildQuery());
    }

    public function testBuildQueryWithDimensions(): void
    {
        self::assertSame('w=200&h=100', (new Options(200, 100))->buildQuery());
    }

    public function testBuildQueryExcludesSignatureWhenFalse(): void
    {
        $query = (new Options(200, signature: 'sig'))->buildQuery(false);
        self::assertStringNotContainsString('signature', $query);
    }

    public function testHasSignatureReturnsFalseByDefault(): void
    {
        self::assertFalse((new Options())->hasSignature());
    }

    public function testHasSignatureReturnsTrueWhenSet(): void
    {
        self::assertTrue((new Options(signature: 'abc'))->hasSignature());
    }

    public function testSetSignatureReturnsNewImmutableInstance(): void
    {
        $original = new Options(200);
        $new = $original->setSignature('xyz');

        self::assertNotSame($original, $new);
        self::assertSame('xyz', $new->signature);
        self::assertNull($original->signature);
    }

    public function testSetSignaturePreservesOtherValues(): void
    {
        $original = new Options(200, 100, watermarkUrl: 'http://example.com/wm.png');
        $new = $original->setSignature('xyz');

        self::assertSame(200, $new->width);
        self::assertSame(100, $new->height);
        self::assertSame('http://example.com/wm.png', $new->watermarkUrl);
    }

    public function testFromArrayWithShortAliases(): void
    {
        $options = Options::fromArray(['w' => 200, 'h' => 100]);
        self::assertSame(200, $options->width);
        self::assertSame(100, $options->height);
    }

    public function testFromArrayWithLongAliases(): void
    {
        $options = Options::fromArray(['width' => 300, 'height' => 150]);
        self::assertSame(300, $options->width);
        self::assertSame(150, $options->height);
    }

    public function testFromArrayWithWatLegacyAliases(): void
    {
        $options = Options::fromArray(
            [
                'wat_url' => 'http://example.com/wm.png',
                'wat_position' => 'top',
                'wat_scale' => 80,
                'wat_opacity' => 40,
            ]
        );

        self::assertSame('http://example.com/wm.png', $options->watermarkUrl);
        self::assertSame('top', $options->watermarkGravity);
        self::assertSame(80, $options->watermarkScale);
        self::assertSame(40, $options->watermarkOpacity);
    }

    public function testFromArrayWithEmptyArrayUsesDefaults(): void
    {
        $options = Options::fromArray([]);
        self::assertNull($options->width);
        self::assertNull($options->height);
        self::assertNull($options->watermarkUrl);
        self::assertSame('center', $options->watermarkGravity);
        self::assertSame(75, $options->watermarkScale);
        self::assertSame(50, $options->watermarkOpacity);
    }

    public function testFromArrayNormalizesStringDimensionsToInt(): void
    {
        $options = Options::fromArray(['w' => '200', 'h' => '100']);
        self::assertSame(200, $options->width);
        self::assertSame(100, $options->height);
    }

    public function testToArrayIncludesVersionWhenSet(): void
    {
        $result = (new Options(version: '2'))->toArray();
        self::assertArrayHasKey('v', $result);
        self::assertSame('2', $result['v']);
    }

    public function testToArrayExcludesVersionWhenNull(): void
    {
        $result = (new Options())->toArray();
        self::assertArrayNotHasKey('v', $result);
    }

    public function testBuildQueryWithVersion(): void
    {
        self::assertSame('w=200&v=2', (new Options(200, version: '2'))->buildQuery(false));
    }

    public function testFromArrayWithVersionShortAlias(): void
    {
        $options = Options::fromArray(['v' => '2']);
        self::assertSame('2', $options->version);
    }

    public function testFromArrayWithVersionLongAlias(): void
    {
        $options = Options::fromArray(['version' => '2']);
        self::assertSame('2', $options->version);
    }

    public function testSetSignaturePreservesVersion(): void
    {
        $original = new Options(200, version: 'abc');
        $new = $original->setSignature('xyz');

        self::assertSame('abc', $new->version);
        self::assertSame(200, $new->width);
    }
}
