.. include:: /Includes.rst.txt

.. _usage:

=========
Verwendung
=========

.. _usage-automatic:

Automatisches Purging
=====================

Nach der Konfiguration purgt IgniterCF den Cloudflare-Cache automatisch wenn:

*  **Content gespeichert wird** - Seiten (pages) oder Inhalte (tt_content)
*  **"Clear all caches" verwendet wird** - Im Backend oder via CLI

Es ist keine weitere Aktion erforderlich.

.. _usage-manual-dropdown:

Manuelles Purging: Cache-Dropdown
=================================

Um alle Cloudflare-Zones manuell zu purgen:

1. Im TYPO3 Backend, klicke auf das Cache-Icon (Blitz-Symbol, oben rechts)
2. Wähle :guilabel:`Clear Cloudflare Cache (All Zones)`
3. Ein Bestätigungsdialog erscheint
4. Nach der Bestätigung werden alle konfigurierten Zones gepurgt

.. _usage-manual-contextmenu:

Manuelles Purging: Context-Menu
===============================

Um den Cache einer einzelnen Seite zu purgen:

1. Im Seitenbaum, Rechtsklick auf eine Seite
2. Wähle :guilabel:`Clear Cloudflare Cache`
3. Bestätige den Dialog

Alle Sprachversionen der Seite werden gepurgt.

.. _usage-cli-commands:

CLI Commands
============

IgniterCF bietet Console Commands für Cache-Purging über die Kommandozeile.

.. _usage-cli-purge-all:

Alle Zones purgen
-----------------

Purgt den gesamten Cloudflare-Cache für alle konfigurierten Zones:

.. code-block:: bash

   vendor/bin/typo3 ignitercf:purge:all

   # Mit DDEV:
   ddev typo3 ignitercf:purge:all

.. _usage-cli-purge-zone:

Spezifische Zone purgen
-----------------------

Purgt den Cache für eine spezifische Site/Zone:

.. code-block:: bash

   vendor/bin/typo3 ignitercf:purge:zone --site=main

   # Mit DDEV:
   ddev typo3 ignitercf:purge:zone --site=my-shop

.. _usage-cli-purge-page:

Spezifische Seite purgen
------------------------

Purgt den Cache für eine spezifische Seite:

.. code-block:: bash

   # Alle Sprachen:
   vendor/bin/typo3 ignitercf:purge:page --page=123

   # Spezifische Sprache:
   vendor/bin/typo3 ignitercf:purge:page --page=123 --language=1

   # Mit DDEV:
   ddev typo3 ignitercf:purge:page --page=123

.. _usage-scheduler-tasks:

Scheduler Tasks
===============

IgniterCF bietet Scheduler Tasks für automatisiertes oder geplantes Cache-Purging.

.. _usage-scheduler-setup:

Tasks einrichten
----------------

1. Gehe zu :guilabel:`System → Scheduler`
2. Klicke :guilabel:`Add task`
3. Wähle einen IgniterCF Task:

   *  **IgniterCF: Purge All Zones** - Purgt alle konfigurierten Zones
   *  **IgniterCF: Purge Zone** - Purgt eine spezifische Zone (Site auswählen)
   *  **IgniterCF: Purge Page** - Purgt eine spezifische Seite (Page UID eingeben)

4. Frequenz konfigurieren (z.B. täglich, stündlich)
5. Speichern

.. _usage-scheduler-purge-all:

Purge All Zones Task
--------------------

Purgt den gesamten Cloudflare-Cache für alle konfigurierten Zones.

*  **Anwendungsfall:** Nächtliche vollständige Cache-Aktualisierung
*  **Konfiguration:** Keine zusätzlichen Felder erforderlich

.. _usage-scheduler-purge-zone:

Purge Zone Task
---------------

Purgt den Cache für eine spezifische Zone.

*  **Anwendungsfall:** Geplanter Purge für eine bestimmte Site
*  **Konfiguration:** Site aus Dropdown auswählen

.. _usage-scheduler-purge-page:

Purge Page Task
---------------

Purgt den Cache für eine spezifische Seite.

*  **Anwendungsfall:** Regelmäßiger Purge von häufig aktualisierten Seiten
*  **Konfiguration:**

   *  **Page UID:** Die zu purgende Seite (erforderlich)
   *  **Language UID:** Spezifische Sprache oder -1 für alle Sprachen

.. _usage-testing:

Testen
======

Nach der Konfiguration empfehlen wir diese Tests:

**Test 1:** Bearbeite ein Content-Element und speichere. Prüfe die Logs auf "Cloudflare cache purged".

**Test 2:** Verwende "Clear all caches" im Backend. Alle Zones sollten gepurgt werden.

**Test 3:** Rechtsklick auf eine Seite → "Clear Cloudflare Cache". Nur diese Seite sollte gepurgt werden.

Logs prüfen:

.. code-block:: bash

   grep -i cloudflare var/log/typo3_*.log | tail -10

.. _usage-debugging:

Debugging
=========

Debug-Modus aktivieren für ausführliches Logging:

.. code-block:: php

   // config/system/additional.php
   $GLOBALS['TYPO3_CONF_VARS']['LOG']['Pahy']['Ignitercf']['writerConfiguration'] = [
       \TYPO3\CMS\Core\Log\LogLevel::DEBUG => [
           \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
               'logFileInfix' => 'ignitercf'
           ],
       ],
   ];

Logs werden in ``var/log/typo3_ignitercf_*.log`` geschrieben.
