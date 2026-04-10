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

use BaBeuloula\CdnPhpBundle\AbstractHandler;
use BaBeuloula\CdnPhpBundle\Options;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AbstractHandlerTest extends TestCase
{
    private function makeHandler(string $assetsPath): AbstractHandler
    {
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        return new class ($assetsPath) extends AbstractHandler {
            // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            public function response(string $file, ?Options $options = null, array $headers = []): Response
            {
                return new Response();
            }
        };
    }

    private function getAssetsPath(AbstractHandler $handler): string
    {
        /** @var string $value */
        $value = (new \ReflectionProperty(AbstractHandler::class, 'assetsPath'))->getValue($handler);

        return $value;
    }

    private function normalizeFile(AbstractHandler $handler, string $file): string
    {
        /** @var string $value */
        $value = (new \ReflectionMethod(AbstractHandler::class, 'normalizeFile'))->invoke($handler, $file);

        return $value;
    }

    public function testAssetsPathAddsTrailingSlash(): void
    {
        self::assertSame('/assets/', $this->getAssetsPath($this->makeHandler('/assets')));
    }

    public function testAssetsPathStripsExtraTrailingSlashes(): void
    {
        self::assertSame('/assets/', $this->getAssetsPath($this->makeHandler('/assets///')));
    }

    public function testAssetsPathAlreadyWithTrailingSlash(): void
    {
        self::assertSame('/assets/', $this->getAssetsPath($this->makeHandler('/assets/')));
    }

    public function testNormalizeFileStripsLeadingSlash(): void
    {
        self::assertSame('images/foo.jpg', $this->normalizeFile($this->makeHandler('/assets/'), '/images/foo.jpg'));
    }

    public function testNormalizeFileWithNoLeadingSlash(): void
    {
        self::assertSame('images/foo.jpg', $this->normalizeFile($this->makeHandler('/assets/'), 'images/foo.jpg'));
    }

    public function testParseHeadersExtractsKnownHeaders(): void
    {
        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'image/webp,image/*',
                'HTTP_USER_AGENT' => 'Mozilla/5.0',
                'HTTP_ACCEPT_LANGUAGE' => 'fr-FR',
            ]
        );

        $headers = $this->makeHandler('/assets/')->parseHeaders($request);

        self::assertArrayHasKey('accept', $headers);
        self::assertArrayHasKey('user-agent', $headers);
        self::assertArrayHasKey('accept-language', $headers);
        self::assertSame('image/webp,image/*', $headers['accept']);
        self::assertSame('Mozilla/5.0', $headers['user-agent']);
        self::assertSame('fr-FR', $headers['accept-language']);
    }

    public function testParseHeadersIgnoresUnknownHeaders(): void
    {
        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_X_CUSTOM' => 'value',
                'HTTP_AUTHORIZATION' => 'Bearer token',
            ]
        );

        $headers = $this->makeHandler('/assets/')->parseHeaders($request);

        self::assertArrayNotHasKey('x-custom', $headers);
        self::assertArrayNotHasKey('authorization', $headers);
    }

    public function testParseHeadersOmitsAbsentHeaders(): void
    {
        // Use new Request() directly to avoid Request::create() defaults (HTTP_ACCEPT, HTTP_ACCEPT_LANGUAGE)
        $request = new Request([], [], [], [], [], ['HTTP_USER_AGENT' => 'Mozilla/5.0']);

        $headers = $this->makeHandler('/assets/')->parseHeaders($request);

        self::assertArrayHasKey('user-agent', $headers);
        self::assertArrayNotHasKey('accept', $headers);
        self::assertArrayNotHasKey('accept-language', $headers);
    }
}
