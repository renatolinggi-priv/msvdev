<?php
// mitgliederfragebogen.php
include 'dbconnect.inc.php';

// Seitenspezifische Styles definieren
$page_specific_css = "
/* Fragebogen-spezifische Styles */
.main-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 2rem;
    margin-bottom: 2rem;
}

.controls-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-left: 4px solid var(--secondary-color);
}

.table-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    border-left: 4px solid var(--info-color);
}

.card-title {
    color: var(--secondary-color);
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Spezielle Fragebogen-Styles */
.fragebogen-form select {
    font-size: 0.75rem;
    padding: 0.15rem 0.3rem;
    max-width: 100px;
    min-width: 70px;
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius);
    transition: all var(--transition-speed) ease;
}

.fragebogen-form select:focus {
    border-color: var(--secondary-color);
    box-shadow: 0 0 0 2px rgba(108, 117, 125, 0.1);
}

.fragebogen-form label {
    font-size: 0.85em;
    font-weight: 500;
}

.fragebogen-form .form-row {
    margin-bottom: 0.3rem;
}

/* Tabellen-Header */
.table-header {
    background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%);
    padding: 1.5rem;
    border-bottom: 1px solid #dee2e6;
    margin: 0;
    color: var(--dark-color);
    font-weight: 600;
    font-size: 1.1rem;
}

/* Tabellen-Styling */
#fragebogenTabelle {
    border: none;
    border-radius: 0;
    overflow: hidden;
    background: white;
    margin-bottom: 0;
}

#fragebogenTabelle thead {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #495057;
    font-size: 0.7em;
    font-weight: 600;
}

#fragebogenTabelle thead th {
    border: none;
    padding: 0.6rem 0.4rem;
    text-align: left;
    vertical-align: middle;
    white-space: nowrap;
    position: sticky !important;
    top: 0 !important;
    z-index: 5 !important;
    background-color: #f8f9fa !important;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    border-bottom: 2px solid #dee2e6 !important;
}

/* Sicherstellen, dass alle Table Headers sticky sind */
.table-wrapper .table thead th,
.table-responsive .table thead th,
.table thead th {
    position: sticky !important;
    top: 0 !important;
    z-index: 5 !important;
    background-color: #f8f9fa !important;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    border-bottom: 2px solid #dee2e6 !important;
    color: #495057 !important;
    font-weight: 600 !important;
}

/* Generelle Regel für alle Tabellen - Sticky Headers */
table thead th {
    position: sticky !important;
    top: 0 !important;
    z-index: 5 !important;
    background-color: #f8f9fa !important;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    border-bottom: 2px solid #dee2e6 !important;
    color: #495057 !important;
    font-weight: 600 !important;
}

#fragebogenTabelle tbody {
    font-size: 0.75em;
}

#fragebogenTabelle tbody td {
    border: 1px solid #e9ecef;
    padding: 0.4rem 0.3rem;
    vertical-align: middle;
    text-align: left;
}

#fragebogenTabelle tbody td:first-child {
    font-weight: 500;
    min-width: 140px;
}

#fragebogenTabelle tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

#fragebogenTabelle tbody tr:hover {
    background-color: #e3f2fd;
    transform: scale(1.001);
    transition: all var(--transition-speed) ease;
}

/* Dropdown-Farben für Teilnahme */
select[name*=\"[mannschaft]\"], 
select[name*=\"[gruppen]\"] {
    font-weight: 500;
    border-width: 1px;
    font-size: 0.75rem;
}

select[name*=\"[mannschaft]\"][style*=\"rgb(212, 237, 218)\"], 
select[name*=\"[gruppen]\"][style*=\"rgb(212, 237, 218)\"] {
    background-color: #d4edda !important;
    border-color: var(--success-color) !important;
    color: #155724 !important;
}

select[name*=\"[mannschaft]\"][style*=\"rgb(248, 215, 218)\"], 
select[name*=\"[gruppen]\"][style*=\"rgb(248, 215, 218)\"] {
    background-color: #f8d7da !important;
    border-color: var(--danger-color) !important;
    color: #721c24 !important;
}

select[name*=\"[mannschaft]\"][style*=\"rgb(255, 243, 205)\"], 
select[name*=\"[gruppen]\"][style*=\"rgb(255, 243, 205)\"] {
    background-color: #fff3cd !important;
    border-color: var(--warning-color) !important;
    color: #856404 !important;
}

/* Dropdown-Farben für Erweitert (Ja/Nein) */
select[name*=\"[erweitert]\"] {
    font-weight: 500;
    border-width: 1px;
    font-size: 0.75rem;
}

select[name*=\"[erweitert]\"][style*=\"rgb(212, 237, 218)\"] {
    background-color: #d4edda !important;
    border-color: var(--success-color) !important;
    color: #155724 !important;
}

select[name*=\"[erweitert]\"][style*=\"rgb(248, 215, 218)\"] {
    background-color: #f8d7da !important;
    border-color: var(--danger-color) !important;
    color: #721c24 !important;
}

/* Loading states */
.btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none !important;
}

.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Loading Spinner */
.loading-spinner {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 3rem;
    color: var(--secondary-color);
}

.spinner-border-custom {
    width: 2rem;
    height: 2rem;
    border: 0.25em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border 0.75s linear infinite;
}

@keyframes spinner-border {
    to { transform: rotate(360deg); }
}

/* PDF Link Styling */
#pdf-link {
    margin-top: 1rem;
}

#pdf-link a {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, var(--success-color), #1e7e34);
    color: white;
    text-decoration: none;
    border-radius: var(--border-radius);
    font-weight: 500;
    transition: all var(--transition-speed) ease;
    box-shadow: var(--box-shadow);
}

#pdf-link a:hover {
    transform: translateY(-2px);
    box-shadow: var(--box-shadow-hover);
    text-decoration: none;
    color: white;
}

#pdf-link a i {
    font-size: 1.1rem;
}

/* Responsive für Fragebogen */
@media (max-width: 768px) {
    .main-card, .controls-card, .table-card {
        padding: 0.8rem;
        margin: 0 0 1.5rem 0;
        border-radius: 0;
    }
    
    #fragebogenTabelle {
        font-size: 0.65rem;
    }
    
    .fragebogen-form select {
        max-width: 80px;
        min-width: 60px;
        font-size: 0.7rem;
        padding: 0.1rem 0.2rem;
    }
    
    #fragebogenTabelle thead th {
        padding: 0.4rem 0.2rem;
        font-size: 0.65em;
    }
    
    #fragebogenTabelle tbody td {
        padding: 0.3rem 0.2rem;
    }
}

/* Animationen */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.main-card, .controls-card, .table-card {
    animation: fadeIn 0.5s ease-out;
}

/* Accessibility */
.btn:focus {
    outline: 2px solid var(--secondary-color);
    outline-offset: 2px;
}

/* Action Button Group */
.action-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}

.action-buttons .btn {
    min-width: 140px;
}

/* Success/Error Messages */
.alert {
    border: none;
    border-radius: var(--border-radius);
    font-weight: 500;
    box-shadow: var(--box-shadow);
}

.alert-success {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
    border-left: 4px solid var(--success-color);
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    color: #721c24;
    border-left: 4px solid var(--danger-color);
}
";

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-12 col-lg-12 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-clipboard-check me-2"></i>
                            Fragebogen erfassen
                        </h2>
                        <p class="text-muted mb-0">Teilnahme und Verfügbarkeit verwalten</p>
                    </div>
                </div>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <form id="fragebogenForm" class="fragebogen-form">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Jahr-Auswahl in eigener Card -->
                        <div class="year-selection-card">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <label for="yearSelect" class="form-label fw-bold">
                                        <i class="bi bi-calendar3 me-1"></i> Jahr auswählen:
                                    </label>
                                    <select id="yearSelect" class="form-select">
                                        <!-- Optionen werden per JavaScript eingefügt -->
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Button Toolbar -->
                        <div class="button-toolbar">
                            <div class="button-group">
                                <button type="submit" class="btn btn-compact-standard btn-outline-success">
                                    <i class="bi bi-save me-2"></i>
                                    Speichern
                                </button>
                                <button id="delete-btn" type="button" class="btn btn-compact-standard btn-outline-danger">
                                    <i class="bi bi-trash me-2"></i>
                                    Löschen
                                </button>
                                <button class="btn btn-compact-standard btn-outline-info pdf-btn" type="button">
                                    <i class="bi bi-file-earmark-pdf me-2"></i>
                                    PDF exportieren
                                </button>
                            </div>
                            <div id="pdf-link" class="ms-auto"></div>
                        </div>

                        <!-- Nachrichten Container -->
                        <div id="message"></div>

                        <!-- Tabelle -->
                        <div class="table-wrapper">
                            <h5 class="table-title">
                                <i class="bi bi-table me-2"></i>
                                Teilnahme-Übersicht
                            </h5>

                            <div class="table-responsive">
                                <table class="table table-bordered mb-0" id="fragebogenTabelle">
                                    <thead>
                                        <tr>
                                            <td colspan="100%" class="text-center">
                                                <div class="loading-spinner">
                                                    <div class="spinner-border-custom me-3"></div>
                                                    Lade Fragebogen...
                                                </div>
                                            </td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dynamisch per AJAX -->
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

<!-- Modal zur Bestätigung für das Löschen aller Daten -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="bi bi-exclamation-triangle"></i> Bestätigung erforderlich
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle text-warning me-3" style="font-size: 2rem;"></i>
                    <div>
                        <strong>Achtung!</strong><br>
                        Möchtest du wirklich alle Fragebogen-Daten für das ausgewählte Jahr löschen?
                        Diese Aktion kann nicht rückgängig gemacht werden.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-compact-standard btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-compact-standard btn-outline-danger" id="confirmAction">
                    <i class="bi bi-trash me-1"></i>Löschen bestätigen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>

<script>
    $(document).ready(function () {
        let currentYear = new Date().getFullYear();
        let basePath = ''; // Falls du einen Pfadprefix hast

        // Toast Container hinzufügen falls nicht vorhanden
        if ($('#toast-container').length === 0) {
            $('body').append('<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>');
        }

        // Erweiterte Toast-Funktion mit mehr Optionen
        function showToast(message, type = 'info', duration = 4000) {
            const colors = {
                'success': '#28a745',
                'error': '#dc3545',
                'warning': '#ffc107',
                'info': '#6c757d'
            };

            const icons = {
                'success': 'bi-check-circle-fill',
                'error': 'bi-exclamation-circle-fill',
                'warning': 'bi-exclamation-triangle-fill',
                'info': 'bi-info-circle-fill'
            };

            // Toast-ID für Duplikate-Vermeidung
            const toastId = btoa(message + type).replace(/[^a-zA-Z0-9]/g, '').substring(0, 10);

            // Prüfe ob dieser Toast bereits angezeigt wird
            if ($(`#toast-${toastId}`).length > 0) {
                return;
            }

            const toast = $('<div>')
                .attr('id', `toast-${toastId}`)
                .css({
                    'background-color': colors[type] || colors.info,
                    'color': 'white',
                    'padding': '14px 20px',
                    'margin-bottom': '10px',
                    'border-radius': '8px',
                    'box-shadow': '0 6px 16px rgba(0,0,0,0.2)',
                    'opacity': '0',
                    'transform': 'translateX(100%)',
                    'transition': 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
                    'font-weight': '500',
                    'display': 'flex',
                    'align-items': 'center',
                    'min-width': '280px',
                    'max-width': '400px',
                    'word-wrap': 'break-word',
                    'position': 'relative',
                    'overflow': 'hidden'
                })
                .html(`
            <i class="bi ${icons[type]} me-2" style="font-size: 1.2rem;"></i>
            <span style="flex: 1;">${message}</span>
            <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.8rem;"></button>
        `);

            // Progress Bar für Timing-Visualisierung
            const progressBar = $('<div>')
                .css({
                    'position': 'absolute',
                    'bottom': '0',
                    'left': '0',
                    'height': '3px',
                    'background-color': 'rgba(255,255,255,0.3)',
                    'width': '100%',
                    'transition': `width ${duration}ms linear`
                });

            toast.append(progressBar);

            // Close Button Funktionalität
            toast.find('.btn-close').on('click', function () {
                hideToast(toast);
            });

            $('#toast-container').prepend(toast);

            // Animation starten
            setTimeout(() => {
                toast.css({
                    'opacity': '1',
                    'transform': 'translateX(0)'
                });
                progressBar.css('width', '0%');
            }, 100);

            // Auto-Hide Timer
            const hideTimer = setTimeout(() => {
                hideToast(toast);
            }, duration);

            // Pause bei Hover
            toast.on('mouseenter', function () {
                clearTimeout(hideTimer);
                progressBar.css('transition', 'none');
            });

            toast.on('mouseleave', function () {
                const remainingTime = parseFloat(progressBar.css('width')) / parseFloat(toast.css('width')) * duration;
                progressBar.css('transition', `width ${remainingTime}ms linear`);
                progressBar.css('width', '0%');
                setTimeout(() => hideToast(toast), remainingTime);
            });
        }

        // Helper-Funktion zum Ausblenden
        function hideToast(toast) {
            toast.css({
                'opacity': '0',
                'transform': 'translateX(100%)'
            });
            setTimeout(() => toast.remove(), 300);
        }

        // Spezielle Toast-Varianten für häufige Fälle
        function showSuccessToast(message) {
            showToast(message, 'success');
        }

        function showErrorToast(message) {
            showToast(message, 'error');
        }

        function showWarningToast(message) {
            showToast(message, 'warning');
        }

        function showInfoToast(message) {
            showToast(message, 'info');
        }

        // Loading Toast mit Spinner
        function showLoadingToast(message) {
            const loadingToast = $('<div>')
                .attr('id', 'loading-toast')
                .css({
                    'background-color': '#6c757d',
                    'color': 'white',
                    'padding': '14px 20px',
                    'margin-bottom': '10px',
                    'border-radius': '8px',
                    'box-shadow': '0 6px 16px rgba(0,0,0,0.2)',
                    'display': 'flex',
                    'align-items': 'center',
                    'min-width': '280px'
                })
                .html(`
            <div class="spinner-border spinner-border-sm me-2" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <span>${message}</span>
        `);

            $('#toast-container').prepend(loadingToast);
            return loadingToast;
        }

        function hideLoadingToast() {
            $('#loading-toast').fadeOut(300, function () {
                $(this).remove();
            });
        }

        // Funktion: Aktualisiert die Hintergrundfarbe der Teilnahme-Dropdowns
        function updateSelectColorForParticipation(el) {
            let val = $(el).val();
            if (val === 'teil') {
                $(el).css('background-color', '#d4edda'); // leicht grün
            } else if (val === 'nicht') {
                $(el).css('background-color', '#f8d7da'); // leicht rot
            } else if (val === 'evtl') {
                $(el).css('background-color', '#fff3cd'); // leicht gelb
            } else {
                $(el).css('background-color', '');
            }
        }

        // Funktion: Aktualisiert die Hintergrundfarbe der erweiterten Dropdowns (Ja/Nein)
        function updateSelectColorForErweitert(el) {
            let val = $(el).val();
            if (val === 'ja') {
                $(el).css('background-color', '#d4edda'); // leicht grün
            } else if (val === 'nein') {
                $(el).css('background-color', '#f8d7da'); // leicht rot
            } else {
                $(el).css('background-color', '');
            }
        }

        // 1) Jahr-Dropdown initialisieren
        function initializeYearDropdown() {
            let yearSelect = $('#yearSelect');
            // z.B. von 2024 bis currentYear+1
            for (let y = 2024; y < currentYear + 1; y++) {
                let option = $('<option></option>').val(y).text(y);
                if (y === currentYear) {
                    option.prop('selected', true);
                }
                yearSelect.append(option);
            }
        }

        // 2) Formular via Ajax laden
        function loadFragebogen(year) {
            // Loading State anzeigen
            $('#fragebogenTabelle thead').html(`
            <tr>
                <td colspan="100%" class="text-center">
                    <div class="loading-spinner">
                        <div class="spinner-border-custom me-3"></div>
                        Lade Fragebogen für ${year}...
                    </div>
                </td>
            </tr>
        `);
            $('#fragebogenTabelle tbody').empty();

            $.ajax({
                url: basePath + 'fragebogen/load_fragebogen_form.php',
                type: 'GET',
                data: { year: year },
                dataType: 'json',
                success: function (response) {
                    if (response.thead && response.tbody) {
                        $('#fragebogenTabelle thead').html(response.thead);
                        $('#fragebogenTabelle tbody').html(response.tbody);
                        console.log("Tabelle nach Insert:", $('#fragebogenTabelle').html());

                        // Setze Hintergrundfarbe für alle Teilnahme-Dropdowns
                        $('select[name*="[mannschaft]"], select[name*="[gruppen]"]').each(function () {
                            updateSelectColorForParticipation(this);
                        });
                        // Setze Hintergrundfarbe für alle erweiterten Dropdowns
                        $('select[name*="[erweitert]"]').each(function () {
                            updateSelectColorForErweitert(this);
                        });

                        showToast('Fragebogen erfolgreich geladen', 'success');
                    } else {
                        console.warn("Keine thead/tbody-Daten gefunden.");
                        $('#fragebogenTabelle thead').html('<tr><th>Keine Daten verfügbar</th></tr>');
                        $('#fragebogenTabelle tbody').html('<tr><td>Keine Daten für dieses Jahr gefunden</td></tr>');
                        showToast('Keine Daten für dieses Jahr gefunden', 'warning');
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Fehler beim Laden des Fragebogens:", error);
                    $('#fragebogenTabelle thead').html('<tr><th class="text-danger">Fehler beim Laden</th></tr>');
                    $('#fragebogenTabelle tbody').html('<tr><td class="text-danger">Fehler beim Laden der Daten</td></tr>');
                    showToast("Fehler beim Laden des Fragebogens: " + error, 'error');
                }
            });
        }

        // 3) JahrDropdown-Change
        $('#yearSelect').on('change', function () {
            let selectedYear = $(this).val();
            loadFragebogen(selectedYear);
        });

        // Initialisierung
        initializeYearDropdown();
        loadFragebogen(currentYear);

        // 4) Event-Listener: Bei Änderung der Teilnahmefelder Hintergrundfarbe aktualisieren
        $(document).on('change', 'select[name*="[mannschaft]"], select[name*="[gruppen]"]', function () {
            updateSelectColorForParticipation(this);
        });

        // 5) Event-Listener: Bei Änderung der erweiterten Felder (Ja/Nein) Hintergrundfarbe aktualisieren
        $(document).on('change', 'select[name*="[erweitert]"]', function () {
            updateSelectColorForErweitert(this);
        });

        // 6) Formular absenden => Speichern
        $('#fragebogenForm').on('submit', function (e) {
            e.preventDefault();

            let $submitBtn = $(this).find('button[type="submit"]');
            let originalText = $submitBtn.html();
            $submitBtn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

            let selectedYear = $('#yearSelect').val();
            let formData = $(this).serialize();
            formData += '&year=' + selectedYear;

            $.ajax({
                url: basePath + 'fragebogen/save_fragebogen.php',
                type: 'POST',
                data: formData,
                success: function (response) {
                    console.log("Speicher-Antwort:", response);
                    showToast("Fragebogen erfolgreich gespeichert!", 'success');
                    loadFragebogen(selectedYear); // Tabelle neu laden
                },
                error: function (xhr, status, error) {
                    console.error("Fehler beim Speichern:", error);
                    showToast("Fehler beim Speichern des Fragebogens!", 'error');
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });

        // 7) PDF-Generierung
        $('.pdf-btn').on('click', function (e) {
            e.preventDefault();

            let $btn = $(this);
            let originalText = $btn.html();
            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...');

            var selectedYear = $('#yearSelect').val();
            $.ajax({
                url: 'fragebogen/generate_pdf.php',
                type: 'GET',
                dataType: 'json',
                data: { year: selectedYear },
                success: function (response) {
                    if (response.pdf_link) {
                        $('#pdf-link').html(`
                        <a href="${response.pdf_link}" target="_blank">
                            <i class="bi bi-download"></i>
                            PDF herunterladen (${selectedYear})
                        </a>
                    `);
                        showToast('PDF erfolgreich generiert!', 'success');
                    } else {
                        showToast('PDF konnte nicht generiert werden.', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    showToast('Fehler beim Generieren des PDFs: ' + error, 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });

        // 8) Event-Listener für den "Löschen" Button
        $('#delete-btn').on('click', function (e) {
            e.preventDefault();
            $('#confirmModal').modal('show');
        });

        // 9) Event-Listener für den Bestätigungs-Button im Modal
        $('#confirmAction').on('click', function () {
            let $btn = $(this);
            let originalText = $btn.html();
            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');

            $.ajax({
                url: 'fragebogen/delete_fragebogen.php',
                method: 'POST',
                data: {
                    year: $('#yearSelect').val(),
                    csrf_token: $('input[name="csrf_token"]').val()
                },
                success: function (response) {
                    console.log('Alle Einträge gelöscht');
                    showToast('Alle Einträge erfolgreich gelöscht', 'success');
                    loadFragebogen($('#yearSelect').val()); // Ergebnisse neu laden
                },
                error: function (xhr, status, error) {
                    console.error('Fehler beim Löschen der aktuellen Einträge:', error);
                    showToast('Fehler beim Löschen der Einträge', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalText);
                    $('#confirmModal').modal('hide');
                }
            });
        });

        // Legacy Message-Funktion für Kompatibilität (falls noch verwendet)
        function showMessage(msg, type) {
            // Konvertiere alte Bootstrap-Klassen zu neuen Toast-Typen
            let toastType = type;
            if (type === 'danger') toastType = 'error';
            showToast(msg, toastType);
        }

        // Global verfügbar machen für Legacy-Code
        window.showMessage = showMessage;
    });
</script>

<?php
include 'footer.inc.php';
?>