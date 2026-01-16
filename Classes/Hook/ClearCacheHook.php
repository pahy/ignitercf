<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Hook;

use Pahy\Ignitercf\Service\CacheClearService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hook for "Clear all caches" button integration
 */
final class ClearCacheHook implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Hook: clearCachePostProc
     * Called after cache clear actions
     *
     * @param array{cacheCmd: string, tags?: array<string>} $params Hook parameters
     * @param DataHandler $dataHandler DataHandler instance
     */
    public function clearCachePostProc(array $params, DataHandler $dataHandler): void
    {
        $cacheCmd = $params['cacheCmd'] ?? '';

        // Only react to "Clear all caches"
        if ($cacheCmd !== 'all') {
            return;
        }

        // Check if purge on clear all is enabled
        try {
            $configurationService = GeneralUtility::getContainer()->get(ConfigurationService::class);
        } catch (\Exception) {
            // Container not available, skip
            return;
        }
        if (!$configurationService->isPurgeOnClearAllEnabled()) {
            return;
        }

        try {
            $this->logger?->info('Clear all caches triggered - purging all Cloudflare zones');

            $cacheClearService = GeneralUtility::getContainer()->get(CacheClearService::class);
            $cacheClearService->clearAllZones();
        } catch (\Exception $e) {
            $this->logger?->error('Cloudflare purge all zones failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
