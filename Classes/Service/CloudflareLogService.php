<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Core\Environment;

/**
 * Service for logging Cloudflare API calls to a dedicated log file
 *
 * Log file: var/log/typo3_ignitercf_cloudflare.log
 * Format: JSON per line (JSONL) for easy parsing
 */
final class CloudflareLogService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const LOG_FILE_NAME = 'typo3_ignitercf_cloudflare.log';

    public function __construct(
        private readonly ConfigurationService $configurationService
    ) {}

    /**
     * Log a successful purge request
     *
     * @param string $type Type of purge (purge_urls, purge_everything)
     * @param string $zoneId Cloudflare zone ID
     * @param string $siteIdentifier TYPO3 site identifier
     * @param array<string> $urls Purged URLs (empty for purge_everything)
     * @param float $responseTimeMs Response time in milliseconds
     */
    public function logSuccess(
        string $type,
        string $zoneId,
        string $siteIdentifier,
        array $urls,
        float $responseTimeMs
    ): void {
        if (!$this->configurationService->shouldLogAllCalls()) {
            return;
        }

        $this->writeLogEntry([
            'timestamp' => $this->getTimestamp(),
            'type' => $type,
            'zone_id' => $zoneId,
            'site' => $siteIdentifier,
            'urls' => $urls,
            'urls_count' => count($urls),
            'success' => true,
            'response_time_ms' => round($responseTimeMs, 2),
        ]);
    }

    /**
     * Log a failed purge request
     *
     * @param string $type Type of purge (purge_urls, purge_everything)
     * @param string $zoneId Cloudflare zone ID
     * @param string $siteIdentifier TYPO3 site identifier
     * @param array<string> $urls Attempted URLs (empty for purge_everything)
     * @param string $errorMessage Error message
     * @param float $responseTimeMs Response time in milliseconds
     */
    public function logError(
        string $type,
        string $zoneId,
        string $siteIdentifier,
        array $urls,
        string $errorMessage,
        float $responseTimeMs
    ): void {
        if (!$this->configurationService->shouldLogErrors()) {
            return;
        }

        $this->writeLogEntry([
            'timestamp' => $this->getTimestamp(),
            'type' => $type,
            'zone_id' => $zoneId,
            'site' => $siteIdentifier,
            'urls' => $urls,
            'urls_count' => count($urls),
            'success' => false,
            'error' => $errorMessage,
            'response_time_ms' => round($responseTimeMs, 2),
        ]);
    }

    /**
     * Get recent log entries
     *
     * @param int $limit Maximum number of entries to return
     * @return array<int, array<string, mixed>> Log entries (newest first)
     */
    public function getRecentEntries(int $limit = 20): array
    {
        $logFile = $this->getLogFilePath();

        if (!file_exists($logFile)) {
            return [];
        }

        $entries = [];
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        // Read from end (newest entries last in file)
        $lines = array_reverse($lines);

        foreach ($lines as $line) {
            if (count($entries) >= $limit) {
                break;
            }

            $entry = json_decode($line, true);
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Delete log entries older than the specified number of days
     *
     * @param int $retentionDays Days to keep (0 = keep all)
     * @return int Number of entries deleted
     */
    public function purgeOldEntries(int $retentionDays): int
    {
        if ($retentionDays <= 0) {
            return 0;
        }

        $logFile = $this->getLogFilePath();

        if (!file_exists($logFile)) {
            return 0;
        }

        $cutoffDate = new \DateTimeImmutable("-{$retentionDays} days");
        $cutoffTimestamp = $cutoffDate->format('c');

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return 0;
        }

        $keptLines = [];
        $deletedCount = 0;

        foreach ($lines as $line) {
            $entry = json_decode($line, true);

            if (!is_array($entry) || !isset($entry['timestamp'])) {
                // Keep malformed lines
                $keptLines[] = $line;
                continue;
            }

            if ($entry['timestamp'] >= $cutoffTimestamp) {
                $keptLines[] = $line;
            } else {
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            file_put_contents($logFile, implode("\n", $keptLines) . "\n", LOCK_EX);
        }

        return $deletedCount;
    }

    /**
     * Get statistics for a time period
     *
     * @param int $days Number of days to look back
     * @return array{total: int, success: int, errors: int}
     */
    public function getStatistics(int $days = 7): array
    {
        $cutoffDate = new \DateTimeImmutable("-{$days} days");
        $cutoffTimestamp = $cutoffDate->format('c');

        $logFile = $this->getLogFilePath();

        $stats = [
            'total' => 0,
            'success' => 0,
            'errors' => 0,
        ];

        if (!file_exists($logFile)) {
            return $stats;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return $stats;
        }

        foreach ($lines as $line) {
            $entry = json_decode($line, true);

            if (!is_array($entry) || !isset($entry['timestamp'])) {
                continue;
            }

            if ($entry['timestamp'] < $cutoffTimestamp) {
                continue;
            }

            $stats['total']++;

            if ($entry['success'] ?? false) {
                $stats['success']++;
            } else {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Get the log file path
     */
    public function getLogFilePath(): string
    {
        return Environment::getVarPath() . '/log/' . self::LOG_FILE_NAME;
    }

    /**
     * Write a log entry to the file
     *
     * @param array<string, mixed> $entry Log entry data
     */
    private function writeLogEntry(array $entry): void
    {
        $logFile = $this->getLogFilePath();
        $logDir = dirname($logFile);

        // Ensure log directory exists
        if (!is_dir($logDir)) {
            try {
                mkdir($logDir, 0775, true);
            } catch (\Exception $e) {
                $this->logger?->error('IgniterCF: Failed to create log directory', [
                    'path' => $logDir,
                    'error' => $e->getMessage(),
                ]);
                return;
            }
        }

        $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return;
        }

        try {
            file_put_contents($logFile, $json . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Failed to write log file', [
                'path' => $logFile,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get current timestamp in ISO 8601 format
     */
    private function getTimestamp(): string
    {
        return (new \DateTimeImmutable())->format('c');
    }
}
