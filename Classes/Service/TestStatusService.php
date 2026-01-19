<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Core\Environment;

/**
 * Service to manage last test execution timestamps
 *
 * Stores and retrieves when configuration tests were last performed per site.
 */
class TestStatusService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const CACHE_DIR = 'ignitercf/test-status';

    public function __construct(
        private readonly ConfigurationService $configurationService,
    ) {}

    /**
     * Record a successful test for a site
     */
    public function recordSuccessfulTest(string $siteIdentifier): void
    {
        $this->recordTest($siteIdentifier, 'success');
    }

    /**
     * Record a failed test for a site
     */
    public function recordFailedTest(string $siteIdentifier): void
    {
        $this->recordTest($siteIdentifier, 'failed');
    }

     /**
      * Get last test information for a site
      *
      * @return array{timestamp: int, status: string, formattedTime: string, tooltipTime: string}|null
      */
     public function getLastTestInfo(string $siteIdentifier): ?array
     {
         $data = $this->readTestData($siteIdentifier);

         if ($data === null) {
             return null;
         }

         return [
             'timestamp' => $data['timestamp'],
             'status' => $data['status'],
             'formattedTime' => $this->formatTimestamp($data['timestamp']),
             'tooltipTime' => $this->formatTooltipTime($data['timestamp']),
         ];
     }

    /**
     * Get last test time formatted for display
     */
    public function getLastTestTimeFormatted(string $siteIdentifier): string
    {
        $info = $this->getLastTestInfo($siteIdentifier);

        if ($info === null) {
            return 'Never tested';
        }

        return $info['formattedTime'];
    }

    /**
     * Get last test status badge (success/failed/pending)
     */
    public function getTestStatusBadge(string $siteIdentifier): array
    {
        $info = $this->getLastTestInfo($siteIdentifier);

        if ($info === null) {
            return [
                'label' => 'Not tested',
                'class' => 'badge-secondary',
                'icon' => 'actions-question-circle',
            ];
        }

        if ($info['status'] === 'success') {
            return [
                'label' => 'Last test: ' . $info['formattedTime'],
                'class' => 'badge-success',
                'icon' => 'actions-check-circle',
            ];
        }

        return [
            'label' => 'Last test failed: ' . $info['formattedTime'],
            'class' => 'badge-danger',
            'icon' => 'actions-exclamation-circle',
        ];
    }

    /**
     * Record a test with timestamp and status
     */
    private function recordTest(string $siteIdentifier, string $status): void
    {
        try {
            $file = $this->getTestFilePath($siteIdentifier);
            $dir = dirname($file);

            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $data = [
                'timestamp' => time(),
                'status' => $status,
                'site' => $siteIdentifier,
            ];

            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Failed to record test status', [
                'site' => $siteIdentifier,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Read test data for a site
     *
     * @return array{timestamp: int, status: string}|null
     */
    private function readTestData(string $siteIdentifier): ?array
    {
        try {
            $file = $this->getTestFilePath($siteIdentifier);

            if (!file_exists($file)) {
                return null;
            }

            $json = file_get_contents($file);

            if ($json === false) {
                return null;
            }

            $data = json_decode($json, true);

            if (!is_array($data) || !isset($data['timestamp'], $data['status'])) {
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Failed to read test status', [
                'site' => $siteIdentifier,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get cache file path for a site's test status
     */
    private function getTestFilePath(string $siteIdentifier): string
    {
        $cacheDir = Environment::getVarPath() . '/cache/' . self::CACHE_DIR;
        return $cacheDir . '/' . md5($siteIdentifier) . '.json';
    }

    /**
     * Format timestamp for display
     */
    private function formatTimestamp(int $timestamp): string
    {
        $now = time();
        $diff = $now - $timestamp;

        // Less than 1 minute
        if ($diff < 60) {
            return 'Just now';
        }

        // Less than 1 hour
        if ($diff < 3600) {
            $minutes = (int)floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        }

        // Less than 12 hours
        if ($diff < 43200) {
            $hours = (int)floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }

        // Fallback to date format (after 12 hours)
        return date('H:i:s d.m.Y', $timestamp);
    }

    /**
     * Format timestamp for tooltip display
     */
    private function formatTooltipTime(int $timestamp): string
    {
        return date('H:i d.m.Y', $timestamp);
    }
}
