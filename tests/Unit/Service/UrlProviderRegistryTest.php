<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Unit\Service;

use Ecourty\SitemapBundle\Contract\UrlProviderInterface;
use Ecourty\SitemapBundle\Enum\ChangeFrequency;
use Ecourty\SitemapBundle\Model\SitemapUrl;
use Ecourty\SitemapBundle\Service\UrlProviderRegistry;
use PHPUnit\Framework\TestCase;

class UrlProviderRegistryTest extends TestCase
{
    public function testGetAllUrlsBySource(): void
    {
        $provider1 = $this->createMock(UrlProviderInterface::class);
        $provider1->method('getSourceName')->willReturn('source1');
        $provider1->method('getUrls')->willReturn([
            new SitemapUrl(
                loc: 'https://example.com/page1',
                priority: 1.0,
                changefreq: ChangeFrequency::DAILY,
            ),
        ]);

        $provider2 = $this->createMock(UrlProviderInterface::class);
        $provider2->method('getSourceName')->willReturn('source2');
        $provider2->method('getUrls')->willReturn([
            new SitemapUrl(
                loc: 'https://example.com/page2',
                priority: 0.8,
                changefreq: ChangeFrequency::WEEKLY,
            ),
        ]);

        $registry = new UrlProviderRegistry([$provider1, $provider2]);
        $urlsBySource = $registry->getAllUrlsBySource();

        // Convert generator to array for assertions
        $urlsBySourceArray = [];
        foreach ($urlsBySource as $source => $urls) {
            $urlsBySourceArray[$source] = \iterator_to_array($urls);
        }

        self::assertArrayHasKey('source1', $urlsBySourceArray);
        self::assertArrayHasKey('source2', $urlsBySourceArray);
        self::assertCount(1, $urlsBySourceArray['source1']);
        self::assertCount(1, $urlsBySourceArray['source2']);
        self::assertSame('https://example.com/page1', $urlsBySourceArray['source1'][0]->loc);
        self::assertSame('https://example.com/page2', $urlsBySourceArray['source2'][0]->loc);
    }

    public function testGetAllUrls(): void
    {
        $provider1 = $this->createMock(UrlProviderInterface::class);
        $provider1->method('getUrls')->willReturn([
            new SitemapUrl(
                loc: 'https://example.com/page1',
                priority: 1.0,
                changefreq: ChangeFrequency::DAILY,
            ),
        ]);

        $provider2 = $this->createMock(UrlProviderInterface::class);
        $provider2->method('getUrls')->willReturn([
            new SitemapUrl(
                loc: 'https://example.com/page2',
                priority: 0.8,
                changefreq: ChangeFrequency::WEEKLY,
            ),
        ]);

        $registry = new UrlProviderRegistry([$provider1, $provider2]);
        $urls = $registry->getAllUrls();

        // Convert generator to array for assertions (preserve_keys=false to avoid key collisions)
        $urlsArray = \iterator_to_array($urls, false);

        self::assertCount(2, $urlsArray);
        self::assertSame('https://example.com/page1', $urlsArray[0]->loc);
        self::assertSame('https://example.com/page2', $urlsArray[1]->loc);
    }

    public function testCount(): void
    {
        $provider1 = $this->createMock(UrlProviderInterface::class);
        $provider1->method('getUrls')->willReturn([
            new SitemapUrl(
                loc: 'https://example.com/page1',
                priority: 1.0,
                changefreq: ChangeFrequency::DAILY,
            ),
        ]);
        $provider1->method('count')->willReturn(1);

        $provider2 = $this->createMock(UrlProviderInterface::class);
        $provider2->method('getUrls')->willReturn([
            new SitemapUrl(
                loc: 'https://example.com/page2',
                priority: 0.8,
                changefreq: ChangeFrequency::WEEKLY,
            ),
            new SitemapUrl(
                loc: 'https://example.com/page3',
                priority: 0.5,
                changefreq: ChangeFrequency::MONTHLY,
            ),
        ]);
        $provider2->method('count')->willReturn(2);

        $registry = new UrlProviderRegistry([$provider1, $provider2]);

        self::assertSame(3, $registry->count());
    }

    public function testEmptyRegistry(): void
    {
        $registry = new UrlProviderRegistry([]);

        self::assertSame([], \iterator_to_array($registry->getAllUrls()));
        self::assertSame([], \iterator_to_array($registry->getAllUrlsBySource()));
        self::assertSame(0, $registry->count());
    }
}
