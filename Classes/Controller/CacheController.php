<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Controller;

use Pahy\Ignitercf\Service\CacheClearService;
use Pahy\Ignitercf\Service\CloudflareApiService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Pahy\Ignitercf\Service\TestStatusService;
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
        private readonly SiteFinder $siteFinder,
        private readonly TestStatusService $testStatusService,
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

            // Full connection test (token + zone)
            $result = $this->cloudflareApiService->testConnection($site);

            // Record test status
            if ($result['success']) {
                $this->testStatusService->recordSuccessfulTest($siteIdentifier);
            } else {
                $this->testStatusService->recordFailedTest($siteIdentifier);
            }

            return new JsonResponse([
                'success' => $result['success'],
                'token' => $result['token'],
                'zone' => $result['zone'],
                'responseTimeMs' => round($result['responseTimeMs'], 0),
            ]);
        } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => sprintf('Site "%s" not found', $siteIdentifier),
            ], 404);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'token' => ['valid' => false, 'status' => 'error', 'message' => $e->getMessage()],
                'zone' => ['valid' => false, 'status' => 'error', 'message' => 'Not tested', 'name' => ''],
                'responseTimeMs' => 0,
            ], 500);
        }
    }
}
