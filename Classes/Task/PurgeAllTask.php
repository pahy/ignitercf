<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Task;

use Pahy\Ignitercf\Service\CacheClearService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Pahy\Ignitercf\Service\EmailNotificationService;
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
            $container = GeneralUtility::getContainer();
            $configurationService = $container->get(ConfigurationService::class);

            if (!$configurationService->isEnabled()) {
                $this->logger?->warning('IgniterCF: Scheduler task skipped - extension is disabled');
                return true;
            }

            $cacheClearService = $container->get(CacheClearService::class);
            $result = $cacheClearService->clearAllZones();

            if ($result->hasErrors()) {
                $this->logger?->error('IgniterCF: Scheduler task completed with errors', [
                    'successCount' => $result->successCount,
                    'errorCount' => $result->errorCount,
                    'errors' => $result->errors,
                ]);
                // Still return true as some zones may have been purged successfully
            } else {
                $this->logger?->info('IgniterCF: Scheduler task - all zones purged', [
                    'urlCount' => $result->urlCount,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Scheduler task failed', [
                'error' => $e->getMessage(),
            ]);
            $this->sendErrorNotification($e);
            return false;
        }
    }

    /**
     * Send error notification email
     */
    private function sendErrorNotification(\Throwable $exception): void
    {
        try {
            $emailService = GeneralUtility::getContainer()->get(EmailNotificationService::class);
            $emailService->notifyTaskError('PurgeAllTask', $exception);
        } catch (\Exception $e) {
            $this->logger?->warning('IgniterCF: Could not send error notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
