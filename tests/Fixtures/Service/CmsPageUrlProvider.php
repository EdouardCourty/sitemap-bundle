<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Fixtures\Service;

use Doctrine\ORM\EntityManagerInterface;
use Ecourty\SitemapBundle\Contract\UrlProviderInterface;
use Ecourty\SitemapBundle\Enum\ChangeFrequency;
use Ecourty\SitemapBundle\Model\SitemapUrl;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\CmsPage;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Custom URL provider for CMS pages with dynamic routing logic.
 *
 * This demonstrates a real-world scenario where:
 * - Different page types map to different routes
 * - Priority and changefreq are stored in database
 * - URLs are generated based on page type and slug
 */
class CmsPageUrlProvider implements UrlProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly string $baseUrl,
    ) {
    }

    public function getUrls(): iterable
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(CmsPage::class, 'p')
            ->where('p.status = :published')
            ->setParameter('published', 'published')
            ->orderBy('p.updatedAt', 'DESC');

        $query = $qb->getQuery();

        foreach ($query->toIterable() as $page) {
            $routeName = $this->getRouteNameForType($page->getType());
            $routeParams = $this->getRouteParamsForPage($page);

            $path = $this->urlGenerator->generate($routeName, $routeParams);
            $url = \rtrim($this->baseUrl, '/') . $path;

            yield new SitemapUrl(
                loc: $url,
                priority: $page->getPriority(),
                changefreq: $page->getChangefreq() !== null
                    ? ChangeFrequency::from($page->getChangefreq())
                    : ChangeFrequency::WEEKLY,
                lastmod: $page->getUpdatedAt(),
            );
        }
    }

    public function count(): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(CmsPage::class, 'p')
            ->where('p.status = :published')
            ->setParameter('published', 'published');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getSourceName(): string
    {
        return 'cms_pages';
    }

    /**
     * Map page type to route name.
     * Different CMS page types can have different routes.
     */
    private function getRouteNameForType(string $type): string
    {
        return match ($type) {
            'article' => 'cms_article_show',
            'landing' => 'cms_landing_show',
            'product' => 'cms_product_show',
            default => 'cms_page_show',
        };
    }

    /**
     * Build route parameters based on page data.
     * Can include dynamic logic based on page properties.
     */
    private function getRouteParamsForPage(CmsPage $page): array
    {
        // Base params
        $params = ['slug' => $page->getSlug()];

        // Add type-specific params
        if ($page->getType() === 'article') {
            // For articles, we might want to include category in URL
            $params['category'] = 'blog';
        }

        return $params;
    }
}
