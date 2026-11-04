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

    public function testGenerateWithAutoModeAndBelowThreshold(): void
    {
        $xmlWriter = $this->createMock(XmlWriter::class);
        $indexWriter = $this->createMock(SitemapIndexWriter::class);

        $urls = [
            new SitemapUrl('https://example.com/page1', 1.0, ChangeFrequency::DAILY),
            new SitemapUrl('https://example.com/page2', 0.8, ChangeFrequency::WEEKLY),
        ];

        $provider = $this->createStub(UrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);
        $provider->method('count')->willReturn(2);

        $this->registry = new UrlProviderRegistry([$provider]);

        $xmlWriter->expects($this->once())
            ->method('write')
            ->with($this->isInstanceOf(\Generator::class))
            ->willReturn('<urlset>...</urlset>');

        $indexWriter->expects($this->never())
            ->method('write');

        $generator = new SitemapGenerator(
            $this->registry,
            $xmlWriter,
            $indexWriter,
            'auto',
            50,
        );

        $result = $generator->generate();

        $this->assertSame('<urlset>...</urlset>', $result);
    }

    public function testGenerateWithAutoModeAndAboveThreshold(): void
    {
        $xmlWriter = $this->createMock(XmlWriter::class);
        $indexWriter = $this->createMock(SitemapIndexWriter::class);

        $urls = \array_map(
            fn (int $i) => new SitemapUrl("https://example.com/page{$i}", 0.5, ChangeFrequency::WEEKLY),
            \range(1, 60),
        );

        $provider = $this->createStub(UrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);
        $provider->method('count')->willReturn(60);
        $provider->method('getSourceName')->willReturn('test');

        $this->registry = new UrlProviderRegistry([$provider]);

        $indexWriter->expects($this->once())
            ->method('write')
            ->willReturn(['index' => '<sitemapindex>...</sitemapindex>', 'sitemaps' => []]);

        $xmlWriter->expects($this->never())
            ->method('write');

        $generator = new SitemapGenerator(
            $this->registry,
            $xmlWriter,
            $indexWriter,
            'auto',
            50,
        );

        $result = $generator->generate();

        $this->assertSame('<sitemapindex>...</sitemapindex>', $result);
    }

    public function testGenerateWithUseIndexTrue(): void
    {
        $xmlWriter = $this->createMock(XmlWriter::class);
        $indexWriter = $this->createMock(SitemapIndexWriter::class);

        $urls = [
            new SitemapUrl('https://example.com/page1', 1.0, ChangeFrequency::DAILY),
        ];

        $provider = $this->createStub(UrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);
        $provider->method('count')->willReturn(1);
        $provider->method('getSourceName')->willReturn('test');

        $this->registry = new UrlProviderRegistry([$provider]);

        // Should use index even with 1 URL
        $indexWriter->expects($this->once())
            ->method('write')
            ->willReturn(['index' => '<sitemapindex>...</sitemapindex>', 'sitemaps' => []]);

        $xmlWriter->expects($this->never())
            ->method('write');

        $generator = new SitemapGenerator(
            $this->registry,
            $xmlWriter,
            $indexWriter,
            true, // Force index
            50,
        );

        $result = $generator->generate();

        $this->assertSame('<sitemapindex>...</sitemapindex>', $result);
    }

    public function testGenerateWithUseIndexFalse(): void
    {
        $xmlWriter = $this->createMock(XmlWriter::class);
        $indexWriter = $this->createMock(SitemapIndexWriter::class);

        // Create 100 URLs (above threshold)
        $urls = \array_map(
            fn (int $i) => new SitemapUrl("https://example.com/page{$i}", 0.5, ChangeFrequency::WEEKLY),
            \range(1, 100),
        );

        $provider = $this->createStub(UrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);
        $provider->method('count')->willReturn(100);

        $this->registry = new UrlProviderRegistry([$provider]);

        // Should NOT use index even with 100 URLs (above threshold)
        $xmlWriter->expects($this->once())
            ->method('write')
            ->with($this->isInstanceOf(\Generator::class))
            ->willReturn('<urlset>...</urlset>');

        $indexWriter->expects($this->never())
            ->method('write');

        $generator = new SitemapGenerator(
            $this->registry,
            $xmlWriter,
            $indexWriter,
            false, // Disable index
            50,
        );

        $result = $generator->generate();

        $this->assertSame('<urlset>...</urlset>', $result);
    }

    public function testGenerateToDirectoryThrowsIfFileExists(): void
    {
        $xmlWriter = $this->createStub(XmlWriter::class);
        $indexWriter = $this->createStub(SitemapIndexWriter::class);

        $provider = $this->createStub(UrlProviderInterface::class);
        $provider->method('count')->willReturn(1);

        $this->registry = new UrlProviderRegistry([$provider]);

        $generator = new SitemapGenerator(
            $this->registry,
            $xmlWriter,
            $indexWriter,
            'auto',
            50,
        );

        $tempDir = \sys_get_temp_dir() . '/sitemap_test_' . \uniqid();
        \mkdir($tempDir);
        $sitemapFile = $tempDir . '/sitemap.xml';
        \file_put_contents($sitemapFile, 'existing content');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("File already exists: {$sitemapFile}");

        try {
            $generator->generateToDirectory($tempDir, false);
        } finally {
            @\unlink($sitemapFile);
            @\rmdir($tempDir);
        }
    }

    public function testGenerateToDirectoryWithForceOverwrites(): void
    {
        $xmlWriter = $this->createMock(XmlWriter::class);
        $indexWriter = $this->createStub(SitemapIndexWriter::class);

        $urls = [
            new SitemapUrl('https://example.com/page1', 1.0, ChangeFrequency::DAILY),
        ];

        $provider = $this->createStub(UrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);
        $provider->method('count')->willReturn(1);

        $this->registry = new UrlProviderRegistry([$provider]);

        $tempDir = \sys_get_temp_dir() . '/sitemap_test_' . \uniqid();
        \mkdir($tempDir);
        $sitemapFile = $tempDir . '/sitemap.xml';

        $xmlWriter->expects($this->once())
            ->method('writeToFile')
            ->with($this->isInstanceOf(\Generator::class), $sitemapFile);

        $generator = new SitemapGenerator(
            $this->registry,
            $xmlWriter,
            $indexWriter,
            'auto',
            50,
        );

        try {
            $generator->generateToDirectory($tempDir, true);
        } finally {
            @\unlink($sitemapFile);
            @\rmdir($tempDir);
        }
    }

    public function testCountUrls(): void
    {
        $xmlWriter = $this->createStub(XmlWriter::class);
        $indexWriter = $this->createStub(SitemapIndexWriter::class);

        $provider1 = $this->createStub(UrlProviderInterface::class);
        $provider1->method('count')->willReturn(10);

        $provider2 = $this->createStub(UrlProviderInterface::class);
        $provider2->method('count')->willReturn(25);

        $this->registry = new UrlProviderRegistry([$provider1, $provider2]);

        $generator = new SitemapGenerator(
            $this->registry,
            $xmlWriter,
            $indexWriter,
            'auto',
            50,
        );

        $this->assertSame(35, $generator->countUrls());
    }

    public function testGenerateToDirectoryUsesIndexWhenNeeded(): void
    {
        $xmlWriter = $this->createMock(XmlWriter::class);
        $indexWriter = $this->createMock(SitemapIndexWriter::class);

        $urls = \array_map(
            fn (int $i) => new SitemapUrl("https://example.com/page{$i}", 0.5, ChangeFrequency::WEEKLY),
            \range(1, 60),
        );

        $provider = $this->createStub(UrlProviderInterface::class);
        $provider->method('getUrls')->willReturn($urls);
        $provider->method('count')->willReturn(60);
        $provider->method('getSourceName')->willReturn('test');

        $this->registry = new UrlProviderRegistry([$provider]);

        $indexWriter->expects($this->once())
            ->method('writeToDirectory')
            ->with($this->anything(), '/tmp');

        $xmlWriter->expects($this->never())
            ->method('writeToFile');

        $generator = new SitemapGenerator(
            $this->registry,
            $xmlWriter,
            $indexWriter,
            'auto',
            50,
        );

        $generator->generateToDirectory('/tmp', true);
    }
}
