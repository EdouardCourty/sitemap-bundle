<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Unit\Service;

use Ecourty\SitemapBundle\Enum\ChangeFrequency;
use Ecourty\SitemapBundle\Exception\FileWriteException;
use Ecourty\SitemapBundle\Model\SitemapUrl;
use Ecourty\SitemapBundle\Service\XmlWriter;
use PHPUnit\Framework\TestCase;

class XmlWriterTest extends TestCase
{
    private XmlWriter $writer;

    protected function setUp(): void
    {
        $this->writer = new XmlWriter();
    }

    public function testWriteEmptyUrlset(): void
    {
        $xml = $this->writer->write([]);

        self::assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', $xml);
    }

    public function testWriteSingleUrl(): void
    {
        $urls = [
            new SitemapUrl(
                loc: 'https://example.com/page',
                priority: 0.8,
                changefreq: ChangeFrequency::DAILY,
                lastmod: new \DateTime('2026-01-05'),
            ),
        ];

        $xml = $this->writer->write($urls);

        self::assertStringContainsString('<loc>https://example.com/page</loc>', $xml);
        self::assertStringContainsString('<priority>0.8</priority>', $xml);
        self::assertStringContainsString('<changefreq>daily</changefreq>', $xml);
        self::assertStringContainsString('<lastmod>2026-01-05</lastmod>', $xml);
    }

    public function testWriteUrlWithoutLastmod(): void
    {
        $urls = [
            new SitemapUrl(
                loc: 'https://example.com/page',
                priority: 1.0,
                changefreq: ChangeFrequency::WEEKLY,
            ),
        ];

        $xml = $this->writer->write($urls);

        self::assertStringContainsString('<loc>https://example.com/page</loc>', $xml);
        self::assertStringNotContainsString('<lastmod>', $xml);
    }

    public function testWriteMultipleUrls(): void
    {
        $urls = [
            new SitemapUrl(
                loc: 'https://example.com/page1',
                priority: 1.0,
                changefreq: ChangeFrequency::DAILY,
            ),
            new SitemapUrl(
                loc: 'https://example.com/page2',
                priority: 0.5,
                changefreq: ChangeFrequency::WEEKLY,
            ),
        ];

        $xml = $this->writer->write($urls);

        self::assertStringContainsString('https://example.com/page1', $xml);
        self::assertStringContainsString('https://example.com/page2', $xml);
    }

    public function testWriteToFileThrowsExceptionOnFailure(): void
    {
        $urls = [
            new SitemapUrl(
                loc: 'https://example.com/page',
                priority: 1.0,
                changefreq: ChangeFrequency::DAILY,
            ),
        ];

        $this->expectException(FileWriteException::class);
        $this->expectExceptionMessage('Cannot write to file: /invalid/path/sitemap.xml');

        $this->writer->writeToFile($urls, '/invalid/path/sitemap.xml');
    }
}
