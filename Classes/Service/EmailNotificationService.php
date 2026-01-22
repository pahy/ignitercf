<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Service;

use Pahy\Ignitercf\Exception\CloudflareException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service for sending email notifications on scheduler task errors
 */
final class EmailNotificationService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ConfigurationService $configurationService
    ) {}

    /**
     * Send error notification email for a failed scheduler task
     *
     * @param string $taskName Name of the failed task
     * @param \Throwable $exception The exception that occurred
     * @param string $siteIdentifier Site identifier (if applicable)
     * @param array $additionalContext Additional context information
     */
    public function notifyTaskError(
        string $taskName,
        \Throwable $exception,
        string $siteIdentifier = '',
        array $additionalContext = []
    ): void {
        if (!$this->configurationService->isErrorNotificationEnabled()) {
            return;
        }

        $recipients = $this->configurationService->getErrorNotificationEmails();

        if (empty($recipients)) {
            return;
        }

        try {
            $mail = $this->createMailMessage();
            $mail->subject($this->buildSubject($taskName, $siteIdentifier));

            // Set recipients
            foreach ($recipients as $email) {
                $mail->addTo($email);
            }

            // Set sender
            $mail->from(new Address(
                $this->configurationService->getErrorNotificationSenderEmail(),
                $this->configurationService->getErrorNotificationSenderName()
            ));

            // Build email content
            $htmlContent = $this->buildHtmlContent($taskName, $exception, $siteIdentifier, $additionalContext);
            $textContent = $this->buildTextContent($taskName, $exception, $siteIdentifier, $additionalContext);

            $mail->html($htmlContent);
            $mail->text($textContent);

            $mail->send();

            $this->logger?->info('IgniterCF: Error notification email sent', [
                'taskName' => $taskName,
                'recipients' => $recipients,
                'siteIdentifier' => $siteIdentifier,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('IgniterCF: Failed to send error notification email', [
                'taskName' => $taskName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a new mail message instance
     */
    private function createMailMessage(): MailMessage
    {
        return GeneralUtility::makeInstance(MailMessage::class);
    }

    /**
     * Build email subject
     */
    private function buildSubject(string $taskName, string $siteIdentifier): string
    {
        $subject = sprintf('[IgniterCF] Scheduler Task Error: %s', $taskName);

        if (!empty($siteIdentifier)) {
            $subject .= sprintf(' (%s)', $siteIdentifier);
        }

        return $subject;
    }

    /**
     * Build HTML email content
     */
    private function buildHtmlContent(
        string $taskName,
        \Throwable $exception,
        string $siteIdentifier,
        array $additionalContext
    ): string {
        $timestamp = date('Y-m-d H:i:s T');
        $errorMessage = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');

        // Extract Cloudflare-specific information
        $cloudflareInfo = $this->extractCloudflareInfo($exception);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { background: #dc3545; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 8px 8px; }
        .section { margin-bottom: 20px; }
        .section-title { font-weight: bold; color: #495057; margin-bottom: 8px; border-bottom: 2px solid #dee2e6; padding-bottom: 4px; }
        .error-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin: 10px 0; }
        .response-box { background: #e9ecef; border: 1px solid #ced4da; border-radius: 4px; padding: 15px; margin: 10px 0; font-family: monospace; font-size: 13px; white-space: pre-wrap; word-break: break-all; max-height: 400px; overflow-y: auto; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 8px; border-bottom: 1px solid #dee2e6; }
        .info-table td:first-child { font-weight: bold; width: 180px; color: #495057; }
        .url-list { margin: 0; padding-left: 20px; }
        .url-list li { margin: 4px 0; font-family: monospace; font-size: 13px; }
        .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d; }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚠️ IgniterCF Scheduler Task Error</h1>
    </div>
    <div class="content">
        <div class="section">
            <div class="section-title">Error Details</div>
            <table class="info-table">
                <tr><td>Task Name:</td><td>{$taskName}</td></tr>
                <tr><td>Timestamp:</td><td>{$timestamp}</td></tr>
HTML;

        if (!empty($siteIdentifier)) {
            $html .= "<tr><td>Site:</td><td>{$siteIdentifier}</td></tr>";
        }

        if ($cloudflareInfo['zoneId']) {
            $html .= "<tr><td>Zone ID:</td><td>{$cloudflareInfo['zoneId']}</td></tr>";
        }

        if ($cloudflareInfo['httpStatusCode']) {
            $html .= "<tr><td>HTTP Status:</td><td>{$cloudflareInfo['httpStatusCode']}</td></tr>";
        }

        $html .= <<<HTML
            </table>
        </div>

        <div class="section">
            <div class="section-title">Error Message</div>
            <div class="error-box">{$errorMessage}</div>
        </div>
HTML;

        // Requested URLs (if available)
        if (!empty($cloudflareInfo['requestedUrls'])) {
            $html .= '<div class="section"><div class="section-title">Requested URLs</div><ul class="url-list">';
            foreach ($cloudflareInfo['requestedUrls'] as $url) {
                $urlEscaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                $html .= "<li>{$urlEscaped}</li>";
            }
            $html .= '</ul></div>';
        }

        // Cloudflare Response (if available)
        if ($cloudflareInfo['response']) {
            $responseFormatted = $this->formatJsonResponse($cloudflareInfo['response']);
            $html .= <<<HTML
        <div class="section">
            <div class="section-title">Cloudflare API Response</div>
            <div class="response-box">{$responseFormatted}</div>
        </div>
HTML;
        }

        // Additional Context
        if (!empty($additionalContext)) {
            $html .= '<div class="section"><div class="section-title">Additional Context</div><table class="info-table">';
            foreach ($additionalContext as $key => $value) {
                $keyEscaped = htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8');
                $valueEscaped = htmlspecialchars($this->formatValue($value), ENT_QUOTES, 'UTF-8');
                $html .= "<tr><td>{$keyEscaped}:</td><td>{$valueEscaped}</td></tr>";
            }
            $html .= '</table></div>';
        }

        // Stack Trace
        $stackTrace = htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        $serverName = $_SERVER['SERVER_NAME'] ?? 'unknown';
        $html .= <<<HTML
        <div class="section">
            <div class="section-title">Stack Trace</div>
            <div class="response-box">{$stackTrace}</div>
        </div>

        <div class="footer">
            This email was sent by IgniterCF - Cloudflare Cache Purge Extension for TYPO3.<br>
            Server: {$serverName}
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Build plain text email content
     */
    private function buildTextContent(
        string $taskName,
        \Throwable $exception,
        string $siteIdentifier,
        array $additionalContext
    ): string {
        $timestamp = date('Y-m-d H:i:s T');
        $cloudflareInfo = $this->extractCloudflareInfo($exception);

        $text = "IgniterCF Scheduler Task Error\n";
        $text .= str_repeat('=', 50) . "\n\n";

        $text .= "ERROR DETAILS\n";
        $text .= str_repeat('-', 30) . "\n";
        $text .= "Task Name: {$taskName}\n";
        $text .= "Timestamp: {$timestamp}\n";

        if (!empty($siteIdentifier)) {
            $text .= "Site: {$siteIdentifier}\n";
        }

        if ($cloudflareInfo['zoneId']) {
            $text .= "Zone ID: {$cloudflareInfo['zoneId']}\n";
        }

        if ($cloudflareInfo['httpStatusCode']) {
            $text .= "HTTP Status: {$cloudflareInfo['httpStatusCode']}\n";
        }

        $text .= "\nERROR MESSAGE\n";
        $text .= str_repeat('-', 30) . "\n";
        $text .= $exception->getMessage() . "\n";

        if (!empty($cloudflareInfo['requestedUrls'])) {
            $text .= "\nREQUESTED URLS\n";
            $text .= str_repeat('-', 30) . "\n";
            foreach ($cloudflareInfo['requestedUrls'] as $url) {
                $text .= "- {$url}\n";
            }
        }

        if ($cloudflareInfo['response']) {
            $text .= "\nCLOUDFLARE API RESPONSE\n";
            $text .= str_repeat('-', 30) . "\n";
            $text .= $this->formatJsonResponse($cloudflareInfo['response']) . "\n";
        }

        if (!empty($additionalContext)) {
            $text .= "\nADDITIONAL CONTEXT\n";
            $text .= str_repeat('-', 30) . "\n";
            foreach ($additionalContext as $key => $value) {
                $text .= "{$key}: " . $this->formatValue($value) . "\n";
            }
        }

        $text .= "\nSTACK TRACE\n";
        $text .= str_repeat('-', 30) . "\n";
        $text .= $exception->getTraceAsString() . "\n";

        $text .= "\n" . str_repeat('=', 50) . "\n";
        $text .= "This email was sent by IgniterCF - Cloudflare Cache Purge Extension for TYPO3.\n";
        $text .= "Server: " . ($_SERVER['SERVER_NAME'] ?? 'unknown') . "\n";

        return $text;
    }

    /**
     * Extract Cloudflare-specific information from exception
     */
    private function extractCloudflareInfo(\Throwable $exception): array
    {
        $info = [
            'httpStatusCode' => null,
            'response' => null,
            'zoneId' => null,
            'requestedUrls' => [],
        ];

        if ($exception instanceof CloudflareException) {
            $info['httpStatusCode'] = $exception->getHttpStatusCode();
            $info['response'] = $exception->getCloudflareResponse();
            $info['zoneId'] = $exception->getZoneId();
            $info['requestedUrls'] = $exception->getRequestedUrls();
        }

        return $info;
    }

    /**
     * Format JSON response for display
     */
    private function formatJsonResponse(string $response): string
    {
        $decoded = json_decode($response, true);

        if ($decoded !== null) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $response;
        }

        return $response;
    }

    /**
     * Format a value for display
     */
    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string)$value;
    }
}
