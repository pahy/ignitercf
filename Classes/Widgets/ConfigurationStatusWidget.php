<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Widgets;

use Pahy\Ignitercf\Service\ChartDataService;
use Pahy\Ignitercf\Service\ConfigurationService;
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
 * - Link to backend module for configuration
 */
class ConfigurationStatusWidget implements WidgetInterface
{
    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly ChartDataService $chartDataService,
        private readonly ConfigurationService $configurationService
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
        ]);

        return $view->render();
    }

    public function getOptions(): array
    {
        return [];
    }
}
