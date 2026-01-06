<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Fixtures\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Song;

/**
 * @extends ServiceEntityRepository<Song>
 */
class SongRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Song::class);
    }

    /**
     * Returns QueryBuilder for sitemap generation.
     * Filters out songs with empty titles.
     */
    public function getSitemapQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->where('s.title != :empty')
            ->setParameter('empty', '')
            ->orderBy('s.updatedAt', 'DESC');
    }
}
