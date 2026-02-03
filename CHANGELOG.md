# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.1] - 2026-01-22

### 🔧 Bug Fix - Cloudflare API Integration Improvements

#### API Token Configuration Validation
- **Issue:** Missing validation for API token configuration with multi-site setups
- **Fix:** Enhanced environment variable lookup order for site-specific tokens
- **Documentation:** Updated configuration guide with multi-domain examples
- **Testing:** All CLI commands tested with multiple sites

## [1.4.0] - 2026-01-10

### ✨ Features - CLI Commands and Scheduler Tasks

#### New Console Commands
- `ignitercf:purge:all` - Purge all configured zones
- `ignitercf:purge:zone` - Purge specific zone by site identifier
- `ignitercf:purge:page` - Purge specific page by UID

#### Scheduler Task Integration
- Three scheduler tasks for automated or scheduled purges
- Support for frequency-based execution (hourly, daily, weekly)
- Task configuration via TYPO3 scheduler module

#### Testing Command
- `ignitercf:test:connection` - Verify Cloudflare token and zone access
- Output shows token validity and zone accessibility
- Helpful for troubleshooting configuration issues

## [1.3.0] - 2026-01-05

### ✨ Features - Backend Module

#### Configuration Module
- **System > IgniterCF > Configuration** module added
- Shows all configured sites and their status
- Test button for each site to verify connectivity
- Display of required environment variables
- Visual indicators for successful/failed configuration

#### Dashboard
- Statistics dashboard for cache purge history
- Last 7 days purge count by zone
- Recent 20 log entries display
- Performance metrics and success rate

## [1.2.0] - 2025-12-15

### ✨ Features - Multi-Site Support Enhancement

#### Multi-Zone Configuration
- Support for multiple Cloudflare zones per TYPO3 installation
- Site-specific API tokens via environment variables
- Lookup order: Site-specific → Global fallback → Config.yaml
- Each site can have independent zone and token configuration

#### Environment Variables
- `IGNITERCF_ZONE_{SITE}` - Site-specific zone ID
- `IGNITERCF_TOKEN_{SITE}` - Site-specific API token
- `IGNITERCF_ZONE_ID` - Global fallback zone ID
- `IGNITERCF_API_TOKEN` - Global fallback API token

## [1.1.0] - 2025-12-01

### ✨ Features - Cache Control Middleware

#### Middleware Implementation
- Prevent Cloudflare from caching backend user pages
- Adds `Cache-Control: no-store` header for backend cookie presence
- Compatible with TYPO3 PSR-15 middleware stack
- Configurable via extension settings

### 🔧 Bug Fix - Context Menu Integration
- Fixed page tree context menu item rendering
- "Clear Cloudflare Cache" now appears reliably on right-click
- Works with all page tree actions

## [1.0.0] - 2025-11-15

### ✨ Initial Release

#### Core Features
- Auto-purge on page save
- Auto-purge on content element changes
- Integration with "Clear All Caches" backend action
- Cache dropdown entry for manual purge
- TYPO3 v12 & v13 support
- Batch purging (max 30 URLs per request)
- Error logging and debug mode

#### Cloudflare Integration
- API v4 communication
- Zone-based configuration
- Token-based authentication
- Multi-domain support ready

#### Configuration
- Extension settings in backend
- Environment variable support
- Site-specific configuration (config.yaml)

---

## Installation & Setup

See [README.md](README.md) for detailed installation and configuration instructions.

## License

GPL-2.0 or later - See LICENSE file

## Contributors

- Patrick Hayder (Author)
