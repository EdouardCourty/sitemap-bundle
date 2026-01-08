<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sitemap');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('base_url')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->enumNode('use_index')
                    ->values(['auto', true, false])
                    ->defaultValue('auto')
                ->end()
                ->integerNode('index_threshold')
                    ->defaultValue(50000)
                    ->min(1)
                ->end()
                ->arrayNode('static_routes')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('route')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->floatNode('priority')
                                ->defaultValue(0.5)
                                ->min(0.0)
                                ->max(1.0)
                            ->end()
                            ->enumNode('changefreq')
                                ->values(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])
                                ->defaultValue('weekly')
                            ->end()
                            ->scalarNode('lastmod')
                                ->defaultNull()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('entity_routes')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('entity')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('route')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('route_params')
                                ->isRequired()
                                ->useAttributeAsKey('name')
                                ->scalarPrototype()->end()
                            ->end()
                            ->floatNode('priority')
                                ->defaultValue(0.5)
                                ->min(0.0)
                                ->max(1.0)
                            ->end()
                            ->enumNode('changefreq')
                                ->values(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])
                                ->defaultValue('weekly')
                            ->end()
                            ->scalarNode('lastmod_property')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('query_builder_method')
                                ->defaultNull()
                            ->end()
                            ->arrayNode('conditions')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                        ->validate()
                            ->ifTrue(static function (mixed $v): bool {
                                if (!\is_array($v)) {
                                    return false;
                                }
                                $queryBuilder = $v['query_builder_method'] ?? null;
                                $conditions = $v['conditions'] ?? [];
                                return $queryBuilder !== null && \count($conditions) > 0;
                            })
                            ->thenInvalid('Cannot use both query_builder_method and conditions')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
