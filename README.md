# IgniterCF - Cloudflare Cache Purge for TYPO3

> [!WARNING]
> This extension is work in progress and not yet production-ready. Use at your own risk.

Automatically purge Cloudflare cache when content changes in TYPO3 v12/v13.

> **[Deutsche Version / German Version](README.de.md)**

## Getting Started

### 1. Create Cloudflare API Token

1. Open the [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Go to **Profile** (top right) > **API Tokens**
3. Click **Create Token**
4. Select **Custom Token** > **Get started**
5. Configure:
   - **Token name:** `TYPO3 IgniterCF`
   - **Permissions:**
     - Zone > Cache Purge > Purge
   - **Zone Resources:**
     - Include > Specific zone > Select your zone(s)
6. Click **Continue to summary** > **Create Token**
7. Copy the token (displayed only once)

### 2. Get Zone ID

1. In Cloudflare Dashboard, select your domain
2. On the Overview page, find **Zone ID** on the right side
3. Copy this ID

### 3. Set Environment Variable

In `.env` (TYPO3 root):

```env
# For site with identifier "main":
IGNITERCF_TOKEN_MAIN=your-cloudflare-api-token

# For multi-domain (additional sites):
IGNITERCF_TOKEN_SHOP=token-for-shop-zone
IGNITERCF_TOKEN_BLOG=token-for-blog-zone

# OR: Global fallback (if all sites use the same zone):
IGNITERCF_API_TOKEN=your-global-token
```

**Naming convention:** Site identifier becomes uppercase, hyphens become underscores:
- `main` > `IGNITERCF_TOKEN_MAIN`
- `my-shop` > `IGNITERCF_TOKEN_MY_SHOP`

### 4. Configure Site

Edit `config/sites/{site-identifier}/config.yaml`:

```yaml
# Add at the end of the file:
cloudflare:
  zoneId: 'your-zone-id-here'
  enabled: true
```

**Multi-domain example:**

```yaml
# config/sites/main/config.yaml
cloudflare:
  zoneId: 'abc123def456'
  enabled: true

# config/sites/shop/config.yaml  
cloudflare:
  zoneId: 'xyz789ghi012'  # Different zone!
  enabled: true
```

### 5. Extension Configuration (optional)

In TYPO3 Backend: **Settings > Extension Configuration > ignitercf**

| Setting | Default | Description |
|---------|---------|-------------|
| Enable Cloudflare Integration | Yes | Global kill switch |
| Purge on Clear All Caches | Yes | Also purge CF on "Clear all caches" |
| Auto-Purge on Content Change | Yes | Automatically purge on content changes |
| Enable Cache-Control Middleware | Yes | Prevent CF caching for BE users |
| Debug Mode | No | Verbose logging |

### 6. Clear Cache

```bash
vendor/bin/typo3 cache:flush

# Or with DDEV:
ddev typo3 cache:flush
```

### 7. Test

#### Test 1: Auto-Purge on Content Change
1. Edit a content element in the backend
2. Save
3. Check log: `var/log/typo3_*.log`
   ```bash
   grep -i cloudflare var/log/typo3_*.log | tail -5
   ```
4. Expected output: `Cloudflare cache purged`

#### Test 2: Middleware (Cache-Control Header)
```bash
# Without BE cookie (should NOT have no-store header):
curl -I https://your-domain.com/

# With BE cookie (should have no-store header):
curl -I -H "Cookie: be_typo_user=test" https://your-domain.com/
```

#### Test 3: Cache Dropdown
1. In backend, click the cache icon (top right)
2. "Clear Cloudflare Cache (All Zones)" should appear
3. Click it

#### Test 4: Context Menu
1. Right-click on a page in the page tree
2. Select "Clear Cloudflare Cache"
3. Confirm

---

## Troubleshooting

### Problem: "Cloudflare API Token is not configured"

**Solution:** Check environment variable:
```bash
# In DDEV:
ddev exec printenv | grep IGNITERCF

# Local:
printenv | grep IGNITERCF
```

Variable must match site identifier (uppercase, hyphens become underscores).

### Problem: Purge not working

**Check 1:** Is API token valid?
```bash
curl -X GET "https://api.cloudflare.com/client/v4/user/tokens/verify" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Check 2:** Is Zone ID correct?
- Cloudflare Dashboard > Domain > Overview > Zone ID

**Check 3:** Check log
```bash
grep -i "cloudflare\|ignitercf" var/log/typo3_*.log
```

### Problem: Backend preview is cached

**Solution 1:** Is middleware active?
- Extension Configuration > "Enable Cache-Control Middleware" enabled

**Solution 2:** Set up Cloudflare Cache Rule:
1. Cloudflare Dashboard > Caching > Cache Rules
2. New Rule: `Cookie contains "be_typo_user"` > Cache: Bypass

### Enable Debug Mode

```php
// config/system/additional.php
$GLOBALS['TYPO3_CONF_VARS']['LOG']['Pahy']['Ignitercf']['writerConfiguration'] = [
    \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
        \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
            'logFileInfix' => 'ignitercf'
        ],
    ],
];
```

Log: `var/log/typo3_ignitercf_*.log`

---

## Configuration Overview

| What | Where | Example |
|------|-------|---------|
| Zone ID | Site config.yaml | `cloudflare.zoneId: 'abc123'` |
| API Token | Environment Variable | `IGNITERCF_TOKEN_MAIN=...` |
| Global Settings | Extension Configuration | Backend > Settings |
| Enable/disable site | Site config.yaml | `cloudflare.enabled: false` |

---

## Features

- Auto-purge on content changes (pages, tt_content)
- Multi-site / multi-zone support
- "Clear all caches" integration
- Cache dropdown entry
- Context menu in page tree
- CLI commands for automated purges
- Scheduler tasks for scheduled purges
- Middleware prevents CF caching for BE users
- Batch purge (max 30 URLs per request)
- TYPO3 v12 + v13 compatible

---

## CLI Commands

IgniterCF provides console commands for cache purging via command line.

### Purge All Zones

```bash
vendor/bin/typo3 ignitercf:purge:all

# With DDEV:
ddev typo3 ignitercf:purge:all
```

### Purge Specific Zone

```bash
vendor/bin/typo3 ignitercf:purge:zone --site=main

# With DDEV:
ddev typo3 ignitercf:purge:zone --site=my-shop
```

### Purge Specific Page

```bash
# All languages:
vendor/bin/typo3 ignitercf:purge:page --page=123

# Specific language:
vendor/bin/typo3 ignitercf:purge:page --page=123 --language=1

# With DDEV:
ddev typo3 ignitercf:purge:page --page=123
```

---

## Scheduler Tasks

IgniterCF provides scheduler tasks for automated or scheduled cache purges.

### Setting Up Tasks

1. Go to **System > Scheduler**
2. Click **Add task**
3. Select an IgniterCF task:
   - **IgniterCF: Purge All Zones** - Purges all configured zones
   - **IgniterCF: Purge Zone** - Purges a specific zone (select site)
   - **IgniterCF: Purge Page** - Purges a specific page (enter page UID)
4. Configure frequency (e.g., daily, hourly)
5. Save

### Task Configuration

| Task | Fields | Description |
|------|--------|-------------|
| Purge All Zones | - | Purges all configured zones |
| Purge Zone | Site | Dropdown for site selection |
| Purge Page | Page UID, Language UID | Page UID (required), Language UID (-1 = all) |

---

## Support

For issues: Enable debug mode and check logs (`var/log/typo3_ignitercf_*.log`).

---

**Author:** Patrick Hayder  
**License:** GPL-2.0-or-later  
**Not affiliated with:** Cloudflare, Inc.
