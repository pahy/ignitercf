<?php

declare(strict_types=1);

namespace Pahy\Ignitercf\ContextMenu;

use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;

/**
 * Context menu item provider for page tree
 */
final class ItemProvider extends AbstractProvider
{
    /**
     * @var array<string, array<string, string>>
     */
    protected $itemsConfiguration = [
        'clearCloudflareCache' => [
            'type' => 'item',
            'label' => 'LLL:EXT:ignitercf/Resources/Private/Language/locallang.xlf:contextMenu.clearCloudflareCache',
            'iconIdentifier' => 'actions-system-cache-clear',
            'callbackAction' => 'clearCloudflareCache',
        ],
    ];

    /**
     * Check if this provider can handle the current context
     */
    public function canHandle(): bool
    {
        return $this->table === 'pages';
    }

    /**
     * Priority for ordering providers
     */
    public function getPriority(): int
    {
        return 45; // After standard items
    }

    /**
     * Get additional attributes for menu items
     *
     * @param string $itemName Item identifier
     * @return array<string, string>
     */
    protected function getAdditionalAttributes(string $itemName): array
    {
        return [
            'data-callback-module' => '@pahy/ignitercf/context-menu-actions',
        ];
    }

    /**
     * Add items to the context menu
     *
     * @param array<string, mixed> $items Existing items
     * @return array<string, mixed> Modified items
     */
    public function addItems(array $items): array
    {
        $this->initDisabledItems();

        if (!$this->canHandle()) {
            return $items;
        }

        // Insert item after "Clear cache" if it exists
        $position = array_search('clearCache', array_keys($items), true);

        if ($position !== false) {
            $items = array_slice($items, 0, $position + 1, true)
                + $this->prepareItems($this->itemsConfiguration)
                + array_slice($items, $position + 1, null, true);
        } else {
            $items += $this->prepareItems($this->itemsConfiguration);
        }

        return $items;
    }
}
