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

namespace BaBeuloula\CdnPhpBundle\Tests\Exception;

use BaBeuloula\CdnPhpBundle\Exception\FileNotFoundException;
use PHPUnit\Framework\TestCase;

final class FileNotFoundExceptionTest extends TestCase
{
    public function testIsInstanceOfException(): void
    {
        self::assertInstanceOf(\Exception::class, new FileNotFoundException());
    }

    public function testAcceptsMessage(): void
    {
        $exception = new FileNotFoundException('file not found');
        self::assertSame('file not found', $exception->getMessage());
    }

    public function testAcceptsPreviousException(): void
    {
        $previous = new \RuntimeException('original');
        $exception = new FileNotFoundException('', 0, $previous);
        self::assertSame($previous, $exception->getPrevious());
    }
}
