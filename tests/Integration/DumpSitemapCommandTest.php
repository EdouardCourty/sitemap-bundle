<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\Integration;

use Ecourty\SitemapBundle\Command\DumpSitemapCommand;
use Ecourty\SitemapBundle\Tests\DatabaseTestCase;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Article;
use Ecourty\SitemapBundle\Tests\Fixtures\Entity\Song;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DumpSitemapCommandTest extends DatabaseTestCase
{
    private DumpSitemapCommand $command;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $command = self::getContainer()->get(DumpSitemapCommand::class);
        \assert($command instanceof DumpSitemapCommand);
        $this->command = $command;

        $this->tempDir = \sys_get_temp_dir() . '/sitemap-test-' . \uniqid();
        \mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    public function testDumpSimpleSitemap(): void
    {
        // Arrange: Insert test data
        $article = new Article(
            'test-article',
            'Test Article',
            'Content',
            new \DateTimeImmutable('2026-01-05'),
        );
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        // Act: Execute command
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--output' => $this->tempDir]);

        // Assert: Command succeeded
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Sitemap(s) generated', $tester->getDisplay());

        // Assert: Sitemap file was created
        $sitemapFile = $this->tempDir . '/sitemap.xml';
        $this->assertFileExists($sitemapFile);

        // Assert: Sitemap content is valid
        $content = \file_get_contents($sitemapFile);
        $this->assertNotFalse($content);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $content);
        $this->assertStringContainsString('<loc>https://example.com/</loc>', $content);
        $this->assertStringContainsString('<loc>https://example.com/article/test-article</loc>', $content);
        $this->assertStringContainsString('</urlset>', $content);
    }

    public function testDumpSitemapIndex(): void
    {
        // Arrange: Insert enough data to trigger index generation
        for ($i = 1; $i <= 100; ++$i) {
            $article = new Article(
                'article-' . $i,
                'Article ' . $i,
                'Content ' . $i,
                new \DateTimeImmutable(),
            );
            $this->entityManager->persist($article);

            if ($i % 20 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }
        $this->entityManager->flush();

        // Act: Execute command
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--output' => $this->tempDir]);

        // Assert: Command succeeded
        $this->assertSame(Command::SUCCESS, $exitCode);

        // Assert: Index file was created
        $indexFile = $this->tempDir . '/sitemap.xml';
        $this->assertFileExists($indexFile);

        // Assert: Individual sitemap files were created
        $staticFile = $this->tempDir . '/sitemap_static.xml';
        $entityFile = $this->tempDir . '/sitemap_entity_article.xml';
        $this->assertFileExists($staticFile);
        $this->assertFileExists($entityFile);

        // Assert: Index content references sub-sitemaps
        $indexContent = \file_get_contents($indexFile);
        $this->assertNotFalse($indexContent);

        $this->assertStringContainsString('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $indexContent);
        $this->assertStringContainsString('<loc>https://example.com/sitemap_static.xml</loc>', $indexContent);
        $this->assertStringContainsString('<loc>https://example.com/sitemap_entity_article.xml</loc>', $indexContent);
    }

    public function testDumpWithForceOverwritesExistingFiles(): void
    {
        // Arrange: Create existing sitemap file
        $sitemapFile = $this->tempDir . '/sitemap.xml';
        \file_put_contents($sitemapFile, 'old content');
        $originalMtime = \filemtime($sitemapFile);

        \sleep(1); // Ensure mtime will be different

        // Act: Execute command without force (should fail)
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--output' => $this->tempDir]);

        // Assert: Command failed
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('already exists', $tester->getDisplay());

        // Assert: File was not modified
        $this->assertSame($originalMtime, \filemtime($sitemapFile));

        // Act: Execute command with force
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--output' => $this->tempDir, '--force' => true]);

        // Assert: Command succeeded
        $this->assertSame(Command::SUCCESS, $exitCode);

        // Assert: File was overwritten
        $content = \file_get_contents($sitemapFile);
        $this->assertNotFalse($content);
        $this->assertStringNotContainsString('old content', $content);
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
    }

    public function testDumpCreatesNestedDirectories(): void
    {
        // Arrange: Non-existing nested directory
        $nestedDir = $this->tempDir . '/nested/sub/directory';

        // Act: Execute command
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--output' => $nestedDir]);

        // Assert: Command succeeded and directory was created
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertDirectoryExists($nestedDir);
        $this->assertFileExists($nestedDir . '/sitemap.xml');
    }

    public function testDumpWithMultipleEntities(): void
    {
        // Arrange: Insert different entity types
        $article = new Article(
            'my-article',
            'My Article',
            'Content',
            new \DateTimeImmutable('2026-01-01'),
        );
        $song = new Song(
            'xyz-789',
            'My Song',
            new \DateTimeImmutable('2026-01-02'),
        );

        $this->entityManager->persist($article);
        $this->entityManager->persist($song);
        $this->entityManager->flush();

        // Act: Execute command
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--output' => $this->tempDir]);

        // Assert: Command succeeded
        $this->assertSame(Command::SUCCESS, $exitCode);

        // Assert: Sitemap contains both entities
        $sitemapFile = $this->tempDir . '/sitemap.xml';
        $content = \file_get_contents($sitemapFile);
        $this->assertNotFalse($content);

        $this->assertStringContainsString('/article/my-article</loc>', $content);
        $this->assertStringContainsString('/song/xyz-789</loc>', $content);
    }

    public function testDumpWithEmptyDatabase(): void
    {
        // Act: Execute command with no entities in database
        $tester = new CommandTester($this->command);
        $exitCode = $tester->execute(['--output' => $this->tempDir]);

        // Assert: Command succeeded
        $this->assertSame(Command::SUCCESS, $exitCode);

        // Assert: Sitemap was created with only static routes
        $sitemapFile = $this->tempDir . '/sitemap.xml';
        $this->assertFileExists($sitemapFile);

        $content = \file_get_contents($sitemapFile);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('<loc>https://example.com/</loc>', $content);
        $this->assertStringNotContainsString('/article/', $content);
        $this->assertStringNotContainsString('/song/', $content);
    }

    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $files = \array_diff(\scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            \is_dir($path) ? $this->removeDirectory($path) : \unlink($path);
        }
        \rmdir($dir);
    }
}
