# IgniterCF - Cloudflare Cache Purge für TYPO3

> **Achtung: Diese Extension ist Work in Progress und noch nicht produktionsreif. Nutzung auf eigene Gefahr.**

Automatisches Purgen des Cloudflare-Caches bei Content-Änderungen in TYPO3 v12/v13.

> **[English Version](README.md)**

## Inbetriebnahme

### 1. Cloudflare API-Token erstellen

1. Öffne das [Cloudflare Dashboard](https://dash.cloudflare.com/)
2. Gehe zu **Profil** (oben rechts) → **API Tokens**
3. Klicke **Create Token**
4. Wähle **Custom Token** → **Get started**
5. Konfiguriere:
   - **Token name:** `TYPO3 IgniterCF`
   - **Permissions:**
     - Zone → Cache Purge → Purge
   - **Zone Resources:**
     - Include → Specific zone → Wähle deine Zone(s)
6. Klicke **Continue to summary** → **Create Token**
7. Token kopieren (wird nur einmal angezeigt)

### 2. Zone ID ermitteln

1. Im Cloudflare Dashboard → Wähle deine Domain
2. Auf der Übersichtsseite rechts: **Zone ID**
3. Kopiere diese ID

### 3. Environment Variable setzen

In `.env` (TYPO3-Root):

```env
# Für Site mit Identifier "main":
IGNITERCF_TOKEN_MAIN=dein-cloudflare-api-token

# Bei Multi-Domain (weitere Sites):
IGNITERCF_TOKEN_SHOP=token-fuer-shop-zone
IGNITERCF_TOKEN_BLOG=token-fuer-blog-zone

# ODER: Globaler Fallback (wenn alle Sites dieselbe Zone nutzen):
IGNITERCF_API_TOKEN=dein-globaler-token
```

**Namenskonvention:** Site-Identifier wird uppercase, Bindestriche werden Unterstriche:
- `main` → `IGNITERCF_TOKEN_MAIN`
- `my-shop` → `IGNITERCF_TOKEN_MY_SHOP`

### 4. Site Configuration erweitern

Bearbeite `config/sites/{site-identifier}/config.yaml`:

```yaml
# Am Ende der Datei hinzufügen:
cloudflare:
  zoneId: 'deine-zone-id-hier'
  enabled: true
```

**Beispiel für Multi-Domain:**

```yaml
# config/sites/main/config.yaml
cloudflare:
  zoneId: 'abc123def456'
  enabled: true

# config/sites/shop/config.yaml  
cloudflare:
  zoneId: 'xyz789ghi012'  # Andere Zone!
  enabled: true
```

### 5. Extension Configuration (optional)

Im TYPO3 Backend: **Settings → Extension Configuration → ignitercf**

| Setting | Default | Beschreibung |
|---------|---------|--------------|
| Enable Cloudflare Integration | ✓ | Globaler Kill-Switch |
| Purge on Clear All Caches | ✓ | Bei "Clear all caches" auch CF purgen |
| Auto-Purge on Content Change | ✓ | Bei Content-Änderungen automatisch purgen |
| Enable Cache-Control Middleware | ✓ | Verhindert CF-Caching für BE-User |
| Debug Mode | ✗ | Verbose Logging |

### 6. Cache leeren

```bash
ddev typo3 cache:flush
# oder
./vendor/bin/typo3 cache:flush
```

### 7. Testen

#### Test 1: Auto-Purge bei Content-Änderung
1. Bearbeite ein Content-Element im Backend
2. Speichere
3. Prüfe Log: `var/log/typo3_*.log`
   ```bash
   grep -i cloudflare var/log/typo3_*.log | tail -5
   ```
4. Erwartete Ausgabe: `Cloudflare cache purged`

#### Test 2: Middleware (Cache-Control Header)
```bash
# Ohne BE-Cookie (sollte KEINEN no-store Header haben):
curl -I https://deine-domain.de/

# Mit BE-Cookie (sollte no-store Header haben):
curl -I -H "Cookie: be_typo_user=test" https://deine-domain.de/
```

#### Test 3: Cache-Dropdown
1. Im Backend oben rechts auf das Cache-Icon klicken
2. "Clear Cloudflare Cache (All Zones)" sollte erscheinen
3. Klicken

#### Test 4: Context-Menu
1. Rechtsklick auf eine Seite im Seitenbaum
2. "Clear Cloudflare Cache" auswählen
3. Bestätigen

---

## Troubleshooting

### Problem: "Cloudflare API Token is not configured"

**Lösung:** Prüfe Environment Variable:
```bash
# In DDEV:
ddev exec printenv | grep IGNITERCF

# Lokal:
printenv | grep IGNITERCF
```

Variable muss Site-Identifier entsprechen (uppercase, Bindestriche → Unterstriche).

### Problem: Purge funktioniert nicht

**Check 1:** API-Token gültig?
```bash
curl -X GET "https://api.cloudflare.com/client/v4/user/tokens/verify" \
  -H "Authorization: Bearer DEIN_TOKEN"
```

**Check 2:** Zone-ID korrekt?
- Cloudflare Dashboard → Domain → Übersicht → Zone-ID

**Check 3:** Log prüfen
```bash
grep -i "cloudflare\|ignitercf" var/log/typo3_*.log
```

### Problem: Backend-Preview wird gecacht

**Lösung 1:** Middleware aktiv?
- Extension Configuration → "Enable Cache-Control Middleware" aktivieren

**Lösung 2:** Cloudflare Cache Rule einrichten:
1. Cloudflare Dashboard → Caching → Cache Rules
2. Neue Rule: `Cookie contains "be_typo_user"` → Cache: Bypass

### Debug-Modus aktivieren

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

## Konfigurationsübersicht

| Was | Wo | Beispiel |
|-----|-----|----------|
| Zone ID | Site config.yaml | `cloudflare.zoneId: 'abc123'` |
| API Token | Environment Variable | `IGNITERCF_TOKEN_MAIN=...` |
| Globale Settings | Extension Configuration | Backend → Settings |
| Site aktivieren/deaktivieren | Site config.yaml | `cloudflare.enabled: false` |

---

## Funktionen

- Auto-Purge bei Content-Änderungen (pages, tt_content)
- Multi-Site / Multi-Zone Support
- "Clear all caches" Integration
- Cache-Dropdown Eintrag
- Context-Menu im Seitenbaum
- CLI Commands für automatisierte Purges
- Scheduler Tasks für geplante Purges
- Middleware verhindert CF-Caching für BE-User
- Batch-Purge (max. 30 URLs pro Request)
- TYPO3 v12 + v13 kompatibel

---

## CLI Commands

IgniterCF bietet Console Commands für Cache-Purging via Kommandozeile.

### Alle Zones purgen

```bash
vendor/bin/typo3 ignitercf:purge:all

# In DDEV:
ddev typo3 ignitercf:purge:all
```

### Spezifische Zone purgen

```bash
vendor/bin/typo3 ignitercf:purge:zone --site=main

# In DDEV:
ddev typo3 ignitercf:purge:zone --site=my-shop
```

### Spezifische Seite purgen

```bash
# Alle Sprachen:
vendor/bin/typo3 ignitercf:purge:page --page=123

# Spezifische Sprache:
vendor/bin/typo3 ignitercf:purge:page --page=123 --language=1

# In DDEV:
ddev typo3 ignitercf:purge:page --page=123
```

---

## Scheduler Tasks

IgniterCF bietet Scheduler Tasks für automatisierte oder geplante Cache-Purges.

### Tasks einrichten

1. Gehe zu **System → Scheduler**
2. Klicke **Add task**
3. Wähle einen IgniterCF Task:
   - **IgniterCF: Purge All Zones** - Purgt alle konfigurierten Zones
   - **IgniterCF: Purge Zone** - Purgt eine spezifische Zone (Site auswählen)
   - **IgniterCF: Purge Page** - Purgt eine spezifische Seite (Page UID eingeben)
4. Frequenz konfigurieren (z.B. täglich, stündlich)
5. Speichern

### Task-Konfiguration

| Task | Felder | Beschreibung |
|------|--------|--------------|
| Purge All Zones | - | Purgt alle konfigurierten Zones |
| Purge Zone | Site | Dropdown zur Site-Auswahl |
| Purge Page | Page UID, Language UID | Page UID (required), Language UID (-1 = alle) |

---

## Support

Bei Problemen: Debug-Modus aktivieren und Logs prüfen (`var/log/typo3_ignitercf_*.log`).

---

**Author:** Patrick Hayder  
**Lizenz:** GPL-2.0-or-later  
**Nicht affiliiert mit:** Cloudflare, Inc.
