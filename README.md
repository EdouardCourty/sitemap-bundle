# ecourty/sitemap-bundle

A Symfony bundle for generating XML sitemaps. Supports static routes, dynamic Doctrine entities, and extensive configuration options.

The bundle handles both dynamic generation via controller and static file generation via command.  
Memory-efficient streaming prevents issues with large datasets.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
  - [Use Cases](#use-cases)
  - [Full Configuration Example](#full-configuration-example)
  - [Configuration Reference](#configuration-reference)
- [Usage](#usage)
  - [Dynamic Generation (Controller)](#dynamic-generation-controller)
  - [Static Generation (Command)](#static-generation-command)
  - [Generated XML Output](#generated-xml-output)
- [Advanced Configuration](#advanced-configuration)
- [Architecture](#architecture)
- [Performance](#performance)
- [Troubleshooting](#troubleshooting)
- [Development](#development)
- [Contributing](#contributing)
- [License](#license)

## Requirements

- PHP 8.3+
- Symfony 6.4+ / 7.0+ / 8.0+
- Doctrine ORM 3.0+ / 4.0+
- Extension: `ext-xmlwriter`

## Installation

```bash
composer require ecourty/sitemap-bundle
```

The bundle will be automatically registered in `config/bundles.php` if using Symfony Flex (otherwise, add it manually):

```php
return [
    // ...
    Ecourty\SitemapBundle\SitemapBundle::class => ['all' => true],
];
```

## Quick Start

### 1. Basic Configuration

Create `config/packages/sitemap.yaml`:

**Example: Static routes only**

```yaml
sitemap:
    base_url: 'https://example.com'
    
    static_routes:
        - route: 'homepage'
          priority: 1.0
          changefreq: 'daily'
```

**Example: With dynamic entities**

```yaml
sitemap:
    base_url: 'https://example.com'
    
    static_routes:
        - route: 'homepage'
          priority: 1.0
          changefreq: 'daily'
    
    entity_routes:
        - entity: 'App\Entity\Article'
          route: 'article_show'
          route_params:
              slug: 'slug'  # entity property -> route parameter
          priority: 0.8
          changefreq: 'weekly'
          lastmod_property: 'updatedAt'
```

### 2. Import Routes (Optional)

**Only required if you want dynamic generation via `/sitemap.xml`.**

If you only use static generation (`sitemap:dump` command), you can skip this step.

Add the bundle routes to `config/routes.yaml`:

```yaml
sitemap:
    resource: '@SitemapBundle/Resources/config/routes.yaml'
```

### 3. Access Your Sitemap

**Dynamic generation** - visit in your browser:
```
https://example.com/sitemap.xml
```

**Static generation** - generate a file:
```bash
php bin/console sitemap:dump # Generates public/sitemap.xml
```

That's it! 🎉

## Configuration

### Use Cases

#### Case 1: Simple Website (Static Pages Only)

Perfect for marketing sites, landing pages, or small websites with fixed pages:

```yaml
sitemap:
    base_url: 'https://mysite.com'
    
    static_routes:
        - route: 'homepage'
          priority: 1.0
          changefreq: 'daily'
        - route: 'about'
        - route: 'services'
        - route: 'contact'
          priority: 0.6
```

#### Case 2: Blog or News Site

Static pages + dynamic articles from database:

```yaml
sitemap:
    base_url: 'https://myblog.com'
    
    static_routes:
        - route: 'homepage'
          priority: 1.0
        - route: 'blog_index'
          priority: 0.9
    
    entity_routes:
        - entity: 'App\Entity\Article'
          route: 'article_show'
          route_params:
              slug: 'slug'
          priority: 0.8
          changefreq: 'weekly'
          lastmod_property: 'publishedAt'
```

#### Case 3: E-commerce Site

Multiple entity types with different priorities:

```yaml
sitemap:
    base_url: 'https://myshop.com'
    
    static_routes:
        - route: 'homepage'
          priority: 1.0
        - route: 'catalog'
          priority: 0.9
    
    entity_routes:
        # Products (high priority, frequently updated)
        - entity: 'App\Entity\Product'
          route: 'product_show'
          route_params:
              id: 'id'
              slug: 'slug'
          priority: 0.8
          changefreq: 'daily'
          lastmod_property: 'updatedAt'
          query_builder_method: 'findActiveProducts'  # Only published products
        
        # Categories (medium priority)
        - entity: 'App\Entity\Category'
          route: 'category_show'
          route_params:
              slug: 'slug'
          priority: 0.6
          changefreq: 'weekly'
        
        # Blog articles (lower priority)
        - entity: 'App\Entity\BlogPost'
          route: 'blog_show'
          route_params:
              slug: 'slug'
          priority: 0.5
          changefreq: 'monthly'
```

#### Case 4: Filtering with DQL Conditions

When you need simple filtering without creating custom repository methods, use `conditions`:

```yaml
sitemap:
    base_url: 'https://myblog.com'
    
    entity_routes:
        # Only published articles
        - entity: 'App\Entity\Article'
          route: 'article_show'
          route_params:
              slug: 'slug'
          priority: 0.8
          changefreq: 'weekly'
          lastmod_property: 'updatedAt'
          conditions: 'e.published = true AND e.deletedAt IS NULL'
        
        # Only active products in stock
        - entity: 'App\Entity\Product'
          route: 'product_show'
          route_params:
              slug: 'slug'
          priority: 0.7
          changefreq: 'daily'
          conditions: 'e.active = true AND e.stock > 0'
        
        # Only upcoming events
        - entity: 'App\Entity\Event'
          route: 'event_show'
          route_params:
              id: 'id'
          priority: 0.9
          changefreq: 'daily'
          conditions: 'e.startDate >= CURRENT_DATE()'
```

**Note**: Use the alias `e` in your DQL conditions. You cannot combine `conditions` with `query_builder_method`.

#### Case 5: Large Dataset (Automatic Index)

For sites with 50,000+ URLs, the bundle automatically creates a sitemap index:

```yaml
sitemap:
    base_url: 'https://bigsite.com'
    use_index: 'auto'  # Automatically split if > 50,000 URLs
    index_threshold: 50000
    
    entity_routes:
        - entity: 'App\Entity\Product'
          route: 'product_show'
          route_params:
              slug: 'slug'
          # With 150,000 products, this creates:
          # sitemap_entity_product_1.xml (50,000 URLs)
          # sitemap_entity_product_2.xml (50,000 URLs)
          # sitemap_entity_product_3.xml (50,000 URLs)
```

### Full Configuration Example

```yaml
sitemap:
    # Base URL of your site (required)
    base_url: 'https://example.com'
    
    # Sitemap index strategy:
    # - 'auto': generate index if total URLs > index_threshold (default)
    # - true: always generate index (even with few URLs)
    # - false: never generate index, single sitemap.xml file
    use_index: 'auto'
    
    # URL count threshold for auto index mode
    index_threshold: 50000
    
    # Static routes (without parameters)
    static_routes:
        # Homepage with high priority
        - route: 'homepage'
          priority: 1.0
          changefreq: 'daily'
          lastmod: '-1 day'  # Optional: relative time string
        
        # Blog listing page
        - route: 'blog_list'
          priority: 0.9
          changefreq: 'daily'
        
        # About page
        - route: 'about'
          priority: 0.5
          changefreq: 'monthly'
    
    # Dynamic routes (with Doctrine entities)
    entity_routes:
        # Example: Song entities with custom repository method
        - entity: 'App\Entity\Song'
          route: 'song_show'
          route_params:
              uid: 'uid'  # entity property -> route parameter
          priority: 0.8
          changefreq: 'weekly'
          lastmod_property: 'updatedAt'  # Optional: DateTime property
          query_builder_method: 'getSitemapQueryBuilder'  # Optional: repository method
        
        # Example: Post entities with custom service
        - entity: 'App\Entity\Post'
          route: 'post_show'
          route_params:
              slug: 'slug'
          priority: 0.7
          changefreq: 'monthly'
          lastmod_property: 'publishedAt'
          query_builder_method: 'App\Service\PostSitemapService::getQueryBuilder'  # Optional: FQCN::method
        
        # Example: Product entities with DQL conditions
        - entity: 'App\Entity\Product'
          route: 'product_detail'
          route_params:
              id: 'id'
              slug: 'slug'
          priority: 0.6
          changefreq: 'weekly'
```

### Configuration Reference

| Option                                 | Type         | Default    | Description                         |
|----------------------------------------|--------------|------------|-------------------------------------|
| `base_url`                             | string       | *required* | Base URL for absolute URLs          |
| `use_index`                            | string\|bool | `'auto'`   | Index strategy: 'auto', true, false |
| `index_threshold`                      | int          | `50000`    | URL count threshold for auto index  |
| `static_routes[].route`                | string       | *required* | Symfony route name                  |
| `static_routes[].priority`             | float        | `0.5`      | Priority (0.0-1.0)                  |
| `static_routes[].changefreq`           | string       | `'weekly'` | Change frequency                    |
| `static_routes[].lastmod`              | string\|null | `null`     | Relative time (e.g., '-2 days')     |
| `entity_routes[].entity`               | string       | *required* | Entity class name (FQCN)            |
| `entity_routes[].route`                | string       | *required* | Symfony route name                  |
| `entity_routes[].route_params`         | array        | *required* | Property → parameter mapping        |
| `entity_routes[].priority`             | float        | `0.5`      | Priority (0.0-1.0)                  |
| `entity_routes[].changefreq`           | string       | `'weekly'` | Change frequency                    |
| `entity_routes[].lastmod_property`     | string\|null | `null`     | DateTime property name              |
| `entity_routes[].query_builder_method` | string\|null | `null`     | Repository method OR FQCN::method   |
| `entity_routes[].conditions`           | string\|null | `null`     | DQL WHERE clause                    |

**Valid `changefreq` values**: `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly`, `never`

**Important**: 
- Cannot use both `query_builder_method` and `conditions` simultaneously.
- `query_builder_method` can be a repository method name (e.g., `'getSitemapQueryBuilder'`) or a FQCN::method (e.g., `'App\Service\SitemapService::getArticlesQueryBuilder'`)

## Usage

### Dynamic Generation (Controller)

**Requires routes import** - see step 2 in [Quick Start](#quick-start).

Once routes are imported, access the dynamic sitemap at:

```
https://example.com/sitemap.xml
```

The sitemap is generated on-the-fly from your configuration and database. Best for small to medium sites or when you need always up-to-date data.

### Static Generation (Command)

**No routes import needed** - works out of the box after configuration.

Generate a static sitemap file:

```bash
# Generate to public/sitemap.xml (default)
php bin/console sitemap:dump

# Generate to custom path (relative to public/)
php bin/console sitemap:dump --output=sitemaps/sitemap.xml

# Generate to absolute path
php bin/console sitemap:dump --output=/var/www/public/sitemap.xml

# Force overwrite without confirmation
php bin/console sitemap:dump --force
```

**Recommended for:**
- Large sites with many URLs (better performance)
- Sites with infrequent content updates
- SEO-critical sites (serve static files via web server/CDN)

**Tip:** Run via cron to regenerate periodically:
```bash
# Regenerate sitemap every night at 3am
0 3 * * * cd /var/www && php bin/console sitemap:dump --force
```

### Generated XML Output

#### Simple Sitemap (Mixed Content)

When you have both static routes and dynamic entities with `use_index: false`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Static routes -->
    <url>
        <loc>https://example.com/</loc>
        <lastmod>2026-01-05</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>https://example.com/about</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc>https://example.com/blog</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    
    <!-- Dynamic entity routes -->
    <url>
        <loc>https://example.com/article/symfony-best-practices</loc>
        <lastmod>2026-01-03</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>https://example.com/article/php-8-features</loc>
        <lastmod>2026-01-04</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>https://example.com/product/123/awesome-widget</loc>
        <lastmod>2026-01-05</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
</urlset>
```

#### Sitemap Index (Large Datasets)

When `use_index: true` or URL count exceeds threshold, the bundle generates an index file referencing separate sitemaps per source.

**Benefits:**
- ✅ Better organization (one file per entity type)
- ✅ Faster incremental updates (regenerate only changed sources)
- ✅ Respects sitemap.org 50,000 URL limit per file
- ✅ Easier debugging and monitoring

**`sitemap.xml` (index file):**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc>https://example.com/sitemap_static.xml</loc>
        <lastmod>2026-01-05T20:30:00+00:00</lastmod>
    </sitemap>
    <sitemap>
        <loc>https://example.com/sitemap_entity_article.xml</loc>
        <lastmod>2026-01-05T20:30:15+00:00</lastmod>
    </sitemap>
    <sitemap>
        <loc>https://example.com/sitemap_entity_product_1.xml</loc>
        <lastmod>2026-01-05T20:30:45+00:00</lastmod>
    </sitemap>
    <sitemap>
        <loc>https://example.com/sitemap_entity_product_2.xml</loc>
        <lastmod>2026-01-05T20:30:52+00:00</lastmod>
    </sitemap>
</sitemapindex>
```

**`sitemap_static.xml` (static routes only):**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://example.com/</loc>
        <lastmod>2026-01-05</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>https://example.com/about</loc>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
</urlset>
```

**`sitemap_entity_article.xml` (articles only):**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://example.com/article/symfony-best-practices</loc>
        <lastmod>2026-01-03</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>https://example.com/article/php-8-features</loc>
        <lastmod>2026-01-04</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <!-- ... more articles ... -->
</urlset>
```

**Note**: When a source has more than 50,000 URLs, it's automatically split into numbered files (`sitemap_entity_product_1.xml`, `sitemap_entity_product_2.xml`, etc.)

## Advanced Configuration

### Custom Repository Method

For better performance with filtering and optimization, create a custom repository method that returns a `QueryBuilder`:

```php
// src/Repository/PostRepository.php
use Doctrine\ORM\QueryBuilder;

class PostRepository extends ServiceEntityRepository
{
    public function getSitemapQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->where('p.published = true')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.updatedAt', 'DESC');
    }
}
```

**Important:** Return a `QueryBuilder`, not the query result. The bundle will:
- Add `COUNT()` for efficient counting
- Optimize the SELECT to fetch only needed fields  
- Use `toIterable()` for memory-efficient streaming

Then reference it in config:

```yaml
entity_routes:
    - entity: 'App\Entity\Post'
      route: 'post_show'
      route_params:
          slug: 'slug'
      query_builder_method: 'getSitemapQueryBuilder'
```

### Custom Service with FQCN::method

For more flexibility, use any service (not just the entity's repository):

```php
// src/Service/PostSitemapService.php
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class PostSitemapService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }
    
    public function getPublishedPostsQueryBuilder(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('p')
            ->from(Post::class, 'p')
            ->where('p.status = :published')
            ->setParameter('published', 'published')
            ->orderBy('p.publishedAt', 'DESC');
    }
}
```

Configuration:

```yaml
entity_routes:
    - entity: 'App\Entity\Post'
      route: 'post_show'
      route_params:
          slug: 'slug'
      query_builder_method: 'App\Service\PostSitemapService::getPublishedPostsQueryBuilder'
```

### DQL Conditions

Use DQL conditions for simple filtering without custom methods:

```yaml
entity_routes:
    - entity: 'App\Entity\Post'
      route: 'post_show'
      route_params:
          slug: 'slug'
      conditions: 'e.published = true AND e.deletedAt IS NULL'
```

### Custom URL Provider

For complex URL generation needs (e.g., CMS pages from database with dynamic routing), implement a custom `UrlProviderInterface`.

**Use cases:**
- Dynamic routes based on page type stored in database
- External data sources (API, MongoDB, etc.)
- Complex URL generation logic
- Custom filtering or business rules

**Example: CMS pages with type-based routing**

```php
// src/Service/CmsPageUrlProvider.php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Ecourty\SitemapBundle\Contract\UrlProviderInterface;
use Ecourty\SitemapBundle\Enum\ChangeFrequency;
use Ecourty\SitemapBundle\Model\SitemapUrl;
use App\Entity\CmsPage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CmsPageUrlProvider implements UrlProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
        private string $baseUrl,
    ) {
    }

    public function getUrls(): iterable
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(CmsPage::class, 'p')
            ->where('p.status = :published')
            ->setParameter('published', 'published')
            ->orderBy('p.updatedAt', 'DESC');

        foreach ($qb->getQuery()->toIterable() as $page) {
            // Different page types use different routes
            $routeName = match ($page->getType()) {
                'article' => 'cms_article_show',
                'landing' => 'cms_landing_show',
                'product' => 'cms_product_show',
                default => 'cms_page_show',
            };

            $path = $this->urlGenerator->generate($routeName, [
                'slug' => $page->getSlug(),
            ]);

            yield new SitemapUrl(
                loc: rtrim($this->baseUrl, '/') . $path,
                priority: $page->getPriority(), // From DB
                changefreq: ChangeFrequency::from($page->getChangefreq()),
                lastmod: $page->getUpdatedAt(),
            );
        }
    }

    public function count(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(CmsPage::class, 'p')
            ->where('p.status = :published')
            ->setParameter('published', 'published')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getSourceName(): string
    {
        return 'cms_pages';
    }
}
```

**Configuration:**

```yaml
# config/services.yaml
services:
    App\Service\CmsPageUrlProvider:
        arguments:
            $baseUrl: '%sitemap.base_url%'
        # Automatically tagged as 'sitemap.url_provider' via autoconfiguration
```

That's it! The provider will be automatically discovered and used. No additional configuration needed.

**Benefits:**
- ✅ Full control over URL generation
- ✅ Type-safe with PHP 8.3 features
- ✅ Memory-efficient with `toIterable()`
- ✅ Automatically registered via Symfony autoconfiguration
- ✅ Works seamlessly with sitemap index splitting

### Multiple Route Parameters

Map multiple entity properties to route parameters:

```yaml
entity_routes:
    - entity: 'App\Entity\Product'
      route: 'product_detail'
      route_params:
          category: 'category.slug'  # Nested property
          slug: 'slug'
      priority: 0.8
```

### Sitemap Index Modes

Control how sitemaps are split:

```yaml
sitemap:
    # Auto mode (default): index if total URLs > 50,000
    use_index: 'auto'
    index_threshold: 50000
    
    # Always use index (even with few URLs)
    use_index: true
    
    # Never use index (single sitemap.xml)
    use_index: false
```

Example with index:
```
sitemap.xml                  # Index file
sitemap_static.xml           # Static routes
sitemap_entity_song.xml      # Song entities
sitemap_entity_post_1.xml    # Post entities (first 50k)
sitemap_entity_post_2.xml    # Post entities (remaining)
```

## Architecture

### Design Patterns

- **Registry Pattern**: `UrlProviderRegistry` collects all URL providers via tagged services
- **Provider Pattern**: Each URL source implements `UrlProviderInterface`
- **Strategy Pattern**: Index vs single sitemap decision based on configuration
- **DTO Pattern**: Immutable readonly configuration objects

### Extension Points

#### Custom URL Provider

Create custom URL sources by implementing `UrlProviderInterface`:

```php
use Ecourty\SitemapBundle\Contract\UrlProviderInterface;
use Ecourty\SitemapBundle\Enum\ChangeFrequency;
use Ecourty\SitemapBundle\Model\SitemapUrl;

class CustomUrlProvider implements UrlProviderInterface
{
    public function getUrls(): iterable
    {
        yield new SitemapUrl(
            loc: 'https://example.com/custom-page',
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
```

That's it! The service will be automatically registered and tagged thanks to Symfony's autoconfiguration.

## Performance

### Memory Optimization

The bundle automatically uses Doctrine's `toIterable()` to stream entities, preventing memory issues with large datasets.

**What the bundle does internally:**

```php
// ✅ Automatic streaming - no memory issues with 100k+ entities
$query->toIterable();

// ❌ Would load all entities in memory at once
$query->getResult();
```

**Your responsibility:** Return a `QueryBuilder` from repository methods (not query results):

```php
// ✅ Correct - return QueryBuilder
public function getSitemapQueryBuilder(): QueryBuilder
{
    return $this->createQueryBuilder('p')
        ->where('p.published = true');
}

// ❌ Wrong - don't call getQuery() or toIterable()
public function getSitemapData(): iterable
{
    return $this->createQueryBuilder('p')
        ->getQuery()
        ->toIterable();  // Bundle handles this automatically
}
```

### Recommendations

1. **Use repository methods** - The bundle optimizes the SELECT to fetch only needed fields
2. **Add database indexes** on columns used in WHERE clauses and route parameters
3. **Enable sitemap index** for datasets >50k URLs (automatic with `use_index: 'auto'`)
4. **Run static generation** as a cron job during low-traffic periods
5. **Use a CDN** to cache sitemap files

### Example: Optimized for Large Datasets

```yaml
sitemap:
    base_url: 'https://bigsite.com'
    use_index: 'auto'  # Splits at 50k URLs per file
    
    entity_routes:
        - entity: 'App\Entity\Product'
          route: 'product_show'
          route_params:
              slug: 'slug'
          query_builder_method: 'getActiveProductsQueryBuilder'
```

```php
// Repository method with filtering
public function getActiveProductsQueryBuilder(): QueryBuilder
{
    return $this->createQueryBuilder('p')
        ->where('p.active = true')
        ->andWhere('p.stock > 0')
        ->orderBy('p.updatedAt', 'DESC');
}
```

**Result:** Can handle millions of products with minimal memory usage.

## Troubleshooting

### Route not found

**Error**: `Route "post_show" does not exist in routing configuration`

**Solution**: Verify the route name exists:
```bash
php bin/console debug:router
```

### Entity not found

**Error**: `Entity "App\Entity\Post" is not a valid Doctrine entity`

**Solution**: Use the fully qualified class name (FQCN):
```yaml
entity: 'App\Entity\Post'  # ✅ Correct
entity: 'Post'              # ❌ Wrong
```

### Property not found

**Error**: `Property "slug" does not exist or is not readable`

**Solution**: Ensure the property exists and is accessible:
```php
class Post
{
    private string $slug;
    
    public function getSlug(): string  // Must have getter
    {
        return $this->slug;
    }
}
```

### Command Parameter Issue

If you get an error about `$publicDir` in `DumpSitemapCommand`, add it to your `services.yaml`:

```yaml
services:
    Ecourty\SitemapBundle\Command\DumpSitemapCommand:
        arguments:
            $publicDir: '%kernel.project_dir%/public'
        tags: ['console.command']
```

### Sitemap route not found (404)

**Error**: `/sitemap.xml` returns 404

**Solution**: Import the bundle routes in `config/routes.yaml`:

```yaml
sitemap:
    resource: '@SitemapBundle/Resources/config/routes.yaml'
```

Then clear the cache:
```bash
php bin/console cache:clear
```

**Note**: If you only use static generation (`sitemap:dump` command), you don't need to import the routes.

## Development

### Run Tests

```bash
composer test
```

### Static Analysis

```bash
composer phpstan
```

### Code Style

```bash
# Fix code style
composer cs-fix

# Check code style
composer cs-check
```

### Quality Assurance

Run all checks:

```bash
composer qa
```

Or use Makefile:

```bash
make install  # Install dependencies
make test     # Run tests
make phpstan  # Static analysis
make cs-fix   # Fix code style
make qa       # All quality checks
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Run tests: `composer test`
4. Check code quality: `composer qa`

### Code Standards

- PHP 8.3+ with strict types
- PSR-12 code style
- PHPStan Level 9
- Comprehensive tests
- Clear documentation

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Credits

**Author**: Édouard Courty - [@ecourty](https://github.com/ecourty)

**Documentation**: See [AGENTS.md](AGENTS.md) for detailed developer and AI agent guide.

## Support

- 🐛 [Report a bug](https://github.com/ecourty/sitemap-bundle/issues)
- 💡 [Request a feature](https://github.com/ecourty/sitemap-bundle/issues)
- 📖 [Documentation](https://github.com/ecourty/sitemap-bundle)
- 📧 Email: e.courty@ecour.es
