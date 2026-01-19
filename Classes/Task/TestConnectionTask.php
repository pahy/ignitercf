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
 *
 * TYPO3 scheduler tasks are loaded from the database and require setter injection.
 */
class TestConnectionTask extends AbstractTask implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ?ConfigurationService $configurationService = null;
    private ?SiteFinder $siteFinder = null;
    private ?CloudflareApiService $cloudflareApiService = null;
    private ?TestStatusService $testStatusService = null;

    public function setConfigurationService(ConfigurationService $configurationService): void
    {
        $this->configurationService = $configurationService;
    }

    public function setSiteFinder(SiteFinder $siteFinder): void
    {
        $this->siteFinder = $siteFinder;
    }

    public function setCloudflareApiService(CloudflareApiService $cloudflareApiService): void
    {
        $this->cloudflareApiService = $cloudflareApiService;
    }

    public function setTestStatusService(TestStatusService $testStatusService): void
    {
        $this->testStatusService = $testStatusService;
    }

    public function execute(): bool
    {
        try {
            // Get services from container if not injected via setters
            if ($this->configurationService === null) {
                $this->configurationService = GeneralUtility::getContainer()->get(ConfigurationService::class);
            }
            if ($this->siteFinder === null) {
                $this->siteFinder = GeneralUtility::getContainer()->get(SiteFinder::class);
            }
            if ($this->cloudflareApiService === null) {
                $this->cloudflareApiService = GeneralUtility::getContainer()->get(CloudflareApiService::class);
            }
            if ($this->testStatusService === null) {
                $this->testStatusService = GeneralUtility::getContainer()->get(TestStatusService::class);
            }

            if (!$this->configurationService->isEnabled()) {
                $this->logger?->info('IgniterCF: Connection test skipped - extension is disabled');
                return true;
            }

            $sites = $this->siteFinder->getAllSites();
            $successCount = 0;
            $failureCount = 0;

            foreach ($sites as $site) {
                $identifier = $site->getIdentifier();
                $zoneId = $this->configurationService->getZoneIdForSite($site);
                $apiToken = $this->configurationService->getApiTokenForSite($site);

                // Skip if not configured
                if (empty($zoneId) || empty($apiToken)) {
                    continue;
                }

                try {
                    // Test the connection
                    $result = $this->cloudflareApiService->testConnection($site);

                    if (!($result['success'] ?? false)) {
                        $this->testStatusService->recordFailedTest($identifier);
                        $failureCount++;
                        $this->logger?->warning('IgniterCF: Connection test failed for site', [
                            'site' => $identifier,
                            'error' => $result['errors'][0]['message'] ?? 'Unknown error',
                        ]);
                    } else {
                        $this->testStatusService->recordSuccessfulTest($identifier);
                        $successCount++;
                        $this->logger?->info('IgniterCF: Connection test successful for site', [
                            'site' => $identifier,
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->testStatusService->recordFailedTest($identifier);
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
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}
