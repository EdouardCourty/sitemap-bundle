<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\DependencyInjection;

use Ecourty\SitemapBundle\Contract\UrlProviderInterface;
use Ecourty\SitemapBundle\Enum\ChangeFrequency;
use Ecourty\SitemapBundle\Model\EntityRouteConfig;
use Ecourty\SitemapBundle\Model\StaticRouteConfig;
use Ecourty\SitemapBundle\Provider\EntityRouteUrlProvider;
use Ecourty\SitemapBundle\Provider\StaticRouteUrlProvider;
use Ecourty\SitemapBundle\Service\SitemapGenerator;
use Ecourty\SitemapBundle\Service\SitemapIndexWriter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class SitemapExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->registerForAutoconfiguration(UrlProviderInterface::class)
            ->addTag('sitemap.url_provider');

        $container->setParameter('sitemap.base_url', $config['base_url']);
        $container->setParameter('sitemap.use_index', $config['use_index']);
        $container->setParameter('sitemap.index_threshold', $config['index_threshold']);

        $this->registerStaticRouteProvider($container, $config['static_routes'], $config['base_url']);
        $this->registerEntityRouteProviders($container, $config['entity_routes'], $config['base_url']);

        $container->getDefinition(SitemapIndexWriter::class)
            ->setArgument('$baseUrl', $config['base_url']);

        $container->getDefinition(SitemapGenerator::class)
            ->setArgument('$useIndex', $config['use_index'])
            ->setArgument('$indexThreshold', $config['index_threshold']);
    }

    /**
     * @param array<array<string, mixed>> $staticRoutes
     */
    private function registerStaticRouteProvider(ContainerBuilder $container, array $staticRoutes, string $baseUrl): void
    {
        if (empty($staticRoutes)) {
            return;
        }

        $configs = [];
        foreach ($staticRoutes as $route) {
            $changefreq = $route['changefreq'];
            \assert(\is_string($changefreq));
            $configs[] = new Definition(StaticRouteConfig::class, [
                '$route' => $route['route'],
                '$priority' => $route['priority'],
                '$changefreq' => ChangeFrequency::from($changefreq),
                '$lastmodRelative' => $route['lastmod'] ?? null,
            ]);
        }

        $provider = new Definition(StaticRouteUrlProvider::class, [
            '$configs' => $configs,
            '$urlGenerator' => new Reference('router'),
            '$baseUrl' => $baseUrl,
        ]);
        $provider->addTag('sitemap.url_provider');

        $container->setDefinition('sitemap.provider.static', $provider);
    }

    /**
     * @param array<array<string, mixed>> $entityRoutes
     */
    private function registerEntityRouteProviders(ContainerBuilder $container, array $entityRoutes, string $baseUrl): void
    {
        foreach ($entityRoutes as $index => $route) {
            $changefreq = $route['changefreq'];
            \assert(\is_string($changefreq));
            $config = new Definition(EntityRouteConfig::class, [
                '$entity' => $route['entity'],
                '$route' => $route['route'],
                '$routeParams' => $route['route_params'],
                '$priority' => $route['priority'],
                '$changefreq' => ChangeFrequency::from($changefreq),
                '$lastmodProperty' => $route['lastmod_property'] ?? null,
                '$queryBuilderMethod' => $route['query_builder_method'] ?? null,
                '$conditions' => $route['conditions'] ?? null,
            ]);

            $provider = new Definition(EntityRouteUrlProvider::class, [
                '$config' => $config,
                '$doctrine' => new Reference('doctrine'),
                '$urlGenerator' => new Reference('router'),
                '$propertyAccessor' => new Reference('property_accessor'),
                '$container' => new Reference('service_container'),
                '$baseUrl' => $baseUrl,
            ]);
            $provider->addTag('sitemap.url_provider');

            $container->setDefinition(\sprintf('sitemap.provider.entity_%d', $index), $provider);
        }
    }

    public function getAlias(): string
    {
        return 'sitemap';
    }
}
