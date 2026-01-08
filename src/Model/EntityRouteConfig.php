<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Model;

use Ecourty\SitemapBundle\Enum\ChangeFrequency;

readonly class EntityRouteConfig
{
    /**
     * @param array<string, string> $routeParams
     * @param list<string>|null $conditions
     */
    public function __construct(
        public string $entity,
        public string $route,
        public array $routeParams,
        public float $priority,
        public ChangeFrequency $changefreq,
        public ?string $lastmodProperty = null,
        public ?string $queryBuilderMethod = null,
        public ?array $conditions = null,
    ) {
    }
}
