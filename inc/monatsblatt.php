<?php
// monatsblatt.php 

include 'dbconnect.inc.php';

// Seitenspezifische Styles definieren
$page_specific_css = "
/* Monatsblatt-spezifische Styles */
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

/* Textarea spezifische Styles */
textarea.form-control {
    resize: vertical;
    min-height: 120px;
}

/* Kompakte Form Layouts */
.form-row-compact {
    margin-bottom: 1rem;
}

.form-row-compact .form-label {
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

.form-row-compact .form-control {
    padding: 0.5rem;
}

/* Responsive für Monatsblatt */
@media (max-width: 768px) {
    .main-card, .controls-card {
        padding: 1rem;
        margin: 0 0 2rem 0;
        border-radius: 0;
    }
}

/* Animationen */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.main-card, .controls-card {
    animation: fadeIn 0.5s ease-out;
}
";

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid" style="max-width: 1200px; padding-left: 1rem; padding-right: 1rem; margin-left: 0;">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                <i class="bi bi-file-earmark-text me-2"></i>
                Monatsblatt exportieren
            </h2>
            <p class="text-muted mb-0">PDF-Export für gewählten Zeitraum erstellen</p>
        </div>
        <div class="col-md-4">
            <div id="message"></div>
        </div>
    </div>

    <!-- Export-Formular -->
    <div class="row">
        <div class="col-md-8">
            <div class="controls-card">
                <h5 class="card-title">
                    <i class="bi bi-gear"></i>
                    Export-Einstellungen
                </h5>
                
                <form id="pdfExportForm" method="GET" action="export_monatsblatt.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div class="row form-row-compact">
                        <!-- Jahr-Auswahl -->
                        <div class="col-md-2">
                            <label for="exportYear" class="form-label">
                                <i class="bi bi-calendar3 me-1"></i>Jahr:
                            </label>
                            <select id="exportYear" name="year" class="form-control">
                                <!-- Optionen werden dynamisch per JS eingefügt -->
                            </select>
                        </div>

                        <!-- Start-Monat -->
                        <div class="col-md-2">
                            <label for="exportStartMonth" class="form-label">
                                <i class="bi bi-calendar-date me-1"></i>Von:
                            </label>
                            <select id="exportStartMonth" name="start_month" class="form-control">
                                <option value="01">Januar</option>
                                <option value="02">Februar</option>
                                <option value="03">März</option>
                                <option value="04">April</option>
                                <option value="05">Mai</option>
                                <option value="06">Juni</option>
                                <option value="07">Juli</option>
                                <option value="08">August</option>
                                <option value="09">September</option>
                                <option value="10">Oktober</option>
                                <option value="11">November</option>
                                <option value="12">Dezember</option>
                            </select>
                        </div>

                        <!-- End-Monat -->
                        <div class="col-md-2">
                            <label for="exportEndMonth" class="form-label">
                                <i class="bi bi-calendar-check me-1"></i>Bis:
                            </label>
                            <select id="exportEndMonth" name="end_month" class="form-control">
                                <option value="01">Januar</option>
                                <option value="02">Februar</option>
                                <option value="03">März</option>
                                <option value="04">April</option>
                                <option value="05">Mai</option>
                                <option value="06">Juni</option>
                                <option value="07">Juli</option>
                                <option value="08">August</option>
                                <option value="09">September</option>
                                <option value="10">Oktober</option>
                                <option value="11">November</option>
                                <option value="12">Dezember</option>
                            </select>
                        </div>

                        <!-- Export Button -->
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-info">
                                <i class="bi bi-file-earmark-pdf me-1"></i> Exportieren
                            </button>
                        </div>
                    </div>

                    <!-- Bemerkungsfeld -->
                    <div class="row form-row-compact">
                        <div class="col-md-8">
                            <label for="bemerkung" class="form-label">
                                <i class="bi bi-chat-text me-1"></i>Bemerkungen:
                            </label>
                            <textarea id="bemerkung" name="bemerkung" rows="6" class="form-control" placeholder="Optional: Zusätzliche Bemerkungen für das Monatsblatt..."></textarea>
                        </div>
                    </div>
                </form>

                <!-- Container für den Download-Link -->
                <div id="pdf-link"></div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>

<script>
$(document).ready(function() {
    // Toast Container hinzufügen falls nicht vorhanden
    if ($('#toast-container').length === 0) {
        $('body').append('<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>');
    }

    // Toast-Funktion
    function showToast(message, type = 'info') {
        const colors = {
            'success': '#28a745',
            'error': '#dc3545',
            'warning': '#ffc107',
            'info': '#6c757d'
        };
        
        const icons = {
            'success': 'bi-check-circle',
            'error': 'bi-exclamation-circle',
            'warning': 'bi-exclamation-triangle',
            'info': 'bi-info-circle'
        };
        
        const toast = $('<div>')
            .css({
                'background-color': colors[type] || colors.info,
                'color': 'white',
                'padding': '12px 20px',
                'margin-bottom': '10px',
                'border-radius': '6px',
                'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                'opacity': '0',
                'transform': 'translateX(100%)',
                'transition': 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
                'font-weight': '500',
                'display': 'flex',
                'align-items': 'center',
                'min-width': '250px'
            })
            .html(`<i class="bi ${icons[type]} me-2"></i>${message}`);
        
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

    ////////////////////////
    // 1) Initialisierung //
    ////////////////////////

    // a) Initialisiere das Jahr-Dropdown für den Export
    function initializeExportYearDropdown() {
        const exportYear = $('#exportYear').empty();
        const currentYear = new Date().getFullYear();
        for (let year = 2024; year <= currentYear; year++) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            exportYear.append(option);
        }
    }
    initializeExportYearDropdown();

    // Standard-Werte setzen
    const currentMonth = new Date().getMonth() + 1;
    $('#exportStartMonth').val(currentMonth.toString().padStart(2, '0'));
    $('#exportEndMonth').val(currentMonth.toString().padStart(2, '0'));

    //////////////////////////////
    // 2) Export via Ajax       //
    //////////////////////////////
    $("#pdfExportForm").on("submit", function(e) {
        e.preventDefault();

        // Button deaktivieren und Loading zeigen
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...');

        // Werte aus den Formularfeldern auslesen
        var year = $("#exportYear").val();
        var startMonth = $("#exportStartMonth").val();
        var endMonth = $("#exportEndMonth").val();
        var bemerkung = $("#bemerkung").val();

        // Validierung
        if (!year || !startMonth || !endMonth) {
            showToast('Bitte alle Pflichtfelder ausfüllen', 'warning');
            $submitBtn.prop('disabled', false).html(originalText);
            return;
        }

        // Debug: Werte in der Konsole ausgeben
        console.log("Export-Parameter:", {
            year: year,
            start_month: startMonth,
            end_month: endMonth,
            bemerkung: bemerkung
        });

        showToast('PDF wird generiert...', 'info');

        // Ajax-Aufruf zum Erzeugen des PDFs
        $.ajax({
            url: 'monatsblatt/export_monatsblatt.php',
            method: 'GET',
            data: {
                year: year,
                start_month: startMonth,
                end_month: endMonth,
                bemerkung: bemerkung,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.pdf_link) {
                    $('#pdf-link').html(`
                        <div class="mt-3">
                            <a href="monatsblatt/${response.pdf_link}" target="_blank">
                                <i class="bi bi-download"></i>
                                Monatsblatt PDF herunterladen
                            </a>
                        </div>
                    `);
                    showToast('PDF erfolgreich generiert!', 'success');
                } else {
                    showToast('PDF konnte nicht generiert werden.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('PDF Export Error:', xhr.responseText);
                showToast('Fehler beim Generieren des PDFs: ' + error, 'error');
            },
            complete: function() {
                // Button wieder aktivieren
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    //////////////////////////////
    // 3) Legacy Support         //
    //////////////////////////////
    function showMessage(message, type) {
        // Konvertiere alte Bootstrap-Klassen zu neuen Toast-Typen
        let toastType = type;
        if (type === 'danger') toastType = 'error';
        showToast(message, toastType);
    }

    // Global verfügbar machen für Legacy-Code
    window.showMessage = showMessage;
});
</script>

<?php
include 'footer.inc.php';
?>