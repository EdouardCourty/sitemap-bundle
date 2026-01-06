<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Service;

use Ecourty\SitemapBundle\Exception\FileWriteException;
use Ecourty\SitemapBundle\Model\SitemapUrl;

class XmlWriter
{
    /**
     * @param iterable<SitemapUrl> $urls
     */
    public function write(iterable $urls): string
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);

        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($urls as $url) {
            $xml->startElement('url');

            $xml->startElement('loc');
            $xml->text($url->loc);
            $xml->endElement();

            if ($url->lastmod !== null) {
                $xml->startElement('lastmod');
                $xml->text($url->lastmod->format('Y-m-d'));
                $xml->endElement();
            }

            $xml->startElement('changefreq');
            $xml->text($url->changefreq->value);
            $xml->endElement();

            $xml->startElement('priority');
            $xml->text((string) $url->priority);
            $xml->endElement();

            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * @param iterable<SitemapUrl> $urls
     */
    public function writeToFile(iterable $urls, string $path): void
    {
        $xml = $this->write($urls);
        $result = @\file_put_contents($path, $xml);

        if ($result === false) {
            throw new FileWriteException(\sprintf('Cannot write to file: %s', $path));
        }
    }
}
