/**
 * Backend module JavaScript for IgniterCF
 * Handles test connection functionality
 */
import Notification from '@typo3/backend/notification.js';

class BackendModule {
    constructor() {
        this.initTestConnectionButtons();
    }

    initTestConnectionButtons() {
        document.querySelectorAll('.ignitercf-test-connection').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const siteIdentifier = button.dataset.site;
                this.testConnection(siteIdentifier, button);
            });
        });
    }

    async testConnection(siteIdentifier, button) {
        const resultContainer = document.querySelector(
            `.ignitercf-connection-result[data-site="${siteIdentifier}"]`
        );

        // Disable button and show loading
        button.disabled = true;
        const originalText = button.textContent;
        button.textContent = 'Testing...';

        if (resultContainer) {
            resultContainer.textContent = 'Testing connection...';
            resultContainer.className = 'ignitercf-connection-result text-muted';
        }

        try {
            const url = TYPO3.settings.ajaxUrls['ignitercf_test_connection'] + '&site=' + encodeURIComponent(siteIdentifier);
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            const data = await response.json();

            if (data.success) {
                Notification.success('Connection successful', data.message);
                if (resultContainer) {
                    this.showResult(resultContainer, true, data.message, data.expires_on);
                }
            } else {
                Notification.error('Connection failed', data.message);
                if (resultContainer) {
                    this.showResult(resultContainer, false, data.message);
                }
            }
        } catch (error) {
            Notification.error('Connection error', 'Failed to test connection: ' + error.message);
            if (resultContainer) {
                this.showResult(resultContainer, false, 'Connection error: ' + error.message);
            }
        } finally {
            // Re-enable button
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    /**
     * Show result in container using safe DOM methods
     */
    showResult(container, success, message, expiresOn = null) {
        // Clear container
        container.textContent = '';
        container.className = 'ignitercf-connection-result';

        // Create wrapper span
        const wrapper = document.createElement('span');
        wrapper.className = success ? 'text-success' : 'text-danger';

        // Create icon
        const icon = document.createElement('span');
        icon.textContent = success ? '✓ ' : '✗ ';
        wrapper.appendChild(icon);

        // Add message
        const messageText = document.createTextNode(message);
        wrapper.appendChild(messageText);

        container.appendChild(wrapper);

        // Add expiry info if available
        if (success && expiresOn) {
            const expiryInfo = document.createElement('small');
            expiryInfo.className = 'text-muted d-block';
            expiryInfo.textContent = 'Token expires: ' + expiresOn;
            container.appendChild(expiryInfo);
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new BackendModule());
} else {
    new BackendModule();
}

export default BackendModule;
