<?php
// jmresultate.php
include 'dbconnect.inc.php';

// Spezifische Styles für JM-Resultate mit Z-Index Fix
$page_specific_css = "
/* ===== Seite: normales Scroll-Verhalten ===== */

/* =========================================
   Anlass Slide-Panel
   ========================================= */
.anlass-panel {
    position: fixed;
    top: 0;
    right: -520px;
    width: 500px;
    height: 100vh;
    background: #fff;
    box-shadow: -8px 0 30px rgba(0,0,0,0.12);
    z-index: 1060;
    transition: right 0.3s cubic-bezier(0.4,0,0.2,1);
    display: flex;
    flex-direction: column;
}
.anlass-panel.open { right: 0; }

.anlass-panel-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.3);
    z-index: 1055;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
}
.anlass-panel-overlay.show { opacity: 1; visibility: visible; }

.anlass-panel-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #1e293b;
    flex-shrink: 0;
}
.anlass-panel-header.sektionsmeisterschaft {
    background: #f0fdf4;
    border-bottom-color: #bbf7d0;
}

.anlass-panel-search {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1.25rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}

.anlass-panel-body {
    overflow-y: auto;
    flex: 1;
    padding: 0;
}

.anlass-panel-footer {
    padding: 0.75rem 1.25rem;
    border-top: 1px solid #e2e8f0;
    background: #fafbfc;
    flex-shrink: 0;
}

.anlass-member-row {
    transition: background 0.15s;
}
.anlass-member-row.has-value {
    background: #f0fdf4;
}

/* Anlass Overview Cards – selected state */
.anlass-overview-card.selected {
    border-color: #6366f1 !important;
    box-shadow: 0 0 0 2px rgba(99,102,241,0.3);
}

@media (max-width: 767.98px) {
    .anlass-panel {
        width: 100vw;
        right: -100vw;
    }
    .anlass-panel-overlay { display: none !important; }
    .anlass-panel-footer .btn { min-height: 48px; font-size: 0.9rem; }
    .anlass-input { min-height: 44px !important; font-size: 16px !important; }
}

/* =========================================
   JM: THEAD-Layout (unten ausrichten, sticky)
   ========================================= */
:root { --jm-head-h: 280px; } /* bei Bedarf 160/180/200px */

#jmresultateTabelle thead tr{
  height: var(--jm-head-h) !important;      /* Zeilenhöhe steuert Gesamthöhe */
}

#jmresultateTabelle thead th{
  display: table-cell !important;           /* echte Table-Cells */
  vertical-align: bottom !important;        /* „valign bottom“ */
  /* keine max-height hier! -> Zelle darf wachsen */
  min-height: var(--jm-head-h) !important;  /* Mindesthöhe */
  position: sticky;
  top: var(--top-hscroll, 0px);             /* berücksichtigt Top-Scrollbar */
  padding: .5rem .5rem .35rem !important;
  line-height: 1.1 !important;
  overflow: visible !important;
}

/* Erste Header-Spalte linksbündig + korrekter Stack */
#jmresultateTabelle thead th:first-child{
  text-align:left !important;
  z-index:110 !important;
}

/* =========================================
   Sticky erste Spalte (Header + Body)
   ========================================= */
#jmresultateTabelle th:first-child{
  position: sticky; left: 0;
  top: var(--top-hscroll, 0px);             /* Offset der Top-Scrollbar */
  background:#f8fafc;
  border-right: 2px solid #dee2e6;
  width:200px; min-width:200px; max-width:200px;
}
#jmresultateTabelle td:first-child{
  position: sticky; left: 0;
  z-index:10 !important;                    /* unter THEAD */
  background:#fff;
  border-right: 2px solid #dee2e6;
}

/* =========================================
   Letzte Spalte: Header sticky, Body NICHT sticky
   ========================================= */
#jmresultateTabelle thead th:last-child{
  position: sticky !important;
  top: var(--top-hscroll, 0px) !important;
  z-index:100 !important;
}
#jmresultateTabelle tbody td:last-child{
  position: static !important;
  left:auto !important; right:auto !important;
  z-index:auto !important;
  box-shadow:none !important;
}

/* Sicherheit: nur die ERSTE Body-Spalte bleibt sticky */
#jmresultateTabelle tbody td:not(:first-child){ position: static !important; }

/* =========================================
   Spaltenbreiten & horizontales Scrollen
   ========================================= */
#jmresultateTabelle{
  table-layout: fixed;
  width: auto;
  min-width: 1800px;                        /* ggf. 2000/2200 erhöhen */
  border-collapse: separate;
  border-spacing: 0;
  --col:80px;
}
#jmresultateTabelle th:not(:first-child),
#jmresultateTabelle td:not(:first-child){
  width: var(--col); min-width: var(--col); max-width: var(--col);
  text-align:center;
}

/* Inputs kompakt */
#jmresultateTabelle .small-input{
  width:65px; min-width:65px; max-width:65px;
  text-align:center; font-size:.85rem;
  padding:.25rem .15rem; font-weight:500;
}

/* unsichtbarer Spacer unten gegen Clipping */
#jmresultateTabelle tbody::after{ content:\\\"\\\"; display:block; height:12px; }

/* Mitglied-Eingaben (status='entwurf') visuell hervorheben */
.jr-cell-wrap{ position: relative; display: inline-block; }
.jr-cell-wrap .small-input.jr-draft{
    border-color:#ffc107;
    background:#fffbea;
    box-shadow: inset 0 0 0 1px #ffc107;
}
.jr-status-dot{
    position:absolute; top:-3px; right:-3px;
    width:9px; height:9px; border-radius:50%;
    background:#ffc107; border:1.5px solid #fff;
    box-shadow:0 0 0 1px #ffc107;
    pointer-events:none;
}

/* Vertikale Zusatz-Header (falls vorhanden) */
#jmresultateTabelle .vertical-header{
  position: sticky; top: var(--top-hscroll, 0px);
  z-index:105 !important; /* zwischen normalem THEAD (100) und erster Spalte (110) */
  background: linear-gradient(180deg,#f8fafc 0%,#eef2f7 100%);
}

/* =========================================
   Responsive Feintuning
   ========================================= */
@media (max-width: 768px){
  #jmresultateTabelle{ min-width:1100px; }
  #jmresultateTabelle th:not(:first-child),
  #jmresultateTabelle td:not(:first-child){ --col:60px; }
  #jmresultateTabelle th:first-child, #jmresultateTabelle td:first-child{
    width:150px; min-width:150px; max-width:150px;
  }
  #jmresultateTabelle .small-input{
    width:50px; min-width:50px; max-width:50px;
    font-size:.75rem; padding:.2rem .1rem;
  }
}

/* =========================================
   Mobile Cards Optimierung
   ========================================= */
@media (max-width: 767.98px) {
  /* Desktop-Tabelle ausblenden, Mobile Cards einblenden (nur alte Eingabe-Tabelle) */
  #jmInputTableWrapper .desktop-table-container { display: none !important; }
  #jmInputTableWrapper .mobile-cards-container { display: flex !important; }

  /* WCAG AAA Touch Targets: Alle Form-Elemente */
  .form-control,
  .form-select,
  input[type=\"text\"],
  input[type=\"number\"],
  select {
    min-height: 48px !important;
    font-size: 16px !important; /* Verhindert iOS Auto-Zoom */
  }

  .btn {
    min-height: 48px !important;
    font-size: 16px !important;
    padding: 0.5rem 1rem !important;
  }

  /* Mobile Inputs: WCAG AAA touch targets + iOS zoom prevention */
  .mobile-card-body .small-input-mobile {
    min-height: 48px !important;
    font-size: 16px !important;
    padding: 0.5rem !important;
    text-align: center !important;
    font-weight: 500 !important;
  }

  /* Mobile Card Body: bessere Abstände */
  .mobile-card-body .mb-3 {
    margin-bottom: 1rem !important;
  }

  .mobile-card-body .form-label {
    margin-bottom: 0.35rem !important;
    color: #475569 !important;
    font-size: 0.875rem !important;
  }

  /* Detail Rows (readonly Felder): kompakter */
  .mobile-card-detail-row {
    padding: 0.5rem 0 !important;
    border-bottom: 1px solid #f1f5f9 !important;
  }

  .mobile-card-detail-label {
    font-size: 0.875rem !important;
    color: #64748b !important;
  }

  .mobile-card-detail-value {
    font-size: 0.95rem !important;
    color: #1e293b !important;
  }

  /* Buttons: größere Touch-Targets */
  .button-toolbar .btn {
    min-height: 48px !important;
    font-size: 0.95rem !important;
  }
}

/* ===== RANGLISTEN (wie jmrang.php) ===== */
#rankJMA, #rankJMB {
    border-collapse: separate !important;
    border-spacing: 0 !important;
}
#rankJMA tbody td, #rankJMB tbody td {
    border-top: none !important;
    border-right: none !important;
    border-bottom: 1px solid #dee2e6 !important;
}
#rankJMA thead, #rankJMB thead {
    position: sticky !important;
    top: 0 !important;
    z-index: 11 !important;
}
#rankJMA thead th, #rankJMB thead th {
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
    background-color: var(--light-color) !important;
    vertical-align: bottom !important;
    writing-mode: horizontal-tb !important;
    text-orientation: initial !important;
    height: auto !important;
    min-width: auto !important;
    max-width: none !important;
    white-space: normal !important;
    overflow: visible !important;
    font-size: 0.8rem !important;
    padding: 0.5rem 0.4rem !important;
    font-weight: 600 !important;
    border-bottom: none !important;
    border-top: none !important;
    box-shadow: inset 0 -2px 0 #dee2e6 !important;
}

.jm-th-rang  { width: 55px !important; }
.jm-th-result{ width: 84px !important; }
.jm-th-total { width: 90px !important; }
.jm-th-toggle{ width: 40px !important; }

/* Lange Anlass-Namen in der Kopfzeile kürzen (voller Name via Tooltip) */
.jm-th-label {
    display: block;
    max-width: 78px;
    margin: 0 auto;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.jm-main-row { cursor: pointer; }
#rankJMA tbody tr.jm-main-row:hover td,
#rankJMB tbody tr.jm-main-row:hover td {
    background-color: rgba(108, 117, 125, 0.06) !important;
}

/* Zellen: Resultate ausgerichtet, Total hervorgehoben, Streicher rot durchgestrichen */
.jm-result-cell   { font-variant-numeric: tabular-nums; }
.jm-cell-strichen { color: #dc3545; text-decoration: line-through; }
.jm-total-cell    { color: #198754; font-variant-numeric: tabular-nums; font-size: 1rem; }
.jm-rang-cell     { color: #334155; }

.jm-toggle-btn {
    color: #6c757d !important;
    text-decoration: none !important;
    font-size: 1rem !important;
}
.jm-toggle-btn i { transition: transform 0.2s ease; }
.jm-toggle-btn.expanded i { transform: rotate(180deg); }
.jm-toggle-btn:hover { color: var(--primary-color) !important; }

/* ===== DETAIL-PANEL (gruppiert) ===== */
.jm-detail-row > td {
    padding: 0 !important;
    border-top: none !important;
    width: auto !important;
    text-align: left !important;
    background-color: transparent !important;
    font-weight: normal !important;
}
.jm-detail-panel {
    background: #f8fafb !important;
    border-top: 1px solid #e2e8f0 !important;
    border-bottom: 2px solid #dee2e6 !important;
    padding: 1rem 1.25rem !important;
    text-align: left !important;
}
.jm-detail-groups {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
    align-items: start;
}
.jm-detail-group {
    background: #fff;
    border: 1px solid #e7edf3;
    border-radius: 0.6rem;
    overflow: hidden;
}
.jm-detail-group-head {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 0.5rem 0.85rem;
    background: #f1f5f9;
    border-bottom: 1px solid #e7edf3;
}
.jm-detail-group-title { font-weight: 700; font-size: 0.82rem; color: #334155; }
.jm-detail-group-meta  { font-size: 0.72rem; color: #94a3b8; }
.jm-group-pflicht .jm-detail-group-title { color: #0f766e; }
.jm-group-streich .jm-detail-group-title { color: #1d4ed8; }

.jm-detail-lines { padding: 0.25rem 0.35rem; }
.jm-detail-line {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 0.5rem;
    padding: 0.34rem 0.5rem;
    border-radius: 0.35rem;
    font-size: 0.85rem;
}
.jm-detail-line + .jm-detail-line { border-top: 1px solid #f1f5f9; }
.jm-detail-line:hover { background: #f8fafc; }
.jm-line-name { color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.jm-line-pts  { display: inline-flex; align-items: baseline; gap: 0.35rem; flex-shrink: 0; white-space: nowrap; }
.jm-line-val  { font-weight: 700; color: #1e293b; font-variant-numeric: tabular-nums; }
.jm-line-max  { font-size: 0.72rem; color: #94a3b8; }
.jm-line-empty .jm-line-name,
.jm-line-empty .jm-line-val { color: #adb5bd; font-weight: 400; }

.jm-detail-line.gestrichen { opacity: 0.7; }
.jm-detail-line.gestrichen .jm-line-val { color: #dc3545; text-decoration: line-through; }
.jm-line-tag {
    font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.4px;
    color: #b91c1c; background: #fee2e2; border-radius: 999px;
    padding: 0.06rem 0.4rem; font-weight: 700;
}

.jm-detail-subtotal {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.45rem 0.85rem;
    border-top: 1px solid #e7edf3;
    background: #fbfdff;
    font-size: 0.8rem; font-weight: 600; color: #475569;
}
.jm-detail-subtotal span:last-child { font-weight: 700; color: #1e293b; font-variant-numeric: tabular-nums; }

.jm-detail-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.85rem;
    padding: 0.6rem 0.9rem;
    background: rgba(25, 135, 84, 0.08);
    border: 1px solid rgba(25, 135, 84, 0.25);
    border-radius: 0.5rem;
    font-weight: 700; font-size: 0.95rem; color: #14532d;
}
.jm-detail-total-val { color: #198754; font-size: 1.1rem; font-variant-numeric: tabular-nums; }
.jm-detail-total.jm-detail-total-offen {
    background: #f1f5f9; border-color: #e2e8f0; color: #64748b; font-weight: 600; font-size: 0.85rem;
}

/* Ranking Table Layout */
.rank-table-wrapper { border: 1px solid #dee2e6; border-radius: 0.5rem; overflow: hidden; }
.rank-table-wrapper .table-responsive { overflow-x: auto; max-height: 50vh; }
.rank-table-wrapper .table-title {
    position: relative !important;
    z-index: 100 !important;
    background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%) !important;
    padding: 0.75rem 1.25rem !important;
    margin: 0 !important;
    border-bottom: 2px solid #dee2e6 !important;
}

/* Mobile Ranking */
@media (max-width: 767.98px) {
    .jm-detail-groups { grid-template-columns: 1fr !important; }
    .jm-mobile-card .mobile-card-header { padding: 0.75rem 1rem; }
    .jm-mobile-rang {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px; height: 28px;
        border-radius: 50%;
        background: #e9ecef;
        font-weight: 700;
        font-size: 0.85rem;
        color: #495057;
        flex-shrink: 0;
    }
    .rank-1 .jm-mobile-rang { background: #ffd700; color: #5a4800; }
    .rank-2 .jm-mobile-rang { background: #c0c0c0; color: #3a3a3a; }
    .rank-3 .jm-mobile-rang { background: #cd7f32; color: #fff; }
    .jm-mobile-total { font-weight: 700; font-size: 0.95rem; color: #198754; white-space: nowrap; }
    .jm-mobile-card .mobile-card-body { padding: 0 !important; }
    .jm-mobile-card .mobile-card-body .jm-detail-panel { border-top: none !important; border-bottom: none !important; padding: 0.75rem !important; }

    .rank-table-wrapper .desktop-table-container { display: none !important; }
    .rank-table-wrapper .mobile-cards-container { display: flex !important; }
}
";

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Serverseitige Vorberechnung -> Anlass-Karten + Veroeffentlichen-Button sofort anzeigen
// (kein AJAX-Flackern). Fallback: JS laedt per AJAX.
require_once __DIR__ . '/jmresultate/anlaesse_data.php';
require_once __DIR__ . '/changelog_helper.php';
$__jmInitYear = (int) date('Y');
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
<link rel="stylesheet" href="../css/fixes/table-title-and-firstcol-override.css">
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-12 col-lg-12 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-trophy me-2"></i>
                            Erfassung Jahresmeisterschaft
                        </h2>
                    </div>
                </div>

                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <form id="jmresultateForm">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Jahr-Auswahl + Aktionen nebeneinander -->
                        <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                                <i class="bi bi-calendar3 me-1"></i>Jahr:
                            </label>
                            <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
                        </div>

                        <!-- Aktionsbereich (Bootstrap Collapse) -->
                        <div class="card action-card mb-0">
                            <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
                                 data-bs-toggle="collapse" data-bs-target="#jmresultateActions"
                                 aria-expanded="false" aria-controls="jmresultateActions">
                                <span class="fw-semibold"><i class="bi bi-tools me-2"></i>Aktionen</span>
                                <i class="bi bi-chevron-down action-chevron"></i>
                            </div>
                            <div class="collapse" id="jmresultateActions">
                                <div class="card-body pt-2 pb-3 px-3">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                                <i class="bi bi-save me-1"></i>Speichern
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button id="redirect-btn" type="button" class="btn btn-outline-info btn-sm w-100">
                                                <i class="bi bi-trophy me-1"></i>Rangliste
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button id="pdf-import-btn" type="button" class="btn btn-outline-success btn-sm w-100">
                                                <i class="bi bi-filetype-pdf me-1"></i>PDF importieren
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button id="btnPublishJm" type="button" class="btn btn-outline-success btn-sm w-100 <?= $__jmUnpublished > 0 ? '' : 'd-none' ?>" title="Unveröffentlichte JM-Resultate freigeben">
                                                <i class="bi bi-megaphone me-1"></i>Veröffentlichen <span id="publishBadge" class="badge bg-warning text-dark ms-1"><?= $__jmUnpublished > 0 ? (int) $__jmUnpublished : '' ?></span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="border-top mt-2 pt-2 text-end">
                                        <button id="delete-btn" type="button" class="btn btn-link btn-sm text-danger text-decoration-none p-0">
                                            <i class="bi bi-trash me-1"></i>Alle Resultate löschen
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div><!-- Ende flex-row Jahr+Aktionen -->

                        <!-- ===== ANLASS OVERVIEW CARDS ===== -->
                        <div id="anlassCardsGrid" class="mb-3" style="display:none;">
                            <!-- Wird per JS befüllt -->
                        </div>

                        <!-- Anlass-Panel wird ausserhalb des Containers platziert (s. unten) -->

                        <!-- ===== RANGLISTEN (wie jmrang.php) ===== -->
                        <div class="info-card mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Hinweis:</strong> Die roten durchgestrichenen Werte sind Streicher und werden nicht in die Gesamtwertung einbezogen.
                        </div>

                        <!-- Kategorie A Rangliste -->
                        <div class="rank-table-wrapper mb-3">
                            <h5 class="table-title">
                                <i class="bi bi-star me-2"></i>
                                Jahresmeisterschaft Kat. A
                            </h5>
                            <div class="desktop-table-container">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="rankJMA">
                                        <thead></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="mobile-cards-container" id="mobileCardsRankJMA">
                                <div class="mobile-search">
                                    <div class="position-relative">
                                        <i class="bi bi-search search-icon"></i>
                                        <input type="text" class="form-control" placeholder="Suchen..."
                                               oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsRankJMA')">
                                    </div>
                                </div>
                                <div class="mobile-cards-scroll"></div>
                            </div>
                        </div>

                        <!-- Kategorie B Rangliste -->
                        <div class="rank-table-wrapper mb-3">
                            <h5 class="table-title">
                                <i class="bi bi-star-half me-2"></i>
                                Jahresmeisterschaft Kat. B
                            </h5>
                            <div class="desktop-table-container">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="rankJMB">
                                        <thead></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="mobile-cards-container" id="mobileCardsRankJMB">
                                <div class="mobile-search">
                                    <div class="position-relative">
                                        <i class="bi bi-search search-icon"></i>
                                        <input type="text" class="form-control" placeholder="Suchen..."
                                               oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsRankJMB')">
                                    </div>
                                </div>
                                <div class="mobile-cards-scroll"></div>
                            </div>
                        </div>

                        <!-- Alte Eingabe-Tabelle (versteckt, Input via Anlass-Modal) -->
                        <div class="table-wrapper" id="jmInputTableWrapper" style="display:none;">
                            <div class="desktop-table-container">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="jmresultateTabelle">
                                        <!-- Tabelleninhalte werden dynamisch geladen -->
                                    </table>
                                </div>
                            </div>
                            <div class="mobile-cards-container" id="mobileCardsJMResultate">
                                <div class="mobile-search">
                                    <div class="position-relative">
                                        <i class="bi bi-search search-icon"></i>
                                        <input type="text" class="form-control" placeholder="Mitglied suchen..."
                                               oninput="filterMobileJM(this)">
                                    </div>
                                </div>
                                <div class="mobile-cards-scroll"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Anlass Slide-Panel Overlay -->
<div class="anlass-panel-overlay" id="anlassPanelOverlay"></div>

<!-- Anlass Slide-Panel -->
<div class="anlass-panel" id="anlassPanel">
    <div class="anlass-panel-header" id="anlassPanelHeader">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h6 class="mb-0" id="anlassPanelTitle" style="font-weight:700;">
                    <i class="bi bi-crosshair me-2"></i>Anlass
                </h6>
                <div id="anlassPanelMeta" style="font-size:0.85rem; color:#64748b;"></div>
            </div>
            <button class="btn btn-sm btn-outline-secondary" id="anlassPanelClose">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
    <div class="anlass-panel-search">
        <div class="position-relative flex-fill">
            <i class="bi bi-search" style="position:absolute; left:0.85rem; top:50%; transform:translateY(-50%); color:#94a3b8;"></i>
            <input type="text" id="anlassPanelSearch" class="form-control form-control-sm"
                   placeholder="Mitglied suchen..." style="padding-left:2.5rem; border-radius:8px;">
        </div>
        <div id="anlassPanelCounter" style="font-size:0.85rem; color:#64748b; white-space:nowrap;">
            <strong style="color:#6366f1;">0</strong>/0 erfasst
        </div>
    </div>
    <div class="anlass-panel-body" id="anlassPanelBody">
        <!-- Wird per JS befüllt -->
    </div>
    <div class="anlass-panel-footer">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2" style="font-size:0.85rem; color:#64748b;">
                <div style="height:6px; width:100px; border-radius:3px; background:#e2e8f0; overflow:hidden;">
                    <div id="anlassPanelProgressBar" style="height:100%; width:0%; border-radius:3px; background:linear-gradient(90deg,#6366f1,#818cf8); transition:width 0.3s;"></div>
                </div>
                <span id="anlassPanelProgressText">0/0</span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="anlassPanelCancelBtn">Abbrechen</button>
                <button type="button" id="btnAnlassSave" class="btn btn-sm"
                        style="background:#6366f1; color:#fff; font-weight:700; border-radius:8px; padding:0.5rem 1.5rem;">
                    <i class="bi bi-save me-1"></i>Speichern
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Unsaved Changes Modal -->
<div class="modal fade" id="unsavedChangesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-exclamation-triangle me-2"></i> Ungespeicherte Änderungen
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        Du hast Änderungen, die noch nicht gespeichert sind. Was möchtest du tun?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btn-cancel-leave" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-outline-danger" id="btn-leave-without-save">Ohne Speichern verlassen</button>
        <button type="button" class="btn btn-success" id="btn-save-and-leave">Speichern & verlassen</button>
      </div>
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
            <p class="text-muted small mb-0">Einzelrangliste eines Anlasses (z.B. Vereinsstich). Vereinsmitglieder werden automatisch erkannt.</p>
          </div>
          <input type="file" id="pdfImportFile" accept="application/pdf" style="display:none;">
        </div>

        <!-- Schritt 2: Vorschau -->
        <div id="pdfImportStep2" style="display:none;">
          <div id="pdfImportStats" class="alert alert-info py-2 px-3 small mb-2"></div>
          <div id="pdfImportSektion" class="mb-2"></div>
          <p class="text-muted small mb-2">
            <i class="bi bi-trophy-fill text-warning"></i> = Top&nbsp;10 (wird zusätzlich als Einzelrangierung gespeichert) ·
            Gelb markierte Zeilen sind bereits erfasst und standardmässig abgewählt.
          </p>
          <div class="table-responsive" style="max-height:50vh;">
            <table class="table table-sm table-hover align-middle mb-0" id="pdfImportPreviewTable">
              <thead class="table-light" style="position:sticky; top:0; z-index:2;">
                <tr>
                  <th style="width:36px;"><input type="checkbox" id="pdfImportSelectAll" class="form-check-input" title="Alle"></th>
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
        <button type="button" class="btn btn-success" id="pdfImportCommitBtn" style="display:none;"><i class="bi bi-download me-1"></i>Importieren (<span id="pdfImportCommitCount">0</span>)</button>
      </div>
    </div>
  </div>
</div>

<script>
    // Serverseitig vorberechnet (siehe PHP oben) -> sofortige Anzeige ohne AJAX-Verzoegerung
    window.JM_INITIAL_ANLAESSE = <?= json_encode($__jmInitAnlaesse, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
    $(function () {
        var currentYear = new Date().getFullYear();
        var startYear = currentYear - 3;
        var basePath = '';
        var $yearDD = $('#yearSelect');

        function showMessage(m, t) { const map = { danger: 'error', success: 'success', warning: 'warning', info: 'info' }; msvToast(m, map[t] || 'info'); }

        // Jahr-Dropdown
        $yearDD.empty();
        for (let y = currentYear; y >= startYear; y--) $yearDD.append($('<option>', { value: y, text: y }));
        $yearDD.val(currentYear);

        // Daten laden
        window.loadJMResultate = function(year) {
            $('#jmresultateTabelle').html(
                '<tr><td class="text-center py-4"><div class="spinner-border spinner-border-sm me-2" style="color:var(--secondary-color);"></div>Lade Daten...</td></tr>'
            );
            $.get(basePath + 'jmresultate/load_jmresultate_form.php', { year })
                .done(function (html) {
                    $('#jmresultateTabelle').html(html);
                    msvToast('Daten erfolgreich geladen', 'success');

                    // Tooltips
                    $('#jmresultateTabelle input.small-input').each(function () {
                        const col = $(this).closest('td').index();
                        const headerText = $('#jmresultateTabelle thead th').eq(col).text().trim();
                        $(this).attr('data-tooltip', headerText);
                    });

                    // Spalten-Highlight
                    $(document).off('focus', '#jmresultateTabelle input')
                        .on('focus', '#jmresultateTabelle input', function () {
                            $('#jmresultateTabelle td, #jmresultateTabelle th').removeClass('active-column active-column-header');
                            const col = $(this).closest('td').index();
                            $('#jmresultateTabelle tr').each(function () {
                                $(this).find('td').eq(col).addClass('active-column');
                                $(this).find('th').eq(col).addClass('active-column-header');
                            });
                        })
                        .off('blur', '#jmresultateTabelle input')
                        .on('blur', '#jmresultateTabelle input', function () {
                            setTimeout(() => {
                                if (!$('#jmresultateTabelle input:focus').length) {
                                    $('#jmresultateTabelle td, #jmresultateTabelle th').removeClass('active-column active-column-header');
                                }
                            }, 100);
                        });

                    // Mobile Cards generieren
                    buildMobileJMCards();
                })
                .fail(function () {
                    $('#jmresultateTabelle').html(
                        '<tr><td class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden der Daten</td></tr>'
                    );
                    msvToast('Fehler beim Laden der Daten', 'error');
                });
        }
        loadJMResultate(currentYear);
        $yearDD.on('change', function () { loadJMResultate($(this).val()); });

        // Speichern - nach erfolgreichem Speichern isDirty zurücksetzen
        $('#jmresultateForm').on('submit.main', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation(); // Verhindert, dass andere Handler ausgeführt werden
            
            const $btn = $(this).find('button[type="submit"]').prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
            const formData = $(this).serialize() + '&year=' + $yearDD.val();

            $.post(basePath + 'jmresultate/save_jmresultate.php', formData)
                .done(() => { 
                    // WICHTIG: isDirty auf false setzen nach erfolgreichem Speichern
                    window.isDirtyFlag = false;
                    // Trigger custom event um anderen Code zu informieren
                    $(document).trigger('jmresultate:saved');
                    
                    showMessage('Ergebnisse erfolgreich gespeichert!', 'success'); 
                    setTimeout(() => {
                        loadJMResultate($yearDD.val());
                        if (window.loadRanglisten) window.loadRanglisten($yearDD.val());
                        window.isDirtyFlag = false; // Nochmal nach dem Laden zurücksetzen
                    }, 800); 
                })
                .fail(() => { 
                    showMessage('Fehler beim Speichern der Ergebnisse', 'danger'); 
                })
                .always(() => { 
                    $btn.prop('disabled', false).html('<i class="bi bi-save me-2"></i>Ergebnisse speichern'); 
                });
        });

        // Löschen
        $('#delete-btn').on('click', async function (e) {
            e.preventDefault();
            const r = await msvConfirm(
                'Möchtest du wirklich ALLE Resultate des aktuellen Jahres löschen?',
                'Alle Resultate löschen',
                'Ja, alles löschen'
            );
            if (!r.isConfirmed) return;

            $.post(basePath + 'jmresultate/delete_jmresultate.php', {
                year: $yearDD.val(),
                csrf_token: $('input[name="csrf_token"]').val()
            }).done(function () {
                showMessage('Alle aktuellen Resultate erfolgreich gelöscht', 'success');
                setTimeout(() => loadJMResultate($yearDD.val()), 600);
            }).fail(function () {
                showMessage('Fehler beim Löschen der aktuellen Resultate', 'danger');
            });
        });

        // Mobile Cards für JM-Resultate generieren
        function buildMobileJMCards() {
            const isMobile = window.matchMedia('(max-width: 767.98px)');
            if (!isMobile.matches) return;

            const table = document.getElementById('jmresultateTabelle');
            const container = document.querySelector('#mobileCardsJMResultate .mobile-cards-scroll');
            if (!table || !container) return;

            const thead = table.querySelector('thead');
            const tbody = table.querySelector('tbody');
            if (!thead || !tbody) {
                container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
                return;
            }

            // Headers extrahieren
            const headers = Array.from(thead.querySelectorAll('th')).map(th => th.textContent.trim());
            const rows = tbody.querySelectorAll('tr');

            if (rows.length === 0) {
                container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
                return;
            }

            let html = '';
            rows.forEach((row, idx) => {
                const cells = Array.from(row.querySelectorAll('td'));
                if (cells.length === 0) return;

                // Erste Zelle: Mitgliedername
                const memberName = cells[0]?.textContent?.trim() || 'Unbekannt';

                // Felder sammeln (alle außer erste Spalte)
                let fieldsHtml = '';
                let summaryTotal = '';

                cells.forEach((cell, colIdx) => {
                    if (colIdx === 0) return; // Name überspringen

                    const label = headers[colIdx] || `Spalte ${colIdx}`;
                    const input = cell.querySelector('input');
                    const isReadonly = input && input.hasAttribute('readonly');
                    const value = input ? input.value : cell.textContent.trim();

                    // Wenn readonly und letzte Spalte: als Summary behandeln
                    if (isReadonly && colIdx === cells.length - 1) {
                        summaryTotal = `<small class="text-muted">Total: <strong>${value}</strong></small>`;
                        return;
                    }

                    if (input) {
                        // Input-Feld mit gleichem Namen wie Desktop
                        const inputName = input.name || '';
                        const inputType = input.type || 'text';
                        const inputValue = input.value || '';

                        if (isReadonly) {
                            // Readonly Feld (berechnete Werte)
                            fieldsHtml += `
                                <div class="mobile-card-detail-row">
                                    <span class="mobile-card-detail-label">${label}</span>
                                    <span class="mobile-card-detail-value"><strong>${inputValue}</strong></span>
                                </div>`;
                        } else {
                            // Editierbares Input-Feld (data-name statt name, um Duplikate zu vermeiden)
                            fieldsHtml += `
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">${label}</label>
                                    <input type="${inputType}"
                                           class="form-control small-input-mobile"
                                           data-name="${inputName}"
                                           value="${inputValue}"
                                           inputmode="numeric"
                                           pattern="[0-9]*">
                                </div>`;
                        }
                    } else if (value) {
                        // Readonly Wert ohne Input (z.B. berechnete Felder)
                        fieldsHtml += `
                            <div class="mobile-card-detail-row">
                                <span class="mobile-card-detail-label">${label}</span>
                                <span class="mobile-card-detail-value"><strong>${value}</strong></span>
                            </div>`;
                    }
                });

                html += `
                <div class="mobile-card" data-index="${idx}">
                    <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                        <div>
                            <div class="fw-bold">${memberName}</div>
                            ${summaryTotal}
                        </div>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="mobile-card-body">
                        ${fieldsHtml}
                    </div>
                </div>`;
            });

            container.innerHTML = html;

            // Event-Listener für Inputs: Werte zurück in Desktop-Tabelle schreiben
            container.querySelectorAll('input[data-name]').forEach(input => {
                input.addEventListener('input', function() {
                    // Finde das entsprechende Desktop-Input anhand des data-name Attributs
                    const inputName = this.getAttribute('data-name');
                    const desktopInput = table.querySelector(`input[name="${inputName}"]`);
                    if (desktopInput) {
                        desktopInput.value = this.value;
                        // Trigger change event für Autosave/isDirty-Tracking
                        $(desktopInput).trigger('input');
                    }
                });
            });
        }

        // Mobile Search Filter (global für inline oninput)
        window.filterMobileJM = function(searchInput) {
            const query = searchInput.value.toLowerCase();
            const cards = document.querySelectorAll('#mobileCardsJMResultate .mobile-card');

            let visibleCount = 0;
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                const isVisible = text.includes(query);
                card.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            // Empty State
            const container = document.querySelector('#mobileCardsJMResultate .mobile-cards-scroll');
            const existingEmpty = container.querySelector('.mobile-cards-empty');
            if (visibleCount === 0 && !existingEmpty) {
                container.insertAdjacentHTML('beforeend', `
                    <div class="mobile-cards-empty">
                        <i class="bi bi-search"></i>
                        <div>Keine Treffer gefunden</div>
                    </div>`);
            } else if (visibleCount > 0 && existingEmpty) {
                existingEmpty.remove();
            }
        }

        // Resize-Listener: Cards neu generieren bei Wechsel zu Mobile
        let wasDesktop = window.matchMedia('(min-width: 768px)').matches;
        window.addEventListener('resize', debounce(function() {
            const isNowDesktop = window.matchMedia('(min-width: 768px)').matches;
            if (wasDesktop && !isNowDesktop) {
                // Von Desktop zu Mobile gewechselt
                buildMobileJMCards();
            }
            wasDesktop = isNowDesktop;
        }, 250));

        function debounce(fn, wait) { let t; return function () { clearTimeout(t); t = setTimeout(() => fn.apply(this, arguments), wait); }; }
    });
</script>

<!-- Hinweis: Der #redirect-btn-Handler liegt weiter unten (mit Schutz vor ungespeicherten
     Änderungen via requestNavigation). Der frühere doppelte Handler hier wurde entfernt. -->
<script>
(function(){
  const $doc = $(document);
  let isDirty = false;            // Flag: ungespeicherte Änderungen vorhanden
  let pendingNav = null;          // Ziel-URL, wenn User weg navigieren möchte
  // AUTOSAVE DEAKTIVIERT - const DEBOUNCE_MS = 800;
  
  // Listen for saved event from main form handler
  $doc.on('jmresultate:saved', function() {
    isDirty = false;
    window.isDirtyFlag = false;
  });

  // ---- Debounce Helper ----
  function debounce(fn, ms){
    let t; return function(...args){
      clearTimeout(t); t = setTimeout(()=>fn.apply(this,args), ms);
    };
  }

  // ---- Payload-Buffer für gebündelte Saves ----
  const pendingPayload = { punkte:{}, punkte_runde1:{}, punkte_runde2:{} };

  function addChangeToPayload(input){
    const name = input.name || '';
    const val  = input.value;
    // Erwartete Namen: punkte[123][456], punkte_runde1[123][456], punkte_runde2[123][456]
    const m = name.match(/^(punkte(?:_runde1|_runde2)?)\[(\d+)\]\[(\d+)\]$/);
    if(!m) return; // unbekanntes Feld, ignorieren
    const bucket = m[1], mid = m[2], did = m[3];
    if(!pendingPayload[bucket][mid]) pendingPayload[bucket][mid] = {};
    pendingPayload[bucket][mid][did] = val;
  }

  function hasPayload(){
    return Object.values(pendingPayload).some(group => Object.keys(group).length);
  }

  function flushPayload(){
    const copy = {
      punkte: JSON.parse(JSON.stringify(pendingPayload.punkte)),
      punkte_runde1: JSON.parse(JSON.stringify(pendingPayload.punkte_runde1)),
      punkte_runde2: JSON.parse(JSON.stringify(pendingPayload.punkte_runde2))
    };
    pendingPayload.punkte = {};
    pendingPayload.punkte_runde1 = {};
    pendingPayload.punkte_runde2 = {};
    return copy;
  }

  // ---- AUTOSAVE DEAKTIVIERT ----
  // Die automatische Speicherfunktion wurde entfernt.
  // Änderungen werden nur noch über den "Speichern"-Button gespeichert.
  /*
  const debouncedAutoSave = debounce(() => {
    if(!hasPayload()) return;
    const data = flushPayload();
    $.ajax({
      url: 'inc/jmresultate/save_jmresultate.php',
      method: 'POST',
      data,
      dataType: 'json'
    }).done(res=>{
      if(res && res.success){
        if(!hasPayload()) isDirty = false;
        msvToast('Gespeichert.', 'success');
      } else {
        isDirty = true;
        msvToast('Fehler beim Speichern: ' + (res?.message || 'Unbekannt'), 'error');
      }
    }).fail(xhr=>{
      isDirty = true;
      console.error('Autosave failed', xhr);
      msvToast('Speichern fehlgeschlagen (HTTP ' + xhr.status + ')', 'error');
    });
  }, DEBOUNCE_MS);
  */

  // ---- Input-Listener: Nur noch Änderungen markieren (kein Autosave mehr) ----
  $doc.on('input change', '#jmresultateTabelle input, #heimresultateTabelle input, #kantiresultateTabelle input', function(){
    isDirty = true;
    window.isDirtyFlag = true; // Global verfügbar machen für andere Scripts
    addChangeToPayload(this);
    // debouncedAutoSave(); // AUTOSAVE DEAKTIVIERT
  });

  // ---- Browser-Refresh/Tab-Schliessen: nativer Hinweis (Modal geht hier nicht!) ----
  window.addEventListener('beforeunload', function(e){
    if(!isDirty) return;
    e.preventDefault();
    e.returnValue = ''; // zeigt Standard-Warnung an
  });

  // ---- Interne Navigation abfangen -> Modal ----
  function requestNavigation(href){
    if(!isDirty){ window.location.href = href; return; }
    pendingNav = href;
    const modal = new bootstrap.Modal(document.getElementById('unsavedChangesModal'));
    modal.show();
  }

  // Links (gleiche Origin), ohne target/_blank und ohne Anker
  $doc.on('click', 'a[href]:not([target]):not([href^="#"]):not([data-ignore-unsaved])', function(e){
    // Nur gleiche Domain abfangen (extern ggf. direkt durchlassen)
    if(this.origin !== window.location.origin) return;
    e.preventDefault();
    requestNavigation(this.href);
  });

  // Form-Submits komplett ignorieren - das Hauptformular hat seinen eigenen Handler
  // Keine Form-Submits abfangen, da das normale Speichern funktionieren soll

  // Spezieller Redirect-Button
  $doc.on('click', '#redirect-btn', function(e){
    e.preventDefault();
    const y = $('#yearSelect').val();
    const href = 'jmrang.php' + (y ? ('?year='+encodeURIComponent(y)) : '');
    requestNavigation(href);
  });

  // ---- Modal-Buttons ----
  $('#btn-leave-without-save').on('click', function(){
    isDirty = false; // beforeunload nicht auslösen
    const href = pendingNav; pendingNav = null;
    bootstrap.Modal.getInstance(document.getElementById('unsavedChangesModal')).hide();
    if(href) window.location.href = href;
  });

  $('#btn-save-and-leave').on('click', function(){
    const $btn = $(this).prop('disabled', true);
    const modalEl = document.getElementById('unsavedChangesModal');

    // Sofort speichern (bypass Debounce) falls noch Änderungen im Buffer sind
    const proceed = () => {
      isDirty = false;
      bootstrap.Modal.getInstance(modalEl).hide();
      const href = pendingNav; pendingNav = null;
      if(href) window.location.href = href;
      $btn.prop('disabled', false);
    };

    if(hasPayload()){
      const dataNow = flushPayload();
      $.ajax({
        url: 'inc/jmresultate/save_jmresultate.php',
        method: 'POST',
        data: dataNow,
        dataType: 'json'
      }).done(res=>{
        if(res && res.success){
          proceed();
        } else {
          isDirty = true;
          $btn.prop('disabled', false);
          msvToast('Speichern fehlgeschlagen: ' + (res?.message || ''), 'error');
        }
      }).fail(xhr=>{
        isDirty = true;
        $btn.prop('disabled', false);
        msvToast('Speichern fehlgeschlagen (HTTP ' + xhr.status + ')', 'error');
      });
    } else {
      // Nichts im Buffer -> einfach verlassen
      proceed();
    }
  });

  $('#btn-cancel-leave').on('click', function(){
    pendingNav = null; // einfach Modal schliessen
  });


})();
</script>

<script>
/**
 * Anlass-basierte Eingabe für JM-Resultate
 */
(function() {
    const $yearDD = $('#yearSelect');
    let currentAnlassData = null;

    // ---- Anlässe laden wenn Jahr wechselt ----
    function loadAnlaesse(year) {
        $.get('jmresultate/load_anlaesse.php', { year: year })
            .done(function(resp) {
                if (!resp.success) return;
                buildAnlassCards(resp.anlaesse);
            });
    }

    // Initiales Laden: serverseitig vorberechnete Daten sofort rendern (kein AJAX-Flackern),
    // sonst Fallback auf AJAX.
    $(function() {
        const init = window.JM_INITIAL_ANLAESSE;
        if (init && Array.isArray(init.anlaesse) && init.anlaesse.length) {
            buildAnlassCards(init.anlaesse);
        } else {
            loadAnlaesse($yearDD.val());
        }
    });
    $yearDD.on('change', function() { closeAnlassPanel(); loadAnlaesse($(this).val()); });

    // ---- Panel öffnen (wird von den Anlass-Karten aufgerufen) ----
    function openAnlassPanel(jmdefID) {
        const year = $yearDD.val();
        const $body = $('#anlassPanelBody');
        $body.html('<div class="text-center py-4"><div class="spinner-border spinner-border-sm me-2" style="color:#6366f1;"></div>Lade...</div>');

        // Karte markieren
        $('.anlass-overview-card').removeClass('selected');
        $('.anlass-overview-card[data-id="' + jmdefID + '"]').addClass('selected');

        // Panel öffnen
        $('#anlassPanel').addClass('open');
        $('#anlassPanelOverlay').addClass('show');

        // Suche zurücksetzen
        $('#anlassPanelSearch').val('');

        $.get('jmresultate/load_anlass_form.php', { year: year, jmdefinitionID: jmdefID })
            .done(function(resp) {
                if (!resp.success) {
                    $body.html('<div class="text-center py-4 text-danger">Fehler: ' + resp.message + '</div>');
                    return;
                }

                currentAnlassData = resp;
                const def = resp.definition;

                // Header aktualisieren
                $('#anlassPanelTitle').html('<i class="bi bi-crosshair me-2"></i>' + def.bezeichnung);
                let metaHtml = '<span><i class="bi bi-trophy me-1"></i>Max: ' + def.maxpunkte + ' Punkte</span>';
                if (def.streicher) metaHtml += '<span class="ms-3"><i class="bi bi-dash-circle me-1"></i>Streicher</span>';
                $('#anlassPanelMeta').html(metaHtml);

                // Sektionsmeisterschaft: Grüner Header
                if (def.isSektionsmeisterschaft) {
                    $('#anlassPanelHeader').addClass('sektionsmeisterschaft');
                } else {
                    $('#anlassPanelHeader').removeClass('sektionsmeisterschaft');
                }

                // Mitglieder-Zeilen generieren
                let html = '';
                resp.members.forEach(function(m, i) {
                    const hasValue = def.isSektionsmeisterschaft
                        ? (m.punkte_runde1 !== null || m.punkte_runde2 !== null)
                        : (m.punkte !== null);
                    const rowClass = hasValue ? 'has-value' : '';

                    html += '<div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom anlass-member-row ' + rowClass + '" data-name="' + m.name.toLowerCase() + '">';
                    html += '<span style="width:30px; text-align:center; font-size:0.8rem; color:#94a3b8; font-weight:600;">' + (i + 1) + '</span>';
                    html += '<span class="flex-fill" style="font-weight:500; color:#1e293b; font-size:0.95rem;">' + m.name;
                    if (m.status === 'entwurf') {
                        html += ' <span title="Vom Mitglied gemeldet – noch nicht bestätigt" style="display:inline-block; margin-left:4px; font-size:0.66rem; font-weight:700; color:#92700c; background:#fff3cd; border:1px solid #ffe69c; border-radius:999px; padding:1px 8px; vertical-align:middle; white-space:nowrap;"><i class="bi bi-person-check"></i> gemeldet</span>';
                    }
                    html += '</span>';

                    if (def.isSektionsmeisterschaft) {
                        html += '<div class="d-flex align-items-center gap-1">';
                        html += '<span style="font-size:0.7rem; font-weight:700; color:#94a3b8;">R1:</span>';
                        html += '<input type="text" class="form-control form-control-sm anlass-input" data-mid="' + m.mitgliedID + '" data-field="punkte_runde1" value="' + (m.punkte_runde1 || '') + '" inputmode="numeric" style="width:65px; text-align:center; font-weight:600; border-radius:8px;">';
                        html += '<span style="font-size:0.7rem; font-weight:700; color:#94a3b8; margin-left:4px;">R2:</span>';
                        html += '<input type="text" class="form-control form-control-sm anlass-input" data-mid="' + m.mitgliedID + '" data-field="punkte_runde2" value="' + (m.punkte_runde2 || '') + '" inputmode="numeric" style="width:65px; text-align:center; font-weight:600; border-radius:8px;">';
                        html += '</div>';
                    } else if (def.isReadonly) {
                        html += '<span style="font-weight:700; color:#059669; min-width:60px; text-align:center;">' + (m.punkte || '\u2013') + '</span>';
                    } else {
                        const draftStyle = (m.status === 'entwurf') ? ' border-color:#ffc107; background:#fffbea; box-shadow:inset 0 0 0 1px #ffc107;' : '';
                        html += '<input type="text" class="form-control form-control-sm anlass-input" data-mid="' + m.mitgliedID + '" data-field="punkte" value="' + (m.punkte || '') + '" inputmode="numeric" placeholder="\u2013" style="width:80px; text-align:center; font-weight:600; font-size:1rem; border-radius:8px;' + draftStyle + '">';
                    }

                    html += '</div>';
                });

                $body.html(html);
                updatePanelCounter();

                // Readonly: Save-Button ausblenden
                if (def.isReadonly) {
                    $('#btnAnlassSave').hide();
                } else {
                    $('#btnAnlassSave').show();
                }

                // Focus auf erstes leeres Input
                setTimeout(function() {
                    const firstEmpty = $body.find('.anlass-input').filter(function() { return !this.value; }).first();
                    if (firstEmpty.length) firstEmpty.focus();
                    else $body.find('.anlass-input').first().focus();
                }, 300);
            })
            .fail(function() {
                $body.html('<div class="text-center py-4 text-danger">Fehler beim Laden</div>');
            });
    }

    // ---- Panel schliessen ----
    function closeAnlassPanel() {
        $('#anlassPanel').removeClass('open');
        $('#anlassPanelOverlay').removeClass('show');
        $('.anlass-overview-card').removeClass('selected');
        currentAnlassData = null;
    }

    $('#anlassPanelClose, #anlassPanelCancelBtn, #anlassPanelOverlay').on('click', function() { closeAnlassPanel(); });

    // Escape schliesst Panel
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#anlassPanel').hasClass('open')) {
            closeAnlassPanel();
            e.stopImmediatePropagation();
        }
    });

    // ---- Suche im Panel ----
    $('#anlassPanelSearch').on('input', function() {
        const q = this.value.toLowerCase();
        $('#anlassPanelBody .anlass-member-row').each(function() {
            const name = $(this).data('name') || '';
            $(this).toggle(name.includes(q));
        });
    });

    // ---- Counter aktualisieren ----
    function updatePanelCounter() {
        const $inputs = $('#anlassPanelBody .anlass-input');
        const total = currentAnlassData ? currentAnlassData.totalMembers : 0;
        let filled = 0;

        const seen = {};
        $inputs.each(function() {
            const mid = $(this).data('mid');
            if (this.value.trim() !== '' && !seen[mid]) {
                seen[mid] = true;
                filled++;
            }
        });

        const pct = total > 0 ? Math.round(filled / total * 100) : 0;
        $('#anlassPanelCounter').html('<strong style="color:#6366f1;">' + filled + '</strong>/' + total + ' erfasst');
        $('#anlassPanelProgressBar').css('width', pct + '%');
        $('#anlassPanelProgressText').text(filled + '/' + total);

        // Zeilen-Highlighting aktualisieren
        $('#anlassPanelBody .anlass-member-row').each(function() {
            const rowInputs = $(this).find('.anlass-input');
            const hasVal = rowInputs.toArray().some(inp => inp.value.trim() !== '');
            $(this).toggleClass('has-value', hasVal);
        });
    }

    // Input-Events im Panel
    $(document).on('input', '.anlass-input', function() {
        updatePanelCounter();
        $(this).toggleClass('border-success', this.value.trim() !== '');
    });

    // Enter-Navigation: zum nächsten Input
    $(document).on('keydown', '.anlass-input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const allInputs = $('#anlassPanelBody .anlass-input:visible').toArray();
            const idx = allInputs.indexOf(this);
            if (idx >= 0 && allInputs[idx + 1]) {
                allInputs[idx + 1].focus();
                allInputs[idx + 1].select();
            }
        }
    });

    // Focus: Select all
    $(document).on('focus', '.anlass-input', function() { this.select(); });

    // ---- Speichern ----
    $('#btnAnlassSave').on('click', function() {
        if (!currentAnlassData) return;
        const def = currentAnlassData.definition;
        const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Speichere...');

        // Daten sammeln
        const members = [];
        const seenMids = {};

        $('#anlassPanelBody .anlass-input').each(function() {
            const mid = $(this).data('mid');
            const field = $(this).data('field');
            const val = this.value.trim();

            if (!seenMids[mid]) {
                seenMids[mid] = { mitgliedID: mid };
                members.push(seenMids[mid]);
            }
            seenMids[mid][field] = val;
        });

        $.post('jmresultate/save_anlass.php', {
            jmdefinitionID: def.id,
            members: JSON.stringify(members),
            isSektionsmeisterschaft: def.isSektionsmeisterschaft ? '1' : '',
            csrf_token: $('input[name="csrf_token"]').val()
        })
        .done(function(resp) {
            if (resp.success) {
                msvToast('Resultate für \u00ab' + def.bezeichnung + '\u00bb gespeichert!', 'success');
                closeAnlassPanel();
                loadJMResultate($yearDD.val());
                loadAnlaesse($yearDD.val());
                if (window.loadRanglisten) window.loadRanglisten($yearDD.val());
            } else {
                msvToast('Fehler: ' + resp.message, 'error');
            }
        })
        .fail(function() { msvToast('Speichern fehlgeschlagen', 'error'); })
        .always(function() { $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Speichern'); });
    });

    // ---- Anlass-Karten Grid ----
    function buildAnlassCards(anlaesse) {
        const $grid = $('#anlassCardsGrid');
        if (!anlaesse || anlaesse.length === 0) { $grid.hide(); return; }

        // Auto-Anlässe (readonly) ausfiltern
        const editableAnlaesse = anlaesse.filter(function(a) { return !a.isReadonly; });
        if (editableAnlaesse.length === 0) { $grid.hide(); return; }

        let html = '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(170px, 1fr)); gap:0.5rem;">';
        editableAnlaesse.forEach(function(a) {
            const pct = a.totalMembers > 0 ? Math.round(a.filledCount / a.totalMembers * 100) : 0;
            const isComplete = pct === 100;
            const borderColor = isComplete ? '#22c55e' : '#e2e8f0';
            const progressColor = isComplete ? '#22c55e' : '#6366f1';

            html += '<div class="anlass-overview-card" data-id="' + a.id + '" style="background:#fff; border:1px solid ' + borderColor + '; border-radius:8px; padding:0.5rem 0.65rem; cursor:pointer; transition:all 0.2s;">';
            html += '<div style="font-weight:600; font-size:0.8rem; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + a.bezeichnung + '</div>';
            html += '<div style="display:flex; align-items:center; gap:0.4rem; margin-top:0.25rem;">';
            html += '<div style="flex:1; height:3px; border-radius:2px; background:#e2e8f0; overflow:hidden;">';
            html += '<div style="height:100%; width:' + pct + '%; border-radius:2px; background:' + progressColor + ';"></div>';
            html += '</div>';
            html += '<span style="font-size:0.7rem; color:#64748b; white-space:nowrap;">' + a.filledCount + '/' + a.totalMembers + '</span>';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';

        $grid.html(html).show();

        // Klick auf Karte -> Panel öffnen
        $grid.find('.anlass-overview-card').on('click', function() {
            openAnlassPanel($(this).data('id'));
        });
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
                scrollContainer.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
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
    const csrfToken = $('input[name="csrf_token"]').val();

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
        }, 'json').fail(function() {
            msvToast('Serverfehler', 'error');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // Initial + bei Jahreswechsel prüfen
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

    function csrf() { return $('#jmresultateForm input[name="csrf_token"]').val(); }
    function selectedYear() { return $('#yearSelect').val(); }
    function escapeHtml(s) { return $('<div>').text(s == null ? '' : s).html(); }

    const dropzoneHtml = '<i class="bi bi-cloud-arrow-up" style="font-size:2.5rem; color:#6c757d;"></i>' +
        '<h6 class="mt-2 mb-1">PDF hier ablegen oder klicken</h6>' +
        '<p class="text-muted small mb-0">Einzelrangliste eines Anlasses (z.B. Vereinsstich). Vereinsmitglieder werden automatisch erkannt.</p>';

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
                if (resp.success && resp.anlaesse) {
                    resp.anlaesse.filter(a => !a.isReadonly).forEach(function (a) {
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
                renderPreview(resp.stats);
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
            const dupNote = isDup ? '<span class="badge bg-warning text-dark ms-1">bereits erfasst</span>' : '';
            const top10 = r.is_top10 ? ' <i class="bi bi-trophy-fill text-warning" title="Top 10 – auch Einzelrangierung"></i>' : '';
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

    $('#pdfImportSelectAll').on('change', function () {
        $('#pdfImportPreviewTable tbody .row-check').prop('checked', this.checked);
        updateCommitCount();
    });
    $('#pdfImportPreviewTable').on('change', '.row-check', updateCommitCount);

    // ---- Import ----
    $('#pdfImportCommitBtn').on('click', function () {
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
})();
</script>

<?php
include 'footer.inc.php';
?>
