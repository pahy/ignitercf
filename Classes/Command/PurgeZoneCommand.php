<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Command;

use Pahy\Ignitercf\Exception\CloudflareException;
use Pahy\Ignitercf\Service\CloudflareApiService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * CLI command to purge a specific Cloudflare zone
 */
final class PurgeZoneCommand extends Command
{
    public function __construct(
        private readonly CloudflareApiService $cloudflareApiService,
        private readonly ConfigurationService $configurationService,
        private readonly SiteFinder $siteFinder
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Purge Cloudflare cache for a specific zone/site')
            ->setHelp('Purges the entire Cloudflare cache for a specific site.')
            ->addOption(
                'site',
                's',
                InputOption::VALUE_REQUIRED,
                'Site identifier (e.g. "main")'
            )
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
        $siteIdentifier = $input->getOption('site');
        $dryRun = (bool)$input->getOption('dry-run');

        if (!$this->configurationService->isEnabled()) {
            $io->error('IgniterCF is globally disabled.');
            return Command::FAILURE;
        }

        if (empty($siteIdentifier)) {
            $io->error('Site identifier required. Use --site=<identifier>');
            $this->listAvailableSites($io);
            return Command::FAILURE;
        }

        $io->title('IgniterCF - Purge Zone');

        if ($dryRun) {
            $io->note('Dry-run mode - no actual purging');
        }

        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
        } catch (\Exception $e) {
            $io->error(sprintf('Site "%s" not found.', $siteIdentifier));
            $this->listAvailableSites($io);
            return Command::FAILURE;
        }

        if (!$this->configurationService->isSiteConfigured($site)) {
            $io->error(sprintf('Site "%s" has no valid Cloudflare configuration.', $siteIdentifier));
            return Command::FAILURE;
        }

        $zoneId = $this->configurationService->getZoneIdForSite($site);
        $io->text(sprintf('Site: %s', $siteIdentifier));
        $io->text(sprintf('Zone: %s', $zoneId));

        try {
            if ($dryRun) {
                $io->success(sprintf('Would purge zone "%s" for site "%s".', $zoneId, $siteIdentifier));
            } else {
                $this->cloudflareApiService->purgeEverything($site);
                $io->success(sprintf('Zone "%s" purged for site "%s".', $zoneId, $siteIdentifier));
            }

            return Command::SUCCESS;
        } catch (CloudflareException $e) {
            $io->error('Purge failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function listAvailableSites(SymfonyStyle $io): void
    {
        $sites = $this->siteFinder->getAllSites();
        $rows = [];

        foreach ($sites as $site) {
            $configured = $this->configurationService->isSiteConfigured($site) ? 'Yes' : 'No';
            $zoneId = $this->configurationService->getZoneIdForSite($site) ?: '-';
            $rows[] = [$site->getIdentifier(), $zoneId, $configured];
        }

        $io->newLine();
        $io->text('Available sites:');
        $io->table(['Identifier', 'Zone ID', 'Configured'], $rows);
    }
}
