<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Service to build frontend URLs for pages
 */
final class UrlBuilderService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Invalid page doktypes that should not be purged
     */
    private const INVALID_DOKTYPES = [
        254, // Sysfolder
        255, // Recycler
        199, // Menu Separator
        6,   // Backend User Section
    ];

    /**
     * Build absolute frontend URL for a page
     *
     * @param int $pageId The page UID
     * @param int $languageId The language UID
     * @param Site $site The site configuration
     * @return string|null The URL or null if page should not be purged
     */
    public function buildUrl(int $pageId, int $languageId, Site $site): ?string
    {
        // Validate: Get page record
        $page = BackendUtility::getRecord('pages', $pageId);

        if ($page === null) {
            return null;
        }

        // Check doktype (no sysfolders, backend pages etc.)
        if (in_array((int)$page['doktype'], self::INVALID_DOKTYPES, true)) {
            $this->logger?->debug('Skipping page with invalid doktype', [
                'page_id' => $pageId,
                'doktype' => $page['doktype'],
            ]);
            return null;
        }

        // Check hidden/deleted
        if ((int)($page['hidden'] ?? 0) === 1 || (int)($page['deleted'] ?? 0) === 1) {
            $this->logger?->debug('Skipping hidden/deleted page', [
                'page_id' => $pageId,
            ]);
            return null;
        }

        // Get language
        try {
            $language = $site->getLanguageById($languageId);
        } catch (\InvalidArgumentException) {
            $this->logger?->warning('Language not found in site', [
                'page_id' => $pageId,
                'language_id' => $languageId,
                'site' => $site->getIdentifier(),
            ]);
            return null;
        }

        // Build URL via Site Router
        try {
            $uri = $site->getRouter()->generateUri(
                $pageId,
                ['_language' => $language]
            );

            return (string)$uri;
        } catch (\Exception $e) {
            $this->logger?->error('URL generation failed', [
                'page_id' => $pageId,
                'language_id' => $languageId,
                'site' => $site->getIdentifier(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
