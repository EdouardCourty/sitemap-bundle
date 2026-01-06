<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Integration;

use Ecourty\SitemapBundle\Contract\SitemapGeneratorInterface;
use Ecourty\SitemapBundle\Tests\DatabaseTestCase;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Page;

class MinimalConfigTest extends DatabaseTestCase
{
    private SitemapGeneratorInterface $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $generator = self::getContainer()->get(SitemapGeneratorInterface::class);
        \assert($generator instanceof SitemapGeneratorInterface);
        $this->generator = $generator;
    }

    public function testMinimalConfigWithDefaultValues(): void
    {
        // Arrange: Page entity has minimal config (only entity, route, route_params)
        $page1 = new Page('about-us', 'About Us');
        $page2 = new Page('contact', 'Contact');

        $this->entityManager->persist($page1);
        $this->entityManager->persist($page2);
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: Pages should be in sitemap
        $this->assertStringContainsString('https://example.com/page/about-us</loc>', $xml);
        $this->assertStringContainsString('https://example.com/page/contact</loc>', $xml);

        // Assert: Should use default priority (0.5)
        $pageUrlCount = \substr_count($xml, '<loc>https://example.com/page/');
        $this->assertEquals(2, $pageUrlCount);

        // Assert: Should use default changefreq (weekly) - check it exists
        $this->assertStringContainsString('<changefreq>weekly</changefreq>', $xml);

        // Assert: Should use default priority (0.5) - check it exists
        $this->assertStringContainsString('<priority>0.5</priority>', $xml);
    }

    public function testMinimalConfigWithoutLastmod(): void
    {
        // Arrange: Page has no lastmod_property in config
        $page = new Page('terms', 'Terms and Conditions');

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: Page should be in sitemap
        $this->assertStringContainsString('https://example.com/page/terms</loc>', $xml);

        // Assert: Should NOT have lastmod tag (no property configured)
        $pattern = '/<url>\s*<loc>https:\/\/example\.com\/page\/terms<\/loc>\s*<lastmod>/s';
        $this->assertDoesNotMatchRegularExpression($pattern, $xml, 'Should not have lastmod when no lastmod_property configured');

        // But should have priority and changefreq
        $this->assertStringContainsString('<priority>0.5</priority>', $xml);
        $this->assertStringContainsString('<changefreq>weekly</changefreq>', $xml);
    }

    public function testMinimalConfigWithNoFiltering(): void
    {
        // Arrange: Create pages with different characteristics
        // Since Page config has no conditions or query_builder_method, ALL pages should appear
        $page1 = new Page('page-1', 'Page One');
        $page2 = new Page('page-2', 'Page Two');
        $page3 = new Page('page-3', 'Page Three');

        $this->entityManager->persist($page1);
        $this->entityManager->persist($page2);
        $this->entityManager->persist($page3);
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: All pages should be included (no filtering)
        $this->assertStringContainsString('https://example.com/page/page-1</loc>', $xml);
        $this->assertStringContainsString('https://example.com/page/page-2</loc>', $xml);
        $this->assertStringContainsString('https://example.com/page/page-3</loc>', $xml);

        $pageUrlCount = \substr_count($xml, '<loc>https://example.com/page/');
        $this->assertEquals(3, $pageUrlCount, 'All pages should be included without filtering');
    }

}
