<?php
// jsendschloesen.php – JS-Endschiessen Erfassung (nur Gäste/Jungschützen)
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in jsendschloesen.php: " . $e->getMessage());
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

<div class="container-fluid">
<div class="row">
  <div class="col-xl-6 col-lg-7 col-md-9 col-12 ps-0">
    <div class="main-content-wrapper">
      <div class="row mb-4 d-none d-md-flex">
        <div class="col-md-12">
          <h2 class="h4 mb-0" style="color: var(--secondary-color);">
            <i class="bi bi-person-badge me-2"></i>
            JS-Endschiessen – Jungschützen erfassen
          </h2>
        </div>
      </div>

      <div class="content-background">
        <form id="stichForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <!-- Jahr -->
          <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
            <div class="d-flex align-items-center gap-2">
              <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                <i class="bi bi-calendar3 me-1"></i>Jahr:
              </label>
              <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
            </div>
            <span class="badge bg-info" id="paketPreisBadge">Festes Paket: CHF 75.00</span>
          </div>

          <!-- Jungschütze erfassen -->
          <div class="mb-3">
            <label class="form-label fw-bold mb-1">
              <i class="bi bi-person-plus"></i> Jungschütze
            </label>
            <div class="row g-2">
              <div class="col-md-6">
                <input type="text" class="form-control" id="gastVorname" placeholder="Vorname *" required>
              </div>
              <div class="col-md-6">
                <input type="text" class="form-control" id="gastNachname" placeholder="Nachname *" required>
              </div>
            </div>
            <div class="row g-2 mt-2">
              <div class="col-md-6">
                <input type="date" class="form-control" id="gastGeburtsdatum" placeholder="Geburtsdatum *" required>
                <small class="text-muted">Geburtsdatum</small>
              </div>
              <div class="col-md-6">
                <!-- Platzhalter für Symmetrie -->
              </div>
            </div>
          </div>

          <!-- Festes Paket Info -->
          <div class="alert alert-success mb-3">
            <h6 class="alert-heading mb-2">
              <i class="bi bi-check-circle"></i> Festes JS-Paket (automatisch ausgewählt)
            </h6>
            <div class="row">
              <div class="col-md-6">
                <ul class="mb-0">
                  <li>Endstich (10 Schuss)</li>
                  <li>Probeschüsse (3 Schuss)</li>
                </ul>
              </div>
              <div class="col-md-6">
                <ul class="mb-0">
                  <li>Schwini (8 Schuss)</li>
                  <li>Zabigstich (6 Schuss)</li>
                </ul>
              </div>
            </div>
            <div class="mt-2">
              <strong id="paketInfo">Total: <span id="totalSchusse">27</span> Schuss = CHF 75.00</strong>
            </div>
          </div>
          
          <!-- Zahlungsmethode -->
          <div class="mb-3">
            <h6 class="mb-2"><i class="bi bi-credit-card"></i> Zahlungsmethode</h6>
            <div class="btn-group w-100" role="group">
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
          
          <!-- Zusätzliche Munition -->
          <div class="mt-3 p-2 bg-light rounded">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0" role="button" data-bs-toggle="collapse" data-bs-target="#munitionCollapse" aria-expanded="false" style="cursor: pointer;">
                <i class="bi bi-chevron-right me-1" id="munitionChevron"></i>
                <i class="bi bi-plus-circle"></i> Zusätzliche Munition
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
                            <strong>60 Schuss GP11</strong> <span class="text-muted">(CHF 36.00)</span>
                          </label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-check">
                          <input class="form-check-input zusatz-check" type="checkbox" 
                                 id="zusatz_gp90_50" data-typ="GP90_50" data-anzahl="50">
                          <label class="form-check-label small" for="zusatz_gp90_50">
                            <strong>50 Schuss GP90</strong> <span class="text-muted">(CHF 30.00)</span>
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
                          <span class="input-group-text">GP11</span>
                          <input type="number" class="form-control zusatz-custom" 
                                 id="zusatz_gp11_custom" data-typ="GP11_CUSTOM" 
                                 min="0" max="500" step="10" value="0">
                          <span class="input-group-text" id="preis_gp11_custom">CHF 0.00</span>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="input-group input-group-sm">
                          <span class="input-group-text">GP90</span>
                          <input type="number" class="form-control zusatz-custom" 
                                 id="zusatz_gp90_custom" data-typ="GP90_CUSTOM" 
                                 min="0" max="500" step="10" value="0">
                          <span class="input-group-text" id="preis_gp90_custom">CHF 0.00</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="mt-2 p-2 bg-white rounded">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="small"><strong>Total Munition:</strong></span>
                  <div class="small">
                    <span id="zusatz_total_schuss">0</span> Schuss = 
                    <strong id="zusatz_total_preis">CHF 0.00</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Buttons -->
          <div class="d-flex justify-content-between gap-2 mt-3 mb-3">
            <button type="button" id="btnGeneratePDF" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-file-earmark-pdf"></i> Liste drucken
            </button>
            <div class="d-flex gap-2">
              <button type="button" id="btnReset" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i> Zurücksetzen
              </button>
              <button type="submit" id="btnSave" class="btn btn-success btn-sm">
                <span class="spinner-border spinner-border-sm me-1 d-none" id="saveSpinner"></span>
                <i class="bi bi-save"></i> Speichern
              </button>
            </div>
          </div>
          <div id="saveFeedback" class="text-end small text-muted mt-2"></div>
          
          <!-- Erfasste Jungschützen Tabelle -->
          <div class="mt-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0"><i class="bi bi-table"></i> Erfasste Jungschützen</h6>
              <button type="button" class="btn btn-sm btn-outline-success" id="btnExport">
                <i class="bi bi-download"></i> Export CSV
              </button>
            </div>
            <!-- Desktop: Tabelle -->
            <div class="desktop-table-container">
              <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered" id="erfassteTabelle">
                  <thead class="table-light">
                    <tr>
                      <th>Name</th>
                      <th>Vorname</th>
                      <th>Geburtsdatum</th>
                      <th>Alter</th>
                      <th class="text-center">Munition</th>
                      <th class="text-center">Bezahlt</th>
                      <th class="text-end">Total</th>
                      <th style="width: 80px;"></th>
                    </tr>
                  </thead>
                  <tbody id="erfassteTableBody">
                    <tr>
                      <td colspan="7" class="text-muted text-center">Noch keine Jungschützen erfasst</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Mobile: Cards -->
            <div class="mobile-cards-container" id="mobileCardsJsendsch">
              <div class="mobile-search">
                <div class="position-relative">
                  <i class="bi bi-search search-icon"></i>
                  <input type="text" class="form-control" placeholder="Suchen..."
                         oninput="filterMobileJsendsch(this)">
                </div>
              </div>
              <div class="mobile-cards-scroll">
                <!-- Cards werden per JavaScript generiert -->
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Sidebar: Admin -->
  <div class="col-xl-2 col-lg-3 col-md-3 col-12">
    <div class="sidebar-wrapper">
      <?php 
      // Admin-Button für Stich-Verwaltung
      $isAdmin = true; // Für Testing, später anpassen an dein Auth-System
      if ($isAdmin): 
      ?>
      <div class="content-background">
        <button class="btn btn-outline-secondary btn-sm w-100" id="btnAdminSettings">
          <i class="bi bi-gear"></i> JS-Paket Definition
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Admin Modal für Stich-Verwaltung -->
<div class="modal fade" id="adminModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-gear-fill"></i> JS-Paket verwalten</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Paket-Preis -->
        <div class="card mb-3">
          <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="bi bi-currency-exchange"></i> Paket-Preis</h6>
          </div>
          <div class="card-body">
            <div class="row align-items-center">
              <div class="col-md-8">
                <label for="paketPreis" class="form-label">Preis für das JS-Paket (CHF)</label>
                <div class="input-group">
                  <span class="input-group-text">CHF</span>
                  <input type="number" class="form-control" id="paketPreis" min="0" step="0.01" value="75.00">
                </div>
              </div>
              <div class="col-md-4">
                <button class="btn btn-primary w-100" id="btnSavePaketPreis">
                  <i class="bi bi-save"></i> Preis speichern
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Stiche im Paket -->
        <div class="card">
          <div class="card-header bg-secondary text-white">
            <h6 class="mb-0"><i class="bi bi-card-list"></i> Stiche im JS-Paket</h6>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-sm table-hover">
                <thead>
                  <tr>
                    <th>Stich</th>
                    <th width="150">Anzahl Schuss</th>
                    <th width="100"></th>
                  </tr>
                </thead>
                <tbody id="adminStichTableBody">
                  <!-- Wird via JS gefüllt -->
                </tbody>
              </table>
            </div>
            <div class="alert alert-info mt-3 mb-0">
              <i class="bi bi-info-circle"></i> 
              <small>Diese Stiche sind fest im JS-Paket enthalten. Die Schussanzahl kann angepasst werden.</small>
            </div>
          </div>
        </div>
      </div>
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

<style>
  /* JS-spezifische Styles */
  .sidebar-wrapper .content-background {
    padding: 0.75rem;
  }
  
  #erfassteTabelle tbody tr:hover {
    background-color: var(--bs-gray-100);
  }
  
  #erfassteTabelle th {
    font-size: 0.8rem;
    vertical-align: middle;
    padding: 0.25rem;
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
  
  /* Badge Animation */
  #munitionBadge {
    transition: all 0.3s ease;
  }
  
  /* Collapse Header Hover */
  h6[data-bs-toggle="collapse"]:hover {
    color: var(--bs-primary);
  }

@media (max-width: 767.98px) {
  .desktop-table-container { display: none !important; }
  .mobile-cards-container { display: flex !important; }

  .mobile-card-detail-row {
    padding: 0.75rem 0 !important;
    border-bottom: 1px solid #f1f5f9 !important;
  }

  .mobile-card-detail-label {
    font-size: 0.875rem !important;
    color: #64748b !important;
    font-weight: 500 !important;
  }

  .mobile-card-detail-value {
    font-size: 1rem !important;
    color: #1e293b !important;
  }

  .mobile-card-body .btn {
    min-height: 48px !important;
    font-size: 1rem !important;
  }
}
</style>

<script>
(function(){
  // === Konfiguration ===
  const API = 'jsendschloesen/jsendschloesen_api.php';
  const MUNITION_PREIS_PRO_SCHUSS = 60; // 60 Rappen
  let PAKET_PREIS = 7500; // CHF 75.00 in Rappen (wird dynamisch geladen)
  
  // Feste Stich-IDs für JS-Paket (müssen in DB existieren)
  const JS_PAKET_STICHE = {
    'END': null,        // Endstich (10 Schuss)
    'PROBE': null,      // Probeschüsse (3 Schuss)
    'SCHWINI_P1': null, // Schwini (8 Schuss) - nur noch EINE Passe!
    'ZABIG': null       // Zabigstich (6 Schuss)
  };

  // Admin Modal
  let adminModal = null;

  // === Helpers ===
  function fmtCHF(cents){ 
    const numCents = Number(cents) || 0;
    const v = numCents/100;
    return 'CHF ' + v.toFixed(2); 
  }

  function populateYearSelect(){
    const sel = document.getElementById('yearSelect');
    sel.innerHTML = '';
    const currentYear = new Date().getFullYear();
    for(let y = currentYear; y >= currentYear - 3; y--){
      const opt = document.createElement('option');
      opt.value = String(y);
      opt.textContent = String(y);
      if (y === currentYear) opt.selected = true;
      sel.appendChild(opt);
    }
  }

  function validateForm(){
    const vorname = document.getElementById('gastVorname').value.trim();
    const nachname = document.getElementById('gastNachname').value.trim();
    const geburtsdatum = document.getElementById('gastGeburtsdatum').value;
    
    if (!vorname || !nachname) {
      msvToast('Bitte Vor- und Nachname eingeben', 'warning');
      return false;
    }
    
    if (!geburtsdatum) {
      msvToast('Bitte Geburtsdatum eingeben', 'warning');
      return false;
    }
    
    // Prüfe Alter (muss zwischen 10 und 20 Jahren sein für JS)
    const geb = new Date(geburtsdatum);
    const heute = new Date();
    const alter = Math.floor((heute - geb) / (365.25 * 24 * 60 * 60 * 1000));
    
    if (alter < 10 || alter > 20) {
      msvToast('Jungschützen müssen zwischen 10 und 20 Jahre alt sein', 'warning');
      return false;
    }
    
    return true;
  }

  function resetForm(){
    document.getElementById('gastVorname').value = '';
    document.getElementById('gastNachname').value = '';
    document.getElementById('gastGeburtsdatum').value = '';
    document.getElementById('zahlung_bar').checked = true;
    resetZusatzSchuesse();
    updateTotals();
  }

  // === Munition Functions ===
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
        const preis = anzahl * MUNITION_PREIS_PRO_SCHUSS;
        
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

  function recalcZusatzTotal() {
    let zusatzSchuss = 0;
    let zusatzPreis = 0;
    
    // Standard-Pakete
    document.querySelectorAll('.zusatz-check:checked').forEach(cb => {
      const anzahl = parseInt(cb.dataset.anzahl) || 0;
      zusatzSchuss += anzahl;
      zusatzPreis += anzahl * MUNITION_PREIS_PRO_SCHUSS;
    });
    
    // Individuelle Anzahl
    document.querySelectorAll('.zusatz-custom').forEach(input => {
      const anzahl = parseInt(input.value) || 0;
      if (anzahl > 0) {
        zusatzSchuss += anzahl;
        zusatzPreis += anzahl * MUNITION_PREIS_PRO_SCHUSS;
      }
    });
    
    // Update Anzeige
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
    
    updateTotals();
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

  function getMunitionData() {
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
    
    return zusatz_schuesse;
  }

  // === Admin Functions ===
  function initAdminModal() {
    const adminBtn = document.getElementById('btnAdminSettings');
    if (adminBtn) {
      adminModal = new bootstrap.Modal(document.getElementById('adminModal'));
      
      adminBtn.addEventListener('click', openAdminModal);
      document.getElementById('btnSavePaketPreis').addEventListener('click', savePaketPreis);
    }
  }
  
  function openAdminModal() {
    // Lade aktuellen Paket-Preis
    fetch(`${API}?action=get_js_config`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          // Setze Paket-Preis
          if (data.paket_preis) {
            document.getElementById('paketPreis').value = (data.paket_preis / 100).toFixed(2);
            PAKET_PREIS = data.paket_preis;
          }
          
          // Lade Stiche
          renderAdminStiche(data.stiche);
        }
      });
    
    adminModal.show();
  }
  
  function renderAdminStiche(stiche) {
    const tbody = document.getElementById('adminStichTableBody');
    tbody.innerHTML = '';
    
    stiche.forEach(stich => {
      const tr = document.createElement('tr');
      const shots = parseInt(stich.shots) || 10; // Stelle sicher dass es eine Zahl ist
      tr.innerHTML = `
        <td>
          <strong>${stich.name}</strong>
          <br><small class="text-muted">Code: ${stich.code}</small>
        </td>
        <td>
          <input type="number" class="form-control form-control-sm stich-shots-input" 
                 data-id="${stich.id}" value="${shots}" min="1" max="100">
        </td>
        <td>
          <button class="btn btn-sm btn-primary btn-save-stich" data-id="${stich.id}">
            <i class="bi bi-save"></i> Speichern
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });
    
    // Event Listener für Speichern-Buttons
    document.querySelectorAll('.btn-save-stich').forEach(btn => {
      btn.addEventListener('click', function() {
        const stichId = this.dataset.id;
        const input = document.querySelector(`.stich-shots-input[data-id="${stichId}"]`);
        const shots = parseInt(input.value);
        
        if (shots > 0) {
          saveStichShots(stichId, shots, this);
        }
      });
    });
  }
  
  function saveStichShots(stichId, shots, btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    fetch(`${API}?action=update_js_stich`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('[name="csrf_token"]').value
      },
      body: JSON.stringify({
        stich_id: stichId,
        shots: shots
      })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        msvToast('Schussanzahl aktualisiert', 'success');
        updatePaketInfo();
      } else {
        msvToast('Fehler beim Speichern', 'danger');
      }
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = originalText;
    });
  }
  
  function savePaketPreis() {
    const preis = parseFloat(document.getElementById('paketPreis').value);
    if (isNaN(preis) || preis < 0) {
      msvToast('Ungültiger Preis', 'warning');
      return;
    }
    
    const preisCents = Math.round(preis * 100);
    const btn = document.getElementById('btnSavePaketPreis');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Speichere...';
    
    fetch(`${API}?action=update_js_paket_preis`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('[name="csrf_token"]').value
      },
      body: JSON.stringify({
        preis_cents: preisCents
      })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        PAKET_PREIS = preisCents;
        document.getElementById('paketPreisBadge').textContent = 'Festes Paket: ' + fmtCHF(preisCents);
        updatePaketInfo();
        msvToast('Paket-Preis gespeichert', 'success');
        loadErfassteJS(); // Tabelle neu laden mit neuen Preisen
      } else {
        msvToast('Fehler beim Speichern', 'danger');
      }
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = originalText;
    });
  }
  
  function updatePaketInfo() {
    // Lade aktuelle Stich-Infos
    fetch(`${API}?action=get_js_stiche`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          let totalShots = 0;
          let sticheText = [];
          
          data.data.forEach(stich => {
            // Konvertiere shots zu Nummer um sicherzustellen dass es addiert wird
            const shots = parseInt(stich.shots) || 0;
            totalShots += shots;
            sticheText.push(`${stich.name} (${shots} Schuss)`);
            
            // Update JS_PAKET_STICHE
            if (JS_PAKET_STICHE.hasOwnProperty(stich.code)) {
              JS_PAKET_STICHE[stich.code] = stich.id;
            }
          });
          
          // Update Paket-Info Anzeige
          document.getElementById('paketInfo').innerHTML = 
            `Total: <span id="totalSchusse">${totalShots}</span> Schuss = ${fmtCHF(PAKET_PREIS)}`;
          
          // Update Liste in der Info-Box
          const listItems = document.querySelectorAll('.alert-success ul li');
          if (listItems.length >= 4 && sticheText.length >= 4) {
            listItems[0].textContent = sticheText[0] || 'Endstich';
            listItems[1].textContent = sticheText[1] || 'Probeschüsse';
            listItems[2].textContent = sticheText[2] || 'Schwini';
            listItems[3].textContent = sticheText[3] || 'Zabigstich';
          }
        }
      });
  }

  // === Daten laden ===
  function loadStichIds() {
    // Lade die Stich-IDs für das JS-Paket
    fetch(`${API}?action=get_js_stiche`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          data.data.forEach(stich => {
            if (JS_PAKET_STICHE.hasOwnProperty(stich.code)) {
              JS_PAKET_STICHE[stich.code] = stich.id;
            }
          });
        }
      })
      .catch(err => console.error('Error loading stich IDs:', err));
  }

  function loadErfassteJS() {
    const jahr = document.getElementById('yearSelect').value;
    
    fetch(`${API}?action=get_year_js&jahr=${encodeURIComponent(jahr)}`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          renderErfassteTabelle(data.data);
          updateTotals();
        }
      })
      .catch(err => {
        console.error('Error loading data:', err);
      });
  }

  function renderErfassteTabelle(data) {
    const tbody = document.getElementById('erfassteTableBody');
    
    if (!data || data.length === 0) {
    tbody.innerHTML = `
    <tr>
    <td colspan="7" class="text-muted text-center">Noch keine Jungschützen für dieses Jahr erfasst</td>
    </tr>`;
    return;
    }
    
    // Filtere nur Einträge mit Geburtsdatum
    const filteredData = data.filter(entry => entry.geburtsdatum);
    
    if (filteredData.length === 0) {
    tbody.innerHTML = `
    <tr>
    <td colspan="7" class="text-muted text-center">Keine Jungschützen mit Geburtsdatum erfasst</td>
    </tr>`;
    return;
    }
    
    tbody.innerHTML = '';
    
    let totalJS = 0;
    let totalPakete = 0;
    let totalMunition = 0;
    
    filteredData.forEach(entry => {
      const tr = document.createElement('tr');
      
      // Munition formatieren
      let gp11Total = 0;
      let gp90Total = 0;
      
      // Hole die aktuelle Schusszahl aus dem Paket-Info Element
      const totalSchussElement = document.getElementById('totalSchusse');
      const paketSchusse = totalSchussElement ? totalSchussElement.textContent : '32';
      let paketText = `JS-Paket (${paketSchusse} Schuss)`; // Festes Paket ist immer dabei
      
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
      
      let munitionText = paketText;
      if (gp11Total > 0 || gp90Total > 0) {
        const parts = [paketText];
        if (gp11Total > 0) parts.push(`GP11: ${gp11Total} Schuss`);
        if (gp90Total > 0) parts.push(`GP90: ${gp90Total} Schuss`);
        munitionText = parts.join('<br>');
      }
      
      // Zahlungsmethode Icon
      let zahlungIcon = '';
      if (entry.zahlungsmethode === 'karte') {
        zahlungIcon = '<i class="bi bi-credit-card-2-back text-primary" title="Karte"></i>';
      } else {
        zahlungIcon = '<i class="bi bi-cash text-success" title="Bar"></i>';
      }
      
      // Berechne Alter aus Geburtsdatum
      let alterText = '-';
      let geburtsdatumText = '-';
      if (entry.geburtsdatum) {
      const geb = new Date(entry.geburtsdatum);
      geburtsdatumText = geb.toLocaleDateString('de-CH');
      const heute = new Date();
      const alter = Math.floor((heute - geb) / (365.25 * 24 * 60 * 60 * 1000));
      alterText = alter + ' Jahre';
      }
        
        tr.innerHTML = `
          <td>${entry.nachname || ''}</td>
          <td>${entry.vorname || ''}</td>
          <td class="text-center">${geburtsdatumText}</td>
          <td class="text-center">${alterText}</td>
          <td class="text-center small">${munitionText}</td>
          <td class="text-center">${zahlungIcon}</td>
          <td class="text-end"><strong>${fmtCHF(entry.total_price || 0)}</strong></td>
          <td class="text-center">
          <div class="btn-group btn-group-sm" role="group">
            <button class="btn btn-outline-secondary btn-edit-js" 
                    data-id="${entry.id}"
                    title="Bearbeiten">
              <i class="bi bi-pencil-square"></i>
            </button>
            <button class="btn btn-outline-danger btn-delete-js" 
                    data-id="${entry.id}"
                    data-name="${entry.vorname} ${entry.nachname}"
                    title="Löschen">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>`;
      
      tbody.appendChild(tr);
    });

    // Mobile Cards generieren
    if (typeof buildMobileJsendschCards === 'function') {
      buildMobileJsendschCards();
    }
  }

  function updateTotals() {
    // Funktion entfernt - Totals werden nicht mehr angezeigt
  }

  // === Events ===
  document.getElementById('btnReset').addEventListener('click', resetForm);
  
  document.getElementById('yearSelect').addEventListener('change', loadErfassteJS);
  
  document.getElementById('stichForm').addEventListener('submit', function(ev) {
    ev.preventDefault();
    
    if (!validateForm()) {
      return;
    }
    
    saveJS();
  });

  function saveJS() {
    const jahr = document.getElementById('yearSelect').value;
    const vorname = document.getElementById('gastVorname').value.trim();
    const nachname = document.getElementById('gastNachname').value.trim();
    const geburtsdatum = document.getElementById('gastGeburtsdatum').value;
    const zahlungsmethode = document.querySelector('input[name="zahlungsmethode"]:checked').value;
    const zusatz_schuesse = getMunitionData();
    
    // Sammle Stich-IDs für das feste Paket (END, PROBE, SCHWINI_P1, ZABIG)
    const stiche = Object.values(JS_PAKET_STICHE).filter(id => id !== null);
    
    if (stiche.length !== 4) {
      msvToast('Fehler: JS-Paket Stiche nicht korrekt konfiguriert (benötigt: Endstich, Probe, Schwini, Zabig)', 'danger');
      return;
    }
    
    // Bestimme die Action (update oder save)
    const isEditing = window.editingJSId !== null && window.editingJSId !== undefined;
    const action = isEditing ? 'update_js' : 'save_js';
    
    // Zeige Spinner
    const spinner = document.getElementById('saveSpinner');
    const btn = document.getElementById('btnSave');
    spinner.classList.remove('d-none');
    btn.disabled = true;
    
    // Sende an API
    const url = `${API}?action=${action}`;
    
    const requestData = {
      jahr: jahr,
      vorname: vorname,
      nachname: nachname,
      geburtsdatum: geburtsdatum,
      stiche: stiche,
      zahlungsmethode: zahlungsmethode,
      zusatz_schuesse: zusatz_schuesse
    };
    
    // Füge ID hinzu wenn Bearbeitung
    if (isEditing) {
      requestData.id = window.editingJSId;
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
        fb.className = 'text-end small text-success mt-2';
        fb.textContent = '✓ ' + (data.message || (isEditing ? 'Aktualisiert' : 'Gespeichert'));
        
        // Toast-Meldung anzeigen
        if (isEditing) {
          msvToast('Jungschütze wurde erfolgreich aktualisiert', 'success');
        } else {
          msvToast('Jungschütze wurde erfolgreich gespeichert', 'success');
        }
        
        // Tabelle aktualisieren
        loadErfassteJS();
        
        // Form zurücksetzen und Bearbeitungsmodus beenden
        setTimeout(() => {
          if (isEditing) {
            cancelEdit(false); // false = keine Meldung anzeigen
          } else {
            resetForm();
          }
          fb.textContent = '';
        }, 1500);
      } else {
        fb.className = 'text-end small text-danger mt-2';
        fb.textContent = '✗ ' + (data.message || 'Fehler beim Speichern');
      }
    })
    .catch(err => {
      console.error('Save error:', err);
      const fb = document.getElementById('saveFeedback');
      fb.className = 'text-end small text-danger mt-2';
      fb.textContent = '✗ Netzwerkfehler';
    })
    .finally(() => {
      spinner.classList.add('d-none');
      btn.disabled = false;
    });
  }

  // Event Listener für Bearbeiten/Löschen
  document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-edit-js')) {
      e.preventDefault();
      const btn = e.target.closest('.btn-edit-js');
      const id = btn.dataset.id;
      editJS(id);
    }
    
    if (e.target.closest('.btn-delete-js')) {
      e.preventDefault();
      const btn = e.target.closest('.btn-delete-js');
      const id = btn.dataset.id;
      const name = btn.dataset.name;
      showDeleteConfirmation(id, name);
    }
  });

  // Bearbeitungsfunktion
  function editJS(id) {
    const jahr = document.getElementById('yearSelect').value;
    
    // Lade die Daten des Jungschützen
    fetch(`${API}?action=get_js_details&id=${id}&jahr=${jahr}`)
      .then(r => r.json())
      .then(data => {
        if (data.success && data.data) {
          const js = data.data;
          
          // Fülle das Formular mit den Daten
          document.getElementById('gastVorname').value = js.vorname || '';
          document.getElementById('gastNachname').value = js.nachname || '';
          document.getElementById('gastGeburtsdatum').value = js.geburtsdatum || '';
          
          // Zahlungsmethode
          if (js.zahlungsmethode === 'karte') {
            document.getElementById('zahlung_karte').checked = true;
          } else {
            document.getElementById('zahlung_bar').checked = true;
          }
          
          // Zusätzliche Munition
          resetZusatzSchuesse();
          if (js.zusatz_schuesse && Array.isArray(js.zusatz_schuesse)) {
            js.zusatz_schuesse.forEach(z => {
              if (z.typ === 'GP11_60') {
                document.getElementById('zusatz_gp11_60').checked = true;
              } else if (z.typ === 'GP90_50') {
                document.getElementById('zusatz_gp90_50').checked = true;
              } else if (z.typ === 'GP11_CUSTOM') {
                document.getElementById('zusatz_gp11_custom').value = z.anzahl;
              } else if (z.typ === 'GP90_CUSTOM') {
                document.getElementById('zusatz_gp90_custom').value = z.anzahl;
              }
            });
          }
          recalcZusatzTotal();
          
          // Wechsle in den Bearbeitungsmodus
          window.editingJSId = id;
          
          // Ändere den Button-Text
          const saveBtn = document.getElementById('btnSave');
          const spinner = saveBtn.querySelector('#saveSpinner');
          saveBtn.innerHTML = '';
          if (spinner) {
            saveBtn.appendChild(spinner);
          } else {
            const newSpinner = document.createElement('span');
            newSpinner.className = 'spinner-border spinner-border-sm me-1 d-none';
            newSpinner.id = 'saveSpinner';
            saveBtn.appendChild(newSpinner);
          }
          const icon = document.createElement('i');
          icon.className = 'bi bi-save';
          saveBtn.appendChild(icon);
          saveBtn.appendChild(document.createTextNode(' Aktualisieren'));
          
          // Füge einen Abbrechen-Button hinzu, wenn nicht vorhanden
          if (!document.getElementById('btnCancelEdit')) {
            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.id = 'btnCancelEdit';
            cancelBtn.className = 'btn btn-outline-warning btn-sm';
            cancelBtn.innerHTML = '<i class="bi bi-x-circle"></i> Abbrechen';
            cancelBtn.onclick = cancelEdit;
            
            const resetBtn = document.getElementById('btnReset');
            resetBtn.parentNode.insertBefore(cancelBtn, resetBtn);
          }
          
          // Scrolle zum Formular
          document.querySelector('.content-background').scrollIntoView({ behavior: 'smooth' });
          
          // Zeige Hinweis
          msvToast('Bearbeitungsmodus - Daten geladen', 'info');
        } else {
          msvToast('Fehler beim Laden der Daten', 'error');
        }
      })
      .catch(err => {
        console.error('Error loading JS data:', err);
        msvToast('Fehler beim Laden der Daten', 'error');
      });
  }
  
  // Abbrechen der Bearbeitung
  function cancelEdit(showMessage = true) {
    window.editingJSId = null;
    resetForm();
    
    // Entferne den Abbrechen-Button
    const cancelBtn = document.getElementById('btnCancelEdit');
    if (cancelBtn) {
      cancelBtn.remove();
    }
    
    // Ändere den Button-Text zurück
    const saveBtn = document.getElementById('btnSave');
    const spinner = saveBtn.querySelector('#saveSpinner');
    saveBtn.innerHTML = '';
    if (spinner) {
      saveBtn.appendChild(spinner);
    } else {
      const newSpinner = document.createElement('span');
      newSpinner.className = 'spinner-border spinner-border-sm me-1 d-none';
      newSpinner.id = 'saveSpinner';
      saveBtn.appendChild(newSpinner);
    }
    const icon = document.createElement('i');
    icon.className = 'bi bi-save';
    saveBtn.appendChild(icon);
    saveBtn.appendChild(document.createTextNode(' Speichern'));
    
    if (showMessage) {
      msvToast('Bearbeitung abgebrochen', 'info');
    }
  }

  // Lösch-Funktionen
  let deleteConfirmModal = null;
  let pendingDeleteId = null;
  
  function showDeleteConfirmation(id, name) {
    pendingDeleteId = id;
    
    const message = `Sie sind dabei, den Jungschützen <strong>${name}</strong> zu löschen.`;
    document.getElementById('deleteConfirmMessage').innerHTML = message;
    
    if (!deleteConfirmModal) {
      deleteConfirmModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    }
    deleteConfirmModal.show();
  }
  
  document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (pendingDeleteId) {
      deleteConfirmModal.hide();
      deleteJS(pendingDeleteId);
      pendingDeleteId = null;
    }
  });
  
  function deleteJS(id) {
    const jahr = document.getElementById('yearSelect').value;
    
    fetch(`${API}?action=delete_js`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('[name="csrf_token"]').value
      },
      body: JSON.stringify({
        id: id,
        jahr: jahr
      })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        msvToast('Jungschütze wurde gelöscht', 'success');
        loadErfassteJS();
      } else {
        msvToast('Fehler beim Löschen: ' + (data.message || 'Unbekannter Fehler'), 'danger');
      }
    })
    .catch(err => {
      console.error('Delete error:', err);
      msvToast('Netzwerkfehler beim Löschen', 'danger');
    });
  }

  // Export CSV
  document.getElementById('btnExport').addEventListener('click', function() {
    const jahr = document.getElementById('yearSelect').value;
    window.location.href = `jsendschloesen/export_js.php?jahr=${jahr}`;
  });

  // PDF Generation
  document.getElementById('btnGeneratePDF').addEventListener('click', function() {
    const jahr = document.getElementById('yearSelect').value;
    const btn = this;
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...';
    
    fetch(`jsendschloesen/generate_pdf_js.php?action=generate_pdf&jahr=${jahr}`)
      .then(response => response.json())
      .then(data => {
        if (data.pdf_link) {
          window.open(data.pdf_link, '_blank');
          msvToast('PDF wurde erfolgreich generiert', 'success');
        } else if (data.error) {
          msvToast('Fehler: ' + data.error, 'danger');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        msvToast('Fehler beim Generieren des PDFs', 'danger');
      })
      .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
      });
  });

  // Collapse Event Listener
  document.getElementById('munitionCollapse').addEventListener('shown.bs.collapse', function() {
    document.getElementById('munitionChevron').className = 'bi bi-chevron-down me-1';
  });
  document.getElementById('munitionCollapse').addEventListener('hidden.bs.collapse', function() {
    document.getElementById('munitionChevron').className = 'bi bi-chevron-right me-1';
  });

  // Init
  document.addEventListener('DOMContentLoaded', function() {
    initAdminModal();
    initZusatzSchuesse();
    
    // Bootstrap Collapse initialisieren
    const munitionCollapse = new bootstrap.Collapse(document.getElementById('munitionCollapse'), {
      toggle: false
    });
  });

  // Mobile Cards für JS-Endschloesen
  function buildMobileJsendschCards() {
    const isMobile = window.matchMedia('(max-width: 767.98px)');
    if (!isMobile.matches) return;

    const table = document.getElementById('erfassteTabelle');
    const container = document.querySelector('#mobileCardsJsendsch .mobile-cards-scroll');
    if (!table || !container) return;

    const tbody = table.querySelector('tbody');
    if (!tbody) {
      container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
      return;
    }

    const rows = tbody.querySelectorAll('tr');
    if (rows.length === 0 || (rows.length === 1 && rows[0].cells.length === 1)) {
      container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
      return;
    }

    let html = '';
    rows.forEach((row, idx) => {
      const cells = Array.from(row.querySelectorAll('td'));
      if (cells.length < 7) return;

      const nachname = cells[0]?.textContent?.trim() || '';
      const vorname = cells[1]?.textContent?.trim() || '';
      const geburtsdatum = cells[2]?.textContent?.trim() || '-';
      const alter = cells[3]?.textContent?.trim() || '-';
      const munition = cells[4]?.innerHTML || '-';
      const bezahlt = cells[5]?.innerHTML || '-';
      const total = cells[6]?.textContent?.trim() || '-';

      // Action buttons
      const actionCell = cells[7];
      const editBtn = actionCell?.querySelector('[data-id]');
      const entityId = editBtn ? editBtn.dataset.id : '';

      html += `
      <div class="mobile-card" data-index="${idx}">
        <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
          <div>
            <div class="fw-bold">${nachname} ${vorname}</div>
            <small class="text-muted">Alter: ${alter} | Total: ${total}</small>
          </div>
          <i class="bi bi-chevron-down"></i>
        </div>
        <div class="mobile-card-body">
          <div class="mobile-card-detail-row">
            <span class="mobile-card-detail-label">Geburtsdatum</span>
            <span class="mobile-card-detail-value">${geburtsdatum}</span>
          </div>
          <div class="mobile-card-detail-row">
            <span class="mobile-card-detail-label">Alter</span>
            <span class="mobile-card-detail-value">${alter}</span>
          </div>
          <div class="mobile-card-detail-row">
            <span class="mobile-card-detail-label">Munition</span>
            <span class="mobile-card-detail-value">${munition}</span>
          </div>
          <div class="mobile-card-detail-row">
            <span class="mobile-card-detail-label">Bezahlt</span>
            <span class="mobile-card-detail-value">${bezahlt}</span>
          </div>
          <div class="mobile-card-detail-row">
            <span class="mobile-card-detail-label">Total</span>
            <span class="mobile-card-detail-value"><strong>${total}</strong></span>
          </div>
        </div>
      </div>`;
    });

    container.innerHTML = html;
  }

  window.filterMobileJsendsch = function(searchInput) {
    const query = searchInput.value.toLowerCase();
    const cards = document.querySelectorAll('#mobileCardsJsendsch .mobile-card');

    let visibleCount = 0;
    cards.forEach(card => {
      const text = card.textContent.toLowerCase();
      const isVisible = text.includes(query);
      card.style.display = isVisible ? '' : 'none';
      if (isVisible) visibleCount++;
    });

    const container = document.querySelector('#mobileCardsJsendsch .mobile-cards-scroll');
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
  };

  let wasDesktop = window.matchMedia('(min-width: 768px)').matches;
  window.addEventListener('resize', function() {
    const isNowDesktop = window.matchMedia('(min-width: 768px)').matches;
    if (wasDesktop && !isNowDesktop) {
      buildMobileJsendschCards();
    }
    wasDesktop = isNowDesktop;
  });

  populateYearSelect();
  loadStichIds();
  loadErfassteJS();

  // Lade initiale Konfiguration
  fetch(`${API}?action=get_js_config`)
    .then(r => r.json())
    .then(data => {
      if (data.success && data.paket_preis) {
        PAKET_PREIS = data.paket_preis;
        document.getElementById('paketPreisBadge').textContent = 'Festes Paket: ' + fmtCHF(PAKET_PREIS);
        updatePaketInfo();
      }
    });

})();
</script>

<?php include 'footer.inc.php'; ?>
