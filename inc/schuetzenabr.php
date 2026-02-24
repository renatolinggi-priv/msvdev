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
                <div class="row mb-4 d-none d-md-flex">
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
                    <!-- Jahr-Auswahl + Aktionen nebeneinander -->
                    <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                            <i class="bi bi-calendar3 me-1"></i>Jahr:
                        </label>
                        <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
                    </div>
                    <!-- Aktionsbereich (Bootstrap Collapse) -->
                    <div class="card action-card mb-0">
                        <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
                             data-bs-toggle="collapse" data-bs-target="#schuetzenabrActions"
                             aria-expanded="false" aria-controls="schuetzenabrActions">
                            <span class="fw-semibold"><i class="bi bi-tools me-2"></i>Aktionen</span>
                            <i class="bi bi-chevron-down action-chevron"></i>
                        </div>
                        <div class="collapse" id="schuetzenabrActions">
                            <div class="card-body pt-2 pb-3 px-3">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <button class="xlsx-btn btn btn-success w-100" type="button">
                                            <i class="bi bi-file-earmark-spreadsheet me-2"></i>Excel generieren
                                        </button>
                                    </div>
                                </div>
                                <div id="excel-link" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                    </div><!-- Ende flex-row Jahr+Aktionen -->
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
        for (let year = currentYear; year >= currentYear - 3; year--) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }
    }
    $(document).ready(function() {

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
                            msvToast('Excel-Datei wurde erfolgreich generiert und heruntergeladen.', 'success');
                            $('#excel-link').empty();
                        } else if (data.error) {
                            msvToast('Fehler: ' + data.error, 'error');
                        }
                    } catch (e) {
                        msvToast('Fehler beim Verarbeiten der Antwort.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    msvToast('Fehler beim Generieren der Excel-Datei: ' + error, 'error');
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
