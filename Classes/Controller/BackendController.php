<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Controller;

use Pahy\Ignitercf\Service\ChartDataService;
use Pahy\Ignitercf\Service\CloudflareLogService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Backend module controller for IgniterCF administration
 */
#[AsController]
final class BackendController extends ActionController
{
    private const UC_KEY = 'ignitercf';
    private const DEFAULT_DAYS = 7;
    private const AVAILABLE_DAYS = [7, 14, 30, 90];

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly SiteFinder $siteFinder,
        private readonly ConfigurationService $configurationService,
        private readonly CloudflareLogService $cloudflareLogService,
        private readonly ChartDataService $chartDataService
    ) {}

    /**
     * Main dashboard view
     */
    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        // Get user's days preferences
        $statisticsDays = $this->getUserSetting('statisticsDays');
        $chartDays = $this->getUserSetting('chartDays');

        // Get all sites with their configuration status
        $sitesStatus = $this->getSitesStatus();

        // Get recent log entries
        $recentLogs = $this->cloudflareLogService->getRecentEntries(20);

        // Get statistics for selected period
        $statistics = $this->cloudflareLogService->getStatistics($statisticsDays);

        // Get chart data (from cache or generate if stale)
        $chartData = $this->chartDataService->getDataOrGenerate(60);
        $chartDataAge = $this->chartDataService->getCacheAgeMinutes();

        // Filter chart data to selected days
        $dailyData = $chartData['daily'] ?? [];
        if (count($dailyData) > $chartDays) {
            $dailyData = array_slice($dailyData, -$chartDays);
        }

        // Get global settings
        $globalSettings = [
            'enabled' => $this->configurationService->isEnabled(),
            'logLevel' => $this->configurationService->getLogLevel(),
            'logRetentionDays' => $this->configurationService->getLogRetentionDays(),
            'autoPurgeOnSave' => $this->configurationService->isAutoPurgeOnSaveEnabled(),
            'purgeOnClearAll' => $this->configurationService->isPurgeOnClearAllEnabled(),
            'middlewareEnabled' => $this->configurationService->isMiddlewareEnabled(),
        ];

        $moduleTemplate->assignMultiple([
            'sitesStatus' => $sitesStatus,
            'recentLogs' => $recentLogs,
            'statistics' => $statistics,
            'globalSettings' => $globalSettings,
            'allConfigured' => $this->areAllSitesConfigured($sitesStatus),
            'chartData' => $chartData,
            'chartDataAge' => $chartDataAge,
            'chartDataJson' => json_encode($dailyData),
            'statisticsDays' => $statisticsDays,
            'chartDays' => $chartDays,
            'availableDays' => $this->getDaysOptions(),
        ]);

        return $moduleTemplate->renderResponse('Backend/Index');
    }

    /**
     * Save days preference for a specific setting
     */
    public function saveDaysAction(string $setting = 'chartDays', int $days = 7): ResponseInterface
    {
        $allowedSettings = ['statisticsDays', 'chartDays'];
        if (in_array($setting, $allowedSettings, true) && in_array($days, self::AVAILABLE_DAYS, true)) {
            $this->setUserSetting($setting, $days);
        }

        return $this->redirect('index');
    }

    /**
     * Get user setting from BE_USER->uc
     */
    private function getUserSetting(string $key): int
    {
        $uc = $GLOBALS['BE_USER']->uc[self::UC_KEY] ?? [];
        $value = (int)($uc[$key] ?? self::DEFAULT_DAYS);

        // Validate against available options
        if (!in_array($value, self::AVAILABLE_DAYS, true)) {
            $value = self::DEFAULT_DAYS;
        }

        return $value;
    }

    /**
     * Save user setting to BE_USER->uc
     */
    private function setUserSetting(string $key, int $value): void
    {
        if (!isset($GLOBALS['BE_USER']->uc[self::UC_KEY])) {
            $GLOBALS['BE_USER']->uc[self::UC_KEY] = [];
        }
        $GLOBALS['BE_USER']->uc[self::UC_KEY][$key] = $value;
        $GLOBALS['BE_USER']->writeUC();
    }

    /**
     * Get days options for dropdown
     *
     * @return array<int, string>
     */
    private function getDaysOptions(): array
    {
        $options = [];
        foreach (self::AVAILABLE_DAYS as $days) {
            $options[$days] = $days . ' days';
        }
        return $options;
    }

    /**
     * Get configuration status for all sites
     *
     * @return array<string, array<string, mixed>>
     */
    private function getSitesStatus(): array
    {
        $sites = $this->siteFinder->getAllSites();
        $sitesStatus = [];

        foreach ($sites as $site) {
            $identifier = $site->getIdentifier();
            $zoneId = $this->configurationService->getZoneIdForSite($site);
            $apiToken = $this->configurationService->getApiTokenForSite($site);
            $siteEnabled = $this->configurationService->isSiteEnabled($site);

            $issues = [];
            $hints = [];

            // Check Zone ID
            if (empty($zoneId)) {
                $issues[] = 'zone_id_missing';
                $hints[] = [
                    'type' => 'zone_id',
                    'message' => 'Zone ID is not configured',
                    'solution' => sprintf(
                        'Add to config/sites/%s/config.yaml:%scloudflare:%s  zoneId: \'your-zone-id\'',
                        $identifier,
                        "\n",
                        "\n"
                    ),
                    'link' => 'https://dash.cloudflare.com/ → Select domain → Overview → Zone ID',
                ];
            }

            // Check API Token
            if (empty($apiToken)) {
                $issues[] = 'api_token_missing';
                $envVarName = 'IGNITERCF_TOKEN_' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', $identifier));
                $hints[] = [
                    'type' => 'api_token',
                    'message' => 'API Token is not configured',
                    'solution' => sprintf(
                        'Set environment variable: %s=your-api-token%sOr global: IGNITERCF_API_TOKEN=your-api-token',
                        $envVarName,
                        "\n"
                    ),
                    'link' => 'https://dash.cloudflare.com/profile/api-tokens → Create Token',
                ];
            }

            // Check if site is disabled
            if (!$siteEnabled) {
                $issues[] = 'site_disabled';
                $hints[] = [
                    'type' => 'site_disabled',
                    'message' => 'Cloudflare is disabled for this site',
                    'solution' => sprintf(
                        'In config/sites/%s/config.yaml, set:%scloudflare:%s  enabled: true',
                        $identifier,
                        "\n",
                        "\n"
                    ),
                ];
            }

            $isConfigured = empty($issues) && $siteEnabled;

            $sitesStatus[$identifier] = [
                'site' => $site,
                'identifier' => $identifier,
                'baseUrl' => (string)$site->getBase(),
                'zoneId' => $zoneId,
                'hasApiToken' => !empty($apiToken),
                'apiTokenMasked' => !empty($apiToken) ? substr($apiToken, 0, 8) . '***' : '',
                'siteEnabled' => $siteEnabled,
                'isConfigured' => $isConfigured,
                'issues' => $issues,
                'hints' => $hints,
            ];
        }

        return $sitesStatus;
    }

    /**
     * Check if all sites are properly configured
     *
     * @param array<string, array<string, mixed>> $sitesStatus
     */
    private function areAllSitesConfigured(array $sitesStatus): bool
    {
        foreach ($sitesStatus as $status) {
            if (!$status['isConfigured']) {
                return false;
            }
        }

        return !empty($sitesStatus);
    }
}
