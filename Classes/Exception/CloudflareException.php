<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Exception;

/**
 * Exception for Cloudflare API related errors
 *
 * Stores additional context like HTTP status code and raw API response
 * for detailed error reporting and email notifications.
 */
final class CloudflareException extends \Exception
{
    private ?int $httpStatusCode = null;
    private ?string $cloudflareResponse = null;
    private ?string $zoneId = null;
    private ?string $siteIdentifier = null;
    private array $requestedUrls = [];

    /**
     * Create exception with Cloudflare API context
     */
    public static function fromApiError(
        string $message,
        int $httpStatusCode,
        string $cloudflareResponse,
        ?string $zoneId = null,
        ?string $siteIdentifier = null,
        array $requestedUrls = [],
        ?\Throwable $previous = null
    ): self {
        $exception = new self($message, $httpStatusCode, $previous);
        $exception->httpStatusCode = $httpStatusCode;
        $exception->cloudflareResponse = $cloudflareResponse;
        $exception->zoneId = $zoneId;
        $exception->siteIdentifier = $siteIdentifier;
        $exception->requestedUrls = $requestedUrls;

        return $exception;
    }

    /**
     * Create exception for configuration errors
     */
    public static function fromConfigError(
        string $message,
        ?string $siteIdentifier = null,
        ?\Throwable $previous = null
    ): self {
        $exception = new self($message, 0, $previous);
        $exception->siteIdentifier = $siteIdentifier;

        return $exception;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function getCloudflareResponse(): ?string
    {
        return $this->cloudflareResponse;
    }

    /**
     * Get parsed Cloudflare response as array
     */
    public function getCloudflareResponseArray(): ?array
    {
        if ($this->cloudflareResponse === null) {
            return null;
        }

        $decoded = json_decode($this->cloudflareResponse, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function getZoneId(): ?string
    {
        return $this->zoneId;
    }

    public function getSiteIdentifier(): ?string
    {
        return $this->siteIdentifier;
    }

    /**
     * @return array<string>
     */
    public function getRequestedUrls(): array
    {
        return $this->requestedUrls;
    }

    /**
     * Get detailed error information for logging/email
     */
    public function getDetailedInfo(): array
    {
        return [
            'message' => $this->getMessage(),
            'httpStatusCode' => $this->httpStatusCode,
            'zoneId' => $this->zoneId,
            'siteIdentifier' => $this->siteIdentifier,
            'requestedUrls' => $this->requestedUrls,
            'cloudflareResponse' => $this->cloudflareResponse,
            'cloudflareResponseParsed' => $this->getCloudflareResponseArray(),
        ];
    }
}
