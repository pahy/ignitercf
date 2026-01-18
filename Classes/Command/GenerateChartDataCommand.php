<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Command;

use Pahy\Ignitercf\Service\ChartDataService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI command to generate chart data from Cloudflare logs
 *
 * Generates pre-computed statistics and saves them to a JSON cache file
 * for fast retrieval by the backend module and dashboard widgets.
 *
 * Registered via:
 * - PHP Attribute #[AsCommand] for Symfony 6.1+ / TYPO3 v13+
 * - Services.yaml for TYPO3 v12
 */
#[AsCommand(
    name: 'ignitercf:chart:generate',
    description: 'Generate chart data from Cloudflare logs'
)]
final class GenerateChartDataCommand extends Command
{
    public function __construct(
        private readonly ChartDataService $chartDataService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Generates pre-computed chart data from Cloudflare API logs and saves it to a JSON cache file.')
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Number of days to include in statistics',
                '7'
            )
            ->addOption(
                'clear',
                'c',
                InputOption::VALUE_NONE,
                'Clear the cache without regenerating'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Handle clear option
        if ($input->getOption('clear')) {
            $this->chartDataService->clearCache();
            $io->success('Chart data cache cleared.');
            return Command::SUCCESS;
        }

        $days = (int)$input->getOption('days');

        if ($days < 1 || $days > 365) {
            $io->error('Days must be between 1 and 365.');
            return Command::FAILURE;
        }

        $io->title('IgniterCF - Generate Chart Data');

        try {
            $io->text(sprintf('Generating statistics for the last %d days...', $days));

            $data = $this->chartDataService->generate($days);

            $io->newLine();
            $io->section('Generated Data Summary');

            // Sites status
            $sitesStatus = $data['sites_status'];
            $io->text(sprintf(
                'Sites: %d/%d configured',
                $sitesStatus['configured'],
                $sitesStatus['total']
            ));

            // Totals
            $totals = $data['totals_7d'];
            $io->text(sprintf(
                'API Calls (last %d days): %d total, %d success, %d errors',
                $days,
                $totals['total'],
                $totals['success'],
                $totals['errors']
            ));

            // Daily breakdown
            if ($output->isVerbose()) {
                $io->newLine();
                $io->section('Daily Breakdown');

                $rows = [];
                foreach ($data['daily'] as $day) {
                    $rows[] = [
                        $day['date'],
                        $day['success'],
                        $day['errors'],
                        $day['avg_response_ms'] . ' ms',
                    ];
                }

                $io->table(
                    ['Date', 'Success', 'Errors', 'Avg Response'],
                    $rows
                );
            }

            $io->newLine();
            $io->success(sprintf(
                'Chart data generated at: %s',
                $data['generated_at']
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to generate chart data: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
