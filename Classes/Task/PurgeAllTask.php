<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Task;

use Pahy\Ignitercf\Service\CacheClearService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler task to purge all Cloudflare zones
 */
class PurgeAllTask extends AbstractTask implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function execute(): bool
    {
        try {
            $configurationService = GeneralUtility::getContainer()->get(ConfigurationService::class);

            if (!$configurationService->isEnabled()) {
                $this->logger?->warning('IgniterCF: Scheduler task skipped - extension is disabled');
                return true;
            }

            $cacheClearService = GeneralUtility::getContainer()->get(CacheClearService::class);
            $cacheClearService->clearAllZones();

            $this->logger?->info('IgniterCF: Scheduler task - all zones purged');
            return true;
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Scheduler task failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
