<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Integration;

use Ecourty\SitemapBundle\Contract\SitemapGeneratorInterface;
use Ecourty\SitemapBundle\Tests\DatabaseTestCase;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Product;

class DqlConditionsTest extends DatabaseTestCase
{
    private SitemapGeneratorInterface $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $generator = self::getContainer()->get(SitemapGeneratorInterface::class);
        \assert($generator instanceof SitemapGeneratorInterface);
        $this->generator = $generator;
    }

    public function testDqlConditionsFilterUnpublishedProducts(): void
    {
        // Arrange: Create products with different published states
        $product1 = new Product('product-1', 'Published Product 1', true, new \DateTimeImmutable('2026-01-01'));
        $product2 = new Product('product-2', 'Unpublished Product', false, new \DateTimeImmutable('2026-01-02'));
        $product3 = new Product('product-3', 'Published Product 2', true, new \DateTimeImmutable('2026-01-03'));
        $product4 = new Product('product-4', 'Another Unpublished', false, new \DateTimeImmutable('2026-01-04'));

        $this->entityManager->persist($product1);
        $this->entityManager->persist($product2);
        $this->entityManager->persist($product3);
        $this->entityManager->persist($product4);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Act: Generate sitemap
        $xml = $this->generator->generate();

        // Assert: Only published products should be in sitemap
        $this->assertStringContainsString('https://example.com/product/product-1</loc>', $xml);
        $this->assertStringContainsString('https://example.com/product/product-3</loc>', $xml);
        $this->assertStringNotContainsString('https://example.com/product/product-2</loc>', $xml);
        $this->assertStringNotContainsString('https://example.com/product/product-4</loc>', $xml);

        // Assert: Verify count is correct (only 2 published products)
        $productUrlCount = \substr_count($xml, '<loc>https://example.com/product/');
        $this->assertEquals(2, $productUrlCount, 'Should only have 2 published products in sitemap');
    }

    public function testDqlConditionsWithLastmodProperty(): void
    {
        // Arrange
        $createdDate = new \DateTimeImmutable('2026-01-10 14:30:00');
        $product = new Product('special-product', 'Special Product', true, $createdDate);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: Product should have correct lastmod date
        $this->assertStringContainsString('https://example.com/product/special-product</loc>', $xml);
        $this->assertStringContainsString('<lastmod>2026-01-10</lastmod>', $xml);
    }

    public function testDqlConditionsWithEmptyResult(): void
    {
        // Arrange: Create only unpublished products
        $product1 = new Product('unpub-1', 'Unpublished 1', false, new \DateTimeImmutable());
        $product2 = new Product('unpub-2', 'Unpublished 2', false, new \DateTimeImmutable());

        $this->entityManager->persist($product1);
        $this->entityManager->persist($product2);
        $this->entityManager->flush();

        // Act
        $xml = $this->generator->generate();

        // Assert: No products should be in sitemap
        $productUrlCount = \substr_count($xml, '<loc>https://example.com/product/');
        $this->assertEquals(0, $productUrlCount, 'Should have no products in sitemap when all are unpublished');

        // Assert: Sitemap should still be valid (contain static routes)
        $this->assertStringContainsString('<loc>https://example.com/</loc>', $xml); // home route
    }
}
