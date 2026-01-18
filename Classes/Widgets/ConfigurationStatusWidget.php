<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Widgets;

use Pahy\Ignitercf\Service\ChartDataService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;

/**
 * Dashboard widget showing Cloudflare configuration status
 *
 * Displays:
 * - Number of configured vs. total sites
 * - Status indicator (green/yellow/red)
 * - Link to backend module for configuration
 */
class ConfigurationStatusWidget implements WidgetInterface, RequestAwareWidgetInterface
{
    private ServerRequestInterface $request;

    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly BackendViewFactory $backendViewFactory,
        private readonly ChartDataService $chartDataService,
        private readonly ConfigurationService $configurationService
    ) {}

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

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

        $view = $this->backendViewFactory->create($this->request);
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

        return $view->render('Widgets/ConfigurationStatus');
    }

    public function getOptions(): array
    {
        return [];
    }
}
