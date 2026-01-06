<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Unit\Model;

use Ecourty\SitemapBundle\Enum\ChangeFrequency;
use Ecourty\SitemapBundle\Model\SitemapUrl;
use PHPUnit\Framework\TestCase;

class SitemapUrlTest extends TestCase
{
    public function testConstructorWithAllProperties(): void
    {
        $lastmod = new \DateTime('2026-01-05');
        $url = new SitemapUrl(
            loc: 'https://example.com/page',
            priority: 0.8,
            changefreq: ChangeFrequency::DAILY,
            lastmod: $lastmod,
        );

        self::assertSame('https://example.com/page', $url->loc);
        self::assertSame(0.8, $url->priority);
        self::assertSame(ChangeFrequency::DAILY, $url->changefreq);
        self::assertSame($lastmod, $url->lastmod);
    }

    public function testConstructorWithoutLastmod(): void
    {
        $url = new SitemapUrl(
            loc: 'https://example.com/page',
            priority: 1.0,
            changefreq: ChangeFrequency::WEEKLY,
        );

        self::assertSame('https://example.com/page', $url->loc);
        self::assertSame(1.0, $url->priority);
        self::assertSame(ChangeFrequency::WEEKLY, $url->changefreq);
        self::assertNull($url->lastmod);
    }
}
