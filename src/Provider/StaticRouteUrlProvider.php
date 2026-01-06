<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Provider;

use Ecourty\SitemapBundle\Contract\UrlProviderInterface;
use Ecourty\SitemapBundle\Exception\InvalidConfigurationException;
use Ecourty\SitemapBundle\Model\SitemapUrl;
use Ecourty\SitemapBundle\Model\StaticRouteConfig;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StaticRouteUrlProvider implements UrlProviderInterface
{
    /**
     * @param array<StaticRouteConfig> $configs
     */
    public function __construct(
        private readonly array $configs,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $baseUrl,
    ) {
    }

    public function getUrls(): iterable
    {
        foreach ($this->configs as $config) {
            try {
                $path = $this->urlGenerator->generate(
                    $config->route,
                    [],
                    UrlGeneratorInterface::ABSOLUTE_PATH,
                );
            } catch (RouteNotFoundException $e) {
                throw new InvalidConfigurationException(
                    \sprintf('Route "%s" does not exist in routing configuration', $config->route),
                    0,
                    $e,
                );
            }

            $url = \rtrim($this->baseUrl, '/') . $path;

            $lastmod = null;
            if ($config->lastmodRelative !== null) {
                try {
                    $lastmod = new \DateTime($config->lastmodRelative);
                } catch (\Exception $e) {
                    throw new InvalidConfigurationException(
                        \sprintf('Invalid lastmod relative time string "%s" for route "%s"', $config->lastmodRelative, $config->route),
                        0,
                        $e,
                    );
                }
            }

            yield new SitemapUrl(
                loc: $url,
                priority: $config->priority,
                changefreq: $config->changefreq,
                lastmod: $lastmod,
            );
        }
    }

    public function getSourceName(): string
    {
        return 'static';
    }

    public function count(): int
    {
        return \count($this->configs);
    }
}
