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
    private const API_ZONE_DETAILS = 'https://api.cloudflare.com/client/v4/zones/%s';
    private const API_VERIFY_TOKEN = 'https://api.cloudflare.com/client/v4/user/tokens/verify';
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
     * Verify API token validity with Cloudflare
     *
     * @param Site $site Site configuration containing Cloudflare settings
     * @return array{valid: bool, status: string, message: string, expires_on?: string}
     */
    public function verifyToken(Site $site): array
    {
        $apiToken = $this->configurationService->getApiTokenForSite($site);

        if (empty($apiToken)) {
            return [
                'valid' => false,
                'status' => 'not_configured',
                'message' => 'API Token is not configured',
            ];
        }

        $additionalOptions = [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type' => 'application/json',
            ],
        ];

        try {
            $response = $this->requestFactory->request(self::API_VERIFY_TOKEN, 'GET', $additionalOptions);
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $data = json_decode($responseBody, true);

            if ($data === null || $statusCode !== 200 || !isset($data['success']) || $data['success'] !== true) {
                $errors = is_array($data) ? ($data['errors'] ?? []) : [];
                $errorMessage = !empty($errors) ? $errors[0]['message'] ?? 'Unknown error' : 'Token verification failed';

                return [
                    'valid' => false,
                    'status' => 'invalid',
                    'message' => $errorMessage,
                ];
            }

            $result = $data['result'] ?? [];
            $tokenStatus = $result['status'] ?? 'unknown';

            if ($tokenStatus !== 'active') {
                return [
                    'valid' => false,
                    'status' => $tokenStatus,
                    'message' => sprintf('Token status: %s', $tokenStatus),
                ];
            }

            return [
                'valid' => true,
                'status' => 'active',
                'message' => 'Token is valid and active',
                'expires_on' => $result['expires_on'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'status' => 'error',
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Test full connection to Cloudflare (token + zone access)
     *
     * @param Site $site Site configuration
     * @return array{success: bool, token: array, zone: array, responseTimeMs: float}
     */
    public function testConnection(Site $site): array
    {
        $startTime = microtime(true);
        $result = [
            'success' => false,
            'token' => ['valid' => false, 'status' => 'not_tested', 'message' => ''],
            'zone' => ['valid' => false, 'status' => 'not_tested', 'message' => '', 'name' => ''],
            'responseTimeMs' => 0,
        ];

        // Check configuration
        $zoneId = $this->configurationService->getZoneIdForSite($site);
        $apiToken = $this->configurationService->getApiTokenForSite($site);

        if (empty($apiToken)) {
            $result['token'] = [
                'valid' => false,
                'status' => 'not_configured',
                'message' => 'API Token is not configured',
            ];
            $result['responseTimeMs'] = (microtime(true) - $startTime) * 1000;
            return $result;
        }

        if (empty($zoneId)) {
            $result['token'] = [
                'valid' => false,
                'status' => 'skipped',
                'message' => 'Skipped - Zone ID missing',
            ];
            $result['zone'] = [
                'valid' => false,
                'status' => 'not_configured',
                'message' => 'Zone ID is not configured',
                'name' => '',
            ];
            $result['responseTimeMs'] = (microtime(true) - $startTime) * 1000;
            return $result;
        }

        // Test token validity
        $tokenResult = $this->verifyToken($site);
        $result['token'] = $tokenResult;

        if (!$tokenResult['valid']) {
            $result['responseTimeMs'] = (microtime(true) - $startTime) * 1000;
            return $result;
        }

        // Test zone access
        $zoneResult = $this->verifyZoneAccess($zoneId, $apiToken);
        $result['zone'] = $zoneResult;

        $result['success'] = $tokenResult['valid'] && $zoneResult['valid'];
        $result['responseTimeMs'] = (microtime(true) - $startTime) * 1000;

        return $result;
    }

    /**
     * Verify zone access with Cloudflare API
     *
     * @param string $zoneId Zone ID
     * @param string $apiToken API Token
     * @return array{valid: bool, status: string, message: string, name: string}
     */
    private function verifyZoneAccess(string $zoneId, string $apiToken): array
    {
        $url = sprintf(self::API_ZONE_DETAILS, $zoneId);

        $additionalOptions = [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type' => 'application/json',
            ],
        ];

        try {
            $response = $this->requestFactory->request($url, 'GET', $additionalOptions);
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $data = json_decode($responseBody, true);

            if ($data === null) {
                return [
                    'valid' => false,
                    'status' => 'invalid_response',
                    'message' => 'Invalid JSON response from Cloudflare API',
                    'name' => '',
                ];
            }

            if ($statusCode === 404) {
                return [
                    'valid' => false,
                    'status' => 'not_found',
                    'message' => 'Zone not found - check Zone ID',
                    'name' => '',
                ];
            }

            if ($statusCode === 403) {
                return [
                    'valid' => false,
                    'status' => 'forbidden',
                    'message' => 'Access denied - token lacks zone permissions',
                    'name' => '',
                ];
            }

            if ($statusCode !== 200 || !isset($data['success']) || $data['success'] !== true) {
                $errors = $data['errors'] ?? [];
                $errorMessage = !empty($errors) ? $errors[0]['message'] ?? 'Unknown error' : 'Zone verification failed';

                return [
                    'valid' => false,
                    'status' => 'error',
                    'message' => $errorMessage,
                    'name' => '',
                ];
            }

            $zoneData = $data['result'] ?? [];
            $zoneName = $zoneData['name'] ?? 'Unknown';
            $zoneStatus = $zoneData['status'] ?? 'unknown';

            return [
                'valid' => true,
                'status' => $zoneStatus,
                'message' => sprintf('Zone "%s" accessible', $zoneName),
                'name' => $zoneName,
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'status' => 'error',
                'message' => 'Connection failed: ' . $e->getMessage(),
                'name' => '',
            ];
        }
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

            if ($data === null || !isset($data['success']) || $data['success'] !== true) {
                $errors = is_array($data) ? ($data['errors'] ?? []) : [];
                $errorMessage = $data === null
                    ? 'Invalid JSON response from Cloudflare API'
                    : 'API returned success=false: ' . json_encode($errors);
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
