<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Integration;

use Ecourty\SitemapBundle\Contract\SitemapGeneratorInterface;
use Ecourty\SitemapBundle\Tests\DatabaseTestCase;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Article;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Song;

class SitemapIndexGenerationTest extends DatabaseTestCase
{
    private SitemapGeneratorInterface $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $generator = self::getContainer()->get(SitemapGeneratorInterface::class);
        \assert($generator instanceof SitemapGeneratorInterface);
        $this->generator = $generator;
    }

    public function testSitemapIndexStructure(): void
    {
        // Create enough data to trigger index generation
        for ($i = 1; $i <= 50; $i++) {
            $song = new Song("uid-{$i}", "Song {$i}", new \DateTimeImmutable());
            $this->entityManager->persist($song);

            if ($i % 20 === 0) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        $output = $this->generator->generate();

        // Assert: Valid XML structure
        $doc = new \DOMDocument();
        $loaded = @$doc->loadXML($output);
        $this->assertTrue($loaded, 'Generated output should be valid XML');

        // Assert: Check sitemap index structure
        $sitemapindexes = $doc->getElementsByTagName('sitemapindex');
        $this->assertCount(1, $sitemapindexes);

        $sitemapindex = $sitemapindexes->item(0);
        \assert($sitemapindex instanceof \DOMElement);
        $this->assertEquals('http://www.sitemaps.org/schemas/sitemap/0.9', $sitemapindex->getAttribute('xmlns'));

        // Check that we have sitemap entries
        $sitemaps = $doc->getElementsByTagName('sitemap');
        $this->assertGreaterThan(0, $sitemaps->length);

        // Each sitemap should have loc and lastmod
        foreach ($sitemaps as $sitemap) {
            $locs = $sitemap->getElementsByTagName('loc');
            $lastmods = $sitemap->getElementsByTagName('lastmod');

            $this->assertCount(1, $locs);
            $this->assertCount(1, $lastmods);
        }
    }

    public function testSitemapIndexBaseUrlConsistency(): void
    {
        // Create data to trigger index
        for ($i = 1; $i <= 60; $i++) {
            $article = new Article("article-{$i}", "Article {$i}", 'Content', new \DateTimeImmutable());
            $this->entityManager->persist($article);

            if ($i % 20 === 0) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();

        $output = $this->generator->generate();

        // Assert: All sitemap URLs should use the configured base_url
        $this->assertStringContainsString('<loc>https://example.com/sitemap_static.xml</loc>', $output);
        $this->assertStringContainsString('<loc>https://example.com/sitemap_entity_article.xml</loc>', $output);

        // Should NOT contain localhost (which is used for route generation)
        $doc = new \DOMDocument();
        $doc->loadXML($output);
        $locs = $doc->getElementsByTagName('loc');

        foreach ($locs as $loc) {
            $url = $loc->textContent;
            $this->assertStringStartsWith(
                'https://example.com/',
                $url,
                'Sitemap index URLs should use configured base_url',
            );
        }
    }

}
