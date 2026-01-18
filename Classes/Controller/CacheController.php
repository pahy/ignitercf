<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Controller;

use Pahy\Ignitercf\Service\CacheClearService;
use Pahy\Ignitercf\Service\CloudflareApiService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * AJAX controller for cache purging and connection testing
 */
final class CacheController
{
    public function __construct(
        private readonly CacheClearService $cacheClearService,
        private readonly CloudflareApiService $cloudflareApiService,
        private readonly ConfigurationService $configurationService,
        private readonly SiteFinder $siteFinder
    ) {}

    /**
     * AJAX: Purge single page (Context-Menu)
     */
    public function clearPageAction(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $pageId = (int)($queryParams['pageId'] ?? 0);

        if ($pageId === 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid page ID',
            ], 400);
        }

        try {
            $this->cacheClearService->clearCacheForPage($pageId);

            return new JsonResponse([
                'success' => true,
                'message' => sprintf('Cloudflare cache cleared for page %d', $pageId),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: Purge all zones (Cache-Dropdown)
     */
    public function clearAllAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->cacheClearService->clearAllZones();

            return new JsonResponse([
                'success' => true,
                'message' => 'Cloudflare cache cleared for all zones',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: Test Cloudflare connection for a specific site
     */
    public function testConnectionAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $siteIdentifier = $params['site'] ?? '';

        if (empty($siteIdentifier)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Site identifier is required',
            ], 400);
        }

        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => sprintf('Site "%s" not found', $siteIdentifier),
            ], 404);
        }

        // Check if Zone ID is configured
        $zoneId = $this->configurationService->getZoneIdForSite($site);
        if (empty($zoneId)) {
            return new JsonResponse([
                'success' => false,
                'status' => 'not_configured',
                'message' => 'Zone ID is not configured for this site',
            ]);
        }

        // Verify token with Cloudflare
        $result = $this->cloudflareApiService->verifyToken($site);

        return new JsonResponse([
            'success' => $result['valid'],
            'status' => $result['status'],
            'message' => $result['message'],
            'expires_on' => $result['expires_on'] ?? null,
        ]);
    }
}
