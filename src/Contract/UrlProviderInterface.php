<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Contract;

use Ecourty\SitemapBundle\Model\SitemapUrl;

interface UrlProviderInterface
{
    /**
     * @return iterable<SitemapUrl>
     */
    public function getUrls(): iterable;

    /**
     * Returns the total number of URLs this provider will generate.
     */
    public function count(): int;

    public function getSourceName(): string;
}
