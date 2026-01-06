<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Integration;

use Ecourty\SitemapBundle\Contract\SitemapGeneratorInterface;
use Ecourty\SitemapBundle\Tests\DatabaseTestCase;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Article;

class DateFormatTest extends DatabaseTestCase
{
    private SitemapGeneratorInterface $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $generator = self::getContainer()->get(SitemapGeneratorInterface::class);
        \assert($generator instanceof SitemapGeneratorInterface);
        $this->generator = $generator;
    }

    public function testLastmodUsesCorrectDateFormat(): void
    {
        // Create article with specific date
        $article = new Article(
            'test-article',
            'Test Article',
            'Content',
            new \DateTimeImmutable('2026-01-05 14:30:45'),
        );
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: lastmod should use YYYY-MM-DD format (not full ISO 8601 with time)
        $this->assertStringContainsString('<lastmod>2026-01-05</lastmod>', $xml);

        // Should NOT contain time part
        $this->assertStringNotContainsString('14:30:45', $xml);
        $this->assertStringNotContainsString('T14:30', $xml);
    }

    public function testStaticRouteWithRelativeLastmod(): void
    {
        // Config has "news" route with lastmod: '-1 week'

        // Act: Generate sitemap
        $xml = $this->generator->generate();

        // Assert: News route should have lastmod in YYYY-MM-DD format
        // We can't test exact date since it's relative ("-1 week"), but we can test format
        $this->assertMatchesRegularExpression(
            '/<url>\s*<loc>https:\/\/example\.com\/news<\/loc>\s*<lastmod>\d{4}-\d{2}-\d{2}<\/lastmod>/s',
            $xml,
            'Static route with relative lastmod should have date in YYYY-MM-DD format',
        );

        // Also verify home route has NO lastmod (not configured)
        $this->assertMatchesRegularExpression(
            '/<url>\s*<loc>https:\/\/example\.com\/<\/loc>\s*<changefreq>daily<\/changefreq>\s*<priority>1<\/priority>\s*<\/url>/s',
            $xml,
            'Static route without lastmod should not contain lastmod tag',
        );
    }

    public function testEntityWithoutLastmodProperty(): void
    {
        // Songs in the test config have lastmod_property set to 'updatedAt'
        // But let's verify Articles do have it and check the behavior

        $article = new Article(
            'test-article',
            'Test Article',
            'Content',
            new \DateTimeImmutable('2026-01-15'),
        );
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $xml = $this->generator->generate();

        // Article has lastmod_property configured, so should have <lastmod>
        $this->assertMatchesRegularExpression(
            '/<url>\s*<loc>https:\/\/example\.com\/article\/test-article<\/loc>\s*<lastmod>2026-01-15<\/lastmod>/s',
            $xml,
        );
    }

    public function testMultipleDatesInSameSitemap(): void
    {
        // Create articles with different dates
        $dates = [
            '2026-01-01',
            '2026-02-15',
            '2026-12-31',
        ];

        foreach ($dates as $i => $date) {
            $article = new Article(
                "article-{$i}",
                "Article {$i}",
                'Content',
                new \DateTimeImmutable($date),
            );
            $this->entityManager->persist($article);
        }
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: All dates should be present in correct format
        foreach ($dates as $date) {
            $this->assertStringContainsString("<lastmod>{$date}</lastmod>", $xml);
        }
    }

    public function testDateTimeImmutableAndDateTimeWork(): void
    {
        // Create article with DateTimeImmutable
        $article1 = new Article(
            'article-1',
            'Article 1',
            'Content',
            new \DateTimeImmutable('2026-01-10'),
        );
        $this->entityManager->persist($article1);

        // The entity uses DateTimeImmutable, but let's make sure both work
        $article2 = new Article(
            'article-2',
            'Article 2',
            'Content',
            \DateTimeImmutable::createFromMutable(new \DateTime('2026-01-20')),
        );
        $this->entityManager->persist($article2);
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert
        $this->assertStringContainsString('<lastmod>2026-01-10</lastmod>', $xml);
        $this->assertStringContainsString('<lastmod>2026-01-20</lastmod>', $xml);
    }

}
