.. include:: /Includes.rst.txt

.. _usage:

=====
Usage
=====

After configuration, IgniterCF runs automatically in the background.

.. _usage-automatic-purge:

Automatic Cache Purge
=====================

On save (pages or content elements), IgniterCF:

1. Detects affected pages
2. Builds URLs for all language versions
3. Sends purge requests to Cloudflare

.. _usage-clear-all-caches:

Clear All Caches
================

Clicking :guilabel:`Clear all caches` in the TYPO3 backend
also purges all Cloudflare zones.

.. _usage-cache-dropdown:

Cache Dropdown
==============

Cache dropdown (top right in backend):

*  **Clear Cloudflare Cache (All Zones)** - Purges the entire cache for all
   configured Cloudflare zones

.. _usage-context-menu:

Context Menu
============

Right-click on pages in the page tree:

*  **Clear Cloudflare Cache** - Purges the cache for this specific page
   (all language versions)

A confirmation dialog appears before purging.

.. _usage-cli-commands:

CLI Commands
============

IgniterCF provides console commands for cache purging via command line.

.. _usage-cli-purge-all:

Purge All Zones
---------------

Purges the entire Cloudflare cache for all configured zones:

.. code-block:: bash

   vendor/bin/typo3 ignitercf:purge:all

   # In DDEV:
   ddev typo3 ignitercf:purge:all

.. _usage-cli-purge-zone:

Purge Specific Zone
-------------------

Purges the cache for a specific site/zone:

.. code-block:: bash

   vendor/bin/typo3 ignitercf:purge:zone --site=main

   # In DDEV:
   ddev typo3 ignitercf:purge:zone --site=my-shop

.. _usage-cli-purge-page:

Purge Specific Page
-------------------

Purges the cache for a specific page:

.. code-block:: bash

   # All languages:
   vendor/bin/typo3 ignitercf:purge:page --page=123

   # Specific language:
   vendor/bin/typo3 ignitercf:purge:page --page=123 --language=1

   # In DDEV:
   ddev typo3 ignitercf:purge:page --page=123

.. _usage-scheduler-tasks:

Scheduler Tasks
===============

IgniterCF provides scheduler tasks for automated or scheduled cache purging.

.. _usage-scheduler-setup:

Setting Up Tasks
----------------

1. Go to :guilabel:`System → Scheduler`
2. Click :guilabel:`Add task`
3. Select one of the IgniterCF tasks:

   *  **IgniterCF: Purge All Zones** - Purges all configured zones
   *  **IgniterCF: Purge Zone** - Purges a specific zone (select site)
   *  **IgniterCF: Purge Page** - Purges a specific page (enter page UID)

4. Configure frequency (e.g., daily, hourly)
5. Save

.. _usage-scheduler-purge-all:

Purge All Zones Task
--------------------

Purges the entire Cloudflare cache for all configured zones.

*  **Use case:** Nightly full cache refresh
*  **Configuration:** No additional fields required

.. _usage-scheduler-purge-zone:

Purge Zone Task
---------------

Purges the cache for a specific zone.

*  **Use case:** Scheduled purge for specific site
*  **Configuration:** Select the site from dropdown

.. _usage-scheduler-purge-page:

Purge Page Task
---------------

Purges the cache for a specific page.

*  **Use case:** Regular purge of frequently updated pages
*  **Configuration:**

   *  **Page UID:** The page to purge (required)
   *  **Language UID:** Specific language or -1 for all languages

.. _usage-testing:

Testing Your Setup
==================

.. _usage-test-auto-purge:

Test 1: Auto-Purge
------------------

1. Edit a content element
2. Save
3. Check log:

   .. code-block:: bash

      grep -i cloudflare var/log/typo3_*.log | tail -5

4. Expected output: ``Cloudflare cache purged``

.. _usage-test-middleware:

Test 2: Middleware
------------------

Verify Cache-Control headers:

.. code-block:: bash

   # Without BE cookie (should NOT have no-store header):
   curl -I https://your-domain.com/

   # With BE cookie (SHOULD have no-store header):
   curl -I -H "Cookie: be_typo_user=test" https://your-domain.com/

Expected header when logged in:

.. code-block:: text

   Cache-Control: no-store, private, max-age=0, must-revalidate

.. _usage-test-dropdown:

Test 3: Cache Dropdown
----------------------

1. Click the cache icon (top right in backend)
2. Select "Clear Cloudflare Cache (All Zones)"
3. A success notification confirms the purge

.. _usage-test-context-menu:

Test 4: Context Menu
--------------------

1. Right-click on a page in the page tree
2. Select "Clear Cloudflare Cache"
3. Confirm the dialog

.. _usage-debugging:

Debugging
=========

.. _usage-debug-mode:

Enable Debug Mode
-----------------

Enable **Debug Mode** in Extension Configuration for verbose logging.

Or via environment variable:

.. code-block:: bash

   IGNITERCF_DEBUG=1

.. _usage-debug-logging:

Custom Log Configuration
------------------------

Add to :file:`config/system/additional.php`:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['LOG']['Pahy']['Ignitercf']['writerConfiguration'] = [
       \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
           \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
               'logFileInfix' => 'ignitercf'
           ],
       ],
   ];

Log file: :file:`var/log/typo3_ignitercf_*.log`

.. _usage-debug-api:

Test Cloudflare API
-------------------

Verify API token:

.. code-block:: bash

   curl -X GET "https://api.cloudflare.com/client/v4/user/tokens/verify" \
     -H "Authorization: Bearer YOUR_TOKEN"

Test a manual purge:

.. code-block:: bash

   curl -X POST "https://api.cloudflare.com/client/v4/zones/{zone_id}/purge_cache" \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     --data '{"files":["https://your-domain.com/test"]}'

.. _usage-cloudflare-status:

Check Cloudflare Cache Status
-----------------------------

Response headers indicate cache status:

*  ``cf-cache-status: HIT`` - Served from cache
*  ``cf-cache-status: MISS`` - Not in cache, fetched from origin
*  ``cf-cache-status: DYNAMIC`` - Not cacheable
*  ``cf-cache-status: BYPASS`` - Cache rule bypassed caching

.. _usage-troubleshooting:

Troubleshooting
===============

.. _usage-troubleshooting-token:

"API Token is not configured"
-----------------------------

Check environment variable:

.. code-block:: bash

   # In DDEV:
   ddev exec printenv | grep IGNITERCF

   # Locally:
   printenv | grep IGNITERCF

Variable name must match site identifier (uppercase, hyphens become underscores).

.. _usage-troubleshooting-purge:

Purge Not Working
-----------------

1. **Check API token:** Is it valid and has Cache Purge permission?
2. **Check Zone ID:** Is it correct for this domain?
3. **Check logs:** ``grep cloudflare var/log/typo3_*.log``
4. **Check Extension Configuration:** Is the extension enabled?

.. _usage-troubleshooting-preview:

Backend Preview Gets Cached
---------------------------

1. Check if middleware is enabled in Extension Configuration
2. Verify the Cloudflare Cache Rule is active
3. Check response headers with curl (see Test 2 above)

.. _usage-known-limitations:

Known Limitations
=================

*  **Rate Limits:** Cloudflare allows 1200 purge requests per 5 minutes.
   Mass updates may hit this limit.

*  **Synchronous:** Purge requests are synchronous. Many pages may slightly
   delay backend saves.

*  **Workspaces:** Workspace publish events are not yet supported.

*  **Middleware Timing:** The middleware runs after TSFE initialization.
   Very early error responses may not be affected.
