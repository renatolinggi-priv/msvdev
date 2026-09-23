# CLAUDE.md

## Deployment & Server (WICHTIG)

**Jede Dateiänderung landet sofort auf PRODUKTION.** Die VS-Code-SFTP-Extension ist mit
`uploadOnSave: true` **und** `watcher.autoUpload: true` konfiguriert ([.vscode/sftp.json](.vscode/sftp.json)).
Der Watcher erfasst auch Änderungen, die *ausserhalb* des Editors auf die Platte geschrieben
werden — also auch Edits von Claude Code. Es gibt keine Staging-Umgebung: wer hier eine Datei
speichert, hat deployt. Entsprechend vorsichtig arbeiten und bei riskanten Änderungen vorher
fragen.

Ausnahmen vom Upload regelt die `ignore`-Liste in `sftp.json` (u.a. `CLAUDE.md`, `.git/**`,
`rohdaten/**`, `**/vendor/**`, Konzept-Dokumente).

### SSH-Zugang

Hosting: Hostpoint, FreeBSD 14.4, PHP 8.3.32. SSH läuft über denselben Key wie SFTP
(`privateKeyPath` in `sftp.json`) — es braucht kein separates Geheimnis:

```bash
ssh -i "C:/Users/LinggiR/.ssh/id_ed25519_tagebuch" -o BatchMode=yes bdebbd4@sl137.web.hostpoint.ch
```

Nutzen: `error_log` lesen, `crontab -l` prüfen, verifizieren was tatsächlich deployt ist.
Schreibende Operationen auf dem Server (Dateien ändern, DB-Writes, Dienste) immer vorher
mit dem Benutzer abklären. Deployen läuft über SFTP, nicht per SSH.

### Verzeichnisse

| | |
|---|---|
| Lokal | `c:\TEMP\msvjm\msvdev` |
| Docroot (Prod) | `/home/bdebbd4/www/jahresmeisterschaft.msvwilen.ch` |
| Domains | `jahresmeisterschaft.msvwilen.ch` **und** `admin.msvwilen.ch` |

`admin.msvwilen.ch` ist **nur ein vHost-Alias auf denselben Docroot**, kein zweites
Deployment — in `~/www` liegt genau ein Verzeichnis. Cron-Jobs und Links, die auf
`admin.msvwilen.ch` zeigen, führen dieselben Dateien aus.

Deploy-Kontrolle per Checksummen-Vergleich (FreeBSD kennt `sha256`, nicht `sha256sum`):

```bash
# Server
ssh … 'sha256 -q ~/www/jahresmeisterschaft.msvwilen.ch/inc/push_helper.php'
# lokal
sha256sum inc/push_helper.php
```

### Cron

`crontab -l` auf dem Server. Der Benachrichtigungs-Lauf ist:

```
0 9 * * *  curl -s -o /dev/null "https://admin.msvwilen.ch/cron/check_benachrichtigungen.php?key=<cron_trigger_key>"
```

Also **täglich 09:00**. Der Key steht im Klartext im crontab und in der `settings`-Tabelle
(`setting_key = 'cron_trigger_key'`); nie in Repo-Dateien oder Commits schreiben.

### PHP-Error-Log

Seit 05.08.2026 schreibt PHP nach `/home/bdebbd4/php_error.log`, gesetzt in der `.user.ini`
im Docroot (`error_log = /home/bdebbd4/php_error.log`, bewusst **ausserhalb** des Docroots,
sonst per Browser abrufbar). Lesen per SSH: `tail -50 /home/bdebbd4/php_error.log`. Keine
Rotation eingerichtet. Änderungen an der `.user.ini` greifen erst nach `user_ini.cache_ttl`
(300 s).

**PITFALL:** `error_reporting(0)` in einer Datei schaltet auch das Logging ab. Die bewusst
geschluckten Exceptions in `cron/check_benachrichtigungen.php` landen nur dann im Log, wenn
`error_log()` dort tatsächlich aufgerufen wird — ein Cron-Block kann sonst still fehlschlagen
und liefert trotzdem `"success": true`.

### Externe Werkzeuge auf dem Server

Vorhanden unter `/usr/local/bin`: `gs`, `pdfinfo`, `pdfseparate`, `pdfunite`, `pdftoppm`,
`wkhtmltopdf`, `pdfjam`/`pdfbook2` (TeX mit pdfpages), `psbook`. Nicht vorhanden: LibreOffice,
`qpdf`, `pdftk`. Aus Web-PHP heraus (`exec` ist erlaubt) müssen PATH und HOME gesetzt werden,
sonst findet `pdfjam` sein `pdflatex` nicht (Muster: `absendenShellEnv()` in
`inc/absenden/generate_absendenbuch_pdf.php`). Office→PDF läuft über
`inc/lib/convertapi_helper.php` (iLoveAPI, Fallback ConvertAPI, Konfig in `msvjm_config.php`).

## Generierte Exportdateien in `inc/<modul>/dat/`

Jeder Export-Generator schreibt eine zeitgestempelte Datei pro Aufruf. Früher räumte
niemand auf (05.08.2026: 965 Dateien in `jmdefinition/dat`, ~600 weitere verteilt, 19 MB).

**Aufräumen läuft über [inc/dat_cleanup.inc.php](inc/dat_cleanup.inc.php)** und ist
**zentral verdrahtet**: [inc/config.php](inc/config.php) ruft am Ende
`datAufraeumenNachAusgabe(5)` auf. Das registriert eine `register_shutdown_function`, die
nach der Ausgabe das `dat/` des *aktuell laufenden Skripts* aufräumt (Verzeichnis aus
`$_SERVER['SCRIPT_FILENAME']`). Da alle 20 Generatoren `inc/config.php` einbinden, ist jedes
Modul automatisch abgedeckt — auch künftige. Skripte ohne `dat/` im eigenen Ordner tun nichts.

Einzelne Generatoren brauchen also **keinen** eigenen Aufruf. Die eine Ausnahme ist
`inc/jmdefinition/export_jmdefinition_pdf.php` (Begründung unten). Für einen manuellen
oder periodischen Durchlauf über alle Module gibt es [cron/cleanup_dat.php](cron/cleanup_dat.php)
(`--dry` für einen Testlauf, `--keep=N` für die Anzahl) — nötig ist er nicht.

Abschalten für einen Einzelfall: `define('DAT_CLEANUP_AUS', true)` vor dem Einbinden von
`inc/config.php`.

**NIEMALS per Wildcard in `dat/` löschen.** In denselben Verzeichnissen liegen Dateien, die
gebraucht werden:

- `MSVWilen_Logo.jpg` / `SKSG_Logo.jpg` — `inc/pdf_design.php` verteilt das Logo in jedes
  Modul-`dat/`, `inc/home.php` bindet `jmrang/dat/MSVWilen_Logo.jpg` als `<img>` ein
- Vorlagen: `Resultatbuch_Template*.docx`, `Resultatbuch_V1..V3.docx`,
  `VorlageFragebogen.docx`, `Kantonalstich_Abrechnungsformular_ab-2023.xlsm`

Der Helfer löscht darum nur, was die Signatur einer generierten Datei trägt: ein
Zeitstempel `_YYYY-MM-DD_HH-MM-SS` unmittelbar vor der Endung. Gruppiert wird pro
Namens-Präfix, `Jahresprogramm_2025` behält also unabhängig von `Jahresprogramm_2026`
seine neuesten Stände. Keine der Schutz-Dateien erfüllt das Muster.

Sonderfall `inc/jmdefinition/export_jmdefinition_pdf.php`: dieser Generator ist **ohne Login
öffentlich erreichbar** (die URL steht in `api/api.php` als `pdf_url`) und damit der einzige,
den ein Fremder beliebig oft auslösen kann. Er behält deshalb zusätzlich seinen direkten
`datAufraeumen($datDir, 3)`-Aufruf unmittelbar nach dem Schreiben — bewusste doppelte
Absicherung, falls der zentrale Hook einmal wegfällt. Ausserdem schreibt er absolut über
`__DIR__`, weil er auch per `include` aus `api/pdf_download.php` läuft.

### PHP-Versionen

Prod 8.3, lokaler Lint 8.2 (XAMPP, `C:\temp\xampp\php\php.exe -l <datei>`). Der Lint prüft
nur Syntax — kein Runtime, keine DB.

## Konventionen für Admin-Seiten und ihre Endpunkte

- **Jeder Endpunkt unter `inc/<modul>/*.php` und `api/*.php` startet mit dem Guard**, direkt
  nach dem DB-Include: `require_once __DIR__ . '/../admin_api_guard.inc.php'; adminApiGuard('json'|'html'|'plain');`
  (Rollen admin/vorstand). Ändernde Aufrufe zusätzlich `require_once __DIR__ . '/../csrf.inc.php'; csrf_require(true);`
  (Token per POST `csrf_token` oder Header `X-CSRF-TOKEN`). Nie `session_start()` direkt — die
  Session kommt über den Guard bzw. `inc/session_config.inc.php`. Öffentliche Ausnahmen sind
  dokumentiert (`inc/jmdefinition/export_jmdefinition_pdf.php`, `inc/standbelegung/get_pdf.php`).
- Nur-Admin-Funktionen (PDF-Vorlage) prüfen zusätzlich `user_role === 'admin'` und antworten 403.
- `$page_specific_css` **vor** `include 'header.inc.php'` setzen und **nur rohes CSS** hineinschreiben —
  der Header wrappt es in `<style>`; ein `<link>` oder `<style>` darin bricht das Layout. Breite über
  genau eine Klasse `.content-width-wide|default|narrow` am `.main-content-wrapper`, kein eigenes `max-width`.
- **Navigation** (Tabelle `navigation`, Renderer `inc/navigation.inc.php`, Editor `admin/nav_admin.php`): Root
  «Einstellungen» (seit Mig. 069) bündelt Konfiguration und Admin-Werkzeuge – neue Konfigurationsseiten gehören
  dorthin, nicht ins Benutzermenü (dort nur Passwort, Portal, Changelog, Abmelden). Spalte `NurAdmin` blendet
  einen Eintrag für den Vorstand aus (Häkchen im Editor); die Seite muss den Zugriff trotzdem selbst prüfen.
  Links relativ zu `inc/` (`seite.php`), Seiten unter `admin/` mit führendem Slash (`/admin/seite.php`); der
  Renderer macht relative Links über `$incBase` absolut. Menüeinträge per idempotenter Migration (Muster 030/069).
- Seitentitel = Menütext = Browser-Tab (Regel aus Migration 043/049). Partials nutzen:
  `partials/page_header.inc.php` (`$page_title`, optional `$page_actions`, `$page_show_mobile`),
  `partials/side_panel.inc.php`, `partials/action_card.inc.php`, `msv_empty_row()`.
- JS-Helfer zentral in `inc/js/msv-toast.js`: `msvToast/msvError/msvConfirm/msvConfirmDelete`,
  `msvEsc()` (HTML-Escaping), `msvXhrMessage(xhr, fallback)`, `msvPost(url, data, ok, {csrf, failMsg})`.
  Keine lokalen Kopien von `esc()`/`ajaxMsg()` mehr anlegen.
- **Hilfesystem** (seit 22.09.2026, Mig. 066/067, Vorbild jungschuetzen.sksg.ch): `<button type="button"
  class="btn-help" data-help="seite.thema" aria-label="Hilfe"></button>` neben Seitentitel
  (`$page_actions`), Abschnitts-/Card-Titel oder einem erklärungsbedürftigen Feld – **nicht pro Button**
  (dafür `data-tooltip`). Text in Tabelle `hilfetexte`, Anzeige `inc/js/msv-help.js` (Klick = Modal in
  `footer.inc.php`, Hover = Kurzansicht, Erstbesuch-Hinweis), Lookup `inc/hilfetexte/api.php`
  (admin/vorstand), Pflege `inc/hilfetexte.php` (**nur admin**). **Neue Funktion → Hilfetext gehört zur
  Änderung**: Schlüssel im Markup setzen und Text als Migration `INSERT … ON DUPLICATE KEY UPDATE`
  (Muster 067) mitliefern; die Migration ist die Quelle der Wahrheit, der Editor dient Schnellkorrekturen.
  Vor Release Code-Scan auf der Seite «Hilfetexte»: 0 «ohne Text». Erlaubtes HTML siehe
  `inc/hilfetexte/html_sanitizer.inc.php`. PITFALL Runner: keine Zeile im SQL-String darf mit `--` beginnen.
- Tooltips über `data-tooltip` (nie `title=`); Buttons Outline nach Zweck + `btn-sm`
  (grün `outline-success` = Anlegen/Hochladen/Import, blau = Speichern/Bearbeiten, türkis = Export/PDF,
  rot nur Löschen, grau = Abbrechen).
