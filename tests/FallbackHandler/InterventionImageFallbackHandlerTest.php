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

namespace BaBeuloula\CdnPhpBundle\Tests\FallbackHandler;

use BaBeuloula\CdnPhpBundle\Exception\FileNotFoundException;
use BaBeuloula\CdnPhpBundle\FallbackHandler\InterventionImageFallbackHandler;
use BaBeuloula\CdnPhpBundle\Options;
use Intervention\Image\Interfaces\DriverInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;

final class InterventionImageFallbackHandlerTest extends TestCase
{
    private const FIXTURES_PATH = __DIR__ . '/../Fixtures/';

    /** @param array<string, mixed> $headers */
    private function makeCacheCapturingKey(array $headers, ?Options $options = null): string
    {
        /** @var DriverInterface&MockObject $driver */
        $driver = $this->createMock(DriverInterface::class);
        /** @var CacheInterface&MockObject $cache */
        $cache = $this->createMock(CacheInterface::class);

        $capturedKey = '';
        $cache->method('get')
            ->willReturnCallback(
                static function (string $key) use (&$capturedKey): array {
                    $capturedKey = $key;

                    return ['content' => '', 'mimetype' => 'image/png'];
                }
            );

        $handler = new InterventionImageFallbackHandler($driver, self::FIXTURES_PATH, $cache);
        $handler->response('test.png', $options, $headers);

        return $capturedKey;
    }

    public function testFormatKeyIsAvifWhenAcceptContainsAvif(): void
    {
        $key = $this->makeCacheCapturingKey(['accept' => 'image/avif,image/webp']);
        self::assertStringEndsWith('.avif', $key);
    }

    public function testFormatKeyIsWebpWhenAcceptContainsWebp(): void
    {
        $key = $this->makeCacheCapturingKey(['accept' => 'image/webp']);
        self::assertStringEndsWith('.webp', $key);
    }

    public function testFormatKeyIsOriginalWhenNoAcceptHeader(): void
    {
        $key = $this->makeCacheCapturingKey([]);
        self::assertStringEndsWith('.original', $key);
    }

    public function testFormatKeyIsAvifOverWebpWhenBothAccepted(): void
    {
        $key = $this->makeCacheCapturingKey(['accept' => 'image/avif,image/webp,image/*']);
        self::assertStringEndsWith('.avif', $key);
    }

    public function testCacheKeyIncludesFile(): void
    {
        $key = $this->makeCacheCapturingKey([]);
        self::assertStringContainsString('test', $key);
    }

    public function testCacheKeyDiffersForDifferentOptions(): void
    {
        $keyA = $this->makeCacheCapturingKey([], new Options(200));
        $keyB = $this->makeCacheCapturingKey([], new Options(300));

        self::assertNotSame($keyA, $keyB);
    }

    public function testCacheKeyDiffersForDifferentFormats(): void
    {
        $keyAvif = $this->makeCacheCapturingKey(['accept' => 'image/avif']);
        $keyWebp = $this->makeCacheCapturingKey(['accept' => 'image/webp']);
        $keyOrig = $this->makeCacheCapturingKey([]);

        self::assertNotSame($keyAvif, $keyWebp);
        self::assertNotSame($keyAvif, $keyOrig);
        self::assertNotSame($keyWebp, $keyOrig);
    }

    #[RequiresPhpExtension('gd')]
    public function testCacheMissProcessesImageAndReturnsResponse(): void
    {
        $driver = new \Intervention\Image\Drivers\Gd\Driver();
        $handler = new InterventionImageFallbackHandler($driver, self::FIXTURES_PATH, new ArrayAdapter());

        $response = $handler->response('test.png');

        self::assertSame(200, $response->getStatusCode());
        self::assertNotEmpty($response->getContent());
    }

    #[RequiresPhpExtension('gd')]
    public function testCacheHitSkipsProcessingAndReturnsResponse(): void
    {
        $driver = new \Intervention\Image\Drivers\Gd\Driver();
        $cache = new ArrayAdapter();
        $handler = new InterventionImageFallbackHandler($driver, self::FIXTURES_PATH, $cache);

        // First call populates cache
        $handler->response('test.png');

        // Second call should hit the cache (same result, no re-processing)
        $response = $handler->response('test.png');
        self::assertSame(200, $response->getStatusCode());
    }

    #[RequiresPhpExtension('gd')]
    public function testResponseSetsCorrectContentType(): void
    {
        $driver = new \Intervention\Image\Drivers\Gd\Driver();
        $handler = new InterventionImageFallbackHandler($driver, self::FIXTURES_PATH, new ArrayAdapter());

        $response = $handler->response('test.png');

        self::assertNotEmpty($response->headers->get('Content-Type'));
    }

    #[RequiresPhpExtension('gd')]
    public function testResponseContentTypeReplacesPassedHeaders(): void
    {
        $driver = new \Intervention\Image\Drivers\Gd\Driver();
        $handler = new InterventionImageFallbackHandler($driver, self::FIXTURES_PATH, new ArrayAdapter());

        $response = $handler->response('test.png', null, ['content-type' => 'wrong/type']);
        $contentType = $response->headers->get('Content-Type');

        self::assertNotNull($contentType);
        self::assertNotSame('wrong/type', $contentType);
    }

    #[RequiresPhpExtension('gd')]
    public function testDecoderExceptionThrowsFileNotFoundException(): void
    {
        $driver = new \Intervention\Image\Drivers\Gd\Driver();
        $handler = new InterventionImageFallbackHandler($driver, self::FIXTURES_PATH, new ArrayAdapter());

        $this->expectException(FileNotFoundException::class);
        $handler->response('nonexistent-file-that-does-not-exist.jpg');
    }

    #[RequiresPhpExtension('gd')]
    public function testBothDimensionsUsesCover(): void
    {
        $driver = new \Intervention\Image\Drivers\Gd\Driver();
        $handler = new InterventionImageFallbackHandler($driver, self::FIXTURES_PATH, new ArrayAdapter());

        $response = $handler->response('test.png', new Options(5, 5));
        self::assertSame(200, $response->getStatusCode());
    }

    #[RequiresPhpExtension('gd')]
    public function testWidthOnlyScalesImage(): void
    {
        $driver = new \Intervention\Image\Drivers\Gd\Driver();
        $handler = new InterventionImageFallbackHandler($driver, self::FIXTURES_PATH, new ArrayAdapter());

        $response = $handler->response('test.png', new Options(5));
        self::assertSame(200, $response->getStatusCode());
    }

    #[RequiresPhpExtension('gd')]
    public function testHeightOnlyScalesImage(): void
    {
        $driver = new \Intervention\Image\Drivers\Gd\Driver();
        $handler = new InterventionImageFallbackHandler($driver, self::FIXTURES_PATH, new ArrayAdapter());

        $response = $handler->response('test.png', new Options(null, 5));
        self::assertSame(200, $response->getStatusCode());
    }

    #[RequiresPhpExtension('gd')]
    public function testWebpEncodingWhenAccepted(): void
    {
        $driver = new \Intervention\Image\Drivers\Gd\Driver();
        $handler = new InterventionImageFallbackHandler($driver, self::FIXTURES_PATH, new ArrayAdapter());

        $response = $handler->response('test.png', null, ['accept' => 'image/webp']);
        self::assertSame('image/webp', $response->headers->get('Content-Type'));
    }
}
