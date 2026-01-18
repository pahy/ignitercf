<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\EventListener;

use TYPO3\CMS\Backend\Backend\Event\ModifyClearCacheActionsEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Event listener to add Cloudflare actions to cache dropdown
 *
 * Registered via:
 * - PHP Attribute #[AsEventListener] for TYPO3 v13+
 * - Services.yaml for TYPO3 v12 (ignored in v13 due to duplicate identifier)
 */
#[AsEventListener(identifier: 'ignitercf/modify-clear-cache-actions')]
final class ModifyClearCacheActionsEventListener
{
    public function __construct(
        private readonly UriBuilder $uriBuilder
    ) {}

    public function __invoke(ModifyClearCacheActionsEvent $event): void
    {
        // Action: Purge all Cloudflare zones
        $event->addCacheAction([
            'id' => 'ignitercf_clear_all',
            'title' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:cache.clearAll',
            'description' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:cache.clearAll.description',
            'href' => (string)$this->uriBuilder->buildUriFromRoute('ajax_ignitercf_clear_all'),
            'severity' => 'warning',
            'iconIdentifier' => 'actions-system-cache-clear-impact-medium',
        ]);

        $event->addCacheActionIdentifier('ignitercf_clear_all');
    }
}
