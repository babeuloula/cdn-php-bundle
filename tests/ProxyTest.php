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

use BaBeuloula\CdnPhpBundle\Exception\FetchAssetException;
use BaBeuloula\CdnPhpBundle\Exception\FileNotFoundException;
use BaBeuloula\CdnPhpBundle\FallbackHandler\FallbackHandlerInterface;
use BaBeuloula\CdnPhpBundle\Options;
use BaBeuloula\CdnPhpBundle\Proxy;
use BaBeuloula\CdnPhpBundle\Signer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProxyTest extends TestCase
{
    private const CDN_URL = 'http://cdn.example.com/';
    private const ASSETS_PATH = '/var/www/assets/';

    /** @var Filesystem&MockObject */
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = $this->createMock(Filesystem::class);
    }

    private function makeProxy(
        MockHttpClient $client,
        bool $checkAssets = false,
        ?FallbackHandlerInterface $fallback = null,
        ?Signer $signer = null,
    ): Proxy {
        return new Proxy(
            self::ASSETS_PATH,
            $checkAssets,
            $this->filesystem,
            $client,
            self::CDN_URL,
            $signer ?? new Signer(),
            $fallback,
        );
    }

    public function testResponseFetchesCdnAndCopiesWhitelistedHeaders(): void
    {
        $client = new MockHttpClient(
            new MockResponse(
                'img-content',
                [
                    'response_headers' => [
                        'content-type' => ['image/jpeg'],
                        'cache-control' => ['max-age=3600'],
                        'etag' => ['"abc123"'],
                        'last-modified' => ['Mon, 01 Jan 2024 00:00:00 GMT'],
                        'expires' => ['Mon, 01 Jan 2025 00:00:00 GMT'],
                        'content-encoding' => ['identity'],
                        'content-length' => ['11'],
                        'vary' => ['Accept'],
                        'x-content-type-options' => ['nosniff'],
                        'x-dominant-color' => ['#ff0000'],
                        'x-custom-header' => ['custom-value'],
                        'cf-cache-status' => ['HIT'],
                        'cf-ray' => ['abc123-CDG'],
                    ],
                ]
            )
        );

        $response = $this->makeProxy($client)->response('image.jpg');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('img-content', $response->getContent());
        self::assertSame('image/jpeg', $response->headers->get('content-type'));
        self::assertStringContainsString('max-age=3600', (string) $response->headers->get('cache-control'));
        self::assertSame('"abc123"', $response->headers->get('etag'));
        self::assertSame('nosniff', $response->headers->get('x-content-type-options'));
        self::assertSame('#ff0000', $response->headers->get('x-dominant-color'));
        self::assertSame('custom-value', $response->headers->get('x-custom-header'));
        self::assertSame('HIT', $response->headers->get('cf-cache-status'));
        self::assertSame('abc123-CDG', $response->headers->get('cf-ray'));
    }

    public function testResponseSetsNoCacheControlHeader(): void
    {
        $client = new MockHttpClient(new MockResponse('content'));
        $response = $this->makeProxy($client)->response('image.jpg');

        self::assertSame('true', $response->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testResponseIgnoresNonXNonWhitelistedHeaders(): void
    {
        $client = new MockHttpClient(
            new MockResponse(
                'content',
                [
                    'response_headers' => ['server' => ['Apache']],
                ]
            )
        );

        $response = $this->makeProxy($client)->response('image.jpg');

        self::assertFalse($response->headers->has('server'));
    }

    public function testResponseForwardsAllXPrefixedHeaders(): void
    {
        $client = new MockHttpClient(
            new MockResponse(
                'content',
                [
                    'response_headers' => ['x-my-custom' => ['my-value']],
                ]
            )
        );

        $response = $this->makeProxy($client)->response('image.jpg');

        self::assertSame('my-value', $response->headers->get('x-my-custom'));
    }

    public function testResponseForwardsAllCfPrefixedHeaders(): void
    {
        $client = new MockHttpClient(
            new MockResponse(
                'content',
                [
                    'response_headers' => [
                        'cf-cache-status' => ['MISS'],
                        'cf-ray' => ['xyz789-LHR'],
                    ],
                ]
            )
        );

        $response = $this->makeProxy($client)->response('image.jpg');

        self::assertSame('MISS', $response->headers->get('cf-cache-status'));
        self::assertSame('xyz789-LHR', $response->headers->get('cf-ray'));
    }

    public function testResponseNormalizesFileStrippingLeadingSlash(): void
    {
        $requestedUrl = '';
        $client = new MockHttpClient(
            // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            static function (string $method, string $url) use (&$requestedUrl): MockResponse {
                $requestedUrl = $url;

                return new MockResponse('content');
            }
        );

        $this->makeProxy($client)->response('/image.jpg');

        self::assertStringContainsString(self::CDN_URL . 'image.jpg', $requestedUrl);
        self::assertStringNotContainsString('//image.jpg', $requestedUrl);
    }

    public function testCheckAssetsEnabledFileExists(): void
    {
        $this->filesystem->expects(self::once())
            ->method('exists')
            ->willReturn(true);

        $client = new MockHttpClient(new MockResponse('content'));
        $this->makeProxy($client, checkAssets: true)->response('image.jpg');
    }

    public function testCheckAssetsEnabledFileMissingThrowsFileNotFoundException(): void
    {
        $this->filesystem->expects(self::once())
            ->method('exists')
            ->willReturn(false);

        $client = new MockHttpClient(new MockResponse('content'));

        $this->expectException(FileNotFoundException::class);
        $this->makeProxy($client, checkAssets: true)->response('image.jpg');
    }

    public function testCheckAssetsDisabledNeverCallsFilesystem(): void
    {
        $this->filesystem->expects(self::never())->method('exists');

        $client = new MockHttpClient(new MockResponse('content'));
        $this->makeProxy($client, checkAssets: false)->response('image.jpg');
    }

    public function testResponseWithOptionsBuildsQueryString(): void
    {
        $requestedUrl = '';
        $client = new MockHttpClient(
            // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            static function (string $method, string $url) use (&$requestedUrl): MockResponse {
                $requestedUrl = $url;

                return new MockResponse('content');
            }
        );

        $this->makeProxy($client)->response('image.jpg', new Options(200, 100));

        self::assertStringContainsString('w=200&h=100', $requestedUrl);
    }

    public function testResponseWithCdnSigningAppendsExpiresAndSig(): void
    {
        $requestedUrl = '';
        $client = new MockHttpClient(
            // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            static function (string $method, string $url) use (&$requestedUrl): MockResponse {
                $requestedUrl = $url;

                return new MockResponse('content');
            }
        );

        $signer = new Signer(null, 'test-cdn-secret');
        $this->makeProxy($client, signer: $signer)->response('image.jpg');

        self::assertStringContainsString('expires=', $requestedUrl);
        self::assertStringContainsString('sig=', $requestedUrl);
    }

    public function testResponseWithCdnSigningAndOptionsAppendsToExistingQuery(): void
    {
        $requestedUrl = '';
        $client = new MockHttpClient(
            // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            static function (string $method, string $url) use (&$requestedUrl): MockResponse {
                $requestedUrl = $url;

                return new MockResponse('content');
            }
        );

        $signer = new Signer(null, 'test-cdn-secret');
        $this->makeProxy($client, signer: $signer)->response('image.jpg', new Options(200));

        self::assertStringContainsString('w=200', $requestedUrl);
        self::assertStringContainsString('expires=', $requestedUrl);
        self::assertStringContainsString('sig=', $requestedUrl);
    }

    public function testHttpTimeoutIs25(): void
    {
        $capturedOptions = [];
        $client = new MockHttpClient(
            // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            static function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
                $capturedOptions = $options;

                return new MockResponse('content');
            }
        );

        $this->makeProxy($client)->response('image.jpg');

        self::assertSame(25.0, $capturedOptions['timeout']);
    }

    public function testExceptionWithFallbackCallsFallback(): void
    {
        $client = new MockHttpClient(
            static function (): never {
                throw new \RuntimeException('CDN unavailable', 503);
            }
        );

        $fallbackResponse = new Response('fallback-content', 200, ['content-type' => 'image/jpeg']);
        $fallback = $this->createMock(FallbackHandlerInterface::class);
        $fallback->expects(self::once())
            ->method('response')
            ->willReturn($fallbackResponse);

        $response = $this->makeProxy($client, fallback: $fallback)->response('image.jpg');

        self::assertSame('fallback-content', $response->getContent());
    }

    public function testExceptionWithout404ThrowsFetchAssetException(): void
    {
        $client = new MockHttpClient(
            static function (): never {
                throw new \RuntimeException('Server error', 500);
            }
        );

        $this->expectException(FetchAssetException::class);
        $this->makeProxy($client)->response('image.jpg');
    }

    public function testExceptionWith404ThrowsNotFoundHttpException(): void
    {
        $client = new MockHttpClient(
            static function (): never {
                throw new \RuntimeException('Not found', Response::HTTP_NOT_FOUND);
            }
        );

        $this->expectException(NotFoundHttpException::class);
        $this->makeProxy($client)->response('image.jpg');
    }
}
