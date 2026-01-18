<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Service;

use Pahy\Ignitercf\Exception\CloudflareException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Service for Cloudflare API v4 communication
 */
final class CloudflareApiService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const API_ENDPOINT = 'https://api.cloudflare.com/client/v4/zones/%s/purge_cache';
    private const MAX_URLS_PER_REQUEST = 30;

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly ConfigurationService $configurationService,
        private readonly CloudflareLogService $cloudflareLogService
    ) {}

    /**
     * Purge specific URLs from Cloudflare cache
     *
     * @param array<string> $urls URLs to purge
     * @param Site $site Site configuration containing Cloudflare settings
     * @return bool True on success
     * @throws CloudflareException On API errors
     */
    public function purgeUrls(array $urls, Site $site): bool
    {
        if (empty($urls)) {
            return true;
        }

        // Validation: Max 30 URLs per request
        if (count($urls) > self::MAX_URLS_PER_REQUEST) {
            throw new \InvalidArgumentException(
                sprintf('Max. %d URLs per request allowed, got %d', self::MAX_URLS_PER_REQUEST, count($urls))
            );
        }

        $config = $this->getCloudflareConfig($site);
        $body = json_encode(['files' => array_values($urls)]);

        return $this->sendPurgeRequest(
            zoneId: $config['zoneId'],
            apiToken: $config['apiToken'],
            body: $body,
            logType: 'purge_urls',
            siteIdentifier: $site->getIdentifier(),
            urls: $urls
        );
    }

    /**
     * Purge entire Cloudflare cache for a zone
     *
     * @param Site $site Site configuration containing Cloudflare settings
     * @return bool True on success
     * @throws CloudflareException On API errors
     */
    public function purgeEverything(Site $site): bool
    {
        $config = $this->getCloudflareConfig($site);
        $body = json_encode(['purge_everything' => true]);

        return $this->sendPurgeRequest(
            zoneId: $config['zoneId'],
            apiToken: $config['apiToken'],
            body: $body,
            logType: 'purge_everything',
            siteIdentifier: $site->getIdentifier(),
            urls: []
        );
    }

    /**
     * Get and validate Cloudflare configuration from site via ConfigurationService
     *
     * @param Site $site Site configuration
     * @return array{zoneId: string, apiToken: string}
     * @throws CloudflareException If configuration is missing
     */
    private function getCloudflareConfig(Site $site): array
    {
        // Validate configuration - throws CloudflareException if invalid
        $this->configurationService->validateSiteConfiguration($site);

        return [
            'zoneId' => $this->configurationService->getZoneIdForSite($site),
            'apiToken' => $this->configurationService->getApiTokenForSite($site),
        ];
    }

    /**
     * Send purge request to Cloudflare API
     *
     * @param string $zoneId Cloudflare zone ID
     * @param string $apiToken Cloudflare API token
     * @param string $body Request body (JSON)
     * @param string $logType Log type (purge_urls, purge_everything)
     * @param string $siteIdentifier Site identifier for logging
     * @param array<string> $urls URLs for logging
     * @return bool True on success
     * @throws CloudflareException On API errors
     */
    private function sendPurgeRequest(
        string $zoneId,
        string $apiToken,
        string $body,
        string $logType,
        string $siteIdentifier,
        array $urls
    ): bool {
        $url = sprintf(self::API_ENDPOINT, $zoneId);

        $additionalOptions = [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type' => 'application/json',
            ],
            'body' => $body,
        ];

        $startTime = microtime(true);

        try {
            $response = $this->requestFactory->request($url, 'POST', $additionalOptions);
            $responseTimeMs = (microtime(true) - $startTime) * 1000;

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            if ($statusCode !== 200) {
                $errorMessage = sprintf('API request failed with status %d: %s', $statusCode, $responseBody);
                $this->cloudflareLogService->logError($logType, $zoneId, $siteIdentifier, $urls, $errorMessage, $responseTimeMs);
                throw new CloudflareException($errorMessage);
            }

            $data = json_decode($responseBody, true);

            if (!isset($data['success']) || $data['success'] !== true) {
                $errors = $data['errors'] ?? [];
                $errorMessage = 'API returned success=false: ' . json_encode($errors);
                $this->cloudflareLogService->logError($logType, $zoneId, $siteIdentifier, $urls, $errorMessage, $responseTimeMs);
                throw new CloudflareException($errorMessage);
            }

            // Log successful request
            $this->cloudflareLogService->logSuccess($logType, $zoneId, $siteIdentifier, $urls, $responseTimeMs);

            return true;
        } catch (\Exception $e) {
            $responseTimeMs = (microtime(true) - $startTime) * 1000;

            if ($e instanceof CloudflareException) {
                throw $e;
            }

            $errorMessage = 'Cloudflare API request failed: ' . $e->getMessage();
            $this->cloudflareLogService->logError($logType, $zoneId, $siteIdentifier, $urls, $errorMessage, $responseTimeMs);

            throw new CloudflareException($errorMessage, 0, $e);
        }
    }
}
