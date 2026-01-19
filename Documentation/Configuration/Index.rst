.. include:: /Includes.rst.txt

.. _configuration:

=============
Configuration
=============

IgniterCF uses a hybrid configuration for multi-domain setups:

*  **Zone ID** - Environment variable or site :file:`config.yaml` (different per domain)
*  **API Token** - Environment variable or site :file:`config.yaml` (secure, never in Git)
*  **Global Settings** - Configured in Extension Configuration (TYPO3 backend)

.. _configuration-cloudflare-token:

Step 1: Create Cloudflare API Token
===================================

1. Open the `Cloudflare Dashboard <https://dash.cloudflare.com/>`__
2. Go to :guilabel:`Profile` (top right) > :guilabel:`API Tokens`
3. Click :guilabel:`Create Token`
4. Select :guilabel:`Custom Token` > :guilabel:`Get started`
5. Configure:

   *  **Token name:** ``TYPO3 IgniterCF``
   *  **Permissions:** Zone > Cache Purge > Purge
   *  **Zone Resources:** Include > Specific zone > Select your zone(s)

6. Click :guilabel:`Continue to summary` > :guilabel:`Create Token`
7. Copy the token (displayed only once)

.. _configuration-zone-id:

Step 2: Get Zone ID
===================

1. In Cloudflare Dashboard, select your domain
2. On the **Overview** page, find the **Zone ID** on the right side
3. Copy this ID

.. _configuration-env-variables:

Step 3: Set Environment Variables
=================================

Add to your :file:`.env` file in TYPO3 root:

.. code-block:: bash

   # Zone ID (site-specific or global):
   IGNITERCF_ZONE_MAIN=your-zone-id
   IGNITERCF_ZONE_ID=fallback-zone-id  # Global fallback

   # API Token (site-specific or global):
   IGNITERCF_TOKEN_MAIN=your-cloudflare-api-token
   IGNITERCF_API_TOKEN=fallback-token  # Global fallback

   # Multi-domain example:
   IGNITERCF_ZONE_MAIN=abc123def456
   IGNITERCF_ZONE_SHOP=xyz789ghi012
   IGNITERCF_TOKEN_MAIN=token-for-main-zone
   IGNITERCF_TOKEN_SHOP=token-for-shop-zone

.. important::

   The site identifier is converted to uppercase and hyphens become underscores:

   *  Site ``main`` -> ``IGNITERCF_ZONE_MAIN`` / ``IGNITERCF_TOKEN_MAIN``
   *  Site ``my-shop`` -> ``IGNITERCF_ZONE_MY_SHOP`` / ``IGNITERCF_TOKEN_MY_SHOP``
   *  Site ``blog-2024`` -> ``IGNITERCF_ZONE_BLOG_2024`` / ``IGNITERCF_TOKEN_BLOG_2024``

.. _configuration-site-config:

Step 4: Configure Site (Alternative to Environment Variables)
==============================================================

If not using environment variables for Zone ID, edit your site's :file:`config/sites/{identifier}/config.yaml`:

.. code-block:: yaml

   # Add at the end of the file:
   cloudflare:
     zoneId: 'your-cloudflare-zone-id'  # Optional if IGNITERCF_ZONE_* is set
     enabled: true

.. note::

   Environment variables take precedence over config.yaml settings.

.. _configuration-multi-domain:

Multi-Domain Example
--------------------

For installations with multiple domains/zones:

:file:`config/sites/main/config.yaml`:

.. code-block:: yaml

   rootPageId: 1
   base: 'https://example.com/'
   # ... languages ...

   cloudflare:
     zoneId: 'abc123def456'
     enabled: true

:file:`config/sites/shop/config.yaml`:

.. code-block:: yaml

   rootPageId: 100
   base: 'https://shop.example.com/'
   # ... languages ...

   cloudflare:
     zoneId: 'xyz789ghi012'  # Different zone!
     enabled: true

:file:`.env`:

.. code-block:: bash

   IGNITERCF_TOKEN_MAIN=token-for-main-zone
   IGNITERCF_TOKEN_SHOP=token-for-shop-zone

.. _configuration-extension-settings:

Step 5: Extension Configuration (Optional)
==========================================

In TYPO3 backend: :guilabel:`Settings` > :guilabel:`Extension Configuration` > :guilabel:`ignitercf`

.. t3-field-list-table::
   :header-rows: 1

   -  :Setting: Setting
      :Default: Default
      :Description: Description

   -  :Setting: Enable Cloudflare Integration
      :Default: Enabled
      :Description: Global kill switch for the extension

   -  :Setting: Purge on Clear All Caches
      :Default: Enabled
      :Description: Purge Cloudflare when using "Clear all caches"

   -  :Setting: Auto-Purge on Content Change
      :Default: Enabled
      :Description: Automatically purge when content is saved

   -  :Setting: Enable Cache-Control Middleware
      :Default: Enabled
      :Description: Prevent CF caching for backend users

   -  :Setting: Debug Mode
      :Default: Disabled
      :Description: Enable verbose logging

Alternative: Environment variables:

.. code-block:: bash

   IGNITERCF_ENABLED=1
   IGNITERCF_PURGE_ON_CLEAR_ALL=1
   IGNITERCF_AUTO_PURGE_ON_SAVE=1
   IGNITERCF_ENABLE_MIDDLEWARE=1
   IGNITERCF_DEBUG=0

.. _configuration-cloudflare-rule:

Step 6: Cloudflare Cache Rule (Recommended)
===========================================

Optional: Create a Cache Rule in Cloudflare as additional protection:

1. Cloudflare Dashboard > :guilabel:`Caching` > :guilabel:`Cache Rules`
2. Create new rule:

   *  **Rule name:** ``Bypass cache for TYPO3 backend users``
   *  **When:** Cookie contains ``be_typo_user``
   *  **Then:** Eligible for cache: No

3. Deploy the rule

.. _configuration-lookup-order:

Lookup Order
============

Zone ID lookup order:

1. ``IGNITERCF_ZONE_{SITE_IDENTIFIER}`` (site-specific, e.g. ``IGNITERCF_ZONE_MAIN``)
2. ``IGNITERCF_ZONE_ID`` (global fallback)
3. Site config.yaml ``cloudflare.zoneId`` (legacy)

API Token lookup order:

1. ``IGNITERCF_TOKEN_{SITE_IDENTIFIER}`` (site-specific, e.g. ``IGNITERCF_TOKEN_MAIN``)
2. ``IGNITERCF_API_TOKEN`` (global fallback)
3. Site config.yaml ``cloudflare.apiToken`` (legacy, supports ``%env()%``)

.. _configuration-legacy:

Legacy Configuration
====================

For existing installations, the old configuration still works:

.. code-block:: yaml

   # config/sites/main/config.yaml (LEGACY)
   cloudflare:
     zoneId: 'abc123...'
     apiToken: '%env(CLOUDFLARE_API_TOKEN)%'

Migration to ``IGNITERCF_TOKEN_*`` variables recommended for multi-domain setups.
