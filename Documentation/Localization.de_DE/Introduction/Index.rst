.. include:: /Includes.rst.txt

.. _introduction:

==========
Einführung
==========

.. _what-it-does:

Was macht die Extension?
========================

IgniterCF purgt den Cloudflare-Cache, wenn sich Inhalte in TYPO3 ändern.
Besucher sehen aktualisierte Inhalte ohne manuelles Cache-Leeren.

.. _features:

Funktionen
==========

*  **Auto-Purge bei Content-Änderungen** - Purgt automatisch betroffene Seiten,
   wenn Inhalte (pages, tt_content) gespeichert werden
*  **Multi-Site / Multi-Zone Support** - Jede Site kann eine eigene Cloudflare-Zone haben
*  **"Clear all caches" Integration** - Purgt Cloudflare beim Verwenden von
   "Clear all caches"
*  **Cache-Dropdown Eintrag** - Fügt "Clear Cloudflare Cache (All Zones)" zum
   Backend Cache-Dropdown hinzu
*  **Context-Menu** - Rechtsklick auf Seiten um deren Cloudflare-Cache zu leeren
*  **Cache-Control Middleware** - Verhindert, dass Cloudflare folgendes cached:

   *  Backend-User Previews (be_typo_user Cookie)
   *  Versteckte Seiten
   *  Seiten mit starttime/endtime Einschränkungen
   *  Zugriffsgeschützte Seiten (fe_group)

*  **Sichere Token-Speicherung** - API-Tokens über Environment-Variablen
*  **TYPO3 v12 & v13 kompatibel**

.. _requirements:

Voraussetzungen
===============

*  TYPO3 12.4 LTS oder 13.4 LTS
*  PHP 8.1+ (v12) oder 8.2+ (v13)
*  Cloudflare-Account mit API-Token (Cache Purge Berechtigung)

.. _disclaimer:

Haftungsausschluss
==================

Diese Extension ist **nicht mit Cloudflare, Inc. affiliiert**.
Cloudflare ist eine eingetragene Marke von Cloudflare, Inc.
