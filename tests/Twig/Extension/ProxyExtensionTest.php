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

namespace BaBeuloula\CdnPhpBundle\Tests\Twig\Extension;

use BaBeuloula\CdnPhpBundle\Signer;
use BaBeuloula\CdnPhpBundle\Twig\Extension\ProxyExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\TwigFunction;

final class ProxyExtensionTest extends TestCase
{
    private const ROUTE_NAME = 'app_cdn';
    private const ROUTE_PARAMETER = 'path';
    private const GENERATED_URL = 'http://example.com/cdn/image.jpg';

    /** @var RouterInterface&MockObject */
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('generate')->willReturn(self::GENERATED_URL);
    }

    private function makeExtension(bool $encryptedParameters = false, ?Signer $signer = null): ProxyExtension
    {
        return new ProxyExtension(
            $this->router,
            self::ROUTE_NAME,
            self::ROUTE_PARAMETER,
            $signer ?? new Signer(),
            $encryptedParameters,
        );
    }

    public function testGetFunctionsReturnsTwoFunctions(): void
    {
        $functions = $this->makeExtension()->getFunctions();

        self::assertCount(2, $functions);
        self::assertContainsOnlyInstancesOf(TwigFunction::class, $functions);

        $names = array_map(static fn (TwigFunction $f) => $f->getName(), $functions);
        self::assertContains('cdn_php', $names);
        self::assertContains('cdn', $names);
    }

    public function testCdnPhpWithNoOptionsAndEncryptionDisabledReturnsUrl(): void
    {
        $url = $this->makeExtension()->cdnPhp('image.jpg');

        self::assertSame(self::GENERATED_URL, $url);
    }

    public function testCdnPhpWithOptionsAppendsQueryString(): void
    {
        $url = $this->makeExtension()->cdnPhp('image.jpg', ['w' => 200]);

        self::assertStringContainsString('?', $url);
        self::assertStringContainsString('w=200', $url);
    }

    public function testCdnPhpWithEmptyQueryNoTrailingQuestionMark(): void
    {
        $url = $this->makeExtension()->cdnPhp('image.jpg', []);

        self::assertStringEndsNotWith('?', $url);
    }

    public function testCdnPhpStripsLeadingSlashFromFile(): void
    {
        $this->router->expects(self::once())
            ->method('generate')
            ->with(
                self::ROUTE_NAME,
                [self::ROUTE_PARAMETER => 'image.jpg'],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            ->willReturn(self::GENERATED_URL);

        $this->makeExtension()->cdnPhp('/image.jpg');
    }

    public function testCdnPhpCallsRouterWithAbsoluteUrl(): void
    {
        $this->router->expects(self::once())
            ->method('generate')
            ->with(
                self::anything(),
                self::anything(),
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            ->willReturn(self::GENERATED_URL);

        $this->makeExtension()->cdnPhp('image.jpg');
    }

    public function testCdnPhpWithEncryptionEnabledAddsSignature(): void
    {
        $url = $this->makeExtension(
            encryptedParameters: true,
            signer: new Signer('test-secret'),
        )->cdnPhp('image.jpg', ['w' => 200]);

        self::assertStringContainsString('signature=', $url);
    }

    public function testCdnPhpWithEncryptionDisabledNoSignature(): void
    {
        $url = $this->makeExtension(
            encryptedParameters: false,
            signer: new Signer('test-secret'),
        )->cdnPhp('image.jpg', ['w' => 200]);

        self::assertStringNotContainsString('signature=', $url);
    }

    public function testExplicitEnableEncrypterTrueOverridesDefaultFalse(): void
    {
        $url = $this->makeExtension(
            encryptedParameters: false,
            signer: new Signer('test-secret'),
        )->cdnPhp('image.jpg', ['w' => 200], true);

        self::assertStringContainsString('signature=', $url);
    }

    public function testExplicitEnableEncrypterFalseOverridesDefaultTrue(): void
    {
        $url = $this->makeExtension(
            encryptedParameters: true,
            signer: new Signer('test-secret'),
        )->cdnPhp('image.jpg', ['w' => 200], false);

        self::assertStringNotContainsString('signature=', $url);
    }

    public function testCdnPhpWithEncryptionAndEmptyQueryAppendsNothing(): void
    {
        // Disabled signer (null key) returns empty query for options with no values
        $url = $this->makeExtension(encryptedParameters: true)->cdnPhp('image.jpg');

        self::assertStringEndsNotWith('?', $url);
    }
}
