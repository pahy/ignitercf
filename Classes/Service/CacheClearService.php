<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Service;

use Pahy\Ignitercf\Dto\PurgeResult;
use Pahy\Ignitercf\Exception\CloudflareException;
use Pahy\Ignitercf\Utility\SiteConfigurationUtility;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Central orchestration service for Cloudflare cache clearing
 */
final class CacheClearService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly SiteConfigurationUtility $siteConfigurationUtility,
        private readonly UrlBuilderService $urlBuilderService,
        private readonly CloudflareApiService $cloudflareApiService,
        private readonly ConfigurationService $configurationService
    ) {}

    /**
     * Purge cache for specific pages and languages
     *
     * @param array<int> $pageIds Page UIDs to purge
     * @param array<int> $languageIds Language UIDs to purge
     * @return PurgeResult Result of the purge operation
     */
    public function clearCacheForPages(array $pageIds, array $languageIds): PurgeResult
    {
        // Check global enabled flag
        if (!$this->configurationService->isEnabled()) {
            return PurgeResult::empty();
        }

        $urlsByZone = [];

        foreach ($pageIds as $pageId) {
            // Get site for this page
            $siteConfigs = $this->siteConfigurationUtility->getSitesForPage($pageId);

            foreach ($siteConfigs as $siteConfig) {
                $site = $siteConfig['site'];

                // Check if site has valid Cloudflare configuration
                if (!$this->configurationService->isSiteConfigured($site)) {
                    continue;
                }

                $zoneId = $this->configurationService->getZoneIdForSite($site);

                foreach ($languageIds as $languageId) {
                    $url = $this->urlBuilderService->buildUrl($pageId, $languageId, $site);

                    if ($url === null) {
                        continue;
                    }

                    if (!isset($urlsByZone[$zoneId])) {
                        $urlsByZone[$zoneId] = [
                            'urls' => [],
                            'page_ids' => [],
                            'site' => $site,
                        ];
                    }

                    // Avoid duplicates
                    if (!in_array($url, $urlsByZone[$zoneId]['urls'], true)) {
                        $urlsByZone[$zoneId]['urls'][] = $url;
                    }

                    // Track page IDs (avoid duplicates)
                    if (!in_array($pageId, $urlsByZone[$zoneId]['page_ids'], true)) {
                        $urlsByZone[$zoneId]['page_ids'][] = $pageId;
                    }
                }
            }
        }

        return $this->purgeUrlsByZone($urlsByZone);
    }

    /**
     * Purge cache for a single page (all languages)
     *
     * @param int $pageId Page UID
     * @return PurgeResult Result of the purge operation
     */
    public function clearCacheForPage(int $pageId): PurgeResult
    {
        // Check global enabled flag
        if (!$this->configurationService->isEnabled()) {
            return PurgeResult::empty();
        }

        $siteConfigs = $this->siteConfigurationUtility->getSitesForPage($pageId);

        if (empty($siteConfigs)) {
            return PurgeResult::empty();
        }

        $site = $siteConfigs[0]['site'];
        $languageIds = [];

        foreach ($site->getAllLanguages() as $language) {
            if ($language->isEnabled()) {
                $languageIds[] = $language->getLanguageId();
            }
        }

        return $this->clearCacheForPages([$pageId], $languageIds);
    }

    /**
     * Purge entire cache for all configured zones
     *
     * @return PurgeResult Result of the purge operation
     */
    public function clearAllZones(): PurgeResult
    {
        // Check global enabled flag
        if (!$this->configurationService->isEnabled()) {
            return PurgeResult::empty();
        }

        $allSites = $this->siteConfigurationUtility->getAllSites();
        $results = [];

        foreach ($allSites as $siteData) {
            $site = $siteData['site'];

            // Check if site has valid Cloudflare configuration
            if (!$this->configurationService->isSiteConfigured($site)) {
                continue;
            }

            $zoneId = $this->configurationService->getZoneIdForSite($site);

            try {
                $this->cloudflareApiService->purgeEverything($site);

                $this->logger?->info('Cloudflare zone purged (everything)', [
                    'zone_id' => $zoneId,
                    'site' => $site->getIdentifier(),
                ]);

                $results[] = PurgeResult::success(1, [], $site->getIdentifier());
            } catch (CloudflareException $e) {
                $this->logger?->error('Cloudflare purge everything failed', [
                    'zone_id' => $zoneId,
                    'site' => $site->getIdentifier(),
                    'error' => $e->getMessage(),
                ]);

                $results[] = PurgeResult::failure($e->getMessage(), $site->getIdentifier());
            }
        }

        return empty($results) ? PurgeResult::empty() : PurgeResult::merge($results);
    }

    /**
     * Internal: Purge URLs grouped by zone
     *
     * @param array<string, array{urls: array<string>, page_ids: array<int>, site: \TYPO3\CMS\Core\Site\Entity\Site}> $urlsByZone
     * @return PurgeResult Result of the purge operation
     */
    private function purgeUrlsByZone(array $urlsByZone): PurgeResult
    {
        if (empty($urlsByZone)) {
            return PurgeResult::empty();
        }

        $results = [];

        foreach ($urlsByZone as $zoneId => $data) {
            $urls = $data['urls'];
            $pageIds = $data['page_ids'] ?? [];
            $site = $data['site'];

            // Split into batches of 30 URLs
            $batches = array_chunk($urls, 30);

            foreach ($batches as $batch) {
                try {
                    $success = $this->cloudflareApiService->purgeUrls($batch, $site, $pageIds);

                    if ($success) {
                        $this->logger?->info('Cloudflare cache purged', [
                            'zone_id' => $zoneId,
                            'urls_count' => count($batch),
                            'urls' => $batch,
                            'page_ids' => $pageIds,
                        ]);

                        $results[] = PurgeResult::success(count($batch), $batch, $site->getIdentifier());
                    }
                } catch (CloudflareException $e) {
                    $this->logger?->error('Cloudflare purge failed', [
                        'zone_id' => $zoneId,
                        'urls' => $batch,
                        'page_ids' => $pageIds,
                        'error' => $e->getMessage(),
                    ]);

                    $results[] = PurgeResult::failure($e->getMessage(), $site->getIdentifier());
                    // Don't rethrow - save operation should succeed
                }
            }
        }

        return empty($results) ? PurgeResult::empty() : PurgeResult::merge($results);
    }
}
