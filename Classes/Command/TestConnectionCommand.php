<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Command;

use Pahy\Ignitercf\Service\CloudflareApiService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Pahy\Ignitercf\Service\TestStatusService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * CLI command to test Cloudflare connection for sites
 */
#[AsCommand(
    name: 'ignitercf:test:connection',
    description: 'Test Cloudflare connection for configured sites'
)]
final class TestConnectionCommand extends Command
{
    public function __construct(
        private readonly CloudflareApiService $cloudflareApiService,
        private readonly ConfigurationService $configurationService,
        private readonly SiteFinder $siteFinder,
        private readonly TestStatusService $testStatusService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Test Cloudflare connection for configured sites')
            ->setHelp('Tests the API token validity and zone access for one or all sites.')
            ->addArgument(
                'site',
                InputArgument::OPTIONAL,
                'Site identifier (optional, tests all sites if omitted)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $siteIdentifier = $input->getArgument('site');

        $io->title('IgniterCF - Connection Test');

        if ($siteIdentifier) {
            return $this->testSingleSite($io, $siteIdentifier);
        }

        return $this->testAllSites($io);
    }

    private function testSingleSite(SymfonyStyle $io, string $siteIdentifier): int
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
        } catch (\Exception $e) {
            $io->error(sprintf('Site "%s" not found.', $siteIdentifier));
            $this->listAvailableSites($io);
            return Command::FAILURE;
        }

        $io->text(sprintf('Testing site: <info>%s</info>', $siteIdentifier));
        $io->newLine();

        $result = $this->cloudflareApiService->testConnection($site);

        $this->displayResult($io, $siteIdentifier, $result);

        // Record test status
        if ($result['success']) {
            $this->testStatusService->recordSuccessfulTest($siteIdentifier);
        } else {
            $this->testStatusService->recordFailedTest($siteIdentifier);
        }

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }

    private function testAllSites(SymfonyStyle $io): int
    {
        $sites = $this->siteFinder->getAllSites();
        $hasFailure = false;

        if (empty($sites)) {
            $io->warning('No sites configured in TYPO3.');
            return Command::SUCCESS;
        }

        foreach ($sites as $site) {
            $identifier = $site->getIdentifier();
            $io->section(sprintf('Site: %s', $identifier));

            $result = $this->cloudflareApiService->testConnection($site);
            $this->displayResult($io, $identifier, $result);

            // Record test status
            if ($result['success']) {
                $this->testStatusService->recordSuccessfulTest($identifier);
            } else {
                $this->testStatusService->recordFailedTest($identifier);
                $hasFailure = true;
            }
        }

        $io->newLine();
        if ($hasFailure) {
            $io->warning('Some sites have connection issues.');
            return Command::FAILURE;
        }

        $io->success('All sites connected successfully.');
        return Command::SUCCESS;
    }

    private function displayResult(SymfonyStyle $io, string $siteIdentifier, array $result): void
    {
        $tokenStatus = $result['token']['valid'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
        $zoneStatus = $result['zone']['valid'] ? '<fg=green>✓</>' : '<fg=red>✗</>';

        $io->text(sprintf('  Token: %s %s', $tokenStatus, $result['token']['message']));
        $io->text(sprintf('  Zone:  %s %s', $zoneStatus, $result['zone']['message']));
        $io->text(sprintf('  Response time: %d ms', round($result['responseTimeMs'])));

        $io->newLine();

        if ($result['success']) {
            $io->success(sprintf('Site "%s" connected successfully.', $siteIdentifier));
        } else {
            $io->error(sprintf('Site "%s" connection failed.', $siteIdentifier));
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
