<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Model;

use Ecourty\SitemapBundle\Enum\ChangeFrequency;

readonly class SitemapUrl
{
    public function __construct(
        public string $loc,
        public float $priority,
        public ChangeFrequency $changefreq,
        public ?\DateTimeInterface $lastmod = null,
    ) {
    }
}
