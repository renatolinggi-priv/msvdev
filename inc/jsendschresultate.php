<?php
// jsendschresultate.php - Modernisierte Version nach endresultate.php Vorbild
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in jsendschresultate.php: " . $e->getMessage());
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
              Endschiessen Resultaterfassung für Jungschützen
            </h2>
          </div>
        </div>

        <div class="content-background">
          <form id="jungschuetzenEndresultateForm">
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
                <button type="button" class="btn btn-compact-standard btn-outline-info ges-btn">
                  <i class="bi bi-file-earmark-pdf me-2"></i>PDF Gesamtrangliste
                </button>
              </div>
            </div>

            <!-- Messages -->
            <div id="message" class="mb-2"></div>
            
            <!-- PDF Link -->
            <div id="pdf-link" class="mb-3"></div>

            <!-- Tabelle (einheitlich) -->
            <div class="table-wrapper">
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="jungschuetzenTabelle">
                  <thead>
                    <tr>
                      <th scope="col"><i class="bi bi-person me-1"></i>Jungschütze</th>
                      <th scope="col" class="text-center">Endstich</th>
                      <th scope="col" class="text-center">Schwini</th>
                      <th scope="col" class="text-center">Zabig</th>
                      <th scope="col" class="text-center">Aktionen</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="5" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                        Lade Jungschützen...
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



<!-- Schuss-Modal für Jungschützen -->
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
          <input type="hidden" id="jungschuetzeID" name="jungschuetzeID">
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
                <div class="d-flex align-items-center gap-1">
                  <?php for ($i=1; $i<=6; $i++): ?>
                    <input type="number" class="small-input schwini-schuss" id="P1Schuss<?= $i ?>" name="P1Schuss<?= $i ?>" min="0" max="10">
                  <?php endfor; ?>
                  <span id="schwiniSumme" class="total-display ms-1">0</span>
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

              <div class="row g-2">
                <div class="col-12">
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
// Globale Fehlerbehandlung für besseres Debugging
window.onerror = function(msg, url, line, col, error) {
    console.error('Global error:', msg, 'at', url, 'line', line);
    return false;
};

$(document).ready(function() {
    // === GLOBALE VARIABLEN ===
    let deleteType = '';
    let jungschuetzeIDToDelete = null;

    // === INITIALISIERUNG ===
    initializeToastSystem();
    initializeYearSelect();
    loadJungschuetzen();
    setupEventHandlers();

    // === TOAST SYSTEM ===
    function initializeToastSystem() {
        if ($('#toast-container').length === 0) {
            $('body').append('<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>');
        }
    }

    // Moderne Toast-Funktion
    function showToast(message, type = 'info') {
        const toast = $('<div>')
            .addClass(`toast-message toast-${type}`)
            .html(`<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>${message}`)
            .css({
                'background-color': type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#6c757d',
                'color': 'white',
                'padding': '12px 20px',
                'margin-bottom': '10px',
                'border-radius': '8px',
                'box-shadow': '0 4px 15px rgba(0,0,0,0.15)',
                'opacity': '0',
                'transform': 'translateX(100%)',
                'transition': 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
                'font-weight': '500',
                'display': 'flex',
                'align-items': 'center',
                'min-width': '280px',
                'font-size': '0.9rem'
            });

        $('#toast-container').append(toast);

        setTimeout(() => {
            toast.css({
                'opacity': '1',
                'transform': 'translateX(0)'
            });
        }, 100);

        setTimeout(() => {
            toast.css({
                'opacity': '0',
                'transform': 'translateX(100%)'
            });
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Nachricht anzeigen (wrapper für Toast)
    function showMessage(message, type) {
        const typeMap = {
            'danger': 'error',
            'success': 'success',
            'warning': 'warning',
            'info': 'info'
        };
        showToast(message, typeMap[type] || 'info');
    }

    // === JAHRESAUSWAHL ===
    function initializeYearSelect() {
        const currentYear = new Date().getFullYear();
        const startYear = 2020; // Oder ein anderes Startjahr
        
        let options = '';
        for (let year = currentYear; year >= startYear; year--) {
            const selected = year === currentYear ? 'selected' : '';
            options += `<option value="${year}" ${selected}>${year}</option>`;
        }
        
        $('#yearSelect').html(options);
    }

    // === EVENT HANDLERS ===
    function setupEventHandlers() {
        // Jahr-Auswahl
        $('#yearSelect').on('change', function() {
            const selectedYear = $(this).val();
            showToast(`Lade Daten für Jahr ${selectedYear}...`, 'info');
            loadJungschuetzen();
        });
        // PDF Gesamtrangliste generieren
        $('.ges-btn').on('click', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const originalText = $btn.html();
            
            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...');
            
            $.ajax({
                url: 'jsendsch/generate_pdf_gesamt.php',
                type: 'GET',
                data: {
                    year: $('#yearSelect').val() || new Date().getFullYear()
                },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        const pdfLink = data.pdf_link;
                        $('#pdf-link').html(`
                            <div class="alert alert-info">
                                <i class="bi bi-file-earmark-pdf me-2"></i>
                                <a href="jsendsch/${pdfLink}" target="_blank" class="alert-link">
                                    PDF herunterladen
                                </a>
                            </div>
                        `);
                        showToast('PDF erfolgreich generiert', 'success');
                    } catch (e) {
                        showToast('Fehler beim Verarbeiten der PDF-Antwort', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showToast('Fehler beim Generieren des PDFs: ' + error, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Button "Alle Daten löschen"
        $('#delall-btn').on('click', function() {
            deleteType = 'all';
            $('#confirmModal .modal-body').text('Sind Sie sicher, dass Sie ALLE Jungschützen-Daten löschen möchten?');
            $('#confirmModal').modal('show');
        });

        // Delete-Buttons der einzelnen Jungschützen
        $(document).on('click', '.delete-btn', function() {
            deleteType = 'single';
            jungschuetzeIDToDelete = $(this).data('id');
            const name = $(this).closest('tr').find('td:first').text();
            $('#confirmModal .modal-body').text(`Sind Sie sicher, dass Sie die Daten von "${name}" löschen möchten?`);
            $('#confirmModal').modal('show');
        });

        // Bestätigungs-Button im Modal
        $('#confirmAction').on('click', function() {
            const $btn = $(this);
            const originalText = $btn.html();
            
            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Löschen...');

            if (deleteType === 'all') {
                $.ajax({
                    url: 'jsendsch/delete_endschresultate.php',
                    method: 'POST',
                    data: {
                        csrf_token: $('input[name="csrf_token"]').val(),
                        year: $('#yearSelect').val() || new Date().getFullYear()
                    },
                    success: function(response) {
                        showToast('Alle Daten erfolgreich gelöscht', 'success');
                        $('#confirmModal').modal('hide');
                        setTimeout(() => loadJungschuetzen(), 1000);
                    },
                    error: function(xhr, status, error) {
                        console.error('Delete all error:', error, 'Response:', xhr.responseText);
                        showToast('Fehler beim Löschen aller Daten', 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            } else if (deleteType === 'single' && jungschuetzeIDToDelete !== null) {
                $.ajax({
                    url: 'jsendsch/delete_endschresultat.php',
                    method: 'POST',
                    data: {
                        jungschuetzeID: jungschuetzeIDToDelete,
                        csrf_token: $('input[name="csrf_token"]').val(),
                        year: $('#yearSelect').val() || new Date().getFullYear()
                    },
                    success: function(response) {
                        showToast('Jungschütze erfolgreich gelöscht', 'success');
                        $('#confirmModal').modal('hide');
                        setTimeout(() => loadJungschuetzen(), 1000);
                    },
                    error: function(xhr, status, error) {
                        console.error('Delete single error:', error, 'Response:', xhr.responseText);
                        showToast('Fehler beim Löschen des Jungschützen', 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            }
        });

        // Button "Rangliste"
        $('#redirect-btn').on('click', function() {
            window.location.href = 'jungschuetzen_rangliste.php';
        });

        // WICHTIG: Edit-Button Event Handler korrekt binden
        $(document).on('click', '.edit-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const jungschuetzeID = $(this).data('id');
            const name = $(this).closest('tr').find('td:first').text().trim();
            
            console.log('Edit button clicked for ID:', jungschuetzeID, 'Name:', name);
            
            // Modal-Titel setzen
            $('#schussModalLabel').html(`<i class="bi bi-target me-2"></i> Erfassen - ${name}`);
            
            // ID im versteckten Feld setzen
            $('#jungschuetzeID').val(jungschuetzeID);
            
            // Form zurücksetzen
            $('#schussForm')[0].reset();
            $('.total-display').text('0');
            
            // Daten laden
            $.ajax({
                url: 'jsendsch/load_schussdaten.php',
                type: 'GET',
                data: {
                    jungschuetzeID: jungschuetzeID,
                    year: $('#yearSelect').val() || new Date().getFullYear()
                },
                success: function(response) {
                    console.log('Loaded data:', response);
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        // Daten in die Felder eintragen
                        for (const key in data) {
                            if (data.hasOwnProperty(key) && data[key] !== null) {
                                const $field = $('#' + key);
                                if ($field.length) {
                                    $field.val(data[key]);
                                    console.log('Setting field', key, 'to', data[key]);
                                }
                            }
                        }
                        
                        // Summen berechnen
                        calculateSum();
                        
                        // Modal anzeigen
                        $('#schussModal').modal('show');
                        
                    } catch (e) {
                        console.error('Parse error:', e);
                        showToast('Fehler beim Verarbeiten der Schussdaten', 'error');
                        // Modal trotzdem anzeigen für neue Eingabe
                        $('#schussModal').modal('show');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Load error:', error, 'Status:', xhr.status, 'Response:', xhr.responseText);
                    showToast('Fehler beim Laden der Schussdaten', 'error');
                    // Modal trotzdem anzeigen für neue Eingabe
                    $('#schussModal').modal('show');
                }
            });
        });

        // Schussdaten speichern
        $('#schussForm').on('submit', function(e) {
            e.preventDefault();
            
            const $submitBtn = $('button[form="schussForm"]');
            const originalText = $submitBtn.html();
            
            // Validierung
            const jungschuetzeID = $('#jungschuetzeID').val();
            if (!jungschuetzeID) {
                showToast('Fehler: Keine Jungschützen-ID gefunden', 'error');
                return;
            }
            
            console.log('Saving data for Jungschütze ID:', jungschuetzeID);
            
            $submitBtn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
            
            // Formulardaten sammeln
            const formData = $(this).serialize() + '&year=' + ($('#yearSelect').val() || new Date().getFullYear());
            
            console.log('Form data being sent:', formData);
            
            $.ajax({
                url: 'jsendsch/save_schuss.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    console.log('Save response:', response);
                    showToast('Resultate erfolgreich gespeichert', 'success');
                    $('#schussModal').modal('hide');
                    setTimeout(() => loadJungschuetzen(), 1000);
                },
                error: function(xhr, status, error) {
                    console.error('Save error:', error, 'Status:', xhr.status, 'Response:', xhr.responseText);
                    showToast('Fehler beim Speichern der Schussdaten: ' + error, 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Summe berechnen bei Eingabe
        $(document).on('input change', '.endschuss, .schwini-schuss, .zabig', function() {
            calculateSum();
        });
    }

    // === DATEN LADEN ===
    function loadJungschuetzen() {
        $('#jungschuetzenTabelle tbody').html(`
            <tr>
                <td colspan="8" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                    Lade Jungschützen...
                </td>
            </tr>
        `);

        $.ajax({
            url: 'jsendsch/load_endschresultate.php',
            type: 'GET',
            data: {
                year: $('#yearSelect').val() || new Date().getFullYear()
            },
            timeout: 10000,
            success: function(response) {
                $('#jungschuetzenTabelle tbody').html(response);
                console.log('Jungschützen loaded successfully');
                // Toast nur bei manueller Aktualisierung zeigen, nicht beim initialen Laden
                if ($('#jungschuetzenTabelle').data('loaded')) {
                    showToast('Jungschützen erfolgreich geladen', 'success');
                }
                $('#jungschuetzenTabelle').data('loaded', true);
            },
            error: function(xhr, status, error) {
                console.error('Load error:', status, error, 'Response:', xhr.responseText);
                let errorMessage = 'Fehler beim Laden der Jungschützen';
                
                if (status === 'timeout') {
                    errorMessage = 'Zeitüberschreitung beim Laden der Daten';
                } else if (xhr.status === 404) {
                    errorMessage = 'Datei nicht gefunden (jsendsch/load_endschresultate.php)';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server-Fehler - Bitte Logs prüfen';
                }
                
                $('#jungschuetzenTabelle tbody').html(`
                    <tr>
                        <td colspan="5" class="text-center text-danger py-4">
                            <i class="bi bi-exclamation-triangle me-2"></i> ${errorMessage}
                            <br><small>Status: ${xhr.status} - ${error}</small>
                        </td>
                    </tr>
                `);
                showToast(errorMessage, 'error');
            }
        });
    }

    // === BERECHNUNG DER SUMMEN ===
    function calculateSum() {
        // Endstich
        let endstichSum = 0;
        $('.endschuss').each(function() {
            const val = parseFloat($(this).val()) || 0;
            endstichSum += val;
        });
        $('#endstichSumme').text(endstichSum);

        // Schwini
        let schwiniSum = 0;
        $('.schwini-schuss').each(function() {
            const val = parseFloat($(this).val()) || 0;
            schwiniSum += val;
        });
        $('#schwiniSumme').text(schwiniSum);

        // Zabig (spezielle Berechnung)
        let zabigSum = 0;
        $('.zabig').each(function() {
            let val = parseFloat($(this).val()) || 0;
            if (val > 0) {
                // Wert zwischen 0 und 100 begrenzen
                val = Math.max(0, Math.min(100, val));
                // Punkte basierend auf dem Bereich berechnen
                const score = Math.ceil(val / 10);
                // Punkte auf Skala von 0 bis 10 anpassen
                zabigSum += Math.max(0, Math.min(10, score));
            }
        });
        $('#zabigsum').text(zabigSum);
    }
});
</script>

<?php
include 'footer.inc.php';
?>
