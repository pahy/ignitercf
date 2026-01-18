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
        const originalHtml = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Testing...';

        if (resultContainer) {
            resultContainer.innerHTML = '<span class="text-muted">Testing connection...</span>';
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
                const message = `Token: ${data.token.message}, Zone: ${data.zone.message} (${data.responseTimeMs} ms)`;
                Notification.success('Connection successful', message);
                if (resultContainer) {
                    this.showDetailedResult(resultContainer, data);
                }
            } else {
                const errorMsg = data.token?.message || data.zone?.message || 'Unknown error';
                Notification.error('Connection failed', errorMsg);
                if (resultContainer) {
                    this.showDetailedResult(resultContainer, data);
                }
            }
        } catch (error) {
            Notification.error('Connection error', 'Failed to test connection: ' + error.message);
            if (resultContainer) {
                resultContainer.innerHTML = '<span class="text-danger">✗ Error: ' + error.message + '</span>';
            }
        } finally {
            // Re-enable button
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }

    /**
     * Show detailed result with token and zone status
     */
    showDetailedResult(container, data) {
        container.innerHTML = '';

        // Token status
        const tokenDiv = document.createElement('div');
        const tokenIcon = data.token.valid ? '✓' : '✗';
        const tokenClass = data.token.valid ? 'text-success' : 'text-danger';
        tokenDiv.innerHTML = `<span class="${tokenClass}">${tokenIcon}</span> Token: ${this.escapeHtml(data.token.message)}`;
        container.appendChild(tokenDiv);

        // Zone status
        const zoneDiv = document.createElement('div');
        const zoneIcon = data.zone.valid ? '✓' : '✗';
        const zoneClass = data.zone.valid ? 'text-success' : 'text-danger';
        zoneDiv.innerHTML = `<span class="${zoneClass}">${zoneIcon}</span> Zone: ${this.escapeHtml(data.zone.message)}`;
        container.appendChild(zoneDiv);

        // Response time
        const timeDiv = document.createElement('div');
        timeDiv.className = 'text-muted';
        timeDiv.innerHTML = `<small>Response: ${data.responseTimeMs} ms</small>`;
        container.appendChild(timeDiv);
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new BackendModule());
} else {
    new BackendModule();
}

export default BackendModule;
