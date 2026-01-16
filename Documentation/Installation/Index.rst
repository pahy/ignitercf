.. include:: /Includes.rst.txt

.. _installation:

============
Installation
============

.. _installation-composer:

Installation via Composer
=========================

Install via Composer:

.. code-block:: bash

   composer require pahy/ignitercf

.. _installation-ter:

Installation from TER
=====================

Or install from TYPO3 Extension Repository:

1. Go to :guilabel:`Admin Tools > Extensions`
2. Click :guilabel:`Get Extensions`
3. Search for "ignitercf"
4. Click :guilabel:`Import and Install`

.. _installation-activate:

Activate the Extension
======================

Activate the extension:

.. code-block:: bash

   # Via CLI
   vendor/bin/typo3 extension:activate ignitercf

   # Or with DDEV
   ddev typo3 extension:activate ignitercf

Or in the TYPO3 backend:

1. Go to :guilabel:`Admin Tools > Extensions`
2. Find "IgniterCF" in the list
3. Click the :guilabel:`Activate` button

.. _installation-clear-cache:

Clear Cache
===========

Clear caches after activation:

.. code-block:: bash

   vendor/bin/typo3 cache:flush

   # Or with DDEV
   ddev typo3 cache:flush
