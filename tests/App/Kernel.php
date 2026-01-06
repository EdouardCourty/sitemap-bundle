<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Ecourty\SitemapBundle\SitemapBundle;
use Ecourty\SitemapBundle\Tests\Fixtures\Repository\SongRepository;
use Ecourty\SitemapBundle\Tests\Fixtures\Service\ArticleSitemapService;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new SitemapBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/test';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'test-secret-key-for-sitemap-bundle',
            'test' => true,
            'router' => ['utf8' => true],
            'property_access' => true,
            'http_method_override' => false,
        ]);

        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'url' => 'sqlite:///:memory:',
                'charset' => 'utf8',
            ],
            'orm' => [
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'auto_mapping' => true,
                'mappings' => [
                    'Test' => [
                        'type' => 'attribute',
                        'dir' => '%kernel.project_dir%/tests/Fixtures/Entity',
                        'prefix' => 'Ecourty\\SitemapBundle\\Tests\\Fixtures\\Entity',
                        'alias' => 'Test',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        // Register test repository
        $container->register(SongRepository::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
            ->addTag('doctrine.repository_service');

        // Register custom sitemap service for testing FQCN::method
        $container->register(ArticleSitemapService::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
            ->setPublic(true);

        $loader->load($this->getProjectDir() . '/tests/app/config/sitemap.yaml');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import($this->getProjectDir() . '/tests/app/config/routes.yaml');
    }
}
