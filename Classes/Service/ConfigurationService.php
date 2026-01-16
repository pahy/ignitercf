<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Service;

use Pahy\Ignitercf\Exception\CloudflareException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Service to retrieve extension configuration with environment variable fallback
 *
 * Architecture (Hybrid for Multi-Domain):
 * - Zone ID: Site config.yaml (different per domain)
 * - API Token: Environment variable based on Site Identifier (secure)
 * - Global settings: Extension Configuration
 *
 * Environment Variables for API Token (Fallback chain):
 * 1. IGNITERCF_TOKEN_{SITE_IDENTIFIER} (e.g. IGNITERCF_TOKEN_MAIN)
 * 2. IGNITERCF_API_TOKEN (global fallback for single-domain)
 * 3. Site config.yaml cloudflare.apiToken (legacy support)
 *
 * Global Environment Variables:
 * - IGNITERCF_ENABLED
 * - IGNITERCF_PURGE_ON_CLEAR_ALL
 * - IGNITERCF_AUTO_PURGE_ON_SAVE
 * - IGNITERCF_ENABLE_MIDDLEWARE
 * - IGNITERCF_DEBUG
 */
final class ConfigurationService
{
    private const EXTENSION_KEY = 'ignitercf';

    /**
     * Mapping of global config keys to environment variable names
     */
    private const ENV_MAPPING = [
        'enabled' => 'IGNITERCF_ENABLED',
        'purgeOnClearAll' => 'IGNITERCF_PURGE_ON_CLEAR_ALL',
        'autoPurgeOnSave' => 'IGNITERCF_AUTO_PURGE_ON_SAVE',
        'enableMiddleware' => 'IGNITERCF_ENABLE_MIDDLEWARE',
        'debug' => 'IGNITERCF_DEBUG',
    ];

    /**
     * Default values for global settings
     */
    private const DEFAULTS = [
        'enabled' => true,
        'purgeOnClearAll' => true,
        'autoPurgeOnSave' => true,
        'enableMiddleware' => true,
        'debug' => false,
    ];

    private ?array $configuration = null;

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {}

    // =========================================================================
    // Global Settings (Extension Configuration)
    // =========================================================================

    /**
     * Check if Cloudflare integration is globally enabled
     */
    public function isEnabled(): bool
    {
        return (bool)$this->get('enabled');
    }

    /**
     * Check if purge on "Clear all caches" is enabled
     */
    public function isPurgeOnClearAllEnabled(): bool
    {
        return (bool)$this->get('purgeOnClearAll');
    }

    /**
     * Check if auto-purge on content save is enabled
     */
    public function isAutoPurgeOnSaveEnabled(): bool
    {
        return (bool)$this->get('autoPurgeOnSave');
    }

    /**
     * Check if middleware is enabled
     */
    public function isMiddlewareEnabled(): bool
    {
        return (bool)$this->get('enableMiddleware');
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugEnabled(): bool
    {
        return (bool)$this->get('debug');
    }

    // =========================================================================
    // Site-specific Settings (Site config.yaml + Environment Variables)
    // =========================================================================

    /**
     * Get Cloudflare Zone ID for a site
     *
     * @param Site $site The site
     * @return string Zone ID or empty string if not configured
     */
    public function getZoneIdForSite(Site $site): string
    {
        $cloudflareConfig = $site->getConfiguration()['cloudflare'] ?? [];
        return (string)($cloudflareConfig['zoneId'] ?? '');
    }

    /**
     * Get Cloudflare API Token for a site
     *
     * Fallback chain:
     * 1. IGNITERCF_TOKEN_{SITE_IDENTIFIER} (e.g. IGNITERCF_TOKEN_MAIN)
     * 2. IGNITERCF_API_TOKEN (global fallback)
     * 3. Site config.yaml cloudflare.apiToken (legacy, supports %env()%)
     *
     * @param Site $site The site
     * @return string API Token or empty string if not configured
     */
    public function getApiTokenForSite(Site $site): string
    {
        $siteIdentifier = $site->getIdentifier();

        // 1. Try site-specific env var: IGNITERCF_TOKEN_{SITE_IDENTIFIER}
        $siteEnvVar = 'IGNITERCF_TOKEN_' . $this->sanitizeForEnvVar($siteIdentifier);
        $token = getenv($siteEnvVar);
        if ($token !== false && $token !== '') {
            return $token;
        }

        // 2. Try global fallback env var: IGNITERCF_API_TOKEN
        $token = getenv('IGNITERCF_API_TOKEN');
        if ($token !== false && $token !== '') {
            return $token;
        }

        // 3. Legacy: Site config.yaml with optional %env()% syntax
        $cloudflareConfig = $site->getConfiguration()['cloudflare'] ?? [];
        $configToken = (string)($cloudflareConfig['apiToken'] ?? '');

        if (!empty($configToken)) {
            return $this->resolveEnvPlaceholder($configToken);
        }

        return '';
    }

    /**
     * Check if a site is enabled for Cloudflare (site-level check)
     *
     * @param Site $site The site
     * @return bool True if enabled at site level (default: true)
     */
    public function isSiteEnabled(Site $site): bool
    {
        $cloudflareConfig = $site->getConfiguration()['cloudflare'] ?? [];
        return (bool)($cloudflareConfig['enabled'] ?? true);
    }

    /**
     * Check if a site has valid Cloudflare configuration
     *
     * @param Site $site The site
     * @return bool True if Zone ID and API Token are configured
     */
    public function isSiteConfigured(Site $site): bool
    {
        return $this->isEnabled()
            && $this->isSiteEnabled($site)
            && !empty($this->getZoneIdForSite($site))
            && !empty($this->getApiTokenForSite($site));
    }

    /**
     * Validate site configuration and throw exception if invalid
     *
     * @param Site $site The site
     * @throws CloudflareException if configuration is invalid
     */
    public function validateSiteConfiguration(Site $site): void
    {
        if (!$this->isEnabled()) {
            throw new CloudflareException('Cloudflare integration is globally disabled');
        }

        if (!$this->isSiteEnabled($site)) {
            throw new CloudflareException(
                sprintf('Cloudflare is disabled for site "%s"', $site->getIdentifier())
            );
        }

        if (empty($this->getZoneIdForSite($site))) {
            throw new CloudflareException(
                sprintf(
                    'Cloudflare Zone ID is not configured for site "%s". Add cloudflare.zoneId to config/sites/%s/config.yaml',
                    $site->getIdentifier(),
                    $site->getIdentifier()
                )
            );
        }

        if (empty($this->getApiTokenForSite($site))) {
            $siteEnvVar = 'IGNITERCF_TOKEN_' . $this->sanitizeForEnvVar($site->getIdentifier());
            throw new CloudflareException(
                sprintf(
                    'Cloudflare API Token is not configured for site "%s". Set environment variable %s or IGNITERCF_API_TOKEN',
                    $site->getIdentifier(),
                    $siteEnvVar
                )
            );
        }
    }

    // =========================================================================
    // Internal Methods
    // =========================================================================

    /**
     * Get a global configuration value with environment variable fallback
     *
     * @param string $key Configuration key
     * @return mixed Configuration value
     */
    public function get(string $key): mixed
    {
        // 1. Check environment variable first
        $envKey = self::ENV_MAPPING[$key] ?? null;
        if ($envKey !== null) {
            $envValue = getenv($envKey);
            if ($envValue !== false && $envValue !== '') {
                return $this->castValue($key, $envValue);
            }
        }

        // 2. Load extension configuration
        $this->loadConfiguration();

        // 3. Return extension config or default
        if (isset($this->configuration[$key])) {
            return $this->configuration[$key];
        }

        return self::DEFAULTS[$key] ?? null;
    }

    /**
     * Get all global configuration values (for debugging)
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $result = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    /**
     * Get all configuration values for a site (for debugging)
     *
     * @param Site $site The site
     * @return array<string, mixed>
     */
    public function getAllForSite(Site $site): array
    {
        $result = $this->getAll();
        $result['siteIdentifier'] = $site->getIdentifier();
        $result['zoneId'] = $this->getZoneIdForSite($site);

        // Mask API token for security
        $token = $this->getApiTokenForSite($site);
        $result['apiToken'] = !empty($token) ? substr($token, 0, 8) . '***' : '';
        $result['siteEnabled'] = $this->isSiteEnabled($site);
        $result['siteConfigured'] = $this->isSiteConfigured($site);

        return $result;
    }

    /**
     * Load extension configuration from TYPO3
     */
    private function loadConfiguration(): void
    {
        if ($this->configuration !== null) {
            return;
        }

        try {
            $this->configuration = $this->extensionConfiguration->get(self::EXTENSION_KEY);
        } catch (\Exception) {
            $this->configuration = [];
        }

        if (!is_array($this->configuration)) {
            $this->configuration = [];
        }
    }

    /**
     * Cast environment variable value to correct type
     */
    private function castValue(string $key, string $value): mixed
    {
        // Boolean values
        if (in_array($key, ['enabled', 'purgeOnClearAll', 'autoPurgeOnSave', 'enableMiddleware', 'debug'], true)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $value;
    }

    /**
     * Sanitize site identifier for use in environment variable name
     *
     * Converts to uppercase and replaces non-alphanumeric chars with underscore
     * Example: "my-site" -> "MY_SITE"
     *
     * @param string $identifier Site identifier
     * @return string Sanitized string for env var
     */
    private function sanitizeForEnvVar(string $identifier): string
    {
        $sanitized = strtoupper($identifier);
        $sanitized = preg_replace('/[^A-Z0-9]/', '_', $sanitized);
        return $sanitized ?? strtoupper($identifier);
    }

    /**
     * Resolve %env(VAR)% placeholder in string
     *
     * @param string $value Value that may contain %env(VAR)% placeholder
     * @return string Resolved value
     */
    private function resolveEnvPlaceholder(string $value): string
    {
        if (preg_match('/%env\(([^)]+)\)%/', $value, $matches)) {
            $envVar = $matches[1];
            $resolved = getenv($envVar);
            if ($resolved !== false) {
                return $resolved;
            }
        }

        return $value;
    }
}
