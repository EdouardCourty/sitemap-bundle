<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Integration;

use Ecourty\SitemapBundle\Service\SitemapGenerator;
use Ecourty\SitemapBundle\Service\SitemapIndexWriter;
use Ecourty\SitemapBundle\Service\UrlProviderRegistry;
use Ecourty\SitemapBundle\Service\XmlWriter;
use Ecourty\SitemapBundle\Tests\DatabaseTestCase;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Article;

class UseIndexConfigTest extends DatabaseTestCase
{
    private UrlProviderRegistry $registry;
    private XmlWriter $xmlWriter;
    private SitemapIndexWriter $indexWriter;

    protected function setUp(): void
    {
        parent::setUp();
        $registry = self::getContainer()->get(UrlProviderRegistry::class);
        \assert($registry instanceof UrlProviderRegistry);
        $this->registry = $registry;

        $xmlWriter = self::getContainer()->get(XmlWriter::class);
        \assert($xmlWriter instanceof XmlWriter);
        $this->xmlWriter = $xmlWriter;

        $indexWriter = self::getContainer()->get(SitemapIndexWriter::class);
        \assert($indexWriter instanceof SitemapIndexWriter);
        $this->indexWriter = $indexWriter;
    }

    public function testForceIndexWithFewUrls(): void
    {
        // Create only 2 articles + 4 static routes = 6 URLs (well below threshold)
        for ($i = 1; $i <= 2; $i++) {
            $article = new Article(
                "article-{$i}",
                "Article {$i}",
                'Content',
                new \DateTimeImmutable('2026-01-01'),
            );
            $this->entityManager->persist($article);
        }
        $this->entityManager->flush();

        // Create generator with use_index forced to true
        $generator = new SitemapGenerator(
            $this->registry,
            $this->xmlWriter,
            $this->indexWriter,
            true, // Force index
            50,
        );

        // Act
        $xml = $generator->generate();

        // Assert: Should generate sitemap index even with only 5 URLs
        $this->assertStringContainsString('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringNotContainsString('<urlset', $xml);
        $this->assertStringContainsString('sitemap_static.xml', $xml);
        $this->assertStringContainsString('sitemap_entity_article.xml', $xml);
    }

    public function testDisableIndexWithManyUrls(): void
    {
        // Create 100 articles + 4 static routes = 104 URLs (well above threshold of 50)
        for ($i = 1; $i <= 100; $i++) {
            $article = new Article(
                "article-{$i}",
                "Article {$i}",
                'Content',
                new \DateTimeImmutable('2026-01-01'),
            );
            $this->entityManager->persist($article);

            if ($i % 20 === 0) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        // Create generator with use_index forced to false
        $generator = new SitemapGenerator(
            $this->registry,
            $this->xmlWriter,
            $this->indexWriter,
            false, // Disable index
            50,
        );

        // Act
        $xml = $generator->generate();

        // Assert: Should generate simple sitemap even with 103 URLs (above threshold)
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringNotContainsString('<sitemapindex', $xml);

        // Verify all URLs are present
        $urlCount = \substr_count($xml, '<url>');
        $this->assertSame(104, $urlCount);
    }
}
