<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Task;

use Pahy\Ignitercf\Service\CloudflareLogService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler task to purge old Cloudflare log entries
 *
 * Deletes log entries older than the configured retention period
 * (default: 30 days, configurable via logRetentionDays setting)
 */
class PurgeOldLogsTask extends AbstractTask implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function execute(): bool
    {
        try {
            $configurationService = GeneralUtility::getContainer()->get(ConfigurationService::class);
            $cloudflareLogService = GeneralUtility::getContainer()->get(CloudflareLogService::class);

            $retentionDays = $configurationService->getLogRetentionDays();

            if ($retentionDays <= 0) {
                $this->logger?->info('IgniterCF: Log cleanup skipped - retention is set to indefinite (0 days)');
                return true;
            }

            $deletedCount = $cloudflareLogService->purgeOldEntries($retentionDays);

            $this->logger?->info('IgniterCF: Log cleanup completed', [
                'retention_days' => $retentionDays,
                'deleted_entries' => $deletedCount,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Log cleanup task failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
