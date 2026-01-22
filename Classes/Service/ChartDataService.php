<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Service for generating and reading pre-computed chart data
 *
 * Chart data is stored in var/cache/ignitercf/chart-data.json
 * and generated via CLI command or scheduler task for performance.
 */
final class ChartDataService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const CACHE_DIR = 'ignitercf';
    private const CACHE_FILE = 'chart-data.json';

    public function __construct(
        private readonly CloudflareLogService $cloudflareLogService,
        private readonly ConfigurationService $configurationService,
        private readonly SiteFinder $siteFinder
    ) {}

    /**
     * Generate chart data from logs and save to JSON file
     *
     * @param int $days Number of days to include in statistics
     * @return array{generated_at: string, daily: array, totals_7d: array, sites_status: array}
     */
    public function generate(int $days = 7): array
    {
        $data = [
            'generated_at' => (new \DateTimeImmutable())->format('c'),
            'daily' => $this->generateDailyStatistics($days),
            'totals_7d' => $this->cloudflareLogService->getStatistics($days),
            'sites_status' => $this->generateSitesStatus(),
        ];

        $this->saveToCache($data);

        return $data;
    }

    /**
     * Get cached chart data
     *
     * @return array|null Cached data or null if not available
     */
    public function getData(): ?array
    {
        $cacheFile = $this->getCacheFilePath();

        if (!file_exists($cacheFile)) {
            return null;
        }

        $content = file_get_contents($cacheFile);

        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Get chart data, generating if not cached or stale
     *
     * @param int $maxAgeMinutes Maximum age of cache before regenerating
     * @return array Chart data
     */
    public function getDataOrGenerate(int $maxAgeMinutes = 60): array
    {
        $data = $this->getData();

        if ($data === null || $this->isCacheStale($data, $maxAgeMinutes)) {
            $data = $this->generate();
        }

        return $data;
    }

    /**
     * Check if cache file exists
     */
    public function hasCachedData(): bool
    {
        return file_exists($this->getCacheFilePath());
    }

    /**
     * Get the age of the cache in minutes
     *
     * @return int|null Age in minutes or null if no cache
     */
    public function getCacheAgeMinutes(): ?int
    {
        $data = $this->getData();

        if ($data === null || !isset($data['generated_at'])) {
            return null;
        }

        try {
            $generatedAt = new \DateTimeImmutable($data['generated_at']);
            $now = new \DateTimeImmutable();
            $diff = $now->getTimestamp() - $generatedAt->getTimestamp();

            return (int)floor($diff / 60);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Delete the cache file
     */
    public function clearCache(): bool
    {
        $cacheFile = $this->getCacheFilePath();

        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return true;
    }

    /**
     * Generate daily statistics from log entries
     *
     * @param int $days Number of days
     * @return array<int, array{date: string, success: int, errors: int, avg_response_ms: float}>
     */
    private function generateDailyStatistics(int $days): array
    {
        $logFile = $this->cloudflareLogService->getLogFilePath();

        if (!file_exists($logFile)) {
            return $this->getEmptyDailyStats($days);
        }

        $cutoffDate = new \DateTimeImmutable("-{$days} days");
        $cutoffTimestamp = $cutoffDate->format('c');

        // Initialize daily buckets
        $dailyData = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = (new \DateTimeImmutable("-{$i} days"))->format('Y-m-d');
            $dailyData[$date] = [
                'date' => $date,
                'success' => 0,
                'errors' => 0,
                'total_response_ms' => 0.0,
                'count' => 0,
            ];
        }

        // Parse log entries using SplFileObject for memory efficiency
        try {
            $file = new \SplFileObject($logFile, 'r');
            while (!$file->eof()) {
                $line = $file->fgets();

                if (empty($line) || $line === false) {
                    continue;
                }

                $entry = json_decode(trim($line), true);

                if (!is_array($entry) || !isset($entry['timestamp'])) {
                    continue;
                }

                if ($entry['timestamp'] < $cutoffTimestamp) {
                    continue;
                }

                try {
                    $entryDate = (new \DateTimeImmutable($entry['timestamp']))->format('Y-m-d');
                } catch (\Exception) {
                    continue;
                }

                if (!isset($dailyData[$entryDate])) {
                    continue;
                }

                if ($entry['success'] ?? false) {
                    $dailyData[$entryDate]['success']++;
                } else {
                    $dailyData[$entryDate]['errors']++;
                }

                $dailyData[$entryDate]['total_response_ms'] += (float)($entry['response_time_ms'] ?? 0);
                $dailyData[$entryDate]['count']++;
            }
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Failed to read log file for chart data', [
                'path' => $logFile,
                'error' => $e->getMessage(),
            ]);
            return $this->getEmptyDailyStats($days);
        }

        // Calculate averages and format output
        $result = [];
        foreach ($dailyData as $data) {
            $avgResponseMs = $data['count'] > 0
                ? round($data['total_response_ms'] / $data['count'], 2)
                : 0.0;

            $result[] = [
                'date' => $data['date'],
                'success' => $data['success'],
                'errors' => $data['errors'],
                'avg_response_ms' => $avgResponseMs,
            ];
        }

        return $result;
    }

    /**
     * Generate sites configuration status
     *
     * @return array{configured: int, total: int, sites: array<string, array{identifier: string, configured: bool}>}
     */
    private function generateSitesStatus(): array
    {
        $sites = $this->siteFinder->getAllSites();
        $configured = 0;
        $sitesData = [];

        foreach ($sites as $site) {
            $isConfigured = $this->configurationService->isSiteConfigured($site);

            if ($isConfigured) {
                $configured++;
            }

            $sitesData[$site->getIdentifier()] = [
                'identifier' => $site->getIdentifier(),
                'configured' => $isConfigured,
            ];
        }

        return [
            'configured' => $configured,
            'total' => count($sites),
            'sites' => $sitesData,
        ];
    }

    /**
     * Get empty daily statistics array
     *
     * @param int $days Number of days
     * @return array<int, array{date: string, success: int, errors: int, avg_response_ms: float}>
     */
    private function getEmptyDailyStats(int $days): array
    {
        $result = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = (new \DateTimeImmutable("-{$i} days"))->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'success' => 0,
                'errors' => 0,
                'avg_response_ms' => 0.0,
            ];
        }

        return $result;
    }

    /**
     * Check if cache is stale
     *
     * @param array $data Cached data
     * @param int $maxAgeMinutes Maximum age in minutes
     */
    private function isCacheStale(array $data, int $maxAgeMinutes): bool
    {
        if (!isset($data['generated_at'])) {
            return true;
        }

        try {
            $generatedAt = new \DateTimeImmutable($data['generated_at']);
            $maxAge = new \DateTimeImmutable("-{$maxAgeMinutes} minutes");

            return $generatedAt < $maxAge;
        } catch (\Exception) {
            return true;
        }
    }

    /**
     * Save data to cache file
     *
     * @param array $data Data to cache
     */
    private function saveToCache(array $data): void
    {
        $cacheFile = $this->getCacheFilePath();
        $cacheDir = dirname($cacheFile);

        // Ensure cache directory exists
        if (!is_dir($cacheDir)) {
            try {
                mkdir($cacheDir, 0775, true);
            } catch (\Exception $e) {
                $this->logger?->error('IgniterCF: Failed to create chart data cache directory', [
                    'path' => $cacheDir,
                    'error' => $e->getMessage(),
                ]);
                return;
            }
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return;
        }

        try {
            file_put_contents($cacheFile, $json, LOCK_EX);
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Failed to write chart data cache file', [
                'path' => $cacheFile,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get cache file path
     */
    private function getCacheFilePath(): string
    {
        return Environment::getVarPath() . '/cache/' . self::CACHE_DIR . '/' . self::CACHE_FILE;
    }
}
