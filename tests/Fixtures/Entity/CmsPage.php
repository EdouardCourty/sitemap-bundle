<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cms_pages')]
class CmsPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'string', length: 10)]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'float')]
    private float $priority = 0.5;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $changefreq = null;

    public function __construct(
        string $slug,
        string $type,
        string $status,
        \DateTimeImmutable $updatedAt,
        float $priority = 0.5,
        ?string $changefreq = null,
    ) {
        $this->slug = $slug;
        $this->type = $type;
        $this->status = $status;
        $this->updatedAt = $updatedAt;
        $this->priority = $priority;
        $this->changefreq = $changefreq;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getPriority(): float
    {
        return $this->priority;
    }

    public function getChangefreq(): ?string
    {
        return $this->changefreq;
    }
}
