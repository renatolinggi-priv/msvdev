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

### PITFALL: es gibt keinen PHP-Error-Log

`error_log` ist in der PHP-ini **leer**, es gibt kein `~/logs` und keine Log-Datei im Home
(`.user.ini` setzt nur `display_errors = stderr`). Alle `error_log()`-Aufrufe — u.a. die
bewusst geschluckten Exceptions in `cron/check_benachrichtigungen.php` — sind damit **nicht
einsehbar**. Ein Cron-Block kann still fehlschlagen und liefert trotzdem `"success": true`.

Fix wäre eine Zeile in der `.user.ini` im Docroot, mit dem Logfile **ausserhalb** des
Docroots (sonst per Browser abrufbar):

```ini
error_log = /home/bdebbd4/php_error.log
```

Noch nicht gesetzt (Stand 05.08.2026) — vor dem Setzen mit dem Benutzer abklären, da es
Prod-Verhalten ändert.

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
