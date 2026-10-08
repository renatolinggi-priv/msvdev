<?php
// endschloesen.php – Endschiessen: Stiche lösen (Mitglieder, Gäste, Jungschützen)
// Backend: endschloesen/endschloesen_api.php (Preise werden dort verbindlich berechnet,
// das JS hier spiegelt die Regeln nur für die Live-Anzeige – siehe preislogik.inc.php).
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in endschloesen.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

$page_specific_css = "
/* =========================================
   Endschiessen lösen – Formular links, Übersicht rechts (ab 1400 px nebeneinander)
   ========================================= */
.erfassung-layout { display: flex; flex-direction: column; gap: 14px; }
.erfassung-form-col, .erfassung-table-col { min-width: 0; }
@media (min-width: 1400px) {
    .erfassung-layout { flex-direction: row; align-items: flex-start; }
    .erfassung-form-col { flex: 0 0 clamp(520px, 39%, 600px); }
    .erfassung-table-col { flex: 1 1 auto; min-width: 0; }
}
.es-karte { padding: 0 var(--ui-pad) var(--ui-pad); }

/* Abschnitte im Formular: durch Linien getrennt, keine Karten in der Karte */
#stichForm .shot-section { margin: 0; padding: 14px 0; background: transparent; border: 0; border-top: 1px solid var(--ui-linie); border-radius: 0; }
#stichForm .shot-section.shot-first { border-top: 0; }
#stichForm .shot-section-head { margin-bottom: 10px; flex-wrap: wrap; }
#stichForm .shot-hint { font-size: .8rem; line-height: 1.5; color: var(--ui-text-2); }
#stichForm .panel-label { font-size: .8rem; font-weight: 500; color: var(--ui-text-2); }
#stichForm .form-control, #stichForm .form-select { min-height: 2.25rem; }
#stichForm .munition-toggle { width: 100%; padding: 0; border: 0; background: transparent; text-align: left; }
#stichForm .munition-toggle:focus-visible { outline: 2px solid var(--ui-akzent); outline-offset: 4px; border-radius: 4px; }
#stichForm .munition-toggle[aria-expanded='false'] { margin-bottom: 0; }
#munitionBadge { min-width: 0; font-size: .85rem; font-weight: 600; color: var(--ui-text); }

/* Waffenwahl: gleiche Breite wie die Mitgliedersuche */
#stichForm .waffe-field { margin-top: 10px; }
#stichForm .waffe-field .panel-label { display: block; margin-bottom: 4px; }
#waffeSelect { width: 100%; font-size: .875rem; }
#waffeHint { margin-top: 4px; }
#waffeHint:empty { display: none; }

/* Umschalter (Teilnehmer, Zahlung) als Segment-Schalter wie .ui-filter */
#stichForm .typ-switch { display: inline-flex; gap: 2px; padding: 2px; background: #f1f4f8; border-radius: 8px; }
#stichForm .typ-switch > .btn { margin: 0 !important; padding: 5px 12px; border: 0 !important; border-radius: 6px !important; background: transparent; color: var(--ui-text); font-size: .82rem; font-weight: 600; box-shadow: none; }
#stichForm .typ-switch > .btn:hover { background: rgba(255, 255, 255, .65); color: var(--ui-text); }
#stichForm .typ-switch > .btn-check:checked + .btn { background: #fff; color: var(--ui-text); box-shadow: 0 1px 2px rgba(16, 24, 40, .12); }
#stichForm .typ-switch > .btn-check:focus-visible + .btn { outline: 2px solid var(--ui-akzent); outline-offset: 1px; }
#stichForm .typ-switch .btn i { font-size: .85em; color: var(--ui-text-2); }

/* Stich-Kacheln: gewählt = Akzentfarbe */
.stich-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); grid-auto-rows: 1fr; gap: 8px; }
@media (max-width: 575.98px) { .stich-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.stich-tile { position: relative; container-type: inline-size; display: flex; flex-direction: column; background: var(--ui-flaeche); border: 1px solid var(--ui-rand); border-radius: var(--ui-rad); user-select: none; transition: border-color .15s, background-color .15s; }
.stich-tile:hover { border-color: var(--ui-rand-stark); }
.stich-tile:focus-within { outline: 2px solid var(--ui-akzent); outline-offset: 2px; }
.stich-tile-main { position: relative; display: flex; flex: 1; flex-direction: column; gap: 6px; margin: 0; padding: 10px 10px 8px; cursor: pointer; }
.stich-tile .form-check-input { position: absolute; left: 10px; top: 12px; margin: 0; cursor: pointer; }
.stich-tile-head { display: flex; min-width: 0; padding-left: 1.5rem; }
.stich-tile:has(.stich-tile-partner) .stich-tile-head { padding-right: 4rem; }
.stich-tile-name { font-size: .875rem; font-weight: 600; line-height: 1.35; color: var(--ui-text); overflow-wrap: anywhere; }
.stich-tile-meta { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 2px 8px; margin-top: auto; font-size: .78rem; color: var(--ui-text-2); }
.stich-tile-meta .stich-price { margin-left: auto; white-space: nowrap; font-weight: 600; color: var(--ui-text); font-variant-numeric: tabular-nums; }
.stich-tile.selected { background: var(--ui-gewaehlt); border-color: var(--ui-akzent); box-shadow: 0 0 0 1px var(--ui-akzent); }
.stich-tile.selected .stich-tile-meta .stich-price { color: var(--ui-akzent-dunkel); }
.stich-tile .form-check-input:checked { background-color: var(--ui-akzent-dunkel); border-color: var(--ui-akzent-dunkel); }
/* Gast: optionale Stiche (nicht im Kombi-Preis) gestrichelt, mit Symbol */
.stich-tile-optional { border-style: dashed; background: var(--ui-flaeche-2); }
.stich-tile-optional.selected { border-style: solid; }
.stich-tile-badge { position: absolute; top: 8px; right: 9px; font-size: .85rem; line-height: 1; color: var(--ui-text-3); pointer-events: auto; }
.stich-tile-optional .stich-tile-head { padding-right: 1.5rem; }
.stich-tile.selected .stich-tile-badge { color: var(--ui-akzent-dunkel); }
.stich-tile-partner { position: absolute; top: 7px; right: 7px; display: flex; align-items: center; gap: 5px; margin: 0; padding: 4px; font-size: .75rem; line-height: 1.5; color: var(--ui-text-2); cursor: pointer; }
.stich-tile-partner .partner-icon { display: none; }
.stich-tile.selected .stich-tile-partner:has(:checked) { color: var(--ui-akzent-dunkel); font-weight: 600; }
.stich-tile-partner .form-check-input { position: static; flex-shrink: 0; width: 1.1em; height: 1.1em; margin: 0; }
@container (max-width: 155px) {
    .stich-tile:has(.stich-tile-partner) .stich-tile-head { padding-right: 2.5rem; }
    .stich-tile-partner .partner-text { display: none; }
    .stich-tile-partner .partner-icon { display: inline; }
}

/* Zusatzmunition */
.muni-row { display: flex; align-items: center; gap: 12px; padding: 8px 0; }
.muni-row + .muni-row { border-top: 1px solid var(--ui-linie-zart); }
.muni-row .form-check { flex: 1 1 auto; margin: 0; }
.muni-row .form-check-label { font-size: .85rem; }
.muni-row .muni-price { min-width: 5.5rem; text-align: right; font-size: .78rem; font-weight: 600; color: var(--ui-text-2); font-variant-numeric: tabular-nums; }
.muni-row .muni-input { width: 5.5rem; text-align: center; }
.muni-row .muni-label { flex: 1 1 auto; font-size: .82rem; }

/* Total-Leiste */
.total-actions-row { display: flex; flex-direction: column; gap: 10px; margin-top: 4px; padding: 12px 14px; background: var(--ui-flaeche-2); border: 1px solid var(--ui-rand); border-radius: var(--ui-rad-l); }
.total-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 16px; }
.total-kpi { display: flex; flex-direction: column; gap: 3px; line-height: 1.2; }
.total-kpi small { font-size: .72rem; font-weight: 600; color: var(--ui-text-2); }
.total-kpi strong { font-size: 1rem; color: var(--ui-text); font-variant-numeric: tabular-nums; }
.total-price-block { margin-left: auto; text-align: right; }
.total-amount { font-size: 1.45rem; font-weight: 700; line-height: 1.2; white-space: nowrap; color: var(--ui-text); font-variant-numeric: tabular-nums; }
.action-buttons { display: flex; flex-wrap: wrap; gap: 8px; }
#btnSave { margin-left: auto; min-width: 8rem; font-weight: 600; }

/* Übersicht (Tabellen-Card) */
.es-tabelle { overflow: hidden; }
.es-anzahl { margin-left: 6px; padding: 1px 8px; border-radius: 6px; background: #f4f6f9; font-size: .78rem; font-weight: 600; color: var(--ui-text-2); font-variant-numeric: tabular-nums; }
.es-tabelle .table-responsive { max-height: calc(100vh - 240px) !important; min-height: 0 !important; overflow: auto !important; }
#erfassteTabelle { margin: 0; }
#erfassteTabelle th { padding: 6px 5px; vertical-align: bottom; white-space: nowrap; font-size: .72rem; }
#erfassteTabelle .stich-header { writing-mode: vertical-rl; transform: rotate(180deg); height: 92px; min-width: 24px; max-width: 28px; padding: 5px 2px !important; text-align: left; font-weight: 600; letter-spacing: .02em; text-transform: none; }
#erfassteTabelle tbody td { padding: 8px 6px; vertical-align: middle; font-size: .85rem; border-bottom: 1px solid var(--ui-linie-zart); }
#erfassteTabelle th, #erfassteTabelle td { border-right: 0; }
#erfassteTabelle thead th:first-child, #erfassteTabelle tbody td:first-child { padding-left: 20px; text-align: left; }
#erfassteTabelle thead th:last-child, #erfassteTabelle tbody td:last-child { padding-right: 16px; }
#erfassteTabelle td.check-cell { padding-left: 2px; padding-right: 2px; text-align: center; font-size: .95rem; color: var(--ui-ok-fg); }
#erfassteTabelle td.check-cell .partner-mark { font-size: .6rem; vertical-align: super; color: var(--ui-akzent-dunkel); }
#erfassteTabelle td.check-cell .empty { color: #d5dbe3; }
#erfassteTabelle td.waffe-cell { white-space: nowrap; font-size: .75rem; color: var(--ui-text-2); }
#erfassteTabelle td.name-cell { white-space: nowrap; font-weight: 600; }
#erfassteTabelle td.name-cell .typ-badge { display: inline-block; margin-left: 6px; padding: 1px 6px; border-radius: 6px; background: #f1f4f8; color: var(--ui-text-2); font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; vertical-align: middle; }
#erfassteTabelle td.name-cell .typ-badge.js { background: var(--ui-akzent-hell); color: var(--ui-akzent-dunkel); }
#erfassteTabelle td.muni-cell { white-space: nowrap; font-size: .75rem; color: var(--ui-text-2); }
#erfassteTabelle td.muni-cell .muni-tag { display: inline-block; margin-right: 4px; padding: 1px 6px; border-radius: 4px; background: #f1f4f8; }
#erfassteTabelle td.total-cell { text-align: right; white-space: nowrap; font-weight: 700; color: var(--ui-text); font-variant-numeric: tabular-nums; }
#erfassteTabelle .dropdown-toggle::after { display: none; }
#erfassteTabelle .dropdown-menu { font-size: .85rem; }

/* Definition (Slide-Panel): Stiche + Spezialpreise */
#adminPanel .def-table td, #adminPanel .def-table th { padding: 6px; font-size: .82rem; vertical-align: middle; }
#adminPanel .def-table code { font-size: .72rem; color: var(--ui-text-2); }
#adminPanel .preis-row { display: flex; align-items: center; gap: 12px; padding: 6px 0; }
#adminPanel .preis-row + .preis-row { border-top: 1px solid var(--ui-linie-zart); }
#adminPanel .preis-row .preis-label { flex: 1 1 auto; font-size: .82rem; }
#adminPanel .preis-row .preis-label small { display: block; font-size: .7rem; color: var(--ui-text-2); }
#adminPanel .preis-row .input-group { flex: 0 0 auto; width: 9.5rem; }
#adminPanel .def-edit { padding: 12px 14px; background: var(--ui-flaeche-2); border: 1px solid var(--ui-rand); border-radius: var(--ui-rad); }

/* Select2 an die Formularfelder angleichen */
#stichForm .select2-container--bootstrap-5 .select2-selection--single { min-height: 2.25rem; padding: .35rem .75rem; font-size: .875rem; }
.select2-container { z-index: 1065; }

@media (max-width: 767.98px) {
    .es-karte { padding: 0 14px 14px; }
    #stichForm .typ-switch { display: flex; width: 100%; }
    #stichForm .typ-switch > .btn { display: flex; flex: 1 1 0; align-items: center; justify-content: center; min-height: 2.75rem; padding: 6px; }
    #stichForm .typ-switch .btn i { display: none; }
    #stichForm .total-actions-row { position: sticky; bottom: .5rem; bottom: max(.5rem, env(safe-area-inset-bottom)); z-index: 10; box-shadow: 0 4px 18px rgba(26, 35, 50, .12); }
    .total-bar { gap: 12px; }
    .total-amount { font-size: 1.25rem; }
    .action-buttons .btn { min-height: 2.75rem; }
    #btnSave { min-width: 0; flex: 1; }
    .muni-row { flex-wrap: wrap; gap: 8px; }
    .muni-row .muni-label { flex-basis: 100%; }
    .muni-row .muni-price { margin-left: auto; }
    #mobileCardsEndsch .mobile-card-header { padding: 1rem; }
    .es-tabelle .desktop-table-container { display: none !important; }
    .es-tabelle .mobile-cards-container { display: flex !important; }
    .es-tabelle .table-responsive { max-height: none !important; }
}
@media (min-width: 768px) { .es-tabelle .mobile-cards-container { display: none !important; } }
@media (max-width: 359.98px) {
    .action-buttons .btn { padding: .5rem; white-space: nowrap; }
    #btnStandblatt i, #btnSave > i { display: none; }
    #btnStandblatt span { margin-left: 0 !important; }
}
";

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
$csrf = csrf_token();
// Definitionen und Preise dürfen Admin und Vorstand pflegen (gleiche Regel wie adminApiGuard)
$kannDefinieren = in_array($_SESSION['user_role'] ?? '', ['admin', 'vorstand'], true);
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

<div class="container-fluid">
<div class="row">
<div class="col-12 ps-0">
  <div class="main-content-wrapper content-width-wide">
    <?php
    $page_title = 'Endschiessen Stiche lösen';
    $page_title_after = '<button type="button" class="btn-help" data-help="endschloesen.uebersicht" aria-label="Hilfe"></button>'
        . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
        . '<select id="yearSelect" class="form-select form-select-sm"></select>';
    $page_actions = '<div class="btn-group btn-group-sm" role="group" aria-label="Abrechnung">'
        . '<button type="button" id="btnGeneratePDF" class="btn btn-outline-info" data-tooltip="Abrechnung des Jahres als PDF"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Abrechnung</span></button>'
        . '<button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschiessen_abrechnung" data-druck-label="Endschiessen Abrechnung" aria-label="Abrechnung direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>'
        . '</div>'
        . ($kannDefinieren ? '<button type="button" class="btn btn-outline-secondary btn-sm" id="btnAdminSettings" data-tooltip="Stiche und Preise definieren"><i class="bi bi-gear me-1"></i>Definition</button>' : '');
    $page_extra = '<div class="ui-fortschritt" aria-live="polite"><span class="ui-zahl" id="progressText">–</span></div>'
        . '<div class="ui-chips" id="progressChips"></div>';
    $page_show_mobile = true;
    include 'partials/page_header.inc.php';
    ?>

    <div class="erfassung-layout">

      <!-- ================= Formular ================= -->
      <div class="erfassung-form-col">
      <section class="ui-karte es-karte" aria-label="Stiche lösen">
        <form id="stichForm" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" id="gastId" value="">

          <!-- Teilnehmer -->
          <div class="shot-section shot-first">
            <div class="shot-section-head">
              <span class="shot-section-title"><i class="bi bi-person"></i>Teilnehmer <button type="button" class="btn-help" data-help="endschloesen.teilnehmer" aria-label="Hilfe"></button></span>
            </div>
            <div class="shot-section-body">
              <div class="btn-group btn-group-sm typ-switch mb-3" role="group" aria-label="Teilnehmertyp">
                <input type="radio" class="btn-check" name="typ" id="typMitglied" value="mitglied" checked>
                <label class="btn btn-outline-primary" for="typMitglied"><i class="bi bi-person-badge me-1"></i>Mitglied</label>
                <input type="radio" class="btn-check" name="typ" id="typGast" value="gast">
                <label class="btn btn-outline-primary" for="typGast"><i class="bi bi-person-plus me-1"></i>Gast</label>
                <input type="radio" class="btn-check" name="typ" id="typJs" value="js">
                <label class="btn btn-outline-primary" for="typJs"><i class="bi bi-mortarboard me-1"></i>Jungschütze/-in</label>
              </div>

              <div id="mitgliedWrap">
                <select id="mitgliedSelect" class="form-select form-select-sm">
                  <option value="">– Mitglied suchen –</option>
                </select>
                <div class="shot-hint mt-1" id="mitgliedHint"></div>
              </div>

              <div id="gastWrap" hidden>
                <div class="row g-2">
                  <div class="col-12 col-md-6">
                    <label for="gastName" class="panel-label">Name</label>
                    <input type="text" class="form-control form-control-sm" id="gastName" placeholder="Nachname Vorname">
                    <div class="shot-hint mt-1" id="gastHint" hidden></div>
                  </div>
                  <div class="col-6 col-md-3" id="geburtsdatumWrap" hidden>
                    <label for="gastGeburtsdatum" class="panel-label">Geburtsdatum</label>
                    <input type="date" class="form-control form-control-sm" id="gastGeburtsdatum">
                  </div>
                </div>
              </div>

              <!-- Waffe: für alle Teilnehmertypen, Pflichtfeld (landet im Standblatt als ${waffe}) -->
              <div class="waffe-field">
                <label for="waffeSelect" class="panel-label">Waffe</label>
                <select id="waffeSelect" class="form-select form-select-sm" aria-describedby="waffeHint">
                  <option value="">– Waffe wählen –</option>
                </select>
                <div class="shot-hint" id="waffeHint"></div>
              </div>
            </div>
          </div>

          <!-- Stiche -->
          <div class="shot-section">
            <div class="shot-section-head">
              <span class="shot-section-title"><i class="bi bi-bullseye"></i>Stiche <span class="shot-hint" id="stichHint"></span> <button type="button" class="btn-help" data-help="endschloesen.stiche" aria-label="Hilfe"></button></span>
              <button type="button" id="btnSelectAll" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-check2-square me-1"></i>Alle
              </button>
            </div>
            <div class="shot-section-body">
              <div class="stich-grid" id="stichList"></div>
            </div>
          </div>

          <!-- Zahlung -->
          <div class="shot-section">
            <div class="shot-section-head">
              <span class="shot-section-title"><i class="bi bi-wallet2"></i>Zahlung</span>
            </div>
            <div class="shot-section-body">
              <div class="btn-group btn-group-sm typ-switch" role="group" aria-label="Zahlungsmethode">
                <input type="radio" class="btn-check" name="zahlungsmethode" id="zahlung_karte" value="karte" checked>
                <label class="btn btn-outline-primary" for="zahlung_karte"><i class="bi bi-credit-card-2-back me-1"></i>Karte</label>
                <input type="radio" class="btn-check" name="zahlungsmethode" id="zahlung_bar" value="bar">
                <label class="btn btn-outline-primary" for="zahlung_bar"><i class="bi bi-cash me-1"></i>Bar</label>
              </div>
            </div>
          </div>

          <!-- Zusatzmunition -->
          <div class="shot-section">
            <button type="button" class="shot-section-head munition-toggle" data-bs-toggle="collapse" data-bs-target="#munitionCollapse" aria-expanded="false" aria-controls="munitionCollapse">
              <span class="shot-section-title">
                <i class="bi bi-chevron-right" id="munitionChevron"></i>Zusätzliche Munition
                <span class="shot-hint" id="munitionProSchussText"></span>
              </span>
              <span class="shot-total" id="munitionBadge" hidden>0</span>
            </button>
            <div class="collapse" id="munitionCollapse">
              <div class="shot-section-body">
                <div class="muni-row">
                  <div class="form-check">
                    <input class="form-check-input zusatz-check" type="checkbox" id="zusatz_gp11_60" data-typ="GP11_60" data-anzahl="60">
                    <label class="form-check-label" for="zusatz_gp11_60"><strong>60 Schuss GP11</strong> <span class="text-muted">Standard-Paket</span></label>
                  </div>
                  <span class="muni-price" id="preis_gp11_60"></span>
                </div>
                <div class="muni-row">
                  <div class="form-check">
                    <input class="form-check-input zusatz-check" type="checkbox" id="zusatz_gp90_50" data-typ="GP90_50" data-anzahl="50">
                    <label class="form-check-label" for="zusatz_gp90_50"><strong>50 Schuss GP90</strong> <span class="text-muted">Standard-Paket</span></label>
                  </div>
                  <span class="muni-price" id="preis_gp90_50"></span>
                </div>
                <div class="muni-row">
                  <label class="muni-label" for="zusatz_gp11_custom"><strong>GP11</strong> <span class="text-muted">individuelle Anzahl</span></label>
                  <input type="number" class="form-control form-control-sm muni-input zusatz-custom" id="zusatz_gp11_custom" data-typ="GP11_CUSTOM" min="0" max="500" step="1" placeholder="0">
                  <span class="muni-price" id="preis_gp11_custom"></span>
                </div>
                <div class="muni-row">
                  <label class="muni-label" for="zusatz_gp90_custom"><strong>GP90</strong> <span class="text-muted">individuelle Anzahl</span></label>
                  <input type="number" class="form-control form-control-sm muni-input zusatz-custom" id="zusatz_gp90_custom" data-typ="GP90_CUSTOM" min="0" max="500" step="1" placeholder="0">
                  <span class="muni-price" id="preis_gp90_custom"></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Total + Aktionen -->
          <div class="total-actions-row">
            <div class="total-bar">
              <div class="total-kpi"><small>Stiche</small><strong id="totalCount">0</strong></div>
              <div class="total-kpi"><small>Schuss</small><strong id="totalShots">0</strong></div>
              <div class="total-kpi"><small>Zusatz</small><strong id="totalZusatzShots">0</strong></div>
              <div class="total-kpi total-price-block"><small>Gesamtbetrag</small><span class="total-amount" id="totalPrice" aria-live="polite" aria-atomic="true">CHF 0.00</span></div>
            </div>
            <div class="action-buttons">
              <div class="btn-group btn-group-sm" role="group" aria-label="Standblatt">
                <button type="button" id="btnStandblatt" class="btn btn-outline-info" disabled data-tooltip="Standblatt (Excel) für diesen Teilnehmer">
                  <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i><span class="ms-1">Standblatt (Excel)</span>
                </button>
                <button type="button" id="btnStandblattDruck" class="btn btn-outline-info" disabled hidden data-druck-eigen data-tooltip="QZ Tray nicht verbunden" aria-label="Standblatt direkt drucken">
                  <i class="bi bi-printer" aria-hidden="true"></i>
                </button>
              </div>
              <button type="button" id="btnReset" class="btn btn-outline-secondary btn-sm" data-tooltip="Formular zurücksetzen" aria-label="Formular zurücksetzen">
                <i class="bi bi-arrow-counterclockwise"></i>
              </button>
              <button type="submit" id="btnSave" class="btn btn-primary btn-sm">
                <span class="spinner-border spinner-border-sm me-1 d-none" id="saveSpinner"></span>
                <i class="bi bi-save me-1"></i>Speichern
              </button>
            </div>
          </div>
        </form>
      </section>
      </div>

      <!-- ================= Übersicht ================= -->
      <div class="erfassung-table-col">
        <section class="ui-karte es-tabelle" aria-label="Erfasste Teilnehmer">
          <div class="ui-tab-kopf">
            <span class="ui-tab-titel">Erfasste Teilnehmer <span class="es-anzahl" id="erfasstCount">0</span></span>
            <label class="ui-suche d-none d-md-flex">
              <i class="bi bi-search" aria-hidden="true"></i>
              <span class="visually-hidden">Teilnehmer suchen</span>
              <input type="search" id="esSuche" placeholder="Teilnehmer suchen" autocomplete="off">
            </label>
          </div>

          <div class="desktop-table-container">
            <div class="table-responsive">
              <table class="table table-hover mb-0" id="erfassteTabelle">
                <thead>
                  <tr id="erfassteTableHeader">
                    <th>Teilnehmer</th>
                    <th class="text-end">Total</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="erfassteTableBody">
                  <tr><td colspan="3" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2"></div>Lade Daten …</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="mobile-cards-container" id="mobileCardsEndsch">
            <div class="mobile-search">
              <div class="position-relative">
                <i class="bi bi-search search-icon"></i>
                <input type="text" class="form-control" placeholder="Suchen..." oninput="filterMobileEndsch(this)">
              </div>
            </div>
            <div class="mobile-cards-scroll"></div>
          </div>
        </section>
      </div>

    </div>
  </div>
</div>
</div>
</div>
<?php if ($kannDefinieren): ?>
<!-- Admin-Panel: Stich-Definitionen + Spezialpreise (Slide-Panel statt Modal) -->
<div class="panel-overlay" id="adminOverlay"></div>
<div class="hybrid-edit-panel" id="adminPanel" style="--panel-width: 620px;">
  <div class="panel-header">
    <h6 class="mb-0">Endschiessen Definition</h6>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="adminClose" data-tooltip="Schliessen (Esc)" aria-label="Schliessen (Esc)"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
  </div>
  <div class="panel-body">
    <div class="shot-section shot-first">
      <div class="shot-section-head">
        <span class="shot-section-title"><i class="bi bi-card-list"></i>Stiche <button type="button" class="btn-help" data-help="endschloesen.definition" aria-label="Hilfe"></button></span>
        <button type="button" class="btn btn-outline-success btn-sm" id="btnAddNewStich"><i class="bi bi-plus-lg me-1"></i>Neuer Stich</button>
      </div>
      <div class="shot-section-body">
        <div class="def-edit mb-3" id="defEdit" hidden>
          <div class="row g-2">
            <div class="col-4">
              <label class="panel-label" for="editStichCode">Code</label>
              <input type="text" class="form-control form-control-sm" id="editStichCode" maxlength="50" placeholder="z.B. END">
            </div>
            <div class="col-8">
              <label class="panel-label" for="editStichName">Name</label>
              <input type="text" class="form-control form-control-sm" id="editStichName" maxlength="100">
            </div>
            <div class="col-4">
              <label class="panel-label" for="editStichShots">Schuss</label>
              <input type="number" class="form-control form-control-sm" id="editStichShots" min="0" max="100">
            </div>
            <div class="col-4">
              <label class="panel-label" for="editStichPrice">Preis CHF</label>
              <input type="number" class="form-control form-control-sm" id="editStichPrice" min="0" max="1000" step="0.05">
            </div>
            <div class="col-4">
              <label class="panel-label" for="editStichSort">Sortierung</label>
              <input type="number" class="form-control form-control-sm" id="editStichSort" min="0" max="999">
            </div>
            <div class="col-12 d-flex align-items-center justify-content-between mt-2">
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="editStichActive" checked>
                <label class="form-check-label small" for="editStichActive">Aktiv</label>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCancelStich">Abbrechen</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnSaveStich">
                  <span class="spinner-border spinner-border-sm me-1 d-none" id="editStichSpinner"></span>Speichern
                </button>
              </div>
            </div>
          </div>
          <input type="hidden" id="editStichId">
        </div>
        <div class="table-responsive">
          <table class="table table-sm mb-0 def-table">
            <thead><tr><th>#</th><th>Stich</th><th class="text-center">Schuss</th><th class="text-end">Preis</th><th class="text-center">Aktiv</th><th></th></tr></thead>
            <tbody id="adminTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="shot-section">
      <div class="shot-section-head">
        <span class="shot-section-title"><i class="bi bi-currency-exchange"></i>Spezialpreise <button type="button" class="btn-help" data-help="endschloesen.spezialpreise" aria-label="Hilfe"></button></span>
      </div>
      <div class="shot-section-body" id="spezialpreiseContainer"></div>
    </div>
  </div>
  <div class="panel-footer">
    <div class="d-flex gap-2 w-100 justify-content-end">
      <button type="button" class="btn btn-primary btn-sm" id="btnSaveSpezialpreise">
        <span class="spinner-border spinner-border-sm me-1 d-none" id="saveSpezialpreiseSpinner"></span>
        <i class="bi bi-save me-1"></i>Spezialpreise speichern
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
(function () {
  'use strict';

  // =========================================================================
  //  Konfiguration / Zustand
  // =========================================================================
  const API = 'endschloesen/endschloesen_api.php';
  const CSRF = document.querySelector('#stichForm [name="csrf_token"]').value;

  // Regeln – identisch zu inc/endschloesen/preislogik.inc.php (Server ist verbindlich)
  const JS_PAKET_CODES   = ['END', 'SCHWINI_P1', 'ZABIG', 'PROBE'];
  const GAST_KOMBI_CODES = ['END', 'SCHWINI_P1', 'SCHWINI_P2'];
  const GAST_ERLAUBT     = ['END', 'SCHWINI_P1', 'SCHWINI_P2', 'SIEUNDER']; // Standard-Stiche (Button «Alle»); weitere optional zum Einzelpreis
  const GAST_GESPERRT    = ['PROBE'];
  const PREIS_DEFAULTS   = { munition_pro_schuss: 50, gast_kombi_2: 3500, gast_kombi_3: 4900, gast_alle: 0, gast_sie_und_er: 1000, partner_zabig: 1000, js_paket_preis: 0 };

  const state = {
    typ: 'mitglied',          // mitglied | gast | js
    stiche: [],               // aktive Definitionen
    alleStiche: [],           // inkl. inaktive (Admin)
    spezial: { ...PREIS_DEFAULTS },
    waffen: [],
    mitgliedWaffe: new Map(), // Mitglied-ID -> WaffenID aus den Stammdaten (Vorbelegung des Waffen-Dropdowns)
    uebersicht: [],           // Zeilen der Jahresübersicht
    erfassteMitglieder: new Set(), // Mitglieder-IDs, die im Jahr schon gelöst haben (im Select2 ausgeblendet)
    aktuelleEntity: null,     // { typ, id } für Zeilenmarkierung
    gastLookupTimer: null,
    canAdmin: <?= $kannDefinieren ? 'true' : 'false' ?>
  };

  const $id = (id) => document.getElementById(id);
  const fmtCHF = (cents) => 'CHF ' + ((Number(cents) || 0) / 100).toFixed(2);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const preis = (typ) => Number(state.spezial[typ] ?? PREIS_DEFAULTS[typ] ?? 0);

  async function api(action, opts = {}) {
    const url = `${API}?action=${encodeURIComponent(action)}` + (opts.query ? '&' + new URLSearchParams(opts.query).toString() : '');
    const init = opts.body
      ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(opts.body) }
      : {};
    const r = await fetch(url, init);
    let j = null;
    try { j = await r.json(); } catch (e) { /* kein JSON */ }
    if (r.status === 401) { msvToast('Sitzung abgelaufen – bitte neu anmelden', 'warning'); }
    if (!j) throw new Error('Ungültige Antwort (' + r.status + ')');
    return j;
  }

  // =========================================================================
  //  Preislogik (Anzeige)
  // =========================================================================
  function calcStichPreis(codes, typ, zabigPartner) {
    const preisVon = (code) => { const s = state.stiche.find(x => x.code === code); return s ? Number(s.price_cents) || 0 : 0; };
    if (typ === 'js') {
      return codes.some(c => JS_PAKET_CODES.includes(c)) ? preis('js_paket_preis') : 0;
    }
    if (typ === 'gast') {
      // Pauschale, wenn alle lösbaren Stiche gewählt sind (0 = Pauschale aus) – Server: preislogik.inc.php
      if (preis('gast_alle') > 0 && gastAlleGeloest(codes)) return preis('gast_alle');
      const kombi = codes.filter(c => GAST_KOMBI_CODES.includes(c));
      let p = 0;
      if (kombi.length === 1) p = preisVon(kombi[0]);
      else if (kombi.length === 2) p = preis('gast_kombi_2');
      else if (kombi.length >= 3) p = preis('gast_kombi_3');
      if (codes.includes('SIEUNDER')) p += preis('gast_sie_und_er');
      // optional dazugelöste Stiche zum Einzelpreis (Server: preislogik.inc.php)
      codes.forEach(c => { if (!GAST_ERLAUBT.includes(c) && !GAST_GESPERRT.includes(c)) p += preisVon(c); });
      return p;
    }
    return codes.reduce((sum, c) => {
      if (c === 'PROBE') return sum;
      if (c === 'ZABIG' && zabigPartner) return sum + preis('partner_zabig');
      return sum + preisVon(c);
    }, 0);
  }

  function erlaubteCodes(typ) {
    if (typ === 'js') return JS_PAKET_CODES;
    if (typ === 'gast') return state.stiche.map(s => s.code).filter(c => !GAST_GESPERRT.includes(c));
    return state.stiche.map(s => s.code).filter(c => c !== 'PROBE');
  }

  /** Gast: alle fuer ihn loesbaren Stiche gewaehlt? (Pauschalpreis gast_alle) */
  function gastAlleGeloest(codes) {
    const noetig = state.stiche.map(s => s.code).filter(c => !GAST_GESPERRT.includes(c));
    return noetig.length > 0 && noetig.every(c => codes.includes(c));
  }

  /** Gast: Stich ist optional (zaehlt nicht zum Kombi-Preis, wird zum Einzelpreis dazugeloest) */
  function istGastOptional(code) {
    return state.typ === 'gast' && !GAST_ERLAUBT.includes(code);
  }

  /** Checkboxen, die der Button «Alle» betrifft: bei Gaesten nur die Standard-Stiche */
  function standardChecks() {
    return [...document.querySelectorAll('.stich-check')].filter(cb => !istGastOptional(cb.dataset.code));
  }

  // =========================================================================
  //  Teilnehmer-Umschalter
  // =========================================================================
  function setTyp(typ, { keepSelection = false } = {}) {
    state.typ = typ;
    $id('typ' + typ.charAt(0).toUpperCase() + typ.slice(1)).checked = true;
    $id('mitgliedWrap').hidden = typ !== 'mitglied';
    $id('gastWrap').hidden = typ === 'mitglied';
    $id('geburtsdatumWrap').hidden = typ !== 'js';
    if (typ === 'mitglied') {
      $id('gastName').value = '';
      $id('gastGeburtsdatum').value = '';
      $id('gastId').value = '';
      $id('gastHint').hidden = true;
      // Waffe kommt beim Wählen des Mitglieds aus den Stammdaten (setWaffeAusAuswahl)
      if (!$id('mitgliedSelect').value) $id('waffeSelect').value = '';
    } else {
      $('#mitgliedSelect').val('').trigger('change.select2');
      if (!keepSelection) setDefaultWaffe();
    }
    updateWaffeHint();
    renderStiche(keepSelection);
    recalcTotals();
  }

  function setDefaultWaffe() {
    const stgw90 = state.waffen.find(w => /stgw\s*90/i.test(w.Bezeichnung || ''));
    $id('waffeSelect').value = stgw90 ? String(stgw90.ID) : '';
  }

  function waffeName(id) {
    const w = state.waffen.find(x => Number(x.ID) === Number(id));
    return w ? w.Bezeichnung : '?';
  }

  /** Waffe nach dem Laden einer Auswahl setzen: gespeicherte Waffe > Stammdaten (Mitglied) > Standard (Gast). */
  function setWaffeAusAuswahl(j) {
    const sel = $id('waffeSelect');
    if (j && j.success && j.waffen_id) { sel.value = String(j.waffen_id); }
    else if (state.typ === 'mitglied') {
      const stamm = state.mitgliedWaffe.get(Number($id('mitgliedSelect').value)) || null;
      sel.value = stamm ? String(stamm) : '';
    } else if (!sel.value) { setDefaultWaffe(); }
    if (sel.value !== '' && sel.selectedIndex < 0) sel.value = ''; // unbekannte ID
    updateWaffeHint();
  }

  function updateWaffeHint() {
    const sel = $id('waffeSelect'), hint = $id('waffeHint');
    if (state.typ !== 'mitglied') { hint.textContent = sel.value ? '' : 'Bitte Waffe wählen'; return; }
    const mid = Number($id('mitgliedSelect').value);
    if (!mid) { hint.textContent = ''; return; }
    const stamm = state.mitgliedWaffe.get(mid) || null;
    if (!sel.value) hint.textContent = stamm ? 'Bitte Waffe wählen' : 'Keine Waffe in den Stammdaten hinterlegt – bitte wählen';
    else if (!stamm) hint.textContent = 'Nicht in den Stammdaten hinterlegt';
    else if (Number(sel.value) !== stamm) hint.textContent = 'Abweichend von den Stammdaten (' + waffeName(stamm) + ')';
    else hint.textContent = '';
  }

  $id('waffeSelect').addEventListener('change', () => { updateWaffeHint(); recalcTotals(); });

  document.querySelectorAll('input[name="typ"]').forEach(r => r.addEventListener('change', () => {
    setTyp(r.value);
    if (r.value === 'js') {
      // JS-Paket vorbelegen
      state.stiche.forEach(s => { const cb = $id('stich_' + s.id); if (cb) cb.checked = JS_PAKET_CODES.includes(s.code); });
      updateTiles(); recalcTotals();
      $id('gastName').focus();
    } else if (r.value === 'gast') {
      $id('gastName').focus();
    } else {
      $('#mitgliedSelect').select2('open');
    }
  }));

  // =========================================================================
  //  Stich-Kacheln
  // =========================================================================
  function renderStiche(keepSelection = false) {
    const list = $id('stichList');
    const vorher = keepSelection ? new Set([...document.querySelectorAll('.stich-check:checked')].map(cb => cb.value)) : new Set();
    const partnerVorher = keepSelection && $id('partner_zabig') ? $id('partner_zabig').checked : false;
    list.innerHTML = '';

    const erlaubt = erlaubteCodes(state.typ);
    let stiche = [...state.stiche].filter(s => erlaubt.includes(s.code)).sort((a, b) => (a.sort_order || 999) - (b.sort_order || 999));
    if (state.typ === 'js') stiche.sort((a, b) => (a.code === 'PROBE' ? -1 : b.code === 'PROBE' ? 1 : 0));
    // Gast: Standard-Stiche zuerst, optionale Stiche dahinter
    if (state.typ === 'gast') stiche.sort((a, b) => Number(istGastOptional(a.code)) - Number(istGastOptional(b.code)));

    $id('stichHint').textContent = state.typ === 'js' ? 'Paketpreis ' + (preis('js_paket_preis') > 0 ? fmtCHF(preis('js_paket_preis')) : 'gratis')
      : state.typ === 'gast' ? 'Kombi-Preise: 2 Stiche ' + fmtCHF(preis('gast_kombi_2')) + ', ab 3 ' + fmtCHF(preis('gast_kombi_3'))
          + ' · weitere Stiche optional zum Einzelpreis'
          + (preis('gast_alle') > 0 ? ' · alle Stiche ' + fmtCHF(preis('gast_alle')) : '')
      : '';

    stiche.forEach(s => {
      const shots = Number(s.shots) || 0;
      let preisText = fmtCHF(s.price_cents);
      if (state.typ === 'js') preisText = 'im Paket';
      if (state.typ === 'gast' && s.code === 'SIEUNDER') preisText = fmtCHF(preis('gast_sie_und_er'));
      if (s.code === 'PROBE' && state.typ !== 'js') preisText = 'gratis';
      const optional = istGastOptional(s.code);

      const partner = (s.code === 'ZABIG' && state.typ === 'mitglied') ? `
        <label class="stich-tile-partner" for="partner_zabig" data-tooltip="Zabig mit Partner: ${fmtCHF(preis('partner_zabig'))}">
          <input class="form-check-input partner-check" type="checkbox" id="partner_zabig" aria-label="Zabig mit Partner" data-stich-id="${s.id}" ${partnerVorher ? 'checked' : ''}><span class="partner-text">Partner</span><i class="bi bi-people partner-icon" aria-hidden="true"></i>
        </label>` : '';

      const tile = document.createElement('div');
      tile.className = 'stich-tile' + (optional ? ' stich-tile-optional' : '');
      tile.dataset.stichId = s.id;
      tile.innerHTML = `
        <label class="stich-tile-main" for="stich_${s.id}">
          <input class="form-check-input stich-check" type="checkbox" value="${s.id}" id="stich_${s.id}" data-code="${esc(s.code)}" data-shots="${shots}" ${vorher.has(String(s.id)) ? 'checked' : ''}>
          <span class="stich-tile-head"><span class="stich-tile-name">${esc(s.name)}</span>${optional ? '<i class="bi bi-plus-circle stich-tile-badge" data-tooltip="Optional: zählt nicht zum Kombi-Preis, wird zum Einzelpreis dazugelöst" aria-label="Optionaler Stich"></i>' : ''}</span>
          <span class="stich-tile-meta"><span>${shots} Schuss</span><span class="stich-price" id="price_${s.id}">${preisText}</span></span>
        </label>${partner}`;
      list.appendChild(tile);
    });
    updateTiles();
  }

  function updateTiles() {
    document.querySelectorAll('.stich-tile').forEach(t => {
      const cb = t.querySelector('.stich-check');
      t.classList.toggle('selected', !!(cb && cb.checked));
    });
    // «Alle» bezieht sich bei Gaesten nur auf die Standard-Stiche (optionale bleiben Handarbeit)
    const alle = standardChecks();
    const alleAn = alle.length > 0 && alle.every(cb => cb.checked);
    $id('btnSelectAll').innerHTML = alleAn ? '<i class="bi bi-square me-1"></i>Keine' : '<i class="bi bi-check2-square me-1"></i>Alle';
  }

  function gewaehlteCodes() {
    return [...document.querySelectorAll('.stich-check:checked')].map(cb => cb.dataset.code);
  }

  document.addEventListener('change', (e) => {
    if (e.target.classList.contains('stich-check')) {
      if (e.target.dataset.code === 'ZABIG' && !e.target.checked && $id('partner_zabig')) $id('partner_zabig').checked = false;
      updateTiles(); recalcTotals();
    }
    if (e.target.classList.contains('partner-check')) {
      const zabig = $id('stich_' + e.target.dataset.stichId);
      if (e.target.checked && zabig && !zabig.checked) zabig.checked = true;
      updateTiles(); recalcTotals();
    }
  });
  $id('btnSelectAll').addEventListener('click', () => {
    const alle = standardChecks();
    const alleAn = alle.length > 0 && alle.every(cb => cb.checked);
    alle.forEach(cb => { cb.checked = !alleAn; });
    // «Keine» leert auch die optionalen Stiche der Gaeste
    if (alleAn) document.querySelectorAll('.stich-check').forEach(cb => { cb.checked = false; });
    if (alleAn && $id('partner_zabig')) $id('partner_zabig').checked = false;
    updateTiles(); recalcTotals();
  });

  // =========================================================================
  //  Totals
  // =========================================================================
  function zusatzListe() {
    const out = [];
    document.querySelectorAll('.zusatz-check:checked').forEach(cb => out.push({ typ: cb.dataset.typ, anzahl: parseInt(cb.dataset.anzahl, 10) || 0 }));
    document.querySelectorAll('.zusatz-custom').forEach(inp => { const n = parseInt(inp.value, 10) || 0; if (n > 0) out.push({ typ: inp.dataset.typ, anzahl: n }); });
    return out;
  }

  // ===== Ungespeicherte Auswahl (Entscheid 08.10.2026: nie still verwerfen) =====
  // Stand der Auswahl nach dem Laden bzw. Zurücksetzen; weicht die aktuelle Auswahl davon ab, fragt die Seite
  // vor einem Wechsel nach. var statt let: recalcTotals() läuft schon beim Aufbau der Seite.
  var losenStand = null, losenJahr = null;
  function losenSchluessel() {
    return JSON.stringify([
      [...document.querySelectorAll('.stich-check:checked')].map(cb => cb.value).sort(),
      (document.querySelector('input[name="zahlungsmethode"]:checked') || {}).value || '',
      !!($id('partner_zabig') && $id('partner_zabig').checked),
      zusatzListe(), $id('waffeSelect').value
    ]);
  }
  function losenGeaendert() { return losenStand !== null && losenSchluessel() !== losenStand; }
  function losenAnzeigen() { msvPanelUngespeichert($id('btnSave').parentElement, losenGeaendert()); }
  function losenMerken() { losenStand = losenSchluessel(); losenAnzeigen(); }
  // Vor dem Wechsel zu einem anderen Eintrag. true = weitermachen (gespeichert oder verworfen)
  async function losenSchutz() {
    if (!losenGeaendert()) return true;
    const wer = state.typ === 'mitglied' ? $('#mitgliedSelect option:selected').text().trim() : $id('gastName').value.trim();
    const wahl = await msvUngespeichert({ wer });
    if (wahl === 'speichern') return await speichern({ danachOeffnen: false });
    return wahl === 'verwerfen';
  }

  function recalcTotals() {
    if (losenStand !== null) setTimeout(losenAnzeigen, 0); // Hinweis «Nicht gespeichert» nachführen
    const checked = [...document.querySelectorAll('.stich-check:checked')];
    const codes = checked.map(cb => cb.dataset.code);
    const shots = checked.reduce((s, cb) => s + (Number(cb.dataset.shots) || 0), 0);
    const zabigPartner = !!($id('partner_zabig') && $id('partner_zabig').checked);
    const stichPreis = calcStichPreis(codes, state.typ, zabigPartner);

    const proSchuss = preis('munition_pro_schuss');
    const zusatz = zusatzListe();
    const zusatzSchuss = zusatz.reduce((s, z) => s + z.anzahl, 0);
    const zusatzPreis = zusatzSchuss * proSchuss;

    $id('totalCount').textContent = String(checked.length);
    $id('totalShots').textContent = String(shots);
    $id('totalZusatzShots').textContent = String(zusatzSchuss);
    $id('totalPrice').textContent = fmtCHF(stichPreis + zusatzPreis);

    $id('preis_gp11_60').textContent = fmtCHF(60 * proSchuss);
    $id('preis_gp90_50').textContent = fmtCHF(50 * proSchuss);
    $id('preis_gp11_custom').textContent = fmtCHF((parseInt($id('zusatz_gp11_custom').value, 10) || 0) * proSchuss);
    $id('preis_gp90_custom').textContent = fmtCHF((parseInt($id('zusatz_gp90_custom').value, 10) || 0) * proSchuss);
    $id('munitionProSchussText').textContent = fmtCHF(proSchuss) + ' pro Schuss';
    $id('munitionBadge').hidden = zusatzSchuss === 0;
    $id('munitionBadge').textContent = zusatzSchuss + ' Schuss · ' + fmtCHF(zusatzPreis);

    const hatPerson = state.typ === 'mitglied' ? !!$id('mitgliedSelect').value : $id('gastName').value.trim() !== '';
    const hatWaffe = !!$id('waffeSelect').value;
    const standblattMoeglich = hatPerson && hatWaffe && checked.length > 0;
    $id('btnStandblatt').disabled = !standblattMoeglich;
    $id('btnStandblattDruck').disabled = !(standblattMoeglich && printReady());
  }

  document.querySelectorAll('.zusatz-check').forEach(cb => cb.addEventListener('change', recalcTotals));
  document.querySelectorAll('.zusatz-custom').forEach(inp => inp.addEventListener('input', recalcTotals));
  $id('munitionCollapse').addEventListener('shown.bs.collapse', () => { $id('munitionChevron').className = 'bi bi-chevron-down'; });
  $id('munitionCollapse').addEventListener('hidden.bs.collapse', () => { $id('munitionChevron').className = 'bi bi-chevron-right'; });

  function resetZusatz() {
    document.querySelectorAll('.zusatz-check').forEach(cb => { cb.checked = false; });
    document.querySelectorAll('.zusatz-custom').forEach(inp => { inp.value = ''; });
  }

  // =========================================================================
  //  Formular: Laden / Zurücksetzen / Speichern
  // =========================================================================
  function resetForm({ keepTyp = false } = {}) {
    $('#mitgliedSelect').val('').trigger('change.select2');
    $id('gastName').value = '';
    $id('gastGeburtsdatum').value = '';
    $id('gastId').value = '';
    $id('waffeSelect').value = '';
    $id('gastHint').hidden = true;
    $id('zahlung_karte').checked = true;
    resetZusatz();
    state.aktuelleEntity = null;
    markRow(null);
    setTyp(keepTyp ? state.typ : 'mitglied');
    losenMerken();
  }

  function applySelection(j) {
    document.querySelectorAll('.stich-check').forEach(cb => { cb.checked = false; });
    if ($id('partner_zabig')) $id('partner_zabig').checked = false;
    resetZusatz();
    if (j && j.success) {
      (j.data || []).forEach(sid => { const el = $id('stich_' + sid); if (el) el.checked = true; });
      $id(j.zahlungsmethode === 'bar' ? 'zahlung_bar' : 'zahlung_karte').checked = true;
      if (j.zabig_partner && $id('partner_zabig')) $id('partner_zabig').checked = true;
      (j.zusatz || []).forEach(z => {
        if (z.typ === 'GP11_60') $id('zusatz_gp11_60').checked = true;
        else if (z.typ === 'GP90_50') $id('zusatz_gp90_50').checked = true;
        else if (z.typ === 'GP11_CUSTOM') $id('zusatz_gp11_custom').value = z.anzahl;
        else if (z.typ === 'GP90_CUSTOM') $id('zusatz_gp90_custom').value = z.anzahl;
      });
      if (zusatzListe().length) new bootstrap.Collapse($id('munitionCollapse'), { toggle: false }).show();
    }
    setWaffeAusAuswahl(j);
    updateTiles(); recalcTotals();
    losenMerken();
  }

  async function loadMitgliedSelection() {
    const mid = $id('mitgliedSelect').value;
    if (!mid) { applySelection(null); return; }
    state.aktuelleEntity = { typ: 'mitglied', id: Number(mid) };
    markRow(state.aktuelleEntity);
    try {
      applySelection(await api('get_selection', { query: { mitglied_id: mid, jahr: $id('yearSelect').value } }));
    } catch (e) { applySelection(null); msvToast('Auswahl konnte nicht geladen werden', 'error'); }
  }

  async function loadGastSelection({ byId = null } = {}) {
    const name = $id('gastName').value.trim();
    const query = { jahr: $id('yearSelect').value };
    if (byId) query.gast_id = byId; else if (name.length >= 3) query.gast_name = name; else return;
    try {
      const j = await api('get_selection', { query });
      if (!j.gefunden) {
        $id('gastId').value = '';
        $id('gastHint').textContent = 'Neuer Gast – wird beim Speichern angelegt';
        $id('gastHint').hidden = false;
        state.aktuelleEntity = null; markRow(null);
        return;
      }
      $id('gastId').value = j.gast_id;
      $id('gastName').value = j.gast_name;
      if (j.typ !== state.typ) setTyp(j.typ, { keepSelection: true });
      $id('gastGeburtsdatum').value = j.geburtsdatum || '';
      $id('gastHint').textContent = 'Bereits erfasst – Änderungen überschreiben die Auswahl';
      $id('gastHint').hidden = false;
      state.aktuelleEntity = { typ: 'gast', id: Number(j.gast_id) };
      markRow(state.aktuelleEntity);
      applySelection(j);
    } catch (e) { /* still */ }
  }

  $('#mitgliedSelect').on('select2:selecting', async function(e) {
    if (!losenGeaendert()) return;
    e.preventDefault();
    const neu = e.params.args.data.id;
    $('#mitgliedSelect').select2('close');
    if (await losenSchutz()) $('#mitgliedSelect').val(neu).trigger('change');
  });
  $('#mitgliedSelect').on('change', () => { if (state.typ === 'mitglied') loadMitgliedSelection(); });
  $id('gastName').addEventListener('input', () => {
    $id('gastId').value = '';
    clearTimeout(state.gastLookupTimer);
    state.gastLookupTimer = setTimeout(() => loadGastSelection(), 400);
    recalcTotals();
  });
  $id('yearSelect').addEventListener('change', async () => {
    const neu = $id('yearSelect').value;
    if (losenJahr && neu !== losenJahr && losenGeaendert()) {
      $id('yearSelect').value = losenJahr;      // gespeichert wird unter dem bisherigen Jahr
      if (!(await losenSchutz())) return;      // «Zurück»
      $id('yearSelect').value = neu;
    }
    losenJahr = neu;
    if (state.typ === 'mitglied') loadMitgliedSelection(); else loadGastSelection({ byId: null });
    loadUebersicht();
  });
  $id('btnReset').addEventListener('click', async () => {
    if (losenGeaendert()) {
      const wahl = await msvUngespeichert({});
      if (wahl === 'zurueck') return;
      if (wahl === 'speichern') { await speichern(); return; }
    }
    resetForm();
  });
  window.addEventListener('beforeunload', (e) => { if (losenGeaendert()) { e.preventDefault(); e.returnValue = ''; } });

  $id('stichForm').addEventListener('submit', (ev) => { ev.preventDefault(); speichern(); });

  // Speichert die Auswahl; true bei Erfolg. danachOeffnen=false beim Wechsel (dann keine Mitgliederauswahl aufklappen)
  async function speichern({ danachOeffnen = true } = {}) {
    const typ = state.typ;
    const body = {
      typ,
      jahr: $id('yearSelect').value,
      stiche: [...document.querySelectorAll('.stich-check:checked')].map(cb => cb.value),
      zahlungsmethode: document.querySelector('input[name="zahlungsmethode"]:checked').value,
      zabig_partner: !!($id('partner_zabig') && $id('partner_zabig').checked),
      zusatz_schuesse: zusatzListe(),
      waffen_id: $id('waffeSelect').value || undefined
    };
    if (typ === 'mitglied') {
      body.mitglied_id = $id('mitgliedSelect').value;
      if (!body.mitglied_id) { msvToast('Bitte ein Mitglied wählen', 'warning'); $('#mitgliedSelect').select2('open'); return false; }
    } else {
      body.gast_name = $id('gastName').value.trim();
      body.gast_id = $id('gastId').value || undefined;
      body.gast_geburtsdatum = typ === 'js' ? $id('gastGeburtsdatum').value : '';
      if (!body.gast_name) { msvToast('Bitte den Namen eingeben', 'warning'); $id('gastName').focus(); return false; }
      if (typ === 'js' && !body.gast_geburtsdatum) { msvToast('Bitte das Geburtsdatum eingeben', 'warning'); $id('gastGeburtsdatum').focus(); return false; }
    }
    if (body.stiche.length && !body.waffen_id) { msvToast('Bitte die Waffe wählen', 'warning'); $id('waffeSelect').focus(); return false; }
    if (!body.stiche.length && !body.zusatz_schuesse.length) {
      const r = await msvConfirm('Es ist kein Stich gewählt. Bestehende Auswahl dieses Teilnehmers wird geleert.', 'Auswahl leeren', 'Ja, leeren');
      if (!r.isConfirmed) return false;
    }

    const btn = $id('btnSave'), spin = $id('saveSpinner');
    btn.disabled = true; spin.classList.remove('d-none');
    let ok = false;
    try {
      const j = await api('save_selection', { body });
      if (!j.success) { msvToast(j.message || 'Fehler beim Speichern', 'error'); return false; }
      ok = true;
      msvToast((j.message || 'Gespeichert') + ' – ' + fmtCHF((j.data && j.data.preis_cents) || 0) + ' Stiche', 'success');
      await loadUebersicht();
      resetForm({ keepTyp: true });
      if (danachOeffnen) { if (state.typ === 'mitglied') $('#mitgliedSelect').select2('open'); else $id('gastName').focus(); }
    } catch (e) {
      msvToast('Netzwerkfehler beim Speichern', 'error');
    } finally {
      btn.disabled = false; spin.classList.add('d-none');
    }
    return ok;
  }

  // =========================================================================
  //  Übersichtstabelle
  // =========================================================================
  function markRow(entity) {
    document.querySelectorAll('#erfassteTabelle tbody tr').forEach(tr => {
      tr.classList.toggle('selected', !!entity && tr.dataset.typ === entity.typ && Number(tr.dataset.entityId) === entity.id);
    });
  }

  async function loadUebersicht() {
    const jahr = $id('yearSelect').value;
    try {
      const j = await api('get_year_details', { query: { jahr } });
      state.uebersicht = j.success ? (j.data || []) : [];
    } catch (e) { state.uebersicht = []; msvToast('Übersicht konnte nicht geladen werden', 'error'); }
    state.erfassteMitglieder = new Set(state.uebersicht.filter(e => e.typ === 'mitglied').map(e => Number(e.entity_id)));
    updateMitgliedHint();
    renderUebersicht();
  }

  function updateMitgliedHint() {
    const total = $id('mitgliedSelect').options.length - 1;
    const offen = total - state.erfassteMitglieder.size;
    $id('mitgliedHint').textContent = total > 0 ? `${offen} von ${total} Mitgliedern haben ${$id('yearSelect').value} noch nichts gelöst` : '';
  }

  function waffeText(e) {
    if (e.waffe_bez) return e.waffe_kat ? `${e.waffe_bez} (${e.waffe_kat})` : e.waffe_bez;
    return '–';
  }

  function renderUebersicht() {
    const stiche = [...state.stiche].sort((a, b) => (a.sort_order || 999) - (b.sort_order || 999));
    const head = $id('erfassteTableHeader');
    head.innerHTML = '<th>Teilnehmer</th>'
      + stiche.map(s => `<th class="stich-header" data-tooltip="${esc(s.name)} · ${s.shots} Schuss · ${fmtCHF(s.price_cents)}">${esc(s.name)}</th>`).join('')
      + '<th>Waffe</th><th>Munition</th><th class="text-center" data-tooltip="Zahlung"><i class="bi bi-wallet2"></i></th><th class="text-end">Total</th><th></th>';

    const tbody = $id('erfassteTableBody');
    const rows = state.uebersicht;
    $id('erfasstCount').textContent = rows.length;
    kopfZahlen(rows);
    if (!rows.length) {
      tbody.innerHTML = `<tr><td colspan="${stiche.length + 6}" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Für ${esc($id('yearSelect').value)} ist noch niemand erfasst</td></tr>`;
      buildMobileEndschCards();
      return;
    }
    const order = { mitglied: 1, gast: 2, js: 3 };
    rows.sort((a, b) => (order[a.teilnehmer_typ] - order[b.teilnehmer_typ]) || (a.name || '').localeCompare(b.name || ''));

    tbody.innerHTML = rows.map(e => {
      const badge = e.teilnehmer_typ === 'js' ? '<span class="typ-badge js">JS</span>' : e.teilnehmer_typ === 'gast' ? '<span class="typ-badge">Gast</span>' : '';
      const cells = stiche.map(s => {
        const hat = (e.stiche || []).includes(Number(s.id));
        const partner = (e.partner_stiche || []).includes(Number(s.id));
        return `<td class="check-cell">${hat ? '<i class="bi bi-check-lg"></i>' + (partner ? '<span class="partner-mark" data-tooltip="Partner">P</span>' : '') : '<span class="empty">·</span>'}</td>`;
      }).join('');
      const muni = [];
      const stichM = [];
      if (e.stich_gp11 > 0) stichM.push('GP11 ' + e.stich_gp11);
      if (e.stich_gp90 > 0) stichM.push('GP90 ' + e.stich_gp90);
      if (stichM.length) muni.push(`<span class="muni-tag" data-tooltip="Schuss aus gelösten Stichen">${stichM.join(' · ')}</span>`);
      const zusM = [];
      if (e.zusatz_gp11 > 0) zusM.push('GP11 ' + e.zusatz_gp11);
      if (e.zusatz_gp90 > 0) zusM.push('GP90 ' + e.zusatz_gp90);
      if (zusM.length) muni.push(`<span class="muni-tag" data-tooltip="Zusätzliche Munition">+ ${zusM.join(' · ')}</span>`);
      const zahlung = e.zahlungsmethode === 'bar'
        ? '<i class="bi bi-cash text-success" data-tooltip="Bar"></i>'
        : '<i class="bi bi-credit-card-2-back text-primary" data-tooltip="Karte"></i>';
      const codes = (e.stiche || []).map(sid => { const s = stiche.find(x => Number(x.id) === Number(sid)); return s ? s.code : ''; }).filter(Boolean).join(',');
      return `
        <tr data-typ="${esc(e.typ)}" data-entity-id="${e.entity_id}">
          <td class="name-cell">${esc(e.name)}${badge}</td>
          ${cells}
          <td class="waffe-cell" data-tooltip="${esc(e.waffe_kat || '')}">${esc(e.waffe_bez || '–')}</td>
          <td class="muni-cell">${muni.join('') || '<span class="text-muted">–</span>'}</td>
          <td class="text-center">${zahlung}</td>
          <td class="total-cell">${fmtCHF(e.total_price)}</td>
          <td class="text-end">
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-tooltip="Aktionen" data-bs-boundary="viewport" data-bs-popper-config='{"strategy":"fixed"}'><i class="bi bi-three-dots"></i></button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item act-edit" href="#" data-typ="${esc(e.typ)}" data-entity-id="${e.entity_id}" data-name="${esc(e.name)}"><i class="bi bi-pencil me-2"></i>Bearbeiten</a></li>
                <li><a class="dropdown-item act-standblatt" href="#" data-typ="${esc(e.typ)}" data-entity-id="${e.entity_id}" data-name="${esc(e.name)}" data-stiche="${esc(codes)}" data-waffe-id="${e.waffe_id || ''}"><i class="bi bi-file-earmark-spreadsheet me-2" aria-hidden="true"></i>Standblatt (Excel)</a></li>
                ${druckSichtbar() ? `<li><a class="dropdown-item act-print${printReady() ? '' : ' disabled'}" href="#" data-typ="${esc(e.typ)}" data-entity-id="${e.entity_id}" data-name="${esc(e.name)}" data-stiche="${esc(codes)}" data-waffe-id="${e.waffe_id || ''}" data-tooltip="${printReady() ? 'Direktdruck über QZ Tray' : 'Kein Druckprofil «Endschiessen Standblatt» (Drucksteuerung)'}"><i class="bi bi-printer me-2"></i>Standblatt drucken</a></li>` : ''}
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item act-delete text-danger" href="#" data-typ="${esc(e.typ)}" data-entity-id="${e.entity_id}" data-name="${esc(e.name)}"><i class="bi bi-trash me-2"></i>Löschen</a></li>
              </ul>
            </div>
          </td>
        </tr>`;
    }).join('');
    markRow(state.aktuelleEntity);
    filterUebersicht();
    buildMobileEndschCards();
  }

  // Kopf-Card: Teilnehmer nach Typ und Summe des Jahres
  function kopfZahlen(rows) {
    const n = { mitglied: 0, gast: 0, js: 0 };
    let summe = 0;
    rows.forEach(e => { n[e.teilnehmer_typ] = (n[e.teilnehmer_typ] || 0) + 1; summe += Number(e.total_price) || 0; });
    $id('progressText').innerHTML = rows.length + ' <span>Teilnehmer · ' + esc(fmtCHF(summe)) + '</span>';
    const chip = (zahl, eins, mehr) => zahl ? `<span class="ui-chip"><b>${zahl}</b> ${zahl === 1 ? eins : mehr}</span>` : '';
    $id('progressChips').innerHTML = chip(n.mitglied, 'Mitglied', 'Mitglieder') + chip(n.gast, 'Gast', 'Gäste') + chip(n.js, 'Jungschütze/-in', 'Jungschützen');
  }

  // Suche in der Übersicht (nach Name)
  function filterUebersicht() {
    const q = ($id('esSuche').value || '').trim().toLowerCase();
    document.querySelectorAll('#erfassteTableBody tr[data-entity-id]').forEach(tr => {
      const name = tr.querySelector('.name-cell');
      tr.style.display = !q || (name && name.textContent.toLowerCase().includes(q)) ? '' : 'none';
    });
  }
  $id('esSuche').addEventListener('input', filterUebersicht);

  document.addEventListener('click', async (e) => {
    const edit = e.target.closest('.act-edit');
    const stand = e.target.closest('.act-standblatt');
    const prt = e.target.closest('.act-print');
    const del = e.target.closest('.act-delete');
    if (!edit && !stand && !prt && !del) return;
    e.preventDefault();
    const a = edit || stand || prt || del;
    const typ = a.dataset.typ, entityId = a.dataset.entityId, name = a.dataset.name;

    if (prt) {
      if (!printReady()) { msvToast('QZ Tray nicht verbunden oder kein Druckprofil «Endschiessen Standblatt»', 'warning'); return; }
      await printStandblatt({ typ, entityId, name, stiche: a.dataset.stiche || '', waffenId: a.dataset.waffeId || '' });
      return;
    }

    if (edit) {
      if (!(await losenSchutz())) return;
      if (typ === 'mitglied') {
        setTyp('mitglied');
        $('#mitgliedSelect').val(entityId).trigger('change.select2');
        await loadMitgliedSelection();
      } else {
        setTyp('gast');
        await loadGastSelection({ byId: entityId });
      }
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }
    if (stand) { await downloadStandblatt(stand, { typ, entityId, name, stiche: a.dataset.stiche || '', waffenId: a.dataset.waffeId || '' }); return; }
    if (del) {
      const r = await msvConfirmDelete(name);
      if (!r.isConfirmed) return;
      try {
        const j = await api('delete_selection', { body: { entity_id: entityId, typ, jahr: $id('yearSelect').value } });
        if (!j.success) { msvToast(j.message || 'Fehler beim Löschen', 'error'); return; }
        msvToast(`${name} entfernt`, 'success');
        if (state.aktuelleEntity && state.aktuelleEntity.typ === typ && state.aktuelleEntity.id === Number(entityId)) resetForm({ keepTyp: true });
        loadUebersicht();
      } catch (err) { msvToast('Netzwerkfehler beim Löschen', 'error'); }
    }
  });

  // =========================================================================
  //  Standblatt / Abrechnung
  // =========================================================================
  function standblattUrl({ typ, entityId, name, stiche, waffenId = '', format = '' }) {
    const jahr = $id('yearSelect').value;
    let url = `endschloesen/generate_standblatt.php?jahr=${encodeURIComponent(jahr)}&stiche=${encodeURIComponent(stiche)}`;
    url += typ === 'mitglied' ? `&mitglied_id=${encodeURIComponent(entityId)}` : `&gast_name=${encodeURIComponent(name)}`;
    if (waffenId) url += `&waffen_id=${encodeURIComponent(waffenId)}`;
    if (format) url += `&format=${encodeURIComponent(format)}`;
    return url;
  }

  // Standblatt (Excel) über den Ausgabe-Baustein; der Server liefert den Dateinamen mit (Content-Disposition)
  function downloadStandblatt(knopf, { typ, entityId, name, stiche, waffenId = '' }) {
    return msvAusgabe(knopf, {
      url: standblattUrl({ typ, entityId, name, stiche, waffenId }),
      titel: 'Standblatt ' + name,
      fehler: 'Das Standblatt konnte nicht erstellt werden. Bitte nochmals versuchen.'
    });
  }

  $id('btnStandblatt').addEventListener('click', async function () {
    const stiche = gewaehlteCodes().join(',');
    const waffenId = $id('waffeSelect').value;
    if (!waffenId) {
      msvToast('Bitte die Waffe wählen', 'warning'); $id('waffeSelect').focus();
      return;
    }
    await downloadStandblatt(this, aktuellerTeilnehmer({ stiche, waffenId }));
    recalcTotals();
  });

  /** Teilnehmer-Parameter (typ, entityId, name) aus dem Formular, ergänzt um weitere Felder. */
  function aktuellerTeilnehmer(extra = {}) {
    if (state.typ === 'mitglied') {
      const opt = $id('mitgliedSelect').selectedOptions[0];
      return { typ: 'mitglied', entityId: $id('mitgliedSelect').value, name: opt ? opt.textContent : 'Mitglied', ...extra };
    }
    return { typ: 'gast', entityId: $id('gastId').value, name: $id('gastName').value.trim(), ...extra };
  }

  // =========================================================================
  //  Direktdruck über QZ Tray – gemeinsamer Baustein js/msv-direktdruck.js (MsvDruck)
  //  Profile «endschiessen_standblatt» (A4 quer, XLSX → PDF serverseitig) und
  //  «endschiessen_abrechnung» (Abrechnungs-PDF) aus der Drucksteuerung.
  // =========================================================================
  const DRUCK_STANDBLATT = 'endschiessen_standblatt';
  const hatMsvDruck = () => typeof MsvDruck !== 'undefined';

  function printReady() {
    return hatMsvDruck() && MsvDruck.bereit(DRUCK_STANDBLATT);
  }

  // Ohne QZ-Verbindung keine Drucker (Hinweis in der Kopf-Card kommt von MsvDruck)
  function druckSichtbar() {
    return hatMsvDruck() && MsvDruck.sichtbar();
  }

  function updatePrintUI() {
    const btn = $id('btnStandblattDruck');
    btn.hidden = !druckSichtbar();
    if (hatMsvDruck()) {
      const grund = MsvDruck.grund(DRUCK_STANDBLATT, 'Endschiessen Standblatt');
      btn.dataset.tooltip = grund || ('Standblatt direkt drucken (' + MsvDruck.profilText(DRUCK_STANDBLATT) + ')');
    } else {
      btn.dataset.tooltip = 'QZ Tray nicht verfügbar';
    }
    recalcTotals();
    if (state.uebersicht.length) renderUebersicht();
  }

  function initPrint() {
    if (!hatMsvDruck()) { updatePrintUI(); return; }
    MsvDruck.onChange = updatePrintUI;
    // Abrechnung: Endpunkt liefert JSON {pdf_link} mit absolutem Pfad ins dat/-Verzeichnis
    MsvDruck.resolve('endschiessen_abrechnung', () => {
      const jahr = $id('yearSelect').value;
      return {
        url: `endschloesen/generate_pdf_endschloesen.php?action=generate_pdf&jahr=${encodeURIComponent(jahr)}`,
        jobName: `Endschiessen Abrechnung ${jahr}`,
      };
    });
    updatePrintUI();
  }

  async function printStandblatt({ typ, entityId, name, stiche, waffenId = '' }) {
    if (!printReady()) return false;
    const jahr = $id('yearSelect').value;
    return MsvDruck.print({
      docType: DRUCK_STANDBLATT,
      url: standblattUrl({ typ, entityId, name, stiche, waffenId, format: 'pdf' }),
      jobName: `Endschiessen Standblatt ${name} ${jahr}`,
      orientation: 'landscape', // Vorlage ist A4 quer
    });
  }

  $id('btnStandblattDruck').addEventListener('click', async function () {
    const btn = this, orig = btn.innerHTML;
    const waffenId = $id('waffeSelect').value;
    if (!waffenId) { msvToast('Bitte die Waffe wählen', 'warning'); $id('waffeSelect').focus(); return; }
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    await printStandblatt(aktuellerTeilnehmer({ stiche: gewaehlteCodes().join(','), waffenId }));
    btn.innerHTML = orig; recalcTotals();
  });

  // Abrechnung als PDF: herunterladen statt neuem Tab (der Popup-Blocker sperrt window.open nach await)
  $id('btnGeneratePDF').addEventListener('click', function () {
    const jahr = $id('yearSelect').value;
    msvAusgabe(this, {
      url: 'endschloesen/generate_pdf_endschloesen.php',
      data: { action: 'generate_pdf', jahr: jahr },
      titel: 'Endschiessen Abrechnung ' + jahr,
      name: 'Endschiessen_Abrechnung_' + jahr,
      fehler: 'Die Abrechnung konnte nicht erstellt werden. Bitte nochmals versuchen.'
    });
  });

  // =========================================================================
  //  Admin-Panel: Definitionen + Spezialpreise
  // =========================================================================
  const PREIS_CONFIG = [
    { typ: 'munition_pro_schuss', label: 'Munition pro Schuss', hint: 'für zusätzliche Munition', rp: true },
    { typ: 'gast_kombi_2', label: 'Gäste: 2 Stiche', hint: 'aus Endstich / Schwini' },
    { typ: 'gast_kombi_3', label: 'Gäste: ab 3 Stiche', hint: 'aus Endstich / Schwini' },
    { typ: 'gast_alle', label: 'Gäste: alle Stiche', hint: 'Pauschale, wenn ein Gast jeden Stich löst; 0 = aus (dann normal gerechnet)' },
    { typ: 'gast_sie_und_er', label: 'Gäste: Sie und Er', hint: 'zusätzlich zum Kombi-Preis' },
    { typ: 'partner_zabig', label: 'Zabig mit Partner', hint: 'ersetzt den Einzelpreis des Zabig (Mitglieder)' },
    { typ: 'js_paket_preis', label: 'Jungschützen-Paket', hint: 'Endstich + Schwini P1 + Zabig + Probe; 0 = gratis' }
  ];

  if (state.canAdmin) {
    const openAdmin = async () => {
      await Promise.all([loadAllStiche(), loadSpezialpreise()]);
      renderSpezialpreise();
      $id('defEdit').hidden = true;
      $id('adminPanel').classList.add('open');
      $id('adminOverlay').classList.add('show');
    };
    const closeAdmin = () => { $id('adminPanel').classList.remove('open'); $id('adminOverlay').classList.remove('show'); };
    $id('btnAdminSettings').addEventListener('click', openAdmin);
    $id('adminClose').addEventListener('click', closeAdmin);
    $id('adminOverlay').addEventListener('click', closeAdmin);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && $id('adminPanel').classList.contains('open')) closeAdmin(); });

    async function loadAllStiche() {
      try { const j = await api('get_stich_definitions'); if (j.success) { state.alleStiche = j.data || []; renderAdminTable(); } else msvToast(j.message || 'Stiche konnten nicht geladen werden', 'error'); } catch (e) { msvToast('Stiche konnten nicht geladen werden', 'error'); }
    }

    function renderAdminTable() {
      $id('adminTableBody').innerHTML = state.alleStiche.map(s => `
        <tr>
          <td class="text-muted">${s.sort_order}</td>
          <td><strong>${esc(s.name)}</strong><br><code>${esc(s.code)}</code></td>
          <td class="text-center">${s.shots}</td>
          <td class="text-end">${fmtCHF(s.price_cents)}</td>
          <td class="text-center">${Number(s.active) === 1 ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>'}</td>
          <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary act-edit-def" data-id="${s.id}" data-tooltip="Bearbeiten"><i class="bi bi-pencil"></i></button></td>
        </tr>`).join('');
    }

    function openEditStich(id) {
      const s = id ? state.alleStiche.find(x => Number(x.id) === Number(id)) : null;
      $id('editStichId').value = s ? s.id : '';
      $id('editStichCode').value = s ? s.code : '';
      $id('editStichCode').disabled = !!s;
      $id('editStichName').value = s ? s.name : '';
      $id('editStichShots').value = s ? s.shots : 10;
      $id('editStichPrice').value = s ? (s.price_cents / 100).toFixed(2) : '20.00';
      $id('editStichSort').value = s ? s.sort_order : 100;
      $id('editStichActive').checked = s ? Number(s.active) === 1 : true;
      $id('defEdit').hidden = false;
      (s ? $id('editStichName') : $id('editStichCode')).focus();
    }

    $id('adminTableBody').addEventListener('click', (e) => { const b = e.target.closest('.act-edit-def'); if (b) openEditStich(b.dataset.id); });
    $id('btnAddNewStich').addEventListener('click', () => openEditStich(null));
    $id('btnCancelStich').addEventListener('click', () => { $id('defEdit').hidden = true; });
    $id('btnSaveStich').addEventListener('click', async () => {
      const body = {
        id: $id('editStichId').value || 0,
        code: $id('editStichCode').value.trim().toUpperCase(),
        name: $id('editStichName').value.trim(),
        shots: parseInt($id('editStichShots').value, 10) || 0,
        price_cents: Math.round((parseFloat($id('editStichPrice').value) || 0) * 100),
        sort_order: parseInt($id('editStichSort').value, 10) || 100,
        active: $id('editStichActive').checked ? 1 : 0
      };
      if (!body.name || (!body.id && !body.code)) { msvToast('Code und Name sind erforderlich', 'warning'); return; }
      const spin = $id('editStichSpinner'); spin.classList.remove('d-none');
      try {
        const j = await api('update_stich_definition', { body });
        if (!j.success) { msvToast(j.message || 'Fehler', 'error'); return; }
        msvToast(j.message || 'Gespeichert', 'success');
        $id('defEdit').hidden = true;
        await Promise.all([loadAllStiche(), loadStiche()]);
        renderStiche(true); recalcTotals(); renderUebersicht();
      } catch (e) { msvToast('Netzwerkfehler', 'error'); }
      finally { spin.classList.add('d-none'); }
    });

    function renderSpezialpreise() {
      $id('spezialpreiseContainer').innerHTML = PREIS_CONFIG.map(c => {
        const cents = preis(c.typ);
        const value = c.rp ? cents : (cents / 100).toFixed(2);
        return `
          <div class="preis-row">
            <div class="preis-label">${esc(c.label)}<small>${esc(c.hint)}</small></div>
            <div class="input-group input-group-sm">
              <span class="input-group-text">${c.rp ? 'Rp.' : 'CHF'}</span>
              <input type="number" class="form-control text-end spezialpreis-input" data-typ="${c.typ}" data-rp="${c.rp ? 1 : 0}" value="${value}" step="${c.rp ? 1 : 0.05}" min="0">
            </div>
          </div>`;
      }).join('');
    }

    $id('btnSaveSpezialpreise').addEventListener('click', async function () {
      const spin = $id('saveSpezialpreiseSpinner'); spin.classList.remove('d-none'); this.disabled = true;
      const updates = [...document.querySelectorAll('.spezialpreis-input')].map(inp => {
        const v = parseFloat(inp.value) || 0;
        return { typ: inp.dataset.typ, price_cents: inp.dataset.rp === '1' ? Math.round(v) : Math.round(v * 100) };
      });
      try {
        const results = await Promise.all(updates.map(u => api('update_spezialpreis', { body: u })));
        const fehler = results.filter(r => !r.success);
        if (fehler.length) msvToast(fehler[0].message || 'Fehler beim Speichern', 'error');
        else msvToast('Spezialpreise gespeichert', 'success');
        await loadSpezialpreise();
        renderStiche(true); recalcTotals(); renderUebersicht();
      } catch (e) { msvToast('Netzwerkfehler', 'error'); }
      finally { spin.classList.add('d-none'); this.disabled = false; }
    });
  }

  // =========================================================================
  //  Mobile Cards
  // =========================================================================
  function buildMobileEndschCards() {
    if (!window.matchMedia('(max-width: 767.98px)').matches) return;
    const container = document.querySelector('#mobileCardsEndsch .mobile-cards-scroll');
    if (!container) return;
    if (!state.uebersicht.length) {
      container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Niemand erfasst</div></div>';
      return;
    }
    const byId = Object.fromEntries(state.stiche.map(s => [Number(s.id), s.name]));
    container.innerHTML = state.uebersicht.map((e, idx) => {
      const names = (e.stiche || []).map(sid => byId[Number(sid)]).filter(Boolean);
      const typLabel = e.teilnehmer_typ === 'js' ? ' (JS)' : e.teilnehmer_typ === 'gast' ? ' (Gast)' : '';
      return `
        <div class="mobile-card" data-index="${idx}">
          <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
            <div><div class="fw-bold">${esc(e.name)}${typLabel}</div><small class="text-muted">${names.length} Stiche · ${fmtCHF(e.total_price)}</small></div>
            <i class="bi bi-chevron-down"></i>
          </div>
          <div class="mobile-card-body">
            <div class="mobile-card-detail-row"><span class="mobile-card-detail-label">Stiche</span><span class="mobile-card-detail-value">${esc(names.join(', ') || '–')}</span></div>
            <div class="mobile-card-detail-row"><span class="mobile-card-detail-label">Waffe</span><span class="mobile-card-detail-value">${esc(waffeText(e))}</span></div>
            <div class="mobile-card-detail-row"><span class="mobile-card-detail-label">Zusatzmunition</span><span class="mobile-card-detail-value">${e.munition_schuss || 0} Schuss</span></div>
            <div class="mobile-card-detail-row"><span class="mobile-card-detail-label">Zahlung</span><span class="mobile-card-detail-value">${e.zahlungsmethode === 'bar' ? 'Bar' : 'Karte'}</span></div>
            <a href="#" class="btn btn-outline-primary btn-sm w-100 mt-3 act-edit" data-typ="${esc(e.typ)}" data-entity-id="${e.entity_id}" data-name="${esc(e.name)}"><i class="bi bi-pencil me-2"></i>Bearbeiten</a>
          </div>
        </div>`;
    }).join('');
  }

  window.filterMobileEndsch = function (input) {
    const q = input.value.toLowerCase();
    const container = document.querySelector('#mobileCardsEndsch .mobile-cards-scroll');
    let visible = 0;
    container.querySelectorAll('.mobile-card').forEach(card => {
      const show = card.textContent.toLowerCase().includes(q);
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    const empty = container.querySelector('.mobile-cards-empty');
    if (visible === 0 && !empty) container.insertAdjacentHTML('beforeend', '<div class="mobile-cards-empty"><i class="bi bi-search"></i><div>Keine Treffer</div></div>');
    else if (visible > 0 && empty) empty.remove();
  };

  let wasDesktop = window.matchMedia('(min-width: 768px)').matches;
  window.addEventListener('resize', () => {
    const now = window.matchMedia('(min-width: 768px)').matches;
    if (wasDesktop && !now) buildMobileEndschCards();
    wasDesktop = now;
  });

  // =========================================================================
  //  Stammdaten laden / Init
  // =========================================================================
  function populateYearSelect() {
      msvJahrAuswahl($id('yearSelect'));
  }

  async function loadMitglieder() {
    const sel = $id('mitgliedSelect');
    try {
      const j = await api('list_mitglieder');
      (j.data || []).forEach(m => {
        sel.add(new Option(`${(m.Nachname || '').trim()} ${(m.Vorname || '').trim()}`.trim(), m.id));
        state.mitgliedWaffe.set(Number(m.id), m.waffe_id ? Number(m.waffe_id) : null);
      });
    } catch (e) { msvToast('Mitglieder konnten nicht geladen werden', 'error'); }
    // Bereits erfasste Mitglieder ausblenden – ausser dem gerade gewählten (Bearbeiten aus der Tabelle)
    const defaultMatcher = $.fn.select2.defaults.defaults.matcher;
    const matcher = (params, data) => {
      if (data.id && state.erfassteMitglieder.has(Number(data.id)) && String(data.id) !== sel.value) return null;
      return defaultMatcher(params, data);
    };
    $(sel).select2({ theme: 'bootstrap-5', placeholder: 'Mitglied suchen…', allowClear: true, width: '100%', matcher })
      .on('select2:open', () => { const f = document.querySelector('.select2-search__field'); if (f) f.focus(); });
  }

  async function loadWaffen() {
    try {
      const j = await api('list_waffen');
      state.waffen = j.data || [];
      const sel = $id('waffeSelect');
      state.waffen.forEach(w => sel.add(new Option(`${w.Bezeichnung} (${w.Kategorie})`, w.ID)));
    } catch (e) { msvToast('Waffenliste konnte nicht geladen werden', 'error'); }
  }

  async function loadSpezialpreise() {
    try {
      const j = await api('get_spezialpreise');
      if (j.success && j.data) Object.keys(j.data).forEach(t => { state.spezial[t] = Number(j.data[t].price_cents) || 0; });
    } catch (e) { msvToast('Spezialpreise konnten nicht geladen werden – es werden Standardpreise angezeigt', 'warning'); }
  }

  async function loadStiche() {
    try {
      const j = await api('list_stiche');
      state.stiche = j.success ? (j.data || []) : [];
    } catch (e) { state.stiche = []; msvToast('Stiche konnten nicht geladen werden', 'error'); }
  }

  (async function init() {
    populateYearSelect();
    await Promise.all([loadMitglieder(), loadWaffen(), loadSpezialpreise(), loadStiche()]);
    if (!state.stiche.length) msvToast('Keine aktiven Stiche definiert', 'warning');
    setTyp('mitglied');
    losenMerken();
    losenJahr = $id('yearSelect').value;
    await loadUebersicht();
    initPrint(); // Druck-Resolver + Status-Callback registrieren (QZ verbindet MsvDruck im Hintergrund)
  })();
})();
</script>

<?php include 'footer.inc.php'; ?>
