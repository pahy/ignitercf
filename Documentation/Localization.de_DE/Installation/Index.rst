.. include:: /Includes.rst.txt

.. _installation:

============
Installation
============

.. _installation-composer:

Installation via Composer
=========================

Installation über Composer:

.. code-block:: bash

   composer require pahy/ignitercf

.. _installation-ter:

Installation aus TER
====================

Oder über das TYPO3 Extension Repository installieren:

1. Gehe zu :guilabel:`Admin Tools > Extensions`
2. Klicke :guilabel:`Get Extensions`
3. Suche nach "ignitercf"
4. Klicke :guilabel:`Import and Install`

.. _installation-activate:

Extension aktivieren
====================

Extension aktivieren:

.. code-block:: bash

   # Via CLI
   vendor/bin/typo3 extension:activate ignitercf

   # Oder mit DDEV
   ddev typo3 extension:activate ignitercf

Oder im TYPO3 Backend:

1. Gehe zu :guilabel:`Admin Tools > Extensions`
2. Finde "IgniterCF" in der Liste
3. Klicke den :guilabel:`Activate` Button

.. _installation-clear-cache:

Cache leeren
============

Nach der Aktivierung Cache leeren:

.. code-block:: bash

   vendor/bin/typo3 cache:flush

   # Oder mit DDEV
   ddev typo3 cache:flush
