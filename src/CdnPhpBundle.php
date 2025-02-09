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

use BaBeuloula\CdnPhpBundle\Twig\Extension\ProxyExtension;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class CdnPhpBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var ArrayNodeDefinition $treeBuilder */
        $treeBuilder = $definition->rootNode();

        $treeBuilder
            ->children()
                ->arrayNode('proxy')->isRequired()
                    ->children()
                        ->scalarNode('assets_path')->isRequired()->end()
                        ->scalarNode('url')->isRequired()->end()
                        ->booleanNode('check_assets')->defaultTrue()->end()
                        ->booleanNode('encrypted_parameters')->defaultFalse()->end()
                    ->end()
                ->end() // proxy
                ->arrayNode('encrypter')->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('secret_key')->defaultNull()->end()
                    ->end()
                ->end() // encrypter
                ->arrayNode('twig')->isRequired()
                    ->children()
                        ->scalarNode('route_name')->isRequired()->end()
                        ->scalarNode('route_parameter')->isRequired()->end()
                    ->end()
                ->end() // twig
            ->end()
        ;
    }

    /** @param array<string, mixed> $config */
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');

        $container->services()
            ->get(Proxy::class)
                ->public()
                ->arg('$assetsPath', $config['proxy']['assets_path'])
                ->arg('$checkAssets', $config['proxy']['check_assets'])
                ->arg('$cdnPhpUrl', $config['proxy']['url'])
        ;

        $container->services()
            ->get(Signer::class)
                ->public()
                ->arg('$secretKey', $config['encrypter']['secret_key'])
        ;

        $container->services()
            ->get(ProxyExtension::class)
                ->arg('$routeName', $config['twig']['route_name'])
                ->arg('$routeParameter', $config['twig']['route_parameter'])
        ;
    }
}
