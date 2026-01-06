<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Integration;

use Ecourty\SitemapBundle\Contract\SitemapGeneratorInterface;
use Ecourty\SitemapBundle\Tests\DatabaseTestCase;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Article;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Song;

class EntityRouteProviderTest extends DatabaseTestCase
{
    private SitemapGeneratorInterface $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $generator = self::getContainer()->get(SitemapGeneratorInterface::class);
        \assert($generator instanceof SitemapGeneratorInterface);
        $this->generator = $generator;
    }

    public function testProviderStreamingWithDoctrineIterable(): void
    {
        // Arrange: Insert 30 articles (below threshold to avoid index)
        for ($i = 1; $i <= 30; $i++) {
            $day = ($i % 28) + 1; // Keep days valid (1-28)
            $article = new Article(
                "slug-{$i}",
                "Title {$i}",
                "Content {$i}",
                new \DateTimeImmutable(\sprintf('2026-01-%02d 10:00:00', $day)),
            );
            $this->entityManager->persist($article);

            if ($i % 10 === 0) {
                $this->entityManager->flush();
            }
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Act: Generate sitemap (which uses providers internally)
        $xml = $this->generator->generate();

        // Assert: Should contain all 30 articles
        $urlCount = \substr_count($xml, '<loc>https://example.com/article/slug-');
        $this->assertEquals(30, $urlCount);

        // Assert: Check first and last article
        $this->assertStringContainsString('https://example.com/article/slug-1</loc>', $xml);
        $this->assertStringContainsString('https://example.com/article/slug-30</loc>', $xml);

        // Assert: Memory should not explode (implicit - test doesn't crash)
    }

    public function testProviderWithLastmodProperty(): void
    {
        // Arrange
        $publishedDate = new \DateTimeImmutable('2026-01-15 14:30:00');
        $article = new Article(
            'test-article',
            'Test Article',
            'Content',
            $publishedDate,
        );
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        // Act: Generate sitemap
        $xml = $this->generator->generate();

        // Assert: Should contain article with lastmod date
        $this->assertStringContainsString('https://example.com/article/test-article</loc>', $xml);
        $this->assertStringContainsString('<lastmod>2026-01-15</lastmod>', $xml);
    }

    public function testProviderWithMultipleEntitiesAndDifferentConfigs(): void
    {
        // Arrange: Create both article and song
        $article = new Article(
            'my-article',
            'My Article',
            'Content',
            new \DateTimeImmutable('2026-01-10 10:00:00'),
        );

        $song = new Song(
            'song-uid-123',
            'Test Song',
            new \DateTimeImmutable('2026-01-20 15:00:00'),
        );

        $this->entityManager->persist($article);
        $this->entityManager->persist($song);
        $this->entityManager->flush();

        // Act: Generate sitemap
        $xml = $this->generator->generate();

        // Assert: Both entities should be in sitemap
        $this->assertStringContainsString('https://example.com/article/my-article</loc>', $xml);
        $this->assertStringContainsString('https://example.com/song/song-uid-123</loc>', $xml);

        // Assert: Article has lastmod (from publishedAt property)
        $this->assertMatchesRegularExpression(
            '/<url>\s*<loc>https:\/\/example\.com\/article\/my-article<\/loc>\s*<lastmod>2026-01-10<\/lastmod>/s',
            $xml,
        );

        // Assert: Song has lastmod (from updatedAt property)
        $this->assertMatchesRegularExpression(
            '/<url>\s*<loc>https:\/\/example\.com\/song\/song-uid-123<\/loc>\s*<lastmod>2026-01-20<\/lastmod>/s',
            $xml,
        );

        // Assert: Different priorities
        $this->assertStringContainsString('<priority>0.7</priority>', $xml); // Article
        $this->assertStringContainsString('<priority>0.9</priority>', $xml); // Song
    }

    public function testProviderWithUrlGeneration(): void
    {
        // Arrange
        $article = new Article(
            'my-special-article',
            'My Special Article',
            'Content here',
            new \DateTimeImmutable(),
        );
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: Correct URL structure
        $this->assertStringContainsString('https://example.com/article/my-special-article</loc>', $xml);

        // Assert: Has correct priority and changefreq (from config)
        $this->assertStringContainsString('<priority>0.7</priority>', $xml);
        $this->assertStringContainsString('<changefreq>monthly</changefreq>', $xml);
    }

    public function testQueryBuilderMethodFiltering(): void
    {
        // Arrange: Insert songs with some having empty titles (should be filtered by query_builder_method)
        $song1 = new Song('uid-1', 'Valid Song', new \DateTimeImmutable('2026-01-01'));
        $song2 = new Song('uid-2', '', new \DateTimeImmutable('2026-01-02')); // Empty title - should be filtered
        $song3 = new Song('uid-3', 'Another Valid', new \DateTimeImmutable('2026-01-03'));

        $this->entityManager->persist($song1);
        $this->entityManager->persist($song2);
        $this->entityManager->persist($song3);
        $this->entityManager->flush();

        // Act: Generate sitemap
        $xml = $this->generator->generate();

        // Assert: Should only contain songs with non-empty titles (filtered by getSitemapQueryBuilder)
        $this->assertStringContainsString('https://example.com/song/uid-1</loc>', $xml);
        $this->assertStringContainsString('https://example.com/song/uid-3</loc>', $xml);
        $this->assertStringNotContainsString('https://example.com/song/uid-2</loc>', $xml);

        // Assert: Verify count is correct (only 2 songs, not 3)
        $songUrlCount = \substr_count($xml, '<loc>https://example.com/song/');
        $this->assertEquals(2, $songUrlCount, 'Should only have 2 songs (uid-2 filtered out by query_builder_method)');
    }
}
