<?php
// kantiabr.php - Kantonalstich Ranglisten im Stil von backup_restore.php
include 'dbconnect.inc.php';

// Seitenspezifische Styles definieren
$page_specific_css = "
/* Kantonalstich spezifische Styles */
.main-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 2rem;
    margin-bottom: 2rem;
}

.sidebar-card,
.table-card,
.export-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    margin-bottom: 1.25rem;
}

.sidebar-card { border-left: 4px solid var(--info-color); }
.table-card { border-left: 4px solid var(--primary-color); }
.export-card { border-left: 4px solid var(--success-color); }

.card-title {
    color: var(--secondary-color);
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}

/* Tabellen Styling */
.table-card table {
    margin-bottom: 0;
}

.table thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: var(--secondary-color);
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
    transition: background-color 0.15s ease;
}

.table td {
    vertical-align: middle;
}

/* Rang-Spalte hervorheben */
.table tbody td:first-child {
    font-weight: 600;
    color: var(--primary-color);
}

/* Total-Spalte hervorheben */
.table tbody td:last-child {
    font-weight: 600;
    background-color: #f8f9fa;
}

/* Medaillen-Ränge */
.table tbody tr:nth-child(1) td:first-child { color: #FFD700; } /* Gold */
.table tbody tr:nth-child(2) td:first-child { color: #C0C0C0; } /* Silber */
.table tbody tr:nth-child(3) td:first-child { color: #CD7F32; } /* Bronze */

/* Button Styles */
.btn-compact-standard {
    padding: .375rem .75rem;
    font-size: .875rem;
}

/* Export Links */
#pdf-link a {
    display: inline-block;
    margin-top: 1rem;
    padding: .5rem 1rem;
    background: var(--success-color);
    color: white;
    text-decoration: none;
    border-radius: var(--border-radius);
    transition: all .2s ease;
}

#pdf-link a:hover {
    background: var(--success-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,.1);
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.sidebar-card, .table-card, .export-card {
    animation: fadeIn .3s ease-out;
}

/* Loading State */
.loading-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid #f3f3f3;
    border-top: 2px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive Tables */
@media (max-width: 768px) {
    .table-card {
        overflow-x: auto;
    }
    
    .table {
        font-size: 0.875rem;
    }
}
";

// Header einbinden
include 'header.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-10 col-lg-11 col-12 ps-0">
      <!-- Außen-Container -->
      <div class="main-content-wrapper">
        <!-- Header-Zeile -->
        <div class="row mb-4">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-trophy me-2"></i> Kantonalstich Ranglisten
            </h2>
            <p class="text-muted mb-0">Übersicht der Kantonalstich-Resultate und SKSG Abrechnung</p>
          </div>
        </div>

        <!-- Weißer Hintergrund-Container -->
        <div class="content-background">
          <!-- Steuerung -->
          <div class="row g-3 mb-4">
            <div class="col-lg-8">
              <div class="sidebar-card">
                <h5 class="card-title">
                  <i class="bi bi-calendar3"></i>
                  Jahr-Auswahl & Aktionen
                </h5>
                <div class="d-flex flex-wrap align-items-center gap-3">
                  <div class="d-flex align-items-center gap-2">
                    <label for="yearSelect" class="form-label mb-0">
                      <strong>Jahr:</strong>
                    </label>
                    <select id="yearSelect" class="form-select form-select-sm" style="width: auto;">
                      <!-- Optionen werden per JavaScript eingefügt -->
                    </select>
                  </div>
                  <button id="redirect-btn" class="btn btn-compact-standard btn-outline-success">
                    <i class="bi bi-pencil-square me-1"></i> Bearbeiten
                  </button>
                  <button id="reload-btn" class="btn btn-compact-standard btn-outline-secondary">
                    <i class="bi bi-arrow-clockwise me-1"></i> Aktualisieren
                  </button>
                </div>
              </div>
            </div>

            <div class="col-lg-4">
              <div class="export-card">
            <h5 class="card-title">
              <i class="bi bi-file-earmark-text"></i>
              SKSG Abrechnung
            </h5>
            <div class="row">
              <div class="col-12 mb-3">
                <a href="https://www.sksg.ch/spezialstich/" target="_blank" class="text-decoration-none">
                  <i class="bi bi-box-arrow-up-right me-1"></i> SKSG Spezialstich Website
                </a>
              </div>
              <div class="col-md-6 col-lg-4 mb-3">
                <button class="btn btn-outline-danger  pdf-btn">
                  <i class="bi bi-file-pdf me-2"></i>PDF
                </button>
              </div>
              <div class="col-md-6 col-lg-4 mb-3">
                <button class="btn btn-outline-success  word-btn">
                  <i class="bi bi-file-earmark-excel me-2"></i>Export
                </button>
              </div>
            </div>
            <div id="pdf-link" class="mt-3"></div>
          </div>
            </div>
          </div>

          <!-- Kategorie A Tabelle -->
          <div class="table-card">
            <h5 class="card-title">
              <i class="bi bi-award"></i>
              Kantonalstich Kategorie A
            </h5>
            <div class="table-responsive">
              <table class="table table-hover" id="KantonalA">
                <thead>
                  <tr>
                    <th scope="col" style="width: 60px;">Rang</th>
                    <th scope="col" style="min-width: 150px;">Name</th>
                    <th scope="col" class="text-center">Passe 1</th>
                    <th scope="col" class="text-center">Passe 2</th>
                    <th scope="col" class="text-center">Passe 3</th>
                    <th scope="col" class="text-center">Passe 4</th>
                    <th scope="col" class="text-center">Passe 5</th>
                    <th scope="col" class="text-center">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td colspan="8" class="text-center text-muted py-3">
                      <div class="loading-spinner me-2"></div> Lade Daten...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Kategorie B Tabelle -->
          <div class="table-card">
            <h5 class="card-title">
              <i class="bi bi-award-fill"></i>
              Kantonalstich Kategorie B
            </h5>
            <div class="table-responsive">
              <table class="table table-hover" id="KantonalB">
                <thead>
                  <tr>
                    <th scope="col" style="width: 60px;">Rang</th>
                    <th scope="col" style="min-width: 150px;">Name</th>
                    <th scope="col" class="text-center">Passe 1</th>
                    <th scope="col" class="text-center">Passe 2</th>
                    <th scope="col" class="text-center">Passe 3</th>
                    <th scope="col" class="text-center">Passe 4</th>
                    <th scope="col" class="text-center">Passe 5</th>
                    <th scope="col" class="text-center">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td colspan="8" class="text-center text-muted py-3">
                      <div class="loading-spinner me-2"></div> Lade Daten...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Nachricht Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
  <div id="messageToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto">System</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body"></div>
  </div>
</div>

<script>
$(document).ready(function() {
    var basePath = '';
    let toastElement = document.getElementById('messageToast');
    let toast = new bootstrap.Toast(toastElement);

    // Initialisierung des Jahres-Dropdowns
    function initializeYearDropdown() {
        const yearSelect = $('#yearSelect').empty();
        const currentYear = new Date().getFullYear();
        for (let year = 2024; year <= currentYear; year++) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }
    }

    // Status-Nachricht anzeigen
    function setStatus(message, type = 'info') {
        const icons = {
            'success': 'bi-check-circle text-success',
            'error': 'bi-x-circle text-danger',
            'warning': 'bi-exclamation-triangle text-warning',
            'info': 'bi-info-circle text-info',
            'loading': 'loading-spinner'
        };
        
        const icon = type === 'loading' ? 
            '<div class="loading-spinner me-2"></div>' : 
            `<i class="bi ${icons[type]} me-2"></i>`;
        
        $('#status-message').html(icon + message);
    }

    // Toast-Nachricht anzeigen
    function showToast(message, type = 'info') {
        const toastBody = $('#messageToast .toast-body');
        const toastHeader = $('#messageToast .toast-header');
        
        // Farbe basierend auf Typ
        toastHeader.removeClass('bg-success bg-danger bg-warning bg-info').addClass(`bg-${type} text-white`);
        toastBody.text(message);
        toast.show();
    }

    // Kantiresultate A laden
    function loadKantonala() {
        var selectedYear = $('#yearSelect').val();
        setStatus('Lade Kategorie A...', 'loading');
        
        $.ajax({
            url: basePath + 'kantirang/load_kantonal.php',
            type: 'GET',
            data: { year: selectedYear, kat: 'A' },
            success: function(response) {
                $('#KantonalA tbody').html(response);
                // Zentriere numerische Werte
                $('#KantonalA tbody td:not(:nth-child(1)):not(:nth-child(2))').addClass('text-center');
            },
            error: function(xhr, status, error) {
                console.error('Error loading Kantonal A:', error);
                $('#KantonalA tbody').html(
                    '<tr><td colspan="8" class="text-center text-danger py-3">' +
                    '<i class="bi bi-x-circle me-2"></i>Fehler beim Laden der Daten</td></tr>'
                );
                setStatus('Fehler beim Laden', 'error');
            }
        });
    }

    // Kantiresultate B laden
    function loadKantonalb() {
        var selectedYear = $('#yearSelect').val();
        
        $.ajax({
            url: basePath + 'kantirang/load_kantonal.php',
            type: 'GET',
            data: { year: selectedYear, kat: 'B' },
            success: function(response) {
                $('#KantonalB tbody').html(response);
                // Zentriere numerische Werte
                $('#KantonalB tbody td:not(:nth-child(1)):not(:nth-child(2))').addClass('text-center');
                setStatus('Daten erfolgreich geladen', 'success');
            },
            error: function(xhr, status, error) {
                console.error('Error loading Kantonal B:', error);
                $('#KantonalB tbody').html(
                    '<tr><td colspan="8" class="text-center text-danger py-3">' +
                    '<i class="bi bi-x-circle me-2"></i>Fehler beim Laden der Daten</td></tr>'
                );
                setStatus('Fehler beim Laden', 'error');
            }
        });
    }

    // PDF-Button Handler
    $(document).on('click', '.pdf-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
        
        var selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'kantirang/generate_pdf.php',
            type: 'GET',
            dataType: 'json',
            data: { year: selectedYear },
            success: function(response) {
                if (response && response.pdf_link) {
                    $('#pdf-link').html(
                        '<div class="alert alert-success d-flex align-items-center">' +
                        '<i class="bi bi-check-circle-fill me-2"></i>' +
                        '<div>PDF erstellt: <a href="' + response.pdf_link + '" target="_blank" class="alert-link">' +
                        '<i class="bi bi-download me-1"></i>Herunterladen</a></div></div>'
                    );
                    showToast('PDF erfolgreich generiert', 'success');
                } else {
                    $('#pdf-link').html(
                        '<div class="alert alert-danger">' +
                        '<i class="bi bi-x-circle-fill me-2"></i>Fehler beim Generieren der PDF-Datei</div>'
                    );
                    showToast('PDF-Generierung fehlgeschlagen', 'danger');
                }
            },
            error: function(xhr, status, error) {
                $('#pdf-link').html(
                    '<div class="alert alert-danger">' +
                    '<i class="bi bi-x-circle-fill me-2"></i>Fehler: ' + error + '</div>'
                );
                showToast('Fehler: ' + error, 'danger');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Word/Excel-Button Handler
    $(document).on('click', '.word-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
        
        var selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'kantirang/generate_word.php',
            type: 'GET',
            data: { year: selectedYear },
            success: function(response) {
                var data = JSON.parse(response);
                var wordLink = data.pdf_link;
                $('#pdf-link').html(
                    '<div class="alert alert-success d-flex align-items-center">' +
                    '<i class="bi bi-check-circle-fill me-2"></i>' +
                    '<div>Excel erstellt: <a href="' + wordLink + '" target="_blank" class="alert-link">' +
                    '<i class="bi bi-download me-1"></i>Herunterladen</a></div></div>'
                );
                showToast('Excel erfolgreich generiert', 'success');
            },
            error: function(xhr, status, error) {
                $('#pdf-link').html(
                    '<div class="alert alert-danger">' +
                    '<i class="bi bi-x-circle-fill me-2"></i>Fehler: ' + error + '</div>'
                );
                showToast('Fehler: ' + error, 'danger');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Event Handler für Jahr-Dropdown
    $('#yearSelect').on('change', function() {
        loadKantonala();
        loadKantonalb();
        $('#pdf-link').empty(); // Clear previous download links
    });

    // Reload Button
    $('#reload-btn').on('click', function() {
        loadKantonala();
        loadKantonalb();
        showToast('Daten aktualisiert', 'info');
    });

    // Redirect-Button
    $('#redirect-btn').on('click', function() {
        window.location.href = 'https://jahresmeisterschaft.msvwilen.ch/inc/kantiresultate.php';
    });

    // Initialisierung
    initializeYearDropdown();
    loadKantonala();
    loadKantonalb();
});
</script>

<?php include 'footer.inc.php'; ?>