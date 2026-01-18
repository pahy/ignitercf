<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Widgets\Provider;

use Pahy\Ignitercf\Service\ChartDataService;
use TYPO3\CMS\Dashboard\Widgets\ChartDataProviderInterface;

/**
 * Data provider for the purge activity chart widget
 *
 * Provides daily purge statistics for display in a bar chart.
 */
class PurgeActivityChartDataProvider implements ChartDataProviderInterface
{
    public function __construct(
        private readonly ChartDataService $chartDataService
    ) {}

    /**
     * Get chart data in Chart.js format
     *
     * @return array{labels: array<string>, datasets: array<array>}
     */
    public function getChartData(): array
    {
        $chartData = $this->chartDataService->getDataOrGenerate(60);
        $daily = $chartData['daily'] ?? [];

        if (empty($daily)) {
            return [
                'labels' => [],
                'datasets' => [],
            ];
        }

        $labels = [];
        $successData = [];
        $errorData = [];

        foreach ($daily as $day) {
            // Format date as short weekday + day
            $date = new \DateTimeImmutable($day['date']);
            $labels[] = $date->format('D j');

            $successData[] = $day['success'];
            $errorData[] = $day['errors'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Successful',
                    'data' => $successData,
                    'backgroundColor' => 'rgba(40, 167, 69, 0.8)',
                    'borderColor' => 'rgba(40, 167, 69, 1)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Errors',
                    'data' => $errorData,
                    'backgroundColor' => 'rgba(220, 53, 69, 0.8)',
                    'borderColor' => 'rgba(220, 53, 69, 1)',
                    'borderWidth' => 1,
                ],
            ],
        ];
    }
}
