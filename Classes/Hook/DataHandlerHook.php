<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Hook;

use Pahy\Ignitercf\Dto\PurgeResult;
use Pahy\Ignitercf\Service\CacheClearService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Pahy\Ignitercf\Service\NotificationService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * DataHandler hook for automatic cache purging on content changes
 */
final class DataHandlerHook implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Tables that trigger cache purge
     */
    private const RELEVANT_TABLES = ['tt_content', 'pages'];

    /**
     * Collected purge results during DataHandler operations
     * @var array<PurgeResult>
     */
    private static array $collectedResults = [];

    /**
     * Hook: processDatamap_afterDatabaseOperations
     * Called after each save operation - collects pages to purge
     *
     * @param string $status 'new' or 'update'
     * @param string $table Table name
     * @param string|int $id Record UID (for 'new': temporary ID like 'NEW123')
     * @param array<string, mixed> $fieldArray Changed fields
     * @param DataHandler $dataHandler DataHandler instance
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        string|int $id,
        array $fieldArray,
        DataHandler $dataHandler
    ): void {
        // Check if auto-purge on save is enabled
        try {
            $configurationService = GeneralUtility::getContainer()->get(ConfigurationService::class);
        } catch (\Exception) {
            // Container not available, skip
            return;
        }
        if (!$configurationService->isAutoPurgeOnSaveEnabled()) {
            return;
        }

        // Filter: Only tt_content and pages
        if (!in_array($table, self::RELEVANT_TABLES, true)) {
            return;
        }

        // For 'new': Get real UID from substNEWwithIDs
        if ($status === 'new' && isset($dataHandler->substNEWwithIDs[$id])) {
            $id = $dataHandler->substNEWwithIDs[$id];
        }

        $recordUid = (int)$id;
        if ($recordUid === 0) {
            return;
        }

        $record = BackendUtility::getRecord($table, $recordUid);

        if ($record === null) {
            return;
        }

        // Skip deleted/hidden records
        if ((int)($record['deleted'] ?? 0) === 1 || (int)($record['hidden'] ?? 0) === 1) {
            return;
        }

        // Get page IDs
        $pageIds = $this->getPageIds($table, $record);

        // Get languages
        $languageIds = $this->getLanguageIds($record, $pageIds[0] ?? 0);

        if (empty($pageIds) || empty($languageIds)) {
            return;
        }

        // Trigger cache clear and collect results (no notification yet)
        try {
            $cacheClearService = GeneralUtility::getContainer()->get(CacheClearService::class);
            $result = $cacheClearService->clearCacheForPages($pageIds, $languageIds);
            
            // Collect result for later notification
            if ($result->hasPurgedUrls() || $result->hasErrors()) {
                self::$collectedResults[] = $result;
            }
        } catch (\Exception $e) {
            $this->logger?->error('Cache purge failed', [
                'table' => $table,
                'uid' => $recordUid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Hook: processDatamap_afterAllOperations
     * Called after ALL DataHandler operations are complete - shows combined notification
     *
     * @param DataHandler $dataHandler DataHandler instance
     */
    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        if (empty(self::$collectedResults)) {
            return;
        }

        try {
            $notificationService = GeneralUtility::getContainer()->get(NotificationService::class);
            
            // Merge all results into one
            $mergedResult = PurgeResult::merge(self::$collectedResults);
            
            // Show single combined notification
            if ($mergedResult->hasPurgedUrls()) {
                $notificationService->notifyPurgeSuccess($mergedResult->successCount, $mergedResult->siteIdentifier);
            }

            if ($mergedResult->hasErrors()) {
                // Show first error (could be extended to show all)
                $notificationService->notifyPurgeError($mergedResult->errors[0] ?? 'Unknown error', $mergedResult->siteIdentifier);
            }
        } catch (\Exception $e) {
            $this->logger?->error('Failed to show purge notification', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Always reset collected results
            self::$collectedResults = [];
        }
    }

    /**
     * Get page IDs for a record
     *
     * @param string $table Table name
     * @param array<string, mixed> $record Record data
     * @return array<int> Page UIDs
     */
    private function getPageIds(string $table, array $record): array
    {
        if ($table === 'pages') {
            return [(int)$record['uid']];
        }

        // tt_content: Parent page
        return [(int)$record['pid']];
    }

    /**
     * Get language IDs for a record
     *
     * @param array<string, mixed> $record Record data
     * @param int $pageId Page UID (for "all languages" lookup)
     * @return array<int> Language UIDs
     */
    private function getLanguageIds(array $record, int $pageId): array
    {
        $languageId = (int)($record['sys_language_uid'] ?? 0);

        // Case: "All languages" (-1)
        if ($languageId === -1) {
            return $this->getAllLanguageIds($pageId);
        }

        return [$languageId];
    }

    /**
     * Get all enabled language IDs for a page
     *
     * @param int $pageId Page UID
     * @return array<int> Language UIDs
     */
    private function getAllLanguageIds(int $pageId): array
    {
        if ($pageId === 0) {
            return [0]; // Fallback
        }

        try {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($pageId);
            $languageIds = [];

            foreach ($site->getAllLanguages() as $language) {
                if ($language->isEnabled()) {
                    $languageIds[] = $language->getLanguageId();
                }
            }

            return $languageIds;
        } catch (\Exception $e) {
            $this->logger?->warning('Could not fetch all languages', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);
            return [0];
        }
    }
}
