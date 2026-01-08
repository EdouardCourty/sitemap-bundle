# AGENTS.md - Developer & AI Agent Guide

This document provides essential information for developers and AI coding agents working with the `ecourty/sitemap-bundle`.

## Quick Overview

**Purpose**: Symfony bundle for generating XML sitemaps conforming to sitemap.org protocol.

**Key Features**:
- Static routes support
- Dynamic routes with Doctrine entities
- Sitemap index for large datasets (>50k URLs)
- Memory-efficient streaming
- Flexible YAML configuration

## Architecture

### Core Components

```
Contract/
├── SitemapGeneratorInterface     # Main generator interface
└── UrlProviderInterface          # Provider pattern for URL sources

Service/
├── SitemapGenerator              # Orchestrates sitemap generation
├── XmlWriter                     # Writes simple sitemap XML
├── SitemapIndexWriter            # Writes sitemap index + multiple files
└── UrlProviderRegistry           # Registry pattern for providers

Provider/
├── StaticRouteUrlProvider        # Provides URLs from static routes
└── EntityRouteUrlProvider        # Provides URLs from Doctrine entities

Model/
├── SitemapUrl                    # DTO for sitemap URL entry
├── StaticRouteConfig             # DTO for static route config
└── EntityRouteConfig             # DTO for entity route config

Enum/
└── ChangeFrequency               # Enum for changefreq values
```

### Design Patterns

1. **Registry Pattern**: `UrlProviderRegistry` collects all URL providers via tagged services
2. **Provider Pattern**: Each URL source implements `UrlProviderInterface`
3. **Strategy Pattern**: Index vs single sitemap decision based on configuration
4. **DTO Pattern**: All configuration and data passed as readonly objects

### Data Flow

```
Configuration (YAML)
    ↓
Extension parses & creates DTOs
    ↓
Providers created with DTOs
    ↓
Registry collects all providers
    ↓
Generator orchestrates generation
    ↓
Writer(s) produce XML output
```

## Configuration Schema

### Validated by Symfony Config Component

```yaml
sitemap:
    base_url: string (required)
    use_index: 'auto'|true|false (default: 'auto')
    index_threshold: int (default: 50000)
    
    static_routes:
        - route: string (required)
          priority: float 0.0-1.0 (default: 0.5)
          changefreq: enum (default: 'weekly')
          lastmod: string|null (relative time)
    
    entity_routes:
        - entity: string (required, FQCN)
          route: string (required)
          route_params: array<string, string> (required)
          priority: float 0.0-1.0 (default: 0.5)
          changefreq: enum (default: 'weekly')
          lastmod_property: string|null
          query_builder_method: string|null (method name OR FQCN::method)
          conditions: string|null (DQL WHERE)
```

**Validation Rules**:
- Cannot use both `query_builder_method` and `conditions`
- `route` must exist in Symfony routing
- `query_builder_method` can be:
  - A method name on the entity's repository (e.g., `'getSitemapQueryBuilder'`)
  - A FQCN::method format to call any service (e.g., `'App\Service\SitemapService::getArticlesQueryBuilder'`)
- `entity` must be valid Doctrine entity
- `route_params` properties must exist on entity
- `lastmod_property` must be DateTime/DateTimeImmutable

## XML Output Format

### Simple Sitemap

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://example.com/page</loc>
        <lastmod>2026-01-05</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
</urlset>
```

### Sitemap Index

```xml
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc>https://example.com/sitemap_static.xml</loc>
        <lastmod>2026-01-05T20:30:00+00:00</lastmod>
    </sitemap>
    <sitemap>
        <loc>https://example.com/sitemap_entity_song.xml</loc>
        <lastmod>2026-01-05T20:30:00+00:00</lastmod>
    </sitemap>
</sitemapindex>
```

## Extension Points

### Adding New URL Providers

1. Implement `UrlProviderInterface`
2. Service will be automatically tagged with `sitemap.url_provider` via autoconfiguration
3. Provide unique source name

Example:

```php
class CustomUrlProvider implements UrlProviderInterface
{
    public function getUrls(): iterable
    {
        yield new SitemapUrl(
            loc: 'https://example.com/custom',
            priority: 0.8,
            changefreq: ChangeFrequency::DAILY,
            lastmod: new \DateTime(),
        );
    }

    public function count(): int
    {
        return 1; // Or calculate based on your data source
    }

    public function getSourceName(): string
    {
        return 'custom_source';
    }
}
}
```

### Custom Repository Methods

For complex queries, repository methods **must** return a `QueryBuilder`:

```php
class SongRepository extends ServiceEntityRepository
{
    public function getSitemapQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->where('s.published = true')
            ->orderBy('s.updatedAt', 'DESC');
    }
}
```

**Benefits of returning QueryBuilder:**
1. The bundle can add `COUNT(s.id)` for efficient counting
2. The bundle can optimize the SELECT to fetch only needed fields
3. Uses `toIterable()` for memory-efficient streaming

**Important:** Do NOT call `->getQuery()` or `->toIterable()` in the repository method. Return the `QueryBuilder` directly.

### Custom Services with FQCN::method

You can also use any service, not just the entity's repository:

```php
class ArticleSitemapService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getPublishedArticlesQueryBuilder(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('a')
            ->from(Article::class, 'a')
            ->where('a.status = :published')
            ->setParameter('published', 'published')
            ->orderBy('a.publishedAt', 'DESC');
    }
}
```

Configuration:
```yaml
entity_routes:
    - entity: 'App\Entity\Article'
      query_builder_method: 'App\Service\ArticleSitemapService::getPublishedArticlesQueryBuilder'
      # ... other config
```

## Testing Strategy

### Unit Tests

- Mock Doctrine, Router, PropertyAccessor
- Test each component in isolation
- Cover edge cases (null values, empty arrays, etc.)

### Integration Tests

- Use SQLite in-memory database
- Real Doctrine entities
- Real URL generation

### Edge Cases to Test

- Zero URLs
- Exactly 50,000 URLs (threshold)
- More than 50,000 URLs (splitting)
- Null lastmod/lastmodProperty
- Non-existent routes
- Non-existent entity properties
- Special characters in URLs (XML escaping)
- Both query_builder_method and conditions (should fail)

## Common Issues

### Memory Issues with Large Datasets

**Problem**: Loading 100k entities crashes with out-of-memory

**Solution**: Always use `toIterable()` in repository methods:
```php
$query->toIterable(); // Good - streams results
$query->getResult();  // Bad - loads all in memory
```

### Route Not Found

**Problem**: `RouteNotFoundException` thrown

**Check**:
1. Route name is correct
2. Route exists in `config/routes.yaml`
3. Bundle with route is registered

### Entity Property Not Found

**Problem**: `InvalidConfigurationException` about property

**Check**:
1. Property exists on entity
2. Property is accessible (public or has getter)
3. Property name matches exactly (case-sensitive)

### Sitemap Not Updating

**Problem**: Dynamic sitemap shows old data

**Reason**: Data is generated on-the-fly from database

**Solution**: Clear cache if using HTTP cache layer

## Performance Tips

### For Large Datasets

1. **Use repository methods** with selective field loading:
   ```php
   ->select('e.id', 'e.slug', 'e.updatedAt') // Only needed fields
   ```

2. **Add database indexes** on columns used in WHERE clauses

3. **Use toIterable()** for streaming:
   ```php
   ->getQuery()->toIterable()
   ```

4. **Consider caching** the generated XML (future feature)

### For Static Generation

1. **Run as cron job** during low-traffic periods
2. **Store in public/** for direct web server serving
3. **Use CDN** to cache sitemap files

## Development Workflow

### Adding a Feature

1. Update `PLAN.md` if architectural change
2. Write failing test
3. Implement feature
4. Run `composer qa`
5. Update `CHANGELOG.md`
6. Update `README.md` if user-facing

### Before Committing

```bash
composer qa  # Runs phpstan, cs-check, tests
```

### Release Process

1. Update version in `CHANGELOG.md`
2. Tag release: `git tag v1.0.0`
3. Push: `git push --tags`
4. GitHub Actions will run CI

## PHPStan Configuration

Level 9 strict mode - all files must:
- Have strict types declaration
- Have complete type coverage
- Have no mixed types
- Have no undefined properties/methods

## Dependencies

### Production

- `php`: >=8.3
- `ext-xmlwriter`: Required for XML generation
- `symfony/*`: ^6.4|^7.0|^8.0
- `doctrine/orm`: ^3.0|^4.0

### Development

- `phpunit/phpunit`: ^12.0
- `phpstan/*`: ^2.0
- `friendsofphp/php-cs-fixer`: ^3.40

## Compatibility Matrix

| PHP | Symfony | Doctrine | Status |
|-----|---------|----------|--------|
| 8.3 | 6.4     | 3.0      | ✅ Tested |
| 8.3 | 7.0     | 3.0      | ✅ Tested |
| 8.3 | 8.0     | 4.0      | ✅ Tested |
| 8.2 | 6.4     | 3.0      | ❌ Not supported |

## AI Agent Instructions

When modifying this codebase:

1. **Maintain strict types**: Every file must have `declare(strict_types=1);`
2. **Type everything**: No mixed, no untyped parameters/returns
3. **Use readonly**: DTOs should be readonly classes
4. **Follow existing patterns**: Registry, Provider, Strategy
5. **Test coverage**: Add tests for new features
6. **Run QA**: Always run `composer qa` before suggesting changes
7. **Update docs**: Update README.md and CHANGELOG.md for user-facing changes
8. **Respect constraints**: PHP 8.3+, Symfony 6.4+, Doctrine 3.0+

### Code Generation Templates

**New DTO**:
```php
<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Model;

readonly class NewDto
{
    public function __construct(
        public string $property,
    ) {
    }
}
```

**New Service**:
```php
<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Service;

class NewService
{
    public function __construct(
        private readonly DependencyInterface $dependency,
    ) {
    }
    
    public function doSomething(): ReturnType
    {
        // Implementation
    }
}
```

**New Exception**:
```php
<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Exception;

class NewException extends SitemapException
{
}
```

## Resources

- [Sitemap Protocol](https://www.sitemaps.org/protocol.html)
- [Symfony Bundles Best Practices](https://symfony.com/doc/current/bundles/best_practices.html)
- [Doctrine Query Optimization](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/batch-processing.html)
- [PHPStan Level 9](https://phpstan.org/user-guide/rule-levels)

## Support

- Issues: https://github.com/ecourty/sitemap-bundle/issues
- Email: e.courty@ecour.es
- Documentation: See README.md and PLAN.md

---

**Last Updated**: 2026-01-05  
**Bundle Version**: 1.0.0-dev  
**Maintained By**: Édouard Courty
