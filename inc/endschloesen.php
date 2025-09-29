<?php
// endschloesen.php – Erfassung der Stiche (Frontend-only, nach Vorbild endresultate.php)
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in endschloesen.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

include 'header.inc.php';

/* CSRF */
if (!isset($_SESSION)) { session_start(); }
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!-- Toast Container -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 11000">
  <div id="toastContainer"></div>
</div>

<div class="row">
  <div class="col-xl-6 col-lg-7 col-md-9 col-12">
    <div class="main-content-wrapper">
      <div class="row mb-3">
        <div class="col-md-12">
          <h2 class="h5 mb-0" style="color: var(--secondary-color);">
            <i class="bi bi-bullseye me-2"></i>
            Endschiessen – Stiche erfassen
          </h2>
        </div>
      </div>

      <div class="content-background">
        <form id="stichForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <!-- Jahr (einheitlich wie endresultate.php) -->
          <div class="year-selection-card mb-2">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0" role="button" data-bs-toggle="collapse" data-bs-target="#yearCollapse" aria-expanded="false" style="cursor: pointer;">
                <i class="bi bi-chevron-right me-1" id="yearChevron"></i>
                <i class="bi bi-calendar3 me-1"></i>Jahr auswählen
              </h6>
            </div>
            <div class="collapse" id="yearCollapse">
              <div class="row align-items-center">
                <div class="col-md-8">
                  <select id="yearSelect" class="form-select form-select-sm"></select>
                </div>
              </div>
            </div>
          </div>

          <!-- Mitgliederauswahl -->
          <div class="mb-2">
            <label for="mitgliedSelect" class="form-label fw-bold mb-1"><i class="bi bi-person"></i> Mitglied / Gast</label>
            <div class="row g-2">
              <div class="col-md-6">
                <select id="mitgliedSelect" class="form-select form-select-sm">
                  <option value="">– Mitglied wählen –</option>
                </select>
              </div>
              <div class="col-md-6">
                <div class="input-group input-group-sm">
                  <span class="input-group-text"><i class="bi bi-person-plus"></i></span>
                  <input type="text" class="form-control" id="gastName" placeholder="Gast/JS Name">
                  <input type="date" class="form-control" id="gastGeburtsdatum" placeholder="Geburtsdatum" style="max-width: 140px;">
                </div>
              </div>
            </div>
            <small class="text-muted">Für Jungschützen: Name eingeben und Geburtsdatum wählen</small>
          </div>

          <!-- Stiche-Auswahl (Kompaktere Checkbox-Karten) -->
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><i class="bi bi-card-checklist"></i> Stiche auswählen</h6>
            <button type="button" id="btnSelectAll" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-check2-square"></i> Alles auswählen
            </button>
          </div>
          <div class="row g-1" id="stichList"></div>
          
          <!-- Zahlungsmethode -->
          <div class="mt-3 mb-3">
            <h6 class="mb-2"><i class="bi bi-credit-card"></i> Zahlungsmethode</h6>
            <div class="btn-group w-100" role="group" aria-label="Zahlungsmethode">
              <input type="radio" class="btn-check" name="zahlungsmethode" id="zahlung_bar" value="bar" checked>
              <label class="btn btn-outline-primary" for="zahlung_bar">
                <i class="bi bi-cash"></i> Bar
              </label>
              
              <input type="radio" class="btn-check" name="zahlungsmethode" id="zahlung_karte" value="karte">
              <label class="btn btn-outline-primary" for="zahlung_karte">
                <i class="bi bi-credit-card-2-back"></i> Karte
              </label>
            </div>
          </div>
          
          <!-- Zusätzliche Schüsse -->
          <div class="mt-3 p-2 bg-light rounded">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0" role="button" data-bs-toggle="collapse" data-bs-target="#munitionCollapse" aria-expanded="false" style="cursor: pointer;">
                <i class="bi bi-chevron-right me-1" id="munitionChevron"></i>
                <i class="bi bi-plus-circle"></i> Zusätzliche Schüsse <span id="munitionProSchussText">(CHF 0.50 pro Schuss)</span>
              </h6>
              <span class="badge bg-primary" id="munitionBadge" style="display: none;">0</span>
            </div>
            <div class="collapse" id="munitionCollapse">
              <div class="row g-2">
              <!-- Standard-Pakete -->
              <div class="col-md-12">
                <div class="card card-body p-2">
                  <h6 class="card-title mb-2 small">Standard-Pakete</h6>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-check mb-1">
                        <input class="form-check-input zusatz-check" type="checkbox" 
                               id="zusatz_gp11_60" data-typ="GP11_60" data-anzahl="60">
                        <label class="form-check-label small" for="zusatz_gp11_60">
                          <strong>60 Schuss GP11</strong> <span class="text-muted" id="preis_gp11_60">(CHF 30.00)</span>
                        </label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-check">
                        <input class="form-check-input zusatz-check" type="checkbox" 
                               id="zusatz_gp90_50" data-typ="GP90_50" data-anzahl="50">
                        <label class="form-check-label small" for="zusatz_gp90_50">
                          <strong>50 Schuss GP90</strong> <span class="text-muted" id="preis_gp90_50">(CHF 25.00)</span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Individuelle Anzahl -->
              <div class="col-md-12">
                <div class="card card-body p-2">
                  <h6 class="card-title mb-2 small">Individuelle Anzahl</h6>
                  <div class="row g-2">
                    <div class="col-md-6">
                      <div class="input-group input-group-sm">
                        <span class="input-group-text" style="width: 50px;">GP11</span>
                        <input type="number" class="form-control form-control-sm zusatz-custom" 
                               id="zusatz_gp11_custom" data-typ="GP11_CUSTOM" 
                               min="0" max="500" step="10" value="0">
                        <span class="input-group-text" id="preis_gp11_custom" style="min-width: 80px;">CHF 0.00</span>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="input-group input-group-sm">
                        <span class="input-group-text" style="width: 50px;">GP90</span>
                        <input type="number" class="form-control form-control-sm zusatz-custom" 
                               id="zusatz_gp90_custom" data-typ="GP90_CUSTOM" 
                               min="0" max="500" step="10" value="0">
                        <span class="input-group-text" id="preis_gp90_custom" style="min-width: 80px;">CHF 0.00</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-2 p-2 bg-white rounded">
              <div class="d-flex justify-content-between align-items-center">
                <span class="small"><strong>Total zusätzliche Schüsse:</strong></span>
                <div class="small">
                  <span id="zusatz_total_schuss">0</span> Schuss = 
                  <strong id="zusatz_total_preis">CHF 0.00</strong>
                </div>
              </div>
            </div>
            </div>
          </div>
          
          <!-- Toolbar direkt unter den Stichen -->
          <div class="d-flex justify-content-between gap-2 mt-2 mb-3">
            <button type="button" id="btnGeneratePDF" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-file-earmark-pdf"></i> Abrechnung
            </button>
            <div class="d-flex gap-2">
              <button type="button" id="btnReset" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i> Zurücksetzen
              </button>
              <button type="submit" id="btnSave" class="btn btn-primary btn-sm">
                <span class="spinner-border spinner-border-sm me-2 d-none" id="saveSpinner"></span>
                <i class="bi bi-save"></i> Speichern
              </button>
            </div>
          </div>
          <div id="saveFeedback" class="text-end small text-muted mt-2"></div>
          
          <!-- Erfasste Stiche Tabelle -->
          <div class="mt-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0"><i class="bi bi-table"></i> Bereits erfasste Stiche</h6>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover table-bordered" id="erfassteTabelle">
                <thead class="table-light">
                  <tr id="erfassteTableHeader">
                    <th style="min-width: 150px;">Mitglied</th>
                    <!-- Stich-Spalten werden dynamisch eingefügt -->
                    <th class="text-end" style="min-width: 80px;">Total</th>
                    <th style="width: 50px;"></th>
                  </tr>
                </thead>
                <tbody id="erfassteTableBody">
                  <tr>
                    <td colspan="3" class="text-muted text-center">Wähle ein Jahr um die erfassten Stiche zu sehen</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Sidebar: Totals -->
  <div class="col-xl-2 col-lg-3 col-md-3 col-12">
    <div class="sidebar-wrapper">
  <div class="content-background p-2">
  <h6 class="mb-2"><i class="bi bi-calculator"></i> Total</h6>
  <div class="d-flex justify-content-between mb-1 small"><span>Ausgewählte Stiche</span><strong id="totalCount">0</strong></div>
  <div class="d-flex justify-content-between mb-1 small"><span>Stiche Schuss</span><strong id="totalShots">0</strong></div>
  <div class="d-flex justify-content-between mb-1 small"><span>Bestellte Munition</span><strong id="totalZusatzShots">0</strong></div>
  <div class="d-flex justify-content-between mb-1 small"><span>Total Schuss</span><strong id="totalAllShots">0</strong></div>
  <hr class="my-1">
  <div class="d-flex justify-content-between"><span><strong>Gesamtpreis</strong></span><strong id="totalPrice">CHF 0.00</strong></div>
  </div>
    
      <?php 
      // Admin-Button nur für berechtigte Benutzer anzeigen
      // Passe diese Bedingung an dein Auth-System an
      $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
      // Für Testing kannst du es auf true setzen:
      $isAdmin = true;
      if ($isAdmin): 
      ?>
      <div class="content-background mt-3">
        <button class="btn btn-outline-secondary btn-sm w-100" id="btnAdminSettings">
          <i class="bi bi-gear"></i> Endschiessen Definition
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Lösch-Bestätigungsmodal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteConfirmModalLabel">
          <i class="bi bi-exclamation-triangle me-2"></i>
          Bestätigung erforderlich
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger mb-3" role="alert">
          <h6 class="alert-heading">Sind Sie sicher?</h6>
          <p class="mb-0" id="deleteConfirmMessage"></p>
        </div>
        <div class="text-muted">
          <small>Diese Aktion kann nicht rückgängig gemacht werden!</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-lg me-2"></i>Abbrechen
        </button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
          <i class="bi bi-trash3 me-2"></i>Ja, löschen
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Admin Modal -->
<div class="modal fade" id="adminModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-gear-fill"></i> Stiche verwalten</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Tabs für Stiche und Spezialpreise -->
        <ul class="nav nav-tabs mb-3" id="adminTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="stiche-tab" data-bs-toggle="tab" data-bs-target="#stiche-panel" type="button" role="tab">
              <i class="bi bi-card-list"></i> Stich-Definitionen
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="preise-tab" data-bs-toggle="tab" data-bs-target="#preise-panel" type="button" role="tab">
              <i class="bi bi-currency-exchange"></i> Spezialpreise
            </button>
          </li>
        </ul>
        
        <div class="tab-content" id="adminTabContent">
          <!-- Stiche Tab -->
          <div class="tab-pane fade show active" id="stiche-panel" role="tabpanel">
            <div class="table-responsive">
              <table class="table table-sm table-hover">
                <thead>
                  <tr>
                    <th width="50">Sort</th>
                    <th>Name</th>
                    <th width="80">Schuss</th>
                    <th width="100">Preis</th>
                    <th width="70">Aktiv</th>
                    <th width="50"></th>
                  </tr>
                </thead>
                <tbody id="adminTableBody">
                  <!-- Wird via JS gefüllt -->
                </tbody>
              </table>
            </div>
            <button class="btn btn-sm btn-success mt-2" id="btnAddNewStich">
              <i class="bi bi-plus-circle"></i> Neuer Stich
            </button>
          </div>
          
          <!-- Spezialpreise Tab -->
          <div class="tab-pane fade" id="preise-panel" role="tabpanel">
            <div class="row" id="spezialpreiseContainer">
              <!-- Wird via JS gefüllt -->
            </div>
            <div class="mt-3">
              <button class="btn btn-primary" id="btnSaveSpezialpreise">
                <span class="spinner-border spinner-border-sm me-2 d-none" id="saveSpezialpreiseSpinner"></span>
                <i class="bi bi-save"></i> Spezialpreise speichern
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal (für einzelnen Stich) -->
<div class="modal fade" id="editStichModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0" id="editModalTitle">Stich bearbeiten</h6>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body py-2">
        <form id="editStichForm">
          <input type="hidden" id="editStichId">
          
          <div class="mb-2">
            <label for="editStichCode" class="form-label form-label-sm mb-1">Code</label>
            <input type="text" class="form-control form-control-sm" id="editStichCode" maxlength="50" required>
            <div class="form-text small">z.B. END, SCHWINI_P1</div>
          </div>
          
          <div class="mb-2">
            <label for="editStichName" class="form-label form-label-sm mb-1">Name</label>
            <input type="text" class="form-control form-control-sm" id="editStichName" maxlength="100" required>
          </div>
          
          <div class="row g-2">
            <div class="col-6">
              <label for="editStichShots" class="form-label form-label-sm mb-1">Anzahl Schuss</label>
              <input type="number" class="form-control form-control-sm" id="editStichShots" min="0" max="100" required>
            </div>
            <div class="col-6">
              <label for="editStichPrice" class="form-label form-label-sm mb-1">Preis (CHF)</label>
              <input type="number" class="form-control form-control-sm" id="editStichPrice" min="0" max="1000" step="0.01" required>
            </div>
          </div>
          
          <div class="row g-2 mt-2">
            <div class="col-6">
              <label for="editStichSort" class="form-label form-label-sm mb-1">Sortierung</label>
              <input type="number" class="form-control form-control-sm" id="editStichSort" min="0" max="999" required>
            </div>
            <div class="col-6">
              <label class="form-label form-label-sm mb-1">Status</label>
              <div class="form-check form-switch form-check-sm">
                <input class="form-check-input" type="checkbox" id="editStichActive" checked>
                <label class="form-check-label small" for="editStichActive">Aktiv</label>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-primary btn-sm" id="btnSaveStich">
          <span class="spinner-border spinner-border-sm me-1 d-none" id="editStichSpinner"></span>
          Speichern
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  /* Seiten-spezifisch; hält sich an bestehendes Layout */
  #stichList .card { 
    cursor: pointer; 
    transition: all 0.2s;
    margin-bottom: 0;
  }
  #stichList .card:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }
  #stichList .card.selected {
    background-color: var(--bs-success-bg-subtle);
    border-color: var(--bs-success);
  }
  #stichList .card.disabled-card {
    opacity: 0.5;
    cursor: not-allowed;
  }
  #stichList .card.disabled-card:hover {
    transform: none;
    box-shadow: none;
  }
  .stich-card-body {
    padding: 0.5rem;
  }
  .stich-card-body .form-check {
    margin-bottom: 0;
  }
  .stich-card-body .form-check-label {
    font-size: 0.875rem;
    font-weight: 600;
  }
  .partner-checkbox-wrapper {
    display: flex;
    align-items: center;
    margin-left: auto;
  }
  .partner-label {
    font-weight: normal;
    color: var(--bs-secondary);
    margin-right: 0.25rem;
    margin-bottom: 0;
    cursor: pointer;
  }
  .partner-checkbox-wrapper .partner-check {
    margin-top: 0;
    cursor: pointer;
  }
  .stich-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.15rem;
    font-size: 0.75rem;
  }
  .stich-price { 
    font-weight: 600; 
    color: var(--bs-primary);
  }
  #erfassteTabelle tbody tr:hover {
    background-color: var(--bs-gray-100);
  }
  #erfassteTabelle th {
    font-size: 0.8rem;
    vertical-align: middle;
    padding: 0.25rem;
  }
  #erfassteTabelle .stich-header {
    writing-mode: vertical-rl;
    text-orientation: mixed;
    padding: 4px 2px;
    min-width: 30px;
    max-width: 40px;
    height: 80px;
    text-align: center;
    font-weight: normal;
    background-color: var(--bs-light);
    border-right: 1px solid var(--bs-gray-300);
    font-size: 0.7rem;
  }
  #erfassteTabelle .check-cell {
    text-align: center;
    font-size: 1rem;
    color: var(--bs-success);
    font-weight: bold;
    padding: 2px;
  }
  #erfassteTabelle .total-cell {
    font-weight: 600;
    white-space: nowrap;
  }
  #erfassteTabelle tbody td {
    vertical-align: middle;
    padding: 0.25rem;
    font-size: 0.8rem;
  }
  #erfassteTabelle .btn-group-sm > .btn {
    padding: 0.1rem 0.3rem;
    font-size: 0.7rem;
  }
  #erfassteTabelle th:last-child {
    min-width: 80px;
  }
  
  /* Kompaktere Sidebar */
  .sidebar-wrapper .content-background {
    padding: 0.75rem;
  }
  
  /* Kleinere Admin-Button */
  #btnAdminSettings {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
  }
  
  /* Collapse Header Hover */
  h6[data-bs-toggle="collapse"]:hover {
    color: var(--bs-primary);
  }
  
  /* Badge Animation */
  #munitionBadge {
    transition: all 0.3s ease;
  }
</style>

<script>
(function(){
  // === Konfiguration ===
  const API = 'endschloesen/endschloesen_api.php'; // Backend folgt separat
  let STICHE = [];
  let ALL_STICHE = []; // Für Admin-Verwaltung
  let SPEZIALPREISE = {}; // NEU: Speichert die Spezialpreise
  let adminModal = null;
  let editStichModal = null;

  // Fallback (damit Frontend klickbar ist, falls API noch nicht vorhanden)
  const FALLBACK_STICHE = [
    {id: 1, code:'END',        name:'Endstich',        shots:10, price_cents:2000, sort_order:10},
    {id: 2, code:'SCHWINI_P1', name:'Schwini P. 1',    shots:10, price_cents:2000, sort_order:20},
    {id: 3, code:'SCHWINI_P2', name:'Schwini P. 2',    shots:10, price_cents:2000, sort_order:30},
    {id: 4, code:'KUNST',      name:'Kunst',           shots:10, price_cents:2000, sort_order:40},
    {id: 5, code:'GLUECK',     name:'Glück',           shots:10, price_cents:2000, sort_order:50},
    {id: 6, code:'ZABIG',      name:'Zabig',           shots:10, price_cents:2000, sort_order:60},
    {id: 7, code:'DIFF',       name:'Differenzler',    shots:10, price_cents:2000, sort_order:70},
    {id: 8, code:'SIEUNDER',   name:'Sie und Er',      shots:5,  price_cents:1000, sort_order:80}
  ];

  // === Helpers ===
  function fmtCHF(cents){ 
    // Stelle sicher dass cents eine Zahl ist
    const numCents = Number(cents) || 0;
    const v = numCents/100;
    return 'CHF ' + v.toFixed(2); 
  }

  function populateYearSelect(){
    const sel = document.getElementById('yearSelect');
    sel.innerHTML = '';
    const currentYear = new Date().getFullYear();
    for(let y = currentYear + 1; y >= 2024; y--){
      const opt = document.createElement('option');
      opt.value = String(y);
      opt.textContent = String(y);
      if (y === currentYear) opt.selected = true;
      sel.appendChild(opt);
    }
  }

function renderStiche(){
  const list = document.getElementById('stichList');
  list.innerHTML = '';
  const stiche = [...STICHE].sort((a,b)=> (a.sort_order||999) - (b.sort_order||999));
  
  // Prüfe ob Gast ausgewählt ist
  const isGast = document.getElementById('gastName').value.trim() !== '';
  
  stiche.forEach(s=>{
    const shots = Number.isFinite(+s.shots) ? +s.shots : 0;
    const price = Number.isFinite(+s.price_cents) ? +s.price_cents : 0;
    
    // Für Gäste deaktiviere bestimmte Stiche
    const isDisabledForGast = isGast && ['GLUECK', 'ZABIG', 'DIFF', 'KUNST'].includes(s.code);
    
    // Spezielle Partner-Checkbox für Zabig (nur für Mitglieder)
    const partnerCheckbox = (s.code === 'ZABIG' && !isGast) ? `
      <div class="partner-checkbox-wrapper ms-auto d-flex align-items-center">
        <label class="partner-label small me-1 mb-0" for="partner_zabig">
          Partner:
        </label>
        <input class="form-check-input partner-check mt-0" type="checkbox" id="partner_zabig" data-stich-id="${s.id}">
      </div>` : '';

    const col = document.createElement('div');
    col.className = 'col-6 col-sm-4 col-md-4 col-lg-3';
    col.innerHTML = `
      <div class="card card-stich ${isDisabledForGast ? 'disabled-card' : ''}" data-stich-id="${s.id}">
        <div class="stich-card-body">
          <div class="d-flex align-items-center">
            <div class="form-check mb-0">
              <input class="form-check-input stich-check"
                     type="checkbox"
                     value="${s.id}"
                     id="stich_${s.id}"
                     data-shots="${shots}"
                     data-price="${price}"
                     data-name="${s.name}"
                     data-code="${s.code}"
                     ${isDisabledForGast ? 'disabled' : ''}>
              <label class="form-check-label fw-semibold ${isDisabledForGast ? 'text-muted' : ''}" for="stich_${s.id}">
                ${s.name}
              </label>
            </div>
            ${partnerCheckbox}
          </div>
          <div class="stich-info">
            <small class="text-muted">${shots} Schuss</small>
            <span class="stich-price small" id="price_${s.id}">${fmtCHF(price)}</span>
          </div>
        </div>
      </div>`;
    list.appendChild(col);
  });
  
  // Update card selected state
  updateCardStyles();
  // Update Preise für Gäste
  updateGastPreise();
}

function updateCardStyles() {
  document.querySelectorAll('.card-stich').forEach(card => {
    const checkbox = card.querySelector('.stich-check');
    if (checkbox && checkbox.checked) {
      card.classList.add('selected');
    } else {
      card.classList.remove('selected');
    }
  });
}

function updateGastPreise() {
  const isGast = document.getElementById('gastName').value.trim() !== '';
  
  if (!isGast) {
    // Stelle normale Preise wieder her
    STICHE.forEach(s => {
      const priceEl = document.getElementById('price_' + s.id);
      if (priceEl) {
        priceEl.textContent = fmtCHF(s.price_cents);
      }
      const checkbox = document.getElementById('stich_' + s.id);
      if (checkbox) {
        checkbox.setAttribute('data-price', s.price_cents);
      }
    });
    return;
  }
  
  // Spezielle Preise für Gäste
  STICHE.forEach(s => {
    let gastPreis = 0;
    
    // Sie und Er ist immer CHF 10.00 für Gäste
    if (s.code === 'SIEUNDER') {
      gastPreis = 1000; // CHF 10.00
    }
    // Endstich, Schwini Passe 1, Schwini Passe 2, Kunst
    // Prüfe welche Kombination ausgewählt ist
    else if (['END', 'SCHWINI_P1', 'SCHWINI_P2', 'KUNST'].includes(s.code)) {
      // Diese Berechnung erfolgt in recalcTotals() da sie von der Kombination abhängt
      gastPreis = s.price_cents; // Erstmal normale Preise
    } else {
      gastPreis = s.price_cents;
    }
    
    const priceEl = document.getElementById('price_' + s.id);
    if (priceEl && s.code === 'SIEUNDER') {
      priceEl.textContent = fmtCHF(gastPreis);
    }
    const checkbox = document.getElementById('stich_' + s.id);
    if (checkbox && s.code === 'SIEUNDER') {
      checkbox.setAttribute('data-price', gastPreis);
    }
  });
}



function recalcTotals(){
  const checked = document.querySelectorAll('.stich-check:checked');
  const isGast = document.getElementById('gastName').value.trim() !== '';
  
  let count = 0, shots = 0, price = 0;
  
  if (isGast) {
    // Spezielle Preisberechnung für Gäste
    let hasEnd = false;
    let hasSchwini1 = false;
    let hasSchwini2 = false;
    let hasKunst = false;
    let hasSieUndEr = false;
    
    // Prüfe welche Stiche ausgewählt sind
    checked.forEach(cb=>{
      const code = cb.getAttribute('data-code');
      if (code === 'END') hasEnd = true;
      if (code === 'SCHWINI_P1') hasSchwini1 = true;
      if (code === 'SCHWINI_P2') hasSchwini2 = true;
      if (code === 'KUNST') hasKunst = true;
      if (code === 'SIEUNDER') hasSieUndEr = true;
    });
    
    // Berechne Kombination für End, Schwini, Kunst (alle 4 Stiche)
    const kombiCount = [hasEnd, hasSchwini1, hasSchwini2, hasKunst].filter(Boolean).length;
    
    // Verwende Spezialpreise aus der Datenbank - stelle sicher dass es Zahlen sind!
    const kombi2Preis = Number(SPEZIALPREISE.gast_kombi_2 ? SPEZIALPREISE.gast_kombi_2.price_cents : 3500);
    const kombi3Preis = Number(SPEZIALPREISE.gast_kombi_3 ? SPEZIALPREISE.gast_kombi_3.price_cents : 4900);
    const sieUndErPreis = Number(SPEZIALPREISE.gast_sie_und_er ? SPEZIALPREISE.gast_sie_und_er.price_cents : 1000);
    
    if (kombiCount === 2) {
      // Zwei ausgewählt = Kombi-2 Preis
      price = Number(price) + kombi2Preis;
    } else if (kombiCount === 3) {
      // Drei ausgewählt = Kombi-3 Preis
      price = Number(price) + kombi3Preis;
    } else if (kombiCount === 4) {
      // Alle vier ausgewählt = Kombi-3 Preis (gleich wie 3)
      price = Number(price) + kombi3Preis;
    } else if (kombiCount === 1) {
      // Nur einer ausgewählt = normale Preise
      checked.forEach(cb=>{
        const code = cb.getAttribute('data-code');
        if (['END', 'SCHWINI_P1', 'SCHWINI_P2', 'KUNST'].includes(code)) {
          const p = Number(cb.getAttribute('data-price'));
          price = Number(price) + (Number.isFinite(p) ? p : 0);
        }
      });
    }
    
    // Sie und Er ist immer Spezialpreis für Gäste
    if (hasSieUndEr) {
      price = Number(price) + sieUndErPreis;
    }
    
    // Zähle alle ausgewählten
    count = checked.length;
    
    // Zähle Schüsse
    checked.forEach(cb=>{
      const s = Number(cb.getAttribute('data-shots'));
      shots += Number.isFinite(s) ? s : 0;
    });
    
  } else {
    // Normale Preisberechnung für Mitglieder
    checked.forEach(cb=>{
      count++;
      const s = Number(cb.getAttribute('data-shots'));
      const p = Number(cb.getAttribute('data-price'));
      shots += Number.isFinite(s) ? s : 0;
      price += Number.isFinite(p) ? p : 0;
    });
  }
  
  document.getElementById('totalCount').textContent = String(count);
  document.getElementById('totalShots').textContent = String(shots);
  
  // Hole Zusätzliche Schüsse Total
  let zusatzSchuss = 0;
  let zusatzPreis = 0;
  
  const munitionProSchuss = Number(SPEZIALPREISE.munition_pro_schuss ? SPEZIALPREISE.munition_pro_schuss.price_cents : 60);
  
  // Standard-Pakete
  document.querySelectorAll('.zusatz-check:checked').forEach(cb => {
    const anzahl = parseInt(cb.dataset.anzahl) || 0;
    zusatzSchuss += anzahl;
    zusatzPreis = Number(zusatzPreis) + (anzahl * munitionProSchuss);
  });
  
  // Individuelle Anzahl
  document.querySelectorAll('.zusatz-custom').forEach(input => {
    const anzahl = parseInt(input.value) || 0;
    if (anzahl > 0) {
      zusatzSchuss += anzahl;
      zusatzPreis = Number(zusatzPreis) + (anzahl * munitionProSchuss);
    }
  });
  
  // Update alle Totals direkt hier
  document.getElementById('totalZusatzShots').textContent = zusatzSchuss;
  document.getElementById('totalAllShots').textContent = shots + zusatzSchuss;
  
  // Debug
  console.log('Gast-Berechnung - price:', price, typeof price, 'zusatzPreis:', zusatzPreis, typeof zusatzPreis, 'total:', (price + zusatzPreis), typeof (price + zusatzPreis));
  
  // Stelle sicher dass beide Werte Zahlen sind
  const totalPrice = Number(price) + Number(zusatzPreis);
  document.getElementById('totalPrice').textContent = fmtCHF(totalPrice);
  
  // Update auch die Munition-Box Anzeige
  document.getElementById('zusatz_total_schuss').textContent = zusatzSchuss;
  document.getElementById('zusatz_total_preis').textContent = fmtCHF(zusatzPreis);
  
  // Update Munition Badge
  const munitionBadge = document.getElementById('munitionBadge');
  if (zusatzSchuss > 0) {
    munitionBadge.textContent = zusatzSchuss;
    munitionBadge.style.display = 'inline-block';
  } else {
    munitionBadge.style.display = 'none';
  }
}



  // === Events ===
// Click auf Karte
document.addEventListener('click', function(e){
  // Nicht reagieren wenn auf Checkbox oder Label geklickt wurde
  if (e.target.classList.contains('stich-check') || 
      e.target.classList.contains('form-check-input') ||
      e.target.classList.contains('form-check-label') ||
      e.target.closest('.form-check-label')) {
    return;
  }
  
  const card = e.target.closest('.card-stich');
  if (card) {
    const cb = card.querySelector('.stich-check');
    if (cb) {
      cb.checked = !cb.checked;
      const changeEvent = new Event('change', { bubbles: true });
      cb.dispatchEvent(changeEvent);
      // Zur Sicherheit direkt recalcTotals aufrufen
      recalcTotals();
    }
  }
});

// Change Event
document.addEventListener('change', function(e){
  if (e.target.classList.contains('stich-check')) {
    recalcTotals();
    updateCardStyles();
  }
  
  // Partner-Checkbox bei Zabig
  if (e.target.classList.contains('partner-check')) {
    const stichId = e.target.dataset.stichId;
    const zabigCheckbox = document.getElementById('stich_' + stichId);
    const partnerPreis = SPEZIALPREISE.partner_zabig ? SPEZIALPREISE.partner_zabig.price_cents : 1000;
    
    if (e.target.checked) {
      // Partner ausgewählt - Zabig automatisch anwählen und Preis auf Partner-Preis
      if (zabigCheckbox && !zabigCheckbox.checked) {
        zabigCheckbox.checked = true;
        updateCardStyles();
      }
      zabigCheckbox.setAttribute('data-price', partnerPreis);
      document.getElementById('price_' + stichId).textContent = fmtCHF(partnerPreis);
    } else {
      // Partner nicht ausgewählt - normaler Preis
      const originalStich = STICHE.find(s => s.id == stichId);
      if (originalStich) {
        zabigCheckbox.setAttribute('data-price', originalStich.price_cents);
        document.getElementById('price_' + stichId).textContent = fmtCHF(originalStich.price_cents);
      }
    }
    
    recalcTotals();
  }
});

// Input Event
document.addEventListener('input', function(e){
  if (e.target.classList.contains('stich-check')) {
    recalcTotals();
  }
});

  // === Zusätzliche Schüsse Functions ===
  function initZusatzSchuesse() {
    // Event Listener für Standard-Pakete
    document.querySelectorAll('.zusatz-check').forEach(cb => {
      cb.addEventListener('change', recalcZusatzTotal);
    });
    
    // Event Listener für individuelle Anzahl
    document.querySelectorAll('.zusatz-custom').forEach(input => {
      input.addEventListener('input', function() {
        const typ = this.dataset.typ;
        const anzahl = parseInt(this.value) || 0;
        const munitionProSchuss = SPEZIALPREISE.munition_pro_schuss ? SPEZIALPREISE.munition_pro_schuss.price_cents : 60;
        const preis = anzahl * munitionProSchuss; // Dynamischer Preis pro Schuss
        
        // Update Preis-Anzeige
        if (typ === 'GP11_CUSTOM') {
          document.getElementById('preis_gp11_custom').textContent = fmtCHF(preis);
        } else if (typ === 'GP90_CUSTOM') {
          document.getElementById('preis_gp90_custom').textContent = fmtCHF(preis);
        }
        
        recalcZusatzTotal();
      });
    });
  }
  
  // Neue Funktion um Munitionspreise in der Anzeige zu aktualisieren
  function updateMunitionPreise() {
    const munitionProSchuss = SPEZIALPREISE.munition_pro_schuss ? SPEZIALPREISE.munition_pro_schuss.price_cents : 60;
    
    // Update "pro Schuss" Anzeige
    const proSchussText = document.getElementById('munitionProSchussText');
    if (proSchussText) {
      proSchussText.textContent = `(CHF ${(munitionProSchuss/100).toFixed(2)} pro Schuss)`;
    }
    
    // Update GP11 60er Paket Preis
    const gp11_60_preis = document.getElementById('preis_gp11_60');
    if (gp11_60_preis) {
      gp11_60_preis.textContent = `(${fmtCHF(60 * munitionProSchuss)})`;
    }
    
    // Update GP90 50er Paket Preis
    const gp90_50_preis = document.getElementById('preis_gp90_50');
    if (gp90_50_preis) {
      gp90_50_preis.textContent = `(${fmtCHF(50 * munitionProSchuss)})`;
    }
  }
  
  function recalcZusatzTotal() {
    // Rufe einfach recalcTotals auf, da diese Funktion jetzt alles berechnet
    recalcTotals();
  }
  
  function updateGesamtTotal(stichePreisOverride) {
    // Hole Stiche-Total
    let stichePreis = 0;
    let sticheSchuss = 0;
    
    if (typeof stichePreisOverride !== 'undefined') {
      // Verwende den übergebenen Preis (für Gast-Spezialpreise)
      stichePreis = stichePreisOverride;
      // Zähle Schüsse normal
      document.querySelectorAll('.stich-check:checked').forEach(cb=>{
        const s = Number(cb.getAttribute('data-shots'));
        sticheSchuss += Number.isFinite(s) ? s : 0;
      });
    } else {
      // Normale Berechnung
      document.querySelectorAll('.stich-check:checked').forEach(cb=>{
        const p = Number(cb.getAttribute('data-price'));
        const s = Number(cb.getAttribute('data-shots'));
        stichePreis += Number.isFinite(p) ? p : 0;
        sticheSchuss += Number.isFinite(s) ? s : 0;
      });
    }
    
    // Hole Zusätzliche Schüsse Total
    let zusatzSchuss = 0;
    let zusatzPreis = 0;
    
    // Standard-Pakete
    document.querySelectorAll('.zusatz-check:checked').forEach(cb => {
      const anzahl = parseInt(cb.dataset.anzahl) || 0;
      zusatzSchuss += anzahl;
      zusatzPreis += anzahl * 50;
    });
    
    // Individuelle Anzahl
    document.querySelectorAll('.zusatz-custom').forEach(input => {
      const anzahl = parseInt(input.value) || 0;
      if (anzahl > 0) {
        zusatzSchuss += anzahl;
        zusatzPreis += anzahl * 50;
      }
    });
    
    // Update Sidebar Totals
    document.getElementById('totalShots').textContent = sticheSchuss;
    document.getElementById('totalZusatzShots').textContent = zusatzSchuss;
    document.getElementById('totalAllShots').textContent = sticheSchuss + zusatzSchuss;
    document.getElementById('totalPrice').textContent = fmtCHF(stichePreis + zusatzPreis);
  }
  
  function resetZusatzSchuesse() {
    document.querySelectorAll('.zusatz-check').forEach(cb => cb.checked = false);
    document.querySelectorAll('.zusatz-custom').forEach(input => {
      input.value = '0';
      // Update Preis-Anzeige
      const typ = input.dataset.typ;
      if (typ === 'GP11_CUSTOM') {
        document.getElementById('preis_gp11_custom').textContent = 'CHF 0.00';
      } else if (typ === 'GP90_CUSTOM') {
        document.getElementById('preis_gp90_custom').textContent = 'CHF 0.00';
      }
    });
    recalcZusatzTotal();
  }
  
  function loadZusatzSchuesse() {
    const mid = document.getElementById('mitgliedSelect').value;
    const gast = document.getElementById('gastName').value.trim();
    const yr = document.getElementById('yearSelect').value;
    
    if (!mid && !gast) {
      resetZusatzSchuesse();
      return;
    }
    
    let url = `${API}?action=get_zusatz_schuesse&jahr=${encodeURIComponent(yr)}`;
    if (mid) {
      url += `&mitglied_id=${encodeURIComponent(mid)}`;
    } else if (gast) {
      url += `&gast_name=${encodeURIComponent(gast)}`;
    }
    
    fetch(url)
      .then(r => r.ok ? r.json() : null)
      .then(data => {
        resetZusatzSchuesse();
        
        if (data && data.success && Array.isArray(data.data)) {
          data.data.forEach(item => {
            if (item.typ === 'GP11_60') {
              document.getElementById('zusatz_gp11_60').checked = true;
            } else if (item.typ === 'GP90_50') {
              document.getElementById('zusatz_gp90_50').checked = true;
            } else if (item.typ === 'GP11_CUSTOM') {
              document.getElementById('zusatz_gp11_custom').value = item.anzahl;
              document.getElementById('preis_gp11_custom').textContent = fmtCHF(item.anzahl * 50);
            } else if (item.typ === 'GP90_CUSTOM') {
              document.getElementById('zusatz_gp90_custom').value = item.anzahl;
              document.getElementById('preis_gp90_custom').textContent = fmtCHF(item.anzahl * 50);
            }
          });
          recalcZusatzTotal();
        }
      })
      .catch(err => console.error('Error loading zusatz:', err));
  }

  // Alles auswählen Button
  document.getElementById('btnSelectAll').addEventListener('click', function() {
    const isGast = document.getElementById('gastName').value.trim() !== '';
    let allChecked = true;
    
    // Prüfe ob alle verfügbaren Checkboxen bereits ausgewählt sind
    document.querySelectorAll('.stich-check:not(:disabled)').forEach(cb => {
      if (!cb.checked) allChecked = false;
    });
    
    if (allChecked) {
      // Wenn alle ausgewählt sind, deselektiere alle
      document.querySelectorAll('.stich-check').forEach(cb => cb.checked = false);
      this.innerHTML = '<i class="bi bi-check2-square"></i> Alles auswählen';
    } else {
      // Wähle alle verfügbaren Stiche aus
      document.querySelectorAll('.stich-check').forEach(cb => {
        if (!cb.disabled) {
          cb.checked = true;
        }
      });
      this.innerHTML = '<i class="bi bi-square"></i> Alles abwählen';
    }
    
    recalcTotals();
    updateCardStyles();
  });
  
  // Modify reset button
  document.getElementById('btnReset').addEventListener('click', function(){
    // Reset Mitglied/Gast
    document.getElementById('mitgliedSelect').value = '';
    document.getElementById('gastName').value = '';
    document.getElementById('gastGeburtsdatum').value = '';
    
    // Reset Stiche
    document.querySelectorAll('.stich-check').forEach(cb=> cb.checked = false);
    document.getElementById('zahlung_bar').checked = true; // Reset auf Bar
    
    // Reset Partner-Checkbox bei Zabig
    const partnerCheck = document.getElementById('partner_zabig');
    if (partnerCheck) {
      partnerCheck.checked = false;
      // Trigger change um Preis zurückzusetzen
      const changeEvent = new Event('change', { bubbles: true });
      partnerCheck.dispatchEvent(changeEvent);
    }
    
    // Render Stiche neu (ohne Gast-Beschränkungen)
    renderStiche();
    resetZusatzSchuesse();
    recalcTotals();
    updateCardStyles();
    
    // Reset "Alles auswählen" Button Text
    document.getElementById('btnSelectAll').innerHTML = '<i class="bi bi-check2-square"></i> Alles auswählen';
  });


  document.getElementById('stichForm').addEventListener('submit', function(ev){
    ev.preventDefault();
    saveSelection();
  });
  
  // Speichern-Funktion
  function saveSelection() {
    const mitglied_id = document.getElementById('mitgliedSelect').value;
    const gast_name = document.getElementById('gastName').value.trim();
    const gast_geburtsdatum = document.getElementById('gastGeburtsdatum').value;
    const jahr = document.getElementById('yearSelect').value;
    
    // Prüfe ob Mitglied oder Gast ausgewählt
    if (!mitglied_id && !gast_name) {
      const fb = document.getElementById('saveFeedback');
      fb.className = 'text-end small text-danger mt-2';
      fb.textContent = 'Bitte wähle ein Mitglied oder gib einen Gastnamen ein';
      setTimeout(() => { fb.textContent = ''; }, 3000);
      return;
    }
    
    // Verhindere dass beides ausgewählt ist
    if (mitglied_id && gast_name) {
      const fb = document.getElementById('saveFeedback');
      fb.className = 'text-end small text-danger mt-2';
      fb.textContent = 'Bitte nur Mitglied ODER Gast auswählen, nicht beides';
      setTimeout(() => { fb.textContent = ''; }, 3000);
      return;
    }
    
    // Füge Geburtsdatum zum Gast-Namen hinzu wenn vorhanden
    let gast_name_with_date = gast_name;
    if (gast_name && gast_geburtsdatum) {
      // Konvertiere YYYY-MM-DD zu DD.MM.YYYY
      const dateParts = gast_geburtsdatum.split('-');
      if (dateParts.length === 3) {
        const formattedDate = `${dateParts[2]}.${dateParts[1]}.${dateParts[0]}`;
        gast_name_with_date = `${gast_name} (${formattedDate})`;
      }
    }
    
    // Sammle ausgewählte Stiche
    const stiche = [];
    let zabigIsPartner = false;
    
    document.querySelectorAll('.stich-check:checked').forEach(cb => {
      stiche.push(cb.value);
      // Prüfe ob es Zabig ist und Partner ausgewählt
      if (cb.dataset.code === 'ZABIG') {
        const partnerCheck = document.getElementById('partner_zabig');
        if (partnerCheck && partnerCheck.checked) {
          zabigIsPartner = true;
        }
      }
    });
    
    // Hole Zahlungsmethode
    const zahlungsmethode = document.querySelector('input[name="zahlungsmethode"]:checked').value;
    
    // Berechne Gast-Spezialpreis wenn Gast
    let gastSpezialpreis = null;
    if (gast_name) {
      // Prüfe welche Stiche ausgewählt sind
      let hasEnd = false;
      let hasSchwini1 = false;
      let hasSchwini2 = false;
      let hasKunst = false;
      let hasSieUndEr = false;
      
      document.querySelectorAll('.stich-check:checked').forEach(cb => {
        const code = cb.getAttribute('data-code');
        if (code === 'END') hasEnd = true;
        if (code === 'SCHWINI_P1') hasSchwini1 = true;
        if (code === 'SCHWINI_P2') hasSchwini2 = true;
        if (code === 'KUNST') hasKunst = true;
        if (code === 'SIEUNDER') hasSieUndEr = true;
      });
      
      // Berechne Kombination für End, Schwini, Kunst (alle 4 Stiche)
      const kombiCount = [hasEnd, hasSchwini1, hasSchwini2, hasKunst].filter(Boolean).length;
      
      let totalPrice = 0;
      
      if (kombiCount === 2) {
        // Zwei ausgewählt = CHF 35.00
        totalPrice += 3500;
      } else if (kombiCount >= 3) {
        // Drei oder vier ausgewählt = CHF 49.00
        totalPrice += 4900;
      } else if (kombiCount === 1) {
        // Nur einer ausgewählt = normale Preise
        document.querySelectorAll('.stich-check:checked').forEach(cb => {
          const code = cb.getAttribute('data-code');
          if (['END', 'SCHWINI_P1', 'SCHWINI_P2', 'KUNST'].includes(code)) {
            const p = Number(cb.getAttribute('data-price'));
            totalPrice += Number.isFinite(p) ? p : 0;
          }
        });
      }
      
      // Sie und Er ist immer CHF 10.00
      if (hasSieUndEr) {
        totalPrice += 1000;
      }
      
      gastSpezialpreis = totalPrice;
    }
    
    // Sammle zusätzliche Schüsse
    const zusatz_schuesse = [];
    
    // Standard-Pakete
    if (document.getElementById('zusatz_gp11_60').checked) {
      zusatz_schuesse.push({ typ: 'GP11_60', anzahl: 60 });
    }
    if (document.getElementById('zusatz_gp90_50').checked) {
      zusatz_schuesse.push({ typ: 'GP90_50', anzahl: 50 });
    }
    
    // Individuelle Anzahl
    const gp11_custom = parseInt(document.getElementById('zusatz_gp11_custom').value) || 0;
    if (gp11_custom > 0) {
      zusatz_schuesse.push({ typ: 'GP11_CUSTOM', anzahl: gp11_custom });
    }
    
    const gp90_custom = parseInt(document.getElementById('zusatz_gp90_custom').value) || 0;
    if (gp90_custom > 0) {
      zusatz_schuesse.push({ typ: 'GP90_CUSTOM', anzahl: gp90_custom });
    }
    
    // Zeige Spinner
    const spinner = document.getElementById('saveSpinner');
    const btn = document.getElementById('btnSave');
    spinner.classList.remove('d-none');
    btn.disabled = true;
    
    // Sende an API
    const url = `${API}?action=save_selection`;
    
    // Bereite Daten vor
    const requestData = {
      jahr: jahr,
      stiche: stiche,
      zusatz_schuesse: zusatz_schuesse,
      zahlungsmethode: zahlungsmethode
    };
    
    // Füge Zabig-Partner-Info hinzu wenn relevant
    if (zabigIsPartner) {
      requestData.zabig_partner = true;
    }
    
    // Füge Gast-Spezialpreis hinzu wenn vorhanden
    if (gastSpezialpreis !== null) {
      requestData.gast_spezialpreis = gastSpezialpreis;
    }
    
    // Entweder Mitglied ID oder Gast Name mit Geburtsdatum
    if (mitglied_id) {
      requestData.mitglied_id = mitglied_id;
    } else {
      requestData.gast_name = gast_name_with_date;
    }
    
    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('[name="csrf_token"]').value
      },
      body: JSON.stringify(requestData)
    })
    .then(r => r.json())
    .then(data => {
      const fb = document.getElementById('saveFeedback');
      if (data.success) {
        fb.className = 'ms-auto small text-success';
        fb.textContent = '✓ ' + (data.message || 'Gespeichert');
        
        // Tabelle der erfassten Stiche aktualisieren
        loadErfassteStiche();
        
        // Nach erfolgreichem Speichern alles zurücksetzen
        setTimeout(() => {
          // Reset Mitglied/Gast Auswahl
          document.getElementById('mitgliedSelect').value = '';
          document.getElementById('gastName').value = '';
          
          // Reset alle Stiche
          document.querySelectorAll('.stich-check').forEach(cb => cb.checked = false);
          
          // Reset Partner-Checkbox
          const partnerCheck = document.getElementById('partner_zabig');
          if (partnerCheck) {
            partnerCheck.checked = false;
            const changeEvent = new Event('change', { bubbles: true });
            partnerCheck.dispatchEvent(changeEvent);
          }
          
          // Reset Zahlungsmethode
          document.getElementById('zahlung_bar').checked = true;
          
          // Reset Munition
          resetZusatzSchuesse();
          
          // Render Stiche neu
          renderStiche();
          
          // Update Totals und Styles
          recalcTotals();
          updateCardStyles();
          
          // Reset "Alles auswählen" Button
          document.getElementById('btnSelectAll').innerHTML = '<i class="bi bi-check2-square"></i> Alles auswählen';
        }, 500); // Kurze Verzögerung damit man das Erfolgsmeldung sieht
      } else {
        fb.className = 'ms-auto small text-danger';
        fb.textContent = '✗ ' + (data.message || 'Fehler beim Speichern');
      }
      setTimeout(() => { fb.textContent = ''; }, 4000);
    })
    .catch(err => {
      console.error('Save error:', err);
      const fb = document.getElementById('saveFeedback');
      fb.className = 'ms-auto small text-danger';
      fb.textContent = '✗ Netzwerkfehler';
      setTimeout(() => { fb.textContent = ''; }, 3000);
    })
    .finally(() => {
      spinner.classList.add('d-none');
      btn.disabled = false;
    });
  }

  // === Daten laden ===
  function loadMitglieder(){
    const sel = document.getElementById('mitgliedSelect');
    sel.innerHTML = '<option value="">– bitte wählen –</option>';
    // Wir versuchen die API – wenn sie (noch) fehlt, machen wir nichts
    fetch(`${API}?action=list_mitglieder`).then(r=>r.ok?r.json():null).then(j=>{
      if (!j || !j.success) return;
      j.data.forEach(m=>{
        const label = `${(m.Nachname||m.Name||'').trim()} ${(m.Vorname||'').trim()}`.trim();
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = label;
        sel.appendChild(opt);
      });
    }).catch(()=>{});
  }

  function loadStiche(){
    // Lade zuerst Spezialpreise, dann Stiche
    fetch(`${API}?action=get_spezialpreise`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          SPEZIALPREISE = data.data;
        }
      })
      .catch(err => {
        // Fallback auf Default-Werte wenn Tabelle nicht existiert
        SPEZIALPREISE = {
          munition_pro_schuss: { price_cents: 60 },
          gast_kombi_2: { price_cents: 3500 },
          gast_kombi_3: { price_cents: 4900 },
          gast_sie_und_er: { price_cents: 1000 },
          partner_zabig: { price_cents: 1000 }
        };
      })
      .finally(() => {
        // Update Munitionspreise in der Anzeige
        updateMunitionPreise();
        
        // Dann lade Stiche
        fetch(`${API}?action=list_stiche`).then(r=>r.ok?r.json():null).then(j=>{
          if (j && j.success && Array.isArray(j.data) && j.data.length){
            STICHE = j.data;
          } else {
            STICHE = FALLBACK_STICHE;
          }
          renderStiche();
          recalcTotals();
        }).catch(()=>{
          STICHE = FALLBACK_STICHE;
          renderStiche();
          recalcTotals();
        });
      });
  }

function loadSelection(){
  const mid = document.getElementById('mitgliedSelect').value;
  const gast = document.getElementById('gastName').value.trim();
  const yr  = document.getElementById('yearSelect').value;
  
  if (!mid && !gast) {
    document.querySelectorAll('.stich-check').forEach(cb=> cb.checked=false);
    // Reset Zahlungsmethode auf Bar
    document.getElementById('zahlung_bar').checked = true;
    recalcTotals();
    updateCardStyles();
    return;
  }
  
  let url = `${API}?action=get_selection&jahr=${encodeURIComponent(yr)}`;
  if (mid) {
    url += `&mitglied_id=${encodeURIComponent(mid)}`;
  } else if (gast) {
    url += `&gast_name=${encodeURIComponent(gast)}`;
  }
  
  fetch(url)
    .then(r=>r.ok?r.json():null)
    .then(j=>{
      document.querySelectorAll('.stich-check').forEach(cb=> cb.checked=false);
      if (j && j.success){
        // Lade Stiche
        if (Array.isArray(j.data)){
          j.data.forEach(id=>{ 
            const el = document.getElementById('stich_'+id); 
            if (el) el.checked = true; 
          });
        }
        // Lade Zahlungsmethode falls vorhanden
        if (j.zahlungsmethode) {
          if (j.zahlungsmethode === 'karte') {
            document.getElementById('zahlung_karte').checked = true;
          } else {
            document.getElementById('zahlung_bar').checked = true;
          }
        } else {
          // Default auf Bar
          document.getElementById('zahlung_bar').checked = true;
        }
        
        // Lade Zabig Partner-Status wenn vorhanden
        if (j.zabig_partner) {
          const partnerCheck = document.getElementById('partner_zabig');
          if (partnerCheck) {
            partnerCheck.checked = true;
            // Trigger change event um Preis zu aktualisieren
            const changeEvent = new Event('change', { bubbles: true });
            partnerCheck.dispatchEvent(changeEvent);
          }
        }
      }
      recalcTotals();
      updateCardStyles();
      // Lade auch die zusätzlichen Schüsse
      loadZusatzSchuesse();
    }).catch(()=>{
      document.querySelectorAll('.stich-check').forEach(cb=> cb.checked=false);
      document.getElementById('zahlung_bar').checked = true;
      recalcTotals();
      updateCardStyles();
    });
}

// Neue Funktion für Gast-Daten laden
function loadGastSelection() {
  const gast = document.getElementById('gastName').value.trim();
  const yr = document.getElementById('yearSelect').value;
  
  if (!gast || gast.length < 3) return;
  
  fetch(`${API}?action=get_selection&gast_name=${encodeURIComponent(gast)}&jahr=${encodeURIComponent(yr)}`)
    .then(r=>r.ok?r.json():null)
    .then(j=>{
      document.querySelectorAll('.stich-check').forEach(cb=> cb.checked=false);
      if (j && j.success && Array.isArray(j.data)){
        j.data.forEach(id=>{ 
          const el = document.getElementById('stich_'+id); 
          if (el) el.checked = true; 
        });
      }
      recalcTotals();
      updateCardStyles();
    }).catch(()=>{
      // Bei Fehler nichts tun, Gast ist vermutlich neu
    });
}

  document.getElementById('mitgliedSelect').addEventListener('change', function() {
    // Leere Gast-Feld wenn Mitglied ausgewählt
    if (this.value) {
      document.getElementById('gastName').value = '';
      document.getElementById('gastGeburtsdatum').value = '';
    }
    // Render Stiche neu (entfernt Gast-Beschränkungen)
    renderStiche();
    loadSelection();
    // Aktualisiere die Tabelle um die Highlight-Zeile zu aktualisieren
    loadErfassteStiche();
  });
  
  // Event Listener für Gast-Eingabe
  document.getElementById('gastName').addEventListener('input', function() {
    // Leere Mitglied-Auswahl wenn Gast eingegeben
    if (this.value.trim()) {
      document.getElementById('mitgliedSelect').value = '';
    }
    
    // Render Stiche neu für Gast-spezifische Einstellungen
    renderStiche();
    
    // Lade evtl. vorhandene Daten für diesen Gast
    if (this.value.trim().length > 2) {
      loadGastSelection();
    }
    
    // Aktualisiere Preise
    recalcTotals();
  });
  
  document.getElementById('yearSelect').addEventListener('change', function() {
    const currentMitglied = document.getElementById('mitgliedSelect').value;
    if (currentMitglied) {
      loadSelection(); // Nur laden wenn ein Mitglied ausgewählt ist
    }
    loadErfassteStiche(); // Immer die Tabelle aktualisieren
  });
  
  // Funktion um erfasste Stiche für das Jahr zu laden
  function loadErfassteStiche() {
    const jahr = document.getElementById('yearSelect').value;
    
    // Lade zuerst die Stich-Definitionen und dann die Daten
    Promise.all([
      fetch(`${API}?action=list_stiche`).then(r => r.json()),
      fetch(`${API}?action=get_year_details&jahr=${encodeURIComponent(jahr)}`).then(r => r.json())
    ]).then(([stichData, yearData]) => {
      if (stichData.success && yearData.success) {
        renderErfassteTabelle(stichData.data, yearData.data);
      }
    }).catch(err => {
      console.error('Error loading data:', err);
    });
  }
  
  function renderErfassteTabelle(stiche, data) {
    // Erstelle Header mit Stich-Spalten
    const headerRow = document.getElementById('erfassteTableHeader');
    headerRow.innerHTML = '<th style="min-width: 150px; text-align: left;">Mitglied</th>';
    
    // Füge Spalte für jeden aktiven Stich hinzu
    stiche.forEach(stich => {
      const th = document.createElement('th');
      th.className = 'stich-header';
      th.title = stich.name + ' (' + stich.shots + ' Schuss, ' + fmtCHF(stich.price_cents) + ')';
      th.innerHTML = stich.name;
      headerRow.appendChild(th);
    });
    
    // Munition, Zahlungsmethode und Total Spalten
    headerRow.innerHTML += '<th class="text-center" style="min-width: 120px;">Munition</th>';
    headerRow.innerHTML += '<th class="text-center" style="min-width: 80px;">Bezahlt</th>';
    headerRow.innerHTML += '<th class="text-end" style="min-width: 80px;">Total</th>';
    headerRow.innerHTML += '<th style="width: 50px;"></th>';
    
    // Fülle Tabellen-Body
    const tbody = document.getElementById('erfassteTableBody');
    
    if (!data || data.length === 0) {
      const colCount = stiche.length + 5; // +2 für Munition und Zahlungsmethode
      tbody.innerHTML = `
        <tr>
          <td colspan="${colCount}" class="text-muted text-center">Noch keine Stiche für dieses Jahr erfasst</td>
        </tr>`;
      return;
    }
    
    // Sortiere die Daten: Mitglieder zuerst, dann normale Gäste, dann JS (Gäste mit Geburtsdatum)
    data.sort((a, b) => {
      // Bestimme die Gruppen
      let groupA, groupB;
      
      if (a.typ === 'mitglied') {
        groupA = 1;
      } else if (a.typ === 'gast') {
        // Prüfe ob es ein JS ist (Gast mit Geburtsdatum)
        // Das erkennen wir am Namen-Suffix oder einem speziellen Marker
        if (a.geburtsdatum || (a.name && a.name.includes('(JS)'))) {
          groupA = 3; // JS ganz unten
        } else {
          groupA = 2; // Normale Gäste in der Mitte
        }
      } else {
        groupA = 2;
      }
      
      if (b.typ === 'mitglied') {
        groupB = 1;
      } else if (b.typ === 'gast') {
        if (b.geburtsdatum || (b.name && b.name.includes('(JS)'))) {
          groupB = 3;
        } else {
          groupB = 2;
        }
      } else {
        groupB = 2;
      }
      
      // Sortiere erst nach Gruppe, dann alphabetisch
      if (groupA !== groupB) {
        return groupA - groupB;
      }
      
      // Innerhalb der Gruppe alphabetisch sortieren
      return (a.name || '').localeCompare(b.name || '');
    });
    
    tbody.innerHTML = '';
    data.forEach(entry => {
      const tr = document.createElement('tr');
      
      // Mitglied/Gast Name - zeige JS statt Gast wenn Geburtsdatum vorhanden
      let displayName = entry.name;
      if (entry.typ === 'gast' && entry.name) {
        // Entferne das alte (Gast) Suffix falls vorhanden
        displayName = displayName.replace(' (Gast)', '');
        
        // Füge JS oder Gast hinzu
        if (entry.geburtsdatum) {
          displayName += ' (JS)';
        } else {
          displayName += ' (Gast)';
        }
      }
      
      let html = `<td style="text-align: left;">${displayName}</td>`;
      
      // Stich-Checkmarks
      stiche.forEach(stich => {
        const stichId = parseInt(stich.id);
        if (entry.stiche && entry.stiche.includes(stichId)) {
          // Prüfe ob es ein Partner-Stich ist
          let isPartner = false;
          if (entry.partner_stiche && entry.partner_stiche.includes(stichId)) {
            isPartner = true;
          }
          
          if (isPartner && stich.code === 'ZABIG') {
            // Zeige Partner-Icon für Zabig
            html += '<td class="check-cell text-center" title="Partner: CHF 10.00"><i class="bi bi-people-fill text-primary"></i></td>';
          } else {
            html += '<td class="check-cell text-center"><i class="bi bi-check-circle-fill text-success"></i></td>';
          }
        } else {
          html += '<td class="check-cell"></td>';
        }
      });
      
      // Munition (GP11 und GP90 getrennt anzeigen)
      let gp11Total = 0;
      let gp90Total = 0;
      
      if (entry.zusatz_schuesse && Array.isArray(entry.zusatz_schuesse)) {
        entry.zusatz_schuesse.forEach(z => {
          const anzahl = parseInt(z.anzahl) || 0;
          if (z.typ === 'GP11_60' || z.typ === 'GP11_CUSTOM') {
            gp11Total += anzahl;
          } else if (z.typ === 'GP90_50' || z.typ === 'GP90_CUSTOM') {
            gp90Total += anzahl;
          }
        });
      }
      
      // Formatiere die Anzeige
      let munitionText = '';
      if (gp11Total > 0 || gp90Total > 0) {
        const parts = [];
        if (gp11Total > 0) parts.push(`GP11: ${gp11Total}`);
        if (gp90Total > 0) parts.push(`GP90: ${gp90Total}`);
        munitionText = parts.join('<br>');
      } else {
        munitionText = '-';
      }
      
      html += `<td class="text-center small">${munitionText}</td>`;
      
      // Zahlungsmethode
      let zahlungIcon = '';
      if (entry.zahlungsmethode === 'karte') {
        zahlungIcon = '<i class="bi bi-credit-card-2-back text-primary" title="Karte"></i>';
      } else if (entry.zahlungsmethode === 'bar') {
        zahlungIcon = '<i class="bi bi-cash text-success" title="Bar"></i>';
      } else {
        zahlungIcon = '<span class="text-muted">-</span>';
      }
      html += `<td class="text-center">${zahlungIcon}</td>`;
      
      // Total
      html += `<td class="text-end total-cell">${fmtCHF(entry.total_price || 0)}</td>`;
      
      // Bearbeiten und Löschen Buttons
      html += `
        <td class="text-center">
          <div class="btn-group btn-group-sm" role="group">
            <button class="btn btn-outline-secondary btn-edit-selection" 
                    data-entity-id="${entry.entity_id || entry.mitglied_id}"
                    data-entity-typ="${entry.typ || 'mitglied'}"
                    data-entity-name="${entry.name}"
                    title="Bearbeiten">
              <i class="bi bi-pencil-square"></i>
            </button>
            <button class="btn btn-outline-danger btn-delete-selection" 
                    data-entity-id="${entry.entity_id || entry.mitglied_id}"
                    data-entity-typ="${entry.typ || 'mitglied'}"
                    data-entity-name="${entry.name}"
                    data-jahr="${document.getElementById('yearSelect').value}"
                    title="Löschen">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>`;
      
      tr.innerHTML = html;
      
      // Highlight-Zeile wenn dieses Mitglied aktuell ausgewählt ist
      if (entry.mitglied_id == document.getElementById('mitgliedSelect').value) {
        tr.classList.add('table-active');
      }
      
      tbody.appendChild(tr);
    });
  }
  
  // Event Listener für Bearbeiten-Button
  document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-edit-selection')) {
      e.preventDefault();
      e.stopPropagation();
      
      const btn = e.target.closest('.btn-edit-selection');
      const entityId = btn.dataset.entityId;
      const entityTyp = btn.dataset.entityTyp;
      const entityName = btn.dataset.entityName;
      
      if (entityTyp === 'mitglied') {
        // Setze das Mitglied im Dropdown
        document.getElementById('mitgliedSelect').value = entityId;
        document.getElementById('gastName').value = '';
        // Render Stiche für Mitglied
        renderStiche();
      } else if (entityTyp === 'gast') {
        // Setze den Gast-Namen
        document.getElementById('mitgliedSelect').value = '';
        // Entferne " (Gast)" vom Namen
        const gastName = entityName.replace(' (Gast)', '');
        document.getElementById('gastName').value = gastName;
        // Render Stiche für Gast
        renderStiche();
      }
      
      // Lade die Auswahl
      loadSelection();
      
      // Scrolle nach oben zum Formular
      window.scrollTo({ top: 0, behavior: 'smooth' });
      
      // Zeige kurz eine Info
      showToast(`Bearbeite Stiche für ${entityName}`, 'info');
    }
    
    // Event Listener für Löschen-Button
    if (e.target.closest('.btn-delete-selection')) {
      e.preventDefault();
      e.stopPropagation();
      
      const btn = e.target.closest('.btn-delete-selection');
      const entityId = btn.dataset.entityId;
      const entityTyp = btn.dataset.entityTyp;
      const entityName = btn.dataset.entityName;
      const jahr = btn.dataset.jahr;
      
      // Bestätigungsdialog mit Modal
      showDeleteConfirmation(entityId, entityTyp, entityName, jahr);
    }
  });
  
  // Lösch-Funktion
  function deleteSelection(entityId, entityTyp, entityName, jahr) {
    // Zeige Ladeindikator im Button
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Lösche...';
    
    const url = `${API}?action=delete_selection`;
    
    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('[name="csrf_token"]').value
      },
      body: JSON.stringify({
        entity_id: entityId,
        typ: entityTyp,
        jahr: jahr
      })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(`${entityName} wurde gelöscht`, 'success');
        // Tabelle neu laden
        loadErfassteStiche();
        
        // Falls das gelöschte Element gerade bearbeitet wird, Form zurücksetzen
        const currentMitglied = document.getElementById('mitgliedSelect').value;
        const currentGast = document.getElementById('gastName').value.trim();
        
        if ((entityTyp === 'mitglied' && currentMitglied == entityId) ||
            (entityTyp === 'gast' && currentGast && entityName.includes(currentGast))) {
          document.getElementById('btnReset').click();
        }
      } else {
        showToast('Fehler beim Löschen: ' + (data.message || 'Unbekannter Fehler'), 'danger');
      }
    })
    .catch(err => {
      console.error('Delete error:', err);
      showToast('Netzwerkfehler beim Löschen', 'danger');
    })
    .finally(() => {
      // Reset Button
      const confirmBtn = document.getElementById('confirmDeleteBtn');
      if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="bi bi-trash3 me-2"></i>Ja, löschen';
      }
    });
  }


  // === Admin Functions ===
  function initAdminModals() {
    const adminBtn = document.getElementById('btnAdminSettings');
    if (adminBtn) {
      adminModal = new bootstrap.Modal(document.getElementById('adminModal'));
      editStichModal = new bootstrap.Modal(document.getElementById('editStichModal'));
      
      adminBtn.addEventListener('click', openAdminModal);
      document.getElementById('btnAddNewStich').addEventListener('click', () => openEditStichDialog(null));
      document.getElementById('btnSaveStich').addEventListener('click', saveStichDefinition);
      
      // Enter-Taste im Edit-Form
      document.getElementById('editStichForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveStichDefinition();
      });
    }
  }
  
  function openAdminModal() {
    loadAllStiche();
    loadSpezialpreise();
    adminModal.show();
  }
  
  function loadSpezialpreise() {
    fetch(`${API}?action=get_spezialpreise`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          SPEZIALPREISE = data.data;
          renderSpezialpreise();
        }
      })
      .catch(err => {
        console.log('Spezialpreise-Tabelle noch nicht vorhanden');
        renderSpezialpreiseDefault();
      });
  }
  
  function renderSpezialpreise() {
    const container = document.getElementById('spezialpreiseContainer');
    if (!container) return;
    
    const preiseConfig = [
      { typ: 'munition_pro_schuss', label: 'Munition pro Schuss', einheit: 'Rp.', beschreibung: 'Preis pro Schuss für zusätzliche Munition' },
      { typ: 'munition_gp11_60', label: '60 Schuss GP11 Paket', einheit: 'CHF', beschreibung: 'Standard-Paket GP11' },
      { typ: 'munition_gp90_50', label: '50 Schuss GP90 Paket', einheit: 'CHF', beschreibung: 'Standard-Paket GP90' },
      { typ: 'gast_kombi_2', label: 'Gäste: 2er Kombination', einheit: 'CHF', beschreibung: '2 Stiche aus Endstich/Schwini' },
      { typ: 'gast_kombi_3', label: 'Gäste: 3er Kombination', einheit: 'CHF', beschreibung: '3 Stiche aus Endstich/Schwini' },
      { typ: 'gast_sie_und_er', label: 'Gäste: Sie und Er', einheit: 'CHF', beschreibung: 'Spezialpreis für Sie und Er Stich' },
      { typ: 'partner_zabig', label: 'Partner Zabigstich', einheit: 'CHF', beschreibung: 'Preis wenn Zabig mit Partner gelöst wird' }
    ];
    
    let html = '';
    preiseConfig.forEach(config => {
      const preis = SPEZIALPREISE[config.typ] || { price_cents: 0 };
      const value = config.einheit === 'Rp.' ? preis.price_cents : (preis.price_cents / 100).toFixed(2);
      
      html += `
        <div class="col-md-6 mb-3">
          <div class="card">
            <div class="card-body">
              <h6 class="card-title">${config.label}</h6>
              <p class="card-text small text-muted">${config.beschreibung}</p>
              <div class="input-group">
                <span class="input-group-text">${config.einheit}</span>
                <input type="number" 
                       class="form-control spezialpreis-input" 
                       data-typ="${config.typ}"
                       data-einheit="${config.einheit}"
                       value="${value}"
                       step="${config.einheit === 'Rp.' ? '1' : '0.01'}"
                       min="0">
              </div>
            </div>
          </div>
        </div>
      `;
    });
    
    container.innerHTML = html;
  }
  
  function renderSpezialpreiseDefault() {
    // Zeige Default-Werte wenn Tabelle noch nicht existiert
    const container = document.getElementById('spezialpreiseContainer');
    if (!container) return;
    
    container.innerHTML = `
      <div class="col-12">
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle"></i> 
          Die Spezialpreise-Tabelle wurde noch nicht erstellt. 
          Bitte führe das SQL-Script aus um die Tabelle zu erstellen.
        </div>
      </div>
    `;
  }
  
  // Event Listener für Spezialpreise speichern
  document.getElementById('btnSaveSpezialpreise').addEventListener('click', function() {
    const spinner = document.getElementById('saveSpezialpreiseSpinner');
    const btn = this;
    spinner.classList.remove('d-none');
    btn.disabled = true;
    
    const updates = [];
    document.querySelectorAll('.spezialpreis-input').forEach(input => {
      const typ = input.dataset.typ;
      const einheit = input.dataset.einheit;
      const value = parseFloat(input.value) || 0;
      const price_cents = einheit === 'Rp.' ? Math.round(value) : Math.round(value * 100);
      
      updates.push({
        typ: typ,
        price_cents: price_cents
      });
    });
    
    // Speichere alle Änderungen
    Promise.all(updates.map(update => {
      return fetch(`${API}?action=update_spezialpreis`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('[name="csrf_token"]').value
        },
        body: JSON.stringify(update)
      }).then(r => r.json());
    }))
    .then(results => {
      showToast('Spezialpreise erfolgreich gespeichert', 'success');
      // Lade Preise neu für die Anwendung
      loadSpezialpreise();
      // Aktualisiere die Munitionspreise in der Anzeige
      setTimeout(() => {
        updateMunitionPreise();
        recalcTotals();
      }, 500);
    })
    .catch(err => {
      console.error('Error saving:', err);
      showToast('Fehler beim Speichern der Spezialpreise', 'danger');
    })
    .finally(() => {
      spinner.classList.add('d-none');
      btn.disabled = false;
    });
  });
  
  function loadAllStiche() {
    fetch(`${API}?action=get_stich_definitions`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          ALL_STICHE = data.data;
          renderAdminTable();
        }
      })
      .catch(err => console.error('Error loading stiche:', err));
  }
  
  function renderAdminTable() {
    const tbody = document.getElementById('adminTableBody');
    tbody.innerHTML = '';
    
    ALL_STICHE.forEach(stich => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${stich.sort_order}</td>
        <td><strong>${stich.name}</strong><br><small class="text-muted">${stich.code}</small></td>
        <td>${stich.shots}</td>
        <td>${fmtCHF(stich.price_cents)}</td>
        <td>
          ${stich.active == 1 
            ? '<span class="badge bg-success">Aktiv</span>' 
            : '<span class="badge bg-secondary">Inaktiv</span>'}
        </td>
        <td>
          <button class="btn btn-sm btn-outline-primary" onclick="openEditStichDialog(${stich.id})">
            <i class="bi bi-pencil"></i>
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }
  
  window.openEditStichDialog = function(id) {
    const stich = id ? ALL_STICHE.find(s => s.id == id) : null;
    
    document.getElementById('editModalTitle').textContent = stich ? 'Stich bearbeiten' : 'Neuer Stich';
    document.getElementById('editStichId').value = id || '';
    
    if (stich) {
      document.getElementById('editStichCode').value = stich.code;
      document.getElementById('editStichCode').disabled = true; // Code nicht änderbar bei bestehenden
      document.getElementById('editStichName').value = stich.name;
      document.getElementById('editStichShots').value = stich.shots;
      document.getElementById('editStichPrice').value = (stich.price_cents / 100).toFixed(2);
      document.getElementById('editStichSort').value = stich.sort_order;
      document.getElementById('editStichActive').checked = stich.active == 1;
    } else {
      document.getElementById('editStichForm').reset();
      document.getElementById('editStichCode').disabled = false;
      document.getElementById('editStichSort').value = '100';
      document.getElementById('editStichActive').checked = true;
    }
    
    editStichModal.show();
  }
  
  function saveStichDefinition() {
    const id = document.getElementById('editStichId').value;
    
    // Daten vorbereiten
    const formData = {
      name: document.getElementById('editStichName').value,
      shots: parseInt(document.getElementById('editStichShots').value),
      price_cents: Math.round(parseFloat(document.getElementById('editStichPrice').value) * 100),
      sort_order: parseInt(document.getElementById('editStichSort').value),
      active: document.getElementById('editStichActive').checked ? 1 : 0
    };
    
    // Bei neuem Stich auch Code mitschicken
    if (!id) {
      formData.code = document.getElementById('editStichCode').value;
    } else {
      formData.id = id;
    }
    
    const spinner = document.getElementById('editStichSpinner');
    const btn = document.getElementById('btnSaveStich');
    spinner.classList.remove('d-none');
    btn.disabled = true;
    
    // URL mit action als Query-Parameter
    const url = `${API}?action=update_stich_definition`;
    
    fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('[name="csrf_token"]').value
      },
      body: JSON.stringify(formData)
    })
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        editStichModal.hide();
        loadAllStiche(); // Admin-Tabelle neu laden
        loadStiche(); // Hauptansicht neu laden
        loadErfassteStiche(); // Tabelle aktualisieren
        showToast('Erfolgreich gespeichert', 'success');
      } else {
        showToast('Fehler: ' + result.message, 'danger');
      }
    })
    .catch(err => {
      console.error('Save error:', err);
      showToast('Netzwerkfehler beim Speichern', 'danger');
    })
    .finally(() => {
      spinner.classList.add('d-none');
      btn.disabled = false;
    });
  }
  
  // Verbesserte Toast-Funktion
  function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toastId = 'toast-' + Date.now();
    
    const toastHtml = `
      <div id="${toastId}" class="toast align-items-center text-white bg-${type === 'danger' ? 'danger' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info'} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">
            ${message}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    `;
    
    container.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
      autohide: true,
      delay: 4000
    });
    
    toast.show();
    
    // Entferne Toast nach dem Ausblenden
    toastElement.addEventListener('hidden.bs.toast', () => {
      toastElement.remove();
    });
  }
  
  // Globale Variablen für Lösch-Bestätigung
  let deleteConfirmModal = null;
  let pendingDeleteData = null;
  
  // Zeige Lösch-Bestätigungsmodal
  function showDeleteConfirmation(entityId, entityTyp, entityName, jahr) {
    pendingDeleteData = { entityId, entityTyp, entityName, jahr };
    
    const message = `Sie sind dabei, alle Stiche und Munitionsbestellungen für <strong>${entityName}</strong> im Jahr ${jahr} zu löschen.`;
    document.getElementById('deleteConfirmMessage').innerHTML = message;
    
    if (!deleteConfirmModal) {
      deleteConfirmModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    }
    deleteConfirmModal.show();
  }
  
  // Event Listener für Bestätigungsbutton
  document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (pendingDeleteData) {
      deleteConfirmModal.hide();
      deleteSelection(pendingDeleteData.entityId, pendingDeleteData.entityTyp, pendingDeleteData.entityName, pendingDeleteData.jahr);
      pendingDeleteData = null;
    }
  });
  
  // PDF Generation
  document.getElementById('btnGeneratePDF').addEventListener('click', function() {
    const jahr = document.getElementById('yearSelect').value;
    const btn = this;
    const originalText = btn.innerHTML;
    
    // Zeige Ladeanimation
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...';
    
    // Rufe PDF-Generator auf
    fetch(`endschloesen/generate_pdf_endschloesen.php?action=generate_pdf&jahr=${jahr}`)
      .then(response => response.json())
      .then(data => {
        if (data.pdf_link) {
          // Öffne PDF in neuem Tab
          window.open(data.pdf_link, '_blank');
          showToast('PDF wurde erfolgreich generiert', 'success');
        } else if (data.error) {
          showToast('Fehler: ' + data.error, 'danger');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showToast('Fehler beim Generieren des PDFs', 'danger');
      })
      .finally(() => {
        // Button zurücksetzen
        btn.disabled = false;
        btn.innerHTML = originalText;
      });
  });
  
  // Collapse Event Listener für Chevron-Animation
  document.getElementById('yearCollapse').addEventListener('shown.bs.collapse', function() {
    document.getElementById('yearChevron').className = 'bi bi-chevron-down me-1';
  });
  document.getElementById('yearCollapse').addEventListener('hidden.bs.collapse', function() {
    document.getElementById('yearChevron').className = 'bi bi-chevron-right me-1';
  });
  
  document.getElementById('munitionCollapse').addEventListener('shown.bs.collapse', function() {
    document.getElementById('munitionChevron').className = 'bi bi-chevron-down me-1';
  });
  document.getElementById('munitionCollapse').addEventListener('hidden.bs.collapse', function() {
    document.getElementById('munitionChevron').className = 'bi bi-chevron-right me-1';
  });
  
  // Init
  document.addEventListener('DOMContentLoaded', function() {
    initAdminModals();
    initZusatzSchuesse();
    
    // Bootstrap Collapse initialisieren
    const yearCollapse = new bootstrap.Collapse(document.getElementById('yearCollapse'), {
      toggle: false
    });
    const munitionCollapse = new bootstrap.Collapse(document.getElementById('munitionCollapse'), {
      toggle: false
    });
  });
  
  populateYearSelect();
  loadMitglieder();
  loadStiche();
  
  // Lade initial die erfassten Stiche für das ausgewählte Jahr
  setTimeout(() => {
    loadErfassteStiche();
  }, 500);
//tiche();
})();
</script>

<?php include 'footer.inc.php'; ?>



