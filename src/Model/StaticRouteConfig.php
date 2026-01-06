<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Model;

use Ecourty\SitemapBundle\Enum\ChangeFrequency;

readonly class StaticRouteConfig
{
    public function __construct(
        public string $route,
        public float $priority,
        public ChangeFrequency $changefreq,
        public ?string $lastmodRelative = null,
    ) {
    }
}
