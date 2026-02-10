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
<style>
/* Styling für deaktivierte Stiche */
.shooting-category.disabled {
    opacity: 0.4;
    pointer-events: none;
    position: relative;
}

.shooting-category.disabled::after {
    content: "Nicht gelöst";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(220, 53, 69, 0.9);
    color: white;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: bold;
    z-index: 10;
}

/* Spezielle Styles für teilweise deaktivierte Schwini-Passen */
#schwiniSchuesse .schwini-pass-disabled {
    opacity: 0.4;
    position: relative;
}

#schwiniSchuesse .schwini-pass-disabled::after {
    content: "Nicht gelöst";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(220, 53, 69, 0.7);
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 0.65rem;
    font-weight: bold;
    z-index: 5;
    white-space: nowrap;
}

/* Disabled inputs innerhalb Schwini */
#schwiniSchuesse input:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
    opacity: 0.5;
}

/* Highlight für fokussiertes Feld */
.focusable-input:focus {
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    border-color: #007bff;
}
</style>

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

          <div class="row g-2">
            <!-- Zeile 1: Absenden + Ansage + Leer -->
            <div class="col-md-4">
              <div id="Absendenanmeldung" class="shooting-category mb-0">
                <h6 class="mb-1"><i class="bi bi-calendar-check me-1"></i> Absenden</h6>
                <input type="text" class="form-control form-control-sm focusable-input" id="AbsendenAnmeldung" name="AbsendenAnmeldung" placeholder="Anmeldung">
              </div>
            </div>

            <div class="col-md-4">
              <div id="Differenzler" class="shooting-category mb-0">
                <h6 class="mb-1"><i class="bi bi-chat-square-text me-1"></i> Ansage</h6>
                <input type="number" class="form-control form-control-sm focusable-input" id="Ansage" name="Ansage" min="0" max="999" placeholder="Differenzler">
              </div>
            </div>

            <div class="col-md-4">
              <!-- Leer -->
            </div>

            <!-- Zeile 2: Endstich (volle Breite) -->
            <div class="col-12">
              <div id="endstichSchuesse" class="shooting-category mb-0" data-stich="END">
                <h6 class="mb-1"><i class="bi bi-bullseye me-1"></i> Endstich</h6>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <?php for ($i=1; $i<=10; $i++): ?>
                    <input type="number" class="small-input endschuss focusable-input" id="Schuss<?= $i ?>" name="Schuss<?= $i ?>" min="0" max="10">
                  <?php endfor; ?>
                  <div class="d-flex align-items-center">
                    <label for="Tiefschuss" class="small me-1 mb-0">TS:</label>
                    <input type="number" class="small-input focusable-input" id="Tiefschuss" name="Tiefschuss" min="0" max="100" style="width: 50px;">
                  </div>
                  <span id="endstichSumme" class="total-display ms-auto">0</span>
                </div>
              </div>
            </div>

            <!-- Zeile 3: Schwini + Zabig -->
            <div class="col-md-6">
              <div id="schwiniSchuesse" class="shooting-category mb-0" data-stich="SCHWINI">
                <h6 class="mb-1"><i class="bi bi-piggy-bank me-1"></i> Schwini</h6>
                <div class="mb-1 schwini-passe-1">
                  <label class="small mb-0" style="font-size: 0.75rem;">Passe 1:</label>
                  <div class="d-flex align-items-center gap-1">
                    <?php for ($i=1; $i<=6; $i++): ?>
                      <input type="number" class="small-input schwini-schuss1 focusable-input" id="P1Schuss<?= $i ?>" name="P1Schuss<?= $i ?>" min="0" max="10">
                    <?php endfor; ?>
                    <span id="schwiniSumme1" class="total-display ms-1">0</span>
                  </div>
                </div>
                <div class="schwini-passe-2">
                  <label class="small mb-0" style="font-size: 0.75rem;">Passe 2:</label>
                  <div class="d-flex align-items-center gap-1">
                    <?php for ($i=1; $i<=6; $i++): ?>
                      <input type="number" class="small-input schwini-schuss2 focusable-input" id="P2Schuss<?= $i ?>" name="P2Schuss<?= $i ?>" min="0" max="10">
                    <?php endfor; ?>
                    <span id="schwiniSumme2" class="total-display ms-1">0</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div id="zabigSchuesse" class="shooting-category mb-0" data-stich="ZABIG">
                <h6 class="mb-1"><i class="bi bi-moon-stars me-1"></i> Zabig</h6>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <?php for ($i=1; $i<=6; $i++): ?>
                    <input type="number" class="small-input zabig focusable-input" id="ZSchuss<?= $i ?>" name="ZSchuss<?= $i ?>" min="0" max="100">
                  <?php endfor; ?>
                  <span id="zabigsum" class="total-display ms-1">0</span>
                </div>
              </div>
            </div>

            <!-- Zeile 4: Sie und Er + Kunst + Glück -->
            <div class="col-md-6">
              <div id="sieunderSchuesse" class="shooting-category mb-0" data-stich="SIEUNDER">
                <h6 class="mb-1">
                  <i class="bi bi-people me-1"></i>"Sie und Er"
                  <span class="badge bg-info ms-1" style="font-size: 0.65rem;">Unique</span>
                </h6>
                
                <div class="d-flex align-items-center gap-1 flex-wrap mb-1">
                  <?php for ($i=6; $i<=10; $i++): ?>
                    <input type="number"
                           class="small-input sie-er-schuss sie-er-mitglied focusable-input"
                           id="SieErSchuss<?= $i ?>"
                           name="SieErSchuss<?= $i ?>"
                           data-position="<?= $i ?>"
                           data-source="mitglied"
                           min="0" max="10" step="0.1"
                           style="border-bottom: 3px solid #007bff;"
                           placeholder="<?= $i ?>">
                  <?php endfor; ?>
                  <span class="badge bg-success ms-auto" id="uniqueTotal" style="font-size: 0.7rem;">
                    <i class="bi bi-calculator me-1"></i>Total: 0
                  </span>
                </div>
                
                <!-- Vorschau sehr kompakt -->
                <div id="previewBadges" class="d-flex gap-1 flex-wrap" style="font-size: 0.75rem;"></div>
              </div>
            </div>

            <div class="col-md-3">
              <div id="kunstSchuesse" class="shooting-category mb-0" data-stich="KUNST">
                <h6 class="mb-1"><i class="bi bi-palette me-1"></i> Kunst</h6>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <?php for ($i=1; $i<=5; $i++): ?>
                    <input type="number" class="small-input kunst focusable-input" id="KSchuss<?= $i ?>" name="KSchuss<?= $i ?>" min="0" max="100">
                  <?php endfor; ?>
                  <span id="kunstSum" class="total-display ms-1">0</span>
                </div>
              </div>
            </div>

            <div class="col-md-3">
              <div id="glueckSchuesse" class="shooting-category mb-0" data-stich="GLUECK">
                <h6 class="mb-1"><i class="bi bi-clover me-1"></i> Glück</h6>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <input type="number" class="small-input glueck focusable-input" id="GSchuss1" name="GSchuss1" min="0" max="100">
                  <input type="number" class="small-input glueck focusable-input" id="GSchuss2" name="GSchuss2" min="0" max="100">
                  <input type="number" class="small-input glueck focusable-input" id="GSchuss3" name="GSchuss3" min="0" max="100">
                </div>
              </div>
            </div>

          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button type="submit" form="schussForm" class="btn btn-outline-success">
          <i class="bi bi-save me-2"></i>Speichern
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-2"></i>Abbrechen
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
// GLOBALE VARIABLEN für End-Resultate
let endManager;

// ENTER-NAVIGATION zwischen Eingabefeldern
function setupEnterNavigation() {
    // Sammle alle fokussierbaren Inputs in der richtigen Reihenfolge
    const $inputs = $('#schussModal .focusable-input:not(:disabled)');
    
    $inputs.on('keydown', function(e) {
        // Enter-Taste gedrückt (nicht Shift+Enter)
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            
            const currentIndex = $inputs.index(this);
            const nextIndex = currentIndex + 1;
            
            if (nextIndex < $inputs.length) {
                // Zum nächsten Feld springen
                $inputs.eq(nextIndex).focus().select();
            } else {
                // Am Ende angekommen - Fokus auf Speichern-Button
                $('#schussModal button[type="submit"]').focus();
            }
        }
    });
}

// GLOBALE FUNKTION: Stiche basierend auf gelösten Stichen aktivieren/deaktivieren
function updateStichAvailability(geloesteStiche) {
    console.log('=== UPDATE STICH AVAILABILITY ===');
    console.log('Gelöste Stiche:', geloesteStiche);
    
    // Erweitertes Mapping inkl. DIFF und Sonderfelder
    const stichElements = {
        'END': '#endstichSchuesse',
        'SCHWINI_P1': '#schwiniSchuesse',
        'SCHWINI_P2': '#schwiniSchuesse',
        'KUNST': '#kunstSchuesse',
        'GLUECK': '#glueckSchuesse',
        'ZABIG': '#zabigSchuesse',
        'DIFF': '#Differenzler',
        'SIEUNDER': '#sieunderSchuesse'
    };
    
    // Sammle welche HTML-Elemente aktiviert werden sollen
    const activateElements = new Set();
    
    // Spezielle Behandlung für Schwini - prüfen welche Passen gelöst sind
    const hasSchiwiniP1 = geloesteStiche.includes('SCHWINI_P1');
    const hasSchiwiniP2 = geloesteStiche.includes('SCHWINI_P2');
    
    geloesteStiche.forEach(function(stichCode) {
        console.log('  Verarbeite:', stichCode);
        if (stichElements[stichCode]) {
            activateElements.add(stichElements[stichCode]);
            console.log('    → Aktiviere:', stichElements[stichCode]);
        }
    });
    
    console.log('Aktivierte Elemente:', Array.from(activateElements));
    
    // Alle Stich-Elemente durchgehen (inkl. Differenzler und Absenden)
    $('.shooting-category[data-stich], .shooting-category#Differenzler, .shooting-category#Absendenanmeldung').each(function() {
        const $element = $(this);
        const elementId = '#' + $element.attr('id');
        
        // Absenden ist immer aktiviert
        if (elementId === '#Absendenanmeldung') {
            $element.removeClass('disabled');
            $element.find('input').prop('disabled', false);
            return;
        }
        
        // Spezialbehandlung für Schwini
        if (elementId === '#schwiniSchuesse') {
            if (hasSchiwiniP1 || hasSchiwiniP2) {
                $element.removeClass('disabled');
                $('.schwini-passe-1, .schwini-passe-2').removeClass('schwini-pass-disabled');
                
                if (hasSchiwiniP1 && !hasSchiwiniP2) {
                    console.log('  ✓ Schwini: Nur Passe 1 aktiviert');
                    $('.schwini-schuss1').prop('disabled', false);
                    $('.schwini-schuss2').prop('disabled', true).val('');
                    $('.schwini-passe-2').addClass('schwini-pass-disabled');
                } else if (!hasSchiwiniP1 && hasSchiwiniP2) {
                    console.log('  ✓ Schwini: Nur Passe 2 aktiviert');
                    $('.schwini-schuss1').prop('disabled', true).val('');
                    $('.schwini-schuss2').prop('disabled', false);
                    $('.schwini-passe-1').addClass('schwini-pass-disabled');
                } else if (hasSchiwiniP1 && hasSchiwiniP2) {
                    console.log('  ✓ Schwini: Beide Passen aktiviert');
                    $('.schwini-schuss1').prop('disabled', false);
                    $('.schwini-schuss2').prop('disabled', false);
                }
            } else {
                $element.addClass('disabled');
                $element.find('input').prop('disabled', true).val('');
                console.log('  ✗ Schwini: Komplett deaktiviert');
            }
            return;
        }
        
        // Normale Verarbeitung für andere Stiche
        if (activateElements.has(elementId)) {
            $element.removeClass('disabled');
            $element.find('input').prop('disabled', false);
            console.log('  ✓ AKTIVIERT:', elementId);
        } else {
            $element.addClass('disabled');
            $element.find('input').prop('disabled', true).val('');
            console.log('  ✗ DEAKTIVIERT:', elementId);
        }
    });
    
    // Nach dem Update die Enter-Navigation neu aufsetzen
    setupEnterNavigation();
    
    console.log('=== END UPDATE ===');
}

// GLOBALE FUNKTION für Sie und Er Visualisierung
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

// EIGENE Edit-Modal Funktion
function customOpenEditModal(mitgliedID) {
    const selectedYear = $('#yearSelect').val();
    const name = $(`[data-id="${mitgliedID}"]`).closest('tr').find('td:first').text();
    
    console.log('=== ÖFFNE EDIT-MODAL ===');
    console.log('MitgliedID:', mitgliedID);
    console.log('Jahr:', selectedYear);
    
    $('#schussModalLabel').html(`<i class="bi bi-target me-2"></i> Erfassen - ${name}`);
    $('#mitgliedID').val(mitgliedID);
    $('#schussForm')[0].reset();
    $('.total-display').text('0');
    
    // WICHTIG: Alle Stiche erstmal deaktivieren
    $('.shooting-category[data-stich], .shooting-category#Differenzler')
        .addClass('disabled')
        .find('input').prop('disabled', true).val('');
    
    // Absenden ist immer aktiviert
    $('.shooting-category#Absendenanmeldung')
        .removeClass('disabled')
        .find('input').prop('disabled', false);
    
    $('#schussModal').modal('show');
    
    // Daten laden
    $.ajax({
        url: 'endschresultate/load_schussdaten.php',
        type: 'GET',
        data: { 
            mitgliedID: mitgliedID, 
            year: selectedYear
        },
        dataType: 'json',
        success: function(data) {
            console.log('=== SCHUSSDATEN GELADEN ===');
            console.log('Gelöste Stiche:', data.geloesteStiche);
            
            if (data.geloesteStiche && data.geloesteStiche.length > 0) {
                updateStichAvailability(data.geloesteStiche);
            }
            
            // Normale Daten laden
            for (var key in data) {
                if (key !== 'geloesteStiche') {
                    const $field = $('#' + key);
                    if ($field.length) {
                        $field.val(data[key]);
                    }
                }
            }
            
            // Berechnungen aktualisieren
            endManager.calculateAllSums();
            updateSieErUniqueVisualization();
            
            // Fokus auf erstes Feld
            setTimeout(() => {
                $('#schussModal .focusable-input:not(:disabled):first').focus().select();
            }, 300);
            
            console.log('=== MODAL BEREIT ===');
            // Toast entfernt - nicht nötig beim Laden im Modal
        },
        error: function(xhr, status, error) {
            console.error('=== AJAX ERROR ===');
            console.error('Response:', xhr.responseText);
            msvToast('Fehler beim Laden der Schussdaten', 'error');
        }
    });
}

// Initialisierung
$(document).ready(function() {
    endManager = MSV.init('end');
    MSV.enableGlobalScroll();
    
    // Enter-Navigation Setup
    setupEnterNavigation();
    
    // Berechnungen
    $(document).on('input change', '.endschuss, .schwini-schuss1, .schwini-schuss2, .kunst, .zabig, .sieunder', function() {
        endManager.calculateAllSums();
    });
    
    // SieEr Unique-Berechnung
    $(document).on('input change', '.sie-er-schuss', function() {
        updateSieErUniqueVisualization();
    });
    
    // Modal-Event: Enter-Navigation neu aufsetzen wenn Modal geöffnet wird
    $('#schussModal').on('shown.bs.modal', function() {
        setupEnterNavigation();
    });
    
    // Edit-Button Handler
    $(document).off('click.msv', '.edit-btn');
    $(document).off('click', '.edit-btn');
    $(document).on('click', '.edit-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const mitgliedID = $(this).data('id');
        console.log('✏️ Edit-Button geklickt für Mitglied:', mitgliedID);
        customOpenEditModal(mitgliedID);
    });
    
    // Schuss Form Submit
    $('#schussForm').off('submit.msv').off('submit').on('submit', function(e) {
        e.preventDefault();
        
        const $btn = $('#schussModal button[type="submit"]');
        const originalText = $btn.html();
        
        $btn.prop('disabled', true)
            .html('<i class="bi bi-hourglass-split me-2"></i>Speichere...');
        
        const selectedYear = $('#yearSelect').val();
        const formData = $(this).serialize() + '&jahr=' + selectedYear;
        
        $.ajax({
            url: 'endschresultate/save_schuss.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('✓ Speichern erfolgreich');
                msvToast('Resultate erfolgreich gespeichert!', 'success');
                $('#schussModal').modal('hide');
                setTimeout(() => endManager.loadData(selectedYear), 500);
            },
            error: function(xhr, status, error) {
                console.error('✗ Fehler beim Speichern:', error);
                msvToast('Fehler beim Speichern der Resultate', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
});

console.log('✅ ENDRESULTATE SCRIPT GELADEN');
</script>

<?php include 'footer.inc.php'; ?>