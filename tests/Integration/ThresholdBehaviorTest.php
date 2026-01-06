<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Integration;

use Ecourty\SitemapBundle\Contract\SitemapGeneratorInterface;
use Ecourty\SitemapBundle\Tests\DatabaseTestCase;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Article;

class ThresholdBehaviorTest extends DatabaseTestCase
{
    private SitemapGeneratorInterface $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $generator = self::getContainer()->get(SitemapGeneratorInterface::class);
        \assert($generator instanceof SitemapGeneratorInterface);
        $this->generator = $generator;
    }

    public function testExactlyAtThresholdGeneratesSimpleSitemap(): void
    {
        // Threshold is 50 in test config
        // 4 static routes + 46 articles = 50 URLs exactly
        for ($i = 1; $i <= 46; $i++) {
            $article = new Article(
                "article-{$i}",
                "Article {$i}",
                'Content',
                new \DateTimeImmutable('2026-01-01'),
            );
            $this->entityManager->persist($article);

            if ($i % 10 === 0) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: Should generate simple sitemap (at threshold, not over)
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringNotContainsString('<sitemapindex', $xml);

        // Verify we have all 50 URLs
        $urlCount = \substr_count($xml, '<url>');
        $this->assertSame(50, $urlCount);
    }

    public function testOneOverThresholdGeneratesIndex(): void
    {
        // 4 static routes + 47 articles = 51 URLs (over threshold by 1)
        for ($i = 1; $i <= 47; $i++) {
            $article = new Article(
                "article-{$i}",
                "Article {$i}",
                'Content',
                new \DateTimeImmutable('2026-01-01'),
            );
            $this->entityManager->persist($article);

            if ($i % 10 === 0) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: Should generate sitemap index
        $this->assertStringContainsString('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringNotContainsString('<urlset', $xml);

        // Should have references to sitemap_static.xml and sitemap_entity_article.xml
        $this->assertStringContainsString('sitemap_static.xml', $xml);
        $this->assertStringContainsString('sitemap_entity_article.xml', $xml);
    }

    public function testBelowThresholdGeneratesSimpleSitemap(): void
    {
        // Only 10 articles + 4 static routes = 14 URLs (well below threshold)
        for ($i = 1; $i <= 10; $i++) {
            $article = new Article(
                "article-{$i}",
                "Article {$i}",
                'Content',
                new \DateTimeImmutable('2026-01-01'),
            );
            $this->entityManager->persist($article);
        }
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: Should generate simple sitemap
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringNotContainsString('<sitemapindex', $xml);

        $urlCount = \substr_count($xml, '<url>');
        $this->assertSame(14, $urlCount);
    }

}
