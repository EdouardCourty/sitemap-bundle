<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Integration;

use Ecourty\SitemapBundle\Contract\SitemapGeneratorInterface;
use Ecourty\SitemapBundle\Tests\DatabaseTestCase;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Article;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Song;

class SitemapGenerationTest extends DatabaseTestCase
{
    private SitemapGeneratorInterface $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $generator = self::getContainer()->get(SitemapGeneratorInterface::class);
        \assert($generator instanceof SitemapGeneratorInterface);
        $this->generator = $generator;
    }

    public function testGenerateSimpleSitemapWithStaticAndEntityRoutes(): void
    {
        // Arrange: Insert test data
        $article1 = new Article(
            'first-article',
            'First Article',
            'Content of first article',
            new \DateTimeImmutable('2026-01-01 10:00:00'),
        );
        $article2 = new Article(
            'second-article',
            'Second Article',
            'Content of second article',
            new \DateTimeImmutable('2026-01-02 15:30:00'),
        );

        $song1 = new Song(
            'abc-123',
            'Amazing Song',
            new \DateTimeImmutable('2026-01-03 08:00:00'),
        );

        $this->entityManager->persist($article1);
        $this->entityManager->persist($article2);
        $this->entityManager->persist($song1);
        $this->entityManager->flush();

        // Act: Generate sitemap
        $xml = $this->generator->generate();

        // Assert: Check XML structure
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);

        // Assert: Check static routes
        $this->assertStringContainsString('<loc>https://example.com/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/about</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/contact</loc>', $xml);

        // Assert: Check entity routes
        $this->assertStringContainsString('<loc>https://example.com/article/first-article</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/article/second-article</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/song/abc-123</loc>', $xml);

        // Assert: Check lastmod dates
        $this->assertStringContainsString('<lastmod>2026-01-01</lastmod>', $xml);
        $this->assertStringContainsString('<lastmod>2026-01-02</lastmod>', $xml);
        $this->assertStringContainsString('<lastmod>2026-01-03</lastmod>', $xml);

        // Assert: Check priority and changefreq
        $this->assertStringContainsString('<priority>1</priority>', $xml); // XMLWriter doesn't output trailing .0
        $this->assertStringContainsString('<changefreq>weekly</changefreq>', $xml);
    }

    public function testGenerateSitemapWithNoEntities(): void
    {
        // Act: Generate sitemap with empty database
        $xml = $this->generator->generate();

        // Assert: Should contain only static routes
        $this->assertStringContainsString('<loc>https://example.com/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/about</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/contact</loc>', $xml);

        // Assert: Should not contain entity routes
        $this->assertStringNotContainsString('/article/', $xml);
        $this->assertStringNotContainsString('/song/', $xml);
    }

    public function testXmlEscapingInUrls(): void
    {
        // Arrange: Insert article with special characters
        $article = new Article(
            'test-&-special',
            'Test & Special',
            'Content',
            new \DateTimeImmutable('2026-01-01'),
        );
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: Special characters should be escaped in URL
        $this->assertStringContainsString('test-%26-special', $xml);
    }

    public function testSitemapIsValidXml(): void
    {
        // Arrange
        $article = new Article('test', 'Test', 'Content', new \DateTimeImmutable());
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: Load XML to verify validity
        $doc = new \DOMDocument();
        $loaded = @$doc->loadXML($xml);
        $this->assertTrue($loaded, 'Generated sitemap should be valid XML');

        // Assert: Check namespace
        $urlsets = $doc->getElementsByTagName('urlset');
        $this->assertCount(1, $urlsets);
        $urlset = $urlsets->item(0);
        \assert($urlset instanceof \DOMElement);
        $this->assertEquals('http://www.sitemaps.org/schemas/sitemap/0.9', $urlset->getAttribute('xmlns'));
    }
}
