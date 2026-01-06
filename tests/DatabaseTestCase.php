<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base test class for integration tests that need Doctrine schema management.
 *
 * Automatically creates and drops the database schema in setUp/tearDown.
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    private function createSchema(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema($metadata);
    }

    private function dropSchema(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
    }
}
