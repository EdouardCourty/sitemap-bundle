<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Integration;

use Ecourty\SitemapBundle\Enum\ChangeFrequency;
use Ecourty\SitemapBundle\Model\EntityRouteConfig;
use Ecourty\SitemapBundle\Provider\EntityRouteUrlProvider;
use Ecourty\SitemapBundle\Tests\DatabaseTestCase;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Article;
use Ecourty\SitemapBundle\Tests\Fixtures\Service\ArticleSitemapService;

class QueryBuilderMethodTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

    }

    public function testFqcnMethodFormat(): void
    {
        // Arrange: Insert articles with some marked as draft
        $article1 = new Article('published-1', 'Published Article', 'content', new \DateTimeImmutable('2026-01-01'));
        $article2 = new Article('draft-1', 'Draft Article', 'draft', new \DateTimeImmutable('2026-01-02'));
        $article3 = new Article('published-2', 'Another Published', 'content', new \DateTimeImmutable('2026-01-03'));

        $this->entityManager->persist($article1);
        $this->entityManager->persist($article2);
        $this->entityManager->persist($article3);
        $this->entityManager->flush();

        // Create provider with FQCN::method format
        $config = new EntityRouteConfig(
            entity: Article::class,
            route: 'article_show',
            routeParams: ['slug' => 'slug'],
            priority: 0.8,
            changefreq: ChangeFrequency::MONTHLY,
            lastmodProperty: 'publishedAt',
            queryBuilderMethod: ArticleSitemapService::class . '::getPublishedArticlesQueryBuilder',
            conditions: null,
        );

        /** @var \Doctrine\Persistence\ManagerRegistry $doctrine */
        $doctrine = self::getContainer()->get('doctrine');
        /** @var \Symfony\Component\Routing\Generator\UrlGeneratorInterface $router */
        $router = self::getContainer()->get('router');
        /** @var \Symfony\Component\PropertyAccess\PropertyAccessorInterface $propertyAccessor */
        $propertyAccessor = self::getContainer()->get('property_accessor');

        $provider = new EntityRouteUrlProvider(
            $config,
            $doctrine,
            $router,
            $propertyAccessor,
            self::getContainer(),
            'https://example.com',
        );

        // Act: Get URLs
        $urls = \iterator_to_array($provider->getUrls());

        // Assert: Should only have 2 articles (draft filtered out)
        $this->assertCount(2, $urls);

        // Assert: Check count method
        $count = $provider->count();
        $this->assertEquals(2, $count);

        // Assert: Verify the right articles are included
        $locs = \array_map(fn ($url) => $url->loc, $urls);
        $this->assertStringContainsString('published-1', \implode(',', $locs));
        $this->assertStringContainsString('published-2', \implode(',', $locs));
        $this->assertStringNotContainsString('draft-1', \implode(',', $locs));
    }

}
