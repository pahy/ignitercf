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
 * Scheduler task to purge Cloudflare cache for a specific page
 */
class PurgePageTask extends AbstractTask implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Page UID to purge
     */
    public int $pageUid = 0;

    /**
     * Language UID (-1 = all languages)
     */
    public int $languageUid = -1;

    public function execute(): bool
    {
        if ($this->pageUid <= 0) {
            $this->logger?->error('IgniterCF: Scheduler task failed - no page UID configured');
            return false;
        }

        try {
            $configurationService = GeneralUtility::getContainer()->get(ConfigurationService::class);

            if (!$configurationService->isEnabled()) {
                $this->logger?->warning('IgniterCF: Scheduler task skipped - extension is disabled');
                return true;
            }

            $cacheClearService = GeneralUtility::getContainer()->get(CacheClearService::class);

            if ($this->languageUid >= 0) {
                $cacheClearService->clearCacheForPages([$this->pageUid], [$this->languageUid]);
                $this->logger?->info('IgniterCF: Scheduler task - page purged', [
                    'pageUid' => $this->pageUid,
                    'languageUid' => $this->languageUid,
                ]);
            } else {
                $cacheClearService->clearCacheForPage($this->pageUid);
                $this->logger?->info('IgniterCF: Scheduler task - page purged (all languages)', [
                    'pageUid' => $this->pageUid,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Scheduler task failed', [
                'pageUid' => $this->pageUid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getAdditionalInformation(): string
    {
        $language = $this->languageUid >= 0 ? (string)$this->languageUid : 'all';
        return sprintf('Page: %d, Language: %s', $this->pageUid, $language);
    }
}
