<?php
//schuetzenabr.php
include 'dbconnect.inc.php';


// Alle Styles sind jetzt zentral in msv-styles.css verwaltet
$page_specific_css = '';
include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
?>

<!-- Schuetzenabr.php HTML-Gerüst nach heimrang.php Vorbild -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <!-- Äusserer weisser Container -->
            <div class="main-content-wrapper content-width-wide">
                <!-- Header ausserhalb des inneren Containers -->
                <?php
$page_title = 'Schützenabrechnung';
$page_show_mobile = true;
ob_start(); ?>
<button type="button" class="btn-help" data-help="schuetzenabr.uebersicht" aria-label="Hilfe"></button>
<label for="yearSelect" class="visually-hidden">Jahr</label>
<select id="yearSelect" class="form-select form-select-sm"></select>
<?php $page_title_after = ob_get_clean();
ob_start(); ?>
<button type="button" class="xlsx-btn btn btn-outline-info btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</button>
<?php $page_actions = ob_get_clean();
include 'partials/page_header.inc.php'; ?>
                <!-- Weisser Hintergrund-Container -->
                <div class="content-background">
                <form id="schuetzenabr-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div id="excel-link"></div>
                    <!-- Info-Bereich -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-info-circle me-2"></i>
                            Informationen zur Schützenabrechnung <button type="button" class="btn-help" data-help="schuetzenabr.inhalt" aria-label="Hilfe"></button>
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
        msvJahrAuswahl('#yearSelect');
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
