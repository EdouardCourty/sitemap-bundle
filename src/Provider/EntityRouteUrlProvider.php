<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Ecourty\SitemapBundle\Contract\UrlProviderInterface;
use Ecourty\SitemapBundle\Exception\InvalidConfigurationException;
use Ecourty\SitemapBundle\Model\EntityRouteConfig;
use Ecourty\SitemapBundle\Model\SitemapUrl;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EntityRouteUrlProvider implements UrlProviderInterface
{
    public function __construct(
        private readonly EntityRouteConfig $config,
        private readonly ManagerRegistry $doctrine,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly ContainerInterface $container,
        private readonly string $baseUrl,
    ) {
    }

    public function getUrls(): iterable
    {
        /** @var class-string $entityClass */
        $entityClass = $this->config->entity;
        $em = $this->doctrine->getManagerForClass($entityClass);

        if (!$em instanceof EntityManagerInterface) {
            throw new InvalidConfigurationException(
                \sprintf('Entity "%s" is not a valid Doctrine entity', $this->config->entity),
            );
        }

        $repository = $em->getRepository($entityClass);
        $entities = $this->fetchEntities($em, $repository);

        foreach ($entities as $entity) {
            $routeParams = [];

            foreach ($this->config->routeParams as $paramName => $propertyPath) {
                if (!$this->propertyAccessor->isReadable($entity, $propertyPath)) {
                    throw new InvalidConfigurationException(
                        \sprintf('Property "%s" does not exist or is not readable on entity "%s"', $propertyPath, $this->config->entity),
                    );
                }

                $routeParams[$paramName] = $this->propertyAccessor->getValue($entity, $propertyPath);
            }

            try {
                $path = $this->urlGenerator->generate(
                    $this->config->route,
                    $routeParams,
                    UrlGeneratorInterface::ABSOLUTE_PATH,
                );
            } catch (RouteNotFoundException $e) {
                throw new InvalidConfigurationException(
                    \sprintf('Route "%s" does not exist in routing configuration', $this->config->route),
                    0,
                    $e,
                );
            }

            $url = \rtrim($this->baseUrl, '/') . $path;

            $lastmod = null;
            if ($this->config->lastmodProperty !== null) {
                if (!$this->propertyAccessor->isReadable($entity, $this->config->lastmodProperty)) {
                    throw new InvalidConfigurationException(
                        \sprintf('Property "%s" does not exist or is not readable on entity "%s"', $this->config->lastmodProperty, $this->config->entity),
                    );
                }

                $value = $this->propertyAccessor->getValue($entity, $this->config->lastmodProperty);

                if ($value !== null && !$value instanceof \DateTimeInterface) {
                    throw new InvalidConfigurationException(
                        \sprintf('Property "%s" on entity "%s" must be an instance of DateTimeInterface', $this->config->lastmodProperty, $this->config->entity),
                    );
                }

                $lastmod = $value;
            }

            yield new SitemapUrl(
                loc: $url,
                priority: $this->config->priority,
                changefreq: $this->config->changefreq,
                lastmod: $lastmod,
            );
        }
    }

    public function getSourceName(): string
    {
        $parts = \explode('\\', $this->config->entity);
        $entityName = \end($parts);

        return 'entity_' . \strtolower($entityName);
    }

    public function count(): int
    {
        /** @var class-string $entityClass */
        $entityClass = $this->config->entity;
        $em = $this->doctrine->getManagerForClass($entityClass);

        if (!$em instanceof EntityManagerInterface) {
            throw new InvalidConfigurationException(
                \sprintf('Entity "%s" is not a valid Doctrine entity', $this->config->entity),
            );
        }

        $repository = $em->getRepository($entityClass);
        $qb = $this->getQueryBuilder($em, $repository);

        // Clone the query builder and modify it for counting
        $countQb = clone $qb;

        // Get the root alias from the query builder
        $rootAliases = $countQb->getRootAliases();
        if (empty($rootAliases)) {
            throw new InvalidConfigurationException(
                \sprintf('QueryBuilder for entity "%s" has no root alias', $this->config->entity),
            );
        }
        $alias = $rootAliases[0];

        $countQb->select(\sprintf('COUNT(%s.id)', $alias))
            ->resetDQLPart('orderBy');

        return (int) $countQb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get the base QueryBuilder for this entity provider.
     * If query_builder_method is configured, it can be:
     * - A method name (calls it on the entity's repository)
     * - A FQCN::method (calls it on the specified service)
     */
    private function getQueryBuilder(EntityManagerInterface $em, object $repository): QueryBuilder
    {
        if ($this->config->queryBuilderMethod !== null) {
            // Check if it's a FQCN::method format
            if (\str_contains($this->config->queryBuilderMethod, '::')) {
                [$serviceClass, $method] = \explode('::', $this->config->queryBuilderMethod, 2);

                if (!$this->container->has($serviceClass)) {
                    throw new InvalidConfigurationException(
                        \sprintf('Service "%s" does not exist in the container', $serviceClass),
                    );
                }

                $service = $this->container->get($serviceClass);

                if (!\method_exists($service, $method)) {
                    throw new InvalidConfigurationException(
                        \sprintf('Method "%s" does not exist on service "%s"', $method, $serviceClass),
                    );
                }

                $result = $service->{$method}();

                if (!$result instanceof QueryBuilder) {
                    throw new InvalidConfigurationException(
                        \sprintf('Method "%s::%s" must return a Doctrine\ORM\QueryBuilder instance', $serviceClass, $method),
                    );
                }

                return $result;
            }

            // Otherwise, it's a method on the default repository
            if (!\method_exists($repository, $this->config->queryBuilderMethod)) {
                throw new InvalidConfigurationException(
                    \sprintf('Method "%s" does not exist on repository for entity "%s"', $this->config->queryBuilderMethod, $this->config->entity),
                );
            }

            $result = $repository->{$this->config->queryBuilderMethod}();

            if (!$result instanceof QueryBuilder) {
                throw new InvalidConfigurationException(
                    \sprintf('Repository method "%s" must return a Doctrine\ORM\QueryBuilder instance', $this->config->queryBuilderMethod),
                );
            }

            return $result;
        }

        // Build default query builder
        $qb = $em->createQueryBuilder();
        $qb->select('e')->from($this->config->entity, 'e');

        if (\count($this->config->conditions) > 0) {
            $qb->where(\implode(' AND ', $this->config->conditions));
        }

        return $qb;
    }

    /**
     * @return iterable<object>
     */
    private function fetchEntities(EntityManagerInterface $em, object $repository): iterable
    {
        $qb = $this->getQueryBuilder($em, $repository);

        /** @var iterable<object> */
        return $qb->getQuery()->toIterable();
    }
}
