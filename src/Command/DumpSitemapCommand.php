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
    private const DEFAULT_OUTPUT_PATH = '/sitemap.xml';

    public function __construct(
        private readonly SitemapGeneratorInterface $generator,
        private readonly string $publicDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output path (absolute or relative to public dir)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $outputPath = $input->getOption('output');
        \assert(\is_string($outputPath) || $outputPath === null);

        if ($outputPath === null) {
            $path = $this->publicDir . self::DEFAULT_OUTPUT_PATH;
        } elseif (!\str_starts_with($outputPath, '/')) {
            $path = $this->publicDir . '/' . $outputPath;
        } else {
            $path = $outputPath;
        }

        $force = $input->getOption('force');

        if (\file_exists($path) && !$force) {
            $io->warning(\sprintf('File already exists: %s', $path));
            if (!$io->confirm('Overwrite?', false)) {
                return Command::FAILURE;
            }
        }

        $startTime = \microtime(true);

        try {
            $this->generator->generateToFile($path, force: true);
        } catch (\Exception $e) {
            $io->error(\sprintf('Failed to generate sitemap: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $duration = \microtime(true) - $startTime;

        $io->success(\sprintf('Sitemap generated in %.2f seconds: %s', $duration, $path));

        return Command::SUCCESS;
    }
}
