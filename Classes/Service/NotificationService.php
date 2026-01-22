<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\Service;

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Service for displaying backend notifications (toast messages)
 */
final class NotificationService
{
    public function __construct(
        private readonly FlashMessageService $flashMessageService,
        private readonly ConfigurationService $configurationService
    ) {}

    /**
     * Show success notification for cache purge
     *
     * @param int $urlCount Number of URLs purged
     * @param string $siteIdentifier Site identifier
     */
    public function notifyPurgeSuccess(int $urlCount, string $siteIdentifier = ''): void
    {
        if (!$this->shouldShowNotifications()) {
            return;
        }

        $title = 'Cloudflare Cache';
        $message = $urlCount === 1
            ? 'Cache für 1 URL erfolgreich gelöscht'
            : sprintf('Cache für %d URLs erfolgreich gelöscht', $urlCount);

        if ($siteIdentifier !== '') {
            $message .= sprintf(' (%s)', $siteIdentifier);
        }

        $this->addNotification($title, $message, ContextualFeedbackSeverity::OK);
    }

    /**
     * Show error notification for cache purge failure
     *
     * @param string $errorMessage Error message
     * @param string $siteIdentifier Site identifier
     */
    public function notifyPurgeError(string $errorMessage, string $siteIdentifier = ''): void
    {
        if (!$this->shouldShowNotifications()) {
            return;
        }

        $title = 'Cloudflare Cache Fehler';
        $message = 'Cache-Löschung fehlgeschlagen';

        if ($siteIdentifier !== '') {
            $message .= sprintf(' (%s)', $siteIdentifier);
        }

        $message .= ': ' . $this->truncateMessage($errorMessage, 100);

        $this->addNotification($title, $message, ContextualFeedbackSeverity::ERROR);
    }

    /**
     * Show info notification
     *
     * @param string $title Notification title
     * @param string $message Notification message
     */
    public function notifyInfo(string $title, string $message): void
    {
        if (!$this->shouldShowNotifications()) {
            return;
        }

        $this->addNotification($title, $message, ContextualFeedbackSeverity::INFO);
    }

    /**
     * Show warning notification
     *
     * @param string $title Notification title
     * @param string $message Notification message
     */
    public function notifyWarning(string $title, string $message): void
    {
        if (!$this->shouldShowNotifications()) {
            return;
        }

        $this->addNotification($title, $message, ContextualFeedbackSeverity::WARNING);
    }

    /**
     * Add notification to the queue
     */
    private function addNotification(
        string $title,
        string $message,
        ContextualFeedbackSeverity $severity
    ): void {
        try {
            $flashMessage = new FlashMessage(
                $message,
                $title,
                $severity,
                true // Store in session
            );

            $queue = $this->flashMessageService->getMessageQueueByIdentifier(
                FlashMessageQueue::NOTIFICATION_QUEUE
            );
            $queue->enqueue($flashMessage);
        } catch (\Exception) {
            // Silently ignore notification errors
        }
    }

    /**
     * Check if notifications should be shown
     */
    private function shouldShowNotifications(): bool
    {
        // Always show notifications in backend context
        // Could be extended with a configuration option
        return defined('TYPO3_MODE') && TYPO3_MODE === 'BE'
            || defined('TYPO3') && ($GLOBALS['TYPO3_REQUEST'] ?? null)?->getAttribute('applicationType') === 2;
    }

    /**
     * Truncate message to max length
     */
    private function truncateMessage(string $message, int $maxLength): string
    {
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, $maxLength - 3) . '...';
    }
}
