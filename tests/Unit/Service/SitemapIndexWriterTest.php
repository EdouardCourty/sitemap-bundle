<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Unit\Service;

use Ecourty\SitemapBundle\Enum\ChangeFrequency;
use Ecourty\SitemapBundle\Exception\FileWriteException;
use Ecourty\SitemapBundle\Model\SitemapUrl;
use Ecourty\SitemapBundle\Service\SitemapIndexWriter;
use Ecourty\SitemapBundle\Service\XmlWriter;
use PHPUnit\Framework\TestCase;

class SitemapIndexWriterTest extends TestCase
{
    private XmlWriter $xmlWriter;
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->xmlWriter = new XmlWriter();
        $this->baseUrl = 'https://example.com';
    }

    public function testWriteIndexWithSingleSource(): void
    {
        $urlsBySource = [
            'static' => [
                new SitemapUrl('https://example.com/', 1.0, ChangeFrequency::DAILY),
                new SitemapUrl('https://example.com/about', 0.8, ChangeFrequency::WEEKLY),
            ],
        ];

        $writer = new SitemapIndexWriter($this->xmlWriter, $this->baseUrl);
        $result = $writer->write($urlsBySource);

        $this->assertCount(1, $result['sitemaps']);
        $this->assertArrayHasKey('sitemap_static.xml', $result['sitemaps']);

        $index = $result['index'];
        $this->assertStringContainsString('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $index);
        $this->assertStringContainsString('<loc>https://example.com/sitemap_static.xml</loc>', $index);
        $this->assertStringContainsString('<lastmod>', $index);
    }

    public function testWriteIndexWithMultipleSources(): void
    {
        $urlsBySource = [
            'static' => [
                new SitemapUrl('https://example.com/', 1.0, ChangeFrequency::DAILY),
            ],
            'entity_article' => [
                new SitemapUrl('https://example.com/article/1', 0.7, ChangeFrequency::MONTHLY),
            ],
            'entity_song' => [
                new SitemapUrl('https://example.com/song/1', 0.9, ChangeFrequency::WEEKLY),
            ],
        ];

        $writer = new SitemapIndexWriter($this->xmlWriter, $this->baseUrl);
        $result = $writer->write($urlsBySource);

        $this->assertCount(3, $result['sitemaps']);
        $this->assertArrayHasKey('sitemap_static.xml', $result['sitemaps']);
        $this->assertArrayHasKey('sitemap_entity_article.xml', $result['sitemaps']);
        $this->assertArrayHasKey('sitemap_entity_song.xml', $result['sitemaps']);

        $index = $result['index'];
        $this->assertStringContainsString('sitemap_static.xml', $index);
        $this->assertStringContainsString('sitemap_entity_article.xml', $index);
        $this->assertStringContainsString('sitemap_entity_song.xml', $index);
    }

    public function testChunkingWithMoreThan50kUrls(): void
    {
        $urls = [];
        for ($i = 1; $i <= 60000; $i++) {
            $urls[] = new SitemapUrl("https://example.com/page{$i}", 0.5, ChangeFrequency::WEEKLY);
        }

        $urlsBySource = ['large_source' => $urls];

        $writer = new SitemapIndexWriter($this->xmlWriter, $this->baseUrl);
        $result = $writer->write($urlsBySource);

        // Should create 2 files: sitemap_large_source_1.xml and sitemap_large_source_2.xml
        $this->assertCount(2, $result['sitemaps']);
        $this->assertArrayHasKey('sitemap_large_source_1.xml', $result['sitemaps']);
        $this->assertArrayHasKey('sitemap_large_source_2.xml', $result['sitemaps']);

        $index = $result['index'];
        $this->assertStringContainsString('sitemap_large_source_1.xml', $index);
        $this->assertStringContainsString('sitemap_large_source_2.xml', $index);

        // Verify chunk sizes
        $chunk1 = $result['sitemaps']['sitemap_large_source_1.xml'];
        $chunk2 = $result['sitemaps']['sitemap_large_source_2.xml'];

        $this->assertSame(50000, \substr_count($chunk1, '<url>'));
        $this->assertSame(10000, \substr_count($chunk2, '<url>'));
    }

    public function testChunkingWithExactly50kUrls(): void
    {
        $urls = [];
        for ($i = 1; $i <= 50000; $i++) {
            $urls[] = new SitemapUrl("https://example.com/page{$i}", 0.5, ChangeFrequency::WEEKLY);
        }

        $urlsBySource = ['exact_source' => $urls];

        $writer = new SitemapIndexWriter($this->xmlWriter, $this->baseUrl);
        $result = $writer->write($urlsBySource);

        // Should create 1 file (exactly at threshold)
        $this->assertCount(1, $result['sitemaps']);
        $this->assertArrayHasKey('sitemap_exact_source.xml', $result['sitemaps']);

        $xml = $result['sitemaps']['sitemap_exact_source.xml'];
        $this->assertSame(50000, \substr_count($xml, '<url>'));
    }

    public function testChunkingWithExactly50001Urls(): void
    {
        $urls = [];
        for ($i = 1; $i <= 50001; $i++) {
            $urls[] = new SitemapUrl("https://example.com/page{$i}", 0.5, ChangeFrequency::WEEKLY);
        }

        $urlsBySource = ['over_threshold' => $urls];

        $writer = new SitemapIndexWriter($this->xmlWriter, $this->baseUrl);
        $result = $writer->write($urlsBySource);

        // Should create 2 files
        $this->assertCount(2, $result['sitemaps']);
        $this->assertArrayHasKey('sitemap_over_threshold_1.xml', $result['sitemaps']);
        $this->assertArrayHasKey('sitemap_over_threshold_2.xml', $result['sitemaps']);

        $chunk1 = $result['sitemaps']['sitemap_over_threshold_1.xml'];
        $chunk2 = $result['sitemaps']['sitemap_over_threshold_2.xml'];

        $this->assertSame(50000, \substr_count($chunk1, '<url>'));
        $this->assertSame(1, \substr_count($chunk2, '<url>'));
    }

    public function testChunkingWith150kUrls(): void
    {
        $urls = [];
        for ($i = 1; $i <= 150000; $i++) {
            $urls[] = new SitemapUrl("https://example.com/page{$i}", 0.5, ChangeFrequency::WEEKLY);
        }

        $urlsBySource = ['huge_source' => $urls];

        $writer = new SitemapIndexWriter($this->xmlWriter, $this->baseUrl);
        $result = $writer->write($urlsBySource);

        // Should create 3 files
        $this->assertCount(3, $result['sitemaps']);
        $this->assertArrayHasKey('sitemap_huge_source_1.xml', $result['sitemaps']);
        $this->assertArrayHasKey('sitemap_huge_source_2.xml', $result['sitemaps']);
        $this->assertArrayHasKey('sitemap_huge_source_3.xml', $result['sitemaps']);

        foreach (\range(1, 3) as $i) {
            $chunk = $result['sitemaps']["sitemap_huge_source_{$i}.xml"];
            $this->assertSame(50000, \substr_count($chunk, '<url>'));
        }
    }

    public function testWriteToFileThrowsExceptionOnInvalidPath(): void
    {
        $urlsBySource = [
            'static' => [
                new SitemapUrl('https://example.com/', 1.0, ChangeFrequency::DAILY),
            ],
        ];

        $writer = new SitemapIndexWriter($this->xmlWriter, $this->baseUrl);

        $this->expectException(FileWriteException::class);
        // Exception can be thrown when writing sitemap file or index file
        $this->expectExceptionMessageMatches('/Cannot write (to file|index file): \/invalid\/path\//');

        $writer->writeToFile($urlsBySource, '/invalid/path/sitemap.xml');
    }

    public function testIndexUsesBaseUrl(): void
    {
        $customBaseUrl = 'https://custom-domain.org';
        $urlsBySource = [
            'test' => [
                new SitemapUrl('https://example.com/test', 0.5, ChangeFrequency::WEEKLY),
            ],
        ];

        $writer = new SitemapIndexWriter($this->xmlWriter, $customBaseUrl);
        $result = $writer->write($urlsBySource);

        $index = $result['index'];
        $this->assertStringContainsString('<loc>https://custom-domain.org/sitemap_test.xml</loc>', $index);
    }

    public function testIndexContainsValidDateFormat(): void
    {
        $urlsBySource = [
            'static' => [
                new SitemapUrl('https://example.com/', 1.0, ChangeFrequency::DAILY),
            ],
        ];

        $writer = new SitemapIndexWriter($this->xmlWriter, $this->baseUrl);
        $result = $writer->write($urlsBySource);

        $index = $result['index'];

        // Check ISO 8601 format (e.g., 2026-01-05T23:30:00+00:00)
        $this->assertMatchesRegularExpression('/<lastmod>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}<\/lastmod>/', $index);
    }
}
