<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Service;

use Ecourty\SitemapBundle\Contract\UrlProviderInterface;
use Ecourty\SitemapBundle\Model\SitemapUrl;

class UrlProviderRegistry
{
    /**
     * @var array<UrlProviderInterface>
     */
    private array $providers = [];

    /**
     * @param iterable<UrlProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[] = $provider;
        }
    }

    /**
     * @return iterable<string, iterable<SitemapUrl>>
     */
    public function getAllUrlsBySource(): iterable
    {
        foreach ($this->providers as $provider) {
            yield $provider->getSourceName() => $provider->getUrls();
        }
    }

    /**
     * @return iterable<SitemapUrl>
     */
    public function getAllUrls(): iterable
    {
        foreach ($this->providers as $provider) {
            yield from $provider->getUrls();
        }
    }

    public function count(): int
    {
        $count = 0;

        foreach ($this->providers as $provider) {
            $count += $provider->count();
        }

        return $count;
    }
}
