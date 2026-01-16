/**
 * Context menu actions for ignitercf extension
 * Module: @pahy/ignitercf/context-menu-actions
 */
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';

class ContextMenuActions {
    /**
     * Clear Cloudflare cache for a single page
     *
     * @param {string} table The table name
     * @param {string|number} uid The record UID
     */
    clearCloudflareCache(table, uid) {
        const pageId = parseInt(uid, 10);

        Modal.confirm(
            'Clear Cloudflare Cache',
            'Do you want to clear the Cloudflare cache for this page and all its language versions?',
            top.TYPO3.Severity.warning
        ).on('confirm.button.ok', () => {
            Modal.dismiss();

            fetch(TYPO3.settings.ajaxUrls['ignitercf_clear_page'] + '&pageId=' + pageId, {
                method: 'POST',
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        Notification.success('Success', data.message);
                    } else {
                        Notification.error('Error', data.message);
                    }
                })
                .catch((error) => {
                    Notification.error('Error', 'Request failed: ' + error.message);
                });
        }).on('confirm.button.cancel', () => {
            Modal.dismiss();
        });
    }
}

export default new ContextMenuActions();
