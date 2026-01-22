<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Dto;

/**
 * Data Transfer Object for cache purge results
 */
final class PurgeResult
{
    /**
     * @param bool $success Whether all purges were successful
     * @param int $urlCount Number of URLs purged
     * @param int $successCount Number of successful purges
     * @param int $errorCount Number of failed purges
     * @param array<string> $errors Error messages
     * @param array<string> $purgedUrls Successfully purged URLs
     * @param string $siteIdentifier Site identifier (if single site)
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $urlCount,
        public readonly int $successCount = 0,
        public readonly int $errorCount = 0,
        public readonly array $errors = [],
        public readonly array $purgedUrls = [],
        public readonly string $siteIdentifier = ''
    ) {}

    /**
     * Create a successful result
     */
    public static function success(int $urlCount, array $purgedUrls = [], string $siteIdentifier = ''): self
    {
        return new self(
            success: true,
            urlCount: $urlCount,
            successCount: $urlCount,
            errorCount: 0,
            errors: [],
            purgedUrls: $purgedUrls,
            siteIdentifier: $siteIdentifier
        );
    }

    /**
     * Create a failed result
     */
    public static function failure(string $error, string $siteIdentifier = ''): self
    {
        return new self(
            success: false,
            urlCount: 0,
            successCount: 0,
            errorCount: 1,
            errors: [$error],
            purgedUrls: [],
            siteIdentifier: $siteIdentifier
        );
    }

    /**
     * Create an empty result (nothing to purge)
     */
    public static function empty(): self
    {
        return new self(
            success: true,
            urlCount: 0,
            successCount: 0,
            errorCount: 0,
            errors: [],
            purgedUrls: [],
            siteIdentifier: ''
        );
    }

    /**
     * Merge multiple results
     *
     * @param PurgeResult[] $results
     */
    public static function merge(array $results): self
    {
        $totalUrls = 0;
        $totalSuccess = 0;
        $totalErrors = 0;
        $allErrors = [];
        $allPurgedUrls = [];
        $siteIdentifier = '';

        foreach ($results as $result) {
            $totalUrls += $result->urlCount;
            $totalSuccess += $result->successCount;
            $totalErrors += $result->errorCount;
            $allErrors = array_merge($allErrors, $result->errors);
            $allPurgedUrls = array_merge($allPurgedUrls, $result->purgedUrls);

            // Use first non-empty site identifier
            if ($siteIdentifier === '' && $result->siteIdentifier !== '') {
                $siteIdentifier = $result->siteIdentifier;
            }
        }

        return new self(
            success: $totalErrors === 0,
            urlCount: $totalUrls,
            successCount: $totalSuccess,
            errorCount: $totalErrors,
            errors: $allErrors,
            purgedUrls: $allPurgedUrls,
            siteIdentifier: count($results) === 1 ? $siteIdentifier : ''
        );
    }

    /**
     * Check if there were any errors
     */
    public function hasErrors(): bool
    {
        return $this->errorCount > 0;
    }

    /**
     * Check if anything was purged
     */
    public function hasPurgedUrls(): bool
    {
        return $this->successCount > 0;
    }
}
