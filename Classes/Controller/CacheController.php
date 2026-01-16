<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Controller;

use Pahy\Ignitercf\Service\CacheClearService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * AJAX controller for manual cache purging
 */
final class CacheController
{
    public function __construct(
        private readonly CacheClearService $cacheClearService
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
}
