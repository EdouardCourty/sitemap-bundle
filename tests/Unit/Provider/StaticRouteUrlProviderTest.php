<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Unit\Provider;

use Ecourty\SitemapBundle\Enum\ChangeFrequency;
use Ecourty\SitemapBundle\Exception\InvalidConfigurationException;
use Ecourty\SitemapBundle\Model\StaticRouteConfig;
use Ecourty\SitemapBundle\Provider\StaticRouteUrlProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StaticRouteUrlProviderTest extends TestCase
{
    public function testGetUrlsReturnsCorrectUrls(): void
    {
        $configs = [
            new StaticRouteConfig(
                route: 'home',
                priority: 1.0,
                changefreq: ChangeFrequency::DAILY,
                lastmodRelative: null,
            ),
            new StaticRouteConfig(
                route: 'about',
                priority: 0.8,
                changefreq: ChangeFrequency::WEEKLY,
                lastmodRelative: '-1 day',
            ),
        ];

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->exactly(2))
            ->method('generate')
            ->willReturnCallback(static fn (string $route): string => match ($route) {
                'home' => '/',
                'about' => '/about',
                default => throw new \InvalidArgumentException('Unknown route: ' . $route),
            });

        $provider = new StaticRouteUrlProvider($configs, $urlGenerator, 'https://example.com');
        $urls = \iterator_to_array($provider->getUrls());

        $this->assertCount(2, $urls);
        $this->assertSame('https://example.com/', $urls[0]->loc);
        $this->assertSame(1.0, $urls[0]->priority);
        $this->assertSame(ChangeFrequency::DAILY, $urls[0]->changefreq);
        $this->assertNull($urls[0]->lastmod);

        $this->assertSame('https://example.com/about', $urls[1]->loc);
        $this->assertSame(0.8, $urls[1]->priority);
        $this->assertInstanceOf(\DateTimeInterface::class, $urls[1]->lastmod);
    }

    public function testNonExistentRouteThrowsException(): void
    {
        $configs = [
            new StaticRouteConfig(
                route: 'non_existent_route',
                priority: 0.5,
                changefreq: ChangeFrequency::WEEKLY,
            ),
        ];

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->willThrowException(new RouteNotFoundException());

        $provider = new StaticRouteUrlProvider($configs, $urlGenerator, 'https://example.com');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Route "non_existent_route" does not exist in routing configuration');

        \iterator_to_array($provider->getUrls());
    }

    public function testInvalidLastmodRelativeThrowsException(): void
    {
        $configs = [
            new StaticRouteConfig(
                route: 'home',
                priority: 0.5,
                changefreq: ChangeFrequency::WEEKLY,
                lastmodRelative: 'invalid-date-format',
            ),
        ];

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->willReturn('/');

        $provider = new StaticRouteUrlProvider($configs, $urlGenerator, 'https://example.com');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid lastmod relative time string "invalid-date-format" for route "home"');

        \iterator_to_array($provider->getUrls());
    }

    public function testCountReturnsCorrectNumber(): void
    {
        $configs = [
            new StaticRouteConfig('route1', 0.5, ChangeFrequency::WEEKLY),
            new StaticRouteConfig('route2', 0.5, ChangeFrequency::WEEKLY),
            new StaticRouteConfig('route3', 0.5, ChangeFrequency::WEEKLY),
        ];

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $provider = new StaticRouteUrlProvider($configs, $urlGenerator, 'https://example.com');

        $this->assertSame(3, $provider->count());
    }

    public function testGetSourceName(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $provider = new StaticRouteUrlProvider([], $urlGenerator, 'https://example.com');

        $this->assertSame('static', $provider->getSourceName());
    }

    public function testEmptyConfigsReturnsEmptyArray(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $provider = new StaticRouteUrlProvider([], $urlGenerator, 'https://example.com');

        $urls = \iterator_to_array($provider->getUrls());

        $this->assertCount(0, $urls);
        $this->assertSame(0, $provider->count());
    }
}
