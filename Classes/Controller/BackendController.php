<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Controller;

use Pahy\Ignitercf\Service\ChartDataService;
use Pahy\Ignitercf\Service\CloudflareLogService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
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
    private const EXTENSION_VERSION = '1.0.0';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly SiteFinder $siteFinder,
        private readonly ConfigurationService $configurationService,
        private readonly CloudflareLogService $cloudflareLogService,
        private readonly ChartDataService $chartDataService,
        private readonly IconFactory $iconFactory
    ) {}

    /**
     * Dashboard view - Statistics & Purge Activity
     */
    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('IgniterCF', 'IgniterCF - Cloudflare Cache Management');
        $this->addDocHeaderMenu($moduleTemplate, 'index');

        // Get user's days preferences
        $statisticsDays = $this->getUserSetting('statisticsDays');
        $chartDays = $this->getUserSetting('chartDays');

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

        // Get global settings for header status
        $globalSettings = $this->getGlobalSettings();

        $moduleTemplate->assignMultiple([
            'recentLogs' => $recentLogs,
            'statistics' => $statistics,
            'globalSettings' => $globalSettings,
            'chartDataAge' => $chartDataAge,
            'chartDataJson' => json_encode($dailyData),
            'statisticsDays' => $statisticsDays,
            'chartDays' => $chartDays,
            'availableDays' => $this->getDaysOptions(),
            'version' => self::EXTENSION_VERSION,
        ]);

        return $moduleTemplate->renderResponse('Backend/Index');
    }

    /**
     * Configuration view - Site Configuration Status & Settings
     */
    public function configurationAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setTitle('IgniterCF', 'IgniterCF - Cloudflare Cache Management');
        $this->addDocHeaderMenu($moduleTemplate, 'configuration');

        // Get all sites with their configuration status
        $sitesStatus = $this->getSitesStatus();

        // Get global settings
        $globalSettings = $this->getGlobalSettings();

        $moduleTemplate->assignMultiple([
            'sitesStatus' => $sitesStatus,
            'globalSettings' => $globalSettings,
            'allConfigured' => $this->areAllSitesConfigured($sitesStatus),
            'version' => self::EXTENSION_VERSION,
        ]);

        return $moduleTemplate->renderResponse('Backend/Configuration');
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
     * Add docheader elements (two rows: title on top, menu + status below)
     */
    private function addDocHeaderMenu(ModuleTemplate $moduleTemplate, string $currentAction): void
    {
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $uriBuilder = $this->uriBuilder;
        $uriBuilder->setRequest($this->request);

        // Row 1 (buttons bar - will be moved to top via CSS): Title left, Status right
        $titleButton = $buttonBar->makeLinkButton()
            ->setHref('#')
            ->setTitle('IgniterCF - Cloudflare Cache Management')
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('module-ignitercf', Icon::SIZE_SMALL))
            ->setClasses('ignitercf-status-indicator ignitercf-docheader-title');
        $buttonBar->addButton($titleButton, ButtonBar::BUTTON_POSITION_LEFT, 1);

        $this->addStatusIndicators($buttonBar);

        // Row 2 (navigation bar - will be moved to bottom via CSS): Dropdown menu
        $menu = $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('ignitercf_menu');

        $menuItems = [
            'index' => 'Dashboard',
            'configuration' => 'Configuration',
        ];

        foreach ($menuItems as $action => $label) {
            $menuItem = $menu->makeMenuItem()
                ->setTitle($label)
                ->setHref($uriBuilder->reset()->uriFor($action, [], 'Backend'))
                ->setActive($currentAction === $action);
            $menu->addMenuItem($menuItem);
        }

        $moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    /**
     * Add status indicators to the docheader button bar
     */
    private function addStatusIndicators(ButtonBar $buttonBar): void
    {
        // Get status data
        $configStatus = $this->getConfigurationStatus();
        $operationStatus = $this->getOperationStatus(3);

        // Configuration status indicator
        $configButton = $buttonBar->makeLinkButton()
            ->setHref('#')
            ->setTitle($configStatus['tooltip'])
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon($configStatus['icon'], Icon::SIZE_SMALL))
            ->setClasses('ignitercf-status-indicator ignitercf-status-' . $configStatus['level']);
        $buttonBar->addButton($configButton, ButtonBar::BUTTON_POSITION_RIGHT, 90);

        // Operation status indicator (last 3 days)
        $operationButton = $buttonBar->makeLinkButton()
            ->setHref('#')
            ->setTitle($operationStatus['tooltip'])
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon($operationStatus['icon'], Icon::SIZE_SMALL))
            ->setClasses('ignitercf-status-indicator ignitercf-status-' . $operationStatus['level']);
        $buttonBar->addButton($operationButton, ButtonBar::BUTTON_POSITION_RIGHT, 91);
    }

    /**
     * Get configuration status (green/yellow/red)
     *
     * @return array{level: string, icon: string, tooltip: string}
     */
    private function getConfigurationStatus(): array
    {
        if (!$this->configurationService->isEnabled()) {
            return [
                'level' => 'red',
                'icon' => 'actions-circle',
                'tooltip' => 'Extension disabled',
            ];
        }

        $sites = $this->siteFinder->getAllSites();
        $configuredCount = 0;
        $totalCount = count($sites);

        foreach ($sites as $site) {
            if ($this->configurationService->isSiteConfigured($site)) {
                $configuredCount++;
            }
        }

        if ($totalCount === 0) {
            return [
                'level' => 'red',
                'icon' => 'actions-circle',
                'tooltip' => 'No sites configured',
            ];
        }

        if ($configuredCount === $totalCount) {
            return [
                'level' => 'green',
                'icon' => 'actions-circle',
                'tooltip' => sprintf('Config: %d/%d sites ready', $configuredCount, $totalCount),
            ];
        }

        if ($configuredCount > 0) {
            return [
                'level' => 'yellow',
                'icon' => 'actions-circle',
                'tooltip' => sprintf('Config: %d/%d sites ready', $configuredCount, $totalCount),
            ];
        }

        return [
            'level' => 'red',
            'icon' => 'actions-circle',
            'tooltip' => 'Config: No sites configured',
        ];
    }

    /**
     * Get operation status for the last N days (green/yellow/red)
     *
     * @return array{level: string, icon: string, tooltip: string}
     */
    private function getOperationStatus(int $days): array
    {
        $statistics = $this->cloudflareLogService->getStatistics($days);

        $total = (int)($statistics['total'] ?? 0);
        $errors = (int)($statistics['errors'] ?? 0);
        $success = (int)($statistics['success'] ?? 0);

        if ($total === 0) {
            return [
                'level' => 'gray',
                'icon' => 'actions-circle',
                'tooltip' => sprintf('Status: No activity (%d days)', $days),
            ];
        }

        $errorRate = $total > 0 ? ($errors / $total) * 100 : 0;

        if ($errors === 0) {
            return [
                'level' => 'green',
                'icon' => 'actions-circle',
                'tooltip' => sprintf('Status: %d OK (%d days)', $success, $days),
            ];
        }

        if ($errorRate <= 10) {
            return [
                'level' => 'yellow',
                'icon' => 'actions-circle',
                'tooltip' => sprintf('Status: %d OK, %d errors (%d days)', $success, $errors, $days),
            ];
        }

        return [
            'level' => 'red',
            'icon' => 'actions-circle',
            'tooltip' => sprintf('Status: %d errors (%d days)', $errors, $days),
        ];
    }

    /**
     * Get global settings array
     *
     * @return array<string, mixed>
     */
    private function getGlobalSettings(): array
    {
        return [
            'enabled' => $this->configurationService->isEnabled(),
            'logLevel' => $this->configurationService->getLogLevel(),
            'logRetentionDays' => $this->configurationService->getLogRetentionDays(),
            'autoPurgeOnSave' => $this->configurationService->isAutoPurgeOnSaveEnabled(),
            'purgeOnClearAll' => $this->configurationService->isPurgeOnClearAllEnabled(),
            'middlewareEnabled' => $this->configurationService->isMiddlewareEnabled(),
        ];
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
