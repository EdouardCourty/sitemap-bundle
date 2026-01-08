<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Integration;

use Ecourty\SitemapBundle\Contract\SitemapGeneratorInterface;
use Ecourty\SitemapBundle\Service\SitemapGenerator;
use Ecourty\SitemapBundle\Service\SitemapIndexWriter;
use Ecourty\SitemapBundle\Service\UrlProviderRegistry;
use Ecourty\SitemapBundle\Service\XmlWriter;
use Ecourty\SitemapBundle\Tests\DatabaseTestCase;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\CmsPage;
use Ecourty\SitemapBundle\Tests\Fixtures\Service\CmsPageUrlProvider;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Integration tests for custom UrlProvider implementation.
 *
 * Tests a real-world scenario: CMS pages stored in database with:
 * - Different page types mapping to different routes
 * - Dynamic routing logic
 * - Priority and changefreq stored in DB
 * - Custom filtering logic (only published pages)
 */
class CustomUrlProviderTest extends DatabaseTestCase
{
    private CmsPageUrlProvider $provider;
    private SitemapGeneratorInterface $generator;

    protected function setUp(): void
    {
        parent::setUp();

        // Get services from container
        $urlGenerator = self::getContainer()->get(UrlGeneratorInterface::class);
        \assert($urlGenerator instanceof UrlGeneratorInterface);

        // Create custom provider instance
        $this->provider = new CmsPageUrlProvider(
            $this->entityManager,
            $urlGenerator,
            new PropertyAccessor(),
            'https://example.com',
        );

        // Create registry with our custom provider
        $registry = new UrlProviderRegistry([$this->provider]);

        // Create writers
        $xmlWriter = new XmlWriter();
        $indexWriter = new SitemapIndexWriter($xmlWriter, 'https://example.com');

        // Create a generator with our custom provider
        $this->generator = new SitemapGenerator(
            $registry,
            $xmlWriter,
            $indexWriter,
            false,
            50000,
        );
    }

    public function testGeneratesSitemapWithCustomProvider(): void
    {
        // Create test CMS pages
        $page1 = new CmsPage(
            slug: 'about-us',
            type: 'page',
            status: 'published',
            updatedAt: new \DateTimeImmutable('2024-01-15 10:00:00'),
            priority: 0.8,
            changefreq: 'monthly',
        );

        $page2 = new CmsPage(
            slug: 'my-article',
            type: 'article',
            status: 'published',
            updatedAt: new \DateTimeImmutable('2024-01-20 14:30:00'),
            priority: 0.9,
            changefreq: 'weekly',
        );

        $page3 = new CmsPage(
            slug: 'special-offer',
            type: 'landing',
            status: 'published',
            updatedAt: new \DateTimeImmutable('2024-01-25 09:15:00'),
            priority: 1.0,
            changefreq: 'daily',
        );

        // Draft page - should NOT appear in sitemap
        $page4 = new CmsPage(
            slug: 'draft-page',
            type: 'page',
            status: 'draft',
            updatedAt: new \DateTimeImmutable('2024-01-10 12:00:00'),
            priority: 0.5,
        );

        $this->entityManager->persist($page1);
        $this->entityManager->persist($page2);
        $this->entityManager->persist($page3);
        $this->entityManager->persist($page4);
        $this->entityManager->flush();

        // Generate sitemap
        $xml = $this->generator->generate();

        // Verify XML structure
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);

        // Verify published pages are included with correct routes
        $this->assertStringContainsString('<loc>https://example.com/page/about-us</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/blog/blog/my-article</loc>', $xml); // article route has category
        $this->assertStringContainsString('<loc>https://example.com/landing/special-offer</loc>', $xml);

        // Verify draft page is NOT included
        $this->assertStringNotContainsString('draft-page', $xml);

        // Verify priorities from DB
        $this->assertMatchesRegularExpression('/<loc>https:\/\/example\.com\/page\/about-us<\/loc>.*?<priority>0\.8<\/priority>/s', $xml);
        $this->assertMatchesRegularExpression('/<loc>https:\/\/example\.com\/blog\/blog\/my-article<\/loc>.*?<priority>0\.9<\/priority>/s', $xml);
        $this->assertMatchesRegularExpression('/<loc>https:\/\/example\.com\/landing\/special-offer<\/loc>.*?<priority>1<\/priority>/s', $xml);

        // Verify changefreq from DB
        $this->assertMatchesRegularExpression('/<loc>https:\/\/example\.com\/page\/about-us<\/loc>.*?<changefreq>monthly<\/changefreq>/s', $xml);
        $this->assertMatchesRegularExpression('/<loc>https:\/\/example\.com\/blog\/blog\/my-article<\/loc>.*?<changefreq>weekly<\/changefreq>/s', $xml);
        $this->assertMatchesRegularExpression('/<loc>https:\/\/example\.com\/landing\/special-offer<\/loc>.*?<changefreq>daily<\/changefreq>/s', $xml);

        // Verify lastmod dates
        $this->assertStringContainsString('<lastmod>2024-01-15</lastmod>', $xml);
        $this->assertStringContainsString('<lastmod>2024-01-20</lastmod>', $xml);
        $this->assertStringContainsString('<lastmod>2024-01-25</lastmod>', $xml);
    }

    public function testCustomProviderHandlesEmptyDatabase(): void
    {
        // No pages in database
        $xml = $this->generator->generate();

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', $xml);

        // Should not contain any <url> elements
        $this->assertStringNotContainsString('<url>', $xml);
    }

    public function testCustomProviderCountsOnlyPublishedPages(): void
    {
        // Create mix of published and draft pages
        $published1 = new CmsPage('page-1', 'page', 'published', new \DateTimeImmutable());
        $published2 = new CmsPage('page-2', 'page', 'published', new \DateTimeImmutable());
        $draft1 = new CmsPage('page-3', 'page', 'draft', new \DateTimeImmutable());
        $draft2 = new CmsPage('page-4', 'page', 'draft', new \DateTimeImmutable());

        $this->entityManager->persist($published1);
        $this->entityManager->persist($published2);
        $this->entityManager->persist($draft1);
        $this->entityManager->persist($draft2);
        $this->entityManager->flush();

        // Should count only published pages
        $this->assertSame(2, $this->provider->count());
    }

    public function testCustomProviderWithLargeDataset(): void
    {
        // Create 100 CMS pages
        for ($i = 1; $i <= 100; ++$i) {
            $page = new CmsPage(
                slug: "page-{$i}",
                type: 'page',
                status: 'published',
                updatedAt: new \DateTimeImmutable(),
                priority: 0.5,
            );
            $this->entityManager->persist($page);
        }
        $this->entityManager->flush();

        $xml = $this->generator->generate();

        // Verify all pages are in sitemap
        for ($i = 1; $i <= 100; ++$i) {
            $this->assertStringContainsString("<loc>https://example.com/page/page-{$i}</loc>", $xml);
        }

        // Count URL elements
        $urlCount = \substr_count($xml, '<url>');
        $this->assertSame(100, $urlCount);
    }

    public function testCustomProviderWithDifferentPageTypes(): void
    {
        // Create one of each type
        $pageTypes = [
            ['slug' => 'about', 'type' => 'page', 'expectedUrl' => '/page/about'],
            ['slug' => 'blog-post', 'type' => 'article', 'expectedUrl' => '/blog/blog/blog-post'],
            ['slug' => 'promo', 'type' => 'landing', 'expectedUrl' => '/landing/promo'],
            ['slug' => 'item-123', 'type' => 'product', 'expectedUrl' => '/product/item-123'],
        ];

        foreach ($pageTypes as $pageData) {
            $page = new CmsPage(
                slug: $pageData['slug'],
                type: $pageData['type'],
                status: 'published',
                updatedAt: new \DateTimeImmutable(),
            );
            $this->entityManager->persist($page);
        }
        $this->entityManager->flush();

        $xml = $this->generator->generate();

        // Verify each type uses correct route
        foreach ($pageTypes as $pageData) {
            $this->assertStringContainsString(
                '<loc>https://example.com' . $pageData['expectedUrl'] . '</loc>',
                $xml,
                "Page type '{$pageData['type']}' should use route '{$pageData['expectedUrl']}'",
            );
        }
    }

    public function testCustomProviderUsesDefaultChangefreqWhenNull(): void
    {
        $page = new CmsPage(
            slug: 'test-page',
            type: 'page',
            status: 'published',
            updatedAt: new \DateTimeImmutable(),
            priority: 0.5,
            changefreq: null, // No changefreq specified
        );

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        $xml = $this->generator->generate();

        // Should use default 'weekly'
        $this->assertMatchesRegularExpression(
            '/<loc>https:\/\/example\.com\/page\/test-page<\/loc>.*?<changefreq>weekly<\/changefreq>/s',
            $xml,
        );
    }

    public function testCustomProviderMemoryEfficiency(): void
    {
        // Create 1000 pages to test streaming
        for ($i = 1; $i <= 1000; ++$i) {
            $page = new CmsPage(
                slug: "memory-test-{$i}",
                type: 'page',
                status: 'published',
                updatedAt: new \DateTimeImmutable(),
            );
            $this->entityManager->persist($page);

            if ($i % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        $memoryBefore = \memory_get_usage(true);

        $xml = $this->generator->generate();

        $memoryAfter = \memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Verify all URLs are present
        $urlCount = \substr_count($xml, '<url>');
        $this->assertSame(1000, $urlCount);

        // Memory usage should be reasonable (less than 50MB for 1000 pages)
        // This verifies that toIterable() is working properly
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage too high - check if using toIterable()');
    }
}
