<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Service;

use Ecourty\SitemapBundle\Contract\SitemapGeneratorInterface;

class SitemapGenerator implements SitemapGeneratorInterface
{
    public function __construct(
        private readonly UrlProviderRegistry $registry,
        private readonly XmlWriter $xmlWriter,
        private readonly SitemapIndexWriter $indexWriter,
        private readonly string|bool $useIndex,
        private readonly int $indexThreshold,
    ) {
    }

    public function generate(): string
    {
        $shouldUseIndex = $this->shouldUseIndex();

        if ($shouldUseIndex) {
            $urlsBySource = $this->registry->getAllUrlsBySource();
            $result = $this->indexWriter->write($urlsBySource);

            return $result['index'];
        }

        $urls = $this->registry->getAllUrls();

        return $this->xmlWriter->write($urls);
    }

    public function generateToFile(string $path, bool $force = false): void
    {
        if (!$force && \file_exists($path)) {
            throw new \RuntimeException(\sprintf('File already exists: %s', $path));
        }

        $shouldUseIndex = $this->shouldUseIndex();

        if ($shouldUseIndex) {
            $urlsBySource = $this->registry->getAllUrlsBySource();
            $this->indexWriter->writeToFile($urlsBySource, $path);
        } else {
            $urls = $this->registry->getAllUrls();
            $this->xmlWriter->writeToFile($urls, $path);
        }
    }

    public function countUrls(): int
    {
        return $this->registry->count();
    }

    private function shouldUseIndex(): bool
    {
        if ($this->useIndex === true) {
            return true;
        }

        if ($this->useIndex === false) {
            return false;
        }

        return $this->countUrls() > $this->indexThreshold;
    }
}
