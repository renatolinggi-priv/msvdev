<?php
// jmresultate.php – JM-Resultate Anlass für Anlass erfassen (Karten + Slide-Panel), Kontroll-Rangliste Kat. A/B
include 'dbconnect.inc.php';

// Seitenspezifische Styles: nur Aufbau dieser Seite; die Optik kommt aus css/msv-ui.css
$page_specific_css = "
/* Seite scrollt normal: Kopf-Card, Anlässe, zwei Kontroll-Ranglisten */
.jm-karte { margin-bottom: 14px; overflow: hidden; }
.jm-kopf-hinweis { font-size: .8rem; color: var(--ui-text-2); }

/* Anlass-Karten */
.jm-anlaesse { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; padding: 14px var(--ui-pad) var(--ui-pad); }
.jm-anlass { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; min-width: 0; padding: 10px 12px; text-align: left; color: var(--ui-text); background: var(--ui-flaeche); border: 1px solid var(--ui-rand); border-radius: var(--ui-rad); cursor: pointer; transition: border-color .15s, background-color .15s; }
.jm-anlass:hover { background: var(--ui-flaeche-2); border-color: #c5ccd6; }
.jm-anlass:focus-visible { outline: 2px solid var(--ui-akzent); outline-offset: 2px; }
.jm-anlass.selected { background: var(--ui-gewaehlt); border-color: var(--ui-akzent); box-shadow: 0 0 0 1px var(--ui-akzent); }
.jm-anlass-name { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .875rem; font-weight: 600; }
.jm-anlass-zeile { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.jm-anlass-zahl { font-size: .8rem; color: var(--ui-text-2); font-variant-numeric: tabular-nums; }
.jm-anlass-zahl b { font-weight: 700; color: var(--ui-text); }
.jm-leer { grid-column: 1 / -1; padding: 24px 8px; text-align: center; color: var(--ui-text-2); }

/* Erfassungs-Panel eines Anlasses (Container .anlass-panel zentral in css/msv-styles.css) */
.anlass-panel-overlay { position: fixed; inset: 0; z-index: 1055; background: rgba(26, 35, 50, .28); opacity: 0; visibility: hidden; transition: opacity .3s, visibility .3s; }
.anlass-panel-overlay.show { opacity: 1; visibility: visible; }
#anlassPanel .panel-header h6 { font-size: 1.15rem; line-height: 1.2; }
#anlassPanel .panel-pos { color: var(--ui-text-2); font-size: .8rem; }
#anlassPanel .panel-footer .ui-kbd { margin: 0 2px; }
.jm-panel-suche { display: flex; align-items: center; gap: 12px; flex-shrink: 0; padding: 10px 20px; background: var(--ui-flaeche); border-bottom: 1px solid var(--ui-linie); }
.jm-panel-suche .ui-suche { flex: 1 1 auto; width: auto; margin: 0; }
.jm-panel-zaehler { flex: none; white-space: nowrap; font-size: .8rem; color: var(--ui-text-2); }
.jm-panel-zaehler b { color: var(--ui-text); }
.anlass-panel-body { flex: 1; overflow-y: auto; }
.anlass-group-header { padding: 6px 20px; background: var(--ui-grund); border-bottom: 1px solid var(--ui-linie); color: var(--ui-text-2); font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
.jm-zeile { display: flex; align-items: center; gap: 12px; min-height: 48px; padding: 6px 20px; border-bottom: 1px solid var(--ui-linie-zart); }
.jm-zeile:hover { background: var(--ui-flaeche-2); }
.jm-pos { flex: none; width: 24px; text-align: right; font-size: .75rem; color: var(--ui-text-3); font-variant-numeric: tabular-nums; }
.jm-name { flex: 1 1 auto; min-width: 0; font-size: .9rem; font-weight: 500; color: var(--ui-text); }
.jm-name .geloest-pill { margin-left: 6px; vertical-align: 1px; }
.jm-runden { display: flex; flex: none; gap: 10px; }
.jm-runde { display: inline-flex; align-items: center; gap: 5px; margin: 0; font-size: .72rem; font-weight: 600; color: var(--ui-text-2); }
.jm-feld.form-control { width: 76px; height: 34px; padding: 0 6px; text-align: center; font-size: 1rem; font-weight: 600; font-variant-numeric: tabular-nums; background-color: var(--ui-flaeche-2); border-color: #dde3ea; border-radius: 6px; }
.jm-runde .jm-feld.form-control { width: 64px; }
.jm-feld.form-control.filled { background-color: var(--ui-flaeche); border-color: #c5ccd6; }
.jm-feld.form-control.jm-gemeldet { background-color: var(--ui-warn-zeile); border-color: var(--ui-warn-rand); }
.jm-feld.form-control:focus { background-color: var(--ui-gewaehlt); border-color: var(--ui-akzent-dunkel); box-shadow: 0 0 0 1px var(--ui-akzent-dunkel); }
.jm-feld.form-control.is-invalid { background-color: var(--ui-fehler-bg); border-color: var(--ui-fehler); box-shadow: 0 0 0 1px var(--ui-fehler); }
.jm-wert { flex: none; min-width: 60px; text-align: center; font-weight: 700; color: var(--ui-text); }
.jm-panel-leer { padding: 32px 16px; text-align: center; color: var(--ui-text-2); }
@media (max-width: 767.98px) {
    .anlass-panel-overlay { display: none !important; }
    #anlassPanel .panel-footer .btn { min-height: 48px; }
    .jm-feld.form-control { height: 44px; font-size: 16px; }
    .jm-panel-suche .ui-suche { height: 40px; }
}

/* ===== Kontroll-Ranglisten Kat. A / B (Inhalt aus jmrang/load_jm.php) ===== */
.jm-karte .table-responsive { max-height: 60vh !important; min-height: 0 !important; overflow: auto !important; }
.jm-legende { margin-left: auto; font-size: .8rem; color: var(--ui-text-2); }
#rankJMA, #rankJMB { border-collapse: separate !important; border-spacing: 0 !important; }
#rankJMA tbody td, #rankJMB tbody td { vertical-align: middle; border-top: none !important; border-right: none !important; border-bottom: 1px solid var(--ui-linie-zart) !important; }
#rankJMA thead, #rankJMB thead { position: sticky !important; top: 0 !important; z-index: 11 !important; }
#rankJMA thead th, #rankJMB thead th {
    position: sticky !important; top: 0 !important; z-index: 10 !important;
    background-color: var(--ui-flaeche-2) !important; color: var(--ui-text-2);
    vertical-align: bottom !important; writing-mode: horizontal-tb !important; text-orientation: initial !important;
    height: auto !important; min-width: auto !important; max-width: none !important; white-space: normal !important; overflow: visible !important;
    font-size: .75rem !important; font-weight: 600 !important; text-transform: none !important; letter-spacing: 0 !important;
    padding: 8px 6px !important; border-top: none !important; border-bottom: none !important; box-shadow: inset 0 -1px 0 var(--ui-linie) !important;
}
.jm-th-rang  { width: 55px !important; }
.jm-th-result{ width: 84px !important; }
.jm-th-total { width: 90px !important; }
.jm-th-toggle{ width: 40px !important; }
/* Lange Anlass-Namen in der Kopfzeile kürzen (voller Name via Tooltip) */
.jm-th-label { display: block; max-width: 78px; margin: 0 auto; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.jm-main-row { cursor: pointer; }
#rankJMA tbody tr.jm-main-row:hover td, #rankJMB tbody tr.jm-main-row:hover td { background-color: var(--ui-flaeche-2) !important; }
#rankJMA .jm-group-cell, #rankJMB .jm-group-cell { padding: 6px 12px !important; background: var(--ui-grund) !important; color: var(--ui-text-2); font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
#rankJMA .jm-group-cell i, #rankJMB .jm-group-cell i { display: none; }
.jm-result-cell   { font-variant-numeric: tabular-nums; }
.jm-cell-strichen { color: var(--ui-k-rot); text-decoration: line-through; }
.jm-total-cell    { color: var(--ui-text); font-variant-numeric: tabular-nums; font-size: 1rem; }
.jm-rang-cell     { color: var(--ui-text-2); }
.jm-toggle-btn { color: var(--ui-text-3) !important; text-decoration: none !important; font-size: 1rem !important; }
.jm-toggle-btn i { transition: transform .2s ease; }
.jm-toggle-btn.expanded i { transform: rotate(180deg); }
.jm-toggle-btn:hover { color: var(--ui-akzent) !important; }

/* Aufschlüsselung (Detail-Zeile) */
.jm-detail-row > td { padding: 0 !important; border-top: none !important; width: auto !important; text-align: left !important; background-color: transparent !important; font-weight: normal !important; }
.jm-detail-panel { padding: 14px 20px !important; text-align: left !important; background: var(--ui-grund) !important; border-top: 1px solid var(--ui-linie) !important; border-bottom: 1px solid var(--ui-rand) !important; }
.jm-detail-groups { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; align-items: start; }
.jm-detail-group { overflow: hidden; background: var(--ui-flaeche); border: 1px solid var(--ui-rand); border-radius: 10px; }
.jm-detail-group-head { display: flex; justify-content: space-between; align-items: baseline; padding: 8px 12px; background: var(--ui-flaeche-2); border-bottom: 1px solid var(--ui-linie); }
.jm-detail-group-title { font-size: .82rem; font-weight: 600; color: var(--ui-text); }
.jm-detail-group-meta  { font-size: .72rem; color: var(--ui-text-2); }
.jm-detail-lines { padding: 4px 6px; }
.jm-detail-line { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; padding: 5px 8px; border-radius: 6px; font-size: .85rem; }
.jm-detail-line + .jm-detail-line { border-top: 1px solid var(--ui-linie-zart); }
.jm-detail-line:hover { background: var(--ui-flaeche-2); }
.jm-line-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--ui-text); }
.jm-line-pts  { display: inline-flex; align-items: baseline; gap: .35rem; flex-shrink: 0; white-space: nowrap; }
.jm-line-val  { font-weight: 700; color: var(--ui-text); font-variant-numeric: tabular-nums; }
.jm-line-max  { font-size: .72rem; color: var(--ui-text-3); }
.jm-line-empty .jm-line-name,
.jm-line-empty .jm-line-val { font-weight: 400; color: #b8c0cc; }
.jm-detail-line.gestrichen { opacity: .75; }
.jm-detail-line.gestrichen .jm-line-val { color: var(--ui-k-rot); text-decoration: line-through; }
.jm-line-tag { padding: 1px 6px; border-radius: 6px; background: #fdecea; color: var(--ui-k-rot); font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.jm-detail-subtotal { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: var(--ui-flaeche-2); border-top: 1px solid var(--ui-linie); font-size: .8rem; font-weight: 600; color: var(--ui-text-2); }
.jm-detail-subtotal span:last-child { font-weight: 700; color: var(--ui-text); font-variant-numeric: tabular-nums; }
.jm-detail-total { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; padding: 10px 14px; background: var(--ui-ok-bg); border: 1px solid var(--ui-ok-rand); border-radius: var(--ui-rad); font-size: .95rem; font-weight: 700; color: var(--ui-ok-fg); }
.jm-detail-total-val { font-size: 1.1rem; color: var(--ui-ok-fg); font-variant-numeric: tabular-nums; }
.jm-detail-total.jm-detail-total-offen { background: var(--ui-flaeche-2); border-color: var(--ui-rand); color: var(--ui-text-2); font-size: .85rem; font-weight: 600; }

@media (max-width: 767.98px) {
    .jm-legende { margin-left: 0; }
    .jm-detail-groups { grid-template-columns: 1fr !important; }
    .jm-mobile-card .mobile-card-header { padding: .75rem 1rem; }
    .jm-mobile-rang { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 28px; height: 28px; border-radius: 50%; background: var(--ui-flaeche-2); border: 1px solid var(--ui-rand); font-size: .85rem; font-weight: 700; color: var(--ui-text-2); }
    .rank-1 .jm-mobile-rang { background: #ffd700; border-color: #ffd700; color: #5a4800; }
    .rank-2 .jm-mobile-rang { background: #c0c0c0; border-color: #c0c0c0; color: #3a3a3a; }
    .rank-3 .jm-mobile-rang { background: #cd7f32; border-color: #cd7f32; color: #fff; }
    .jm-mobile-total { white-space: nowrap; font-size: .95rem; font-weight: 700; color: var(--ui-text); }
    .jm-mobile-card .mobile-card-body { padding: 0 !important; }
    .jm-mobile-card .mobile-card-body .jm-detail-panel { padding: .75rem !important; border-top: none !important; border-bottom: none !important; }
    .rank-table-wrapper .desktop-table-container { display: none !important; }
    .rank-table-wrapper .mobile-cards-container { display: flex !important; }
}
@media (min-width: 768px) {
    .rank-table-wrapper .mobile-cards-container { display: none !important; }
}
";

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
$csrf = csrf_token();

// Serverseitige Vorberechnung -> Anlass-Karten + Veröffentlichen-Knopf sofort anzeigen
// (kein AJAX-Flackern). Fallback: JS lädt per AJAX.
require_once __DIR__ . '/jmresultate/anlaesse_data.php';
require_once __DIR__ . '/changelog_helper.php';
require_once __DIR__ . '/jahr.inc.php';
$__jmInitYear = msvJahrStandard(isset($conn) && $conn instanceof mysqli ? msvJahreMitDaten($conn) : null);
$__jmInitAnlaesse = ['anlaesse' => [], 'totalMembers' => 0];
$__jmUnpublished = 0;
try {
    if (isset($conn) && $conn instanceof mysqli) {
        $__jmInitAnlaesse = getJmAnlaesse($conn, $__jmInitYear);
    }
    if (function_exists('countUnpublishedJmChangelog')) {
        $__jmUnpublished = (int) countUnpublishedJmChangelog($__jmInitYear);
    }
} catch (Throwable $__e) {
    // Fallback bleibt: AJAX
}
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-wide">
                <?php
                $page_title = 'Erfassung Jahresmeisterschaft';
                $page_title_after = '<button type="button" class="btn-help" data-help="jmresultate.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_actions = '<button id="pdf-import-btn" type="button" class="btn btn-outline-success btn-sm"><i class="bi bi-filetype-pdf me-1"></i>PDF importieren</button>'
                    . '<button id="redirect-btn" type="button" class="btn btn-outline-info btn-sm"><i class="bi bi-list-ol me-1"></i>Rangliste</button>'
                    . '<button id="btnPublishJm" type="button" class="btn btn-outline-secondary btn-sm' . ($__jmUnpublished > 0 ? '' : ' d-none') . '" data-tooltip="Gespeicherte Änderungen im Portal sichtbar machen">'
                    . '<i class="bi bi-megaphone me-1"></i>Veröffentlichen <span id="publishBadge" class="badge bg-warning text-dark ms-1">' . ($__jmUnpublished > 0 ? (int) $__jmUnpublished : '') . '</span></button>';
                $page_extra = '<div class="ui-fortschritt" aria-live="polite"><span class="ui-zahl" id="progressText">–</span></div>'
                    . '<div class="ui-chips" id="progressChips"></div>';
                $page_show_mobile = true;
                include 'partials/page_header.inc.php';
                ?>
                <input type="hidden" name="csrf_token" id="jmCsrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Anlässe: Karte anklicken öffnet die Erfassung -->
                <section class="ui-karte jm-karte" aria-label="Anlässe">
                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel">Anlässe <button type="button" class="btn-help" data-help="jmresultate.erfassung" aria-label="Hilfe"></button></span>
                        <span class="jm-kopf-hinweis">Anlass anklicken, um die Resultate aller Mitglieder zu erfassen</span>
                    </div>
                    <div class="jm-anlaesse" id="anlassCardsGrid">
                        <div class="jm-leer"><div class="spinner-border spinner-border-sm me-2"></div>Lade Anlässe …</div>
                    </div>
                </section>

                <!-- Kontroll-Ranglisten Kat. A / Kat. B (Inhalt aus jmrang/load_jm.php) -->
                <?php foreach (['A' => 'rankJMA', 'B' => 'rankJMB'] as $__kat => $__tab): ?>
                <section class="ui-karte jm-karte rank-table-wrapper" aria-label="Rangliste Kat. <?= $__kat ?>">
                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel">Rangliste Kat. <?= $__kat ?><?php if ($__kat === 'A'): ?> <button type="button" class="btn-help" data-help="jmresultate.rangliste" aria-label="Hilfe"></button><?php endif; ?></span>
                        <span class="jm-legende"><span class="jm-cell-strichen">88</span> Streicher, zählt nicht · Zeile anklicken für die Aufschlüsselung</span>
                    </div>
                    <div class="desktop-table-container">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="<?= $__tab ?>">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="mobile-cards-container" id="mobileCards<?= ucfirst($__tab) ?>">
                        <div class="mobile-search">
                            <div class="position-relative">
                                <i class="bi bi-search search-icon"></i>
                                <input type="text" class="form-control" placeholder="Suchen..."
                                       oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCards<?= ucfirst($__tab) ?>')">
                            </div>
                        </div>
                        <div class="mobile-cards-scroll"></div>
                    </div>
                </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Erfassungs-Panel eines Anlasses -->
<div class="anlass-panel-overlay" id="anlassPanelOverlay"></div>
<div class="anlass-panel" id="anlassPanel" style="--panel-width: 560px;">
    <div class="panel-header" id="anlassPanelHeader">
        <div class="min-w-0">
            <h6 class="mb-0"><span id="anlassPanelTitle">Anlass</span> <button type="button" class="btn-help" data-help="jmresultate.erfassung" aria-label="Hilfe"></button></h6>
            <small class="panel-pos" id="anlassPanelMeta"></small>
        </div>
        <div class="d-flex align-items-center gap-1 ms-auto">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="anlassPanelClose" data-tooltip="Schliessen (Esc)" aria-label="Schliessen (Esc)">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <div class="jm-panel-suche">
        <label class="ui-suche">
            <i class="bi bi-search" aria-hidden="true"></i>
            <span class="visually-hidden">Mitglied suchen</span>
            <input type="search" id="anlassPanelSearch" placeholder="Mitglied suchen" autocomplete="off">
        </label>
        <span class="jm-panel-zaehler" id="anlassPanelCounter" aria-live="polite"></span>
    </div>
    <div class="anlass-panel-body" id="anlassPanelBody">
        <!-- Wird per JS befüllt -->
    </div>
    <div class="panel-footer">
        <div class="alert alert-danger small py-2 px-3 msv-eingabe-fehler" id="anlassPanelFehler" role="alert" hidden></div>
        <div class="d-flex gap-2 w-100 align-items-center">
            <span class="small text-muted me-auto d-none d-md-inline"><kbd class="ui-kbd">Enter</kbd> nächstes Feld · <kbd class="ui-kbd">Ctrl</kbd>+<kbd class="ui-kbd">S</kbd> speichern</span>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="anlassPanelCancelBtn">Abbrechen</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnAnlassSave">
                <i class="bi bi-save me-1"></i>Speichern
            </button>
        </div>
    </div>
</div>

<!-- ===== PDF-Import Modal ===== -->
<style>
#pdfImportModal .upload-area { border:2px dashed #cbd5e1; border-radius:0.75rem; padding:2.25rem 1rem; text-align:center; cursor:pointer; transition:all .2s; background:#f8fafc; }
#pdfImportModal .upload-area:hover, #pdfImportModal .upload-area.dragover { border-color:#22c55e; background:#f0fdf4; }
#pdfImportModal tr.row-dup { background:#fffbeb; }
#pdfImportModal tr.row-none { opacity:.55; }
#pdfImportModal .res-input { width:72px; text-align:center; font-weight:600; }
#pdfImportModal .preis-input { width:92px; text-align:center; }
#pdfImportModal #pdfImportPreviewTable thead th { font-size:0.7rem; text-transform:uppercase; letter-spacing:0.3px; }
</style>
<div class="modal fade" id="pdfImportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-filetype-pdf me-2"></i>Rangliste aus PDF importieren</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <!-- Schritt 1: Anlass + Upload -->
        <div id="pdfImportStep1">
          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold"><i class="bi bi-calendar-event me-1"></i>Anlass</label>
              <select id="pdfImportAnlass" class="form-select"><option value="">-- Anlass wählen --</option></select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold"><i class="bi bi-calendar3 me-1"></i>Jahr</label>
              <input type="text" id="pdfImportYear" class="form-control" readonly>
            </div>
          </div>
          <div class="upload-area" id="pdfImportDropzone">
            <i class="bi bi-cloud-arrow-up" style="font-size:2.5rem; color:#6c757d;"></i>
            <h6 class="mt-2 mb-1">PDF hier ablegen oder klicken</h6>
            <p class="text-muted small mb-0">Einzelrangliste eines Anlasses (z.B. Vereinsstich) oder FSA-Teilnehmerliste (Obligatorisch + Feldschiessen). Vereinsmitglieder werden automatisch erkannt.</p>
          </div>
          <input type="file" id="pdfImportFile" accept="application/pdf" style="display:none;">
        </div>

        <!-- Schritt 2: Vorschau -->
        <div id="pdfImportStep2" style="display:none;">
          <div id="pdfImportStats" class="alert alert-info py-2 px-3 small mb-2"></div>
          <div id="pdfImportGenWarn"></div>
          <div id="pdfImportSektion" class="mb-2"></div>
          <p class="text-muted small mb-2" id="pdfImportHint">
            <i class="bi bi-trophy-fill text-warning"></i> = Top&nbsp;10 (wird zusätzlich als Einzelrangierung gespeichert) ·
            Gelb markierte Zeilen sind bereits erfasst und standardmässig abgewählt.
          </p>
          <div class="table-responsive" style="max-height:50vh;">
            <table class="table table-sm table-hover align-middle mb-0" id="pdfImportPreviewTable">
              <thead class="table-light" style="position:sticky; top:0; z-index:2;">
                <tr>
                  <th style="width:36px;"><input type="checkbox" id="pdfImportSelectAll" class="form-check-input" data-tooltip="Alle auswählen" aria-label="Alle auswählen"></th>
                  <th style="width:64px;">Rang</th>
                  <th>Name (PDF)</th>
                  <th>Mitglied</th>
                  <th style="width:84px;">Resultat</th>
                  <th style="width:104px;">Preis CHF</th>
                  <th style="width:150px;">Status</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-outline-primary" id="pdfImportBackBtn" style="display:none;"><i class="bi bi-arrow-left me-1"></i>Andere Datei</button>
        <button type="button" class="btn btn-outline-success" id="pdfImportCommitBtn" style="display:none;"><i class="bi bi-download me-1"></i>Importieren (<span id="pdfImportCommitCount">0</span>)</button>
      </div>
    </div>
  </div>
</div>

<script>
    // Serverseitig vorberechnet (siehe PHP oben) -> sofortige Anzeige ohne AJAX-Verzoegerung
    window.JM_INITIAL_ANLAESSE = <?= json_encode($__jmInitAnlaesse + ['jahr' => $__jmInitYear], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
    // Jahr-Auswahl sofort füllen: die Blöcke unten lesen $('#yearSelect').val() beim Start
    msvJahrAuswahl('#yearSelect');

    // Rangliste: Ranglisten-Seite im gewählten Jahr
    $(document).on('click', '#redirect-btn', function () {
        const y = $('#yearSelect').val();
        window.location.href = 'jmrang.php' + (y ? '?year=' + encodeURIComponent(y) : '');
    });
</script>
<script>
/**
 * Anlass-basierte Eingabe für JM-Resultate: Karte pro Anlass, Erfassung im Slide-Panel
 */
(function() {
    const $yearDD = $('#yearSelect');
    let currentAnlassData = null;
    let panelGeaendert = false;   // Eingaben im offenen Panel noch nicht gespeichert
    function jmGeaendert(an) { panelGeaendert = !!an; msvPanelUngespeichert($('#anlassPanelMeta').parent(), panelGeaendert); }

    // ---- Anlässe laden (Jahreswechsel, nach dem Speichern) ----
    function loadAnlaesse(year) {
        $.get('jmresultate/load_anlaesse.php', { year: year })
            .done(function(resp) {
                if (!resp.success) { msvToast(resp.message || 'Anlässe konnten nicht geladen werden', 'error'); return; }
                buildAnlassCards(resp.anlaesse);
            })
            .fail(function(xhr) { msvToast(msvXhrMessage(xhr, 'Anlässe konnten nicht geladen werden'), 'error'); });
    }

    // Initial: serverseitig vorberechnete Daten sofort zeigen (kein AJAX-Flackern), sonst AJAX
    $(function() {
        const init = window.JM_INITIAL_ANLAESSE;
        if (init && String(init.jahr) === String($yearDD.val()) && Array.isArray(init.anlaesse) && init.anlaesse.length) {
            buildAnlassCards(init.anlaesse);
        } else {
            loadAnlaesse($yearDD.val());
        }
    });
    $yearDD.on('change', function() { closeAnlassPanel(); loadAnlaesse($(this).val()); });

    // ---- Eingabefeld einer Zeile ----
    function feld(m, field, label, def) {
        const wert = m[field] ?? '';
        const max = field === 'punkte' ? (parseInt(def.maxpunkte, 10) || '') : '';
        return '<input type="text" class="form-control form-control-sm anlass-input jm-feld'
            + (String(wert) !== '' ? ' filled' : '') + (m.status === 'entwurf' ? ' jm-gemeldet' : '') + '"'
            + ' data-mid="' + m.mitgliedID + '" data-field="' + field + '" value="' + msvEsc(wert) + '"'
            + ' inputmode="numeric" autocomplete="off"' + (field === 'punkte' ? ' placeholder="\u2013"' : '')
            + ' aria-label="' + label + '"' + (max !== '' ? ' data-max="' + max + '"' : '') + '>';
    }

    // ---- Panel öffnen (Klick auf eine Anlass-Karte) ----
    function openAnlassPanel(jmdefID) {
        const year = $yearDD.val();
        const $body = $('#anlassPanelBody');
        $body.html('<div class="jm-panel-leer"><div class="spinner-border spinner-border-sm me-2"></div>Lade Mitglieder …</div>');
        $('#anlassPanelCounter').text('');
        jmGeaendert(false);
        msvEingabeFehler('#anlassPanelFehler', []);

        $('.jm-anlass').removeClass('selected').attr('aria-pressed', 'false');
        $('.jm-anlass[data-id="' + jmdefID + '"]').addClass('selected').attr('aria-pressed', 'true');

        $('#anlassPanel').addClass('open');
        $('#anlassPanelOverlay').addClass('show');
        $('#anlassPanelSearch').val('');

        $.get('jmresultate/load_anlass_form.php', { year: year, jmdefinitionID: jmdefID })
            .done(function(resp) {
                if (!resp.success) {
                    $body.html('<div class="jm-panel-leer text-danger">Fehler: ' + msvEsc(resp.message) + '</div>');
                    return;
                }
                currentAnlassData = resp;
                const def = resp.definition;

                $('#anlassPanelTitle').text(def.bezeichnung);
                const meta = ['Max. ' + def.maxpunkte + ' Punkte'];
                if (def.isSektionsmeisterschaft) meta.push('2 Runden');
                if (def.streicher) meta.push('Streicher');
                if (def.isReadonly) meta.push('nur Anzeige, wird automatisch berechnet');
                $('#anlassPanelMeta').text(meta.join(' · '));

                // Gruppierung nach JM-Aktivität des Jahres (Resultat in IRGENDEINEM Anlass), nicht nach
                // diesem Anlass. Beide Gruppen bleiben alphabetisch (Server sortiert nach Name/Vorname).
                const isJahrAktiv = function(m) { return !!m.hatJahrResultat; };
                const mit = resp.members.filter(isJahrAktiv);
                const ohne = resp.members.filter(function(m) { return !isJahrAktiv(m); });
                const ordered = [];
                if (mit.length) {
                    ordered.push({ __group: 'Mit JM-Resultat (' + mit.length + ')' });
                    mit.forEach(function(m) { ordered.push(m); });
                }
                if (ohne.length) {
                    ordered.push({ __group: 'Noch ohne JM-Resultat (' + ohne.length + ')' });
                    ohne.forEach(function(m) { ordered.push(m); });
                }

                let html = '';
                let pos = 0;
                ordered.forEach(function(m) {
                    if (m.__group) {
                        html += '<div class="anlass-group-header">' + m.__group + '</div>';
                        return;
                    }
                    pos++;
                    const nameEsc = msvEsc(m.name);
                    html += '<div class="jm-zeile anlass-member-row" data-name="' + msvEsc(m.name.toLowerCase()) + '">';
                    html += '<span class="jm-pos">' + pos + '</span>';
                    html += '<span class="jm-name">' + nameEsc;
                    if (m.status === 'entwurf') {
                        html += '<span class="geloest-pill" data-tooltip="Vom Mitglied im Portal gemeldet – Speichern bestätigt es">gemeldet</span>';
                    }
                    html += '</span>';
                    if (def.isSektionsmeisterschaft) {
                        html += '<span class="jm-runden">'
                            + '<label class="jm-runde">R1 ' + feld(m, 'punkte_runde1', 'Runde 1, ' + nameEsc, def) + '</label>'
                            + '<label class="jm-runde">R2 ' + feld(m, 'punkte_runde2', 'Runde 2, ' + nameEsc, def) + '</label>'
                            + '</span>';
                    } else if (def.isReadonly) {
                        html += '<span class="jm-wert">' + msvEsc(m.punkte || '\u2013') + '</span>';
                    } else {
                        html += feld(m, 'punkte', 'Punkte, ' + nameEsc, def);
                    }
                    html += '</div>';
                });

                $body.html(html || '<div class="jm-panel-leer">Keine aktiven Mitglieder gefunden.</div>');
                updatePanelCounter();
                $('#btnAnlassSave').toggle(!def.isReadonly);

                // Fokus auf das erste leere Feld
                setTimeout(function() {
                    const $felder = $body.find('.anlass-input');
                    const $leer = $felder.filter(function() { return !this.value; }).first();
                    ($leer.length ? $leer : $felder.first()).trigger('focus');
                }, 300);
            })
            .fail(function(xhr) {
                $body.html('<div class="jm-panel-leer text-danger">Die Mitglieder konnten nicht geladen werden: ' + msvEsc(msvXhrMessage(xhr, 'Serverfehler')) + '</div>');
            });
    }

    // ---- Panel schliessen ----
    function closeAnlassPanel() {
        $('#anlassPanel').removeClass('open');
        $('#anlassPanelOverlay').removeClass('show');
        $('.jm-anlass').removeClass('selected').attr('aria-pressed', 'false');
        currentAnlassData = null;
        jmGeaendert(false);
        msvEingabeFehler('#anlassPanelFehler', []);
    }

    // Schliessen ohne Speichern: bei ungespeicherten Eingaben zuerst nachfragen
    async function versucheSchliessen() {
        if (panelGeaendert) {
            const wahl = await msvUngespeichert({ wer: $('#anlassPanelTitle').text().trim() });
            if (wahl === 'zurueck') return;
            if (wahl === 'speichern') { $('#btnAnlassSave').trigger('click'); return; } // schliesst nach Erfolg selbst
        }
        closeAnlassPanel();
    }
    $('#anlassPanelClose, #anlassPanelCancelBtn, #anlassPanelOverlay').on('click', versucheSchliessen);

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#anlassPanel').hasClass('open') && !(window.Swal && Swal.isVisible())) {
            e.stopImmediatePropagation();
            versucheSchliessen();
        }
    });

    // ---- Suche im Panel ----
    $('#anlassPanelSearch').on('input', function() {
        const q = this.value.trim().toLowerCase();
        $('#anlassPanelBody .anlass-member-row').each(function() {
            $(this).toggle(String($(this).data('name') || '').includes(q));
        });
        // Gruppen-Überschriften nur ohne Suchbegriff
        $('#anlassPanelBody .anlass-group-header').toggle(q === '');
    });

    // ---- Zähler: Mitglieder mit Resultat in diesem Anlass ----
    function updatePanelCounter() {
        const total = currentAnlassData ? currentAnlassData.totalMembers : 0;
        const seen = {};
        let filled = 0;
        $('#anlassPanelBody .anlass-input').each(function() {
            const voll = this.value.trim() !== '';
            $(this).toggleClass('filled', voll);
            const mid = $(this).data('mid');
            if (voll && !seen[mid]) { seen[mid] = true; filled++; }
        });
        $('#anlassPanelCounter').html('<b>' + filled + '</b> von ' + total + ' erfasst');
    }

    // Plausibilität: Zahl, nicht negativ, höchstens Max-Punkte des Anlasses (data-max).
    // Liefert einen Fehlertext oder ''.
    function pruefePunkte(input) {
        const roh = input.value.trim();
        let fehler = '';
        if (roh !== '') {
            const zahl = Number(roh.replace(',', '.'));
            const max = parseInt(input.getAttribute('data-max'), 10);
            if (!isFinite(zahl) || zahl < 0) fehler = 'Keine gültige Punktzahl';
            else if (max > 0 && zahl > max) fehler = 'Höchstens ' + max + ' Punkte';
            else if (!Number.isInteger(zahl)) fehler = 'Nur ganze Punkte';
        }
        input.classList.toggle('is-invalid', fehler !== '');
        if (fehler) {
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('data-tooltip', fehler);
        } else {
            input.removeAttribute('aria-invalid');
            input.removeAttribute('data-tooltip');
        }
        return fehler;
    }

    // Alle unplausiblen Felder als Liste für msvEingabeFehler (Mitglied + Feld)
    function jmFehlerliste() {
        return $('#anlassPanelBody .anlass-input').toArray().map(function(inp) {
            const fehler = pruefePunkte(inp);
            if (!fehler) return null;
            const name = $(inp).closest('.anlass-member-row').find('.jm-name').contents().first().text().trim();
            const feld = inp.getAttribute('aria-label');
            return { el: inp, name: name + (feld ? ', ' + feld : ''), wert: inp.value, fehler: fehler };
        }).filter(Boolean);
    }

    // Eingaben im Panel
    $(document).on('input', '.anlass-input', function() {
        jmGeaendert(true);
        updatePanelCounter();
        pruefePunkte(this);
        if (!$('#anlassPanelFehler').prop('hidden')) msvEingabeFehler('#anlassPanelFehler', jmFehlerliste(), false);
    });
    $(window).on('beforeunload', function() { if (panelGeaendert) return 'Nicht gespeicherte Eingaben'; });

    // Enter: nächstes Feld, im letzten Feld speichern; Pfeil auf/ab: gleiches Feld der Nachbarzeile
    $(document).on('keydown', '.anlass-input', function(e) {
        const felder = $('#anlassPanelBody .anlass-input:visible').toArray();
        const idx = felder.indexOf(this);
        if (e.key === 'Enter') {
            e.preventDefault();
            if (idx >= 0 && felder[idx + 1]) {
                felder[idx + 1].focus();
                felder[idx + 1].select();
            } else if (idx === felder.length - 1) {
                $('#btnAnlassSave:visible').trigger('click');
            }
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const art = $(this).data('field');
            const gleiche = felder.filter(function(f) { return $(f).data('field') === art; });
            const j = gleiche.indexOf(this) + (e.key === 'ArrowDown' ? 1 : -1);
            if (j >= 0 && j < gleiche.length) {
                gleiche[j].focus();
                gleiche[j].select();
            }
        }
    });

    // Ctrl+S / Cmd+S speichert das offene Erfassungs-Panel
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S') && $('#anlassPanel').hasClass('open')) {
            e.preventDefault();
            $('#btnAnlassSave:visible').trigger('click');
        }
    });

    $(document).on('focus', '.anlass-input', function() { this.select(); });

    // ---- Speichern ----
    $('#btnAnlassSave').on('click', function() {
        if (!currentAnlassData) return;
        if ($(this).prop('disabled')) return;
        const def = currentAnlassData.definition;

        // Unplausible Werte zuerst korrigieren lassen
        if (msvEingabeFehler('#anlassPanelFehler', jmFehlerliste())) return;

        const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Speichere …');

        // Daten sammeln (pro Mitglied ein Objekt, bei der Sektionsmeisterschaft mit zwei Runden)
        const members = [];
        const seenMids = {};
        $('#anlassPanelBody .anlass-input').each(function() {
            const mid = $(this).data('mid');
            if (!seenMids[mid]) {
                seenMids[mid] = { mitgliedID: mid };
                members.push(seenMids[mid]);
            }
            seenMids[mid][$(this).data('field')] = this.value.trim();
        });

        $.post('jmresultate/save_anlass.php', {
            jmdefinitionID: def.id,
            members: JSON.stringify(members),
            isSektionsmeisterschaft: def.isSektionsmeisterschaft ? '1' : '',
            csrf_token: $('#jmCsrf').val()
        })
        .done(function(resp) {
            if (resp.success) {
                msvToast('Resultate für \u00ab' + def.bezeichnung + '\u00bb gespeichert', 'success');
                closeAnlassPanel();
                loadAnlaesse($yearDD.val());
                if (window.loadRanglisten) window.loadRanglisten($yearDD.val());
                if (window.jmPruefeUnveroeffentlicht) window.jmPruefeUnveroeffentlicht();
            } else {
                msvToast('Nicht gespeichert: ' + (resp.message || 'unbekannter Fehler') + '. Die Eingaben sind noch da.', 'error');
            }
        })
        .fail(function(xhr) { msvToast('Nicht gespeichert: ' + msvXhrMessage(xhr, 'Serverfehler') + '. Die Eingaben sind noch da.', 'error'); })
        .always(function() { $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Speichern'); });
    });

    // ---- Anlass-Karten + Kopf-Card ----
    function buildAnlassCards(anlaesse) {
        const $grid = $('#anlassCardsGrid');
        // Endstich und Bester Kantonalstich werden automatisch berechnet und haben keine Karte
        const bearbeitbar = (anlaesse || []).filter(function(a) { return !a.isReadonly; });
        kopfZahlen(bearbeitbar);
        if (!bearbeitbar.length) {
            $grid.html('<div class="jm-leer">Für dieses Jahr sind keine Anlässe erfasst. Sie werden unter <a href="jmdefinition.php">Jahresmeisterschaft Definition</a> angelegt.</div>');
            return;
        }
        let html = '';
        bearbeitbar.forEach(function(a) {
            html += '<button type="button" class="jm-anlass" data-id="' + a.id + '" aria-pressed="false">'
                + '<span class="jm-anlass-name" data-tooltip="' + msvEsc(a.bezeichnung) + '">' + msvEsc(a.bezeichnung) + '</span>'
                + '<span class="jm-anlass-zeile"><span class="jm-anlass-zahl"><b>' + a.filledCount + '</b> ' + (a.filledCount === 1 ? 'Resultat' : 'Resultate') + '</span>'
                + (a.gemeldetCount ? '<span class="geloest-pill">' + a.gemeldetCount + ' gemeldet</span>' : '')
                + '</span></button>';
        });
        $grid.html(html);
    }
    $(document).on('click', '.jm-anlass', function() { openAnlassPanel($(this).data('id')); });

    // Kopf-Card: erfasste Resultate über alle Anlässe, offene Meldungen von Mitgliedern
    function kopfZahlen(anlaesse) {
        const resultate = anlaesse.reduce(function(s, a) { return s + (a.filledCount || 0); }, 0);
        const gemeldet = anlaesse.reduce(function(s, a) { return s + (a.gemeldetCount || 0); }, 0);
        $('#progressText').html(resultate + ' <span>' + (resultate === 1 ? 'Resultat' : 'Resultate') + ' in '
            + anlaesse.length + ' ' + (anlaesse.length === 1 ? 'Anlass' : 'Anlässen') + '</span>');
        $('#progressChips').html(gemeldet
            ? '<span class="ui-chip"><span class="ui-punkt"></span><b>' + gemeldet + '</b> von Mitgliedern gemeldet, noch nicht bestätigt</span>'
            : '');
    }
})();
</script>
<script>
/**
 * Ranglisten-Darstellung (identisch mit jmrang.php)
 * Lädt Kat. A und Kat. B via jmrang/load_jm.php
 */
(function() {
    const $yearDD = $('#yearSelect');

    // ---- Tabellen-Update mit Fade-Animation ----
    function updateRankTable(tableSelector, theadHtml, tbodyHtml) {
        const $table = $(tableSelector);
        $table.fadeTo(200, 0.5, function() {
            $table.find('thead').html(theadHtml);
            $table.find('tbody').html(tbodyHtml);

            // Tooltips
            $table.find('td[data-toggle="tooltip"]').each(function() {
                new bootstrap.Tooltip(this);
            });

            $table.fadeTo(200, 1, function() {
                // Mobile Cards generieren
                if (tableSelector === '#rankJMA') {
                    buildRankMobileCards('#rankJMA', '#mobileCardsRankJMA');
                } else if (tableSelector === '#rankJMB') {
                    buildRankMobileCards('#rankJMB', '#mobileCardsRankJMB');
                }
            });
        });
    }

    // ---- Mobile Cards Builder (wie jmrang.php) ----
    function buildRankMobileCards(tableSelector, containerSelector) {
        if (typeof MSVMobileCards === 'undefined') return;
        MSVMobileCards.initResponsive(function() {
            const table = document.querySelector(tableSelector);
            const container = document.querySelector(containerSelector);
            if (!table || !container) return;

            const scrollContainer = container.querySelector('.mobile-cards-scroll');
            if (!scrollContainer) return;

            const mainRows = table.querySelectorAll('tbody tr.jm-main-row');
            if (mainRows.length === 0) {
                scrollContainer.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten gefunden</div></div>';
                return;
            }

            let html = '';
            mainRows.forEach(function(row, idx) {
                const cells = Array.from(row.querySelectorAll('td'));
                if (cells.length === 0) return;

                const rowIdx = row.dataset.row;
                const rang = cells[0]?.textContent?.trim() || '';
                const name = cells[1]?.textContent?.trim() || '';
                const totalCell = cells[cells.length - 2] || cells[cells.length - 1];
                const total = totalCell?.textContent?.trim() || '';

                const rankNum = parseInt(rang) || 0;
                let rankClass = '';
                if (rankNum >= 1 && rankNum <= 3) rankClass = ' rank-' + rankNum;

                // Detail-Panel aus zugehöriger .jm-detail-row
                const detailRow = table.querySelector('tr.jm-detail-row[data-row="' + rowIdx + '"]');
                let detailHtml = '';
                if (detailRow) {
                    const panel = detailRow.querySelector('.jm-detail-panel');
                    if (panel) detailHtml = panel.outerHTML;
                }

                html += '<div class="mobile-card jm-mobile-card' + rankClass + '" data-index="' + idx + '">' +
                    '<div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">' +
                        '<div class="d-flex align-items-center gap-2">' +
                            '<span class="jm-mobile-rang">' + rang + '</span>' +
                            '<span class="fw-bold">' + name + '</span>' +
                        '</div>' +
                        '<div class="d-flex align-items-center gap-2">' +
                            '<span class="jm-mobile-total">' + total + '</span>' +
                            '<i class="bi bi-chevron-down"></i>' +
                        '</div>' +
                    '</div>' +
                    '<div class="mobile-card-body">' + detailHtml + '</div>' +
                '</div>';
            });

            scrollContainer.innerHTML = html;
        });
    }

    // ---- AJAX-Loader ----
    function loadRankData(url, params, targetSelector) {
        $(targetSelector).find('tbody').html(
            '<tr><td colspan="100%" class="loading-indicator">' +
            '<div class="spinner-border spinner-border-sm me-2"></div>' +
            'Lade Rangliste...' +
            '</td></tr>'
        );

        $.ajax({
            url: url,
            type: 'GET',
            data: params,
            success: function(response) {
                try {
                    const parsed = typeof response === 'string' ? JSON.parse(response) : response;
                    if (parsed.thead && parsed.tbody) {
                        updateRankTable(targetSelector, parsed.thead, parsed.tbody);
                    } else if (parsed.error) {
                        $(targetSelector).find('tbody').html(
                            '<tr><td colspan="100%" class="text-center text-danger">' +
                            '<i class="bi bi-exclamation-triangle me-2"></i>' + parsed.error +
                            '</td></tr>'
                        );
                    }
                } catch (e) {
                    $(targetSelector).html(response);
                }
            },
            error: function() {
                $(targetSelector).find('tbody').html(
                    '<tr><td colspan="100%" class="text-center text-danger">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden' +
                    '</td></tr>'
                );
            }
        });
    }

    function loadRankJMA(year) {
        loadRankData('jmrang/load_jm.php', { year: year, kategorie: 'Kat. A' }, '#rankJMA');
    }

    function loadRankJMB(year) {
        loadRankData('jmrang/load_jm.php', { year: year, kategorie: 'Kat. B' }, '#rankJMB');
    }

    // ---- Ranglisten laden (initial + bei Jahreswechsel) ----
    window.loadRanglisten = function(year) {
        loadRankJMA(year);
        loadRankJMB(year);
    };

    $(function() {
        loadRanglisten($yearDD.val());
    });
    $yearDD.on('change', function() { loadRanglisten($(this).val()); });

    // ---- Detail-Zeilen aufklappen ----
    $(document).on('click', '.jm-main-row', function() {
        const rowIdx = $(this).data('row');
        const $detail = $('tr.jm-detail-row[data-row="' + rowIdx + '"]');
        const $btn = $(this).find('.jm-toggle-btn');
        $detail.toggle();
        $btn.toggleClass('expanded');
    });
})();
</script>

<script>
/**
 * Veröffentlichen-Button für JM-Resultate Changelog
 */
(function() {
    const $btn = $('#btnPublishJm');
    const $badge = $('#publishBadge');
    const $yearDD = $('#yearSelect');
    const csrfToken = $('#jmCsrf').val();

    function checkUnpublished() {
        const year = $yearDD.val();
        if (!year) return;
        $.get('jmresultate/count_unpublished.php', { year: year }, function(res) {
            if (res.success && res.count > 0) {
                $badge.text(res.count);
                $btn.removeClass('d-none');
            } else {
                $btn.addClass('d-none');
            }
        }, 'json').fail(function() { $btn.addClass('d-none'); });
    }

    $btn.on('click', async function() {
        const year = $yearDD.val();
        const r = await msvConfirm(
            'Alle unveröffentlichten JM-Resultate für ' + year + ' freigeben?',
            'Veröffentlichen',
            'Ja, veröffentlichen'
        );
        if (!r.isConfirmed) return;

        $btn.prop('disabled', true);
        $.post('jmresultate/publish_resultate.php', {
            year: year,
            csrf_token: csrfToken
        }, function(res) {
            if (res.success) {
                msvToast(res.message, 'success');
                $btn.addClass('d-none');
            } else {
                msvToast(res.message || 'Fehler', 'error');
            }
        }, 'json').fail(function(xhr) {
            msvToast(msvXhrMessage(xhr, 'Veröffentlichen fehlgeschlagen'), 'error');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // Initial, bei Jahreswechsel und nach dem Speichern eines Anlasses prüfen
    window.jmPruefeUnveroeffentlicht = checkUnpublished;
    $(function() { checkUnpublished(); });
    $yearDD.on('change', function() { checkUnpublished(); });
})();
</script>

<script>
/* ===== PDF-Ranglisten-Import ===== */
(function () {
    const modalEl = document.getElementById('pdfImportModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);
    let previewRows = [];
    let sektionData = null;
    let opfsMode = false;   // FSA-Teilnehmerliste: Obligatorisch + Feldschiessen (Bonus)
    let opfsDefs = null;
    let generatorInfo = null; // erkannte Generator-Familie (z.B. 'vereinswk') für gezielte Hinweise

    function csrf() { return $('#jmCsrf').val(); }
    function selectedYear() { return $('#yearSelect').val(); }
    function escapeHtml(s) { return $('<div>').text(s == null ? '' : s).html(); }

    const theadDefaultHtml = $('#pdfImportPreviewTable thead tr').html();
    const hintDefaultHtml = $('#pdfImportHint').html();

    const dropzoneHtml = '<i class="bi bi-cloud-arrow-up" style="font-size:2.5rem; color:#6c757d;"></i>' +
        '<h6 class="mt-2 mb-1">PDF hier ablegen oder klicken</h6>' +
        '<p class="text-muted small mb-0">Einzelrangliste eines Anlasses (z.B. Vereinsstich) oder FSA-Teilnehmerliste (Obligatorisch + Feldschiessen). Vereinsmitglieder werden automatisch erkannt.</p>';

    // ---- Öffnen ----
    $('#pdf-import-btn').on('click', function () {
        resetModal();
        $('#pdfImportYear').val(selectedYear());
        populateAnlass();
        modal.show();
    });

    function resetModal() {
        previewRows = [];
        sektionData = null;
        opfsMode = false;
        opfsDefs = null;
        generatorInfo = null;
        $('#pdfImportGenWarn').empty();
        $('#pdfImportPreviewTable thead tr').html(theadDefaultHtml);
        $('#pdfImportHint').html(hintDefaultHtml);
        $('#pdfImportStep1').show();
        $('#pdfImportStep2').hide();
        $('#pdfImportBackBtn, #pdfImportCommitBtn').hide();
        $('#pdfImportPreviewTable tbody').empty();
        $('#pdfImportSektion').empty();
        $('#pdfImportFile').val('');
        $('#pdfImportDropzone').removeClass('dragover').html(dropzoneHtml);
    }

    function populateAnlass() {
        const $sel = $('#pdfImportAnlass').html('<option value="">Lade Anlässe...</option>');
        $.get('jmresultate/load_anlaesse.php', { year: selectedYear() })
            .done(function (resp) {
                $sel.html('<option value="">-- Anlass wählen --</option>');
                // OP + FS werden nur kombiniert aus der FSA-Teilnehmerliste importiert
                $sel.append($('<option>').val('opfs').text('Obligatorisch + Feldschiessen (FSA-Teilnehmerliste)'));
                if (resp.success && resp.anlaesse) {
                    resp.anlaesse.filter(a => !a.isReadonly && a.bezeichnung !== 'Obligatorisch' && a.bezeichnung !== 'Feldschiessen').forEach(function (a) {
                        $sel.append($('<option>').val(a.id).text(a.bezeichnung).attr('data-max', a.maxpunkte));
                    });
                }
            })
            .fail(function () { $sel.html('<option value="">Fehler beim Laden</option>'); });
    }

    // ---- Drag & Drop ----
    const $dz = $('#pdfImportDropzone');
    $dz.on('click', function () { $('#pdfImportFile').click(); });
    $dz.on('dragover', function (e) { e.preventDefault(); e.stopPropagation(); $dz.addClass('dragover'); });
    $dz.on('dragleave', function (e) { e.preventDefault(); e.stopPropagation(); $dz.removeClass('dragover'); });
    $dz.on('drop', function (e) {
        e.preventDefault(); e.stopPropagation(); $dz.removeClass('dragover');
        const files = e.originalEvent.dataTransfer.files;
        if (files.length) handleFile(files[0]);
    });
    $('#pdfImportFile').on('change', function () { if (this.files.length) handleFile(this.files[0]); });
    $('#pdfImportBackBtn').on('click', function () {
        const keep = $('#pdfImportAnlass').val();
        resetModal();
        $('#pdfImportYear').val(selectedYear());
        populateAnlass();
        // Anlass-Auswahl nach Neuladen wiederherstellen
        setTimeout(function () { $('#pdfImportAnlass').val(keep); }, 300);
    });

    function handleFile(file) {
        const anlassId = $('#pdfImportAnlass').val();
        if (!anlassId) { msvToast('Bitte zuerst einen Anlass wählen', 'warning'); return; }
        if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)) {
            msvToast('Bitte eine PDF-Datei wählen', 'warning'); return;
        }

        const fd = new FormData();
        fd.append('action', 'parse');
        fd.append('csrf_token', csrf());
        fd.append('jmdefinitionID', anlassId);
        fd.append('year', selectedYear());
        fd.append('pdf', file);

        $dz.html('<div class="spinner-border text-success mb-2"></div><div>Analysiere PDF...</div>');

        $.ajax({
            url: 'rangliste_import/import_api.php', type: 'POST', data: fd,
            processData: false, contentType: false, dataType: 'json'
        })
            .done(function (resp) {
                if (!resp.success) {
                    if (resp.csrf_expired) { msvError('Sitzung abgelaufen. Bitte Seite neu laden.'); return; }
                    msvToast(resp.message || 'Fehler beim Parsen', 'error');
                    $dz.html(dropzoneHtml);
                    return;
                }
                previewRows = resp.rows || [];
                sektionData = resp.sektion || null;
                opfsMode = (resp.mode === 'opfs');
                opfsDefs = resp.defs || null;
                generatorInfo = resp.generator || null;
                if (opfsMode) {
                    renderPreviewOpfs(resp.stats);
                } else {
                    renderPreview(resp.stats);
                }
            })
            .fail(function () { msvToast('Fehler beim Hochladen / Parsen', 'error'); $dz.html(dropzoneHtml); });
    }

    function renderPreview(stats) {
        $('#pdfImportStep1').hide();
        $('#pdfImportStep2').show();
        $('#pdfImportBackBtn').show();

        const anlassName = $('#pdfImportAnlass option:selected').text();
        $('#pdfImportStats').html('<i class="bi bi-info-circle me-1"></i><strong>' + escapeHtml(anlassName) + '</strong> – ' +
            stats.matched + ' Mitglieder erkannt · ' + stats.top10 + ' Top-10 · ' + stats.duplicates + ' bereits erfasst' +
            (stats.fuzzy ? ' · ' + stats.fuzzy + ' unsicher' : '') + ' (von ' + stats.total_lines + ' Zeilen)');

        // Gezielter Hinweis pro Generator-Familie
        $('#pdfImportGenWarn').html(generatorInfo === 'vereinswk'
            ? '<div class="alert alert-warning py-2 px-3 small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>' +
              '<strong>VereinsWK-Rangliste:</strong> Bei Schützen, die nachgedoppelt haben (Auszahlungsstich), steht in der ' +
              'Punkte-Spalte das <em>ersetzte</em> Resultat – das ursprüngliche Stich-Resultat ist im PDF nicht enthalten. ' +
              'Betroffene Resultate vor dem Import hier in der Vorschau anpassen.</div>'
            : '');

        renderSektion();

        if (previewRows.length === 0) {
            $('#pdfImportPreviewTable tbody').html('<tr><td colspan="7" class="text-center text-muted py-3">Keine Vereinsmitglieder im PDF erkannt.</td></tr>');
            // Commit trotzdem anbieten, falls eine Sektionsrangierung gefunden wurde
            $('#pdfImportCommitBtn').toggle(!!sektionData);
            updateCommitCount();
            return;
        }
        $('#pdfImportCommitBtn').show();

        const badge = {
            license: '<span class="badge bg-success">Lizenz</span>',
            exact: '<span class="badge bg-success">Name</span>',
            fuzzy: '<span class="badge bg-warning text-dark">unsicher</span>',
            none: '<span class="badge bg-secondary">kein Treffer</span>'
        };

        let html = '';
        previewRows.forEach(function (r, i) {
            const isDup = r.dup_jm || r.dup_einzel;
            const checked = (!isDup && r.match_status !== 'none') ? 'checked' : '';
            const trCls = isDup ? 'row-dup' : (r.match_status === 'none' ? 'row-none' : '');
            const dupVal = (r.dup_jm && r.dup_jm_punkte != null) ? ': ' + r.dup_jm_punkte : '';
            const dupNote = isDup ? '<span class="badge bg-warning text-dark ms-1">bereits erfasst' + dupVal + '</span>' : '';
            const top10 = r.is_top10 ? ' <i class="bi bi-trophy-fill text-warning" data-tooltip="Top 10 – auch Einzelrangierung" aria-label="Top 10"></i>' : '';
            const preisCell = r.is_top10
                ? '<input type="text" class="form-control form-control-sm preis-input" value="' + (r.preis !== null ? r.preis : '') + '" inputmode="decimal">'
                : '<span class="text-muted small">–</span>';
            html += '<tr class="' + trCls + '" data-i="' + i + '">' +
                '<td><input type="checkbox" class="form-check-input row-check" ' + checked + '></td>' +
                '<td>' + (r.rang !== null ? r.rang : '–') + top10 + '</td>' +
                '<td class="small">' + escapeHtml(r.raw_name) + '</td>' +
                '<td class="small fw-semibold">' + escapeHtml(r.matched_name) + '</td>' +
                '<td><input type="text" class="form-control form-control-sm res-input" value="' + (r.resultat !== null ? r.resultat : '') + '" inputmode="numeric"></td>' +
                '<td>' + preisCell + '</td>' +
                '<td>' + (badge[r.match_status] || '') + dupNote + '</td>' +
                '</tr>';
        });
        $('#pdfImportPreviewTable tbody').html(html);
        $('#pdfImportSelectAll').prop('checked', false);
        updateCommitCount();
    }

    // ---- Vorschau: Obligatorisch + Feldschiessen (FSA-Bonus) ----
    function renderPreviewOpfs(stats) {
        $('#pdfImportStep1').hide();
        $('#pdfImportStep2').show();
        $('#pdfImportBackBtn').show();

        const opMax = (opfsDefs && opfsDefs.op) ? opfsDefs.op.max : 20;
        const fsMax = (opfsDefs && opfsDefs.fs) ? opfsDefs.fs.max : 20;

        $('#pdfImportStats').html('<i class="bi bi-info-circle me-1"></i><strong>FSA-Teilnehmerliste – Obligatorisch + Feldschiessen</strong> – ' +
            stats.matched + ' Mitglieder erkannt · OP: ' + stats.op_count + ' · FS: ' + stats.fs_count +
            ' · ' + stats.duplicates + ' bereits (teilweise) erfasst' +
            (stats.fuzzy ? ' · ' + stats.fuzzy + ' unsicher' : '') + ' (von ' + stats.total_lines + ' Teilnehmer-Zeilen, alle Vereine im PDF)');

        sektionData = null;
        $('#pdfImportSektion').empty();
        $('#pdfImportGenWarn').empty();
        $('#pdfImportHint').html('Bonus-Punkte sind mit ' + opMax + ' (Obligatorisch) bzw. ' + fsMax + ' (Feldschiessen) vorbelegt, wo ein Resultat vorhanden ist – leeres Feld wird nicht importiert. ' +
            'Bereits erfasste Disziplinen bleiben leer (gelbe Zeile), unsichere Zuordnungen sind standardmässig abgewählt.');

        $('#pdfImportPreviewTable thead tr').html(
            '<th style="width:36px;"><input type="checkbox" id="pdfImportSelectAll" class="form-check-input" data-tooltip="Alle auswählen" aria-label="Alle auswählen"></th>' +
            '<th>Mitglied</th>' +
            '<th>Name (PDF) / Quelle</th>' +
            '<th style="width:120px;">Geschossen</th>' +
            '<th style="width:96px;">Bonus OP</th>' +
            '<th style="width:96px;">Bonus FS</th>' +
            '<th style="width:170px;">Status</th>');

        if (previewRows.length === 0) {
            $('#pdfImportPreviewTable tbody').html('<tr><td colspan="7" class="text-center text-muted py-3">Keine Vereinsmitglieder im PDF erkannt.</td></tr>');
            $('#pdfImportCommitBtn').hide();
            updateCommitCount();
            return;
        }
        $('#pdfImportCommitBtn').show();

        const badge = {
            exact: '<span class="badge bg-success">Name</span>',
            fuzzy: '<span class="badge bg-warning text-dark">unsicher</span>'
        };

        let html = '';
        previewRows.forEach(function (r, i) {
            const hasOp = r.op_resultat !== null;
            const hasFs = r.fs_resultat !== null;
            const opImportable = hasOp && !r.dup_op;
            const fsImportable = hasFs && !r.dup_fs;
            const isDup = (hasOp && r.dup_op) || (hasFs && r.dup_fs);
            const checked = (r.match_status === 'exact' && (opImportable || fsImportable)) ? 'checked' : '';
            const trCls = isDup ? 'row-dup' : '';

            const geschossen = '<span class="text-nowrap">OP ' + (hasOp ? '<strong>' + r.op_resultat + '</strong>' : '–') +
                ' · FS ' + (hasFs ? '<strong>' + r.fs_resultat + '</strong>' : '–') + '</span>';
            const opCell = hasOp
                ? '<input type="text" class="form-control form-control-sm res-input op-input" value="' + (opImportable ? opMax : '') + '" inputmode="numeric">'
                : '<span class="text-muted small">–</span>';
            const fsCell = hasFs
                ? '<input type="text" class="form-control form-control-sm res-input fs-input" value="' + (fsImportable ? fsMax : '') + '" inputmode="numeric">'
                : '<span class="text-muted small">–</span>';

            let status = badge[r.match_status] || '';
            if (hasOp && r.dup_op) status += ' <span class="badge bg-warning text-dark">OP erfasst</span>';
            if (hasFs && r.dup_fs) status += ' <span class="badge bg-warning text-dark">FS erfasst</span>';

            html += '<tr class="' + trCls + '" data-i="' + i + '">' +
                '<td><input type="checkbox" class="form-check-input row-check" ' + checked + '></td>' +
                '<td class="small fw-semibold">' + escapeHtml(r.matched_name) + '</td>' +
                '<td class="small">' + escapeHtml(r.raw_name) +
                    (r.quelle ? '<div class="text-muted" style="font-size:0.72rem;">' + escapeHtml(r.quelle) + '</div>' : '') + '</td>' +
                '<td class="small">' + geschossen + '</td>' +
                '<td>' + opCell + '</td>' +
                '<td>' + fsCell + '</td>' +
                '<td>' + status + '</td>' +
                '</tr>';
        });
        $('#pdfImportPreviewTable tbody').html(html);
        $('#pdfImportSelectAll').prop('checked', false);
        updateCommitCount();
    }

    function renderSektion() {
        const $c = $('#pdfImportSektion');
        if (!sektionData) { $c.empty(); return; }
        const dup = !!sektionData.dup;
        const checked = dup ? '' : 'checked';
        const verein = sektionData.verein || 'Eigener Verein';
        $c.html(
            '<div class="card ' + (dup ? 'border-warning' : 'border-success') + '">' +
              '<div class="card-body py-2 px-3">' +
                '<div class="d-flex align-items-center flex-wrap gap-2">' +
                  '<input type="checkbox" class="form-check-input mt-0" id="pdfImportSektionCheck" ' + checked + '>' +
                  '<i class="bi bi-people-fill text-success"></i>' +
                  '<strong class="me-1">Sektionsrangierung</strong>' +
                  '<span class="text-muted small me-2">' + escapeHtml(verein) + '</span>' +
                  '<span class="text-nowrap">Rang <input type="text" id="pdfImportSektionRang" class="form-control form-control-sm d-inline-block" style="width:58px;text-align:center;font-weight:600;" value="' + (sektionData.rang != null ? sektionData.rang : '') + '" inputmode="numeric"></span>' +
                  '<span class="text-nowrap">Preis <input type="text" id="pdfImportSektionPreis" class="form-control form-control-sm d-inline-block" style="width:78px;text-align:center;" value="' + (sektionData.preis != null ? sektionData.preis : '') + '" inputmode="decimal"> CHF</span>' +
                  (dup ? '<span class="badge bg-warning text-dark">bereits erfasst</span>' : '') +
                '</div>' +
              '</div>' +
            '</div>'
        );
        $('#pdfImportSektionCheck').on('change', updateCommitCount);
    }

    function updateCommitCount() {
        const n = $('#pdfImportPreviewTable tbody .row-check:checked').length;
        const s = $('#pdfImportSektionCheck').is(':checked') ? 1 : 0;
        $('#pdfImportCommitCount').text(n + s);
        $('#pdfImportCommitBtn').prop('disabled', (n + s) === 0);
    }

    // Delegiert, da der thead im OP/FS-Modus neu aufgebaut wird
    $('#pdfImportPreviewTable').on('change', '#pdfImportSelectAll', function () {
        $('#pdfImportPreviewTable tbody .row-check').prop('checked', this.checked);
        updateCommitCount();
    });
    $('#pdfImportPreviewTable').on('change', '.row-check', updateCommitCount);

    // ---- Import ----
    $('#pdfImportCommitBtn').on('click', function () {
        if (opfsMode) { commitOpfs($(this)); return; }
        const rows = [];
        $('#pdfImportPreviewTable tbody tr').each(function () {
            const $tr = $(this);
            if (!$tr.find('.row-check').is(':checked')) return;
            const r = previewRows[$tr.data('i')];
            if (!r) return;
            rows.push({
                mitglied_id: r.mitglied_id,
                rang: r.rang,
                resultat: ($tr.find('.res-input').val() || '').trim(),
                preis: ($tr.find('.preis-input').val() || '').trim()
            });
        });
        const data = {
            action: 'import', csrf_token: csrf(),
            jmdefinitionID: $('#pdfImportAnlass').val(), year: selectedYear(),
            rows: JSON.stringify(rows)
        };
        if (sektionData && $('#pdfImportSektionCheck').is(':checked')) {
            data.sektion = JSON.stringify({
                rang: ($('#pdfImportSektionRang').val() || '').trim(),
                preis: ($('#pdfImportSektionPreis').val() || '').trim()
            });
        }
        if (!rows.length && !data.sektion) { msvToast('Nichts ausgewählt', 'warning'); return; }

        const $btn = $(this), orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Importiere...');

        $.ajax({
            url: 'rangliste_import/import_api.php', type: 'POST', dataType: 'json',
            data: data
        })
            .done(function (resp) {
                if (resp.success) {
                    msvToast(resp.message, 'success');
                    modal.hide();
                    $('#yearSelect').trigger('change'); // Anlass-Karten + Ranglisten neu laden
                } else {
                    if (resp.csrf_expired) { msvError('Sitzung abgelaufen. Bitte Seite neu laden.'); return; }
                    msvToast(resp.message || 'Fehler beim Import', 'error');
                }
            })
            .fail(function () { msvToast('Fehler beim Import', 'error'); })
            .always(function () { $btn.prop('disabled', false).html(orig); });
    });

    // ---- Import: OP/FS-Bonus aus FSA-Teilnehmerliste ----
    function commitOpfs($btn) {
        const rows = [];
        $('#pdfImportPreviewTable tbody tr').each(function () {
            const $tr = $(this);
            if (!$tr.find('.row-check').is(':checked')) return;
            const r = previewRows[$tr.data('i')];
            if (!r) return;
            rows.push({
                mitglied_id: r.mitglied_id,
                op_punkte: ($tr.find('.op-input').val() || '').trim(),
                fs_punkte: ($tr.find('.fs-input').val() || '').trim()
            });
        });
        if (!rows.length) { msvToast('Nichts ausgewählt', 'warning'); return; }

        const orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Importiere...');

        $.ajax({
            url: 'rangliste_import/import_api.php', type: 'POST', dataType: 'json',
            data: { action: 'import', mode: 'opfs', csrf_token: csrf(), year: selectedYear(), rows: JSON.stringify(rows) }
        })
            .done(function (resp) {
                if (resp.success) {
                    msvToast(resp.message, 'success');
                    modal.hide();
                    $('#yearSelect').trigger('change'); // Anlass-Karten + Ranglisten neu laden
                } else {
                    if (resp.csrf_expired) { msvError('Sitzung abgelaufen. Bitte Seite neu laden.'); return; }
                    msvToast(resp.message || 'Fehler beim Import', 'error');
                }
            })
            .fail(function () { msvToast('Fehler beim Import', 'error'); })
            .always(function () { $btn.prop('disabled', false).html(orig); });
    }
})();
</script>

<?php
include 'footer.inc.php';
?>
