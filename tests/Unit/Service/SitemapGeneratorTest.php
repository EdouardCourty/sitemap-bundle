<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Unit\Service;

use Ecourty\SitemapBundle\Contract\UrlProviderInterface;
use Ecourty\SitemapBundle\Enum\ChangeFrequency;
use Ecourty\SitemapBundle\Model\SitemapUrl;
use Ecourty\SitemapBundle\Service\SitemapGenerator;
use Ecourty\SitemapBundle\Service\SitemapIndexWriter;
use Ecourty\SitemapBundle\Service\UrlProviderRegistry;
use Ecourty\SitemapBundle\Service\XmlWriter;
use PHPUnit\Framework\TestCase;

class SitemapGeneratorTest extends TestCase
{
    private UrlProviderRegistry $registry;
    /** @var XmlWriter&\PHPUnit\Framework\MockObject\MockObject */
    private XmlWriter $xmlWriter;
    /** @var SitemapIndexWriter&\PHPUnit\Framework\MockObject\MockObject */
    private SitemapIndexWriter $indexWriter;

    protected function setUp(): void
    {
        $this->xmlWriter = $this->createMock(XmlWriter::class);
        $this->indexWriter = $this->createMock(SitemapIndexWriter::class);
    }

    public function testGenerateWithAutoModeAndBelowThreshold(): void
    {
        $urls = [
            new SitemapUrl('https://example.com/page1', 1.0, ChangeFrequency::DAILY),
            new SitemapUrl('https://example.com/page2', 0.8, ChangeFrequency::WEEKLY),
        ];

        $provider = $this->createMock(UrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);
        $provider->method('count')->willReturn(2);

        $this->registry = new UrlProviderRegistry([$provider]);

        $this->xmlWriter->expects($this->once())
            ->method('write')
            ->with($this->isInstanceOf(\Generator::class))
            ->willReturn('<urlset>...</urlset>');

        $this->indexWriter->expects($this->never())
            ->method('write');

        $generator = new SitemapGenerator(
            $this->registry,
            $this->xmlWriter,
            $this->indexWriter,
            'auto',
            50,
        );

        $result = $generator->generate();

        $this->assertSame('<urlset>...</urlset>', $result);
    }

    public function testGenerateWithAutoModeAndAboveThreshold(): void
    {
        $urls = \array_map(
            fn (int $i) => new SitemapUrl("https://example.com/page{$i}", 0.5, ChangeFrequency::WEEKLY),
            \range(1, 60),
        );

        $provider = $this->createMock(UrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);
        $provider->method('count')->willReturn(60);
        $provider->method('getSourceName')->willReturn('test');

        $this->registry = new UrlProviderRegistry([$provider]);

        $this->indexWriter->expects($this->once())
            ->method('write')
            ->willReturn(['index' => '<sitemapindex>...</sitemapindex>', 'sitemaps' => []]);

        $this->xmlWriter->expects($this->never())
            ->method('write');

        $generator = new SitemapGenerator(
            $this->registry,
            $this->xmlWriter,
            $this->indexWriter,
            'auto',
            50,
        );

        $result = $generator->generate();

        $this->assertSame('<sitemapindex>...</sitemapindex>', $result);
    }

    public function testGenerateWithUseIndexTrue(): void
    {
        $urls = [
            new SitemapUrl('https://example.com/page1', 1.0, ChangeFrequency::DAILY),
        ];

        $provider = $this->createMock(UrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);
        $provider->method('count')->willReturn(1);
        $provider->method('getSourceName')->willReturn('test');

        $this->registry = new UrlProviderRegistry([$provider]);

        // Should use index even with 1 URL
        $this->indexWriter->expects($this->once())
            ->method('write')
            ->willReturn(['index' => '<sitemapindex>...</sitemapindex>', 'sitemaps' => []]);

        $this->xmlWriter->expects($this->never())
            ->method('write');

        $generator = new SitemapGenerator(
            $this->registry,
            $this->xmlWriter,
            $this->indexWriter,
            true, // Force index
            50,
        );

        $result = $generator->generate();

        $this->assertSame('<sitemapindex>...</sitemapindex>', $result);
    }

    public function testGenerateWithUseIndexFalse(): void
    {
        // Create 100 URLs (above threshold)
        $urls = \array_map(
            fn (int $i) => new SitemapUrl("https://example.com/page{$i}", 0.5, ChangeFrequency::WEEKLY),
            \range(1, 100),
        );

        $provider = $this->createMock(UrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);
        $provider->method('count')->willReturn(100);

        $this->registry = new UrlProviderRegistry([$provider]);

        // Should NOT use index even with 100 URLs (above threshold)
        $this->xmlWriter->expects($this->once())
            ->method('write')
            ->with($this->isInstanceOf(\Generator::class))
            ->willReturn('<urlset>...</urlset>');

        $this->indexWriter->expects($this->never())
            ->method('write');

        $generator = new SitemapGenerator(
            $this->registry,
            $this->xmlWriter,
            $this->indexWriter,
            false, // Disable index
            50,
        );

        $result = $generator->generate();

        $this->assertSame('<urlset>...</urlset>', $result);
    }

    public function testGenerateToFileThrowsIfFileExists(): void
    {
        $provider = $this->createMock(UrlProviderInterface::class);
        $provider->method('count')->willReturn(1);

        $this->registry = new UrlProviderRegistry([$provider]);

        $generator = new SitemapGenerator(
            $this->registry,
            $this->xmlWriter,
            $this->indexWriter,
            'auto',
            50,
        );

        $tempFile = \tempnam(\sys_get_temp_dir(), 'sitemap_test_');
        \assert($tempFile !== false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("File already exists: {$tempFile}");

        try {
            $generator->generateToFile($tempFile, false);
        } finally {
            @\unlink($tempFile);
        }
    }

    public function testGenerateToFileWithForceOverwrites(): void
    {
        $urls = [
            new SitemapUrl('https://example.com/page1', 1.0, ChangeFrequency::DAILY),
        ];

        $provider = $this->createMock(UrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);
        $provider->method('count')->willReturn(1);

        $this->registry = new UrlProviderRegistry([$provider]);

        $this->xmlWriter->expects($this->once())
            ->method('writeToFile')
            ->with($this->isInstanceOf(\Generator::class), $this->anything());

        $generator = new SitemapGenerator(
            $this->registry,
            $this->xmlWriter,
            $this->indexWriter,
            'auto',
            50,
        );

        $tempFile = \tempnam(\sys_get_temp_dir(), 'sitemap_test_');
        \assert($tempFile !== false);

        try {
            $generator->generateToFile($tempFile, true);
        } finally {
            @\unlink($tempFile);
        }
    }

    public function testCountUrls(): void
    {
        $provider1 = $this->createMock(UrlProviderInterface::class);
        $provider1->method('count')->willReturn(10);

        $provider2 = $this->createMock(UrlProviderInterface::class);
        $provider2->method('count')->willReturn(25);

        $this->registry = new UrlProviderRegistry([$provider1, $provider2]);

        $generator = new SitemapGenerator(
            $this->registry,
            $this->xmlWriter,
            $this->indexWriter,
            'auto',
            50,
        );

        $this->assertSame(35, $generator->countUrls());
    }

    public function testGenerateToFileUsesIndexWhenNeeded(): void
    {
        $urls = \array_map(
            fn (int $i) => new SitemapUrl("https://example.com/page{$i}", 0.5, ChangeFrequency::WEEKLY),
            \range(1, 60),
        );

        $provider = $this->createMock(UrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);
        $provider->method('count')->willReturn(60);
        $provider->method('getSourceName')->willReturn('test');

        $this->registry = new UrlProviderRegistry([$provider]);

        $this->indexWriter->expects($this->once())
            ->method('writeToFile')
            ->with($this->anything(), '/tmp/sitemap.xml');

        $this->xmlWriter->expects($this->never())
            ->method('writeToFile');

        $generator = new SitemapGenerator(
            $this->registry,
            $this->xmlWriter,
            $this->indexWriter,
            'auto',
            50,
        );

        $generator->generateToFile('/tmp/sitemap.xml', true);
    }
}
