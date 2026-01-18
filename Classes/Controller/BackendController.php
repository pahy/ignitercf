<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Controller;

use Pahy\Ignitercf\Service\CloudflareApiService;
use Pahy\Ignitercf\Service\CloudflareLogService;
use Pahy\Ignitercf\Service\ConfigurationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Backend module controller for IgniterCF administration
 */
#[AsController]
final class BackendController extends ActionController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly SiteFinder $siteFinder,
        private readonly ConfigurationService $configurationService,
        private readonly CloudflareApiService $cloudflareApiService,
        private readonly CloudflareLogService $cloudflareLogService
    ) {}

    /**
     * Main dashboard view
     */
    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        // Get all sites with their configuration status
        $sitesStatus = $this->getSitesStatus();

        // Get recent log entries
        $recentLogs = $this->cloudflareLogService->getRecentEntries(20);

        // Get statistics
        $statistics = $this->cloudflareLogService->getStatistics(7);

        // Get global settings
        $globalSettings = [
            'enabled' => $this->configurationService->isEnabled(),
            'logLevel' => $this->configurationService->getLogLevel(),
            'logRetentionDays' => $this->configurationService->getLogRetentionDays(),
            'autoPurgeOnSave' => $this->configurationService->isAutoPurgeOnSaveEnabled(),
            'purgeOnClearAll' => $this->configurationService->isPurgeOnClearAllEnabled(),
            'middlewareEnabled' => $this->configurationService->isMiddlewareEnabled(),
        ];

        $moduleTemplate->assignMultiple([
            'sitesStatus' => $sitesStatus,
            'recentLogs' => $recentLogs,
            'statistics' => $statistics,
            'globalSettings' => $globalSettings,
            'allConfigured' => $this->areAllSitesConfigured($sitesStatus),
        ]);

        return $moduleTemplate->renderResponse('Backend/Index');
    }

    /**
     * AJAX: Test connection for a specific site
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

    /**
     * Get configuration status for all sites
     *
     * @return array<string, array<string, mixed>>
     */
    private function getSitesStatus(): array
    {
        $sites = $this->siteFinder->getAllSites();
        $sitesStatus = [];

        foreach ($sites as $site) {
            $identifier = $site->getIdentifier();
            $zoneId = $this->configurationService->getZoneIdForSite($site);
            $apiToken = $this->configurationService->getApiTokenForSite($site);
            $siteEnabled = $this->configurationService->isSiteEnabled($site);

            $issues = [];
            $hints = [];

            // Check Zone ID
            if (empty($zoneId)) {
                $issues[] = 'zone_id_missing';
                $hints[] = [
                    'type' => 'zone_id',
                    'message' => 'Zone ID is not configured',
                    'solution' => sprintf(
                        'Add to config/sites/%s/config.yaml:%scloudflare:%s  zoneId: \'your-zone-id\'',
                        $identifier,
                        "\n",
                        "\n"
                    ),
                    'link' => 'https://dash.cloudflare.com/ → Select domain → Overview → Zone ID',
                ];
            }

            // Check API Token
            if (empty($apiToken)) {
                $issues[] = 'api_token_missing';
                $envVarName = 'IGNITERCF_TOKEN_' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', $identifier));
                $hints[] = [
                    'type' => 'api_token',
                    'message' => 'API Token is not configured',
                    'solution' => sprintf(
                        'Set environment variable: %s=your-api-token%sOr global: IGNITERCF_API_TOKEN=your-api-token',
                        $envVarName,
                        "\n"
                    ),
                    'link' => 'https://dash.cloudflare.com/profile/api-tokens → Create Token',
                ];
            }

            // Check if site is disabled
            if (!$siteEnabled) {
                $issues[] = 'site_disabled';
                $hints[] = [
                    'type' => 'site_disabled',
                    'message' => 'Cloudflare is disabled for this site',
                    'solution' => sprintf(
                        'In config/sites/%s/config.yaml, set:%scloudflare:%s  enabled: true',
                        $identifier,
                        "\n",
                        "\n"
                    ),
                ];
            }

            $isConfigured = empty($issues) && $siteEnabled;

            $sitesStatus[$identifier] = [
                'site' => $site,
                'identifier' => $identifier,
                'baseUrl' => (string)$site->getBase(),
                'zoneId' => $zoneId,
                'hasApiToken' => !empty($apiToken),
                'apiTokenMasked' => !empty($apiToken) ? substr($apiToken, 0, 8) . '***' : '',
                'siteEnabled' => $siteEnabled,
                'isConfigured' => $isConfigured,
                'issues' => $issues,
                'hints' => $hints,
            ];
        }

        return $sitesStatus;
    }

    /**
     * Check if all sites are properly configured
     *
     * @param array<string, array<string, mixed>> $sitesStatus
     */
    private function areAllSitesConfigured(array $sitesStatus): bool
    {
        foreach ($sitesStatus as $status) {
            if (!$status['isConfigured']) {
                return false;
            }
        }

        return !empty($sitesStatus);
    }
}
