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

namespace BaBeuloula\CdnPhpBundle\Twig\Extension;

use BaBeuloula\CdnPhpBundle\Options;
use BaBeuloula\CdnPhpBundle\Signer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ProxyExtension extends AbstractExtension
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly string $routeName,
        private readonly string $routeParameter,
        private readonly Signer $encrypter,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cdn_php', $this->cdnPhp(...)),
            new TwigFunction('cdn', $this->cdnPhp(...)),
        ];
    }

    /** @param array<string, mixed> $options */
    public function cdnPhp(string $file, array $options = [], bool $enableEncrypter = true): string
    {
        $options = Options::fromArray($options);
        $queryParams = (true === $enableEncrypter)
            ? '?' . $this->encrypter->sign($options)
            : '?' . $options->buildQuery()
        ;

        return $this->router->generate(
            $this->routeName,
            [$this->routeParameter => $file],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ) . $queryParams;
    }
}
