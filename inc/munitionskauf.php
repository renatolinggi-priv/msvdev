<?php
// munitionskauf.php - Munitionsbestellungen erfassen
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in munitionskauf.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

include 'header.inc.php';

// CSRF Token wird bereits in header.inc.php generiert
// Wir brauchen hier nichts mehr zu machen, nur sicherstellen dass es existiert
if (empty($_SESSION['csrf_token'])) {
    // Fallback, sollte normalerweise nicht nötig sein
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!-- Toast Container -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 11000">
  <div id="toastContainer"></div>
</div>

<div class="row">
  <div class="col-xl-6 col-lg-8 col-md-10 col-12 ps-0">
    <div class="main-content-wrapper">
      <div class="row mb-3">
        <div class="col-md-12">
          <h2 class="h5 mb-0" style="color: var(--secondary-color);">
            <i class="bi bi-cart-check me-2"></i>
            Munitionskauf erfassen
          </h2>
        </div>
      </div>

      <div class="content-background">
        <form id="munitionForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <!-- Jahr und Datum -->
          <div class="year-selection-card mb-3">
            <div class="row align-items-center g-2">
              <div class="col-lg-4 col-md-6">
                <label for="yearSelect" class="form-label fw-bold mb-1 small">
                  <i class="bi bi-calendar3 me-1"></i>Jahr:
                </label>
                <select id="yearSelect" class="form-select form-select-sm"></select>
              </div>
              <div class="col-lg-4 col-md-6">
                <label for="kaufDatum" class="form-label fw-bold mb-1 small">
                  <i class="bi bi-calendar-event me-1"></i>Kaufdatum:
                </label>
                <input type="date" id="kaufDatum" class="form-control form-control-sm" required>
              </div>
              <div class="col-lg-4 col-md-12">
                <label for="anlass" class="form-label fw-bold mb-1 small">
                  <i class="bi bi-tag me-1"></i>Anlass:
                </label>
                <input type="text" id="anlass" class="form-control form-control-sm" placeholder="z.B. Training">
              </div>
            </div>
          </div>

          <!-- Mitgliederauswahl -->
          <div class="mb-3">
            <label for="mitgliedSelect" class="form-label fw-bold mb-1 small"><i class="bi bi-person"></i> Mitglied / Gast</label>
            <div class="row g-2">
              <div class="col-lg-6 col-md-12">
                <select id="mitgliedSelect" class="form-select form-select-sm">
                  <option value="">– Mitglied wählen –</option>
                </select>
              </div>
              <div class="col-lg-6 col-md-12">
                <div class="input-group input-group-sm">
                  <span class="input-group-text"><i class="bi bi-person-plus"></i></span>
                  <input type="text" class="form-control" id="gastName" placeholder="Gast Name">
                </div>
              </div>
            </div>
          </div>

          <!-- Munitionsauswahl -->
          <div class="p-2 bg-light rounded">
            <h6 class="mb-2 small"><i class="bi bi-box-seam"></i> Munition (CHF 0.50/Schuss)</h6>
            
            <div class="row g-2">
              <!-- Standard-Pakete -->
              <div class="col-lg-6 col-md-12">
                <div class="card card-body p-2">
                  <h6 class="card-title mb-2 small">Standard-Pakete</h6>
                  
                  <div class="form-check mb-2">
                    <input class="form-check-input paket-check" type="checkbox" 
                           id="paket_gp11_60" data-typ="GP11_60" data-anzahl="60">
                    <label class="form-check-label small" for="paket_gp11_60">
                      <strong>60x GP11</strong>
                      <span class="text-muted ms-1">CHF 30</span>
                    </label>
                  </div>
                  
                  <div class="form-check">
                    <input class="form-check-input paket-check" type="checkbox" 
                           id="paket_gp90_50" data-typ="GP90_50" data-anzahl="50">
                    <label class="form-check-label small" for="paket_gp90_50">
                      <strong>50x GP90</strong>
                      <span class="text-muted ms-1">CHF 25</span>
                    </label>
                  </div>
                </div>
              </div>
              
              <!-- Individuelle Anzahl -->
              <div class="col-lg-6 col-md-12">
                <div class="card card-body p-2">
                  <h6 class="card-title mb-2 small">Individuelle Anzahl</h6>
                  
                  <div class="mb-2">
                    <div class="input-group input-group-sm">
                      <span class="input-group-text" style="width: 50px;">GP11</span>
                      <input type="number" class="form-control form-control-sm custom-anzahl" 
                             id="custom_gp11" data-typ="GP11_CUSTOM" 
                             min="0" max="500" step="1" value="0" 
                             placeholder="Anzahl">
                      <span class="input-group-text custom-preis small">CHF 0</span>
                    </div>
                  </div>
                  
                  <div>
                    <div class="input-group input-group-sm">
                      <span class="input-group-text" style="width: 50px;">GP90</span>
                      <input type="number" class="form-control form-control-sm custom-anzahl" 
                             id="custom_gp90" data-typ="GP90_CUSTOM" 
                             min="0" max="500" step="1" value="0" 
                             placeholder="Anzahl">
                      <span class="input-group-text custom-preis small">CHF 0</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Total Anzeige -->
            <div class="mt-2 p-2 bg-white rounded border">
              <div class="row align-items-center">
                <div class="col">
                  <strong class="small">Total:</strong>
                </div>
                <div class="col-auto text-end">
                  <div class="small"><span class="text-muted">GP11:</span> <span id="total_gp11">0</span></div>
                  <div class="small"><span class="text-muted">GP90:</span> <span id="total_gp90">0</span></div>
                </div>
                <div class="col-auto">
                  <div class="h6 mb-0 text-primary">
                    <strong id="total_preis">CHF 0.00</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Toolbar -->
          <div class="d-flex justify-content-end gap-2 mt-3 mb-3">
            <button type="button" id="btnReset" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-arrow-counterclockwise"></i> Zurücksetzen
            </button>
            <button type="submit" id="btnSave" class="btn btn-primary btn-sm">
              <span class="spinner-border spinner-border-sm me-2 d-none" id="saveSpinner"></span>
              <i class="bi bi-save"></i> Kauf speichern
            </button>
          </div>
          
          <!-- Bestellungen Tabelle -->
          <div class="mt-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0"><i class="bi bi-table"></i> Munitionskäufe</h6>
              <div class="btn-group btn-group-sm">
                <button type="button" id="btnFilterToday" class="btn btn-outline-secondary active">Heute</button>
                <button type="button" id="btnFilterWeek" class="btn btn-outline-secondary">Diese Woche</button>
                <button type="button" id="btnFilterMonth" class="btn btn-outline-secondary">Dieser Monat</button>
                <button type="button" id="btnFilterYear" class="btn btn-outline-secondary">Ganzes Jahr</button>
                <button type="button" id="btnGeneratePDF" class="btn btn-outline-primary">
                  <i class="bi bi-file-earmark-pdf"></i> PDF
                </button>
              </div>
            </div>
            
            <div class="table-responsive">
              <table class="table table-sm table-hover table-bordered" id="bestellungenTabelle">
                <thead class="table-light">
                  <tr>
                    <th style="width: 100px;">Datum</th>
                    <th style="min-width: 150px;">Käufer</th>
                    <th>Anlass</th>
                    <th class="text-center">GP11</th>
                    <th class="text-center">GP90</th>
                    <th class="text-end">Preis</th>
                    <th style="width: 80px;"></th>
                  </tr>
                </thead>
                <tbody id="bestellungenTableBody">
                  <tr>
                    <td colspan="7" class="text-muted text-center">Wähle einen Filter um Bestellungen zu sehen</td>
                  </tr>
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <th colspan="3">Total</th>
                    <th class="text-center" id="footerGP11">0</th>
                    <th class="text-center" id="footerGP90">0</th>
                    <th class="text-end" id="footerPreis">CHF 0.00</th>
                    <th></th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Sidebar: Statistiken -->
  <div class="col-xl-3 col-lg-4 col-md-12 col-12">
    <div class="sidebar-wrapper">
      <div class="content-background p-3 mb-3">
        <h6 class="mb-3"><i class="bi bi-graph-up"></i> Statistiken <span class="badge bg-secondary" id="statsYear">2024</span></h6>
        
        <div class="mb-3">
          <div class="d-flex justify-content-between mb-1">
            <span>Heute</span>
            <strong id="statsToday">CHF 0.00</strong>
          </div>
          <div class="d-flex justify-content-between mb-1">
            <span>Diese Woche</span>
            <strong id="statsWeek">CHF 0.00</strong>
          </div>
          <div class="d-flex justify-content-between mb-1">
            <span>Diesen Monat</span>
            <strong id="statsMonth">CHF 0.00</strong>
          </div>
          <hr>
          <div class="d-flex justify-content-between">
            <span><strong>Jahrestotal</strong></span>
            <strong class="text-primary" id="statsYearTotal">CHF 0.00</strong>
          </div>
        </div>
        
        <h6 class="mb-2 mt-3">Top Käufer (Jahr)</h6>
        <div id="topKaeuferList" class="small">
          <div class="text-muted">Wird geladen...</div>
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
  .paket-check:checked + label {
    color: var(--bs-success);
    font-weight: 600;
  }
  
  .custom-preis {
    min-width: 65px;
    font-weight: 600;
    font-size: 0.875rem;
  }
  
  #bestellungenTabelle tbody tr:hover {
    background-color: var(--bs-gray-100);
  }
  
  .sidebar-wrapper .content-background {
    background-color: var(--bs-white);
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
  }
  
  .btn-group-sm .btn.active {
    background-color: var(--bs-primary);
    color: white;
  }
  
  #topKaeuferList .käufer-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.25rem 0;
    border-bottom: 1px solid var(--bs-gray-200);
  }
  
  #topKaeuferList .käufer-item:last-child {
    border-bottom: none;
  }
  
  /* Kompakteres Layout */
  .year-selection-card,
  .content-background {
    max-width: 100%;
  }
  
  @media (min-width: 1200px) {
    .main-content-wrapper {
      max-width: 900px;
    }
  }
</style>

<script src="munitionskauf/munitionskauf.js"></script>

<?php include 'footer.inc.php'; ?>
