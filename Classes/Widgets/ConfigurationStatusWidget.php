<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Widgets;

use Pahy\Ignitercf\Service\ChartDataService;
use Pahy\Ignitercf\Service\ConfigurationService;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Dashboard widget showing Cloudflare configuration status
 *
 * Displays:
 * - Number of configured vs. total sites
 * - Status indicator (green/yellow/red)
 * - Configuration hints for unconfigured sites
 */
class ConfigurationStatusWidget implements WidgetInterface
{
    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly ChartDataService $chartDataService,
        private readonly ConfigurationService $configurationService,
        private readonly SiteFinder $siteFinder
    ) {}

    public function renderWidgetContent(): string
    {
        $chartData = $this->chartDataService->getDataOrGenerate(60);
        $sitesStatus = $chartData['sites_status'] ?? ['configured' => 0, 'total' => 0, 'sites' => []];

        $configured = $sitesStatus['configured'];
        $total = $sitesStatus['total'];

        // Determine status
        if ($total === 0) {
            $status = 'warning';
            $statusText = 'No sites found';
            $statusIcon = 'actions-exclamation-triangle';
        } elseif ($configured === $total) {
            $status = 'success';
            $statusText = 'All sites configured';
            $statusIcon = 'actions-check-circle';
        } elseif ($configured === 0) {
            $status = 'danger';
            $statusText = 'No sites configured';
            $statusIcon = 'actions-ban';
        } else {
            $status = 'warning';
            $statusText = sprintf('%d of %d sites configured', $configured, $total);
            $statusIcon = 'actions-exclamation-circle';
        }

        // Get configuration hints for unconfigured sites
        $hints = $this->getConfigurationHints();

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename(
            'EXT:ignitercf/Resources/Private/Templates/Widgets/ConfigurationStatus.html'
        );
        $view->assignMultiple([
            'configuration' => $this->configuration,
            'configured' => $configured,
            'total' => $total,
            'status' => $status,
            'statusText' => $statusText,
            'statusIcon' => $statusIcon,
            'enabled' => $this->configurationService->isEnabled(),
            'sites' => $sitesStatus['sites'] ?? [],
            'hints' => $hints,
        ]);

        return $view->render();
    }

    /**
     * Get configuration hints for unconfigured sites
     *
     * @return array<string, array<string, mixed>>
     */
    private function getConfigurationHints(): array
    {
        $hints = [];
        $sites = $this->siteFinder->getAllSites();

        foreach ($sites as $site) {
            $identifier = $site->getIdentifier();

            if ($this->configurationService->isSiteConfigured($site)) {
                continue;
            }

            $siteHints = [];

            // Check Zone ID
            if (empty($this->configurationService->getZoneIdForSite($site))) {
                $siteHints[] = [
                    'type' => 'zone_id',
                    'message' => 'Zone ID missing',
                    'solution' => "config/sites/{$identifier}/config.yaml:\ncloudflare:\n  zoneId: 'your-zone-id'",
                ];
            }

            // Check API Token
            if (empty($this->configurationService->getApiTokenForSite($site))) {
                $envVarName = 'IGNITERCF_TOKEN_' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', $identifier));
                $siteHints[] = [
                    'type' => 'api_token',
                    'message' => 'API Token missing',
                    'solution' => "Environment: {$envVarName}=your-token\nOr global: IGNITERCF_API_TOKEN=your-token",
                ];
            }

            // Check if site is disabled
            if (!$this->configurationService->isSiteEnabled($site)) {
                $siteHints[] = [
                    'type' => 'disabled',
                    'message' => 'Site disabled',
                    'solution' => "config/sites/{$identifier}/config.yaml:\ncloudflare:\n  enabled: true",
                ];
            }

            if (!empty($siteHints)) {
                $hints[$identifier] = [
                    'identifier' => $identifier,
                    'hints' => $siteHints,
                ];
            }
        }

        return $hints;
    }

    public function getOptions(): array
    {
        return [];
    }
}
