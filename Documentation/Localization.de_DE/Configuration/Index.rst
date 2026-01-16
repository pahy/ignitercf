.. include:: /Includes.rst.txt

.. _configuration:

=============
Konfiguration
=============

IgniterCF nutzt eine hybride Konfiguration für Multi-Domain Setups:

*  **Zone ID** - Pro Site in :file:`config.yaml` konfiguriert (unterschiedlich pro Domain)
*  **API Token** - In Environment-Variablen gespeichert (sicher, nie in Git)
*  **Globale Einstellungen** - In der Extension Configuration konfiguriert (TYPO3 Backend)

.. _configuration-cloudflare-token:

Schritt 1: Cloudflare API-Token erstellen
=========================================

1. Öffne das `Cloudflare Dashboard <https://dash.cloudflare.com/>`__
2. Gehe zu :guilabel:`Profil` (oben rechts) > :guilabel:`API Tokens`
3. Klicke :guilabel:`Create Token`
4. Wähle :guilabel:`Custom Token` > :guilabel:`Get started`
5. Konfiguriere:

   *  **Token name:** ``TYPO3 IgniterCF``
   *  **Permissions:** Zone > Cache Purge > Purge
   *  **Zone Resources:** Include > Specific zone > Wähle deine Zone(s)

6. Klicke :guilabel:`Continue to summary` > :guilabel:`Create Token`
7. Token kopieren (wird nur einmal angezeigt)

.. _configuration-zone-id:

Schritt 2: Zone ID ermitteln
============================

1. Im Cloudflare Dashboard, wähle deine Domain
2. Auf der **Übersichtsseite** findest du rechts die **Zone ID**
3. Diese ID kopieren

.. _configuration-env-variables:

Schritt 3: Environment-Variablen setzen
=======================================

Füge zu deiner :file:`.env` Datei im TYPO3-Root hinzu:

.. code-block:: bash

   # Für Site mit Identifier "main":
   IGNITERCF_TOKEN_MAIN=dein-cloudflare-api-token

   # Für weitere Sites (Multi-Domain):
   IGNITERCF_TOKEN_SHOP=token-fuer-shop-zone
   IGNITERCF_TOKEN_BLOG=token-fuer-blog-zone

   # ODER: Globaler Fallback (Single-Domain Setups):
   IGNITERCF_API_TOKEN=dein-globaler-token

.. important::

   Der Site-Identifier wird in Großbuchstaben umgewandelt und Bindestriche werden zu Unterstrichen:

   *  Site ``main`` -> ``IGNITERCF_TOKEN_MAIN``
   *  Site ``my-shop`` -> ``IGNITERCF_TOKEN_MY_SHOP``
   *  Site ``blog-2024`` -> ``IGNITERCF_TOKEN_BLOG_2024``

.. _configuration-site-config:

Schritt 4: Site konfigurieren
=============================

Bearbeite die :file:`config/sites/{identifier}/config.yaml` deiner Site:

.. code-block:: yaml

   # Am Ende der Datei hinzufügen:
   cloudflare:
     zoneId: 'deine-cloudflare-zone-id'
     enabled: true

.. _configuration-multi-domain:

Multi-Domain Beispiel
---------------------

Für Installationen mit mehreren Domains/Zones:

:file:`config/sites/main/config.yaml`:

.. code-block:: yaml

   rootPageId: 1
   base: 'https://example.com/'
   # ... Sprachen ...

   cloudflare:
     zoneId: 'abc123def456'
     enabled: true

:file:`config/sites/shop/config.yaml`:

.. code-block:: yaml

   rootPageId: 100
   base: 'https://shop.example.com/'
   # ... Sprachen ...

   cloudflare:
     zoneId: 'xyz789ghi012'  # Andere Zone!
     enabled: true

:file:`.env`:

.. code-block:: bash

   IGNITERCF_TOKEN_MAIN=token-fuer-main-zone
   IGNITERCF_TOKEN_SHOP=token-fuer-shop-zone

.. _configuration-extension-settings:

Schritt 5: Extension Configuration (Optional)
=============================================

Im TYPO3 Backend: :guilabel:`Settings` > :guilabel:`Extension Configuration` > :guilabel:`ignitercf`

.. t3-field-list-table::
   :header-rows: 1

   -  :Setting: Einstellung
      :Default: Standard
      :Description: Beschreibung

   -  :Setting: Enable Cloudflare Integration
      :Default: Aktiviert
      :Description: Globaler Kill-Switch für die Extension

   -  :Setting: Purge on Clear All Caches
      :Default: Aktiviert
      :Description: Cloudflare purgen bei "Clear all caches"

   -  :Setting: Auto-Purge on Content Change
      :Default: Aktiviert
      :Description: Automatisch purgen wenn Inhalte gespeichert werden

   -  :Setting: Enable Cache-Control Middleware
      :Default: Aktiviert
      :Description: CF-Caching für Backend-User verhindern

   -  :Setting: Debug Mode
      :Default: Deaktiviert
      :Description: Ausführliches Logging aktivieren

Alternative: Environment-Variablen:

.. code-block:: bash

   IGNITERCF_ENABLED=1
   IGNITERCF_PURGE_ON_CLEAR_ALL=1
   IGNITERCF_AUTO_PURGE_ON_SAVE=1
   IGNITERCF_ENABLE_MIDDLEWARE=1
   IGNITERCF_DEBUG=0

.. _configuration-cloudflare-rule:

Schritt 6: Cloudflare Cache Rule (Empfohlen)
============================================

Optional: Erstelle eine Cache Rule in Cloudflare als zusätzlichen Schutz:

1. Cloudflare Dashboard > :guilabel:`Caching` > :guilabel:`Cache Rules`
2. Neue Rule erstellen:

   *  **Rule name:** ``Bypass cache for TYPO3 backend users``
   *  **When:** Cookie contains ``be_typo_user``
   *  **Then:** Eligible for cache: No

3. Rule deployen

.. _configuration-api-token-fallback:

API Token Lookup-Reihenfolge
============================

Token-Lookup-Reihenfolge:

1. ``IGNITERCF_TOKEN_{SITE_IDENTIFIER}`` (site-spezifisch)
2. ``IGNITERCF_API_TOKEN`` (globaler Fallback)
3. Site config.yaml ``cloudflare.apiToken`` (Legacy, unterstützt ``%env()%``)
