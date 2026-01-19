<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Task;

use Pahy\Ignitercf\Service\CloudflareApiService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Pahy\Ignitercf\Service\TestStatusService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler task to test Cloudflare connection for all configured sites
 */
class TestConnectionTask extends AbstractTask implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function execute(): bool
    {
        try {
            $configurationService = GeneralUtility::getContainer()->get(ConfigurationService::class);

            if (!$configurationService->isEnabled()) {
                $this->logger?->info('IgniterCF: Connection test skipped - extension is disabled');
                return true;
            }

            $siteFinder = GeneralUtility::getContainer()->get(SiteFinder::class);
            $cloudflareApiService = GeneralUtility::getContainer()->get(CloudflareApiService::class);
            $testStatusService = GeneralUtility::getContainer()->get(TestStatusService::class);

            $sites = $siteFinder->getAllSites();
            $successCount = 0;
            $failureCount = 0;

            foreach ($sites as $site) {
                $identifier = $site->getIdentifier();
                $zoneId = $configurationService->getZoneId($identifier);
                $apiToken = $configurationService->getApiToken($identifier);
                $siteEnabled = $configurationService->isSiteEnabled($identifier);

                // Skip if not configured
                if (empty($zoneId) || empty($apiToken) || !$siteEnabled) {
                    continue;
                }

                try {
                    // Test the connection by fetching zone info
                    $info = $cloudflareApiService->getZoneInfo($zoneId, $apiToken);

                    if (!$info || !($info['success'] ?? false)) {
                        $testStatusService->recordFailedTest($identifier);
                        $failureCount++;
                        $this->logger?->warning('IgniterCF: Connection test failed for site', [
                            'site' => $identifier,
                            'error' => $info['errors'][0]['message'] ?? 'Unknown error',
                        ]);
                    } else {
                        $testStatusService->recordSuccessfulTest($identifier);
                        $successCount++;
                        $this->logger?->info('IgniterCF: Connection test successful for site', [
                            'site' => $identifier,
                        ]);
                    }
                } catch (\Exception $e) {
                    $testStatusService->recordFailedTest($identifier);
                    $failureCount++;
                    $this->logger?->error('IgniterCF: Connection test error for site', [
                        'site' => $identifier,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger?->info('IgniterCF: Connection test completed', [
                'successful' => $successCount,
                'failed' => $failureCount,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Connection test task failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
