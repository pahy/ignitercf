<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Middleware;

use Pahy\Ignitercf\Service\ConfigurationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * PSR-15 Middleware to prevent Cloudflare caching for backend previews
 */
final class CacheControlMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ConfigurationService $configurationService
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check if middleware is enabled
        if (!$this->configurationService->isMiddlewareEnabled()) {
            return $handler->handle($request);
        }

        // Generate response first
        $response = $handler->handle($request);

        // Check 1: Backend user logged in?
        $hasBackendSession = $this->hasBackendUserSession($request);

        // Check 2: Current page should not be cached?
        $shouldNotCache = $this->shouldNotCachePage($request);

        // If any check is true: Set no-cache headers
        if ($hasBackendSession || $shouldNotCache) {
            $this->logger?->debug('Setting no-cache headers', [
                'backend_session' => $hasBackendSession,
                'should_not_cache' => $shouldNotCache,
                'uri' => (string)$request->getUri(),
            ]);

            return $response->withHeader(
                'Cache-Control',
                'no-store, private, max-age=0, must-revalidate'
            );
        }

        return $response;
    }

    /**
     * Check if backend user session exists
     */
    private function hasBackendUserSession(ServerRequestInterface $request): bool
    {
        $cookies = $request->getCookieParams();
        return isset($cookies['be_typo_user']) && !empty($cookies['be_typo_user']);
    }

    /**
     * Check if page should not be cached
     *
     * Uses version-specific page data access:
     * - TYPO3 v13+: PageInformation object via request attribute
     * - TYPO3 v12: $GLOBALS['TSFE']->page (deprecated in v13)
     */
    private function shouldNotCachePage(ServerRequestInterface $request): bool
    {
        $page = $this->getPageRecord($request);
        if ($page === null) {
            return false;
        }

        // Check 1: Page hidden?
        if ((int)($page['hidden'] ?? 0) === 1) {
            return true;
        }

        // Check 2: Starttime not reached?
        $starttime = (int)($page['starttime'] ?? 0);
        if ($starttime > 0 && $starttime > time()) {
            return true;
        }

        // Check 3: Endtime exceeded?
        $endtime = (int)($page['endtime'] ?? 0);
        if ($endtime > 0 && $endtime < time()) {
            return true;
        }

        // Check 4: Frontend groups protected?
        $feGroup = (string)($page['fe_group'] ?? '');
        if (!empty($feGroup) && $feGroup !== '0') {
            // Page is protected - don't cache
            return true;
        }

        return false;
    }

    /**
     * Get page record from request (v12/v13 compatible)
     *
     * @return array<string, mixed>|null Page record or null
     */
    private function getPageRecord(ServerRequestInterface $request): ?array
    {
        // TYPO3 v13+: Use PageInformation from request attribute
        $pageInformation = $request->getAttribute('frontend.page.information');
        if ($pageInformation !== null && method_exists($pageInformation, 'getPageRecord')) {
            return $pageInformation->getPageRecord();
        }

        // TYPO3 v12 fallback: Use TSFE (deprecated in v13, removed in v14)
        // @extensionScannerIgnoreLine
        if (isset($GLOBALS['TSFE']) && is_object($GLOBALS['TSFE']) && isset($GLOBALS['TSFE']->page)) {
            return $GLOBALS['TSFE']->page;
        }

        return null;
    }
}
