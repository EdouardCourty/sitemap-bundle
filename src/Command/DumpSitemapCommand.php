<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Command;

use Ecourty\SitemapBundle\Contract\SitemapGeneratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sitemap:dump',
    description: 'Generate sitemap XML file(s)',
)]
class DumpSitemapCommand extends Command
{
    public function __construct(
        private readonly SitemapGeneratorInterface $generator,
        private readonly string $publicDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output directory (absolute or relative to public dir)', '')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $outputPath = $input->getOption('output');
        \assert(\is_string($outputPath));

        // Determine the output directory
        if ($outputPath === '') {
            $directory = $this->publicDir;
        } elseif (!\str_starts_with($outputPath, '/')) {
            $directory = $this->publicDir . '/' . \rtrim($outputPath, '/');
        } else {
            $directory = \rtrim($outputPath, '/');
        }

        // Ensure directory exists
        if (!\is_dir($directory)) {
            if (!\mkdir($directory, 0755, true) && !\is_dir($directory)) {
                $io->error(\sprintf('Failed to create directory: %s', $directory));

                return Command::FAILURE;
            }
        }

        $force = $input->getOption('force');
        \assert(\is_bool($force));

        $startTime = \microtime(true);

        try {
            $this->generator->generateToDirectory($directory, force: $force);
        } catch (\Exception $e) {
            $io->error(\sprintf('Failed to generate sitemap: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $duration = \microtime(true) - $startTime;

        $io->success(\sprintf('Sitemap(s) generated in %.2f seconds in: %s', $duration, $directory));

        return Command::SUCCESS;
    }
}
