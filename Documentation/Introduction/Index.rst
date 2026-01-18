eln.. include:: /Includes.rst.txt

.. _introduction:

============
Introduction
============

.. _what-it-does:

What does it do?
================

IgniterCF purges the Cloudflare cache when content changes in TYPO3.
Visitors see updated content without manual cache clearing.

.. _features:

Features
========

*  **Auto-Purge on Content Change** - Automatically purges affected pages when
   content (pages, tt_content) is saved
*  **Multi-Site / Multi-Zone Support** - Each site can have its own Cloudflare zone
*  **"Clear all caches" Integration** - Purges Cloudflare when using TYPO3's
   "Clear all caches" button
*  **Cache Dropdown Entry** - Adds "Clear Cloudflare Cache (All Zones)" to the
   backend cache dropdown
*  **Context Menu** - Right-click on pages to clear their Cloudflare cache
*  **Cache-Control Middleware** - Prevents Cloudflare from caching:

   *  Backend user previews (be_typo_user cookie)
   *  Hidden pages
   *  Pages with starttime/endtime restrictions
   *  Access-restricted pages (fe_group)

*  **Secure Token Storage** - API tokens via environment variables
*  **TYPO3 v12 & v13 Compatible**

.. _requirements:

Requirements
============

*  TYPO3 12.4 LTS or 13.4 LTS
*  PHP 8.1+ (v12) or 8.2+ (v13)
*  Cloudflare account with API token (Cache Purge permission)

.. _disclaimer:

Disclaimer
==========

This extension is **not affiliated with Cloudflare, Inc.**
Cloudflare is a registered trademark of Cloudflare, Inc.
