<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Task;

use Pahy\Ignitercf\Service\CloudflareApiService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler task to purge a specific Cloudflare zone
 */
class PurgeZoneTask extends AbstractTask implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Site identifier to purge
     */
    public string $siteIdentifier = '';

    public function execute(): bool
    {
        if (empty($this->siteIdentifier)) {
            $this->logger?->error('IgniterCF: Scheduler task failed - no site identifier configured');
            return false;
        }

        try {
            $configurationService = GeneralUtility::getContainer()->get(ConfigurationService::class);

            if (!$configurationService->isEnabled()) {
                $this->logger?->warning('IgniterCF: Scheduler task skipped - extension is disabled');
                return true;
            }

            $siteFinder = GeneralUtility::getContainer()->get(SiteFinder::class);

            try {
                $site = $siteFinder->getSiteByIdentifier($this->siteIdentifier);
            } catch (\Exception $e) {
                $this->logger?->error('IgniterCF: Scheduler task failed - site not found', [
                    'siteIdentifier' => $this->siteIdentifier,
                ]);
                return false;
            }

            if (!$configurationService->isSiteConfigured($site)) {
                $this->logger?->error('IgniterCF: Scheduler task failed - site not configured', [
                    'siteIdentifier' => $this->siteIdentifier,
                ]);
                return false;
            }

            $cloudflareApiService = GeneralUtility::getContainer()->get(CloudflareApiService::class);
            $cloudflareApiService->purgeEverything($site);

            $this->logger?->info('IgniterCF: Scheduler task - zone purged', [
                'siteIdentifier' => $this->siteIdentifier,
                'zoneId' => $configurationService->getZoneIdForSite($site),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Scheduler task failed', [
                'siteIdentifier' => $this->siteIdentifier,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getAdditionalInformation(): string
    {
        return sprintf('Site: %s', $this->siteIdentifier ?: '(not configured)');
    }
}
