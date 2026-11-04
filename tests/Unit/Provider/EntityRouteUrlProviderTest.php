<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Unit\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Ecourty\SitemapBundle\Enum\ChangeFrequency;
use Ecourty\SitemapBundle\Exception\InvalidConfigurationException;
use Ecourty\SitemapBundle\Model\EntityRouteConfig;
use Ecourty\SitemapBundle\Provider\EntityRouteUrlProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EntityRouteUrlProviderTest extends TestCase
{
    public function testNonDoctrineEntityThrowsException(): void
    {
        $config = new EntityRouteConfig(
            entity: 'App\\Entity\\NonExistent',
            route: 'entity_show',
            routeParams: ['id' => 'id'],
            priority: 0.5,
            changefreq: ChangeFrequency::WEEKLY,
        );

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->once())
            ->method('getManagerForClass')
            ->with('App\\Entity\\NonExistent')
            ->willReturn(null);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $urlGenerator,
            $propertyAccessor,
            $container,
            'https://example.com',
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Entity "App\Entity\NonExistent" is not a valid Doctrine entity');

        \iterator_to_array($provider->getUrls());
    }

    public function testRouteParamPropertyNotReadableThrowsException(): void
    {
        $config = new EntityRouteConfig(
            entity: 'App\\Entity\\Product',
            route: 'product_show',
            routeParams: ['slug' => 'nonExistentProperty'],
            priority: 0.5,
            changefreq: ChangeFrequency::WEEKLY,
        );

        $query = $this->createMock(Query::class);
        [$em, $repository, $qb] = $this->createDoctrineStack($query);

        $entity = new \stdClass();
        $entity->id = 1;

        $query->expects($this->once())
            ->method('toIterable')
            ->willReturn([$entity]);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $propertyAccessor = $this->createMock(PropertyAccessorInterface::class);
        $propertyAccessor->expects($this->once())
            ->method('isReadable')
            ->with($entity, 'nonExistentProperty')
            ->willReturn(false);

        $container = $this->createStub(ContainerInterface::class);

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $urlGenerator,
            $propertyAccessor,
            $container,
            'https://example.com',
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Property "nonExistentProperty" does not exist or is not readable on entity "App\Entity\Product"');

        \iterator_to_array($provider->getUrls());
    }

    public function testRouteNotFoundThrowsException(): void
    {
        $config = new EntityRouteConfig(
            entity: 'App\\Entity\\Product',
            route: 'non_existent_route',
            routeParams: ['slug' => 'slug'],
            priority: 0.5,
            changefreq: ChangeFrequency::WEEKLY,
        );

        $query = $this->createStub(Query::class);
        [$em, $repository, $qb] = $this->createDoctrineStack($query);

        $entity = new \stdClass();
        $entity->slug = 'test-slug';

        $query->method('toIterable')
            ->willReturn([$entity]);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willThrowException(new RouteNotFoundException());

        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $propertyAccessor->method('isReadable')->willReturn(true);
        $propertyAccessor->method('getValue')->willReturnMap([[$entity, 'slug', 'test-slug']]);

        $container = $this->createStub(ContainerInterface::class);

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $urlGenerator,
            $propertyAccessor,
            $container,
            'https://example.com',
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Route "non_existent_route" does not exist in routing configuration');

        \iterator_to_array($provider->getUrls());
    }

    public function testLastmodPropertyNotReadableThrowsException(): void
    {
        $config = new EntityRouteConfig(
            entity: 'App\\Entity\\Product',
            route: 'product_show',
            routeParams: ['slug' => 'slug'],
            priority: 0.5,
            changefreq: ChangeFrequency::WEEKLY,
            lastmodProperty: 'nonExistentDate',
        );

        $query = $this->createMock(Query::class);
        [$em, $repository, $qb] = $this->createDoctrineStack($query);

        $entity = new \stdClass();
        $entity->slug = 'test-slug';

        $query->expects($this->once())
            ->method('toIterable')
            ->willReturn([$entity]);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/product/test-slug');

        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $propertyAccessor->method('isReadable')
            ->willReturnCallback(fn ($obj, $prop) => $prop === 'slug');

        $propertyAccessor->method('getValue')->willReturnMap([[$entity, 'slug', 'test-slug']]);

        $container = $this->createStub(ContainerInterface::class);

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $urlGenerator,
            $propertyAccessor,
            $container,
            'https://example.com',
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Property "nonExistentDate" does not exist or is not readable on entity "App\Entity\Product"');

        \iterator_to_array($provider->getUrls());
    }

    public function testLastmodPropertyNotDateTimeThrowsException(): void
    {
        $config = new EntityRouteConfig(
            entity: 'App\\Entity\\Product',
            route: 'product_show',
            routeParams: ['slug' => 'slug'],
            priority: 0.5,
            changefreq: ChangeFrequency::WEEKLY,
            lastmodProperty: 'createdAt',
        );

        $query = $this->createMock(Query::class);
        [$em, $repository, $qb] = $this->createDoctrineStack($query);

        $entity = new \stdClass();
        $entity->slug = 'test-slug';
        $entity->createdAt = 'not-a-datetime';

        $query->expects($this->once())
            ->method('toIterable')
            ->willReturn([$entity]);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/product/test-slug');

        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $propertyAccessor->method('isReadable')->willReturn(true);
        $propertyAccessor->method('getValue')
            ->willReturnCallback(fn ($obj, $prop) => match ($prop) {
                'slug' => 'test-slug',
                'createdAt' => 'not-a-datetime',
                default => null,
            });

        $container = $this->createStub(ContainerInterface::class);

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $urlGenerator,
            $propertyAccessor,
            $container,
            'https://example.com',
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Property "createdAt" on entity "App\Entity\Product" must be an instance of DateTimeInterface');

        \iterator_to_array($provider->getUrls());
    }

    public function testQueryBuilderWithoutRootAliasThrowsException(): void
    {
        $config = new EntityRouteConfig(
            entity: 'App\\Entity\\Product',
            route: 'product_show',
            routeParams: ['slug' => 'slug'],
            priority: 0.5,
            changefreq: ChangeFrequency::WEEKLY,
        );

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('getRootAliases')->willReturn([]);

        $countQb = $this->createStub(QueryBuilder::class);
        $countQb->method('getRootAliases')->willReturn([]);

        $repository = $this->createStub(EntityRepository::class);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('createQueryBuilder')->willReturn($qb);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $urlGenerator,
            $propertyAccessor,
            $container,
            'https://example.com',
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('QueryBuilder for entity "App\Entity\Product" has no root alias');

        $provider->count();
    }

    public function testFqcnMethodServiceNotFoundThrowsException(): void
    {
        $config = new EntityRouteConfig(
            entity: 'App\\Entity\\Product',
            route: 'product_show',
            routeParams: ['slug' => 'slug'],
            priority: 0.5,
            changefreq: ChangeFrequency::WEEKLY,
            queryBuilderMethod: 'App\\Service\\NonExistent::getQueryBuilder',
        );

        $repository = $this->createStub(EntityRepository::class);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('has')
            ->with('App\\Service\\NonExistent')
            ->willReturn(false);

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $urlGenerator,
            $propertyAccessor,
            $container,
            'https://example.com',
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Service "App\Service\NonExistent" does not exist in the container');

        \iterator_to_array($provider->getUrls());
    }

    public function testFqcnMethodNotExistsThrowsException(): void
    {
        $config = new EntityRouteConfig(
            entity: 'App\\Entity\\Product',
            route: 'product_show',
            routeParams: ['slug' => 'slug'],
            priority: 0.5,
            changefreq: ChangeFrequency::WEEKLY,
            queryBuilderMethod: 'App\\Service\\MyService::nonExistentMethod',
        );

        $repository = $this->createStub(EntityRepository::class);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);

        $service = new \stdClass();

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($service);

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $urlGenerator,
            $propertyAccessor,
            $container,
            'https://example.com',
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Method "nonExistentMethod" does not exist on service "App\Service\MyService"');

        \iterator_to_array($provider->getUrls());
    }

    public function testFqcnMethodNotReturningQueryBuilderThrowsException(): void
    {
        $config = new EntityRouteConfig(
            entity: 'App\\Entity\\Product',
            route: 'product_show',
            routeParams: ['slug' => 'slug'],
            priority: 0.5,
            changefreq: ChangeFrequency::WEEKLY,
            queryBuilderMethod: 'App\\Service\\MyService::getBadQueryBuilder',
        );

        $repository = $this->createStub(EntityRepository::class);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);

        $service = new class () {
            public function getBadQueryBuilder(): string
            {
                return 'not-a-query-builder';
            }
        };

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($service);

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $urlGenerator,
            $propertyAccessor,
            $container,
            'https://example.com',
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Method "App\Service\MyService::getBadQueryBuilder" must return a Doctrine\ORM\QueryBuilder instance');

        \iterator_to_array($provider->getUrls());
    }

    public function testRepositoryMethodNotExistsThrowsException(): void
    {
        $config = new EntityRouteConfig(
            entity: 'App\\Entity\\Product',
            route: 'product_show',
            routeParams: ['slug' => 'slug'],
            priority: 0.5,
            changefreq: ChangeFrequency::WEEKLY,
            queryBuilderMethod: 'nonExistentMethod',
        );

        $repository = $this->createStub(EntityRepository::class);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $urlGenerator,
            $propertyAccessor,
            $container,
            'https://example.com',
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Method "nonExistentMethod" does not exist on repository for entity "App\Entity\Product"');

        \iterator_to_array($provider->getUrls());
    }

    public function testRepositoryMethodNotReturningQueryBuilderThrowsException(): void
    {
        $config = new EntityRouteConfig(
            entity: 'App\\Entity\\Product',
            route: 'product_show',
            routeParams: ['slug' => 'slug'],
            priority: 0.5,
            changefreq: ChangeFrequency::WEEKLY,
            queryBuilderMethod: 'getBadQueryBuilder',
        );

        $repository = new class () extends EntityRepository {
            public function __construct()
            {
            }

            /**
             * @return array<empty, empty>
             */
            public function getBadQueryBuilder(): array
            {
                return [];
            }
        };

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($em);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $urlGenerator,
            $propertyAccessor,
            $container,
            'https://example.com',
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Repository method "getBadQueryBuilder" must return a Doctrine\ORM\QueryBuilder instance');

        \iterator_to_array($provider->getUrls());
    }

    public function testCountForNonDoctrineEntityThrowsException(): void
    {
        $config = new EntityRouteConfig(
            entity: 'App\\Entity\\NonExistent',
            route: 'entity_show',
            routeParams: ['id' => 'id'],
            priority: 0.5,
            changefreq: ChangeFrequency::WEEKLY,
        );

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->once())
            ->method('getManagerForClass')
            ->with('App\\Entity\\NonExistent')
            ->willReturn(null);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $container = $this->createStub(ContainerInterface::class);

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $urlGenerator,
            $propertyAccessor,
            $container,
            'https://example.com',
        );

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Entity "App\Entity\NonExistent" is not a valid Doctrine entity');

        $provider->count();
    }

    /**
     * @param Query<array-key, mixed>|null $query
     *
     * @return array{EntityManagerInterface, EntityRepository<object>, QueryBuilder, Query<array-key, mixed>}
     */
    private function createDoctrineStack(?Query $query = null): array
    {
        $query ??= $this->createStub(Query::class);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository = $this->createStub(EntityRepository::class);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('createQueryBuilder')->willReturn($qb);

        /** @var array{EntityManagerInterface, EntityRepository<object>, QueryBuilder, Query<array-key, mixed>} */
        return [$em, $repository, $qb, $query];
    }
}
