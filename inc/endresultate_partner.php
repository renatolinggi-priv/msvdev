<?php
// endresultate_partner.php - Partner-Endresultate
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in endresultate_partner.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

include 'header.inc.php';

/* CSRF */
if (!isset($_SESSION)) { session_start(); }
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<style>
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

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-8 col-lg-12 col-12 ps-0">
      <div class="main-content-wrapper">
        <div class="row mb-4 d-none d-md-flex">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-people me-2"></i>
              Partner Endresultate
            </h2>
          </div>
        </div>

        <div class="content-background">
          <form id="partnerResultateForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <!-- Jahr (einheitlich) -->
            <div class="d-flex align-items-center gap-2 mb-3">
              <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                <i class="bi bi-calendar3 me-1"></i>Jahr:
              </label>
              <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
            </div>

            <!-- Toolbar -->
            <div class="button-toolbar mb-3">
              <div class="button-group d-flex gap-2 flex-wrap">
                <button id="redirect-btn" type="button" class="btn btn-compact-standard btn-outline-success">
                  <i class="bi bi-trophy me-2"></i>Rangliste
                </button>
                <button id="add-partner-btn" type="button" class="btn btn-compact-standard btn-outline-primary">
                  <i class="bi bi-plus me-2"></i>Partnerin hinzufügen
                </button>
                <button id="delete-year-btn" type="button" class="btn btn-compact-standard btn-outline-danger">
                  <i class="bi bi-trash3 me-2"></i>Jahr löschen
                </button>
              </div>
            </div>

            <!-- Partner Liste -->
            <div class="table-wrapper">
              <!-- Desktop: Tabelle -->
              <div class="desktop-table-container">
                <div class="table-responsive">
                  <table class="table table-hover mb-0" id="partnerTabelle" style="">
                    <thead>
                      <tr>
                        <th scope="col" style="min-width: 200px;" class="text-left"><i class="bi bi-heart me-1"></i>Partnerin</th>
                        <th scope="col" style="min-width: 200px;"><i class="bi bi-person me-1"></i>Mitglied</th>
                        <th scope="col" class="text-center">Endstich</th>
                        <th scope="col" class="text-center">Sie und Er</th>
                        <th scope="col" class="text-center">Partner Schwini</th>
                        <th scope="col" class="text-center">Aktionen</th>
                      </tr>
                    </thead>
                    <tbody id="partnerListBody">
                      <tr>
                        <td colspan="6" class="text-center py-4">
                          <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                          Lade Daten...
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Mobile: Cards -->
              <div class="mobile-cards-container" id="mobileCardsPartner">
                <div class="mobile-search">
                  <div class="position-relative">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="form-control" placeholder="Suchen..."
                           oninput="filterMobilePartner(this)">
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
</div>

<!-- Partnerin Erfassen Modal -->
<div class="modal fade" id="partnerModal" tabindex="-1" aria-labelledby="partnerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="partnerModalLabel">
          <i class="bi bi-people me-2"></i> Partnerin erfassen
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <form id="partnerForm" style="display: contents;">
          <input type="hidden" id="partnerID" name="partnerID">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <input type="hidden" id="jahr" name="jahr" value="<?= date('Y') ?>">

          <div class="row g-3">
            <!-- Grunddaten -->
            <div class="col-12">
              <div class="shooting-category mb-3">
                <h6 class="mb-2"><i class="bi bi-info-circle me-2"></i>Grunddaten</h6>
                <div class="row g-2">
                  <div class="col-md-6">
                    <label for="mitgliedSelect" class="small mb-1">Mitglied <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="mitgliedSelect" name="mitgliedID" required>
                      <option value="">-- Mitglied auswählen --</option>
                      <?php
                      $sql = "SELECT ID, Name, Vorname FROM mitglieder WHERE Verstorben = 0 ORDER BY Name, Vorname";
                      $result = $conn->query($sql);
                      if ($result && $result->num_rows > 0) {
                          while ($row = $result->fetch_assoc()) {
                              echo "<option value='" . $row['ID'] . "'>" . htmlspecialchars($row['Name'] . " " . $row['Vorname']) . "</option>";
                          }
                      }
                      ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="partnerName" class="small mb-1">Name der Partnerin <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="partnerName" name="partnerName"
                           placeholder="z.B. Maria Muster" required>
                  </div>
                </div>
              </div>
            </div>

            <!-- Endstich -->
            <div class="col-md-6">
              <div id="endstichSchuesse" class="shooting-category mb-2">
                <h6 class="mb-2"><i class="bi bi-bullseye me-2"></i>Endstich (10 Schüsse)</h6>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <?php for ($i=1; $i<=10; $i++): ?>
                    <input type="number" class="small-input endstich-schuss" id="EndstichSchuss<?= $i ?>" 
                           name="EndstichSchuss<?= $i ?>" min="0" max="10" step="0.1">
                  <?php endfor; ?>
                  <span id="endstichSumme" class="total-display ms-auto">0</span>
                </div>
              </div>

              <div id="sieErSchuesse" class="shooting-category">
                <h6 class="mb-2">
                  <i class="bi bi-heart me-2"></i>"Sie und Er" 
                  <span class="badge bg-info ms-2" style="font-size: 0.7rem;">Spezielle Berechnung</span>
                </h6>
                
                <!-- Partner Schüsse (1-5) -->
                <div class="mb-2">
                  <label class="small mb-1 text-danger">
                    <i class="bi bi-heart-fill"></i> Partner-Schüsse (Position 1-5):
                  </label>
                  <div class="d-flex align-items-center gap-1 flex-wrap">
                    <?php for ($i=1; $i<=5; $i++): ?>
                      <div class="input-wrapper" style="position: relative; display: inline-block;">
                        <input type="number" 
                               class="small-input sie-er-schuss sie-er-partner" 
                               id="SieErSchuss<?= $i ?>" 
                               name="SieErSchuss<?= $i ?>" 
                               data-position="<?= $i ?>"
                               data-source="partner"
                               min="0" max="10" step="0.1"
                               style="border-bottom: 3px solid #dc3545;"
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
            </div>

            <!-- Schwini -->
            <div class="col-md-6">
              <div id="schwiniSchuesse" class="shooting-category mb-2">
                <h6 class="mb-2"><i class="bi bi-piggy-bank me-2"></i>Partner Schwini</h6>
                
                <!-- 1. Passe -->
                <div class="mb-2">
                  <label class="small mb-1">1. Passe (6 Schüsse):</label>
                  <div class="d-flex align-items-center gap-1">
                    <?php for ($i=1; $i<=6; $i++): ?>
                      <input type="number" class="small-input schwini-schuss schwini-passe1" id="PartnerSchwiniSchuss<?= $i ?>" 
                             name="PartnerSchwiniSchuss<?= $i ?>" min="0" max="10" step="0.1">
                    <?php endfor; ?>
                    <span id="schwiniSumme1" class="total-display ms-1">0</span>
                  </div>
                </div>
                
                <!-- 2. Passe -->
                <div class="mb-2">
                  <label class="small mb-1">2. Passe (6 Schüsse):</label>
                  <div class="d-flex align-items-center gap-1">
                    <?php for ($i=7; $i<=12; $i++): ?>
                      <input type="number" class="small-input schwini-schuss schwini-passe2" id="PartnerSchwiniSchuss<?= $i ?>" 
                             name="PartnerSchwiniSchuss<?= $i ?>" min="0" max="10" step="0.1">
                    <?php endfor; ?>
                    <span id="schwiniSumme2" class="total-display ms-1">0</span>
                  </div>
                </div>
                
                <!-- Total -->
                <div class="mt-2">
                  <strong>Total: <span id="schwiniSummeTotal" class="text-primary">0</span></strong>
                </div>
              </div>

              <div class="alert alert-info mt-3">
                <small><i class="bi bi-info-circle me-1"></i>
                <strong>Hinweise zum "Sie und Er" Stich:</strong><br>
                â€¢ Besteht aus 5 Schüssen der Partnerin (hier erfassen) + 5 Schüssen des Mitglieds<br>
                â€¢ <span class="badge bg-warning text-dark">Spezielle Berechnung:</span> Jeder Wert zählt nur 1x (Unique-Logik)<br>
                â€¢ <span style="color: #28a745;">âœ“ Grüner Rand</span> = Wert wird gezählt<br>
                â€¢ <span style="color: #dc3545;">âœ— Roter Rand</span> = Duplikat (zählt nicht)<br>
                â€¢ Die 5 Mitglied-Schüsse (Position 6-10) werden separat bei den Mitglied-Resultaten erfasst
                </small>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-2"></i>Abbrechen
        </button>
        <button type="submit" form="partnerForm" class="btn btn-outline-success">
          <i class="bi bi-save me-2"></i>Speichern
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Jahr löschen Bestätigungsmodal -->
<div class="modal fade" id="deleteYearModal" tabindex="-1" aria-labelledby="deleteYearModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteYearModalLabel">
          <i class="bi bi-exclamation-triangle me-2"></i>
          Achtung - Alle Daten löschen
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger" role="alert">
          <h6 class="alert-heading">Sind Sie sicher?</h6>
          <p class="mb-2">Sie sind dabei, <strong>ALLE Partner-Endresultate</strong> des Jahres <strong id="yearToDelete"></strong> zu löschen.</p>
          <hr>
          <p class="mb-0"><strong>Diese Aktion kann nicht rückgängig gemacht werden!</strong></p>
        </div>
        <div class="text-muted">
          <small>Anzahl betroffene Einträge: <span id="entryCount" class="fw-bold">0</span></small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-lg me-2"></i>Abbrechen
        </button>
        <button type="button" class="btn btn-danger" id="confirmDeleteYearBtn">
          <i class="bi bi-trash3 me-2"></i>Ja, alle Daten löschen
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
    // Toast Manager für Nachrichten
    const toastManager = new MSV.ToastManager();
    
    // Layout Manager für Tabellenhöhe
    const layoutManager = new MSV.LayoutManager();
    layoutManager.init();
    
    let currentYear = new Date().getFullYear();
    let deletePartnerID = null;
    
    // Jahr-Selector initialisieren
    function initYearSelector() {
        const yearSelect = $('#yearSelect');
        yearSelect.empty();
        for (let year = currentYear; year >= currentYear - 3; year--) {
            yearSelect.append(`<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`);
        }
    }
    
    // Partner Liste laden
    function loadPartnerList() {
        const tbody = $('#partnerListBody');
        tbody.html('<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>Lade Partner-Daten...</td></tr>');
        
        $.get('endresultate_partner/load_partner_resultate.php', {
            year: currentYear
        }, function(data) {
            tbody.html(data);
            layoutManager.refresh();
            buildMobilePartnerCards();
        }).fail(function(xhr, status, error) {
            tbody.html('<tr><td colspan="6" class="text-center text-danger">Fehler beim Laden der Daten</td></tr>');
            toastManager.show('Fehler beim Laden der Partner-Daten', 'error');
        });
    }
    
    // Summen automatisch berechnen
    function calculateSums() {
        // Endstich Summe (10 Schüsse)
        let endstichSum = 0;
        $('.endstich-schuss').each(function() {
            const value = parseFloat($(this).val() || 0);
            endstichSum += value;
        });
        $('#endstichSumme').text(endstichSum.toFixed(1));
        
        // "Sie und Er" mit Unique-Logik
        updateSieErUniqueVisualization();
        
        // Partner Schwini - 1. Passe (6 Schüsse)
        let schwiniSum1 = 0;
        $('.schwini-passe1').each(function() {
            const value = parseFloat($(this).val() || 0);
            schwiniSum1 += value;
        });
        $('#schwiniSumme1').text(schwiniSum1.toFixed(1));
        
        // Partner Schwini - 2. Passe (6 Schüsse)
        let schwiniSum2 = 0;
        $('.schwini-passe2').each(function() {
            const value = parseFloat($(this).val() || 0);
            schwiniSum2 += value;
        });
        $('#schwiniSumme2').text(schwiniSum2.toFixed(1));
        
        // Total Schwini
        const schwiniTotal = schwiniSum1 + schwiniSum2;
        $('#schwiniSummeTotal').text(schwiniTotal.toFixed(1));
    }
    
    // Funktion zur Berechnung und Visualisierung der Unique-Logik
    function updateSieErUniqueVisualization() {
        const allValues = [];
        const valuePositions = {};
        
        // Sammle alle Partner-Werte (nur 1-5)
        $('.sie-er-partner').each(function() {
            const value = parseFloat($(this).val() || 0);
            const position = $(this).data('position');
            
            if (value > 0) {
                const intValue = Math.floor(value);
                
                if (!valuePositions[intValue]) {
                    valuePositions[intValue] = [];
                }
                
                valuePositions[intValue].push({
                    position: position,
                    source: 'partner',
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
        updatePreview(valuePositions, uniqueValues);
        
        // Update Total
        const uniqueSum = uniqueValues.reduce((sum, val) => sum + val, 0);
        $('#uniqueTotal').html('<i class="bi bi-calculator me-1"></i>Total: ' + uniqueSum);
    }
    
    // Vorschau-Update Funktion
    function updatePreview(valuePositions, uniqueValues) {
        let previewHTML = '';
        const processedForPreview = {};
        
        // Partner Badges
        $('.sie-er-partner').each(function() {
            const value = parseFloat($(this).val() || 0);
            if (value > 0) {
                const intValue = Math.floor(value);
                const isDuplicate = processedForPreview[intValue];
                
                if (isDuplicate) {
                    previewHTML += '<span class="badge bg-danger bg-opacity-25 text-danger" style="text-decoration: line-through; font-size: 0.7rem;">' + value + '</span> ';
                } else {
                    previewHTML += '<span class="badge bg-danger" style="font-size: 0.7rem;">' + value + '</span> ';
                    processedForPreview[intValue] = true;
                }
            }
        });
        
        $('#previewBadges').html(previewHTML || '<span class="text-muted small">Noch keine Werte</span>');
    }
    
    // Partnerin speichern
    function savePartner() {
        const $submitBtn = $('#partnerForm button[type="submit"]');
        const originalText = $submitBtn.html();
        
        // Validierung
        if (!$('#partnerName').val().trim()) {
            toastManager.show('Bitte gib den Namen der Partnerin ein', 'error');
            return;
        }
        
        if (!$('#mitgliedSelect').val()) {
            toastManager.show('Bitte wähle ein Mitglied aus', 'error');
            return;
        }
        
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
        
        const formData = $('#partnerForm').serialize();
        
        $.post('endresultate_partner/save_partner_schuss.php', formData, function(data) {
            if (data.success) {
                $('#partnerModal').modal('hide');
                loadPartnerList();
                toastManager.show('Partnerin erfolgreich gespeichert!', 'success');
                resetForm();
            } else {
                toastManager.show('Fehler beim Speichern: ' + data.error, 'error');
            }
        }, 'json').fail(function(xhr) {
            let errorMsg = 'Fehler beim Speichern der Partnerin';
            if (xhr.responseText) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.error || errorMsg;
                } catch(e) {
                    console.error('Parse error:', e);
                }
            }
            toastManager.show(errorMsg, 'error');
        }).always(function() {
            $submitBtn.prop('disabled', false).html(originalText);
        });
    }
    
    // Formular zurücksetzen
    function resetForm() {
        $('#partnerForm')[0].reset();
        $('#partnerID').val('');
        $('#jahr').val(currentYear);
        $('.total-display').text('0');
        
        // Entferne Gast-Hinweis falls vorhanden
        $('#guestHint').remove();
        
        // Setze Modal-Titel zurück
        $('#partnerModalLabel').html('<i class="bi bi-people me-2"></i> Partnerin erfassen');
    }
    
    // Partnerin bearbeiten laden
    function loadPartnerForEdit(partnerID) {
        $.get('endresultate_partner/load_partner_data.php', {
            id: partnerID
        }, function(data) {
            if (data.success) {
                const partner = data.partner;
                
                // Formular füllen
                $('#partnerID').val(partner.ID);
                $('#partnerName').val(partner.PartnerName);
                $('#mitgliedSelect').val(partner.MitgliedID);
                
                // Schüsse laden
                for (let i = 1; i <= 10; i++) {
                    $('#EndstichSchuss' + i).val(partner['EndstichSchuss' + i] || '0');
                }
                
                for (let i = 1; i <= 5; i++) {
                    $('#SieErSchuss' + i).val(partner['SieErSchuss' + i] || '0');
                }
                
                for (let i = 1; i <= 12; i++) {
                    $('#PartnerSchwiniSchuss' + i).val(partner['PartnerSchwiniSchuss' + i] || '0');
                }
                
                calculateSums();
                $('#partnerModalLabel').html('<i class="bi bi-people me-2"></i> Partnerin bearbeiten');
                $('#partnerModal').modal('show');
            } else {
                toastManager.show('Fehler beim Laden der Partnerin: ' + data.error, 'error');
            }
        }, 'json').fail(function() {
            toastManager.show('Fehler beim Laden der Partnerin', 'error');
        });
    }
    
    // NEU: Funktion um Modal für Gast zu öffnen (mit vorausgefülltem Namen)
    function openModalForGuest(guestName) {
        resetForm();
        
        // Setze den Gast-Namen
        $('#partnerName').val(guestName);
        
        // Modal-Titel anpassen
        $('#partnerModalLabel').html('<i class="bi bi-people me-2"></i> Gast-Resultate erfassen');
        
        // Zeige Hinweis dass dies ein Gast ist
        const guestHint = `
            <div class="alert alert-info mb-3" id="guestHint">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Gast:</strong> Dieser Eintrag wurde als Gast erfasst. 
                Bitte wähle ein Mitglied aus, dem dieser Gast zugeordnet werden soll.
            </div>
        `;
        
        // Füge Hinweis nach dem Formular-Start ein (vor Grunddaten)
        $('#partnerForm .row.g-3').prepend(guestHint);
        
        // Öffne Modal
        $('#partnerModal').modal('show');
        
        // Fokus auf Mitglied-Auswahl
        setTimeout(function() {
            $('#mitgliedSelect').focus();
        }, 300);
    }
    
    // Lösch-Bestätigung zeigen
    function showDeleteConfirmation(partnerID, name) {
        deletePartnerID = partnerID;
        const message = `
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle text-warning me-3" style="font-size: 2rem;"></i>
                <div>
                    <strong>Möchtest du die Partnerin "${name}" wirklich löschen?</strong>
                    <br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden.</small>
                </div>
            </div>
        `;
        
        $('#confirmModal .modal-body').html(message);
        $('#confirmModal').modal('show');
    }
    
    // Partnerin löschen (nach Bestätigung)
    function executeDelete() {
        const $btn = $('#confirmAction');
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');
        
        $.post('endresultate_partner/delete_partner.php', {
            id: deletePartnerID,
            csrf_token: $('input[name="csrf_token"]').val()
        }, function(data) {
            if (data.success) {
                $('#confirmModal').modal('hide');
                loadPartnerList();
                toastManager.show('Partnerin erfolgreich gelöscht', 'success');
            } else {
                toastManager.show('Fehler beim Löschen: ' + data.error, 'error');
            }
        }, 'json').fail(function() {
            toastManager.show('Fehler beim Löschen der Partnerin', 'error');
        }).always(function() {
            $btn.prop('disabled', false)
                .html('<i class="bi bi-check-circle me-2"></i>Bestätigen');
        });
    }
    
    // Event Handler
    
    // Jahr ändern
    $('#yearSelect').on('change', function() {
        currentYear = parseInt($(this).val());
        $('#jahr').val(currentYear);
        loadPartnerList();
    });
    
    // Partnerin hinzufügen Button
    $('#add-partner-btn').on('click', function() {
        resetForm();
        $('#partnerModalLabel').html('<i class="bi bi-people me-2"></i> Partnerin erfassen');
        $('#partnerModal').modal('show');
    });
    
    // Jahr löschen Button
    $('#delete-year-btn').on('click', function() {
        // Get count of entries for the year
        $.get('endresultate_partner/count_year_entries.php', {
            year: currentYear
        }, function(data) {
            if (data.success) {
                $('#yearToDelete').text(currentYear);
                $('#entryCount').text(data.count);
                
                if (data.count > 0) {
                    $('#deleteYearModal').modal('show');
                } else {
                    toastManager.show('Keine Einträge für das Jahr ' + currentYear + ' vorhanden.', 'info');
                }
            } else {
                toastManager.show('Fehler beim Abrufen der Daten: ' + data.error, 'error');
            }
        }, 'json').fail(function() {
            toastManager.show('Fehler beim Abrufen der Daten', 'error');
        });
    });
    
    // Bestätigung Jahr löschen
    $('#confirmDeleteYearBtn').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();
        
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');
        
        $.post('endresultate_partner/delete_year_data.php', {
            year: currentYear,
            csrf_token: $('input[name="csrf_token"]').val()
        }, function(data) {
            if (data.success) {
                $('#deleteYearModal').modal('hide');
                loadPartnerList();
                toastManager.show(data.message, 'success');
            } else {
                toastManager.show('Fehler beim Löschen: ' + data.error, 'error');
            }
        }, 'json').fail(function() {
            toastManager.show('Fehler beim Löschen der Daten', 'error');
        }).always(function() {
            $btn.prop('disabled', false).html(originalText);
        });
    });
    
    // Automatische Summenberechnung bei Eingabe
    $(document).on('input change', '.endstich-schuss, .sie-er-schuss, .schwini-schuss', function() {
        calculateSums();
    });
    
    // Auto-Tab zu nächstem Feld
    $(document).on('keydown', '.small-input', function(e) {
        if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            const inputs = $('.small-input:visible');
            const currentIndex = inputs.index(this);
            const nextIndex = e.shiftKey ? currentIndex - 1 : currentIndex + 1;
            const nextInput = inputs.eq(nextIndex);
            
            if (nextInput.length) {
                nextInput.focus().select();
            }
        }
    });
    
    // Focus-Verhalten für Inputs
    $(document).on('focus', '.small-input', function() {
        if ($(this).val() === '0') {
            $(this).val('').select();
        } else if ($(this).val() !== '') {
            $(this).select();
        }
    });
    
    $(document).on('blur', '.small-input', function() {
        if ($(this).val().trim() === '') {
            $(this).val('0');
        }
    });
    
    // Formular absenden
    $('#partnerForm').on('submit', function(e) {
        e.preventDefault();
        savePartner();
    });
    
    // Partnerin bearbeiten (delegiert)
    $(document).on('click', '.edit-partner-btn', function(e) {
        e.preventDefault();
        const partnerID = $(this).data('id');
        loadPartnerForEdit(partnerID);
    });
    
    // NEU: Gast-Resultate erfassen (delegiert)
    $(document).on('click', '.add-guest-result-btn', function(e) {
        e.preventDefault();
        const guestName = $(this).data('guest-name');
        openModalForGuest(guestName);
    });
    
    // Partnerin löschen (delegiert)
    $(document).on('click', '.delete-partner-btn', function(e) {
        e.preventDefault();
        const partnerID = $(this).data('id');
        const name = $(this).closest('tr').find('td:eq(0)').text();
        showDeleteConfirmation(partnerID, name);
    });
    
    // Bestätigung für Löschen
    $('#confirmAction').on('click', function() {
        executeDelete();
    });
    
    // Reset bei Modal-Schließung
    $('#confirmModal').on('hidden.bs.modal', function() {
        deletePartnerID = null;
    });
    
    // NEU: Modal Reset bei Schließung (entfernt Gast-Hinweis)
    $('#partnerModal').on('hidden.bs.modal', function() {
        $('#guestHint').remove();
    });

    document.getElementById("redirect-btn").addEventListener("click", function () {
        // Gewähltes Jahr holen (falls relevant)
        const selectedYear = document.getElementById("yearSelect")?.value || "";

        // Ziel-URL bauen
        let targetUrl = "https://jahresmeisterschaft.msvwilen.ch/inc/endschrang.php";
        if (selectedYear) {
            targetUrl += "?year=" + encodeURIComponent(selectedYear);
        }

        // Weiterleiten
        window.location.href = targetUrl;
    });

    // Mobile Cards für Partner-Endresultate
    function buildMobilePartnerCards() {
        const isMobile = window.matchMedia('(max-width: 767.98px)');
        if (!isMobile.matches) return;

        const table = document.getElementById('partnerTabelle');
        const container = document.querySelector('#mobileCardsPartner .mobile-cards-scroll');
        if (!table || !container) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
            return;
        }

        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
            return;
        }

        let html = '';
        rows.forEach((row, idx) => {
            const cells = Array.from(row.querySelectorAll('td'));
            if (cells.length < 6) return;

            const partnerin = cells[0]?.textContent?.trim() || 'Unbekannt';
            const mitglied = cells[1]?.textContent?.trim() || '-';
            const endstich = cells[2]?.textContent?.trim() || '-';
            const sieUndEr = cells[3]?.textContent?.trim() || '-';
            const schwini = cells[4]?.textContent?.trim() || '-';

            // Extract partner-ID from action button
            const actionBtn = cells[5]?.querySelector('button[onclick*="openPartnerModal"]');
            const onclickAttr = actionBtn ? actionBtn.getAttribute('onclick') : '';
            const partnerId = onclickAttr.match(/openPartnerModal\((\d+)\)/)?.[1] || '';

            html += `
            <div class="mobile-card" data-index="${idx}">
                <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div>
                        <div class="fw-bold"><i class="bi bi-heart me-2"></i>${partnerin}</div>
                        <small class="text-muted">mit ${mitglied}</small>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="mobile-card-body">
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Mitglied</span>
                        <span class="mobile-card-detail-value"><strong>${mitglied}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Endstich</span>
                        <span class="mobile-card-detail-value"><strong>${endstich}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Sie und Er</span>
                        <span class="mobile-card-detail-value"><strong>${sieUndEr}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Partner Schwini</span>
                        <span class="mobile-card-detail-value"><strong>${schwini}</strong></span>
                    </div>
                    ${partnerId ? `
                    <button type="button" class="btn btn-primary w-100 mt-3"
                            onclick="loadPartnerForEdit(${partnerId})"
                            style="min-height: 48px;">
                        <i class="bi bi-pencil me-2"></i>Bearbeiten
                    </button>` : ''}
                </div>
            </div>`;
        });

        container.innerHTML = html;
    }

    window.filterMobilePartner = function(searchInput) {
        const query = searchInput.value.toLowerCase();
        const cards = document.querySelectorAll('#mobileCardsPartner .mobile-card');

        let visibleCount = 0;
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const isVisible = text.includes(query);
            card.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        const container = document.querySelector('#mobileCardsPartner .mobile-cards-scroll');
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
            buildMobilePartnerCards();
        }
        wasDesktop = isNowDesktop;
    });

    // Global Scroll aktivieren
    MSV.enableGlobalScroll();

    // Initialisierung
    initYearSelector();
    loadPartnerList();
});
</script>

<?php include 'footer.inc.php'; ?>