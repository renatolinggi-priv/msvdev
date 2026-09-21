<?php
/**
 * Einsatzplanung – Arbeitseinsätze (Obligatorisch / Feldschiessen / Wiler Chilbi) in der App planen.
 *
 * Ohne ?id: Planliste des Jahres (Neuer Plan: leer, Kopie aus Vorjahr, aus Dokument).
 * Mit ?id:  Plan-Editor – Raster Funktionen × Termine (Slots als Chips, Slide-Panel, Auto-Save)
 *           bzw. Personen × Schichten (Chilbi). Struktur (Termine/Funktionen) in Modals.
 *           Exporte DOCX/PDF/XLSX, Rückmeldung der Vereine einlesen, Freigabe ins Portal.
 *
 * Backend: inc/einsatzplanung/*.php (adminApiGuard + CSRF), Logik in plan_helpers.inc.php.
 * Datenmodell: Migration 050. Lesetabelle fürs Portal bleibt einsatz_zuweisungen (Projektion).
 */
require_once 'dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';

// Zugriffsschutz: nur Vorstand/Admin (vor header.inc.php, da dieser Output erzeugt)
if (!(isAdmin() || isVorstand()) && (int)($_SESSION['user_id'] ?? 0) !== 1) {
    header('Location: home.php');
    exit();
}
require_once __DIR__ . '/einsatzplanung/plan_helpers.inc.php';
require_once __DIR__ . '/helferabrechnung/abrechnung.inc.php';   // Ansicht «Abrechnung» (Schlossturm, Migration 058)
require_once __DIR__ . '/partials/empty_state.inc.php';   // msv_empty_row()

$db            = getDB();
$planId        = (int)($_GET['id'] ?? 0);
$selected_year = (int)($_GET['year'] ?? date('Y'));
$plan          = null;
$plaene        = [];
$allePlaene    = [];
$dokumente     = [];
$mitglieder    = [];
$years         = [];
$dbFehler      = '';

try {
    if ($planId > 0) {
        $plan = ep_plan_laden($db, $planId);
        if (!$plan) { header('Location: einsatzplanung.php'); exit(); }
    }
    $allePlaene = ep_plan_liste($db, null);
    $plaene     = $plan ? [] : ep_plan_liste($db, $selected_year);
    $mitglieder = ep_mitglieder_map($db);
    foreach ($allePlaene as $p) $years[] = (int)$p['jahr'];
    $dokumente = $db->query("SELECT id, titel, dateiname, jahr FROM vorstand_dokumente
                              WHERE typ = 'einsatzplan' AND LOWER(dateiname) REGEXP '\\\\.(docx|xlsx)$'
                              ORDER BY jahr DESC, titel")->fetchAll();
} catch (Throwable $e) {
    $dbFehler = 'Die Tabellen der Einsatzplanung fehlen noch – bitte Migration 050 über «Aktualisierung» ausführen.';
    error_log('[einsatzplanung] ' . $e->getMessage());
}
// Ansicht «Abrechnung» (Helferabrechnung pro Verein, nur Schlossturm-Pläne): ?id=…&ansicht=abrechnung[&ok=1]
// Logik in helferabrechnung/abrechnung.inc.php, Markup in helferabrechnung/ansicht.inc.php
$ansichtAbr = $plan && $plan['typ'] === 'schlossturm' && ($_GET['ansicht'] ?? '') === 'abrechnung';
$okMit      = (int)($_GET['ok'] ?? 0) === 1;
$a = null; $haFehler = '';
if ($ansichtAbr) {
    try { $a = ha_abrechnung($db, $plan, $okMit); }
    catch (Throwable $e) {
        $haFehler = 'Die Abrechnung konnte nicht geladen werden – bitte Migration 058 über «Aktualisierung» ausführen.';
        error_log('[einsatzplanung/abrechnung] ' . $e->getMessage());
    }
}
$istA  = $plan ? $plan['layout'] === 'funktion_x_termin' : false;   // Layout A = Funktionen × Termine
// Portal-Umfragen als mögliche Quelle der Verfügbarkeiten (Migration 053)
$umfragen = [];
if ($plan && $istA) {
    try { $umfragen = $db->query("SELECT id, titel, status FROM umfragen WHERE kategorie IN ('arbeitseinsatz','helfer') ORDER BY erstellt_am DESC")->fetchAll(); } catch (Throwable $e) { $umfragen = []; }
}
$hatRollen = $plan && $istA && count(array_filter($plan['funktionen'], fn($f) => !empty($f['rolle']) && $f['rolle'] !== 'OK')) > 0;
$years = array_values(array_unique($years));
if (!in_array((int)date('Y'), $years, true)) $years[] = (int)date('Y');
if (!in_array($selected_year, $years, true)) $years[] = $selected_year;
if (!in_array((int)date('Y') + 1, $years, true)) $years[] = (int)date('Y') + 1;
rsort($years);

$page_specific_css = <<<'CSS'
.ep-list td { vertical-align: middle; }
.ep-status { font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; }
.ep-status.entwurf { background: #e2e8f0; color: #475569; }
.ep-status.freigegeben { background: #dbeafe; color: #1d4ed8; }
.ep-status.final { background: #dcfce7; color: #15803d; }
.ep-toolbar { display: flex; flex-wrap: wrap; gap: .5rem 1.1rem; align-items: flex-end; }
.ep-toolbar .btn { white-space: nowrap; }
.ep-tb-group { display: flex; flex-direction: column; gap: .12rem; }
.ep-tb-label { font-size: .6rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; font-weight: 600; padding-left: .2rem; }
.ep-toolbar .dropdown-menu-sm .dropdown-item { font-size: .82rem; padding: .3rem .8rem; }
/* kleinere Monitore: engere Leiste, Statusgruppe bleibt rechts */
@media (max-width: 1499px) {
  .ep-toolbar { gap: .4rem .7rem; }
  .ep-toolbar .btn-group-sm > .btn { padding: .2rem .45rem; font-size: .76rem; }
  .ep-toolbar .btn .me-1 { margin-right: .2rem !important; }
  .ep-tb-group.ms-lg-auto { margin-left: auto !important; }
}
@media (max-width: 1199px) {
  .ep-toolbar .btn-group-sm > .btn { padding: .18rem .38rem; font-size: .72rem; }
  .ep-tb-label { font-size: .56rem; }
}
.ep-toolbar .dropdown-menu-sm .dropdown-item small { font-size: .7rem; }
/* Anwesenheit im Admin (gleiche Optik wie die Portal-Seite) */
.an-termine { display: flex; gap: .4rem; flex-wrap: wrap; margin-bottom: .8rem; }
.an-termine button { border: 1px solid #e2e8f0; border-radius: 999px; padding: .25rem .8rem; font-size: .8rem; color: #334155; background: #fff; }
.an-termine button.aktiv { background: var(--primary-color, #2d4373); color: #fff; border-color: var(--primary-color, #2d4373); }
.an-stat { display: flex; gap: .5rem; margin-bottom: .6rem; font-size: .78rem; }
.an-stat span { border-radius: .5rem; padding: .2rem .55rem; background: #f1f5f9; color: #475569; }
.an-stat .da { background: #dcfce7; color: #15803d; } .an-stat .nein { background: #fee2e2; color: #b91c1c; }
.an-fn { font-size: .66rem; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; font-weight: 700; margin: .6rem 0 .25rem; }
.an-row { display: flex; align-items: center; gap: .6rem; background: #fff; border: 1px solid #e2e8f0; border-radius: .55rem; padding: .3rem .55rem; margin-bottom: .3rem; }
.an-row.da { border-color: #86efac; background: #f0fdf4; } .an-row.nein { border-color: #fca5a5; background: #fef2f2; }
.an-name { flex: 1; min-width: 0; font-weight: 600; font-size: .86rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.an-name small { font-weight: 400; color: #94a3b8; font-size: .7rem; margin-left: .4rem; }
.an-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; background: #c62828; }
.an-dot.freienbach { background: #3b5998; } .an-dot.wollerau { background: #2e7d32; }
.an-btn { flex: 0 0 auto; width: 2.6rem; height: 2.1rem; border-radius: .5rem; border: 1px solid #cbd5e1; background: #fff; font-size: 1.05rem; color: #94a3b8; display: flex; align-items: center; justify-content: center; }
.an-row.da .an-btn.ja { background: #16a34a; border-color: #16a34a; color: #fff; }
.an-row.nein .an-btn.no { background: #dc2626; border-color: #dc2626; color: #fff; }
.an-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 0 1rem; }
.ep-grid-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: .5rem; background: #fff; }
.ep-grid { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .85rem; }
.ep-grid th, .ep-grid td { border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: .35rem .45rem; vertical-align: top; min-width: 170px; }
.ep-grid th:last-child, .ep-grid td:last-child { border-right: 0; }
.ep-grid tbody tr:last-child td { border-bottom: 0; }
.ep-grid thead th { background: #f8f9fa; font-size: .72rem; text-transform: uppercase; color: #64748b; font-weight: 600; }
.ep-grid thead th .ep-t-bez { text-transform: none; font-size: .85rem; color: #1e293b; }
.ep-grid thead th .ep-t-datum { text-transform: none; font-size: .82rem; color: #334155; font-weight: 500; }
.ep-grid thead th .ep-t-zeit { text-transform: none; font-weight: 400; }
.ep-grid thead th .ep-t-info { text-transform: none; font-weight: 400; font-size: .66rem; color: #94a3b8; margin-top: .1rem; }
.ep-grid .ep-fn { width: 220px; min-width: 200px; font-weight: 600; background: #fcfcfd; color: #1e293b; }
.ep-grid .ep-fn small { display: block; font-weight: 400; color: #94a3b8; }
.ep-grid .ep-gruppe td { background: #f1f5f9; font-weight: 700; font-size: .72rem; text-transform: uppercase; color: #475569; padding: .25rem .45rem; }
.ep-chip, .ep-cell { display: flex; align-items: center; gap: .4rem; width: 100%; text-align: left; border: 1px solid #cbd5e1; background: #fff;
  border-radius: .4rem; padding: .22rem .5rem; margin-bottom: .25rem; font-size: .82rem; line-height: 1.25; cursor: pointer; min-height: 1.9rem; }
.ep-chip:last-child, .ep-cell:last-child { margin-bottom: 0; }
.ep-chip:hover, .ep-cell:hover { border-color: #94a3b8; background: #f8fafc; }
.ep-chip.selected, .ep-cell.selected { outline: 2px solid var(--primary-color, #2d4373); outline-offset: 1px; }
.ep-chip.ep-offen, .ep-cell.ep-leer { color: #94a3b8; border-style: dashed; }
/* offene Positionen (kein Name, MSV) und Soll-Platzhalter: leicht rot, damit sie nach dem Einteilen auffallen */
.ep-chip.ep-offen.ep-v-msv, .ep-chip.ep-offen:not([data-verein]), .ep-chip.ep-ph { background: #fff1f2; border-color: #fca5a5; color: #b91c1c; }
.ep-chip.ep-offen.ep-v-msv .ep-dot, .ep-chip.ep-offen:not([data-verein]) .ep-dot, .ep-chip.ep-ph .ep-dot { background: #fca5a5; }
.ep-chip.ep-offen.ep-v-msv:hover, .ep-chip.ep-ph:hover { background: #ffe4e6; border-color: #f87171; }
.ep-chip.ep-v-freienbach { background: #e8f0fe; border-color: #b6c8f0; color: #2d4373; }
.ep-chip.ep-v-wollerau   { background: #e8f5e9; border-color: #b5dcb8; color: #2e7d32; }
.ep-chip.ep-warn { border-color: #f0ad4e; background: #fff7ec; }
.ep-chip .ep-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; background: #cbd5e1; }
.ep-chip.ep-v-msv.ep-besetzt .ep-dot { background: #c62828; }
.ep-chip.ep-v-freienbach .ep-dot { background: #3b5998; }
.ep-chip.ep-v-wollerau .ep-dot { background: #2e7d32; }
.ep-chip.ep-warn .ep-dot { background: #f0ad4e; }
.ep-chip .ep-txt { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ep-chip .ep-bem { font-size: .7rem; color: #94a3b8; }
.ep-chip .ep-anw { font-size: .8rem; flex-shrink: 0; }
.ep-chip .ep-anw-da { color: #16a34a; } .ep-chip .ep-anw-nein { color: #dc2626; }
.ep-ausw-table td, .ep-ausw-table th { font-size: .8rem; padding: .25rem .4rem; white-space: nowrap; }
.ep-ausw-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.ep-ausw-table td.ep-fehlt { color: #c62828; font-weight: 600; }
.ep-legend { display: flex; flex-wrap: wrap; gap: 1rem; font-size: .75rem; color: #64748b; }
/* Editor: Raster links, Mitgliederliste (Drag & Drop) rechts */
.ep-editor-row { display: flex; gap: 1rem; align-items: flex-start; }
.ep-grid-col { flex: 1 1 auto; min-width: 0; }
.ep-side { flex: 0 0 250px; position: sticky; top: 70px; border: 1px solid #e2e8f0; border-radius: .5rem; background: #fff; display: flex; flex-direction: column; max-height: calc(100vh - 90px); }
.ep-side-head { padding: .35rem .45rem; border-bottom: 1px solid #e2e8f0; background: #f8f9fa; border-radius: .5rem .5rem 0 0; font-size: .72rem; text-transform: uppercase; color: #64748b; font-weight: 600; min-height: 2.65rem; display: flex; align-items: center; }
.ep-side-search { padding: .35rem .45rem; border-bottom: 1px solid #e2e8f0; }
.ep-side-list { overflow-y: auto; padding: .35rem .45rem; }
/* Listeneintrag = gleiche Chip-Optik wie eine Position (weiss, roter MSV-Punkt), rechts der Zähler */
.ep-mitglied { display: flex; align-items: center; gap: .4rem; width: 100%; border: 1px solid #cbd5e1; background: #fff; border-radius: .4rem; padding: .22rem .5rem; margin-bottom: .25rem; font-size: .82rem; line-height: 1.25; min-height: 1.9rem; cursor: grab; user-select: none; }
.ep-mitglied:last-child { margin-bottom: 0; }
.ep-mitglied:hover { border-color: #94a3b8; background: #f8fafc; }
.ep-mitglied.dragging { opacity: .5; }
.ep-mitglied .ep-m-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ep-mitglied .ep-m-count { display: none; font-size: .68rem; min-width: 1.35rem; text-align: center; border-radius: .6rem; padding: .05rem .3rem; background: #dcfce7; color: #15803d; font-weight: 600; }
.ep-mitglied.geplant .ep-m-count { display: inline-block; }
.ep-mitglied.inaktiv .ep-m-name { color: #94a3b8; font-style: italic; }
.ep-mitglied.extern { border-style: dashed; }
.ep-mitglied.extern .ep-m-name::after { content: " (extern)"; color: #94a3b8; font-size: .7rem; }
.ep-side-sub { font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; font-weight: 600; margin: .35rem 0 .25rem; padding: .3rem .2rem .15rem; border-top: 1px dashed #e2e8f0; cursor: pointer; user-select: none; display: flex; align-items: center; gap: .3rem; }
.ep-side-group:first-child .ep-side-sub { border-top: 0; margin-top: 0; }
.ep-side-sub .ep-caret { font-size: .7rem; color: #94a3b8; transition: transform .15s; }
.ep-side-group.ep-collapsed .ep-side-sub .ep-caret { transform: rotate(-90deg); }
.ep-side-sub .ep-g-n { margin-left: auto; font-size: .66rem; min-width: 1.35rem; text-align: center; border-radius: .6rem; padding: .05rem .3rem; background: #e2e8f0; color: #475569; }
.ep-side-group.ep-collapsed .ep-side-items { display: none; }
.ep-side-foot { padding: .35rem .6rem; border-top: 1px solid #eef1f6; font-size: .7rem; color: #94a3b8; }
.ep-side-stunden { padding: 0 .45rem .35rem; }
.ep-stunden-table { width: 100%; font-size: .74rem; }
.ep-stunden-table td { padding: .1rem .15rem; white-space: nowrap; }
.ep-stunden-table td.ep-st-pos, .ep-stunden-table td.ep-st-std { text-align: right; font-variant-numeric: tabular-nums; }
.ep-stunden-table td.ep-st-vs { font-size: .66rem; text-align: right; }
.ep-stunden-table tr.ep-st-total td { border-top: 1px solid #e2e8f0; font-weight: 600; }
.ep-chip.ep-drop { outline: 2px dashed var(--primary-color, #2d4373); outline-offset: 1px; background: #eef2ff; }
/* Einklappbare Funktionszeilen: Klick auf den Funktionsnamen; eingeklappt nur Zusammenfassung je Zelle */
.ep-grid .ep-fn { cursor: pointer; user-select: none; }
.ep-grid .ep-fn .ep-caret { float: right; color: #94a3b8; font-size: .75rem; margin-top: .15rem; transition: transform .15s; }
.ep-grid tr.ep-collapsed .ep-fn .ep-caret { transform: rotate(-90deg); }
.ep-grid tr.ep-collapsed td .ep-chip { display: none; }
.ep-grid .ep-summary { display: none; color: #475569; background: #f8fafc; border-style: solid; cursor: pointer; }
.ep-grid tr.ep-collapsed .ep-summary { display: flex; }
.ep-grid .ep-summary.ep-leer { color: #94a3b8; background: #fff; border-style: dashed; }
.ep-grid .ep-summary .ep-offen-n { color: #c62828; font-weight: 600; margin-left: auto; font-size: .72rem; }
.ep-grid .ep-fn .ep-fn-n { display: none; font-size: .68rem; min-width: 1.35rem; text-align: center; border-radius: .6rem; padding: .05rem .3rem; background: #e2e8f0; color: #475569; font-weight: 600; vertical-align: middle; }
.ep-grid tr.ep-collapsed .ep-fn .ep-fn-n { display: inline-block; }
.ep-grid thead th.ep-fn .ep-alle { float: right; font-size: .9rem; line-height: 1; color: var(--primary-color, #2d4373); cursor: pointer; padding: .1rem .25rem; border-radius: .3rem; }
.ep-grid thead th.ep-fn .ep-alle:hover { background: #e2e8f0; }
/* Einteilungs-Vorschläge (automatisch, noch nicht bestätigt) */
.ep-chip.ep-vorschlag { border: 1px dashed #3b5998 !important; background: #eef2ff !important; }
.ep-chip.ep-vorschlag .ep-txt::before { content: "? "; color: #3b5998; font-weight: 700; }
/* Verfügbarkeits-Filter in der Mitgliederliste */
.ep-mitglied.ep-nv { opacity: .35; }
.ep-verf-table td, .ep-verf-table th { vertical-align: middle; font-size: .8rem; padding: .25rem .35rem; }
.ep-verf-table .form-check-inline { margin-right: .5rem; }
.ep-verf-table .form-check-input { margin-top: .15rem; }
.ep-verf-badge { font-size: .7rem; }
.ep-einteilung-tabelle td, .ep-einteilung-tabelle th { font-size: .8rem; padding: .25rem .4rem; text-align: center; }
.ep-einteilung-tabelle td.ep-fehlt { color: #c62828; font-weight: 600; }
.ep-chip.ep-add { justify-content: center; color: #94a3b8; border-style: dashed; background: transparent; min-height: 1.6rem; padding: .05rem .5rem; }
.ep-chip.ep-add:hover { color: var(--primary-color, #2d4373); border-color: var(--primary-color, #2d4373); background: #f8fafc; }
.ep-chip[draggable="true"] { cursor: grab; }
.ep-chip.dragging { opacity: .5; }
.ep-side.ep-drop-remove { outline: 2px dashed #c62828; outline-offset: 2px; background: #fff5f5; }
.ep-side.ep-drop-remove .ep-side-head { color: #c62828; }
.ep-side-remove-hint { display: none; padding: .5rem .6rem; font-size: .78rem; color: #c62828; text-align: center; }
.ep-side.ep-drop-remove .ep-side-remove-hint { display: block; }
/* Kompaktmodus bei vielen Spalten (Chilbi: 8 Schichten) – passt ohne horizontales Scrollen in den 1500px-Container */
.ep-editor-row.ep-eng .ep-side { flex-basis: 220px; }
.ep-grid.ep-eng th, .ep-grid.ep-eng td { min-width: 112px; padding: .3rem .3rem; }
.ep-grid.ep-eng .ep-fn { width: 150px; min-width: 140px; font-size: .78rem; }
.ep-grid.ep-eng thead th { font-size: .66rem; }
.ep-grid.ep-eng thead th .ep-t-datum { font-size: .76rem; }
.ep-grid.ep-eng thead th .ep-t-zeit { font-size: .68rem; }
.ep-grid.ep-eng .ep-chip { font-size: .74rem; padding: .18rem .35rem; gap: .3rem; min-height: 1.7rem; }
.ep-grid.ep-eng .ep-chip .ep-dot { width: 6px; height: 6px; }
@media (max-width: 991px) { .ep-editor-row { flex-direction: column; } .ep-side { position: static; flex-basis: auto; width: 100%; max-height: 320px; } }
.ep-legend .ep-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: .3rem; }
.ep-person-td { font-weight: 600; white-space: nowrap; background: #fcfcfd; }
.ep-grid tfoot td { background: #f8f9fa; font-size: .72rem; color: #475569; border-top: 2px solid #e2e8f0; border-bottom: 0; vertical-align: top; }
.ep-grid tfoot td.ep-fehlt { color: #c62828; font-weight: 600; }
.ep-grid th.ep-drop { outline: 2px dashed var(--primary-color, #2d4373); outline-offset: -2px; background: #eef2ff; }
.ep-cell[draggable="true"] { cursor: grab; }
.ep-cell.dragging { opacity: .5; }
.ep-soll-table input { width: 4.2rem; text-align: center; }
.ep-struct-table td { vertical-align: middle; padding: .25rem .3rem; }
.ep-struct-table input.form-control-sm { min-width: 70px; }
.ep-preview { max-height: 420px; overflow: auto; }
.ep-preview td, .ep-preview th { font-size: .8rem; }
#epMitgliedFilter { margin-bottom: .3rem; }
.ep-hint { font-size: .78rem; color: #94a3b8; }
.ep-chip .ep-ok { order: -1; flex-shrink: 0; font-size: .58rem; font-weight: 800; line-height: 1.2; background: #f59e0b; color: #fff; border-radius: .3rem; padding: 0 .25rem; }   /* vor dem Namen, wird nie abgeschnitten */
.ep-ok-liste td { vertical-align: middle; }
.ep-ok-liste .form-switch { min-height: 0; margin: 0; }
CSS;
if ($ansichtAbr) $page_specific_css .= "\n" . require __DIR__ . '/helferabrechnung/ansicht_css.inc.php';

include 'header.inc.php';
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Mitglieder-Options (aktive zuerst) – einmal gerendert, im Panel wiederverwendet
$optAktiv = ''; $optInaktiv = '';
foreach ($mitglieder as $m) {
    $label = $h(ep_name_vorname($m));
    if ((int)$m['Verstorben'] === 1) continue;
    if ((int)$m['Status'] === 1) $optAktiv .= '<option value="' . (int)$m['ID'] . '">' . $label . '</option>';
    else $optInaktiv .= '<option value="' . (int)$m['ID'] . '">' . $label . ' (inaktiv)</option>';
}

// Seitenkopf-Aktionen
ob_start();
if ($plan) { ?>
  <a href="einsatzplanung.php?year=<?= (int)$plan['jahr'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Zur Liste</a>
  <?php if ($plan['typ'] === 'schlossturm'): ?>
  <!-- Umschalter Planung ↔ Abrechnung (Helferstunden pro Verein, nur Schlossturm) -->
  <div class="btn-group btn-group-sm" role="group">
    <a href="einsatzplanung.php?id=<?= $planId ?>" class="btn <?= $ansichtAbr ? 'btn-outline-primary' : 'btn-primary' ?>"><i class="bi bi-grid-3x3 me-1"></i>Planung</a>
    <a href="einsatzplanung.php?id=<?= $planId ?>&ansicht=abrechnung" class="btn <?= $ansichtAbr ? 'btn-primary' : 'btn-outline-primary' ?>" data-tooltip="Helferstunden pro Verein: Pauschalen, OK, Vor-/Nacharbeiten, Excel/PDF"><i class="bi bi-calculator me-1"></i>Abrechnung</a>
  </div>
  <?php endif; ?>
  <span class="badge ep-status <?= $h($plan['status']) ?>" id="epStatusBadge"><?= $h(EP_STATUS[$plan['status']] ?? $plan['status']) ?></span>
<?php } else { ?>
  <form method="get" class="d-flex align-items-center gap-2">
    <label class="text-muted small mb-0" for="epYear">Jahr</label>
    <select name="year" id="epYear" class="form-select form-select-sm export-year-select" onchange="this.form.submit()">
      <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === $selected_year ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
    </select>
  </form>
  <button type="button" class="btn btn-outline-info btn-sm js-auswertung" data-jahr="<?= $selected_year ?>" <?= $dbFehler ? 'disabled' : '' ?> data-tooltip="Anwesenheit <?= $selected_year ?>: je Verein und Mitglied über alle freigegebenen Pläne"><i class="bi bi-clipboard-data me-1"></i>Auswertung</button>
  <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#epNeuModal" <?= $dbFehler ? 'disabled' : '' ?>><i class="bi bi-plus-lg me-1"></i>Neuer Plan</button>
<?php }
$page_actions     = ob_get_clean();
$page_show_mobile = true;
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-wide">
        <?php $page_title = 'Einsatzplanung'; include 'partials/page_header.inc.php'; ?>
        <input type="hidden" id="csrfToken" value="<?= $csrf ?>">

        <?php if ($dbFehler): ?>
          <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><?= $h($dbFehler) ?></div>
        <?php elseif (!$plan): ?>

        <!-- ================= PLANLISTE ================= -->
        <div class="table-wrapper">
          <h5 class="table-title"><span><i class="bi bi-person-lines-fill me-2"></i>Einsatzpläne <?= $selected_year ?></span><span class="badge bg-secondary"><?= count($plaene) ?></span></h5>
          <div class="table-responsive">
            <table class="hybrid-table ep-list">
              <thead><tr><th>Titel</th><th>Typ</th><th style="width:110px">Status</th><th style="width:90px;text-align:center">Termine</th><th style="width:130px;text-align:center">Besetzt</th><th style="width:150px;text-align:right">Aktionen</th></tr></thead>
              <tbody>
              <?php if (!$plaene) { echo msv_empty_row(6, 'Keine Einsatzpläne für ' . $selected_year . ' gefunden', 'bi-inbox'); } ?>
              <?php foreach ($plaene as $p): $offen = (int)$p['anz_slots'] - (int)$p['anz_besetzt']; ?>
                <tr class="hybrid-row" onclick="if (!event.target.closest('button, a')) location.href='einsatzplanung.php?id=<?= (int)$p['id'] ?>'" style="cursor:pointer">
                  <td class="fw-semibold"><?= $h($p['titel']) ?></td>
                  <td><?= $h(EP_TYPEN[$p['typ']] ?? $p['typ']) ?> <small class="text-muted">· <?= $h(EP_LAYOUTS[$p['layout']] ?? '') ?></small></td>
                  <td><span class="badge ep-status <?= $h($p['status']) ?>"><?= $h(EP_STATUS[$p['status']] ?? $p['status']) ?></span></td>
                  <td class="text-center"><?= (int)$p['anz_termine'] ?></td>
                  <td class="text-center"><?= (int)$p['anz_besetzt'] ?> / <?= (int)$p['anz_slots'] ?><?php if ($offen > 0 && $p['layout'] === 'funktion_x_termin'): ?> <span class="badge bg-warning text-dark" data-tooltip="offene Positionen"><?= $offen ?></span><?php endif; ?></td>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-outline-primary btn-sm" href="einsatzplanung.php?id=<?= (int)$p['id'] ?>" data-tooltip="Bearbeiten"><i class="bi bi-pencil"></i></a>
                    <button type="button" class="btn btn-outline-info btn-sm js-export" data-plan="<?= (int)$p['id'] ?>" data-fmt="docx" data-tooltip="Word"><i class="bi bi-file-earmark-word"></i></button>
                    <button type="button" class="btn btn-outline-info btn-sm js-export" data-plan="<?= (int)$p['id'] ?>" data-fmt="pdf" data-tooltip="PDF (Konvertierung, dauert einige Sekunden)"><i class="bi bi-file-earmark-pdf"></i></button>
                    <button type="button" class="btn btn-outline-info btn-sm msv-druck" data-druck-doctype="einsatzplan" data-druck-label="Einsatzplan" data-plan="<?= (int)$p['id'] ?>" data-typ="<?= $h($p['typ']) ?>" data-titel="<?= $h($p['titel']) ?>" data-tooltip="Direkt drucken (Druckprofil «Einsatzplan»)"><i class="bi bi-printer"></i></button>
                    <button type="button" class="btn btn-outline-danger btn-sm js-plan-delete" data-plan="<?= (int)$p['id'] ?>" data-titel="<?= $h($p['titel']) ?>" data-tooltip="Löschen"><i class="bi bi-trash"></i></button>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php elseif ($ansichtAbr):
          // ================= ANSICHT ABRECHNUNG (Schlossturm) =================
          include __DIR__ . '/helferabrechnung/ansicht.inc.php';
        else:
          // ================= PLAN-EDITOR =================
          $terminIndex = []; foreach ($plan['termine'] as $t) $terminIndex[(int)$t['id']] = $t;
        ?>
        <div class="content-background">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
              <h5 class="mb-1"><?php if (!empty($plan['farbe'])): ?><span class="d-inline-block rounded me-2" style="width:14px;height:14px;background:<?= $h($plan['farbe']) ?>;vertical-align:-2px" data-tooltip="Titelfarbe im Word/PDF"></span><?php endif; ?><?= $h($plan['titel']) ?> <small class="text-muted fw-normal">· <?= $h(EP_TYPEN[$plan['typ']] ?? $plan['typ']) ?> <?= (int)$plan['jahr'] ?></small></h5>
              <div class="ep-hint"><?= count($plan['termine']) ?> Termine · <?= count($plan['funktionen']) ?> <?= $istA ? 'Funktionen' : 'Einsatztypen' ?> · <?= count(array_filter($plan['slots'], 'ep_slot_besetzt')) ?> / <?= count($plan['slots']) ?> Positionen besetzt<?php if ($plan['vorlage_plan_id']): ?> · kopiert aus Plan #<?= (int)$plan['vorlage_plan_id'] ?><?php endif; ?></div>
            </div>
            <div class="ep-toolbar">
              <!-- Gruppe: Plan-Struktur -->
              <div class="ep-tb-group"><span class="ep-tb-label">Plan</span>
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#epTermineModal"><i class="bi bi-calendar3 me-1"></i><?= $istA ? 'Termine' : 'Schichten' ?></button>
                  <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#epFunktionenModal"><i class="bi bi-list-task me-1"></i><?= $istA ? 'Funktionen' : 'Einsatztypen' ?></button>
                  <?php if ($plan['termine']): ?><button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#epSollModal" data-tooltip="<?= $istA ? 'Positionen je Termin – Abweichungen von der Standardanzahl der Funktion (z.B. Warner Sa 11, So 8)' : 'Soll-Besetzung je Schicht und Funktion' ?>"><i class="bi bi-bullseye me-1"></i><?= $istA ? 'Pos. je Termin' : 'Soll' ?></button><?php endif; ?>
                  <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#epMetaModal"><i class="bi bi-gear me-1"></i>Titel / Fusstext</button>
                  <?php if ($plan['typ'] === 'schlossturm'): ?><button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#epOkModal" data-tooltip="Wer ist OK-Mitglied? Ein Häkchen pro Person gilt für alle ihre Positionen (im Word «OK» statt «X», in der Abrechnung separat)"><i class="bi bi-award me-1"></i>OK-Mitglieder</button><?php endif; ?>
                </div>
              </div>

              <?php if ($istA && $plan['termine']): ?>
              <!-- Gruppe: Planung (Verfügbarkeiten, Einteilung, Rückmeldung der Vereine) -->
              <div class="ep-tb-group"><span class="ep-tb-label">Planung</span>
                <div class="d-flex gap-2 align-items-center">
                  <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#epVerfModal" data-tooltip="Wer kann wann in welcher Rolle (Umfrage, Excel Personalanfrage, manuell)"><i class="bi bi-person-check me-1"></i>Verfügbarkeiten <span class="badge bg-secondary" id="epVerfCount"><?= count($plan['verfuegbarkeit']) ?></span></button>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#epEinteilungModal" <?= $hatRollen ? '' : 'disabled data-tooltip="Zuerst Anfrage-Rollen bei den Funktionen setzen"' ?>><i class="bi bi-magic me-1"></i>Einteilen</button>
                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#epRueckModal" data-tooltip="Ausgefülltes Word eines Vereins einlesen"><i class="bi bi-upload me-1"></i>Rückmeldung</button>
                  </div>
                  <div class="btn-group btn-group-sm js-vorschlaege-group <?= $plan['anz_vorschlaege'] ? '' : 'd-none' ?>">
                    <button type="button" class="btn btn-outline-success js-vorschlaege" data-aktion="uebernehmen" data-tooltip="Alle Vorschläge (blau gestrichelt) als fest übernehmen"><i class="bi bi-check2-all me-1"></i>Vorschläge übernehmen (<span class="ep-vs-n"><?= (int)$plan['anz_vorschlaege'] ?></span>)</button>
                    <button type="button" class="btn btn-outline-secondary js-vorschlaege" data-aktion="verwerfen" data-tooltip="Alle Vorschläge verwerfen (Positionen werden wieder leer)"><i class="bi bi-x-lg"></i></button>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <!-- Gruppe: Ausgabe (Dropdown + Direktdruck) -->
              <div class="ep-tb-group"><span class="ep-tb-label">Ausgabe</span>
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="einsatzplan" data-druck-label="Einsatzplan" data-plan="<?= $planId ?>" data-typ="<?= $h($plan['typ']) ?>" data-titel="<?= $h($plan['titel']) ?>" data-tooltip="Einsatzliste direkt drucken (PDF, Druckprofil «Einsatzplan»)"><i class="bi bi-printer me-1"></i>Drucken</button>
                  <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download me-1"></i>Export</button>
                  <ul class="dropdown-menu dropdown-menu-sm">
                    <li><a class="dropdown-item js-export" href="#" data-plan="<?= $planId ?>" data-fmt="docx"><i class="bi bi-file-earmark-word me-2"></i>Word <small class="text-muted">· <?= $istA ? 'Funktionen × Termine' : 'Personen × Schichten' ?></small></a></li>
                    <li><a class="dropdown-item js-export" href="#" data-plan="<?= $planId ?>" data-fmt="docx" data-ansicht="<?= $istA ? 'personen' : 'funktionen' ?>"><i class="bi bi-file-earmark-word me-2"></i>Word <small class="text-muted">· <?= $istA ? 'Personen: wer arbeitet wann' : 'Funktionen: wer ist wo' ?></small></a></li>
                    <li><a class="dropdown-item js-export" href="#" data-plan="<?= $planId ?>" data-fmt="pdf"><i class="bi bi-file-earmark-pdf me-2"></i>PDF</a></li>
                    <li><a class="dropdown-item" href="#" id="epPdfAblegen"><i class="bi bi-folder-plus me-2"></i>PDF ins Portal ablegen <small class="text-muted">· Dokument für alle Mitglieder</small></a></li>
                    <?php if (!$istA): ?><li><a class="dropdown-item js-export" href="#" data-plan="<?= $planId ?>" data-fmt="xlsx"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Excel <small class="text-muted">· Personen × Schichten</small></a></li><?php endif; ?>
                    <?php if ($plan['typ'] === 'schlossturm'): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item js-export" href="#" data-plan="<?= $planId ?>" data-fmt="personalanfrage" data-verein="msv"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Personalanfrage MSV <small class="text-muted">· Excel mit unseren Verfügbarkeiten</small></a></li>
                    <li><a class="dropdown-item js-export" href="#" data-plan="<?= $planId ?>" data-fmt="personalanfrage" data-verein="msv" data-leer="1"><i class="bi bi-file-earmark me-2"></i>Personalanfrage leer <small class="text-muted">· Vorlage für die Vereine</small></a></li>
                    <?php endif; ?>
                  </ul>
                </div>
              </div>

              <?php if ($plan['termine']): ?>
              <!-- Gruppe: Anwesenheit (alle Layouts) -->
              <div class="ep-tb-group"><span class="ep-tb-label">Anwesenheit</span>
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#epAnwModal" data-tooltip="Wer war da, wer nicht – pro Schicht erfassen"><i class="bi bi-person-check me-1"></i>Erfassen</button>
                  <button type="button" class="btn btn-outline-info js-auswertung" data-plan="<?= $planId ?>" data-tooltip="Auswertung dieses Plans"><i class="bi bi-clipboard-data me-1"></i>Auswertung</button>
                  <a class="btn btn-outline-secondary" href="../portal/einsatz_anwesenheit.php?id=<?= $planId ?>" target="_blank" data-tooltip="Mobile Erfassung im Portal (fürs Handy)"><i class="bi bi-phone"></i></a>
                </div>
              </div>
              <?php endif; ?>

              <!-- Gruppe: Status (rechts) -->
              <div class="ep-tb-group ms-lg-auto"><span class="ep-tb-label">Status</span>
                <div class="btn-group btn-group-sm">
                  <?php if ($plan['status'] === 'entwurf'): ?>
                    <button type="button" class="btn btn-outline-primary js-publish" data-status="freigegeben"><i class="bi bi-send-check me-1"></i>Freigeben</button>
                  <?php elseif ($plan['status'] === 'freigegeben'): ?>
                    <button type="button" class="btn btn-outline-primary js-publish" data-status="final"><i class="bi bi-check2-all me-1"></i>Final setzen</button>
                    <button type="button" class="btn btn-outline-secondary js-publish" data-status="entwurf" data-tooltip="Aus dem Portal zurückziehen"><i class="bi bi-eye-slash"></i></button>
                  <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary js-publish" data-status="freigegeben" data-tooltip="Zurück auf «Freigegeben»"><i class="bi bi-arrow-counterclockwise"></i></button>
                  <?php endif; ?>
                  <button type="button" class="btn btn-outline-danger js-plan-delete" data-plan="<?= $planId ?>" data-titel="<?= $h($plan['titel']) ?>" data-tooltip="Plan löschen"><i class="bi bi-trash"></i></button>
                </div>
              </div>
            </div>
          </div>

          <?php
            // Mitgliederliste rechts (beide Layouts): Zähler = Positionen des Mitglieds in diesem Plan
            $planZaehler = [];
            foreach ($plan['slots'] as $s) if (($s['verein'] ?? 'msv') === 'msv' && !empty($s['mitglied_id'])) $planZaehler[(int)$s['mitglied_id']] = ($planZaehler[(int)$s['mitglied_id']] ?? 0) + 1;
            ob_start(); ?>
          <aside class="ep-side" id="epSide">
            <div class="ep-side-head"><i class="bi bi-people me-1"></i>Mitglieder <span class="text-muted fw-normal text-lowercase">· auf Position ziehen</span></div>
            <div class="ep-side-remove-hint"><i class="bi bi-eraser me-1"></i>Hier ablegen, um den Einsatz zu entfernen</div>
            <div class="ep-side-search"><input type="text" class="form-control form-control-sm" id="epSideFilter" placeholder="Suchen…" autocomplete="off">
              <?php if ($istA && $plan['verfuegbarkeit']): ?>
              <select class="form-select form-select-sm mt-1" id="epVerfFilter" data-tooltip="Nicht gemeldete Personen ausgrauen">
                <option value="">Verfügbarkeit: alle anzeigen</option>
                <?php foreach ($plan['termine'] as $t): ?><option value="<?= (int)$t['id'] ?>">verfügbar <?= $h(ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t)) ?></option><?php endforeach; ?>
                <option value="alle">irgendwann verfügbar</option>
              </select>
              <?php endif; ?>
            </div>
            <div class="ep-side-list" id="epSideList">
              <?php
                $mItem = function (array $m, int $anz, bool $aktiv) use ($h): string {
                    $mid = (int)$m['ID'];
                    return '<div class="ep-mitglied' . ($anz > 0 ? ' geplant' : '') . ($aktiv ? '' : ' inaktiv') . '" draggable="true" data-mid="' . $mid . '" data-name="' . $h(ep_name_vorname($m)) . '" data-nachname="' . $h($m['Name']) . '" data-vorname="' . $h($m['Vorname']) . '" data-tooltip="' . ($anz > 0 ? $anz . ' Einsatz/Einsätze in diesem Plan' : 'noch nicht eingeplant') . '">'
                         . '<span class="ep-m-name">' . $h(ep_name_vorname($m)) . '</span><span class="ep-m-count">' . $anz . '</span></div>';
                };
                $offen = ''; $nOffen = 0; $geplant = ''; $nGeplant = 0;
                foreach ($mitglieder as $m) {
                    $mid = (int)$m['ID']; $anz = $planZaehler[$mid] ?? 0;
                    $aktiv = (int)$m['Status'] === 1 && (int)$m['Verstorben'] === 0;
                    if (!$aktiv && $anz === 0) continue;   // Inaktive nur zeigen, wenn bereits eingeplant
                    if ($anz > 0) { $geplant .= $mItem($m, $anz, $aktiv); $nGeplant++; } else { $offen .= $mItem($m, 0, $aktiv); $nOffen++; }
                }
                $externe = ep_externe_im_plan($plan);
              ?>
              <div class="ep-side-group ep-collapsed" data-gruppe="offen">
                <div class="ep-side-sub"><i class="bi bi-chevron-down ep-caret"></i>Nicht eingeplant <span class="ep-g-n"><?= $nOffen ?></span></div>
                <div class="ep-side-items" id="epGruppeOffen"><?= $offen ?></div>
              </div>
              <div class="ep-side-group ep-collapsed" data-gruppe="geplant">
                <div class="ep-side-sub"><i class="bi bi-chevron-down ep-caret"></i>Eingeplant <span class="ep-g-n"><?= $nGeplant ?></span></div>
                <div class="ep-side-items" id="epGruppeGeplant"><?= $geplant ?></div>
              </div>
              <div class="ep-side-group ep-collapsed" data-gruppe="extern">
                <div class="ep-side-sub"><i class="bi bi-chevron-down ep-caret"></i>Externe <span class="fw-normal text-lowercase">· ohne Mitgliedschaft</span> <span class="ep-g-n"><?= count($externe) ?></span></div>
                <div class="ep-side-items" id="epExterne">
              <?php foreach ($externe as $name => $anz): [$nn, $vn] = ep_name_split($name); ?>
              <div class="ep-mitglied extern geplant" draggable="true" data-mid="0" data-name="<?= $h($name) ?>" data-nachname="<?= $h($nn) ?>" data-vorname="<?= $h($vn) ?>" data-tooltip="<?= $anz ?> Einsatz/Einsätze in diesem Plan">
                <span class="ep-m-name"><?= $h($name) ?></span>
                <span class="ep-m-count"><?= $anz ?></span>
              </div>
              <?php endforeach; ?>
                </div>
              </div>
              <div class="input-group input-group-sm mt-1">
                <input type="text" class="form-control" id="epExternNeu" placeholder="Name Vorname" maxlength="100" autocomplete="off" data-tooltip="Eigene Person ohne Mitgliedschaft erfassen (Enter)">
                <button type="button" class="btn btn-outline-success" id="epExternAdd" data-tooltip="Externe Person zur Liste hinzufügen"><i class="bi bi-plus-lg"></i></button>
              </div>
            </div>
            <?php if ($istA): ?>
            <div class="ep-side-stunden" id="epStunden" data-tooltip="Feste Positionen und Helferstunden (Pauschale bzw. Schichtdauer × Positionen) je Verein, ohne Anwesenheit/OK; Vorschläge separat in Klammern. Abrechnung: Menü «Helferabrechnung»">
              <div class="ep-side-sub" style="border-top:1px solid #e2e8f0;cursor:default"><i class="bi bi-clock-history"></i>Helferstunden je Verein</div>
              <table class="ep-stunden-table"><tbody>
                <?php foreach (EP_VEREINE as $vk => $vl): ?><tr data-verein="<?= $vk ?>"><td><?= $h($vl) ?></td><td class="ep-st-pos">–</td><td class="ep-st-std">–</td><td class="ep-st-vs text-muted"></td></tr><?php endforeach; ?>
                <tr class="ep-st-total"><td>Total</td><td class="ep-st-pos">–</td><td class="ep-st-std">–</td><td class="ep-st-vs text-muted"></td></tr>
              </tbody></table>
            </div>
            <?php endif; ?>
            <div class="ep-side-foot"><span class="badge rounded-pill" style="background:#dcfce7;color:#15803d">n</span> = eingeplant (Anzahl Einsätze) · Position auf die Liste ziehen = entfernen, auf eine andere Position = verschieben</div>
          </aside>
          <?php $sideHtml = ob_get_clean(); ?>

          <?php if (!$plan['termine']): ?>
            <div class="alert alert-info mt-3 mb-0"><i class="bi bi-info-circle me-2"></i>Noch keine <?= $istA ? 'Termine' : 'Schichten' ?> – über «<?= $istA ? 'Termine' : 'Schichten' ?>» anlegen<?php if ($istA && in_array($plan['typ'], ['obligatorisch', 'feldschiessen'], true)): ?> oder aus der JM-Definition übernehmen<?php endif; ?>.</div>
          <?php else: ?>

          <!-- Raster Funktionen × Termine + Mitgliederliste (Drag & Drop) – beide Layouts.
               Layout A: feste Positionen (anzahl pro Termin). Layout B (Chilbi): Positionen entstehen
               dynamisch, «offen»-Platzhalter gemäss Soll, «+»-Chip zum Hinzufügen. -->
          <?php $eng = count($plan['termine']) > 4 ? ' ep-eng' : ''; ?>
          <div class="ep-editor-row mt-3<?= $eng ?>">
          <div class="ep-grid-col">
          <div class="ep-grid-wrap">
            <table class="ep-grid<?= $eng ?>" id="epGrid">
              <thead>
                <tr>
                  <th class="ep-fn">Funktion <span class="ep-alle" id="epAlleToggle" data-tooltip="Alle Funktionszeilen aufklappen"><i class="bi bi-arrows-expand"></i></span></th>
                  <?php foreach ($plan['termine'] as $t): ?>
                    <th data-termin="<?= (int)$t['id'] ?>">
                      <?php if (trim((string)$t['bezeichnung']) !== ''): ?><div class="ep-t-bez"><?= $h($t['bezeichnung']) ?></div><?php endif; ?>
                      <div class="ep-t-datum"><?= $h($eng ? ep_datum_kurz($t['datum']) : ep_datum_lang($t['datum'])) ?></div>
                      <div class="ep-t-zeit"><?= $h(ep_zeit_text($t)) ?></div>
                      <?php if (trim((string)($t['info'] ?? '')) !== ''): ?><div class="ep-t-info"><?= $h($t['info']) ?></div><?php endif; ?>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
              <?php foreach (ep_funktionen_gruppiert($plan['funktionen']) as $g): ?>
                <?php if ($g['gruppe'] !== ''): ?><tr class="ep-gruppe"><td colspan="<?= count($plan['termine']) + 1 ?>"><?= $h($g['gruppe']) ?></td></tr><?php endif; ?>
                <?php foreach ($g['funktionen'] as $f): $anz = max(1, (int)$f['anzahl']); $anzMax = $istA ? ep_anzahl_max($plan, $f) : $anz;
                    $proTermin = $istA ? array_map(fn($t) => ep_anzahl_pos($plan, (int)$t['id'], $f), $plan['termine']) : [];
                    $variabel = $istA && count(array_unique($proTermin)) > 1;
                    $flabelBase = (trim((string)$f['gruppe']) !== '' ? $f['gruppe'] . ': ' : '') . $f['bezeichnung']; ?>
                <?php $fnTotal = 0; foreach ($plan['termine'] as $t) foreach ($plan['zellen'][$t['id'] . '|' . $f['id']] ?? [] as $s) if (ep_slot_besetzt($s)) $fnTotal++; ?>
                <tr data-fid="<?= (int)$f['id'] ?>" data-okfn="<?= ($f['rolle'] ?? '') === 'OK' ? 1 : 0 ?>" class="ep-collapsed">
                  <td class="ep-fn" data-tooltip="Ein-/ausklappen"><i class="bi bi-chevron-down ep-caret"></i><?= $h($f['bezeichnung']) ?> <span class="ep-fn-n" data-tooltip="eingeteilte Personen über alle Termine"><?= $fnTotal ?></span><?php if ($istA): ?><small><?= $variabel ? 'je Termin ' . $h(implode(' / ', $proTermin)) : $anz . ' pro Termin' ?></small><?php endif; ?></td>
                  <?php foreach ($plan['termine'] as $t): $key = $t['id'] . '|' . $f['id'];
                      $tlabel = (trim((string)$t['bezeichnung']) !== '' ? $t['bezeichnung'] . ' · ' : '') . ($istA ? ep_datum_lang($t['datum']) : ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t));
                      $anzT = $istA ? ep_anzahl_pos($plan, (int)$t['id'], $f) : 0;
                      if ($istA) { $zellSlots = []; for ($pos = 1; $pos <= $anzT; $pos++) $zellSlots[] = $plan['slot_index'][$key . '|' . $pos] ?? null; }
                      else { $zellSlots = array_values(array_filter($plan['zellen'][$key] ?? [], 'ep_slot_besetzt')); }
                      $soll = $istA ? $anzT : (int)($plan['soll'][$key] ?? 0);
                  ?>
                    <td data-termin="<?= (int)$t['id'] ?>" data-funktion="<?= (int)$f['id'] ?>" data-soll="<?= $soll ?>" data-flabel="<?= $h($flabelBase) ?>" data-tlabel="<?= $h($tlabel) ?>">
                    <?php foreach ($zellSlots as $i => $s): $pos = $i + 1;
                        if (!$s) { echo '<div class="ep-chip ep-offen"><span class="ep-dot"></span><span class="ep-txt">–</span></div>'; continue; }
                        $besetzt = ep_slot_besetzt($s);
                        $text = ep_slot_text($s, $mitglieder);
                        $warn = '';
                        if (!empty($s['mitglied_id']) && isset($mitglieder[(int)$s['mitglied_id']])) {
                            $mm = $mitglieder[(int)$s['mitglied_id']];
                            if ((int)$mm['Verstorben'] === 1) $warn = 'verstorben'; elseif ((int)$mm['Status'] !== 1) $warn = 'inaktiv';
                        }
                        $cls = 'ep-chip ep-v-' . $h($s['verein']) . ($besetzt ? ' ep-besetzt' : ' ep-offen') . ($warn ? ' ep-warn' : '') . ((int)($s['vorschlag'] ?? 0) === 1 ? ' ep-vorschlag' : '');
                        $flabel = $flabelBase . ($istA && $anzMax > 1 ? ' (' . $pos . ')' : '');
                    ?>
                      <button type="button" class="<?= $cls ?>" data-slot="<?= (int)$s['id'] ?>" data-verein="<?= $h($s['verein']) ?>" data-mid="<?= (int)$s['mitglied_id'] ?>" data-vorschlag="<?= (int)($s['vorschlag'] ?? 0) ?>"
                              data-name="<?= $h($s['name_text']) ?>" data-bem="<?= $h($s['bemerkung']) ?>" data-warn="<?= $warn ?>" data-ok="<?= ep_slot_ist_ok($s, $f) ? 1 : 0 ?>" data-okslot="<?= (int)($s['ok'] ?? 0) ?>" data-okfn="<?= ($f['rolle'] ?? '') === 'OK' ? 1 : 0 ?>"
                              data-funktion="<?= $h($flabel) ?>" data-termin="<?= $h($tlabel) ?>">
                        <span class="ep-dot"></span><span class="ep-txt"<?= $eng && $text !== '' ? ' data-tooltip="' . $h($text) . '"' : '' ?>><?= $text !== '' ? $h($text) : 'offen' ?></span>
                        <?php if ($besetzt && ep_slot_ist_ok($s, $f)): ?><span class="ep-ok" data-tooltip="<?= ($f['rolle'] ?? '') === 'OK' ? 'OK über die Funktion (Rolle «OK»)' : 'OK-Mitglied' ?>">OK</span><?php endif; ?>
                        <?php if (trim((string)$s['bemerkung']) !== ''): ?><i class="bi bi-chat-left-text ep-bem" data-tooltip="<?= $h($s['bemerkung']) ?>"></i><?php endif; ?>
                        <?php if ($s['anwesend'] !== null): ?><i class="bi <?= (int)$s['anwesend'] === 1 ? 'bi-check-circle-fill ep-anw ep-anw-da' : 'bi-x-circle-fill ep-anw ep-anw-nein' ?>" data-tooltip="<?= (int)$s['anwesend'] === 1 ? 'anwesend' : 'nicht erschienen' ?>"></i><?php endif; ?>
                      </button>
                    <?php endforeach; ?>
                    <?php if (!$istA): for ($i = count($zellSlots); $i < $soll; $i++): ?>
                      <div class="ep-chip ep-offen ep-ph" data-tooltip="Soll noch nicht erreicht – Person hierher ziehen"><span class="ep-dot"></span><span class="ep-txt">offen</span></div>
                    <?php endfor; ?>
                      <button type="button" class="ep-chip ep-add" data-tooltip="Person hinzufügen (oder aus der Liste hierher ziehen)"><span class="ep-txt"><i class="bi bi-plus-lg"></i></span></button>
                    <?php endif; ?>
                    <?php $namen = array_map(fn($s) => ep_slot_text($s, $mitglieder), array_filter($zellSlots, fn($s) => $s && ep_slot_besetzt($s))); $nBes = count($namen); $nOffen = max(0, $soll - $nBes); ?>
                      <div class="ep-chip ep-summary<?= $nBes === 0 ? ' ep-leer' : '' ?>" data-tooltip="<?= $h($namen ? implode(', ', $namen) : 'niemand eingeteilt') ?>"><span class="ep-txt"><?= $nBes ?> <?= $nBes === 1 ? 'Person' : 'Personen' ?></span><?php if ($nOffen > 0): ?><span class="ep-offen-n"><?= $nOffen ?> offen</span><?php endif; ?></div>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
              <?php if (!$plan['funktionen']) echo '<tr><td colspan="' . (count($plan['termine']) + 1) . '" class="text-center text-muted py-3">Noch keine Funktionen – über «Funktionen» anlegen.</td></tr>'; ?>
              </tbody>
            </table>
          </div>
          <div class="ep-legend mt-2">
            <span><span class="ep-dot" style="background:#c62828"></span>MSV Wilen</span>
            <span><span class="ep-dot" style="background:#3b5998"></span>SV Freienbach</span>
            <span><span class="ep-dot" style="background:#2e7d32"></span>SV Wollerau</span>
            <span><span class="ep-dot" style="background:#f0ad4e"></span>Mitglied inaktiv / verstorben – prüfen</span>
            <span><span class="ep-dot" style="background:#fca5a5"></span>offen (noch niemand eingeteilt)</span>
          </div>
          </div><!-- /ep-grid-col -->
          <?= $sideHtml ?>
          </div><!-- /ep-editor-row -->

          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($plan && !$ansichtAbr):
  // ---------- Slide-Panel: Position bearbeiten (beide Layouts; Verein nur bei Obli/Feld) ----------
  $panel_id = 'epSlotPanel'; $panel_class = 'hybrid-edit-panel'; $panel_overlay_id = 'epSlotOverlay'; $panel_close_id = 'epSlotClose';
  $panel_title = '<i class="bi bi-person-badge me-2"></i><span id="epSlotTitle">Position</span>';
  ob_start(); ?>
    <div class="panel-section"><i class="bi bi-calendar-event me-1"></i><span id="epSlotTermin"></span></div>
    <div class="mb-3<?= $istA ? '' : ' d-none' ?>">
      <label class="panel-label d-block">Verein</label>
      <div class="btn-group btn-group-sm w-100" role="group" id="epVerein">
        <?php foreach (EP_VEREINE as $vk => $vl): ?>
        <input type="radio" class="btn-check" name="epVerein" id="epVerein_<?= $vk ?>" value="<?= $vk ?>"><label class="btn btn-outline-secondary" for="epVerein_<?= $vk ?>"><?= $h($vl) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="mb-3" id="epMitgliedBlock">
      <label class="panel-label" for="epMitglied">Mitglied</label>
      <input type="text" class="form-control form-control-sm" id="epMitgliedFilter" placeholder="Filtern…" autocomplete="off">
      <select class="form-select form-select-sm" id="epMitglied" size="8">
        <option value="0">– kein Mitglied –</option>
        <optgroup label="Aktive Mitglieder"><?= $optAktiv ?></optgroup>
        <?php if ($optInaktiv): ?><optgroup label="Inaktive"><?= $optInaktiv ?></optgroup><?php endif; ?>
      </select>
      <div class="ep-hint mt-1 ep-verf-badge" id="epVerfBadge"></div>
    </div>
    <div class="mb-3">
      <label class="panel-label" for="epNameText"><span id="epNameTextLabel">Name (extern, ohne Mitgliedschaft)</span></label>
      <input type="text" class="form-control form-control-sm" id="epNameText" maxlength="100" placeholder="Name Vorname">
      <div class="ep-hint mt-1" id="epNameTextHint">Leer lassen, wenn der Verein den Namen erst später meldet – im Word erscheint dann der Vereinsname.</div>
    </div>
    <div class="mb-3">
      <label class="panel-label" for="epBemerkung">Bemerkung</label>
      <input type="text" class="form-control form-control-sm" id="epBemerkung" maxlength="100">
    </div>
    <?php if ($plan['typ'] === 'schlossturm'): ?>
    <div class="form-check form-switch mb-3" data-tooltip="Im Original-Einsatzplan «OK» statt «x»; zählt in der Helferabrechnung nur mit dem Schalter «OK-Einsätze mitzählen»">
      <input class="form-check-input" type="checkbox" role="switch" id="epOk">
      <label class="form-check-label" for="epOk">OK-Mitglied (Organisationskomitee)</label>
      <div class="ep-hint" id="epOkFn" style="display:none">Diese Funktion hat die Rolle «OK» – alle ihre Positionen zählen als OK (Dialog «Funktionen»).</div>
    </div>
    <?php endif; ?>
    <div class="d-flex gap-2 flex-wrap">
      <button type="button" class="btn btn-outline-success btn-sm d-none" id="epSlotVorschlagOk" data-tooltip="Diesen Einteilungs-Vorschlag als fest übernehmen"><i class="bi bi-check2 me-1"></i>Vorschlag übernehmen</button>
      <button type="button" class="btn btn-outline-<?= $istA ? 'secondary' : 'danger' ?> btn-sm" id="epSlotLeeren"><i class="bi bi-<?= $istA ? 'eraser' : 'trash' ?> me-1"></i><?= $istA ? 'Leeren' : 'Position entfernen' ?></button>
      <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" id="epSlotFertig">Fertig</button>
    </div>
  <?php $panel_body = ob_get_clean(); include 'partials/side_panel.inc.php'; ?>
<?php endif; ?>

<?php if ($plan && !$ansichtAbr && $plan['termine']): ?>
<?php if ($plan['typ'] === 'schlossturm'):
  // OK-Mitglieder: eine Zeile je Person im Plan (fest besetzte Positionen), Häkchen gilt für alle ihre Positionen
  $okPersonen = [];
  $okStamm = ep_ok_stamm_laden($db);
  $fById = []; foreach ($plan['funktionen'] as $f) $fById[(int)$f['id']] = $f;
  foreach ($plan['slots'] as $s) {
      if (!ep_slot_fix($s)) continue;
      $k = ep_person_key((int)$s['mitglied_id'], (string)$s['name_text']);
      $okPersonen[$k] ??= ['name' => ep_slot_text($s, $mitglieder), 'verein' => $s['verein'] ?? 'msv', 'mid' => (int)$s['mitglied_id'], 'name_text' => (string)$s['name_text'], 'n' => 0, 'ok' => 0, 'fn' => 0,
                           'stamm' => ep_ok_stamm_hat($okStamm, (int)$s['mitglied_id'], (string)$s['name_text'])];
      $okPersonen[$k]['n']++;
      $okPersonen[$k]['vereine'][$s['verein'] ?? 'msv'] = true;
      if ((int)($s['ok'] ?? 0) === 1) $okPersonen[$k]['ok']++;
      if (($fById[(int)$s['funktion_id']]['rolle'] ?? '') === 'OK') $okPersonen[$k]['fn']++;
  }
  $vOrder = array_flip(array_keys(EP_VEREINE));
  uasort($okPersonen, fn($a, $b) => [$vOrder[$a['verein']] ?? 9, $a['name']] <=> [$vOrder[$b['verein']] ?? 9, $b['name']]);
?>
<!-- ================= MODAL: OK-Mitglieder (Schlossturm) ================= -->
<div class="modal fade" id="epOkModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-award me-2"></i>OK-Mitglieder – wer gehört zum Organisationskomitee?</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
      <input type="text" class="form-control form-control-sm" id="epOkFilter" placeholder="Name filtern…" style="max-width:220px" autocomplete="off">
      <span class="text-muted small ms-auto"><b id="epOkAnzahl"><?= count(array_filter($okPersonen, fn($p) => $p['ok'] > 0 || $p['fn'] >= $p['n'])) ?></b> von <?= count($okPersonen) ?> Personen als OK markiert</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm ep-ok-liste mb-0">
        <thead><tr><th>Name</th><th>Verein</th><th class="text-center">Positionen</th><th class="text-center" style="width:110px" data-tooltip="OK in diesem Plan (alle Positionen der Person)">OK im Plan</th><th class="text-center" style="width:110px" data-tooltip="Stammliste: gilt automatisch in jedem künftigen Plan (Import, Kopie, Einteilen)">dauerhaft</th></tr></thead>
        <tbody>
        <?php if (!$okPersonen) echo msv_empty_row(5, 'Noch keine besetzten Positionen'); ?>
        <?php foreach ($okPersonen as $p): $nurFn = $p['fn'] >= $p['n']; ?>
          <tr data-suche="<?= $h(mb_strtolower($p['name'])) ?>">
            <td class="fw-semibold"><?= $h($p['name']) ?><?php if ($p['fn'] > 0): ?> <span class="badge bg-light text-dark border" data-tooltip="<?= $p['fn'] ?> Position(en) in Funktionen mit Rolle «OK» – zählen immer als OK">Funktion OK<?= $nurFn ? '' : ' ' . $p['fn'] . '/' . $p['n'] ?></span><?php endif; ?></td>
            <td><span class="ep-dot d-inline-block me-1" style="width:8px;height:8px;border-radius:50%;background:<?= $p['verein'] === 'msv' ? '#c62828' : ($p['verein'] === 'freienbach' ? '#3b5998' : '#2e7d32') ?>"></span><?= $h(EP_VEREINE[$p['verein']] ?? $p['verein']) ?><?php if (count($p['vereine']) > 1): ?> <span class="badge bg-danger" data-tooltip="Diese Person ist in diesem Plan bei mehreren Vereinen eingetragen (<?= $h(implode(' / ', array_map(fn($k) => EP_VEREINE[$k] ?? $k, array_keys($p['vereine'])))) ?>) – im Raster korrigieren"><?= count($p['vereine']) ?> Vereine</span><?php endif; ?></td>
            <td class="text-center"><?= (int)$p['n'] ?></td>
            <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input ep-ok-toggle" type="checkbox" role="switch" data-mid="<?= $p['mid'] ?>" data-name="<?= $h($p['name_text']) ?>" <?= $p['ok'] > 0 || $nurFn ? 'checked' : '' ?> <?= $nurFn ? 'disabled' : '' ?>></div></td>
            <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input ep-ok-stamm" type="checkbox" role="switch" data-mid="<?= $p['mid'] ?>" data-name="<?= $h($p['name_text']) ?>" <?= $p['stamm'] ? 'checked' : '' ?>></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="ep-hint mt-2"><b>OK im Plan</b> setzt das Kennzeichen auf allen Positionen der Person in diesem Plan – wie im Original-Einsatzplan, wo bei OK-Mitgliedern «OK» statt «x» steht. <b>Dauerhaft</b> merkt die Person in der Stammliste: in jedem künftigen Plan (Import, Kopie, Einteilen) ist sie automatisch OK; Ausschalten entfernt nur den Stammeintrag. Im Word wird «OK» gedruckt; in der Abrechnung zählen OK-Positionen nur mit dem Schalter «OK-Einsätze mitzählen».</div>

    <?php $okFnListe = ep_ok_funktionen_laden($db); $slotsProFn = []; foreach ($plan['slots'] as $s) if (ep_slot_fix($s)) $slotsProFn[(int)$s['funktion_id']] = ($slotsProFn[(int)$s['funktion_id']] ?? 0) + 1; ?>
    <h6 class="small text-uppercase text-muted mt-4 mb-1"><i class="bi bi-list-task me-1"></i>Funktionen, die immer vom OK besetzt sind</h6>
    <div class="table-responsive">
      <table class="table table-sm ep-ok-liste mb-0" id="epOkFunktionen">
        <thead><tr><th>Funktion</th><th class="text-center">besetzte Positionen</th><th class="text-center" style="width:110px" data-tooltip="Definition für alle Jahre: alle Positionen dieser Funktion zählen als OK (Rolle «OK» wird gesetzt)">immer OK</th></tr></thead>
        <tbody>
        <?php foreach ($plan['funktionen'] as $f): $inListe = in_array(ep_funktion_norm((string)$f['bezeichnung']), $okFnListe, true); ?>
          <tr data-fid="<?= (int)$f['id'] ?>">
            <td class="fw-semibold"><?= $h(trim(($f['gruppe'] ? $f['gruppe'] . ': ' : '') . $f['bezeichnung'])) ?><?php if (($f['rolle'] ?? '') === 'OK' && !$inListe): ?> <span class="badge bg-light text-dark border" data-tooltip="Rolle «OK» nur in diesem Plan (Dialog «Funktionen»)">nur dieser Plan</span><?php endif; ?></td>
            <td class="text-center"><?= (int)($slotsProFn[(int)$f['id']] ?? 0) ?></td>
            <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input ep-ok-fn" type="checkbox" role="switch" data-fid="<?= (int)$f['id'] ?>" <?= $inListe ? 'checked' : '' ?>></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="ep-hint mt-2">Die Definition gilt für alle Jahre (z.B. EDV / Anlage, Schiessleitung) und wird beim Import, beim Anlegen und Kopieren eines Plans angewendet – hier sofort auf diesen Plan. Personen in solchen Funktionen brauchen kein eigenes Häkchen.</div>
  </div>
  <div class="modal-footer py-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button></div>
</div></div></div>
<?php endif; ?>

<!-- ================= MODAL: Anwesenheit erfassen (Desktop, alle Layouts) ================= -->
<div class="modal fade" id="epAnwModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-person-check me-2"></i>Anwesenheit erfassen – wer war da, wer nicht</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="an-termine" id="epAnwTermine">
      <?php foreach ($plan['termine'] as $t): ?><button type="button" data-termin="<?= (int)$t['id'] ?>" class="<?= $t['datum'] === date('Y-m-d') ? 'aktiv' : '' ?>"><?= $h(ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t)) ?><?= $t['datum'] === date('Y-m-d') ? ' · heute' : '' ?></button><?php endforeach; ?>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
      <div class="an-stat mb-0"><span class="da"><i class="bi bi-check-lg"></i> <b id="anDa">0</b> da</span><span class="nein"><i class="bi bi-x-lg"></i> <b id="anNein">0</b> nicht da</span><span><b id="anOffen">0</b> offen</span></div>
      <button type="button" class="btn btn-outline-success btn-sm ms-auto" id="anAlleDa"><i class="bi bi-check2-all me-1"></i>Alle offenen als «da»</button>
    </div>
    <div class="an-grid" id="epAnwListe"></div>
    <div class="ep-hint mt-2">Erneutes Antippen der aktiven Taste setzt auf «nicht erfasst» zurück. Nur feste Positionen mit Person; Vorschläge und leere Positionen erscheinen nicht. Dieselbe Erfassung gibt es im Portal fürs Handy.</div>
  </div>
  <div class="modal-footer py-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button></div>
</div></div></div>
<?php endif; ?>

<!-- ================= MODAL: Auswertung Anwesenheit ================= -->
<div class="modal fade" id="epAuswModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-clipboard-data me-2"></i><span id="epAuswTitel">Auswertung Anwesenheit</span></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="epAuswBody"><div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Lade…</div></div>
  <div class="modal-footer py-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button></div>
</div></div></div>

<!-- ================= MODAL: Neuer Plan ================= -->
<div class="modal fade" id="epNeuModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Neuer Einsatzplan</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2 mb-3">
      <div class="col-7"><label class="form-label small mb-1">Typ</label>
        <select class="form-select form-select-sm" id="epNeuTyp"><?php foreach (EP_TYPEN as $k => $v): ?><option value="<?= $k ?>"><?= $h($v) ?></option><?php endforeach; ?></select></div>
      <div class="col-5"><label class="form-label small mb-1">Jahr</label>
        <select class="form-select form-select-sm" id="epNeuJahr"><?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === (int)date('Y') + 1 ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><label class="form-label small mb-1">Titel</label><input type="text" class="form-control form-control-sm" id="epNeuTitel" maxlength="100"></div>
    </div>
    <label class="form-label small mb-1">Quelle</label>
    <div class="form-check"><input class="form-check-input" type="radio" name="epNeuQuelle" id="epQuelleLeer" value="leer" checked><label class="form-check-label" for="epQuelleLeer">Leer, ohne Personen</label></div>
    <div class="form-check ms-4" id="epStrukturBlock"><input class="form-check-input" type="checkbox" id="epNeuStruktur" checked><label class="form-check-label small" for="epNeuStruktur">Stammdaten aus <strong id="epStrukturVon">–</strong> übernehmen: Funktionen mit Anzahl, Gruppe und Rolle, Titelfarbe, Fusstext, Termine auf den gleichen Wochentag verschoben (falls nicht aus der JM-Definition)</label></div>
    <div class="ep-hint ms-4 d-none" id="epStrukturHint">Noch kein Plan dieses Typs vorhanden – es werden die Standard-Funktionen angelegt.</div>
    <div class="form-check"><input class="form-check-input" type="radio" name="epNeuQuelle" id="epQuelleKopie" value="kopie" <?= $allePlaene ? '' : 'disabled' ?>><label class="form-check-label" for="epQuelleKopie">Kopie eines bestehenden Plans (Endstand nach Tausch)</label></div>
    <div class="ep-hint ms-4 d-none" id="epKopieHint">Für den gewählten Typ gibt es noch keinen Plan zum Kopieren.</div>
    <select class="form-select form-select-sm ms-4 mb-2 d-none" id="epNeuQuellePlan" style="width:calc(100% - 1.5rem)">
      <?php foreach ($allePlaene as $p): ?><option value="<?= (int)$p['id'] ?>" data-typ="<?= $h($p['typ']) ?>" data-jahr="<?= (int)$p['jahr'] ?>"><?= $h($p['titel']) ?> (<?= (int)$p['jahr'] ?>, <?= $h(EP_STATUS[$p['status']] ?? '') ?>)</option><?php endforeach; ?>
    </select>
    <div class="form-check"><input class="form-check-input" type="radio" name="epNeuQuelle" id="epQuelleDok" value="dokument" <?= $dokumente ? '' : 'disabled' ?>><label class="form-check-label" for="epQuelleDok">Aus hochgeladenem Dokument (Word Obli/Feld, Excel Chilbi)</label></div>
    <select class="form-select form-select-sm ms-4 mb-2 d-none" id="epNeuQuelleDok" style="width:calc(100% - 1.5rem)">
      <?php foreach ($dokumente as $d): ?><option value="<?= (int)$d['id'] ?>" data-jahr="<?= (int)$d['jahr'] ?>"><?= $h($d['titel']) ?> – <?= $h($d['dateiname']) ?></option><?php endforeach; ?>
    </select>
    <div class="form-check mt-2" id="epJmBlock"><input class="form-check-input" type="checkbox" id="epNeuJm" checked><label class="form-check-label" for="epNeuJm">Termine aus der JM-Definition des Jahres übernehmen (Schiesstage)</label></div>
  </div>
  <div class="modal-footer py-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
    <button type="button" class="btn btn-outline-success btn-sm" id="epNeuSpeichern"><i class="bi bi-plus-lg me-1"></i>Anlegen</button>
  </div>
</div></div></div>

<?php if ($plan && !$ansichtAbr): ?>
<!-- ================= MODAL: Termine / Schichten ================= -->
<div class="modal fade" id="epTermineModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-calendar3 me-2"></i><?= $istA ? 'Termine' : 'Schichten' ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <table class="table table-sm ep-struct-table mb-2">
      <thead><tr><th style="width:18%">Bezeichnung</th><th style="width:16%">Datum</th><th style="width:10%">von</th><th style="width:10%">bis</th><th style="width:14%">Zeit-Text</th><th style="width:22%">Info</th><th></th></tr></thead>
      <tbody id="epTermineBody">
        <?php foreach ($plan['termine'] as $t): ?>
        <tr data-id="<?= (int)$t['id'] ?>">
          <td><input type="text" class="form-control form-control-sm t-bez" value="<?= $h($t['bezeichnung']) ?>" placeholder="<?= $istA ? 'z.B. 1.Obligatorisch' : '' ?>"></td>
          <td><input type="date" class="form-control form-control-sm t-datum" value="<?= $h($t['datum']) ?>"></td>
          <td><input type="time" class="form-control form-control-sm t-von" value="<?= $h(substr((string)$t['zeit_von'], 0, 5)) ?>"></td>
          <td><input type="time" class="form-control form-control-sm t-bis" value="<?= $h(substr((string)$t['zeit_bis'], 0, 5)) ?>"></td>
          <td><input type="text" class="form-control form-control-sm t-text" value="<?= $h($t['zeit_text']) ?>" maxlength="30" placeholder="z.B. 17:00-fertig"></td>
          <td><input type="text" class="form-control form-control-sm t-info" value="<?= $h($t['info'] ?? '') ?>" maxlength="100" placeholder="z.B. Treffpunkt 07.30 Uhr · Büro 07:15 Uhr"></td>
          <td class="text-end text-nowrap"><button type="button" class="btn btn-outline-primary btn-sm js-termin-save" data-tooltip="Speichern"><i class="bi bi-check-lg"></i></button> <button type="button" class="btn btn-outline-danger btn-sm js-termin-delete" data-tooltip="Löschen (inkl. Positionen)"><i class="bi bi-trash"></i></button></td>
        </tr>
        <?php endforeach; ?>
        <tr data-id="0" class="table-light">
          <td><input type="text" class="form-control form-control-sm t-bez" placeholder="neu…"></td>
          <td><input type="date" class="form-control form-control-sm t-datum"></td>
          <td><input type="time" class="form-control form-control-sm t-von"></td>
          <td><input type="time" class="form-control form-control-sm t-bis"></td>
          <td><input type="text" class="form-control form-control-sm t-text" maxlength="30"></td>
          <td><input type="text" class="form-control form-control-sm t-info" maxlength="100"></td>
          <td class="text-end"><button type="button" class="btn btn-outline-success btn-sm js-termin-save" data-tooltip="Hinzufügen"><i class="bi bi-plus-lg"></i></button></td>
        </tr>
      </tbody>
    </table>
    <div class="ep-hint">Zeit-Text überschreibt von/bis in der Anzeige (Chilbi: «17:00-fertig»). Info erscheint klein im Spaltenkopf und im Word (Schlossturm: Treffpunkt / Büro). Änderungen an Terminen wirken sofort auf freigegebene Pläne.</div>
  </div>
  <div class="modal-footer py-2 justify-content-between">
    <?php if ($istA && in_array($plan['typ'], ['obligatorisch', 'feldschiessen'], true)): ?>
      <button type="button" class="btn btn-outline-success btn-sm" id="epTermineAusJm"><i class="bi bi-calendar-plus me-1"></i>Aus JM-Definition ergänzen</button>
    <?php else: ?><span></span><?php endif; ?>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button>
  </div>
</div></div></div>

<!-- ================= MODAL: Funktionen / Einsatztypen ================= -->
<div class="modal fade" id="epFunktionenModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-list-task me-2"></i><?= $istA ? 'Funktionen' : 'Einsatztypen' ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <table class="table table-sm ep-struct-table mb-2">
      <?php $rolleSelect = function (?string $wert) use ($h): string {
          $o = '<option value="">– keine –</option>';
          foreach (EP_ROLLEN as $k => $label) $o .= '<option value="' . $h($k) . '"' . ($wert === $k ? ' selected' : '') . '>' . $h($label) . '</option>';
          return $o;
      }; ?>
      <thead><tr><?php if ($istA): ?><th style="width:18%">Gruppe</th><?php endif; ?><th>Bezeichnung</th><?php if ($istA): ?><th style="width:11%">pro Termin</th><th style="width:18%" data-tooltip="Rolle in der Personalanfrage/Umfrage – Grundlage der automatischen Einteilung; «OK Schlossturm» = fest durch OK">Anfrage-Rolle</th><?php endif; ?><th style="width:150px"></th></tr></thead>
      <tbody id="epFunktionenBody">
        <?php foreach ($plan['funktionen'] as $f): ?>
        <tr data-id="<?= (int)$f['id'] ?>">
          <?php if ($istA): ?><td><input type="text" class="form-control form-control-sm f-gruppe" value="<?= $h($f['gruppe']) ?>" maxlength="50" placeholder="z.B. Büro"></td><?php endif; ?>
          <td><input type="text" class="form-control form-control-sm f-bez" value="<?= $h($f['bezeichnung']) ?>" maxlength="100"></td>
          <?php if ($istA): ?><td><input type="number" class="form-control form-control-sm f-anz" value="<?= (int)$f['anzahl'] ?>" min="1" max="20"></td>
          <td><select class="form-select form-select-sm f-rolle"><?= $rolleSelect($f['rolle'] ?? null) ?></select></td><?php endif; ?>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-outline-secondary btn-sm js-funktion-move" data-dir="up" data-tooltip="Nach oben"><i class="bi bi-arrow-up"></i></button>
            <button type="button" class="btn btn-outline-secondary btn-sm js-funktion-move" data-dir="down" data-tooltip="Nach unten"><i class="bi bi-arrow-down"></i></button>
            <button type="button" class="btn btn-outline-primary btn-sm js-funktion-save" data-tooltip="Speichern"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="btn btn-outline-danger btn-sm js-funktion-delete" data-tooltip="Löschen"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr data-id="0" class="table-light">
          <?php if ($istA): ?><td><input type="text" class="form-control form-control-sm f-gruppe" maxlength="50" placeholder="Gruppe"></td><?php endif; ?>
          <td><input type="text" class="form-control form-control-sm f-bez" maxlength="100" placeholder="neu…"></td>
          <?php if ($istA): ?><td><input type="number" class="form-control form-control-sm f-anz" value="1" min="1" max="20"></td>
          <td><select class="form-select form-select-sm f-rolle"><?= $rolleSelect(null) ?></select></td><?php endif; ?>
          <td class="text-end"><button type="button" class="btn btn-outline-success btn-sm js-funktion-save" data-tooltip="Hinzufügen"><i class="bi bi-plus-lg"></i></button></td>
        </tr>
      </tbody>
    </table>
    <div class="ep-hint"><?= $istA ? 'Funktionen mit gleicher Gruppe bilden im Word eine Zeile (z.B. «Büro»). «pro Termin» = Anzahl Positionen je Termin; Kürzen ist nur möglich, wenn die oberen Positionen leer sind.' : 'Einsatztypen stehen in den Zellen des Chilbi-Rasters (z.B. «Einsatz Bar»).' ?></div>
  </div>
  <div class="modal-footer py-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button></div>
</div></div></div>

<?php if ($plan['termine']): ?>
<!-- ================= MODAL: Soll / Positionen je Termin ================= -->
<div class="modal fade" id="epSollModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-bullseye me-2"></i><?= $istA ? 'Positionen je Termin' : 'Soll-Besetzung je Schicht' ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="table-responsive">
      <table class="table table-sm ep-soll-table mb-2">
        <thead><tr><th>Funktion<?php if ($istA): ?> <small class="text-muted fw-normal">(Standard)</small><?php endif; ?></th><?php foreach ($plan['termine'] as $t): ?><th class="text-center small"><?= $h(ep_datum_kurz($t['datum'])) ?><br><span class="fw-normal text-muted"><?= $h(ep_zeit_text($t)) ?></span></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($plan['funktionen'] as $f): ?>
          <tr><td class="fw-semibold align-middle"><?= $h($f['bezeichnung']) ?><?php if ($istA): ?> <span class="badge bg-light text-dark border"><?= (int)$f['anzahl'] ?></span><?php endif; ?></td>
            <?php foreach ($plan['termine'] as $t): $key = $t['id'] . '|' . $f['id']; $wert = $istA ? ep_anzahl_pos($plan, (int)$t['id'], $f) : (int)($plan['soll'][$key] ?? 0); ?>
            <td class="text-center"><input type="number" min="<?= $istA ? 1 : 0 ?>" max="99" class="form-control form-control-sm d-inline-block ep-soll<?= $istA && $wert !== (int)$f['anzahl'] ? ' border-primary' : '' ?>" data-key="<?= $key ?>" value="<?= $wert ?>" placeholder="0"></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="ep-hint"><?= $istA
        ? 'Anzahl Positionen dieser Funktion am jeweiligen Termin; blau umrandet = weicht vom Standard der Funktion ab. Kürzen geht nur, wenn die oberen Positionen leer sind. Im Word bekommt jede Funktion so viele Zeilen wie der Termin mit den meisten Positionen; Termine mit weniger Positionen bleiben dort grau.'
        : '0 = kein Soll (keine Anzeige). Ist &lt; Soll wird im Raster und im Word rot bzw. als «offen» ausgewiesen.' ?></div>
  </div>
  <div class="modal-footer py-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
    <button type="button" class="btn btn-outline-primary btn-sm" id="epSollSpeichern"><i class="bi bi-check-lg me-1"></i>Speichern</button>
  </div>
</div></div></div>
<?php endif; ?>

<!-- ================= MODAL: Titel / Fusstext ================= -->
<div class="modal fade" id="epMetaModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-gear me-2"></i>Titel / Fusstext</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label small mb-1" for="epMetaTitel">Titel</label>
    <input type="text" class="form-control form-control-sm mb-3" id="epMetaTitel" maxlength="100" value="<?= $h($plan['titel']) ?>">
    <label class="form-label small mb-1" for="epMetaFarbe">Titelfarbe im Word/PDF</label>
    <div class="d-flex align-items-center gap-2 mb-3">
      <input type="color" class="form-control form-control-color form-control-sm" id="epMetaFarbe" value="<?= $h(preg_match('/^#[0-9a-fA-F]{6}$/', (string)($plan['farbe'] ?? '')) ? $plan['farbe'] : '#D9D9D9') ?>" data-tooltip="Hintergrund der zentrierten Titelzeile; Schrift wird auf dunklen Farben automatisch weiss">
      <div class="d-flex gap-1" id="epMetaFarbePalette">
        <?php foreach (['#D9D9D9' => 'Grau (Standard)', '#2D4373' => 'Vereinsblau', '#C62828' => 'Rot', '#2E7D32' => 'Grün', '#E65100' => 'Orange', '#6A1B9A' => 'Violett', '#F9A825' => 'Gelb'] as $hex => $name): ?>
          <button type="button" class="btn btn-sm p-0 border" style="width:22px;height:22px;background:<?= $hex ?>" data-farbe="<?= $hex ?>" data-tooltip="<?= $name ?>"></button>
        <?php endforeach; ?>
      </div>
      <span class="ep-hint">leer/grau = Standard</span>
    </div>
    <label class="form-label small mb-1" for="epMetaFuss">Fusstext im Word/PDF (eine Zeile pro Absatz)</label>
    <textarea class="form-control form-control-sm" id="epMetaFuss" rows="5"><?= $h($plan['fusstext']) ?></textarea>
    <?php if ($istA): ?>
    <label class="form-label small mb-1 mt-3" for="epMetaUmfrage">Portal-Umfrage als Quelle der Verfügbarkeiten</label>
    <select class="form-select form-select-sm" id="epMetaUmfrage">
      <option value="0">– keine –</option>
      <?php foreach ($umfragen as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)($plan['umfrage_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= $h($u['titel']) ?> (<?= $h($u['status']) ?>)</option><?php endforeach; ?>
    </select>
    <div class="ep-hint mt-1">Umfragen der Kategorie «Arbeitseinsatz»/«Helfer». Einlesen über «Verfügbarkeiten → Aus Umfrage übernehmen».</div>
    <?php endif; ?>
  </div>
  <div class="modal-footer py-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
    <button type="button" class="btn btn-outline-primary btn-sm" id="epMetaSpeichern"><i class="bi bi-check-lg me-1"></i>Speichern</button>
  </div>
</div></div></div>

<?php if ($istA): ?>
<!-- ================= MODAL: Verfügbarkeiten (Personalanfrage) ================= -->
<div class="modal fade" id="epVerfModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-person-check me-2"></i>Verfügbarkeiten – wer kann wann in welcher Rolle</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <button type="button" class="btn btn-outline-success btn-sm" id="epVerfUmfrage" <?= $plan['umfrage_id'] ? '' : 'disabled data-tooltip="Zuerst unter «Titel / Fusstext» eine Umfrage zuordnen"' ?>><i class="bi bi-ui-checks me-1"></i>Aus Umfrage übernehmen</button>
      <form id="epVerfExcelForm" class="d-flex flex-wrap gap-2 align-items-center">
        <select class="form-select form-select-sm" name="verein" style="max-width:170px"><?php foreach (EP_VEREINE as $k => $v): ?><option value="<?= $k ?>"><?= $h($v) ?></option><?php endforeach; ?></select>
        <input type="file" class="form-control form-control-sm" name="datei" accept=".xlsx" style="max-width:280px" required>
        <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-upload me-1"></i>Personalanfrage-Excel prüfen</button>
      </form>
      <span class="ms-auto d-flex gap-2">
        <button type="button" class="btn btn-outline-info btn-sm js-export" data-plan="<?= $planId ?>" data-fmt="personalanfrage" data-verein="msv" data-tooltip="Excel mit den MSV-Verfügbarkeiten"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel MSV</button>
        <button type="button" class="btn btn-outline-info btn-sm js-export" data-plan="<?= $planId ?>" data-fmt="personalanfrage" data-verein="msv" data-leer="1" data-tooltip="Leere Vorlage für die anderen Vereine"><i class="bi bi-file-earmark me-1"></i>Leere Vorlage</button>
      </span>
    </div>
    <div id="epVerfImportBox" class="d-none mb-3">
      <div id="epVerfImportMeldungen"></div>
      <div class="table-responsive ep-preview" id="epVerfImportPreview"></div>
      <div class="mt-2 d-flex gap-2"><button type="button" class="btn btn-outline-primary btn-sm" id="epVerfImportSave"><i class="bi bi-check-lg me-1"></i>Ausgewählte übernehmen</button><button type="button" class="btn btn-outline-secondary btn-sm" id="epVerfImportCancel">Abbrechen</button></div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm ep-verf-table mb-2">
        <thead><tr><th style="min-width:170px">Person</th><th style="width:120px">Verein</th><th>Rollen</th><th>Schichten</th><th style="min-width:140px">Bemerkung</th><th style="width:70px">Quelle</th><th style="width:80px"></th></tr></thead>
        <tbody id="epVerfBody">
          <?php $rollenOhneOk = array_filter(EP_ROLLEN, fn($k) => $k !== 'OK', ARRAY_FILTER_USE_KEY);
          foreach ($plan['verfuegbarkeit'] as $v): ?>
          <tr data-id="<?= (int)$v['id'] ?>" data-mid="<?= (int)$v['mitglied_id'] ?>" data-name="<?= $h($v['name_text'] ?? '') ?>">
            <td class="fw-semibold"><?= $h($v['name']) ?><?php if (empty($v['mitglied_id'])): ?> <small class="text-muted fw-normal">(extern)</small><?php endif; ?></td>
            <td><select class="form-select form-select-sm v-verein" <?= !empty($v['mitglied_id']) ? 'disabled' : '' ?>><?php foreach (EP_VEREINE as $k => $lbl): ?><option value="<?= $k ?>" <?= $v['verein'] === $k ? 'selected' : '' ?>><?= $h($lbl) ?></option><?php endforeach; ?></select></td>
            <td><?php foreach ($rollenOhneOk as $k => $lbl): ?><div class="form-check form-check-inline"><input class="form-check-input v-rolle" type="checkbox" value="<?= $h($k) ?>" id="vr<?= (int)$v['id'] ?>_<?= $h($k) ?>" <?= in_array($k, $v['rollen'], true) ? 'checked' : '' ?>><label class="form-check-label" for="vr<?= (int)$v['id'] ?>_<?= $h($k) ?>"><?= $h($k) ?></label></div><?php endforeach; ?></td>
            <td><?php foreach ($plan['termine'] as $t): ?><div class="form-check form-check-inline"><input class="form-check-input v-termin" type="checkbox" value="<?= (int)$t['id'] ?>" id="vt<?= (int)$v['id'] ?>_<?= (int)$t['id'] ?>" <?= in_array((int)$t['id'], $v['termin_ids'], true) ? 'checked' : '' ?>><label class="form-check-label" for="vt<?= (int)$v['id'] ?>_<?= (int)$t['id'] ?>"><?= $h(ep_datum_kurz($t['datum']) . ' ' . substr((string)$t['zeit_von'], 0, 5)) ?></label></div><?php endforeach; ?></td>
            <td><input type="text" class="form-control form-control-sm v-bem" value="<?= $h($v['bemerkung'] ?? '') ?>" maxlength="255"></td>
            <td><span class="badge bg-light text-dark border"><?= $h(EP_QUELLEN[$v['quelle']] ?? $v['quelle']) ?></span></td>
            <td class="text-end text-nowrap"><button type="button" class="btn btn-outline-primary btn-sm js-verf-save" data-tooltip="Speichern"><i class="bi bi-check-lg"></i></button> <button type="button" class="btn btn-outline-danger btn-sm js-verf-delete" data-tooltip="Löschen"><i class="bi bi-trash"></i></button></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$plan['verfuegbarkeit']): ?><tr id="epVerfLeer"><td colspan="7" class="text-center text-muted py-3">Noch keine Verfügbarkeiten – aus Umfrage übernehmen, Excel einlesen oder unten erfassen.</td></tr><?php endif; ?>
          <tr data-id="0" class="table-light">
            <td>
              <select class="form-select form-select-sm v-mitglied mb-1"><option value="0">– Mitglied wählen –</option><optgroup label="Aktive"><?= $optAktiv ?></optgroup><?php if ($optInaktiv): ?><optgroup label="Inaktive"><?= $optInaktiv ?></optgroup><?php endif; ?></select>
              <input type="text" class="form-control form-control-sm v-name" placeholder="oder Name Vorname (extern / anderer Verein)" maxlength="100">
            </td>
            <td><select class="form-select form-select-sm v-verein"><?php foreach (EP_VEREINE as $k => $lbl): ?><option value="<?= $k ?>"><?= $h($lbl) ?></option><?php endforeach; ?></select></td>
            <td><?php foreach ($rollenOhneOk as $k => $lbl): ?><div class="form-check form-check-inline"><input class="form-check-input v-rolle" type="checkbox" value="<?= $h($k) ?>" id="vr0_<?= $h($k) ?>"><label class="form-check-label" for="vr0_<?= $h($k) ?>"><?= $h($k) ?></label></div><?php endforeach; ?></td>
            <td><?php foreach ($plan['termine'] as $t): ?><div class="form-check form-check-inline"><input class="form-check-input v-termin" type="checkbox" value="<?= (int)$t['id'] ?>" id="vt0_<?= (int)$t['id'] ?>"><label class="form-check-label" for="vt0_<?= (int)$t['id'] ?>"><?= $h(ep_datum_kurz($t['datum']) . ' ' . substr((string)$t['zeit_von'], 0, 5)) ?></label></div><?php endforeach; ?></td>
            <td><input type="text" class="form-control form-control-sm v-bem" maxlength="255"></td>
            <td></td>
            <td class="text-end"><button type="button" class="btn btn-outline-success btn-sm js-verf-save" data-tooltip="Hinzufügen"><i class="bi bi-plus-lg"></i></button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="ep-hint">Umfrage und Excel überschreiben keine manuell erfassten Zeilen. Rolle «OK Schlossturm» wird nicht automatisch eingeteilt.</div>
  </div>
  <div class="modal-footer py-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button></div>
</div></div></div>

<!-- ================= MODAL: Automatische Einteilung ================= -->
<div class="modal fade" id="epEinteilungModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-magic me-2"></i>Automatische Einteilung (Vorschlag)</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <?php
      // Bereitschaft: von allen drei Vereinen müssen Verfügbarkeiten vorliegen, sonst wird nicht eingeteilt
      $verfProVerein = []; foreach (EP_VEREINE as $vk => $vl) $verfProVerein[$vk] = ['n' => 0, 'quellen' => []];
      foreach ($plan['verfuegbarkeit'] as $v) { $verfProVerein[$v['verein']]['n']++; $verfProVerein[$v['verein']]['quellen'][$v['quelle']] = true; }
      $fehlend = array_keys(array_filter($verfProVerein, fn($x) => $x['n'] === 0));
    ?>
    <div class="mb-3">
      <h6 class="small text-uppercase text-muted mb-1">Bereitschaft – Personalanfragen der Vereine</h6>
      <table class="table table-sm table-bordered ep-einteilung-tabelle mb-1" style="max-width:560px">
        <thead><tr><th class="text-start">Verein</th><th>Personen gemeldet</th><th>Quelle</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach (EP_VEREINE as $vk => $vl): $x = $verfProVerein[$vk]; ?>
          <tr><td class="text-start"><?= $h($vl) ?></td><td><?= $x['n'] ?></td><td><?= $h(implode(', ', array_map(fn($q) => EP_QUELLEN[$q] ?? $q, array_keys($x['quellen'])))) ?: '–' ?></td>
              <td class="<?= $x['n'] ? 'text-success' : 'ep-fehlt' ?>"><?= $x['n'] ? '<i class="bi bi-check-circle"></i> vorhanden' : '<i class="bi bi-exclamation-circle"></i> fehlt' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($fehlend): ?>
        <div class="alert alert-warning py-2 px-3 small mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Eingeteilt wird erst, wenn die Personalanfragen aller drei Vereine vorliegen. Es fehlt: <strong><?= $h(implode(', ', array_map(fn($k) => EP_VEREINE[$k], $fehlend))) ?></strong>. Excel-Rückmeldungen unter «Verfügbarkeiten» einlesen.</div>
        <div class="form-check"><input class="form-check-input" type="checkbox" id="epEtTrotzdem"><label class="form-check-label small" for="epEtTrotzdem">Trotzdem einteilen (nur mit den vorhandenen Meldungen, fehlende Vereine bleiben leer)</label></div>
      <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
      <div class="form-check"><input class="form-check-input" type="checkbox" id="epEtKonti" checked><label class="form-check-label" for="epEtKonti">Gleiche Funktion über mehrere Schichten bevorzugen</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" id="epEtVerwerfen" checked><label class="form-check-label" for="epEtVerwerfen">Bestehende Vorschläge zuerst verwerfen</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" id="epEtVerein"><label class="form-check-label" for="epEtVerein" data-tooltip="Leere Fremdvereins-Positionen (z.B. aus dem Vorjahr) nur mit Personen dieses Vereins besetzen">Verein der Position beachten</label></div>
      <button type="button" class="btn btn-outline-primary btn-sm ms-auto" id="epEtBerechnen" <?= $fehlend ? 'disabled' : '' ?>><i class="bi bi-magic me-1"></i>Berechnen</button>
    </div>
    <div class="ep-hint mb-2">Fest besetzte Positionen bleiben. Offene Positionen mit Anfrage-Rolle werden nach Knappheit besetzt: Verein im Rückstand zuerst, dann wenig belastete Personen, Kontinuität, wenig flexible Personen vor flexiblen. Eine Person höchstens eine Position pro Schicht, nur wo sie verfügbar ist.</div>
    <div id="epEtErgebnis"></div>
  </div>
  <div class="modal-footer py-2">
    <button type="button" class="btn btn-outline-secondary btn-sm js-vorschlaege d-none" data-aktion="verwerfen">Vorschläge verwerfen</button>
    <button type="button" class="btn btn-outline-success btn-sm js-vorschlaege d-none" data-aktion="uebernehmen"><i class="bi bi-check2-all me-1"></i>Alle Vorschläge übernehmen</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" id="epEtSchliessen">Schliessen und im Raster prüfen</button>
  </div>
</div></div></div>

<!-- ================= MODAL: Rückmeldung einlesen ================= -->
<div class="modal fade" id="epRueckModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-upload me-2"></i>Rückmeldung eines Vereins einlesen</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <form id="epRueckForm" class="row g-2 align-items-end mb-3">
      <div class="col-md-6"><label class="form-label small mb-1">Ausgefülltes Word (aus diesem Plan exportiert)</label><input type="file" class="form-control form-control-sm" name="datei" accept=".docx" required></div>
      <div class="col-md-3"><label class="form-label small mb-1">Rückmeldung von</label>
        <select class="form-select form-select-sm" name="verein"><option value="beide">beiden Vereinen</option><option value="freienbach">SV Freienbach</option><option value="wollerau">SV Wollerau</option></select></div>
      <div class="col-md-3"><button type="submit" class="btn btn-outline-success btn-sm w-100"><i class="bi bi-search me-1"></i>Prüfen</button></div>
    </form>
    <div id="epRueckMeldungen"></div>
    <div class="ep-preview" id="epRueckPreview"></div>
  </div>
  <div class="modal-footer py-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
    <button type="button" class="btn btn-outline-primary btn-sm d-none" id="epRueckUebernehmen"><i class="bi bi-check-lg me-1"></i>Ausgewählte übernehmen</button>
  </div>
</div></div></div>
<?php endif; ?>
<?php endif; ?>

<script>
$(function () {
  const basePath = (/\/inc(\/|$)/.test(location.pathname)) ? 'einsatzplanung/' : 'inc/einsatzplanung/';
  const CSRF = document.getElementById('csrfToken').value;
  const PLAN_ID = <?= $planId ?>;
  const EP_VEREINE_JS = <?= json_encode(EP_VEREINE) ?>;   // Vereins-Labels zentral aus plan_helpers (EP_VEREINE)
  const post = (file, data, ok, failMsg) => msvPost(basePath + file, Object.assign({ plan_id: PLAN_ID }, data || {}), ok, { csrf: CSRF, failMsg });

  // ---------- Export (Liste + Editor) ----------
  $(document).on('click', '.js-export', function (e) {
    e.preventDefault();
    const $b = $(this), orig = $b.html();
    $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.getJSON(basePath + 'export_' + $b.data('fmt') + '.php', { plan_id: $b.data('plan'), ansicht: $b.data('ansicht') || '', verein: $b.data('verein') || '', leer: $b.data('leer') || '' })
      .done(r => { if (r && r.success && r.link) { window.open(basePath + r.link, '_blank'); msvToast('Datei erstellt', 'success'); } else msvToast((r && r.message) || 'Export fehlgeschlagen', 'error'); })
      .fail(xhr => msvToast(msvXhrMessage(xhr, 'Export fehlgeschlagen'), 'error'))
      .always(() => $b.prop('disabled', false).html(orig));
  });

  // ---------- Auswertung Anwesenheit (Plan oder Jahr) ----------
  $(document).on('click', '.js-auswertung', function () {
    const $b = $(this), q = $b.data('plan') ? { plan_id: $b.data('plan') } : { jahr: $b.data('jahr') };
    $('#epAuswTitel').text('Auswertung Anwesenheit ' + ($b.data('plan') ? '' : $b.data('jahr')));
    $('#epAuswBody').html('<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Lade…</div>');
    new bootstrap.Modal(document.getElementById('epAuswModal')).show();
    $.getJSON(basePath + 'anwesenheit_auswertung.php', q).done(r => {
      if (!r || !r.success) { $('#epAuswBody').html('<div class="alert alert-warning small">' + msvEsc((r && r.message) || 'Fehler') + '</div>'); return; }
      const V = EP_VEREINE_JS;
      const zelle = (n, cls) => '<td class="num ' + (cls || '') + '">' + n + '</td>';
      let html = '<div class="row g-3"><div class="col-lg-5"><h6 class="small text-uppercase text-muted mb-1">Je Verein</h6><table class="table table-sm table-bordered ep-ausw-table mb-3"><thead><tr><th>Verein</th><th>eingeteilt</th><th>da</th><th>nicht da</th><th>offen</th><th>Std. da</th></tr></thead><tbody>';
      Object.keys(r.vereine).forEach(v => { const z = r.vereine[v]; html += '<tr><td>' + V[v] + '</td>' + zelle(z.eingeteilt) + zelle(z.da) + zelle(z.nein, z.nein ? 'ep-fehlt' : '') + zelle(z.offen) + zelle(z.stunden_da.toLocaleString('de-CH')) + '</tr>'; });
      html += '</tbody></table>';
      if ((r.plaene || []).length > 1) {
        html += '<h6 class="small text-uppercase text-muted mb-1">Je Plan</h6><table class="table table-sm table-bordered ep-ausw-table mb-0"><thead><tr><th>Plan</th><th>eingeteilt</th><th>da</th><th>nicht da</th><th>offen</th></tr></thead><tbody>';
        r.plaene.forEach(p => html += '<tr><td>' + msvEsc(p.titel) + '</td>' + zelle(p.eingeteilt) + zelle(p.da) + zelle(p.nein, p.nein ? 'ep-fehlt' : '') + zelle(p.offen) + '</tr>');
        html += '</tbody></table>';
      }
      html += '</div><div class="col-lg-7"><h6 class="small text-uppercase text-muted mb-1">Je Person (nicht erschienen zuerst)</h6><div class="ep-preview" style="max-height:460px"><table class="table table-sm table-hover ep-ausw-table mb-0"><thead><tr><th>Person</th><th>Verein</th><th>eingeteilt</th><th>da</th><th>nicht da</th><th>offen</th><th>Std. da</th></tr></thead><tbody>';
      (r.personen || []).forEach(p => html += '<tr' + (p.nein ? ' class="table-danger"' : '') + '><td data-tooltip="' + msvEsc((p.plaene || []).join(', ')) + '">' + msvEsc(p.name) + '</td><td>' + V[p.verein] + '</td>' + zelle(p.eingeteilt) + zelle(p.da) + zelle(p.nein, p.nein ? 'ep-fehlt' : '') + zelle(p.offen) + zelle(p.stunden_da.toLocaleString('de-CH')) + '</tr>');
      html += '</tbody></table></div><div class="ep-hint mt-1">Erfassung pro Schicht im Portal unter «Anwesenheit» (Vorstand/Admin). Std. da = Pauschale bzw. Schichtdauer der Einsätze mit Anwesenheit «da».</div></div></div>';
      $('#epAuswBody').html(html);
    }).fail(xhr => $('#epAuswBody').html('<div class="alert alert-warning small">' + msvEsc(msvXhrMessage(xhr, 'Auswertung nicht verfügbar')) + '</div>'));
  });

  // ---------- Plan löschen ----------
  $(document).on('click', '.js-plan-delete', function () {
    const id = $(this).data('plan'), titel = $(this).data('titel');
    msvConfirmDelete('den Einsatzplan «' + msvEsc(titel) + '» samt allen Positionen').then(res => {
      if (!res.isConfirmed) return;
      msvPost(basePath + 'plan_delete.php', { plan_id: id }, r => { msvToast(r.message, 'success'); setTimeout(() => location.href = 'einsatzplanung.php', 600); }, { csrf: CSRF });
    });
  });

  // ---------- Neuer Plan ----------
  const typLabels = <?= json_encode(EP_TYPEN, JSON_UNESCAPED_UNICODE) ?>;
  function neuTitelVorschlag() {
    const q = $('input[name=epNeuQuelle]:checked').val();
    if (q === 'dokument') return;
    const typ = $('#epNeuTyp').val(), jahr = $('#epNeuJahr').val();
    $('#epNeuTitel').val((typLabels[typ] || '') + ' ' + jahr);
    $('#epJmBlock').toggle(typ === 'obligatorisch' || typ === 'feldschiessen');
  }
  // Quellpläne für «Kopie» nach dem gewählten Typ filtern (der Typ bleibt führend; vorher hat die
  // Quellliste den Typ überschrieben und sprang so auf den ersten Plan «Obligatorisch» zurück).
  function filterQuellePlaene() {
    const typ = $('#epNeuTyp').val();
    let erster = null;
    $('#epNeuQuellePlan option').each(function () {
      const passt = $(this).data('typ') === typ;
      $(this).prop('hidden', !passt).prop('disabled', !passt);
      if (passt && erster === null) erster = this.value;
    });
    const $kopie = $('#epQuelleKopie');
    if (erster !== null) { $('#epNeuQuellePlan').val(erster); $kopie.prop('disabled', false); }
    else { $kopie.prop('disabled', true); if ($kopie.is(':checked')) $('#epQuelleLeer').prop('checked', true).trigger('change'); }
    $('#epKopieHint').toggleClass('d-none', erster !== null);
    // Stammdaten-Vorlage für «Leer»: neuester Plan gleichen Typs (Liste ist nach Jahr absteigend sortiert)
    const $v = $('#epNeuQuellePlan option').filter(function () { return $(this).data('typ') === typ; }).first();
    $('#epStrukturBlock').toggleClass('d-none', !$v.length); $('#epStrukturHint').toggleClass('d-none', !!$v.length);
    $('#epStrukturVon').text($v.length ? $v.text().replace(/\s*\(.*\)$/, '') : '–').data('id', $v.length ? $v.val() : 0);
  }
  $('#epNeuTyp').on('change', function () { filterQuellePlaene(); neuTitelVorschlag(); });
  $('#epNeuJahr').on('change', neuTitelVorschlag);
  $('input[name=epNeuQuelle]').on('change', function () {
    const q = this.value;
    $('#epNeuQuellePlan').toggleClass('d-none', q !== 'kopie');
    $('#epNeuQuelleDok').toggleClass('d-none', q !== 'dokument');
    $('#epStrukturBlock, #epStrukturHint').toggle(q === 'leer');
    $('#epNeuTyp').prop('disabled', q === 'dokument'); // Typ kommt beim Dokument aus der Datei
    if (q === 'dokument') { const o = $('#epNeuQuelleDok option:selected'); if (o.data('jahr')) $('#epNeuJahr').val(String(o.data('jahr'))); $('#epNeuTitel').val(o.text().split(' – ')[0]); }
    else neuTitelVorschlag();
  });
  $('#epNeuQuellePlan').on('change', neuTitelVorschlag);
  $('#epNeuQuelleDok').on('change', function () { $('input[name=epNeuQuelle][value=dokument]').trigger('change'); });
  $('#epNeuModal').on('show.bs.modal', function () { filterQuellePlaene(); neuTitelVorschlag(); });
  $('#epNeuSpeichern').on('click', function () {
    const q = $('input[name=epNeuQuelle]:checked').val();
    const $b = $(this).prop('disabled', true);
    const data = { titel: $('#epNeuTitel').val(), jahr: $('#epNeuJahr').val(), termine_aus_jm: $('#epNeuJm').is(':checked') ? 1 : 0 };
    let file = 'plan_save.php';
    if (q === 'kopie') { data.action = 'copy'; data.quelle_id = $('#epNeuQuellePlan').val(); }
    else if (q === 'dokument') { file = 'plan_from_dokument.php'; data.dokument_id = $('#epNeuQuelleDok').val(); }
    else { data.action = 'create'; data.typ = $('#epNeuTyp').val(); if ($('#epNeuStruktur').is(':checked') && !$('#epStrukturBlock').hasClass('d-none')) data.struktur_von = $('#epStrukturVon').data('id') || 0; }
    msvPost(basePath + file, data, r => { msvToast(r.message, 'success'); setTimeout(() => location.href = 'einsatzplanung.php?id=' + r.plan_id, 500); }, { csrf: CSRF })
      .always(() => $b.prop('disabled', false));
  });

<?php if ($plan && !$ansichtAbr): ?>
  // ---------- Struktur: Termine ----------
  // Termine-Dialog bleibt offen (mehrere Daten nacheinander); Raster wird erst beim Schliessen neu geladen
  let termineGeaendert = false;
  $('#epTermineModal').on('hidden.bs.modal', function () { if (termineGeaendert) location.reload(); });
  $('#epTermineBody').on('click', '.js-termin-save', function () {
    const $tr = $(this).closest('tr'), neu = !($tr.data('id') > 0);
    if (!$tr.find('.t-datum').val()) { msvToast('Bitte ein Datum wählen', 'warning'); $tr.find('.t-datum').trigger('focus'); return; }
    post('struktur_save.php', { action: 'termin_save', id: $tr.data('id'), bezeichnung: $tr.find('.t-bez').val(), datum: $tr.find('.t-datum').val(),
      zeit_von: $tr.find('.t-von').val(), zeit_bis: $tr.find('.t-bis').val(), zeit_text: $tr.find('.t-text').val(), info: $tr.find('.t-info').val() },
      r => {
        termineGeaendert = true;
        msvToast(r.message, 'success');
        if (!neu) return;
        // gespeicherte Zeile «einfrieren», neue Eingabezeile mit vorbelegten Zeiten anhängen
        const $neu = $tr.clone();
        $tr.attr('data-id', r.id).data('id', r.id).removeClass('table-light');
        $tr.find('td').last().html('<button type="button" class="btn btn-outline-primary btn-sm js-termin-save" data-tooltip="Speichern"><i class="bi bi-check-lg"></i></button> <button type="button" class="btn btn-outline-danger btn-sm js-termin-delete" data-tooltip="Löschen (inkl. Positionen)"><i class="bi bi-trash"></i></button>');
        $neu.attr('data-id', '0').data('id', 0);
        $neu.find('.t-bez, .t-datum').val('');   // Zeiten, Zeit-Text und Info bleiben als Vorlage stehen
        $tr.after($neu);
        $neu.find('.t-datum').trigger('focus');
      });
  });
  $('#epTermineBody').on('click', '.js-termin-delete', function () {
    const $tr = $(this).closest('tr');
    msvConfirmDelete('den Termin vom ' + msvEsc($tr.find('.t-datum').val()) + ' mit allen Positionen').then(res => {
      if (!res.isConfirmed) return;
      post('struktur_save.php', { action: 'termin_delete', id: $tr.data('id') }, r => { termineGeaendert = true; $tr.remove(); msvToast(r.message, 'success'); });
    });
  });
  // Enter in der Eingabezeile = speichern
  $('#epTermineBody').on('keydown', 'input', function (e) { if (e.key === 'Enter') { e.preventDefault(); $(this).closest('tr').find('.js-termin-save').trigger('click'); } });
  $('#epTermineAusJm').on('click', function () {
    post('struktur_save.php', { action: 'termine_aus_jm' }, r => { termineGeaendert = true; msvToast(r.message, 'success'); setTimeout(() => location.reload(), 700); });
  });

  // ---------- Struktur: Funktionen ----------
  $('#epFunktionenBody').on('click', '.js-funktion-save', function () {
    const $tr = $(this).closest('tr');
    post('struktur_save.php', { action: 'funktion_save', id: $tr.data('id'), gruppe: $tr.find('.f-gruppe').val() || '', bezeichnung: $tr.find('.f-bez').val(), anzahl: $tr.find('.f-anz').val() || 0, rolle: $tr.find('.f-rolle').val() || '' },
      r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 500); });
  });
  $('#epFunktionenBody').on('click', '.js-funktion-delete', function () {
    const $tr = $(this).closest('tr');
    msvConfirmDelete('die Funktion «' + msvEsc($tr.find('.f-bez').val()) + '»').then(res => {
      if (!res.isConfirmed) return;
      post('struktur_save.php', { action: 'funktion_delete', id: $tr.data('id') }, r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 500); });
    });
  });
  $('#epFunktionenBody').on('click', '.js-funktion-move', function () {
    const $tr = $(this).closest('tr');
    post('struktur_save.php', { action: 'funktion_move', id: $tr.data('id'), dir: $(this).data('dir') }, () => location.reload());
  });

  // ---------- Titel / Fusstext ----------
  $('#epMetaFarbePalette').on('click', 'button', function () { $('#epMetaFarbe').val(this.dataset.farbe); });
  $('#epMetaSpeichern').on('click', function () {
    const meta = { action: 'update', titel: $('#epMetaTitel').val(), fusstext: $('#epMetaFuss').val(), farbe: $('#epMetaFarbe').val() || '' };
    if ($('#epMetaUmfrage').length) meta.umfrage_id = $('#epMetaUmfrage').val() || 0;
    post('plan_save.php', meta, r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 500); });
  });

  // ---------- Mitgliederliste rechts (beide Layouts): Zähler, Suche, Drag-Quelle ----------
  // Personen sind Mitglieder (mid > 0) oder Externe (mid = 0, Schlüssel = Name)
  const MitgliederListe = {
    eintrag(mid, name) {
      return mid > 0 ? $('#epSideList .ep-mitglied[data-mid="' + mid + '"]')
                     : $('#epSideList .ep-mitglied[data-mid="0"]').filter(function () { return (this.dataset.name || '') === (name || ''); });
    },
    verschiebe(altMid, neuMid, altName, neuName) {
      if (altMid === neuMid && (altMid > 0 || (altName || '') === (neuName || ''))) return;
      if (altMid || altName) this.aendere(altMid, -1, altName);
      if (neuMid || neuName) this.aendere(neuMid, +1, neuName);
    },
    aendere(mid, delta, name) {
      const $m = this.eintrag(mid, name);
      if (!$m.length || !delta) return;
      const n = Math.max(0, parseInt($m.find('.ep-m-count').text() || '0', 10) + delta);
      $m.find('.ep-m-count').text(n);
      $m.toggleClass('geplant', n > 0).attr('data-tooltip', n > 0 ? n + ' Einsatz/Einsätze in diesem Plan' : 'noch nicht eingeplant');
      // Mitglieder wandern zwischen den Gruppen «Nicht eingeplant» / «Eingeplant» (alphabetisch einsortiert)
      if (mid > 0) {
        const $ziel = $(n > 0 ? '#epGruppeGeplant' : '#epGruppeOffen');
        if (!$m.parent().is($ziel)) {
          const nm = ($m.data('name') || '').toLowerCase();
          const $vor = $ziel.children('.ep-mitglied').filter(function () { return (this.dataset.name || '').toLowerCase() > nm; }).first();
          if ($vor.length) $m.insertBefore($vor); else $ziel.append($m);
        }
      }
      this.gruppenZaehler();
    },
    gruppenZaehler() {
      $('#epSideList .ep-side-group').each(function () { $(this).find('.ep-g-n').text($(this).find('.ep-mitglied').length); });
    },
    externHinzufuegen(name) {
      name = name.trim().replace(/\s+/g, ' ');
      if (!name) return null;
      const $vorh = this.eintrag(0, name);
      if ($vorh.length) { msvToast('Person ist schon in der Liste', 'info'); return $vorh; }
      const parts = name.split(' '); const vorname = parts.length > 1 ? parts.pop() : ''; const nachname = parts.join(' ');
      const $el = $('<div class="ep-mitglied extern" draggable="true" data-mid="0"></div>')
        .attr({ 'data-name': name, 'data-nachname': nachname, 'data-vorname': vorname, 'data-tooltip': 'noch nicht eingeplant' })
        .append($('<span class="ep-m-name"></span>').text(name)).append('<span class="ep-m-count">0</span>');
      $('#epExterne').append($el);
      $('#epExterne').closest('.ep-side-group').removeClass('ep-collapsed');
      this.gruppenZaehler();
      return $el;
    }
  };
  // Gruppen der Mitgliederliste ein-/ausklappen (Standard zu; Zustand pro Plan gemerkt)
  const sideKey = 'ep_side_open_' + PLAN_ID;
  try { JSON.parse(localStorage.getItem(sideKey) || '[]').forEach(g => $('#epSideList .ep-side-group[data-gruppe="' + g + '"]').removeClass('ep-collapsed')); } catch (e) {}
  $(document).on('click', '#epSideList .ep-side-sub', function () {
    $(this).closest('.ep-side-group').toggleClass('ep-collapsed');
    try { localStorage.setItem(sideKey, JSON.stringify($('#epSideList .ep-side-group:not(.ep-collapsed)').map(function () { return this.dataset.gruppe; }).get())); } catch (e) {}
  });
  $('#epExternAdd').on('click', function () { if (MitgliederListe.externHinzufuegen($('#epExternNeu').val())) $('#epExternNeu').val('').focus(); });
  $('#epExternNeu').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#epExternAdd').trigger('click'); } });
  $('#epSideFilter').on('input', function () {
    const q = this.value.trim().toLowerCase();
    $('#epSideList .ep-mitglied').each(function () { $(this).toggle(!q || (this.dataset.name || '').toLowerCase().indexOf(q) !== -1); });
    if (q) $('#epSideList .ep-side-group').removeClass('ep-collapsed');   // Suche klappt alle Gruppen auf
  });
  let dragQuelle = null;          // Layout A: Chip, von dem gezogen wird (null = aus der Mitgliederliste)
  let dragQuelleZelle = null;     // Layout B: Zelle, von der gezogen wird
  let sideDropHandler = null;     // wird je Layout gesetzt; liefert true, wenn behandelt
  let dragName = '';              // Name der gezogenen Person (für Externe ohne Mitglieds-ID)
  $(document).on('dragstart', '.ep-mitglied', function (e) {
    dragQuelle = null; dragQuelleZelle = null; dragName = this.dataset.name || '';
    e.originalEvent.dataTransfer.setData('text/plain', this.dataset.mid);
    e.originalEvent.dataTransfer.effectAllowed = 'copy';
    $(this).addClass('dragging');
  });
  $(document).on('dragend', '.ep-mitglied', function () { $(this).removeClass('dragging'); $('.ep-chip, .ep-cell, #epGridB th').removeClass('ep-drop'); });
  $('#epSide').on('dragover', function (e) { if (dragQuelle || dragQuelleZelle) { e.preventDefault(); e.originalEvent.dataTransfer.dropEffect = 'move'; } });
  $('#epSide').on('drop', function (e) {
    if (!dragQuelle && !dragQuelleZelle) return;
    e.preventDefault();
    $('#epSide').removeClass('ep-drop-remove');
    if (typeof sideDropHandler === 'function') sideDropHandler();
  });

  // ---------- PDF als Dokument im Portal ablegen ----------
  $('#epPdfAblegen').on('click', function (e) {
    e.preventDefault();
    const $b = $(this);
    msvConfirm('Das PDF wird erzeugt und unter «Dokumente verwalten → Einsatzpläne» für alle Mitglieder abgelegt. Ein bestehendes Dokument wird nicht ersetzt.', 'PDF ins Portal', 'Ja, ablegen').then(res => {
      if (!res.isConfirmed) return;
      const orig = $b.html(); $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
      post('export_pdf.php', { ablegen: 1 }, r => { msvToast(r.message, 'success'); if (r.link) window.open(basePath + r.link, '_blank'); })
        .always(() => $b.prop('disabled', false).html(orig));
    });
  });

  // ---------- Freigabe ----------
  $('.js-publish').on('click', function () {
    const status = $(this).data('status');
    const texte = { freigegeben: 'Der Plan wird ins Portal übertragen: Mitglieder sehen ihre Einsätze unter «Meine Einsätze» und den ganzen Plan unter «Einsatzpläne».',
                    final: 'Der Plan gilt als definitiv (alle Namen komplett). Die Einsätze im Portal werden aktualisiert.',
                    entwurf: 'Der Plan wird aus dem Portal zurückgezogen; die Einsätze der Mitglieder verschwinden aus «Meine Einsätze».' };
    Swal.fire({ title: status === 'entwurf' ? 'Zurückziehen?' : (status === 'final' ? 'Final setzen?' : 'Freigeben?'), html: texte[status], icon: 'question',
      input: status === 'entwurf' ? undefined : 'checkbox', inputValue: 1, inputPlaceholder: 'Eingeteilte Mitglieder benachrichtigen (Push/Inbox)',
      showCancelButton: true, confirmButtonText: 'Ja', cancelButtonText: 'Abbrechen', confirmButtonColor: '#3085d6', cancelButtonColor: '#6c757d' })
    .then(res => {
      if (!res.isConfirmed) return;
      post('plan_publish.php', { status, push: res.value ? 1 : 0 }, r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 900); });
    });
  });
<?php endif; ?>

<?php if ($plan && !$ansichtAbr): ?>
  // ---------- Positions-Panel (beide Layouts) ----------
  const IST_A = <?= $istA ? 'true' : 'false' ?>;   // false = Chilbi: Positionen dynamisch (add/delete)

  // Neuen Chip in eine Zelle einsetzen (vor Platzhalter/«+»), aus dem Server-Payload befüllen
  function chipEinsetzen($td, s) {
    const $chip = $('<button type="button" class="ep-chip ep-v-msv ep-offen" data-mid="0" data-verein="msv" data-name="" data-bem="" data-ok="0" data-okslot="0" data-okfn="' + ($td.closest('tr').attr('data-okfn') || '0') + '"><span class="ep-dot"></span><span class="ep-txt"></span></button>');
    $chip.attr({ 'data-slot': s.id, 'data-funktion': $td.attr('data-flabel'), 'data-termin': $td.attr('data-tlabel') });
    const $anker = $td.children('.ep-ph, .ep-add').first();
    if ($anker.length) $chip.insertBefore($anker); else $td.append($chip);
    applySlotToChip($chip, s);
    refreshOffen($td);
    return $chip;
  }
  // Helferstunden je Verein (Pauschale bzw. Schichtdauer × feste Positionen), Vorschläge separat – Formel zentral in ep_termin_stunden()
  const EP_TERMIN_STD = <?= json_encode(array_column(array_map(fn($t) => ['id' => (int)$t['id'], 'std' => ep_termin_stunden($t)], $plan['termine']), 'std', 'id')) ?>;
  function updateStunden() {
    const $box = $('#epStunden'); if (!$box.length) return;
    const acc = {}; let tPos = 0, tStd = 0, tVs = 0;
    $('#epGrid td[data-termin]').each(function () {
      const std = EP_TERMIN_STD[this.dataset.termin] || 0;
      $(this).children('.ep-chip[data-slot].ep-besetzt').each(function () {
        const v = this.dataset.verein || 'msv'; acc[v] = acc[v] || { pos: 0, std: 0, vs: 0 };
        if (this.dataset.vorschlag === '1') { acc[v].vs++; tVs++; } else { acc[v].pos++; acc[v].std += std; tPos++; tStd += std; }
      });
    });
    const fmt = n => (Math.round(n * 10) / 10).toLocaleString('de-CH');
    $box.find('tr[data-verein]').each(function () {
      const a = acc[this.dataset.verein] || { pos: 0, std: 0, vs: 0 };
      $(this).find('.ep-st-pos').text(a.pos + ' Pos.'); $(this).find('.ep-st-std').text(fmt(a.std) + ' h'); $(this).find('.ep-st-vs').text(a.vs ? '(+' + a.vs + ' ?)' : '');
    });
    $box.find('tr.ep-st-total .ep-st-pos').text(tPos + ' Pos.'); $box.find('tr.ep-st-total .ep-st-std').text(fmt(tStd) + ' h'); $box.find('tr.ep-st-total .ep-st-vs').text(tVs ? '(+' + tVs + ' ?)' : '');
  }
  updateStunden();

  // Zusammenfassung einer Zelle (eingeklappte Zeile) nachführen
  function refreshSummary($td) {
    updateStunden();
    if (!$td || !$td.length) return;
    const namen = $td.children('.ep-chip[data-slot].ep-besetzt').map(function () { return $(this).find('.ep-txt').text() + (this.dataset.ok === '1' ? ' (OK)' : ''); }).get();
    const n = namen.length, offen = $td.children('.ep-ph').length + $td.children('.ep-chip[data-slot].ep-offen').length;
    const $s = $td.children('.ep-summary');
    $s.toggleClass('ep-leer', n === 0).attr('data-tooltip', namen.length ? namen.join(', ') : 'niemand eingeteilt');
    $s.find('.ep-txt').text(n + (n === 1 ? ' Person' : ' Personen'));
    $s.find('.ep-offen-n').remove();
    if (offen > 0) $s.append('<span class="ep-offen-n">' + offen + ' offen</span>');
    // Gesamtzahl der Funktion (Anzeige im eingeklappten Zustand)
    const $tr = $td.closest('tr');
    $tr.find('.ep-fn .ep-fn-n').text($tr.find('td .ep-chip[data-slot].ep-besetzt').length);
  }
  // Ein-/Ausklappen der Funktionszeilen: Standard eingeklappt, aufgeklappte pro Plan im Browser gemerkt
  const expandedKey = 'ep_expanded_' + PLAN_ID;
  function expandedLaden() { try { return JSON.parse(localStorage.getItem(expandedKey) || '[]'); } catch (e) { return []; } }
  function collapsedSpeichern() { try { localStorage.setItem(expandedKey, JSON.stringify($('#epGrid tbody tr[data-fid]:not(.ep-collapsed)').map(function () { return this.dataset.fid; }).get())); } catch (e) {} }
  expandedLaden().forEach(fid => $('#epGrid tbody tr[data-fid="' + fid + '"]').removeClass('ep-collapsed'));
  // Button-Beschriftung: solange mindestens eine Zeile zu ist → «Alle aufklappen», sonst «Alle einklappen»
  function alleToggleLabel() {
    const $rows = $('#epGrid tbody tr[data-fid]'), zu = $rows.filter('.ep-collapsed').length > 0;
    $('#epAlleToggle').attr('data-tooltip', zu ? 'Alle Funktionszeilen aufklappen' : 'Alle Funktionszeilen einklappen');
    $('#epAlleToggle i').attr('class', 'bi ' + (zu ? 'bi-arrows-expand' : 'bi-arrows-collapse'));
  }
  alleToggleLabel();
  $(document).on('click', '#epGrid tbody td.ep-fn', function () { $(this).closest('tr').toggleClass('ep-collapsed'); collapsedSpeichern(); alleToggleLabel(); });
  $(document).on('click', '.ep-summary', function () { $(this).closest('tr').removeClass('ep-collapsed'); collapsedSpeichern(); alleToggleLabel(); });
  $('#epAlleToggle').on('click', function () {
    const $rows = $('#epGrid tbody tr[data-fid]');
    const auf = $rows.filter('.ep-collapsed').length > 0;   // mindestens eine zu → alle auf, sonst alle zu
    $rows.toggleClass('ep-collapsed', !auf); collapsedSpeichern(); alleToggleLabel();
  });
  // beim Ziehen über eine eingeklappte Zeile: aufklappen, damit die Zelle als Ablage sichtbar wird
  $(document).on('dragenter', '#epGrid tbody tr.ep-collapsed td', function () { $(this).closest('tr').removeClass('ep-collapsed'); collapsedSpeichern(); alleToggleLabel(); });

  // Chilbi: «offen»-Platzhalter gemäss Soll nachführen
  function refreshOffen($td) {
    if (!$td || !$td.length) return;
    refreshSummary($td);
    if (IST_A) return;
    const soll = parseInt($td.attr('data-soll') || '0', 10), ist = $td.children('.ep-chip[data-slot]').length;
    $td.children('.ep-ph').remove();
    const $add = $td.children('.ep-add');
    for (let i = ist; i < soll; i++) $('<div class="ep-chip ep-offen ep-ph" data-tooltip="Soll noch nicht erreicht – Person hierher ziehen"><span class="ep-dot"></span><span class="ep-txt">offen</span></div>').insertBefore($add);
    refreshSummary($td);
  }
  // Chilbi: Position löschen (Chip verschwindet, Zähler und Platzhalter nachführen)
  function positionLoeschen($chip, ok) {
    const $td = $chip.closest('td');
    post('chilbi_zelle_save.php', { action: 'delete', slot_id: $chip.data('slot') }, r => {
      MitgliederListe.verschiebe(parseInt($chip.attr('data-mid') || '0', 10), 0, chipExternName($chip), '');
      if (Slot.$chip && Slot.$chip.is($chip)) Slot.close();
      $chip.remove(); refreshOffen($td);
      if (typeof ok === 'function') ok(r);
    });
  }
  // Chilbi: Position in Zelle anlegen (mid > 0 oder externer Name)
  function positionAnlegen($td, mid, name, ok) {
    post('chilbi_zelle_save.php', { action: 'add', termin_id: $td.data('termin'), funktion_id: $td.data('funktion'), mitglied_id: mid || 0, name_text: mid ? '' : (name || '') }, r => {
      const $chip = chipEinsetzen($td, r.slot);
      if (r.slot.warnung) msvToast('Mitglied ist ' + r.slot.warnung + ' – bitte prüfen', 'warning');
      if (typeof ok === 'function') ok($chip, r);
    });
  }

  const Slot = {
    $chip: null, $addTd: null,   // $addTd gesetzt = Panel im Modus «neue Position» (Chilbi)
    open(chip) {
      this.$chip = $(chip); this.$addTd = null;
      const d = chip.dataset;
      $('#epSlotTitle').text(d.funktion);
      $('#epSlotTermin').text(d.termin);
      $('input[name=epVerein][value="' + d.verein + '"]').prop('checked', true);
      $('#epMitglied').val(String(d.mid || 0));
      $('#epMitgliedFilter').val('').trigger('input');
      $('#epNameText').val(d.name || '');
      $('#epBemerkung').val(d.bem || '');
      $('#epOk').prop('checked', d.ok === '1').prop('disabled', d.okfn === '1'); $('#epOkFn').toggle(d.okfn === '1');
      this.vereinUi(d.verein);
      $('#epSlotLeeren').show();
      $('#epSlotVorschlagOk').toggleClass('d-none', d.vorschlag !== '1');
      $('.ep-chip').removeClass('selected'); this.$chip.addClass('selected');
      $('#epSlotPanel').addClass('open'); $('#epSlotOverlay').addClass('show');
    },
    openAdd(td) {
      this.$chip = null; this.$addTd = $(td);
      $('#epSlotTitle').text(this.$addTd.attr('data-flabel') + ' – neue Position');
      $('#epSlotTermin').text(this.$addTd.attr('data-tlabel'));
      $('input[name=epVerein][value="msv"]').prop('checked', true);
      $('#epMitglied').val('0'); $('#epMitgliedFilter').val('').trigger('input');
      $('#epNameText').val(''); $('#epBemerkung').val(''); $('#epOk').prop('checked', false);
      this.vereinUi('msv');
      $('#epSlotLeeren').hide(); $('#epSlotVorschlagOk').addClass('d-none');
      $('.ep-chip').removeClass('selected'); this.$addTd.children('.ep-add').addClass('selected');
      $('#epSlotPanel').addClass('open'); $('#epSlotOverlay').addClass('show');
      $('#epMitgliedFilter').trigger('focus');
    },
    close() { $('#epSlotPanel').removeClass('open'); $('#epSlotOverlay').removeClass('show'); $('.ep-chip').removeClass('selected'); this.$chip = null; this.$addTd = null; },
    vereinUi(v) {
      const msv = v === 'msv';
      $('#epMitgliedBlock').toggle(msv);
      $('#epNameTextLabel').text(msv ? 'Name (extern, ohne Mitgliedschaft)' : 'Name der gemeldeten Person');
      $('#epNameTextHint').toggle(!msv);
    },
    save() {
      if (this.$addTd) {
        // neue Position: sobald Mitglied oder Name gewählt ist
        const mid = parseInt($('#epMitglied').val() || '0', 10), name = $('#epNameText').val().trim();
        if (!mid && !name) return;
        const $td = this.$addTd;
        positionAnlegen($td, mid, name, $chip => { this.open($chip[0]); if ($('#epBemerkung').val()) this.save(); });
        return;
      }
      if (!this.$chip) return;
      const $chip = this.$chip;
      const verein = $('input[name=epVerein]:checked').val() || 'msv';
      const daten = { slot_id: $chip.data('slot'), verein, mitglied_id: verein === 'msv' ? ($('#epMitglied').val() || 0) : 0,
        name_text: $('#epNameText').val(), bemerkung: $('#epBemerkung').val() };
      if ($('#epOk').length) daten.ok = $('#epOk').is(':checked') ? 1 : 0;   // OK-Kennzeichen nur bei Schlossturm-Plänen im Panel
      post('slot_save.php', daten, r => {
          applySlotToChip($chip, r.slot);
          if (r.slot.mitglied_id) $('#epNameText').val('');
          if (r.slot.warnung) msvToast('Mitglied ist ' + r.slot.warnung + ' – bitte prüfen', 'warning');
        });
    }
  };
  $(document).on('click', '.ep-chip.ep-add', function () { Slot.openAdd($(this).closest('td')[0]); });

  // OK-Kennzeichen am Chip (Badge vor dem Namen, data-ok) – genutzt vom Panel-Speichern und vom Dialog «OK-Mitglieder»
  function chipOkSetzen($chip, ok) {
    $chip.attr('data-okslot', ok ? '1' : '0');      // eigenes Kennzeichen der Position
    const fn = $chip.attr('data-okfn') === '1';   // Funktion mit Rolle «OK»: immer OK, unabhängig vom Kennzeichen der Position
    ok = ok || fn;
    $chip.find('.ep-ok').remove();
    if (ok && $chip.hasClass('ep-besetzt')) $chip.append('<span class="ep-ok" data-tooltip="' + (fn ? 'OK über die Funktion (Rolle «OK»)' : 'OK-Mitglied') + '">OK</span>');
    $chip.attr('data-ok', ok ? '1' : '0');
  }
  // Chip nach dem Speichern aktualisieren + Zähler in der Mitgliederliste nachführen
  // Externer Name eines Chips (MSV ohne Mitglied), sonst ''
  const chipExternName = $chip => ($chip.attr('data-verein') === 'msv' && !parseInt($chip.attr('data-mid') || '0', 10)) ? ($chip.attr('data-name') || '') : '';
  function applySlotToChip($chip, s) {
    const altMid = parseInt($chip.attr('data-mid') || '0', 10), altName = chipExternName($chip);
    $chip.attr({ 'data-verein': s.verein, 'data-mid': s.mitglied_id, 'data-name': s.name_text, 'data-bem': s.bemerkung, 'data-warn': s.warnung });
    $chip.removeClass('ep-v-msv ep-v-freienbach ep-v-wollerau ep-besetzt ep-offen ep-warn')
         .addClass('ep-v-' + s.verein).addClass(s.besetzt ? 'ep-besetzt' : 'ep-offen').toggleClass('ep-warn', !!s.warnung);
    $chip.find('.ep-txt').text(s.anzeige || 'offen');
    if ($('#epGrid').hasClass('ep-eng')) $chip.find('.ep-txt').attr('data-tooltip', s.anzeige || null);   // Kompaktmodus: voller Name im Tooltip
    $chip.find('.ep-bem').remove();
    chipOkSetzen($chip, !!s.ok);
    if (s.bemerkung) $chip.append('<i class="bi bi-chat-left-text ep-bem" data-tooltip="' + msvEsc(s.bemerkung) + '"></i>');
    const neuName = chipExternName($chip);
    $chip.attr('draggable', (s.mitglied_id || neuName) ? 'true' : 'false');
    $chip.toggleClass('ep-vorschlag', !!s.vorschlag).attr('data-vorschlag', s.vorschlag ? '1' : '0');   // manuelle Änderung bestätigt einen Vorschlag
    if (typeof updateVorschlagButtons === 'function') updateVorschlagButtons();
    if (neuName && !MitgliederListe.eintrag(0, neuName).length) MitgliederListe.externHinzufuegen(neuName);   // im Panel neu getippter Externer
    MitgliederListe.verschiebe(altMid, parseInt(s.mitglied_id || 0, 10), altName, neuName);
    refreshSummary($chip.closest('td'));
  }
  // Besetzte MSV-Positionen (Mitglied oder Externer) sind ziehbar (entfernen / verschieben)
  $('.ep-chip[data-slot]').each(function () { this.setAttribute('draggable', (parseInt(this.dataset.mid || '0', 10) > 0 || chipExternName($(this))) ? 'true' : 'false'); });

  // Position setzen (gemeinsam für Drop aus Liste und Verschieben); ok() nach Erfolg; name = Externer ohne Mitglied
  function slotSetzen($chip, mid, verein, ok, name) {
    post('slot_save.php', { slot_id: $chip.data('slot'), verein: verein || 'msv', mitglied_id: mid || 0, name_text: mid ? '' : (name || ''), bemerkung: $chip.attr('data-bem') || '' }, r => {
      applySlotToChip($chip, r.slot);
      if (Slot.$chip && Slot.$chip.is($chip)) Slot.open($chip[0]);   // offenes Panel nachführen
      if (r.slot.warnung) msvToast('Mitglied ist ' + r.slot.warnung + ' – bitte prüfen', 'warning');
      if (typeof ok === 'function') ok(r.slot);
    });
  }
  $(document).on('dragstart', '.ep-chip[data-slot][draggable="true"]', function (e) {
    dragQuelle = this; dragName = chipExternName($(this));
    e.originalEvent.dataTransfer.setData('text/plain', this.dataset.mid);
    e.originalEvent.dataTransfer.effectAllowed = 'move';
    $(this).addClass('dragging');
    $('#epSide').addClass('ep-drop-remove');
  });
  $(document).on('dragend', '.ep-chip[data-slot]', function () { dragQuelle = null; $(this).removeClass('dragging'); $('.ep-chip').removeClass('ep-drop'); $('#epSide').removeClass('ep-drop-remove'); });
  // Ablegen auf der Liste = Position leeren (Obli/Feld) bzw. Position löschen (Chilbi)
  sideDropHandler = function () {
    if (!dragQuelle) return false;
    const $q = $(dragQuelle); dragQuelle = null;
    if (IST_A) slotSetzen($q, 0, $q.attr('data-verein'), () => msvToast('Mitglied von der Position entfernt', 'success'));
    else positionLoeschen($q, () => msvToast('Position entfernt', 'success'));
    return true;
  };
  // Chilbi: Ablegen auf «offen»/«+» = neue Position in dieser Zelle (aus der Liste oder verschoben)
  $(document).on('dragover', '.ep-chip.ep-ph, .ep-chip.ep-add', function (e) { e.preventDefault(); e.originalEvent.dataTransfer.dropEffect = 'copy'; $(this).addClass('ep-drop'); });
  $(document).on('dragleave', '.ep-chip.ep-ph, .ep-chip.ep-add', function () { $(this).removeClass('ep-drop'); });
  $(document).on('drop', '.ep-chip.ep-ph, .ep-chip.ep-add', function (e) {
    e.preventDefault();
    const $td = $(this).removeClass('ep-drop').closest('td');
    const mid = parseInt(e.originalEvent.dataTransfer.getData('text/plain') || '0', 10);
    const name = mid ? '' : dragName;
    const $q = dragQuelle ? $(dragQuelle) : null; dragQuelle = null; dragName = '';
    $('#epSide').removeClass('ep-drop-remove');
    if (!mid && !name) return;
    if ($q && $q.closest('td').is($td)) return;                     // in derselben Zelle
    positionAnlegen($td, mid, name, () => { if ($q) positionLoeschen($q, () => msvToast('Position verschoben', 'success')); });
  });

  $(document).on('dragover', '.ep-chip[data-slot]', function (e) { e.preventDefault(); e.originalEvent.dataTransfer.dropEffect = 'copy'; $(this).addClass('ep-drop'); });
  $(document).on('dragleave', '.ep-chip[data-slot]', function () { $(this).removeClass('ep-drop'); });
  $(document).on('drop', '.ep-chip[data-slot]', function (e) {
    e.preventDefault();
    const $chip = $(this).removeClass('ep-drop');
    const mid = parseInt(e.originalEvent.dataTransfer.getData('text/plain') || '0', 10);
    const name = mid ? '' : dragName;                               // Externer ohne Mitglieds-ID
    const $q = dragQuelle ? $(dragQuelle) : null; dragQuelle = null; dragName = '';
    $('#epSide').removeClass('ep-drop-remove');
    if (!mid && !name) return;
    if ($q && $q.is($chip)) return;                                  // auf sich selbst
    const zielMid = parseInt($chip.attr('data-mid') || '0', 10), zielName = chipExternName($chip);
    if (!$q && zielMid === mid && (mid || zielName === name)) return;
    if ($q) {
      // Verschieben: Ziel bekommt die Person, Quelle die bisherige Ziel-Person (Tausch) oder wird leer
      const zielBesetzt = zielMid > 0 || zielName !== '';
      slotSetzen($chip, mid, 'msv', () => {
        if (IST_A || zielBesetzt) slotSetzen($q, zielMid, zielBesetzt ? 'msv' : $q.attr('data-verein'), () => msvToast(zielBesetzt ? 'Positionen getauscht' : 'Person verschoben', 'success'), zielName);
        else positionLoeschen($q, () => msvToast('Person verschoben', 'success'));   // Chilbi: Quelle wird nicht leer, sondern verschwindet
      }, name);
    } else {
      slotSetzen($chip, mid, 'msv', null, name);
    }
  });
  $(document).on('click', '.ep-chip[data-slot]', function () { Slot.open(this); });
  $('#epSlotClose, #epSlotOverlay, #epSlotFertig').on('click', () => Slot.close());
  $(document).on('keydown', e => { if ($('#epSlotPanel').hasClass('open') && e.key === 'Escape') { Slot.close(); e.stopImmediatePropagation(); } });
  $('input[name=epVerein]').on('change', function () { Slot.vereinUi(this.value); if (this.value !== 'msv') $('#epMitglied').val('0'); Slot.save(); });
  $('#epMitglied').on('change', () => { if ($('#epMitglied').val() !== '0') $('#epNameText').val(''); Slot.save(); });
  $('#epNameText, #epBemerkung, #epOk').on('change', () => Slot.save());   // Auto-Save; #epOk nur bei Schlossturm im Panel
  $('#epNameText').on('input', function () { if (this.value.trim() !== '') $('#epMitglied').val('0'); });
  $('#epSlotLeeren').on('click', () => {
    if (IST_A) { $('#epMitglied').val('0'); $('#epNameText').val(''); $('#epBemerkung').val(''); Slot.save(); }
    else if (Slot.$chip) positionLoeschen(Slot.$chip, () => msvToast('Position entfernt', 'success'));
  });
  // Soll-Besetzung speichern (Chilbi)
  $('#epSollSpeichern').on('click', function () {
    const map = {};
    $('.ep-soll').each(function () { map[this.dataset.key] = parseInt(this.value || '0', 10) || 0; });
    post('struktur_save.php', { action: 'soll_save', soll: JSON.stringify(map) }, r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 500); });
  });
  // Layout A: Standardanzahl der Funktion als Vorbelegung markieren (blau = Abweichung)
  $('.ep-soll').on('input', function () { const std = parseInt($(this).closest('tr').find('.badge').text() || '0', 10); if (std) $(this).toggleClass('border-primary', parseInt(this.value || '0', 10) !== std); });
  $('#epMitgliedFilter').on('input', function () {
    const q = this.value.trim().toLowerCase();
    $('#epMitglied option').each(function () { if (this.value === '0') return; $(this).toggle(!q || this.text.toLowerCase().indexOf(q) !== -1); });
  });
<?php endif; ?>

<?php if ($plan && !$ansichtAbr && $plan['termine']): ?>
  // ---------- Anwesenheit erfassen (Desktop-Dialog; Daten aus den Chips, API wie im Portal; alle Layouts) ----------
  const VEREIN_LABEL = EP_VEREINE_JS;
  let anwTermin = 0;
  function chipAnwSetzen($chip, wert) {
    $chip.find('.ep-anw').remove();
    if (wert === 1) $chip.append('<i class="bi bi-check-circle-fill ep-anw ep-anw-da" data-tooltip="anwesend"></i>');
    else if (wert === 0) $chip.append('<i class="bi bi-x-circle-fill ep-anw ep-anw-nein" data-tooltip="nicht erschienen"></i>');
  }
  function anwZaehlen() {
    $('#anDa').text($('#epAnwListe .an-row.da').length); $('#anNein').text($('#epAnwListe .an-row.nein').length);
    $('#anOffen').text($('#epAnwListe .an-row:not(.da):not(.nein)').length);
  }
  function anwLaden(tid) {
    anwTermin = tid;
    $('#epAnwTermine button').removeClass('aktiv').filter('[data-termin="' + tid + '"]').addClass('aktiv');
    let html = '', letzteFn = null;
    $('#epGrid td[data-termin="' + tid + '"]').each(function () {
      const $td = $(this), fn = $td.attr('data-flabel');
      $td.children('.ep-chip[data-slot].ep-besetzt:not(.ep-vorschlag)').each(function () {
        const $c = $(this), wert = $c.find('.ep-anw-da').length ? 'da' : ($c.find('.ep-anw-nein').length ? 'nein' : '');
        if (letzteFn !== fn) { html += '<div class="an-fn" style="grid-column:1/-1">' + msvEsc(fn) + '</div>'; letzteFn = fn; }
        html += '<div class="an-row ' + wert + '" data-slot="' + $c.data('slot') + '"><span class="an-dot ' + msvEsc($c.attr('data-verein') || 'msv') + '"></span><div class="an-name">' + msvEsc($c.find('.ep-txt').text()) + '<small>' + (VEREIN_LABEL[$c.attr('data-verein')] || '') + '</small></div>'
              + '<button type="button" class="an-btn ja" data-wert="1" aria-label="da"><i class="bi bi-check-lg"></i></button><button type="button" class="an-btn no" data-wert="0" aria-label="nicht da"><i class="bi bi-x-lg"></i></button></div>';
      });
    });
    $('#epAnwListe').html(html || '<div class="text-muted small py-3" style="grid-column:1/-1">In dieser Schicht ist niemand fest eingeteilt.</div>');
    anwZaehlen();
  }
  function anwSenden(data, ok) {
    $.post('../api/einsatz_anwesenheit.php', Object.assign({ csrf_token: CSRF }, data), null, 'json')
      .done(r => { if (r && r.success) ok(r); else msvToast((r && (r.message || r.error)) || 'Fehler', 'error'); })
      .fail(xhr => msvToast(msvXhrMessage(xhr, 'Anwesenheit konnte nicht gespeichert werden'), 'error'));
  }
  $('#epAnwModal').on('show.bs.modal', function () {
    const $akt = $('#epAnwTermine button.aktiv'); anwLaden(parseInt(($akt.length ? $akt : $('#epAnwTermine button').first()).data('termin') || 0, 10));
  });
  $('#epAnwTermine').on('click', 'button', function () { anwLaden(parseInt(this.dataset.termin, 10)); });
  $('#epAnwListe').on('click', '.an-btn', function () {
    const $row = $(this).closest('.an-row'), wert = this.dataset.wert;
    const aktiv = (wert === '1' && $row.hasClass('da')) || (wert === '0' && $row.hasClass('nein'));
    anwSenden({ action: 'set', slot_id: $row.data('slot'), anwesend: aktiv ? '' : wert }, r => {
      $row.removeClass('da nein'); if (r.anwesend === 1) $row.addClass('da'); else if (r.anwesend === 0) $row.addClass('nein');
      chipAnwSetzen($('#epGrid .ep-chip[data-slot="' + $row.data('slot') + '"]'), r.anwesend); anwZaehlen();
    });
  });
  $('#anAlleDa').on('click', function () {
    anwSenden({ action: 'alle', plan_id: PLAN_ID, termin_id: anwTermin, anwesend: '1' }, r => {
      $('#epAnwListe .an-row:not(.da):not(.nein)').each(function () { $(this).addClass('da'); chipAnwSetzen($('#epGrid .ep-chip[data-slot="' + $(this).data('slot') + '"]'), 1); });
      anwZaehlen(); msvToast(r.message, 'success');
    });
  });

<?php endif; ?>

<?php if ($plan && !$ansichtAbr && $plan['typ'] === 'schlossturm'): ?>
  // ---------- OK-Mitglieder (Dialog): Häkchen je Person → alle Positionen der Person im Plan ----------
  // OK im Plan (alle Positionen der Person) bzw. dauerhaft (Stammliste, setzt zugleich OK im Plan)
  function okPersonSpeichern(cb, daten) {
    const $tr = $(cb).closest('tr'), $plan = $tr.find('.ep-ok-toggle');
    cb.disabled = true;
    msvPost(basePath + 'ok_person_save.php', Object.assign({ csrf_token: CSRF, plan_id: PLAN_ID, mitglied_id: parseInt(cb.dataset.mid || '0', 10), name_text: parseInt(cb.dataset.mid || '0', 10) ? '' : (cb.dataset.name || '') }, daten), r => {
      if (r.ok !== null && r.ok !== undefined) {
        (r.slot_ids || []).forEach(id => chipOkSetzen($('.ep-chip[data-slot="' + id + '"]'), !!r.ok));
        $('#epGrid td[data-termin]').each(function () { refreshSummary($(this)); });
        if (!$plan.prop('disabled')) $plan.prop('checked', !!r.ok);
      }
      $('#epOkAnzahl').text($('#epOkModal .ep-ok-toggle:checked').length);
      msvToast(r.message, 'success');
    }, { csrf: CSRF, failMsg: 'OK konnte nicht gespeichert werden', fail: () => { cb.checked = !cb.checked; } }).always(() => { cb.disabled = false; });
  }
  $('#epOkModal').on('change', '.ep-ok-toggle', function () { okPersonSpeichern(this, { ok: this.checked ? 1 : 0 }); });
  $('#epOkModal').on('change', '.ep-ok-stamm',  function () { okPersonSpeichern(this, { dauerhaft: this.checked ? 1 : 0 }); });
  // Funktion «immer vom OK besetzt» (Definition für alle Jahre) – Chips sofort nachführen, Personenliste beim Schliessen neu laden
  let okFnGeaendert = false;
  $('#epOkModal').on('change', '.ep-ok-fn', function () {
    const cb = this; cb.disabled = true;
    msvPost(basePath + 'ok_funktion_save.php', { csrf_token: CSRF, plan_id: PLAN_ID, funktion_id: cb.dataset.fid, ok: cb.checked ? 1 : 0 }, r => {
      okFnGeaendert = true;
      $('#epGrid tr[data-fid]').each(function () { const fid = this.dataset.fid; if (fid in r.funktionen) this.dataset.okfn = r.funktionen[fid] ? '1' : '0'; });
      (r.slots || []).forEach(s => { const $c = $('.ep-chip[data-slot="' + s.id + '"]'); if (!$c.length) return; $c.attr('data-okfn', r.funktionen[s.funktion_id] ? '1' : '0'); chipOkSetzen($c, !!s.ok); });
      $('#epGrid td[data-termin]').each(function () { refreshSummary($(this)); });
      msvToast(r.message, 'success');
    }, { csrf: CSRF, failMsg: 'Definition konnte nicht gespeichert werden', fail: () => { cb.checked = !cb.checked; } }).always(() => { cb.disabled = false; });
  });
  $('#epOkModal').on('hidden.bs.modal', () => { if (okFnGeaendert) location.reload(); });
  $('#epOkFilter').on('input', function () {
    const q = this.value.trim().toLowerCase();
    $('#epOkModal tbody tr[data-suche]').each(function () { $(this).toggle(!q || this.dataset.suche.indexOf(q) >= 0); });
  });
  $('#epOkModal').on('shown.bs.modal', () => $('#epOkFilter').trigger('focus'));
<?php endif; ?>

<?php if ($plan && !$ansichtAbr && $plan['layout'] === 'funktion_x_termin'): ?>
  // ---------- Verfügbarkeiten: Anzeige in Liste und Panel ----------
  const EP_VERF = <?= json_encode(array_column(array_map(fn($v) => ['k' => $v['person_key'], 'r' => $v['rollen'], 't' => array_map('intval', $v['termin_ids']), 'n' => $v['name']], $plan['verfuegbarkeit']), null, 'k'), JSON_UNESCAPED_UNICODE) ?>;
  const EP_TERMIN_LABEL = <?= json_encode(array_column(array_map(fn($t) => ['id' => (int)$t['id'], 'l' => ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t)], $plan['termine']), 'l', 'id')) ?>;
  const personKey = (mid, name) => mid > 0 ? 'm' + mid : 'n' + String(name || '').trim().replace(/\s+/g, ' ').toLowerCase();
  const verfVon = (mid, name) => EP_VERF[personKey(mid, name)] || null;
  $('#epVerfFilter').on('change', function () {
    const f = this.value;
    $('#epSideList .ep-mitglied').each(function () {
      const v = verfVon(parseInt(this.dataset.mid || '0', 10), this.dataset.name);
      let ok = true;
      if (f === 'alle') ok = !!(v && v.t.length && v.r.length);
      else if (f) ok = !!(v && v.t.indexOf(parseInt(f, 10)) !== -1 && v.r.length);
      $(this).toggleClass('ep-nv', !ok);
      if (v) $(this).attr('data-tooltip', 'Verfügbar: ' + v.r.join(', ') + ' · ' + v.t.map(id => EP_TERMIN_LABEL[id] || id).join(' | '));
    });
    if (f) $('#epSideList .ep-side-group').removeClass('ep-collapsed');
  });
  // Badge im Panel: ist das gewählte Mitglied für die Schicht der Position gemeldet?
  function verfBadge() {
    const $b = $('#epVerfBadge'); if (!$b.length) return;
    if (!Object.keys(EP_VERF).length) { $b.text(''); return; }
    const mid = parseInt($('#epMitglied').val() || '0', 10);
    const $td = Slot.$chip ? Slot.$chip.closest('td') : (Slot.$addTd || $());
    const tid = parseInt($td.data('termin') || '0', 10);
    if (!mid) { $b.text(''); return; }
    const v = verfVon(mid, '');
    if (!v) { $b.html('<i class="bi bi-question-circle me-1"></i>keine Verfügbarkeit gemeldet'); return; }
    const ok = tid && v.t.indexOf(tid) !== -1;
    $b.html('<i class="bi ' + (ok ? 'bi-check-circle text-success' : 'bi-exclamation-circle text-warning') + ' me-1"></i>' + (ok ? 'verfügbar in dieser Schicht' : 'für diese Schicht nicht gemeldet') + ' · ' + msvEsc(v.r.join(', ') || 'keine Rolle'));
  }
  $('#epMitglied').on('change', verfBadge);
  $(document).on('click', '.ep-chip[data-slot], .ep-chip.ep-add', () => setTimeout(verfBadge, 0));

  // ---------- Einteilungs-Vorschläge: Übernehmen / Verwerfen ----------
  function updateVorschlagButtons() {
    const n = $('#epGrid .ep-chip.ep-vorschlag').length;
    $('.ep-toolbar .js-vorschlaege-group').toggleClass('d-none', n === 0);
    $('.ep-vs-n').text(n);
  }
  $(document).on('click', '.js-vorschlaege', function () {
    const aktion = $(this).data('aktion');
    msvConfirm(aktion === 'uebernehmen' ? 'Alle Vorschläge (blau gestrichelt) werden als feste Einteilung übernommen.' : 'Alle Vorschläge werden verworfen, die Positionen sind danach wieder leer.',
      aktion === 'uebernehmen' ? 'Vorschläge übernehmen?' : 'Vorschläge verwerfen?', 'Ja').then(res => {
      if (!res.isConfirmed) return;
      post('einteilung_vorschlag.php', { action: aktion }, r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 600); });
    });
  });
  $('#epSlotVorschlagOk').on('click', function () {
    if (!Slot.$chip) return;
    const $chip = Slot.$chip;
    post('einteilung_vorschlag.php', { action: 'uebernehmen', slot_ids: JSON.stringify([$chip.data('slot')]) }, r => {
      $chip.removeClass('ep-vorschlag').attr('data-vorschlag', '0'); $('#epSlotVorschlagOk').addClass('d-none'); updateVorschlagButtons(); msvToast('Vorschlag übernommen', 'success');
    });
  });

  // ---------- Einteilung berechnen ----------
  $('#epEtTrotzdem').on('change', function () { $('#epEtBerechnen').prop('disabled', !this.checked); });
  $('#epEtBerechnen').on('click', function () {
    const $b = $(this).prop('disabled', true);
    $('#epEtErgebnis').html('<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Berechne…</div>');
    post('einteilung_vorschlag.php', { action: 'berechnen', kontinuitaet: $('#epEtKonti').is(':checked') ? 1 : 0, verwerfen: $('#epEtVerwerfen').is(':checked') ? 1 : 0, verein_beachten: $('#epEtVerein').is(':checked') ? 1 : 0 }, r => {
      let html = '<div class="alert alert-info py-2 px-3 small mb-2"><i class="bi bi-info-circle me-1"></i>' + msvEsc(r.message) + '</div>';
      (r.warnungen || []).forEach(w => html += '<div class="alert alert-warning py-1 px-2 small mb-1">' + msvEsc(w) + '</div>');
      html += '<div class="row g-3"><div class="col-lg-7"><h6 class="small text-uppercase text-muted mb-1">Besetzung je Schicht (Ist / Ziel, inkl. feste und vorgeschlagene)</h6><table class="table table-sm table-bordered ep-einteilung-tabelle mb-2"><thead><tr><th class="text-start">Schicht</th><th>Positionen</th><th>MSV Wilen</th><th>SV Freienbach</th><th>SV Wollerau</th></tr></thead><tbody>';
      Object.values(r.tabelle || {}).forEach(t => {
        html += '<tr><td class="text-start">' + msvEsc(t.termin) + '</td><td>' + t.positionen + '</td>';
        ['msv', 'freienbach', 'wollerau'].forEach(v => { const z = t.vereine[v]; html += '<td class="' + (z.ist < z.ziel - 0.5 ? 'ep-fehlt' : '') + '">' + z.ist + ' / ' + z.ziel + '</td>'; });
        html += '</tr>';
      });
      html += '</tbody></table></div><div class="col-lg-5">';
      html += '<h6 class="small text-uppercase text-muted mb-1">Offen (' + (r.offen || []).length + ')</h6><div class="ep-preview small mb-2" style="max-height:180px">' + ((r.offen || []).map(o => '<div>' + msvEsc(o.termin + ' · ' + o.funktion + ' #' + o.pos) + ' <span class="text-muted">– ' + msvEsc(o.grund) + '</span></div>').join('') || '<span class="text-muted">keine</span>') + '</div>';
      html += '<h6 class="small text-uppercase text-muted mb-1">Verfügbar, aber nicht eingeteilt (' + (r.ungenutzt || []).length + ')</h6><div class="ep-preview small" style="max-height:140px">' + ((r.ungenutzt || []).map(u => '<div>' + msvEsc(u.person) + ' <span class="text-muted">(' + msvEsc(u.verein + ', ' + u.rollen.join('/') + ', ' + u.angeboten + ' Schichten') + ')</span></div>').join('') || '<span class="text-muted">keine</span>') + '</div></div></div>';
      html += '<h6 class="small text-uppercase text-muted mt-2 mb-1">Vorschläge (' + (r.vorschlaege || []).length + ')</h6><div class="ep-preview" style="max-height:220px"><table class="table table-sm table-hover mb-0"><thead><tr><th>Schicht</th><th>Funktion</th><th>Verein</th><th>Person</th><th>Begründung</th></tr></thead><tbody>';
      (r.vorschlaege || []).forEach(v => html += '<tr><td>' + msvEsc(v.termin) + '</td><td>' + msvEsc(v.funktion) + '</td><td>' + msvEsc(v.verein) + '</td><td class="fw-semibold">' + msvEsc(v.person) + '</td><td class="small text-muted">' + msvEsc(v.grund) + '</td></tr>');
      html += '</tbody></table></div>';
      $('#epEtErgebnis').html(html);
      $('#epEinteilungModal .js-vorschlaege').toggleClass('d-none', !(r.vorschlaege || []).length);
      $('#epEtSchliessen').off('click.reload').on('click.reload', () => location.reload());
    }).always(() => $b.prop('disabled', false));
  });

  // ---------- Verfügbarkeiten: manuell, Umfrage, Excel ----------
  function verfDaten($tr) {
    return { id: $tr.data('id'), verein: $tr.find('.v-verein').val(), mitglied_id: $tr.data('id') > 0 ? ($tr.data('mid') || 0) : ($tr.find('.v-mitglied').val() || 0),
             name_text: $tr.data('id') > 0 ? ($tr.data('name') || '') : ($tr.find('.v-name').val() || ''),
             rollen: $tr.find('.v-rolle:checked').map(function () { return this.value; }).get(),
             termin_ids: $tr.find('.v-termin:checked').map(function () { return this.value; }).get(), bemerkung: $tr.find('.v-bem').val() || '' };
  }
  $('#epVerfBody').on('click', '.js-verf-save', function () {
    const $tr = $(this).closest('tr');
    post('verfuegbarkeit_save.php', Object.assign({ action: 'save' }, verfDaten($tr)), r => { msvToast(r.message, 'success'); if (!($tr.data('id') > 0)) setTimeout(() => location.reload(), 500); });
  });
  $('#epVerfBody').on('click', '.js-verf-delete', function () {
    const $tr = $(this).closest('tr');
    msvConfirmDelete('die Verfügbarkeit von ' + msvEsc($tr.find('td').first().text().trim())).then(res => {
      if (!res.isConfirmed) return;
      post('verfuegbarkeit_save.php', { action: 'delete', id: $tr.data('id') }, r => { $tr.remove(); msvToast(r.message, 'success'); });
    });
  });
  $('#epVerfUmfrage').on('click', function () {
    const $b = $(this).prop('disabled', true);
    post('verfuegbarkeit_import.php', { action: 'umfrage' }, r => {
      let html = '<div class="alert alert-success py-2 px-3 small mb-1">' + msvEsc(r.message) + '</div>';
      (r.hinweise || []).forEach(h => html += '<div class="alert alert-warning py-1 px-2 small mb-1">' + msvEsc(h) + '</div>');
      $('#epVerfImportMeldungen').html(html); $('#epVerfImportPreview').empty(); $('#epVerfImportBox').removeClass('d-none'); $('#epVerfImportSave').addClass('d-none');
      $('#epVerfImportCancel').text('Neu laden').off('click.rl').on('click.rl', () => location.reload());
    }).always(() => $b.prop('disabled', false));
  });
  let verfVorschau = [];
  $('#epVerfExcelForm').on('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this); fd.append('action', 'excel_parse'); fd.append('plan_id', PLAN_ID); fd.append('csrf_token', CSRF);
    const $btn = $(this).find('button[type=submit]').prop('disabled', true);
    $.ajax({ url: basePath + 'verfuegbarkeit_import.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
      .done(r => {
        if (!r || !r.success) { msvToast((r && r.message) || 'Excel konnte nicht gelesen werden', 'error'); return; }
        verfVorschau = r.vorschau || [];
        let html = '<div class="alert alert-info py-2 px-3 small mb-1">' + msvEsc(r.message) + '</div>';
        (r.warnungen || []).forEach(w => html += '<div class="alert alert-warning py-1 px-2 small mb-1">' + msvEsc(w) + '</div>');
        $('#epVerfImportMeldungen').html(html);
        let t = '<table class="table table-sm table-hover mb-0"><thead><tr><th style="width:30px"><input type="checkbox" id="epVerfAlle" checked></th><th>Name (Excel)</th><th>Mitglied</th><th>Rollen</th><th>Schichten</th><th>Hinweis</th></tr></thead><tbody>';
        verfVorschau.forEach((z, i) => t += '<tr><td><input type="checkbox" class="ep-verf-cb" data-i="' + i + '" checked></td><td>' + msvEsc(z.name) + '</td><td>' + (z.mitglied_id ? '<span class="text-success">' + msvEsc(z.matched_name) + (z.match === 'fuzzy' ? ' <small class="text-warning">(unsicher)</small>' : '') + '</span>' : '<span class="text-muted">' + (z.verein === 'msv' ? 'kein Mitglied – Klartext' : 'Klartext') + '</span>') + '</td><td>' + msvEsc(z.rollen.join(', ')) + '</td><td>' + z.termin_ids.map(id => msvEsc(EP_TERMIN_LABEL[id] || id)).join('<br>') + '</td><td class="small text-warning">' + msvEsc(z.hinweis || '') + '</td></tr>');
        $('#epVerfImportPreview').html(t + '</tbody></table>');
        $('#epVerfImportBox').removeClass('d-none'); $('#epVerfImportSave').removeClass('d-none').data('verein', fd.get('verein'));
        $('#epVerfImportCancel').text('Abbrechen').off('click.rl').on('click.rl', () => $('#epVerfImportBox').addClass('d-none'));
      })
      .fail(xhr => msvToast(msvXhrMessage(xhr, 'Excel konnte nicht gelesen werden'), 'error'))
      .always(() => $btn.prop('disabled', false));
  });
  $(document).on('change', '#epVerfAlle', function () { $('.ep-verf-cb').prop('checked', this.checked); });
  $('#epVerfImportSave').on('click', function () {
    const zeilen = $('.ep-verf-cb:checked').map(function () { return verfVorschau[$(this).data('i')]; }).get();
    if (!zeilen.length) { msvToast('Nichts ausgewählt', 'info'); return; }
    post('verfuegbarkeit_import.php', { action: 'excel_save', verein: $(this).data('verein') || 'msv', zeilen: JSON.stringify(zeilen) }, r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 700); });
  });

  // ---------- Rückmeldung einlesen ----------
  let rueckVorschlaege = [];
  $('#epRueckForm').on('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this); fd.append('plan_id', PLAN_ID); fd.append('csrf_token', CSRF);
    const $btn = $(this).find('button[type=submit]').prop('disabled', true);
    $('#epRueckMeldungen').empty(); $('#epRueckPreview').empty(); $('#epRueckUebernehmen').addClass('d-none');
    $.ajax({ url: basePath + 'rueckmeldung_parse.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
      .done(r => {
        if (!r || !r.success) { msvToast((r && r.message) || 'Datei konnte nicht gelesen werden', 'error'); return; }
        rueckVorschlaege = r.vorschlaege || [];
        (r.warnungen || []).forEach(w => $('#epRueckMeldungen').append('<div class="alert alert-warning py-1 px-2 mb-1 small"><i class="bi bi-exclamation-triangle me-1"></i>' + msvEsc(w) + '</div>'));
        if (!rueckVorschlaege.length) { $('#epRueckMeldungen').append('<div class="alert alert-info py-1 px-2 small mb-0">Keine Änderungen gegenüber dem Plan gefunden.</div>'); return; }
        let html = '<table class="table table-sm table-hover mb-0"><thead><tr><th style="width:30px"><input type="checkbox" id="epRueckAlle" checked></th><th>Termin</th><th>Funktion</th><th>Verein</th><th>bisher</th><th>neu</th><th>Hinweis</th></tr></thead><tbody>';
        rueckVorschlaege.forEach((v, i) => {
          html += '<tr><td><input type="checkbox" class="ep-rueck-cb" data-i="' + i + '" ' + (v.vorschlag ? 'checked' : '') + '></td><td>' + msvEsc(v.termin) + '</td><td>' + msvEsc(v.funktion) + '</td><td>' + msvEsc(v.verein_label) + '</td><td class="text-muted">' + msvEsc(v.alt || '–') + '</td><td class="fw-semibold">' + msvEsc(v.neu || '–') + '</td><td class="small text-muted">' + msvEsc(v.hinweis || '') + '</td></tr>';
        });
        $('#epRueckPreview').html(html + '</tbody></table>');
        $('#epRueckUebernehmen').removeClass('d-none');
      })
      .fail(xhr => msvToast(msvXhrMessage(xhr, 'Datei konnte nicht gelesen werden'), 'error'))
      .always(() => $btn.prop('disabled', false));
  });
  $(document).on('change', '#epRueckAlle', function () { $('.ep-rueck-cb').prop('checked', this.checked); });
  $('#epRueckUebernehmen').on('click', function () {
    const aenderungen = $('.ep-rueck-cb:checked').map(function () { const v = rueckVorschlaege[$(this).data('i')]; return { slot_id: v.slot_id, name_text: v.neu }; }).get();
    if (!aenderungen.length) { msvToast('Nichts ausgewählt', 'info'); return; }
    post('rueckmeldung_save.php', { aenderungen: JSON.stringify(aenderungen) }, r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 700); });
  });
<?php endif; ?>
});
</script>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray): PDF der Einsatzliste über export_pdf.php (JSON mit «link» relativ zu inc/einsatzplanung/)
(function () {
  if (!window.MsvDruck) return;
  const basePath = (/\/inc(\/|$)/.test(location.pathname)) ? 'einsatzplanung/' : 'inc/einsatzplanung/';
  MsvDruck.resolve('einsatzplan', btn => ({
    url: basePath + 'export_pdf.php?plan_id=' + encodeURIComponent(btn.dataset.plan || 0),
    jobName: 'Einsatzplan ' + (btn.dataset.titel || ''),
    linkPrefix: basePath,
    orientation: btn.dataset.typ === 'schlossturm' ? 'portrait' : 'landscape'   // Schlossturm-Liste ist A4 hoch
  }));
})();
</script>

<?php include 'footer.inc.php'; ?>
