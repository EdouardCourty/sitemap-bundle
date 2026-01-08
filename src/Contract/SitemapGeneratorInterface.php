<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Contract;

interface SitemapGeneratorInterface
{
    public function generate(): string;

    public function generateToDirectory(string $directory, bool $force = false): void;

    public function countUrls(): int;
}
