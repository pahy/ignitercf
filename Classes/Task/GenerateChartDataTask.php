<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Task;

use Pahy\Ignitercf\Service\ChartDataService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler task to generate chart data from Cloudflare logs
 *
 * Generates pre-computed statistics and saves them to a JSON cache file
 * for fast retrieval by the backend module and dashboard widgets.
 *
 * Recommended frequency: every 15-60 minutes
 */
class GenerateChartDataTask extends AbstractTask implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Number of days to include in statistics
     */
    public int $days = 7;

    public function execute(): bool
    {
        try {
            $chartDataService = GeneralUtility::getContainer()->get(ChartDataService::class);

            $data = $chartDataService->generate($this->days);

            $this->logger?->info('IgniterCF: Chart data generated', [
                'days' => $this->days,
                'total_calls' => $data['totals_7d']['total'] ?? 0,
                'sites_configured' => $data['sites_status']['configured'] ?? 0,
                'sites_total' => $data['sites_status']['total'] ?? 0,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Chart data generation failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get additional information for the scheduler module
     */
    public function getAdditionalInformation(): string
    {
        return sprintf('Generate chart data for the last %d days', $this->days);
    }
}
