<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Fixtures\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Ecourty\SitemapBundle\Tests\Fixtures\Repository\SongRepository::class)]
#[ORM\Table(name: 'songs')]
class Song
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 36, unique: true)]
    private string $uid;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $uid,
        string $title,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->uid = $uid;
        $this->title = $title;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
