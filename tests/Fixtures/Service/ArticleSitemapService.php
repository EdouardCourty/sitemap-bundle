<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Fixtures\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Article;

/**
 * Custom service that provides a QueryBuilder for Articles.
 * This demonstrates using FQCN::method instead of repository method.
 */
class ArticleSitemapService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Returns a QueryBuilder for published articles only.
     */
    public function getPublishedArticlesQueryBuilder(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Article::class, 'a')
            ->where('a.content != :empty')
            ->setParameter('empty', 'draft')
            ->orderBy('a.publishedAt', 'DESC');
    }
}
