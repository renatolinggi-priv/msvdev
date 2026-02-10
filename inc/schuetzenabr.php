<?php
//schuetzenabr.php
include 'dbconnect.inc.php';

// Session-Kontrolle wie in jmresultate.php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Alle Styles sind jetzt zentral in msv-styles.css verwaltet
$page_specific_css = '';
include 'header.inc.php';
?>

<!-- Schuetzenabr.php HTML-Gerüst nach heimrang.php Vorbild -->
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-10 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i>
                            Schützenabrechnung
                        </h2>
                    </div>
                </div>
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                <form id="schuetzenabr-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <!-- Jahr-Auswahl in eigener Card -->
                    <div class="year-selection-card">
                        <div class="row align-items-center">
                            <div class="col-md-5">
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
                            <button class="btn btn-compact-standard btn-outline-success xlsx-btn" type="button">
                                <i class="bi bi-file-earmark-spreadsheet me-2"></i>
                                Excel generieren
                            </button>
                        </div>
                        <div id="excel-link"></div>
                    </div>
                    <!-- Nachrichten Container -->
                    <div id="message"></div>
                    <!-- Info-Bereich -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-info-circle me-2"></i>
                            Informationen zur Schützenabrechnung
                        </h5>
                        <div class="alert alert-info">
                            <h6 class="fw-bold">Was ist enthalten:</h6>
                            <ul class="mb-2">
                                <li><strong>Mitgliederbeitrag:</strong> CHF 10.- (Ehrenmitglieder: CHF 0.-)</li>
                                <li><strong>Kantonalstich:</strong> Je nach Teilnahme</li>
                                <li><strong>Königskränze:</strong> Endstich, Kunststich, Endschiessen, Heimmeisterschaft</li>
                            </ul>
                            <p class="mb-0">
                                <i class="bi bi-download me-1"></i>
                                Das Excel wird für jedes Mitglied ein separates Tabellenblatt erstellen.
                            </p>
                        </div>
                    </div>
                </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
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
    $(document).ready(function() {

        // Nachricht anzeigen
        function showMessage(message, type) {
            var messageDiv = $('#message');
            messageDiv.removeClass().addClass('alert alert-' + type).text(message).show();
            setTimeout(function () {
                messageDiv.fadeOut();
            }, 3000);
        }

        // Excel-Button Handler
        $(document).on('click', '.xlsx-btn', function(e) {
            e.preventDefault();
            var selectedYear = $('#yearSelect').val();

            // Button deaktivieren während der Verarbeitung
            $(this).prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Generiere...');
            $.ajax({
                url: 'schuetzenabr/generate_schuetzenabr_xlsx.php',
                type: 'GET',
                data: {
                    year: selectedYear
                },
                success: function(response) {
                    try {
                        var data = JSON.parse(response);
                        if (data.excel_link) {

                            // Excel direkt herunterladen
                            const link = document.createElement('a');
                            link.href = 'schuetzenabr/' + data.excel_link;
                            link.download = data.excel_link.split('/').pop();
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            showMessage('Excel-Datei wurde erfolgreich generiert und heruntergeladen.', 'success');
                            $('#excel-link').empty();
                        } else if (data.error) {
                            showMessage('Fehler: ' + data.error, 'danger');
                        }
                    } catch (e) {
                        showMessage('Fehler beim Verarbeiten der Antwort.', 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    showMessage('Fehler beim Generieren der Excel-Datei: ' + error, 'danger');
                },
                complete: function() {

                    // Button wieder aktivieren
                    $('.xlsx-btn').prop('disabled', false).html('<i class="bi bi-file-earmark-spreadsheet me-2"></i>Excel generieren');
                }
            });
        });

        // Initialisierung beim Laden der Seite
        initializeYearDropdown();
    });
</script>

<?php
include 'footer.inc.php';
?>
