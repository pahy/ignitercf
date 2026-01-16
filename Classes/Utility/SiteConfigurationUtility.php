<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Utility;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Utility class for Site Configuration related operations
 *
 * Note: Cloudflare configuration check (zoneId, apiToken, enabled) is now handled
 * by ConfigurationService. This utility only provides site lookups.
 */
final class SiteConfigurationUtility
{
    public function __construct(
        private readonly SiteFinder $siteFinder
    ) {}

    /**
     * Get all sites that contain the given page
     *
     * @param int $pageId The page UID
     * @return array<int, array{site: Site, pageId: int}>
     */
    public function getSitesForPage(int $pageId): array
    {
        $sites = [];

        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
            $sites[] = [
                'site' => $site,
                'pageId' => $pageId,
            ];
        } catch (\Exception) {
            // No site found - can happen for sys folders
        }

        return $sites;
    }

    /**
     * Get all configured sites
     *
     * Note: Filtering for Cloudflare-enabled sites is done by the caller
     * using ConfigurationService::isSiteConfigured()
     *
     * @return array<int, array{site: Site}>
     */
    public function getAllSites(): array
    {
        $sites = [];

        foreach ($this->siteFinder->getAllSites() as $site) {
            $sites[] = ['site' => $site];
        }

        return $sites;
    }
}
