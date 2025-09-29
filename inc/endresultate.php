<?php
// endresultate.php
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in endresultate.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

include 'header.inc.php';

/* CSRF */
if (!isset($_SESSION)) { session_start(); }
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<link rel="stylesheet" href="../css/fixes/no-page-scroll-override.css">
<link rel="stylesheet" href="../css/fixes/resultate-unified.css">
<link rel="stylesheet" href="../css/fixes/endresultate-firstcol-override.css">
<div class="container-fluid">
  <div class="row">
    <div class="col-xl-8 col-lg-12 col-12 ps-0">
      <div class="main-content-wrapper">
        <div class="row mb-4">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-target me-2"></i>
              Endschiessen Resultaterfassung
            </h2>
          </div>
        </div>

        <div class="content-background">
          <form id="endresultateForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <!-- Jahr (einheitlich) -->
            <div class="year-selection-card mb-3">
              <div class="row align-items-center">
                <div class="col-md-5">
                  <label for="yearSelect" class="form-label fw-bold">
                    <i class="bi bi-calendar3 me-1"></i>Jahr auswählen:
                  </label>
                  <select id="yearSelect" class="form-select"></select>
                </div>
              </div>
            </div>

            <!-- Toolbar (einheitlich) -->
            <div class="button-toolbar mb-3">
              <div class="button-group d-flex gap-2 flex-wrap">
                <button id="redirect-btn" type="button" class="btn btn-compact-standard btn-outline-success">
                  <i class="bi bi-trophy me-2"></i>Rangliste
                </button>
                <button id="delall-btn" type="button" class="btn btn-compact-standard btn-outline-danger">
                  <i class="bi bi-trash me-2"></i>Alle Daten löschen
                </button>
              </div>
            </div>

            <!-- Messages -->
            <div id="message" class="mb-2"></div>

            <!-- Tabelle (einheitlich) -->
            <div class="table-wrapper">
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="mitgliederTabelle">
                  <thead>
                    <tr>
                      <th scope="col"><i class="bi bi-person me-1"></i>Mitglied</th>
                      <th scope="col" class="text-center">Endstich</th>
                      <th scope="col" class="text-center">Schwini</th>
                      <th scope="col" class="text-center">Kunst</th>
                      <th scope="col" class="text-center">Glück</th>
                      <th scope="col" class="text-center">Zabig</th>
                      <th scope="col" class="text-center">Sie und Er</th>
                      <th scope="col" class="text-center">Ansage</th>
                      <th scope="col" class="text-center">Aktionen</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="9" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                        Lade Daten...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Schuss-Modal -->
<div class="modal fade" id="schussModal" tabindex="-1" aria-labelledby="schussModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="schussModalLabel">
          <i class="bi bi-target me-2"></i> Erfassen
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <form id="schussForm" style="display: contents;">
          <input type="hidden" id="mitgliedID" name="mitgliedID">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

          <div class="row g-3">
            <div class="col-12">
              <div id="endstichSchuesse" class="shooting-category mb-2">
                <h6 class="mb-2"><i class="bi bi-bullseye me-2"></i> Endstich</h6>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <?php for ($i=1; $i<=10; $i++): ?>
                    <input type="number" class="small-input endschuss" id="Schuss<?= $i ?>" name="Schuss<?= $i ?>" min="0" max="10">
                  <?php endfor; ?>
                  <div class="ms-2 d-flex align-items-center">
                    <label for="Tiefschuss" class="small me-1">TS:</label>
                    <input type="number" class="small-input" id="Tiefschuss" name="Tiefschuss" min="0" max="100">
                  </div>
                  <span id="endstichSumme" class="total-display ms-auto">0</span>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div id="schwiniSchuesse" class="shooting-category mb-2">
                <h6 class="mb-2"><i class="bi bi-piggy-bank me-2"></i> Schwini</h6>
                <div class="mb-2">
                  <label class="small mb-1">Passe 1:</label>
                  <div class="d-flex align-items-center gap-1">
                    <?php for ($i=1; $i<=6; $i++): ?>
                      <input type="number" class="small-input schwini-schuss1" id="P1Schuss<?= $i ?>" name="P1Schuss<?= $i ?>" min="0" max="10">
                    <?php endfor; ?>
                    <span id="schwiniSumme1" class="total-display ms-1">0</span>
                  </div>
                </div>
                <div>
                  <label class="small mb-1">Passe 2:</label>
                  <div class="d-flex align-items-center gap-1">
                    <?php for ($i=1; $i<=6; $i++): ?>
                      <input type="number" class="small-input schwini-schuss2" id="P2Schuss<?= $i ?>" name="P2Schuss<?= $i ?>" min="0" max="10">
                    <?php endfor; ?>
                    <span id="schwiniSumme2" class="total-display ms-1">0</span>
                  </div>
                </div>
              </div>

              <div id="glueckSchuesse" class="shooting-category">
                <h6 class="mb-2"><i class="bi bi-clover me-2"></i> Glück</h6>
                <div class="d-flex gap-1">
                  <input type="number" class="small-input glueck" id="GSchuss1" name="GSchuss1" min="0" max="100">
                  <input type="number" class="small-input glueck" id="GSchuss2" name="GSchuss2" min="0" max="100">
                  <input type="number" class="small-input glueck" id="GSchuss3" name="GSchuss3" min="0" max="100">
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div id="zabigSchuesse" class="shooting-category mb-2">
                <h6 class="mb-2"><i class="bi bi-moon-stars me-2"></i> Zabig</h6>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <?php for ($i=1; $i<=6; $i++): ?>
                    <input type="number" class="small-input zabig" id="ZSchuss<?= $i ?>" name="ZSchuss<?= $i ?>" min="0" max="100">
                  <?php endfor; ?>
                  <span id="zabigsum" class="total-display ms-1">0</span>
                </div>
              </div>

              <div id="kunstSchuesse" class="shooting-category mb-2">
                <h6 class="mb-2"><i class="bi bi-palette me-2"></i> Kunst</h6>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <?php for ($i=1; $i<=5; $i++): ?>
                    <input type="number" class="small-input kunst" id="KSchuss<?= $i ?>" name="KSchuss<?= $i ?>" min="0" max="100">
                  <?php endfor; ?>
                  <span id="kunstSum" class="total-display ms-1">0</span>
                </div>
              </div>

              <div id="sieunderSchuesse" class="shooting-category mb-2">
                <h6 class="mb-2">
                  <i class="bi bi-people me-2"></i>"Sie und Er"
                  <span class="badge bg-info ms-2" style="font-size: 0.7rem;">Spezielle Berechnung</span>
                </h6>
                
                <!-- Mitglied Schüsse (6-10) -->
                <div class="mb-2">
                  <label class="small mb-1 text-primary">
                    <i class="bi bi-person-fill"></i> Mitglied-Schüsse (Position 6-10):
                  </label>
                  <div class="d-flex align-items-center gap-1 flex-wrap">
                    <?php for ($i=6; $i<=10; $i++): ?>
                      <div class="input-wrapper" style="position: relative; display: inline-block;">
                        <input type="number"
                               class="small-input sie-er-schuss sie-er-mitglied"
                               id="SieErSchuss<?= $i ?>"
                               name="SieErSchuss<?= $i ?>"
                               data-position="<?= $i ?>"
                               data-source="mitglied"
                               min="0" max="10" step="0.1"
                               style="border-bottom: 3px solid #007bff;"
                               placeholder="<?= $i ?>">
                      </div>
                    <?php endfor; ?>
                  </div>
                </div>
                
                <!-- Live-Vorschau -->
                <div class="sieer-preview mt-2 p-2 bg-light rounded">
                  <small><strong>Unique-Berechnung:</strong></small>
                  <div id="sieErPreview" class="mt-1">
                    <div id="previewBadges"></div>
                    <span class="badge bg-success mt-1" id="uniqueTotal">
                      <i class="bi bi-calculator me-1"></i>Total: 0
                    </span>
                  </div>
                </div>
              </div>

              <div class="row g-2">
                <div class="col-6">
                  <div id="Differenzler" class="shooting-category">
                    <h6 class="mb-2"><i class="bi bi-chat-square-text me-2"></i> Ansage</h6>
                    <input type="number" class="form-control form-control-sm" id="Ansage" name="Ansage" min="0" max="999" placeholder="Diff.">
                  </div>
                </div>
                <div class="col-6">
                  <div id="Absendenanmeldung" class="shooting-category">
                    <h6 class="mb-2"><i class="bi bi-calendar-check me-2"></i> Absenden</h6>
                    <input type="text" class="form-control form-control-sm" id="AbsendenAnmeldung" name="AbsendenAnmeldung" placeholder="Anmeldung">
                  </div>
                </div>
              </div>
            </div>

          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-2"></i>Abbrechen
        </button>
        <button type="submit" form="schussForm" class="btn btn-outline-success">
          <i class="bi bi-save me-2"></i>Speichern
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Einheitliches Bestätigungsmodal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="confirmModalLabel">
          <i class="bi bi-exclamation-triangle text-warning me-2"></i>
          Bestätigung erforderlich
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body text-center py-4">Sind Sie sicher, dass Sie diese Aktion durchführen möchten?</div>
      <div class="modal-footer border-0 justify-content-center">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-2"></i>Abbrechen
        </button>
        <button type="button" class="btn btn-outline-danger" id="confirmAction">
          <i class="bi bi-check-circle me-2"></i>Bestätigen
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Gemeinsame Bibliothek einbinden -->
<script src="js/msv-resultate-common.js"></script>

<script>
// Initialisierung mit der gemeinsamen Bibliothek
$(document).ready(function() {
    // Initialisiere den Manager für End-Resultate
    const endManager = MSV.init('end');
    
    // Global Scroll aktivieren
    MSV.enableGlobalScroll();
    
    // Funktion zur Berechnung und Visualisierung der SieEr Unique-Logik
    function updateSieErUniqueVisualization() {
        const allValues = [];
        const valuePositions = {};
        
        // Sammle alle Mitglied-Werte (nur 6-10)
        $('.sie-er-mitglied').each(function() {
            const value = parseFloat($(this).val() || 0);
            const position = $(this).data('position');
            
            if (value > 0) {
                const intValue = Math.floor(value);
                
                if (!valuePositions[intValue]) {
                    valuePositions[intValue] = [];
                }
                
                valuePositions[intValue].push({
                    position: position,
                    source: 'mitglied',
                    element: $(this),
                    value: value
                });
                
                allValues.push(intValue);
            }
        });
        
        // Reset alle Styles
        $('.sie-er-schuss').css({
            'border-color': '',
            'background-color': ''
        });
        
        // Markiere Duplikate und Unique-Werte
        const uniqueValues = [];
        const processedValues = {};
        
        Object.keys(valuePositions).forEach(value => {
            const positions = valuePositions[value];
            
            if (positions.length === 1) {
                // Einzigartiger Wert - grüner Rand
                positions[0].element.css({
                    'border': '2px solid #28a745',
                    'background-color': '#f0fff4'
                });
                uniqueValues.push(parseInt(value));
            } else {
                // Duplikate - nur der erste zählt
                positions.forEach((pos, index) => {
                    if (index === 0) {
                        // Erstes Vorkommen - grün
                        pos.element.css({
                            'border': '2px solid #28a745',
                            'background-color': '#f0fff4'
                        });
                        if (!processedValues[value]) {
                            uniqueValues.push(parseInt(value));
                            processedValues[value] = true;
                        }
                    } else {
                        // Duplikat - rot
                        pos.element.css({
                            'border': '2px solid #dc3545',
                            'background-color': '#fff5f5',
                            'opacity': '0.7'
                        });
                    }
                });
            }
        });
        
        // Update Vorschau
        updateSieErPreview(valuePositions, uniqueValues);
        
        // Update Total
        const uniqueSum = uniqueValues.reduce((sum, val) => sum + val, 0);
        $('#uniqueTotal').html('<i class="bi bi-calculator me-1"></i>Total: ' + uniqueSum);
        
        // Update das normale sieunderSum für Kompatibilität
        $('#sieunderSum').text(uniqueSum);
    }
    
    // Vorschau-Update Funktion für SieEr
    function updateSieErPreview(valuePositions, uniqueValues) {
        let previewHTML = '';
        const processedForPreview = {};
        
        // Mitglied Badges
        $('.sie-er-mitglied').each(function() {
            const value = parseFloat($(this).val() || 0);
            if (value > 0) {
                const intValue = Math.floor(value);
                const isDuplicate = processedForPreview[intValue];
                
                if (isDuplicate) {
                    previewHTML += '<span class="badge bg-primary bg-opacity-25 text-primary" style="text-decoration: line-through; font-size: 0.7rem;">' + value + '</span> ';
                } else {
                    previewHTML += '<span class="badge bg-primary" style="font-size: 0.7rem;">' + value + '</span> ';
                    processedForPreview[intValue] = true;
                }
            }
        });
        
        $('#previewBadges').html(previewHTML || '<span class="text-muted small">Noch keine Werte</span>');
    }
    
    // Spezielle End-Resultate Berechnungen
    $(document).on('input change', '.endschuss, .schwini-schuss1, .schwini-schuss2, .kunst, .zabig, .sieunder', function() {
        endManager.calculateAllSums();
    });
    
    // SieEr Unique-Berechnung bei Eingabe
    $(document).on('input change', '.sie-er-schuss', function() {
        updateSieErUniqueVisualization();
    });
});

</script>

<?php include 'footer.inc.php'; ?>
