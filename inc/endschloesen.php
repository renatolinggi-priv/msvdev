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
   Endschiessen lösen – Formular links, Übersicht rechts
   ========================================= */
.main-content-wrapper {
    background: transparent !important;
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
}
.erfassung-layout { display: flex; flex-direction: column; gap: 1.25rem; }
.erfassung-form-col .content-background {
    padding: 1.25rem 1.5rem !important;
    background: #fff !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 0.75rem !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
    margin-bottom: 0 !important;
}
.erfassung-table-col .table-wrapper {
    margin-bottom: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    overflow: hidden;
}
.erfassung-table-col .table-title {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
}
/* Innenabstand der Karte: Tabelle klebt sonst am Rand */
.erfassung-table-col .desktop-table-container { padding: 0.75rem 1.25rem 1.25rem; }
.erfassung-table-col .table-responsive { max-height: calc(100vh - 230px); overflow-y: auto; }
#erfassteTabelle thead th:first-child, #erfassteTabelle tbody td:first-child { padding-left: 0.75rem; }
@media (min-width: 1400px) {
    .erfassung-layout { flex-direction: row; align-items: flex-start; }
    .erfassung-form-col { flex: 0 0 640px; }
    .erfassung-table-col { flex: 1 1 auto; min-width: 0; }
}

/* Abschnitte im Formular: gleiche Sprache wie das Erfassungs-Panel (.shot-section zentral) */
#stichForm .shot-section { padding: 1rem 0; }
#stichForm .shot-section.shot-first { padding-top: 0; }
#stichForm .shot-section-head { margin-bottom: 0.75rem; }

/* Teilnehmer-Umschalter */
.typ-switch .btn { font-size: 0.8rem; padding: 0.3rem 0.8rem; }
.typ-switch .btn i { font-size: 0.85em; }

/* Stich-Kacheln */
.stich-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; }
@media (max-width: 575.98px) { .stich-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.stich-tile {
    position: relative;
    display: flex; flex-direction: column; gap: 0.15rem;
    padding: 0.55rem 0.7rem 0.5rem 2.1rem;
    border: 1.5px solid #dee2e6; border-radius: 0.5rem; background: #fff;
    cursor: pointer; user-select: none;
    transition: border-color 0.15s, background-color 0.15s, box-shadow 0.15s;
}
.stich-tile:hover { border-color: #b6c6d9; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.stich-tile .form-check-input {
    position: absolute; left: 0.65rem; top: 0.7rem; margin: 0; cursor: pointer;
}
.stich-tile-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; min-width: 0; }
.stich-tile-name { font-size: 0.82rem; font-weight: 600; color: #1e293b; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.stich-tile-meta { display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.7rem; color: #94a3b8; white-space: nowrap; }
.stich-tile-meta .stich-price { font-weight: 600; color: #475569; }
.stich-tile.selected { background: #f0fdf4; border-color: #86efac; }
.stich-tile.selected .stich-tile-meta .stich-price { color: #15803d; }
.stich-tile-partner {
    display: inline-flex; align-items: center; gap: 0.3rem; flex: 0 0 auto;
    font-size: 0.68rem; color: #64748b; cursor: pointer; margin: 0;
}
.stich-tile.selected .stich-tile-partner:has(:checked) { color: #15803d; font-weight: 600; }
.stich-tile-partner .form-check-input { position: static; margin: 0; width: 1.6em; height: 0.9em; }

/* Zusatzmunition */
.muni-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.35rem 0; }
.muni-row + .muni-row { border-top: 1px solid #f1f5f9; }
.muni-row .form-check { margin: 0; flex: 1 1 auto; }
.muni-row .form-check-label { font-size: 0.82rem; }
.muni-row .muni-price { font-size: 0.78rem; color: #475569; font-weight: 600; min-width: 5.5rem; text-align: right; }
.muni-row .muni-input { width: 5.5rem; text-align: center; }
.muni-row .muni-label { flex: 1 1 auto; font-size: 0.82rem; }

/* Total-Leiste */
.total-actions-row {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
    margin-top: 0.75rem; padding-top: 1rem; border-top: 1px solid #eef2f7;
}
.total-bar { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.total-kpi { display: flex; flex-direction: column; line-height: 1.1; }
.total-kpi small { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.4px; color: #94a3b8; font-weight: 600; }
.total-kpi strong { font-size: 0.95rem; color: #1e293b; }
.total-amount {
    font-size: 1.05rem; font-weight: 700; color: #fff; background: var(--secondary-color, #0d6efd);
    border-radius: 999px; padding: 0.25rem 0.9rem;
}
.action-buttons { display: flex; gap: 0.4rem; flex-wrap: wrap; }

/* Übersichtstabelle */
#erfassteTabelle th { font-size: 0.72rem; vertical-align: bottom; padding: 0.4rem 0.35rem; white-space: nowrap; }
#erfassteTabelle .stich-header {
    writing-mode: vertical-rl; transform: rotate(180deg);
    text-align: left; font-weight: 600; letter-spacing: 0.3px;
    height: 92px; min-width: 24px; max-width: 28px; padding: 0.3rem 0.15rem !important;
    text-transform: none;
}
#erfassteTabelle tbody td { vertical-align: middle; padding: 0.35rem 0.4rem; font-size: 0.82rem; }
#erfassteTabelle td.check-cell { padding-left: 0.15rem; padding-right: 0.15rem; }
#erfassteTabelle td.waffe-cell { white-space: nowrap; font-size: 0.75rem; color: #64748b; }
#erfassteTabelle td.name-cell { white-space: nowrap; font-weight: 500; text-align: left; }
#erfassteTabelle thead th:first-child { text-align: left; }
#erfassteTabelle td.name-cell .typ-badge {
    display: inline-block; margin-left: 0.4rem; font-size: 0.6rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.4px; padding: 0.05rem 0.4rem; border-radius: 999px; background: #f1f5f9; color: #64748b; vertical-align: middle;
}
#erfassteTabelle td.name-cell .typ-badge.js { background: #dbeafe; color: #1d4ed8; }
#erfassteTabelle td.check-cell { text-align: center; color: #16a34a; font-size: 0.95rem; }
#erfassteTabelle td.check-cell .partner-mark { font-size: 0.6rem; color: #1d4ed8; vertical-align: super; }
#erfassteTabelle td.check-cell .empty { color: #d9e0e8; }
#erfassteTabelle td.muni-cell { white-space: nowrap; font-size: 0.75rem; color: #475569; }
#erfassteTabelle td.muni-cell .muni-tag {
    display: inline-block; padding: 0.05rem 0.4rem; border-radius: 4px; background: #f1f5f9; margin-right: 0.25rem;
}
#erfassteTabelle td.total-cell { text-align: right; font-weight: 600; white-space: nowrap; }
#erfassteTabelle tbody tr.row-selected { background: rgba(74,144,217,0.08); box-shadow: inset 3px 0 0 #4a90d9; }
#erfassteTabelle .dropdown-toggle::after { display: none; }
#erfassteTabelle .dropdown-menu { font-size: 0.85rem; }

/* Admin-Panel: Definitionen + Spezialpreise */
#adminPanel .def-table td, #adminPanel .def-table th { padding: 0.35rem 0.4rem; font-size: 0.82rem; vertical-align: middle; }
#adminPanel .def-table code { font-size: 0.72rem; color: #64748b; }
#adminPanel .preis-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.35rem 0; }
#adminPanel .preis-row + .preis-row { border-top: 1px solid #f1f5f9; }
#adminPanel .preis-row .preis-label { flex: 1 1 auto; font-size: 0.82rem; }
#adminPanel .preis-row .preis-label small { display: block; color: #94a3b8; font-size: 0.7rem; }
#adminPanel .preis-row .input-group { width: 9.5rem; flex: 0 0 auto; }
#adminPanel .def-edit { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.85rem 1rem; }

/* Select2 an form-select-sm angleichen */
.select2-container--bootstrap-5 .select2-selection--single {
    font-size: 0.875rem; min-height: calc(1.5em + 0.5rem + 2px); padding: 0.25rem 0.5rem;
}
.select2-container { z-index: 1065; }

@media (max-width: 767.98px) {
    .erfassung-table-col .desktop-table-container { display: none !important; }
    .erfassung-table-col .mobile-cards-container { display: flex !important; }
    .erfassung-table-col .table-responsive { max-height: none; }
}
@media (min-width: 768px) { .erfassung-table-col .mobile-cards-container { display: none !important; } }
";

include 'header.inc.php';

if (!isset($_SESSION)) { session_start(); }
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Definitionen und Preise dürfen Admin und Vorstand pflegen (gleiche Regel wie adminApiGuard)
$kannDefinieren = in_array($_SESSION['user_role'] ?? '', ['admin', 'vorstand'], true);
?>
<style><?= $page_specific_css ?></style>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

<div class="container-fluid">
<div class="row">
<div class="col-12 ps-0">
  <div class="main-content-wrapper content-width-wide">
    <?php $page_title = 'Endschiessen – Stiche lösen'; include 'partials/page_header.inc.php'; ?>

    <div class="erfassung-layout">

      <!-- ================= Formular ================= -->
      <div class="erfassung-form-col">
      <div class="content-background">
        <form id="stichForm" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" id="gastId" value="">

          <!-- Teilnehmer -->
          <div class="shot-section shot-first">
            <div class="shot-section-head">
              <span class="shot-section-title"><i class="bi bi-person"></i>Teilnehmer</span>
              <div class="d-flex align-items-center gap-2">
                <label for="yearSelect" class="shot-hint mb-0">Jahr</label>
                <select id="yearSelect" class="form-select form-select-sm" style="width:auto"></select>
              </div>
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
                  <div class="col-6 col-md-3">
                    <label for="gastWaffe" class="panel-label">Waffe</label>
                    <select id="gastWaffe" class="form-select form-select-sm">
                      <option value="">– wählen –</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Stiche -->
          <div class="shot-section">
            <div class="shot-section-head">
              <span class="shot-section-title"><i class="bi bi-bullseye"></i>Stiche <span class="shot-hint" id="stichHint"></span></span>
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
            <div class="shot-section-head" role="button" data-bs-toggle="collapse" data-bs-target="#munitionCollapse" aria-expanded="false" aria-controls="munitionCollapse">
              <span class="shot-section-title">
                <i class="bi bi-chevron-right" id="munitionChevron"></i>Zusätzliche Munition
                <span class="shot-hint" id="munitionProSchussText"></span>
              </span>
              <span class="shot-total" id="munitionBadge" hidden>0</span>
            </div>
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
              <span class="total-amount" id="totalPrice">CHF 0.00</span>
            </div>
            <div class="action-buttons">
              <button type="button" id="btnStandblatt" class="btn btn-outline-info btn-sm" disabled data-tooltip="Standblatt (Excel) für diesen Teilnehmer">
                <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-lg-inline ms-1">Standblatt</span>
              </button>
              <button type="button" id="btnReset" class="btn btn-outline-secondary btn-sm" data-tooltip="Formular zurücksetzen">
                <i class="bi bi-arrow-counterclockwise"></i>
              </button>
              <button type="submit" id="btnSave" class="btn btn-outline-primary btn-sm">
                <span class="spinner-border spinner-border-sm me-1 d-none" id="saveSpinner"></span>
                <i class="bi bi-save me-1"></i>Speichern
              </button>
            </div>
          </div>
        </form>
      </div>
      </div>

      <!-- ================= Übersicht ================= -->
      <div class="erfassung-table-col">
        <div class="table-wrapper">
          <div class="table-title">
            <span><i class="bi bi-table me-2"></i>Gelöst <span class="badge bg-light text-dark border ms-1" id="erfasstCount">0</span></span>
            <div class="d-flex gap-2">
              <button type="button" id="btnGeneratePDF" class="btn btn-outline-info btn-sm" data-tooltip="Abrechnung als PDF">
                <i class="bi bi-file-earmark-pdf me-1"></i>Abrechnung
              </button>
              <?php if ($kannDefinieren): ?>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAdminSettings" data-tooltip="Stiche und Preise definieren">
                <i class="bi bi-gear me-1"></i>Definition
              </button>
              <?php endif; ?>
            </div>
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
                  <tr><td colspan="3" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm me-2"></div>Lade Daten...</td></tr>
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
        </div>
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
    <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Endschiessen Definition</h6>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="adminClose"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="panel-body">
    <div class="shot-section shot-first">
      <div class="shot-section-head">
        <span class="shot-section-title"><i class="bi bi-card-list"></i>Stiche</span>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddNewStich"><i class="bi bi-plus-lg me-1"></i>Neuer Stich</button>
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
        <span class="shot-section-title"><i class="bi bi-currency-exchange"></i>Spezialpreise</span>
      </div>
      <div class="shot-section-body" id="spezialpreiseContainer"></div>
    </div>
  </div>
  <div class="panel-footer">
    <div class="d-flex gap-2 w-100 justify-content-end">
      <button type="button" class="btn btn-outline-primary btn-sm" id="btnSaveSpezialpreise">
        <span class="spinner-border spinner-border-sm me-1 d-none" id="saveSpezialpreiseSpinner"></span>
        <i class="bi bi-save me-1"></i>Spezialpreise speichern
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
  const GAST_ERLAUBT     = ['END', 'SCHWINI_P1', 'SCHWINI_P2', 'SIEUNDER'];
  const PREIS_DEFAULTS   = { munition_pro_schuss: 50, gast_kombi_2: 3500, gast_kombi_3: 4900, gast_sie_und_er: 1000, partner_zabig: 1000, js_paket_preis: 0 };

  const state = {
    typ: 'mitglied',          // mitglied | gast | js
    stiche: [],               // aktive Definitionen
    alleStiche: [],           // inkl. inaktive (Admin)
    spezial: { ...PREIS_DEFAULTS },
    waffen: [],
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
      const kombi = codes.filter(c => GAST_KOMBI_CODES.includes(c));
      let p = 0;
      if (kombi.length === 1) p = preisVon(kombi[0]);
      else if (kombi.length === 2) p = preis('gast_kombi_2');
      else if (kombi.length >= 3) p = preis('gast_kombi_3');
      if (codes.includes('SIEUNDER')) p += preis('gast_sie_und_er');
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
    if (typ === 'gast') return GAST_ERLAUBT;
    return state.stiche.map(s => s.code).filter(c => c !== 'PROBE');
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
    } else {
      $('#mitgliedSelect').val('').trigger('change.select2');
      if (!$id('gastWaffe').value) setDefaultWaffe();
    }
    renderStiche(keepSelection);
    recalcTotals();
  }

  function setDefaultWaffe() {
    const stgw90 = state.waffen.find(w => /stgw\s*90/i.test(w.Bezeichnung || ''));
    if (stgw90) $id('gastWaffe').value = stgw90.ID;
  }

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

    $id('stichHint').textContent = state.typ === 'js' ? 'Paketpreis ' + (preis('js_paket_preis') > 0 ? fmtCHF(preis('js_paket_preis')) : 'gratis')
      : state.typ === 'gast' ? 'Kombi-Preise: 2 Stiche ' + fmtCHF(preis('gast_kombi_2')) + ', ab 3 ' + fmtCHF(preis('gast_kombi_3'))
      : '';

    stiche.forEach(s => {
      const shots = Number(s.shots) || 0;
      let preisText = fmtCHF(s.price_cents);
      if (state.typ === 'js') preisText = 'im Paket';
      if (state.typ === 'gast' && s.code === 'SIEUNDER') preisText = fmtCHF(preis('gast_sie_und_er'));
      if (s.code === 'PROBE' && state.typ !== 'js') preisText = 'gratis';

      const partner = (s.code === 'ZABIG' && state.typ === 'mitglied') ? `
        <label class="stich-tile-partner" for="partner_zabig" data-tooltip="Zabig mit Partner: ${fmtCHF(preis('partner_zabig'))}">
          <input class="form-check-input partner-check" type="checkbox" role="switch" id="partner_zabig" data-stich-id="${s.id}" ${partnerVorher ? 'checked' : ''}>Partner
        </label>` : '';

      const tile = document.createElement('label');
      tile.className = 'stich-tile';
      tile.setAttribute('for', 'stich_' + s.id);
      tile.dataset.stichId = s.id;
      tile.innerHTML = `
        <input class="form-check-input stich-check" type="checkbox" value="${s.id}" id="stich_${s.id}" data-code="${esc(s.code)}" data-shots="${shots}" ${vorher.has(String(s.id)) ? 'checked' : ''}>
        <span class="stich-tile-head"><span class="stich-tile-name">${esc(s.name)}</span>${partner}</span>
        <span class="stich-tile-meta"><span>${shots} Schuss</span><span class="stich-price" id="price_${s.id}">${preisText}</span></span>`;
      list.appendChild(tile);
    });
    updateTiles();
  }

  function updateTiles() {
    document.querySelectorAll('.stich-tile').forEach(t => {
      const cb = t.querySelector('.stich-check');
      t.classList.toggle('selected', !!(cb && cb.checked));
    });
    const alle = [...document.querySelectorAll('.stich-check')];
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
  // Klick auf den Partner-Schalter darf die Kachel nicht mit-toggeln
  document.addEventListener('click', (e) => {
    if (e.target.closest('.stich-tile-partner')) e.stopPropagation();
  }, true);

  $id('btnSelectAll').addEventListener('click', () => {
    const alle = [...document.querySelectorAll('.stich-check')];
    const alleAn = alle.length > 0 && alle.every(cb => cb.checked);
    alle.forEach(cb => { cb.checked = !alleAn; });
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

  function recalcTotals() {
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
    $id('btnStandblatt').disabled = !(hatPerson && checked.length > 0);
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
    $id('gastWaffe').value = '';
    $id('gastHint').hidden = true;
    $id('zahlung_karte').checked = true;
    resetZusatz();
    state.aktuelleEntity = null;
    markRow(null);
    setTyp(keepTyp ? state.typ : 'mitglied');
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
    updateTiles(); recalcTotals();
  }

  async function loadMitgliedSelection() {
    const mid = $id('mitgliedSelect').value;
    if (!mid) { applySelection(null); return; }
    state.aktuelleEntity = { typ: 'mitglied', id: Number(mid) };
    markRow(state.aktuelleEntity);
    try {
      applySelection(await api('get_selection', { query: { mitglied_id: mid, jahr: $id('yearSelect').value } }));
    } catch (e) { applySelection(null); }
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
      if (j.waffen_id) $id('gastWaffe').value = j.waffen_id;
      $id('gastHint').textContent = 'Bereits erfasst – Änderungen überschreiben die Auswahl';
      $id('gastHint').hidden = false;
      state.aktuelleEntity = { typ: 'gast', id: Number(j.gast_id) };
      markRow(state.aktuelleEntity);
      applySelection(j);
    } catch (e) { /* still */ }
  }

  $('#mitgliedSelect').on('change', () => { if (state.typ === 'mitglied') loadMitgliedSelection(); });
  $id('gastName').addEventListener('input', () => {
    $id('gastId').value = '';
    clearTimeout(state.gastLookupTimer);
    state.gastLookupTimer = setTimeout(() => loadGastSelection(), 400);
    recalcTotals();
  });
  $id('yearSelect').addEventListener('change', () => {
    if (state.typ === 'mitglied') loadMitgliedSelection(); else loadGastSelection({ byId: null });
    loadUebersicht();
  });
  $id('btnReset').addEventListener('click', () => resetForm());

  $id('stichForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const typ = state.typ;
    const body = {
      typ,
      jahr: $id('yearSelect').value,
      stiche: [...document.querySelectorAll('.stich-check:checked')].map(cb => cb.value),
      zahlungsmethode: document.querySelector('input[name="zahlungsmethode"]:checked').value,
      zabig_partner: !!($id('partner_zabig') && $id('partner_zabig').checked),
      zusatz_schuesse: zusatzListe()
    };
    if (typ === 'mitglied') {
      body.mitglied_id = $id('mitgliedSelect').value;
      if (!body.mitglied_id) { msvToast('Bitte ein Mitglied wählen', 'warning'); $('#mitgliedSelect').select2('open'); return; }
    } else {
      body.gast_name = $id('gastName').value.trim();
      body.gast_id = $id('gastId').value || undefined;
      body.gast_geburtsdatum = typ === 'js' ? $id('gastGeburtsdatum').value : '';
      body.waffen_id = $id('gastWaffe').value || undefined;
      if (!body.gast_name) { msvToast('Bitte den Namen eingeben', 'warning'); $id('gastName').focus(); return; }
      if (typ === 'js' && !body.gast_geburtsdatum) { msvToast('Bitte das Geburtsdatum eingeben', 'warning'); $id('gastGeburtsdatum').focus(); return; }
    }
    if (!body.stiche.length && !body.zusatz_schuesse.length) {
      const r = await msvConfirm('Es ist kein Stich gewählt. Bestehende Auswahl dieses Teilnehmers wird geleert.', 'Auswahl leeren', 'Ja, leeren');
      if (!r.isConfirmed) return;
    }

    const btn = $id('btnSave'), spin = $id('saveSpinner');
    btn.disabled = true; spin.classList.remove('d-none');
    try {
      const j = await api('save_selection', { body });
      if (!j.success) { msvToast(j.message || 'Fehler beim Speichern', 'error'); return; }
      msvToast((j.message || 'Gespeichert') + ' – ' + fmtCHF((j.data && j.data.preis_cents) || 0) + ' Stiche', 'success');
      await loadUebersicht();
      resetForm({ keepTyp: true });
      if (state.typ === 'mitglied') $('#mitgliedSelect').select2('open'); else $id('gastName').focus();
    } catch (e) {
      msvToast('Netzwerkfehler beim Speichern', 'error');
    } finally {
      btn.disabled = false; spin.classList.add('d-none');
    }
  });

  // =========================================================================
  //  Übersichtstabelle
  // =========================================================================
  function markRow(entity) {
    document.querySelectorAll('#erfassteTabelle tbody tr').forEach(tr => {
      tr.classList.toggle('row-selected', !!entity && tr.dataset.typ === entity.typ && Number(tr.dataset.entityId) === entity.id);
    });
  }

  async function loadUebersicht() {
    const jahr = $id('yearSelect').value;
    try {
      const j = await api('get_year_details', { query: { jahr } });
      state.uebersicht = j.success ? (j.data || []) : [];
    } catch (e) { state.uebersicht = []; }
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
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-tooltip="Aktionen"><i class="bi bi-three-dots"></i></button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item act-edit" href="#" data-typ="${esc(e.typ)}" data-entity-id="${e.entity_id}" data-name="${esc(e.name)}"><i class="bi bi-pencil me-2"></i>Bearbeiten</a></li>
                <li><a class="dropdown-item act-standblatt" href="#" data-typ="${esc(e.typ)}" data-entity-id="${e.entity_id}" data-name="${esc(e.name)}" data-stiche="${esc(codes)}"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Standblatt</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item act-delete text-danger" href="#" data-typ="${esc(e.typ)}" data-entity-id="${e.entity_id}" data-name="${esc(e.name)}"><i class="bi bi-trash me-2"></i>Löschen</a></li>
              </ul>
            </div>
          </td>
        </tr>`;
    }).join('');
    markRow(state.aktuelleEntity);
    buildMobileEndschCards();
  }

  document.addEventListener('click', async (e) => {
    const edit = e.target.closest('.act-edit');
    const stand = e.target.closest('.act-standblatt');
    const del = e.target.closest('.act-delete');
    if (!edit && !stand && !del) return;
    e.preventDefault();
    const a = edit || stand || del;
    const typ = a.dataset.typ, entityId = a.dataset.entityId, name = a.dataset.name;

    if (edit) {
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
    if (stand) { await downloadStandblatt({ typ, entityId, name, stiche: a.dataset.stiche || '' }); return; }
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
  async function downloadStandblatt({ typ, entityId, name, stiche }) {
    const jahr = $id('yearSelect').value;
    let url = `endschloesen/generate_standblatt.php?jahr=${encodeURIComponent(jahr)}&stiche=${encodeURIComponent(stiche)}`;
    url += typ === 'mitglied' ? `&mitglied_id=${encodeURIComponent(entityId)}` : `&gast_name=${encodeURIComponent(name)}`;
    try {
      const r = await fetch(url);
      if (!r.ok) throw new Error();
      const blob = await r.blob();
      const m = (r.headers.get('Content-Disposition') || '').match(/filename="?([^"]+)"?/);
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = m ? m[1] : `Endschiessen_${jahr}_${name.replace(/[^a-zA-ZäöüÄÖÜ0-9]/g, '_')}.xlsx`;
      document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(a.href);
      msvToast('Standblatt heruntergeladen', 'success');
    } catch (err) { msvToast('Fehler beim Erstellen des Standblatts', 'error'); }
  }

  $id('btnStandblatt').addEventListener('click', async function () {
    const btn = this, orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    const stiche = gewaehlteCodes().join(',');
    if (state.typ === 'mitglied') {
      const opt = $id('mitgliedSelect').selectedOptions[0];
      await downloadStandblatt({ typ: 'mitglied', entityId: $id('mitgliedSelect').value, name: opt ? opt.textContent : 'Mitglied', stiche });
    } else {
      await downloadStandblatt({ typ: 'gast', entityId: $id('gastId').value, name: $id('gastName').value.trim(), stiche });
    }
    btn.disabled = false; btn.innerHTML = orig; recalcTotals();
  });

  $id('btnGeneratePDF').addEventListener('click', async function () {
    const btn = this, orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>PDF…';
    try {
      const r = await fetch(`endschloesen/generate_pdf_endschloesen.php?action=generate_pdf&jahr=${encodeURIComponent($id('yearSelect').value)}`);
      const j = await r.json();
      if (j.pdf_link) { window.open(j.pdf_link, '_blank'); msvToast('Abrechnung erstellt', 'success'); }
      else msvToast('Fehler: ' + (j.message || j.error || 'unbekannt'), 'error');
    } catch (err) { msvToast('Fehler beim Erstellen der Abrechnung', 'error'); }
    finally { btn.disabled = false; btn.innerHTML = orig; }
  });

  // =========================================================================
  //  Admin-Panel: Definitionen + Spezialpreise
  // =========================================================================
  const PREIS_CONFIG = [
    { typ: 'munition_pro_schuss', label: 'Munition pro Schuss', hint: 'für zusätzliche Munition', rp: true },
    { typ: 'gast_kombi_2', label: 'Gäste: 2 Stiche', hint: 'aus Endstich / Schwini' },
    { typ: 'gast_kombi_3', label: 'Gäste: ab 3 Stiche', hint: 'aus Endstich / Schwini' },
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
      try { const j = await api('get_stich_definitions'); if (j.success) { state.alleStiche = j.data || []; renderAdminTable(); } } catch (e) { /* still */ }
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
    const sel = $id('yearSelect');
    const y = new Date().getFullYear();
    sel.innerHTML = '';
    for (let i = y; i >= y - 3; i--) sel.add(new Option(String(i), String(i), i === y, i === y));
  }

  async function loadMitglieder() {
    const sel = $id('mitgliedSelect');
    try {
      const j = await api('list_mitglieder');
      (j.data || []).forEach(m => sel.add(new Option(`${(m.Nachname || '').trim()} ${(m.Vorname || '').trim()}`.trim(), m.id)));
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
      const sel = $id('gastWaffe');
      state.waffen.forEach(w => sel.add(new Option(`${w.Bezeichnung} (${w.Kategorie})`, w.ID)));
    } catch (e) { /* ohne Waffenliste weiterarbeiten */ }
  }

  async function loadSpezialpreise() {
    try {
      const j = await api('get_spezialpreise');
      if (j.success && j.data) Object.keys(j.data).forEach(t => { state.spezial[t] = Number(j.data[t].price_cents) || 0; });
    } catch (e) { /* Defaults bleiben */ }
  }

  async function loadStiche() {
    try {
      const j = await api('list_stiche');
      state.stiche = j.success ? (j.data || []) : [];
    } catch (e) { state.stiche = []; }
  }

  (async function init() {
    populateYearSelect();
    await Promise.all([loadMitglieder(), loadWaffen(), loadSpezialpreise(), loadStiche()]);
    if (!state.stiche.length) msvToast('Keine aktiven Stiche definiert', 'warning');
    setTyp('mitglied');
    await loadUebersicht();
  })();
})();
</script>

<?php include 'footer.inc.php'; ?>
