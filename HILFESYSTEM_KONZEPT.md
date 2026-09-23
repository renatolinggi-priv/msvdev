# Konzept: Hilfesystem («?»-Buttons mit Hilfetexten) für MSV JM

**Umsetzungsstand 23.09.2026:** Phase 1 (Infrastruktur, Migration 066), Phase 2 (Pilot-Texte,
Migration 067) und Phase 3 (Rollout auf alle 44 übrigen Admin-Seiten, Migration 068 mit 139 Texten)
sind im Code. Entscheide: kein Portal, Schreiben nur `admin`, Hover und Erstbesuch-Hinweis aktiv.
Offen: Migrationen 066 bis 068 ausführen, Browser-Test, fachliches Gegenlesen der Texte.

Stand 22.09.2026. Vorlage ist das Hilfesystem im Projekt `jungschuetzen.sksg.ch`
(dort seit Migration 026, heute 87 `data-help`-Referenzen in 50 Seiten, gepflegt über
50+ Inhalts-Migrationen). Dieses Dokument beschreibt, was dort existiert, was hier
schon passt, und in welchen Schritten die Übernahme läuft.

---

## 1. Was das Jungschützen-System ausmacht (Referenz)

| Baustein | Datei (JSK) | Funktion |
|---|---|---|
| Tabelle | `sql/migrations/migration_026_hilfetexte.sql` → `jsk_hilfetexte` | `schluessel` (unique, `seite.thema`), `titel`, `inhalt_html` (beim Speichern sanitisiert), `kategorie` (= Seite), Audit-Spalten, Soft-Delete `ist_geloescht` |
| API | `pages/hilfetexte/api.php` | `GET ?action=lookup&key=` für **alle Eingeloggten** (nur titel + inhalt_html) · `GET` Liste, `GET ?action=scan` (Code-Scan), `POST add/update/delete` nur Admin/AL, CSRF, JSON-Body |
| Sanitizer | `shared/html_sanitizer.php` | Whitelist `p br strong em b i u ul ol li code pre h4 h5 h6 a`; auf `<a>` nur `href` mit Schema-Whitelist, `on*`-Handler weg. Anzeige rendert das gespeicherte HTML **ohne** weiteres Escaping |
| Frontend | `js/help-modal.js` (~330 Zeilen) | Event-Delegation auf `[data-help]`; Klick → eigenes Modal (kein Bootstrap-Modal) mit Overlay; **Hover-Quick-Peek** nach 180 ms neben dem Button («Klicken für volle Ansicht»); Client-Cache pro Key; ESC in Capture-Phase mit `stopImmediatePropagation`, damit Slide-Panels nicht mitschliessen; Fokus-Rückgabe; **First-Visit-Hint** (einmaliger Tooltip nach 2 s, LocalStorage-Flag); `hoverCustom()` für fremde Inhalte |
| Markup | `shared/footer.inc.php` | `#help-overlay` + `#help-modal` global einmal im Footer |
| CSS | `css/style.css` Zeilen 1803–2001 (~200 Zeilen) | `.btn-help` (26 px rund, `bi-question-lg`, Hover blau), `.help-modal*`, `#help-hover-popup`, `#help-firsthint`; z-index 1070/1080/1085/1086 (über Slide-Panel 1060) |
| Editor | `pages/hilfetexte.php` (871 Zeilen) | Hybrid-Tabelle (Schlüssel/Titel/Kategorie/Verwendung/Geändert) + Suche; Slide-Panel mit Textarea + **Format-Toolbar** (p, b, i, u, H, ul/ol, code, pre, Link, br, Undo/Redo, Ctrl+B/I/U, undo-fähig via `execCommand('insertText')`), Live-Vorschau, **Link-Dialog** mit Vorschlägen aus der Navigationstabelle; **Code-Scan** durchsucht `pages/` nach `data-help="…"`, zeigt «fehlen in DB» (Klick legt an) und «verwaist» |
| Inhalte | Migrationen `INSERT … ON DUPLICATE KEY UPDATE` | Texte sind **versioniert im Repo**, Editor nur für Schnellkorrekturen; Umbenennungen/Zusammenlegungen laufen ebenfalls als Migration (z.B. 144) |

**Erfahrungsregeln aus dem JSK-Projekt (übernehmen):**

- Ein «?» pro Seite (Toolbar/Seitenkopf) plus eines pro Abschnitt/Card-Kopf. **Nicht** pro Button —
  die Per-Button-Helps wurden in Migration 144 wieder zusammengelegt.
- Schlüssel `seite.thema`, Kategorie = Seitenname. Kleinbuchstaben, Punkt, `_`, `-`.
- Hilfetext erklärt, was der Anwender **entscheiden** kann. Schalter, deren Wirkung er nicht
  beurteilen kann, gehören nicht in die UI und nicht in einen Hilfetext (dort: SUPPORT.md).
- Neue Funktion → Hilfetext gehört zur Änderung (Migration), nicht nachträglich.

---

## 2. Was hier schon passt / anders ist

**Passt (gleicher Stack):** Bootstrap 5 + bootstrap-icons + jQuery + SweetAlert2;
`inc/js/msv-toast.js` mit `msvToast/msvEsc/msvPost/msvGet/msvConfirmDelete`; Slide-Panel zentral
(`inc/partials/side_panel.inc.php`, CSS in `msv-styles.css`, Overlay 1055 / Panel 1060);
`inc/partials/page_header.inc.php` (`$page_title`, `$page_actions`); `adminApiGuard()` +
`csrf_require(true)`; PDO `getDB()`; Migrations-Runner `admin/aktualisierung.php`
(nächste freie Nummer **066**); DB-Navigation `navigation` (Text/Link/ParentID/SortOrder/Icon/IstTrennlinie)
mit idempotentem Insert-Muster (Migration 030/049). Alle 51 Admin-Seiten unter `inc/*.php`
binden `header.inc.php` **und** `footer.inc.php` ein → ein globaler Modal-Einbau reicht.

**Anders (Anpassungen beim Portieren):**

| JSK | MSV |
|---|---|
| `App.*`-Namespace, `window.pageReady`, Turbo (`data-turbo-permanent`, `data-turbo-eval`) | kein Turbo → IIFE `window.MsvHelp`, normale `$(function)`; Turbo-Sonderfälle entfallen |
| `requireLogin()` / `requireMinRole('AbteilungsleiterAusbildung')` | `adminApiGuard('json')` (admin/vorstand), Schreiben ggf. zusätzlich `user_role === 'admin'` |
| POST mit JSON-Body + `csrf_token` im Body | `msvPost()` (form-encoded, Token automatisch aus `[name=csrf_token]`) + `csrf_require(true)` |
| `App.toast`, `App.esc`, `App.confirmDelete`, `App.openPanel` | `msvToast`, `msvEsc`, `msvConfirmDelete`, Slide-Panel-Klassen `.open` auf `.hybrid-edit-panel` |
| Tabellenpräfix `jsk_` | hier ohne Präfix (`settings`, `navigation`, …) → Tabelle **`hilfetexte`** |
| API-Pfad relativ `pages/hilfetexte/api.php` | Seiten liegen in `inc/` **und** `admin/` → Pfad über `$incBase` aus `header.inc.php` injizieren (bekannter Nav-Link-Pitfall `../inc/`) |
| Farbe `#3b6cce` hart | `var(--primary-color)` / bestehende Tokens |
| `title=` an Editor-Buttons | `data-tooltip` (Konvention) |
| Links im Hilfetext immer `target="_blank"` | interne `*.php`-Links im selben Tab öffnen, nur externe `_blank` |
| Code-Scan-Wurzel `pages/` | `inc/`, `admin/` (später `portal/`), ohne `vendor/`, `dat/`, `*.js.bak` |

Es gibt hier heute **kein** Hilfesystem; `data-tooltip` (`inc/js/msv-tooltips.js`) trägt nur
einzeilige Texte und bleibt für Buttons bestehen. Beide Systeme ergänzen sich: Tooltip = «was
macht dieser Knopf», Hilfetext = «wie funktioniert dieser Abschnitt».

---

## 3. Entscheidungen vorab

1. **Umfang:** Admin-Bereich zuerst (Empfehlung). Portal als eigene Phase 4, weil dort
   Header/Footer/CSS getrennt sind und der Lookup andere Rollen braucht.
2. **Wer pflegt Texte:** Lesen = alle Admin-Rollen (admin, vorstand). Schreiben: Empfehlung
   **admin + vorstand** (der Vorstand kennt die Abläufe; es ist Inhalt, keine Konfiguration).
   Alternative: nur admin nach dem Muster «PDF-Vorlage».
3. **Hover-Quick-Peek:** ja (gleicher Code, kein Mehraufwand; auf Touch ohnehin nur Klick).
4. **First-Visit-Hint:** ja, LocalStorage-Key `msvjm.help_hint_seen`.
5. **Quelle der Wahrheit:** Texte als Migration (`ON DUPLICATE KEY UPDATE`), Editor für
   Schnellkorrekturen; eine Editor-Korrektur wird bei der nächsten Gelegenheit in eine
   Migration zurückgeführt (JSK-Praxis).

---

## 4. Phase 1 — Infrastruktur (ein Arbeitsgang, ~8 Dateien)

Reihenfolge ist wegen **Sofort-Deploy auf Prod** wichtig: neue Dateien sind harmlos,
Änderungen an `header.inc.php`/`footer.inc.php`/`msv-styles.css` sind sofort live.
Vor jedem Edit an bestehenden Dateien Zeilenenden prüfen (LF/CRLF-Pitfall), keine
Heredoc-Massen-Edits.

### 4.1 Migration `migrations/066_hilfetexte.sql`

```sql
CREATE TABLE IF NOT EXISTS hilfetexte (
    id            INT NOT NULL AUTO_INCREMENT,
    schluessel    VARCHAR(100) NOT NULL COMMENT 'Stabiler Key, z.B. jmdefinition.skalierung',
    titel         VARCHAR(150) NOT NULL,
    inhalt_html   TEXT NOT NULL COMMENT 'Sanitisiertes HTML (Whitelist beim Speichern)',
    kategorie     VARCHAR(50) DEFAULT NULL COMMENT 'Seitenname',
    erstellt_am   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    geaendert_am  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    erstellt_von  INT DEFAULT NULL,
    geaendert_von INT DEFAULT NULL,
    ist_geloescht TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_schluessel (schluessel),
    KEY idx_kategorie (kategorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Plus Navigationseintrag «Hilfetexte» (`hilfetexte.php`, Icon `bi-question-circle`) unter
«Definitionen / Ausdrucke», Block Stammdaten, `SortOrder` 45 (zwischen PDF-Vorlage 40 und
Trennlinie 50) — idempotent nach Muster Migration 030. Seitentitel = Menütext = Tab: «Hilfetexte».

### 4.2 Sanitizer `inc/hilfetexte/html_sanitizer.inc.php`

1:1-Port von `shared/html_sanitizer.php`. Einzige Änderung: `target="_blank"` nur für
`http(s)://`, interne Links (`xyz.php`, `../portal/…`) ohne target.

### 4.3 Endpunkt `inc/hilfetexte/api.php`

Konventions-Kopf: DB-Include → `adminApiGuard('json')` → bei POST `csrf_require(true)`.

- `GET ?action=lookup&key=` → `{success, item:{titel, inhalt_html}}`, 404 wenn fehlt.
  Key-Regex `^[a-z0-9._-]+$`.
- `GET` (ohne action) → Liste aller nicht gelöschten Einträge.
- `GET ?action=scan` → durchsucht `inc/`, `admin/` nach `data-help="…"` (Kommentare
  gestrippt, `vendor|lib|dat|node_modules` ausgeschlossen, Endungen php/html/inc), liefert
  `used: {key: [dateien]}`.
- `POST action=add|update|delete` (form-encoded via `msvPost`), Soft-Delete, Unique-Prüfung
  mit sauberem 409. Schreibrecht gemäss Entscheidung 2.

### 4.4 Frontend `inc/js/msv-help.js`

Port von `help-modal.js` als IIFE `window.MsvHelp` mit `init/show/close/hoverCustom/hoverHide`.
Ohne Turbo-Sonderpfade. API-URL aus `window.MSV_HELP_API` (im Header gesetzt:
`<?= $incBase ?>hilfetexte/api.php`). Fehler → `msvToast(..., 'error')`. Cache-Invalidierung
nach Speichern im Editor über `MsvHelp.forget(key)`.

Einbindung in `inc/header.inc.php` direkt nach `msv-tooltips.js`, mit `filemtime`-Cache-Busting
(nie `time()`).

### 4.5 CSS in `css/msv-styles.css`

Block «Hilfesystem» (~200 Zeilen aus `style.css` 1803–2001): `.btn-help`, `.help-overlay`,
`.help-modal*`, `#help-hover-popup`, `#help-firsthint`, `prefers-reduced-motion`.
z-index-Kette bleibt 1070/1080/1085/1086 (Slide-Panel hier 1055/1060, passt).
Farben auf Tokens. `.btn-help` ist bewusst **kein** `.btn` → keine Kollision mit dem
Inline-Button-CSS im Header (Pitfall Button-Grösse).

### 4.6 Modal-Markup in `inc/footer.inc.php`

`#help-overlay` + `#help-modal` (Header mit `<h5 id="help-modal-title">`, Close-Button,
`#help-modal-body`). Einmal, vor dem schliessenden `</div>`-Stapel.

### 4.7 Editor `inc/hilfetexte.php` + `inc/hilfetexte/hilfetexte.js`

Port von `pages/hilfetexte.php`, aufgeteilt in Seite (PHP/Markup) und JS-Datei (IIFE, wie
`munitionskauf.js`), damit die Seite schlank bleibt.

- `$page_specific_css` **vor** Header, nur rohes CSS (Editor-Toolbar, Scan-Banner, Link-Dialog).
  Breite `.content-width-default`.
- `partials/page_header.inc.php` mit `$page_title = 'Hilfetexte'`, `$page_actions` = Suche,
  Code-Scan (`btn-outline-secondary btn-sm`), Neu (`btn-outline-success btn-sm`).
- Tabelle nach Tabellen-Norm, `msv_empty_row()` («Keine Hilfetexte gefunden»), Zeilen
  `.hybrid-row(.selected)`.
- Slide-Panel über `partials/side_panel.inc.php` (`$panel_width = '560px'`): Schlüssel, Titel,
  Kategorie (datalist), Textarea + Format-Toolbar (alle Buttons mit `data-tooltip`), Vorschau,
  Footer Löschen (`outline-danger`) · Abbrechen (`outline-secondary`) · Speichern (`outline-primary`).
- Link-Dialog: Vorschläge aus `navigation` (Text, Link ≠ '' und ≠ '#').
- Code-Scan-Banner: fehlende Keys anklickbar → Panel mit vorbefülltem Schlüssel + Kategorie.
- `msvConfirmDelete` fürs Löschen, `msvPost`/`msvGet` für alle Aufrufe.

### 4.8 Doku

- `changelog.json`: Eintrag `admin_only`, Typ «neu» (keine geraden Anführungszeichen).
- `CLAUDE.md` «Konventionen»: Absatz Hilfesystem (Button-Platzierung, Schlüssel-Schema,
  Text gehört zur Migration, Code-Scan vor Release).

### 4.9 Deploy-Reihenfolge (Prod live)

1. Neue Dateien: Migration, Sanitizer, API, `msv-help.js`, Editor-Seite + JS.
2. Migration in `admin/aktualisierung.php` ausführen (Tabelle + Menüpunkt).
3. Editor per Menü aufrufen und einen Testeintrag speichern (API, CSRF, Sanitizer geprüft).
4. Erst dann bestehende Dateien: CSS-Block, Footer-Markup, Header-Script.
5. Lint jeder PHP-Datei mit `C:\temp\xampp\php\php.exe -l`.

Ohne Tabelle liefert der Lookup 404 → nur ein Fehler-Toast beim Klick, keine Seite bricht.

---

## 5. Phase 2 — Pilot-Inhalte (3–5 Seiten)

Zuerst wenige Seiten mit hohem Erklärungsbedarf ausrüsten, Optik und Textlänge im Modal
prüfen, dann erst breit ausrollen. Kandidaten (Texte grösstenteils schon vorhanden in
CLAUDE.md, Memory-Notizen und Changelog-Beschreibungen):

| Seite | Schlüssel (Vorschlag) | Inhalt |
|---|---|---|
| `jmdefinition.php` | `jmdefinition.uebersicht`, `jmdefinition.skalierung`, `jmdefinition.zuschlag` | Hochrechnung nur bei Maxpunkte < 100, Zuschlag 2026, Gruppen |
| `endschloesen.php` | `endschloesen.typ`, `endschloesen.preise` | Typ-Umschalter, serverseitige Preislogik, Gäste |
| `einsatzplanung.php` | `einsatzplanung.slots`, `einsatzplanung.word`, `einsatzplanung.abrechnung` | Slots → Zuweisungen, Word-Roundtrip mit Platzhaltern, Helferabrechnung Pauschale/OK/Korrektur |
| `cup.php` | `cup.baum`, `cup.nachruecker` | Turnierbaum, Nachrücker aus R1-Verlierern, manuelle Gewinner |
| `kantiabr.php` | `kantiabr.formular` | SKSG-xlsm, Verantwortlicher, Sportgerät-Mapping, 40 Zeilen |

Platzierung: ein `?` im Seitenkopf (`$page_actions`) für die Übersicht, je eines im Kopf der
betreffenden Card/Tabelle (`.table-title` bzw. `action_card`). Inhalte als
`migrations/067_hilfetexte_pilot.sql` mit `ON DUPLICATE KEY UPDATE`.

---

## 6. Phase 3 — Rollout auf die übrigen Admin-Seiten

Rund 45 weitere Seiten. Vorgehen pro Seite: `?`-Buttons setzen → Code-Scan zeigt fehlende
Keys → Texte in Migration nachziehen. Der Code-Scan ist damit die Checkliste; Ziel vor
jedem Release: «0 fehlen in DB». Weitere Seiten mit klarem Bedarf:
`wanderpreise_regeln.php`, `csv_schnittstelle.php`, `drucksteuerung.php`, `pdf_design.php`,
`admin/aktualisierung.php`, `anlass_galerie_verwaltung.php`, `jungschuetzen_verwaltung.php`,
`dokumente_verwaltung.php`, `benutzerverwaltung.php`, `munitionskauf.php`,
Import-Seiten (`endsch_import`, `heimkanti_import`, `resultimport`).

Der Aufwand liegt im **Schreiben der Texte**, nicht im Code.

---

## 7. Phase 4 (optional) — Mitgliederportal

- Lookup für Rollen `mitglied`/`jungschuetze`: eigener Endpunkt `api/hilfe_lookup.php` nach
  Portal-Konvention (`requireLogin()` aus `auth.php`), nur `lookup`. Pflege bleibt im Admin.
- `portal/portal_footer.php`: Modal-Markup; `portal/portal_header.php`: `msv-help.js`;
  CSS-Block nach `css/portal.css` (Portal-Tokens).
- Schlüssel `portal.<seite>.<thema>`, Kategorie `portal.<seite>`; Code-Scan-Wurzel um `portal/`
  erweitern.
- Portal-Changelog erst nach Freigabe (Feedback-Regel).

---

## 8. Nicht übernehmen

Turbo-Attribute und `_ensureHoverPopup`-Re-Attach-Logik (kein Turbo hier), `App.*`-Namespace,
`window.pageReady`, `sendJson`/`requireMinRole` (MSV-Äquivalente vorhanden),
JSK-spezifische Texte. `kranzabzeichen`-artige Domänenlogik spielt keine Rolle.
