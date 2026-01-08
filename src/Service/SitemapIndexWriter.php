<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Service;

use Ecourty\SitemapBundle\Exception\FileWriteException;
use Ecourty\SitemapBundle\Model\SitemapUrl;

class SitemapIndexWriter
{
    private const SITEMAP_MAX_URLS = 50000;

    public function __construct(
        private readonly XmlWriter $xmlWriter,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @param iterable<string, iterable<SitemapUrl>> $urlsBySource
     * @return array{index: string, sitemaps: array<string, string>}
     */
    public function write(iterable $urlsBySource): array
    {
        $sitemapFiles = [];
        $sitemaps = [];

        foreach ($urlsBySource as $sourceName => $urls) {
            $chunks = $this->processSourceChunks($sourceName, $urls);

            foreach ($chunks as $chunkData) {
                $sitemaps[$chunkData['filename']] = $this->xmlWriter->write($chunkData['urls']);
                $sitemapFiles[] = [
                    'loc' => $this->baseUrl . '/' . $chunkData['filename'],
                    'lastmod' => new \DateTime(),
                ];
            }
        }

        $index = $this->writeIndex($sitemapFiles);

        return [
            'index' => $index,
            'sitemaps' => $sitemaps,
        ];
    }

    /**
     * @param iterable<string, iterable<SitemapUrl>> $urlsBySource
     */
    public function writeToDirectory(iterable $urlsBySource, string $directory): void
    {
        $directory = \rtrim($directory, '/');
        $sitemapFiles = [];

        foreach ($urlsBySource as $sourceName => $urls) {
            $chunks = $this->processSourceChunks($sourceName, $urls);

            foreach ($chunks as $chunkData) {
                $filepath = $directory . '/' . $chunkData['filename'];
                $this->xmlWriter->writeToFile($chunkData['urls'], $filepath);
                $sitemapFiles[] = [
                    'loc' => $this->baseUrl . '/' . $chunkData['filename'],
                    'lastmod' => new \DateTime(),
                ];
            }
        }

        $indexPath = $directory . '/sitemap.xml';
        $this->writeIndexFile($sitemapFiles, $indexPath);
    }

    /**
     * Process chunks for a source and generate filenames.
     *
     * @param iterable<SitemapUrl> $urls
     * @return \Generator<array{filename: string, urls: array<SitemapUrl>}>
     */
    private function processSourceChunks(string $sourceName, iterable $urls): \Generator
    {
        // Always chunk at sitemap protocol limit (50000 URLs per file)
        $chunks = $this->chunkUrls($urls, self::SITEMAP_MAX_URLS);
        $totalChunks = 0;

        // Collect all chunks to determine total count
        $collectedChunks = [];
        foreach ($chunks as $chunk) {
            $collectedChunks[] = $chunk;
            $totalChunks++;
        }

        // Generate filenames based on whether there are multiple chunks
        foreach ($collectedChunks as $index => $chunk) {
            $filename = $totalChunks === 1
                ? \sprintf('sitemap_%s.xml', $sourceName)
                : \sprintf('sitemap_%s_%d.xml', $sourceName, $index + 1);

            yield [
                'filename' => $filename,
                'urls' => $chunk['urls'],
            ];
        }
    }

    /**
     * @param array<array{loc: string, lastmod: \DateTime}> $sitemapFiles
     */
    private function writeIndex(array $sitemapFiles): string
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);

        $xml->startElement('sitemapindex');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($sitemapFiles as $sitemap) {
            $xml->startElement('sitemap');

            $xml->startElement('loc');
            $xml->text($sitemap['loc']);
            $xml->endElement();

            $xml->startElement('lastmod');
            $xml->text($sitemap['lastmod']->format('c'));
            $xml->endElement();

            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * @param array<array{loc: string, lastmod: \DateTime}> $sitemapFiles
     */
    private function writeIndexFile(array $sitemapFiles, string $path): void
    {
        $xml = $this->writeIndex($sitemapFiles);
        $result = @\file_put_contents($path, $xml);

        if ($result === false) {
            throw new FileWriteException(\sprintf('Cannot write index file: %s', $path));
        }
    }

    /**
     * Chunks URLs from an iterable into batches without loading all in memory.
     *
     * @param iterable<SitemapUrl> $urls
     * @return \Generator<array{urls: array<SitemapUrl>, chunk_number: int, total_urls: int}>
     */
    private function chunkUrls(iterable $urls, int $chunkSize): \Generator
    {
        $chunk = [];
        $chunkNumber = 0;
        $totalUrls = 0;

        foreach ($urls as $url) {
            $chunk[] = $url;
            $totalUrls++;

            if (\count($chunk) === $chunkSize) {
                $chunkNumber++;
                yield ['urls' => $chunk, 'chunk_number' => $chunkNumber, 'total_urls' => $totalUrls];
                $chunk = [];
            }
        }

        // Yield remaining URLs if any
        if (\count($chunk) > 0) {
            $chunkNumber++;
            yield ['urls' => $chunk, 'chunk_number' => $chunkNumber, 'total_urls' => $totalUrls];
        }
    }
}
