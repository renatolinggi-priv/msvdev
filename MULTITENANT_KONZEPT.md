# Multi-Tenant-Refactoring — MSV JM (SaaS für Schützenvereine)

> Konzept / Planungsdokument. Noch nicht umgesetzt — Umsetzung zu einem späteren Zeitpunkt.

## Context

Die Webapp ist heute fest auf **einen** Verein (MSV Wilen) zugeschnitten: eine DB
(`bdebbd4_msvjm`), „MSV Wilen" hart codiert in Header/Portal/PDFs/Mails, das Logo als
17 Kopien in `inc/*/dat/`. Ein zweiter Verein soll die App nutzen, ohne dass die Vereine
gegenseitig Daten sehen, und jeder Verein soll Logo/Name/Titel/Farben/Mail selbst pflegen.

**Getroffene Entscheidungen:**
- **Isolation: eine Datenbank pro Verein**, identisches Schema. Stärkste Trennung, fast
  keine Query-Änderungen (das bestehende `$conn`/`getDB()` verbindet einfach zur richtigen DB).
- **Zentrales SaaS** auf dem bestehenden Hostpoint-Account. Andere Vereine zeigen nur per
  **Subdomain oder externer Domain (CNAME)** auf die App.
- **Super-Admin** legt Vereine **manuell** an (zentrale Oberfläche über allen Vereinen).
- **Volles Branding** pro Verein: Name/Logo, PDF-Kopf/Fuss, Mail-Absender/Domain, Farben/PWA.
- **Provisionierung:** neue DB nur strukturell (leeres Schema). **Stammdaten-Übernahme aus der
  MSV-Wilen-DB** als separates, manuell ausgelöstes Super-Admin-Werkzeug (tabellenweise wählbar).

**Leitprinzip:** Der Verein-Kontext wird an **einer** Stelle, möglichst früh aus
`$_SERVER['HTTP_HOST']` aufgelöst und in `$dbConf`/`$conn`/`getDB()` geschrieben — bevor
bestehender Code DB-Zugriffe macht. Dadurch bleiben die ~250 Query-Dateien unverändert.

---

## Architektur

### Zentrale Master-DB (neu, z. B. `xxxx_saas_master`)

Schreibbare Registry (Super-Admin pflegt sie), getrennt von den Vereins-DBs:

```sql
CREATE TABLE vereine (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(50) NOT NULL UNIQUE,          -- 'msvwilen' (interner Key, Upload-Pfade)
  name VARCHAR(150) NOT NULL,                -- Anzeigename (Wartungsseite)
  db_name VARCHAR(64) NOT NULL,
  db_user VARCHAR(64) NOT NULL,
  db_pass_enc VARBINARY(512) NOT NULL,       -- AES-256-GCM, Key aus msvjm_config
  db_host VARCHAR(120) NOT NULL DEFAULT 'localhost',
  cookie_domain VARCHAR(120) NULL,           -- '' / NULL = host-only
  status ENUM('aktiv','gesperrt','wartung') NOT NULL DEFAULT 'aktiv',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE verein_hosts (                  -- 1:n: Subdomains UND externe CNAME-Domains
  id INT AUTO_INCREMENT PRIMARY KEY,
  verein_id INT NOT NULL,
  host VARCHAR(190) NOT NULL UNIQUE,         -- 'mitglieder.msvwilen.ch', 'portal.svx.ch'
  bereich ENUM('admin','portal') NOT NULL DEFAULT 'portal',  -- ersetzt admin.*-Regex
  ist_primaer TINYINT(1) NOT NULL DEFAULT 0, -- kanonischer Host für absolute Links/Mails
  FOREIGN KEY (verein_id) REFERENCES vereine(id) ON DELETE CASCADE
);
CREATE TABLE super_admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150),
  totp_secret VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

**Sicherheit:** Hostpoint vergibt pro DB einen eigenen DB-User mit Grant nur auf seine DB →
selbst ein falsch geladenes Credential öffnet nur die eigene DB (Defense-in-depth). DB-Passwörter
verschlüsselt (`openssl_encrypt(..,'aes-256-gcm',$key)`), Key in `msvjm_config.php`.

`msvjm_config.php` enthält künftig nur noch: `master_db`-Credentials, `tenant_secret`
(Encryption-Key), `superadmin_hosts`-Liste, globale Keys. Das alte `$config['db']` entfällt
nach der Migration (sonst lädt versehentlich Code die fixe Alt-DB).

### Tenant-Resolution + Connection-Layer

**Neu: `inc/tenant.inc.php`** — löst Host → Verein auf (nur Master-DB, kein Vereins-Connect),
static-gecacht (genau einmal pro Request, kein DB-Wechsel mitten im Request):

```php
function tenant_resolve(): array {
    static $ctx = null; if ($ctx !== null) return $ctx;
    $root = require __DIR__.'/../../msvjm_config.php';
    $mdb  = tenant_master_pdo($root['master_db']);
    $host = tenant_current_host();                 // HTTP_HOST oder CLI: --verein/ENV
    if (in_array(strtolower($host), $root['superadmin_hosts'] ?? [], true))
        return $ctx = ['mode'=>'superadmin','master'=>$mdb];
    $row = /* SELECT v.*, h.bereich FROM verein_hosts h JOIN vereine v ... WHERE h.host=? */;
    if (!$row)                      tenant_fail_unknown_host($host);  // 404/Landing, NIE Default-DB
    if ($row['status']!=='aktiv')   tenant_fail_gesperrt($row);       // Wartungsseite
    return $ctx = ['mode'=>'verein','verein_id'=>..,'slug'=>..,'name'=>..,'bereich'=>..,
                   'cookie_domain'=>$row['cookie_domain']??'', 'dbConf'=>[..entschlüsselt..]];
}
```

**Minimale Eingriffe (Dreh- und Angelpunkt):**
- `inc/dbconnect.inc.php` Zeile 4-5: statt `$config=require '../../msvjm_config.php'; $dbConf=$config['db'];`
  → `require_once __DIR__.'/tenant.inc.php'; $dbConf = tenant_resolve()['dbConf'];`
  Der Rest (`connect_db`, `get_db_connection`, `getDB`, globales `$conn`) bleibt **unverändert**.
- `inc/config.php`: baut ebenfalls eigenständig `$conn` + Konstanten → gleiche Umstellung auf `tenant_resolve()`.
  Beide Einstiegspfade treffen denselben static-Cache → identische, einmalige Auflösung.

### Session / Cookies pro Verein

Heute setzt `inc/session_config.inc.php` `cookie_domain` hart auf `.msvwilen.ch` (teilt Session
über `admin.*`↔`mitglieder.*`). Umstellen auf `vereine.cookie_domain` aus der Registry:
- gesetzt (`.msvwilen.ch`) → Session geteilt über die Subdomains **dieses** Vereins.
- NULL/'' → **host-only Cookie** (Pflicht für externe CNAME-Domains; maximale Isolation).
- `session_name('MSVSESS_'.$verein_id)` → verhindert PHPSESSID-Mehrfachnutzung über Vereinsgrenzen.
- `msv_cookie_domain()` liest künftig den Tenant-Kontext (→ wirkt automatisch in
  `inc/remember_me.inc.php`). `remember_tokens` liegt ohnehin pro Vereins-DB.
- `login.php` `getLoginRedirect()`: `admin.*`-Regex → `bereich` aus `verein_hosts`.

### Super-Admin

Eigene Subdomain (in `superadmin_hosts`), eigener Bereich **`superadmin/`**, eigener Login gegen
`super_admins` (Master-DB), eigene Session-Var `$_SESSION['superadmin_id']` — lädt **nie**
`auth.php`. Ein Vereins-Admin kann strukturell nicht Super-Admin werden (sein DB-User hat keinen
Grant auf die Master-DB). TOTP-2FA empfohlen. Funktionen: Vereine listen/anlegen/sperren,
Host-Mapping pflegen, Multi-DB-Migrationen, **Stammdaten-Import**, Branding-Defaults.

### Branding

Pro Verein in der **bestehenden `settings`-Tabelle** der jeweiligen Vereins-DB (automatisch
isoliert, kein Master-Lookup im Heißpfad). Neue Keys (Seed-Migration): `brand_name`,
`brand_logo`, `brand_color_primary/secondary`, `pdf_header_text`, `pdf_footer_text`,
`mail_from_name`, `mail_from_address`, `mail_domain`, `pwa_theme_color`, `pwa_name`.
Nur was VOR dem Vereins-Connect gebraucht wird (Name für Wartungsseite, cookie_domain) bleibt
in der Master-DB.

**Neu: `inc/branding.inc.php`** — Singleton, ein Query/Request:
- `brand($key, $default)` — lädt alle Branding-Keys einmal.
- `brand_logo_path()` / `brand_logo_datauri()` (für Dompdf-base64) / `brand_logo_weburl()` (für `<img>`).

**Logo:** ein Logo pro Verein unter `portal/uploads/branding/{verein_id}/logo.*` (Pfad in
`brand_logo`). Die 17 PDF-Referenzen `'dat/MSVWilen_Logo.jpg'` (z. B.
`inc/endschrang/PDFGenerator.php` Z. 110/138) → `brand_logo_datauri()`.
HTML-Brand in `inc/header.inc.php` (Z. 129/313) + `portal/portal_header.php`
→ `brand(...)`/`brand_logo_weburl()`. `manifest.json` → dynamisch `manifest.php`. Danach `dat/`-Kopien löschen.

### PDF-/Mail-/Domain-Stellen (hart codiert → Branding)

PDF: `inc/jmrang/generate_pdf_jm.php` + `config_pdf.php`, `inc/fragebogen/generate_pdf.php` +
`config_pdf.php`, `inc/endschrang/PDFGenerator.php` + `config_pdf.php`,
`inc/wanderpreise/PDFGenerator.php`, `inc/jsendsch/generate_pdf_gesamt.php`.
Mail/Domain: `password_reset_request.php` (Z. 37/40), `inc/push_helper.php` (VAPID-Subject Z. 72),
`termine.php` (iCal-UIDs `@msvwilen.ch`). Absolute Links nutzen den `ist_primaer`-Host des Vereins.

---

## Provisionierung & Stammdaten-Übernahme

### Tabellen-Kategorisierung

**Stammdaten / Definitionen** (per Werkzeug aus MSV Wilen kopierbar):
`navigation`, `JMDefinition`, `endstich_definition`, `siegerdef`, `Parameter`, `sKranzLimiten`,
`wanderpreise_regeln`, sowie Branding-Default-Keys in `settings`.

**Nutzdaten** (immer leer): `mitglieder`, `users`, `jmresultate`, `endstich`,
`endresultate_partner`, `cupPairs`, `cupFinalResults`, `einzelrangierungen`, `sieger`,
`kantiresultate`, `heimresultate`, `jungschuetzen(_resultate)`, `umfragen(_antworten/_fragen)`,
`vorstand_dokumente`, `glueck*`, `schwini*`, `kunst*`, `munitionskauf*`, `wichtige_termine`,
`wanderpreise_gewinner`, `push_abos`, `remember_tokens`, `changelog`, `mitglieder_aenderungen`,
`Standbelegung`. (Genaue Whitelist beim Umsetzen final bestätigen.)

### Neuen Verein anlegen (`superadmin/verein_anlegen.php` / CLI `tools/provision_verein.php`)

1. Master-DB: `vereine` + `verein_hosts` anlegen (Status `wartung`).
2. Hostpoint: DB + DB-User existieren (ggf. manueller Hostpoint-Schritt, Credentials eintragen).
3. **Schema-Only-Template** einspielen: `db/schema_template.sql` (`mysqldump --no-data` der
   MSV-Wilen-DB, einmalig erzeugt). Achtung: das vorhandene `db/bdebbd4_msvjm.sql` ist ein
   Voll-Dump **mit Daten** und ist NICHT als Template geeignet.
4. `markBaseline($pdo, migrations/)` → bestehende Migrationen als angewendet markieren.
5. Seed: erster Admin-User (role=admin, status=approved, Einladungslink/bcrypt) + Default-Branding.
6. Status → `aktiv`.

### Stammdaten-Import-Werkzeug (separat, manuell — `superadmin/stammdaten_import.php`)

Super-Admin wählt Ziel-Verein + Stammdaten-Tabellen aus der Whitelist; das Tool kopiert
**Inhalte** dieser Tabellen aus der MSV-Wilen-Vereins-DB in die Ziel-DB (`TRUNCATE`+`INSERT … SELECT`
bzw. Cross-DB-Copy per PDO, in Transaktion, mit Vorschau/Bestätigung). Nutzdaten-Tabellen sind
ausgeschlossen. Idempotent wiederholbar.

### Migrationen über mehrere DBs (`superadmin/migrationen.php`)

`inc/migrations.inc.php` ist bereits PDO-parametrisiert (`runPendingMigrations(PDO $db, $dir)`).
Neuer Treiber iteriert über alle Vereine, sammelt einen Report (Fehler pro Verein isoliert).
`admin/aktualisierung.php` bleibt als per-Verein-Selbstbedienung gegen die eigene DB erhalten.

### Cron / CLI (kein HTTP_HOST)

Realer Einstieg `cron/check_benachrichtigungen.php` ist DB-getrieben (`settings`-Keys pro Verein).
Neuer Wrapper `cron/run_all.php`: **ein Prozess pro Verein** (`exec(php … --verein=<slug>)`) —
nicht require-in-Schleife (sonst bleiben `getDB()`-static/globales `$conn` auf der ersten DB).
`tenant_current_host()` liest im CLI `--verein`/ENV; absolute URLs aus `ist_primaer`-Host.

---

## Umsetzungs-Reihenfolge (Phasen)

- **Phase 0 — Infrastruktur (zuerst!):** Hostpoint-Limits verifizieren: max. Anzahl MySQL-DBs,
  Domains/Aliase pro Account, SSL/Let's-Encrypt pro (auch externer CNAME-)Domain. Ohne grünes
  Licht hier ist der Rest Makulatur.
- **Phase 1 — Tenant-Layer ohne Verhaltensänderung:** Master-DB + `vereine`/`verein_hosts`,
  nur MSV Wilen eintragen. `inc/tenant.inc.php`, Umbau `dbconnect.inc.php` + `inc/config.php`.
  Ziel: App läuft exakt wie heute, nur durch den Layer.
- **Phase 2 — Session/Cookies pro Verein:** `session_config.inc.php`, `msv_cookie_domain()`,
  `getLoginRedirect()` auf Kontext umstellen (mit MSV Wilen unverändert testen).
- **Phase 3 — Branding zentralisieren:** `inc/branding.inc.php`, `settings`-Seed-Migration,
  Logo-Resolver, 17 PDF-Stellen, `header.inc.php`/`portal_header.php`, `manifest.php`, Mail/Domain.
- **Phase 4 — Super-Admin + Provisionierung:** `super_admins`, `superadmin/`, `schema_template.sql`,
  `provision_verein`, Multi-DB-Migrations-Runner, **Stammdaten-Import-Werkzeug**.
- **Phase 5 — Zweiter Verein (Pilot):** echte Cross-DB-Isolation testen (Login A sieht nichts
  von B; Branding/Mails/PDFs korrekt; Stammdaten-Import verifizieren).
- **Phase 6 — Cron/CLI Multi-Tenant:** `cron/run_all.php`, Push/Calendar je Verein verifizieren.

## Risiken

1. **Datenleck über falsche DB** (gefährlichster Fehler): static-Cache + eigener DB-User pro
   Verein + harter Test, dass unbekannter/gesperrter Host 404/Wartung liefert (NIE Default-DB).
2. **`getDB()`-static / globales `$conn`** in CLI-Schleifen → Prozess-pro-Verein im Cron.
3. **Hostpoint-Limits** für DBs/Domains/SSL — kann das Ein-Account-Modell begrenzen (Phase 0).
4. **Session-Leck** über zu breite cookie_domain → host-only-Default + `session.name`-Suffix.
5. **`msvjm_config.php` Doppelrolle** während der Migration → `$config['db']` danach entfernen.

## Verifikation

- Phase 1/2: Bestehende MSV-Wilen-Subdomains aufrufen, Login/Portal/Admin/Save-Endpoints +
  PDF-Export wie bisher prüfen (keine Verhaltensänderung). Unbekannten Host → 404/Wartung.
- Phase 3: Logo/Name/Farben/PDF-Kopf-Fuss aus `settings` ändern → in HTML, allen PDFs, Mail-From,
  PWA-Manifest sichtbar. `dat/`-Kopien entfernt, keine toten Logo-Pfade.
- Phase 4/5: Testverein anlegen (leeres Schema), Stammdaten-Import aus MSV Wilen, ersten Admin-Login;
  parallel als MSV-Wilen-User einloggen → **keine** gegenseitig sichtbaren Daten. Migration gegen
  beide DBs laufen lassen, Report prüfen.
- Phase 6: `cron/run_all.php` manuell starten → Push/Calendar erzeugen pro Verein korrekte,
  isolierte Ergebnisse mit richtigem Absender/Host.
