<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Command;

use Pahy\Ignitercf\Service\CacheClearService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI command to purge all Cloudflare zones
 */
final class PurgeAllCommand extends Command
{
    public function __construct(
        private readonly CacheClearService $cacheClearService,
        private readonly ConfigurationService $configurationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Purge Cloudflare cache for all configured zones')
            ->setHelp('Purges the entire Cloudflare cache for all sites with valid configuration.')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be purged without actually purging'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');

        if (!$this->configurationService->isEnabled()) {
            $io->error('IgniterCF is globally disabled.');
            return Command::FAILURE;
        }

        $io->title('IgniterCF - Purge All Zones');

        if ($dryRun) {
            $io->note('Dry-run mode - no actual purging');
        }

        try {
            if ($dryRun) {
                $io->success('Would purge all configured Cloudflare zones.');
            } else {
                $this->cacheClearService->clearAllZones();
                $io->success('All Cloudflare zones purged.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Purge failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
