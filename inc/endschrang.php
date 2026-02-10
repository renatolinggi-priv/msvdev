<?
//endschrang.php
include 'dbconnect.inc.php';

// Session-Kontrolle wie in jmresultate.php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Alle Styles sind jetzt zentral in msv-styles.css verwaltet
$page_specific_css = '';
include 'header.inc.php';
?>

<style>
/* Rotating Icon Animation statt Spinner */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.rotating-icon {
    display: inline-block;
    animation: spin 1s linear infinite;
    font-size: 0.875rem;
}

/* Sicherstellen dass der Button nicht wächst */
.btn-compact {
    white-space: nowrap;
    overflow: hidden;
}

</style>

<!-- Header -->
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-10 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-trophy me-2"></i>
                            Endschiessen Ranglisten
                        </h2>
                    </div>
                </div>
                <!-- Weißer Container für den Rest -->
                <div class="content-background">
                <!-- Jahr-Auswahl -->
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
                <!-- Dashboard Kachel-Grid -->
                <div class="row g-2 mb-4">
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                        <button class="btn btn-compact btn-info ges-btn w-100">
                            <i class="bi bi-trophy me-1"></i>
                            <span>Gesamtrangliste</span>
                            <i class="bi bi-file-earmark-pdf ms-auto"></i>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                        <button class="btn btn-compact btn-success kun-btn w-100">
                            <i class="bi bi-palette me-1"></i>
                            <span>Kunst</span>
                            <i class="bi bi-file-earmark-pdf ms-auto"></i>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                        <button class="btn btn-compact btn-zwischenrang zwi-btn w-100">
                            <i class="bi bi-list-ol me-1"></i>
                            <span>Zwischenrangliste</span>
                            <i class="bi bi-file-earmark-pdf ms-auto"></i>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                        <button class="btn btn-compact btn-warning glu-btn w-100">
                            <i class="bi bi-dice-3 me-1"></i>
                            <span>Glück</span>
                            <i class="bi bi-file-earmark-pdf ms-auto"></i>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                        <button class="btn btn-compact btn-danger end-btn w-100">
                            <i class="bi bi-award me-1"></i>
                            <span>Endstich</span>
                            <i class="bi bi-file-earmark-pdf ms-auto"></i>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                        <button class="btn btn-compact btn-pink sch-btn w-100">
                            <i class="bi bi-piggy-bank me-1"></i>
                            <span>Schwini</span>
                            <i class="bi bi-file-earmark-pdf ms-auto"></i>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                        <button class="btn btn-compact btn-secondary zab-btn w-100">
                            <i class="bi bi-cup-straw me-1"></i>
                            <span>Zabig</span>
                            <i class="bi bi-file-earmark-pdf ms-auto"></i>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                        <button class="btn btn-compact btn-dark dif-btn w-100">
                            <i class="bi bi-sliders me-1"></i>
                            <span>Differenzler</span>
                            <i class="bi bi-file-earmark-pdf ms-auto"></i>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                        <button class="btn btn-compact btn-outline-secondary anm-btn w-100">
                            <i class="bi bi-person-plus me-1"></i>
                            <span>Anmeldungen</span>
                            <i class="bi bi-file-earmark-pdf ms-auto"></i>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                        <button class="btn btn-compact btn-primary part-btn w-100">
                            <i class="bi bi-people me-1"></i>
                            <span>Partner Rangliste</span>
                            <i class="bi bi-file-earmark-pdf ms-auto"></i>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                        <button class="btn btn-compact btn-special sieer-btn w-100">
                            <i class="bi bi-bullseye me-1"></i>
                            <span>Sie und Er</span>
                            <i class="bi bi-file-earmark-pdf ms-auto"></i>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                        <button class="btn btn-compact btn-absendenbuch abs-btn w-100">
                            <i class="bi bi-journal-bookmark-fill me-1"></i>
                            <span>Absendenbuch</span>
                            <i class="bi bi-file-earmark-word ms-auto"></i>
                        </button>
                    </div>
                </div>
                <!-- Tabellenbereich Kat. A -->
                <div class="table-wrapper mb-4">
                    <h5 class="table-title">Endschiessen Kat. A</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" id="EndA">
                            <thead>
                                <tr>
                                    <th scope="col">Rang</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Endstich</th>
                                    <th scope="col">Schwini</th>
                                    <th scope="col">Kunst</th>
                                    <th scope="col">Glück</th>
                                    <th scope="col">Zabig</th>
                                    <th scope="col">Differenzler</th>
                                    <th scope="col">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dynamische Inhalte hier -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Tabellenbereich Kat. B -->
                <div class="table-wrapper mb-4">
                    <h5 class="table-title">Endschiessen Kat. B</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" id="EndB">
                            <thead>
                                <tr>
                                    <th scope="col">Rang</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Endstich</th>
                                    <th scope="col">Schwini</th>
                                    <th scope="col">Kunst</th>
                                    <th scope="col">Glück</th>
                                    <th scope="col">Zabig</th>
                                    <th scope="col">Differenzler</th>
                                    <th scope="col">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dynamische Inhalte hier -->
                            </tbody>
                        </table>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
$(document).ready(function () {
    var basePath = '';

    // Automatischer Download
    function downloadFile(url, filename) {
        const link = document.createElement('a');
        link.href = url;
        link.download = filename || url.split('/').pop();
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

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

    // Endschiessen A laden
    function loadenda() {
        var selectedYear = $('#yearSelect').val();
        $.ajax({
            url: basePath + 'endschrang/load_endsch.php',
            type: 'GET',
            data: {
                kat: 'A',
                year: selectedYear
            },
            success: function (response) {
                $('#EndA tbody').html(response);
                msvToast('Kategorie A geladen', 'success');
            },
            error: function(xhr, status, error) {
                msvToast('Fehler beim Laden Kategorie A: ' + error, 'error');
            }
        });
    }

    // Endschiessen B laden
    function loadendb() {
        var selectedYear = $('#yearSelect').val();
        $.ajax({
            url: basePath + 'endschrang/load_endsch.php',
            type: 'GET',
            data: {
                kat: 'B',
                year: selectedYear
            },
            success: function (response) {
                $('#EndB tbody').html(response);
                msvToast('Kategorie B geladen', 'success');
            },
            error: function(xhr, status, error) {
                msvToast('Fehler beim Laden Kategorie B: ' + error, 'error');
            }
        });
    }

    // Generische PDF-Generator Funktion mit Toast
    function generatePDF(buttonClass, scriptName, documentName) {
        $(document).on('click', '.' + buttonClass, function (e) {
            e.preventDefault();
            var selectedYear = $('#yearSelect').val();
            var $button = $(this);
            var originalHtml = $button.html();

            // Ladeindikator mit rotierendem Icon
            $button.prop('disabled', true);
            var buttonText = $button.find('span').first().text();
            $button.html(
                '<i class="bi bi-arrow-repeat rotating-icon me-1"></i>' +
                '<span>' + buttonText + '</span>' +
                '<i class="bi bi-hourglass-split ms-auto"></i>'
            );
            msvToast(documentName + ' wird generiert...', 'info');
            $.ajax({
                url: 'endschrang/' + scriptName,
                type: 'GET',
                dataType: 'json',
                data: {
                    year: selectedYear
                },
                success: function (response) {
                    if (response.pdf_link) {

                        // PDF direkt herunterladen
                        const fullPath = 'endschrang/' + response.pdf_link;
                        const filename = documentName + '_' + selectedYear + '.pdf';
                        downloadFile(fullPath, filename);
                        msvToast(documentName + ' erfolgreich erstellt und heruntergeladen', 'success');
                    } else if (response.error) {
                        msvToast('Fehler: ' + response.error, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    msvToast('Fehler beim Generieren: ' + error, 'error');
                },
                complete: function () {

                    // Button wiederherstellen
                    $button.prop('disabled', false);
                    $button.html(originalHtml);
                }
            });
        });
    }

    // Spezieller Handler für Word-Dokument (Absendenbuch) mit Toast
    $(document).on('click', '.abs-btn', function (e) {
        e.preventDefault();
        var selectedYear = $('#yearSelect').val();
        var $button = $(this);
        var originalHtml = $button.html();

        // Ladeindikator mit rotierendem Icon
        $button.prop('disabled', true);
        var buttonText = $button.find('span').first().text();
        $button.html(
            '<i class="bi bi-arrow-repeat rotating-icon me-1"></i>' +
            '<span>' + buttonText + '</span>' +
            '<i class="bi bi-hourglass-split ms-auto"></i>'
        );
        msvToast('Absendenbuch wird generiert...', 'info');
        $.ajax({
            url: 'absenden/generate_absendenbuch.php',
            type: 'GET',
            dataType: 'json',
            data: {
                year: selectedYear
            },
            success: function (response) {
                 if (response.word_link) {

                    // Word-Dokument direkt herunterladen mit dem vom Server zurückgegebenen Namen
                    const fullPath = 'absenden/' + response.word_link;
                    const filename = response.display_name; // Hier den Namen vom Server verwenden
                    downloadFile(fullPath, filename);
                    msvToast('Absendenbuch erfolgreich erstellt und heruntergeladen', 'success');
                } else {
                    msvToast('Fehler beim Generieren des Absendenbuchs', 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('Word generation error:', xhr.responseText);
                msvToast('Fehler beim Generieren: ' + error, 'error');
            },
            complete: function () {

                // Button wiederherstellen
                $button.prop('disabled', false);
                $button.html(originalHtml);
            }
        });
    });

    // Alle PDF-Buttons registrieren mit beschreibenden Namen
    generatePDF('ges-btn', 'generate_pdf_gesamt.php', 'EndschiessenGesamtrangliste');
    generatePDF('zwi-btn', 'generate_pdf_zwischenrangliste.php', 'EndschiessenZwischenrangliste');
    generatePDF('end-btn', 'generate_pdf_end.php', 'EndschiessenEndstich');
    generatePDF('sch-btn', 'generate_pdf_schwini.php', 'EndschiessenSchwini');
    generatePDF('kun-btn', 'generate_pdf_kunst.php', 'EndschiessenKunst');
    generatePDF('glu-btn', 'generate_pdf_glueck.php', 'EndschiessenGlück');
    generatePDF('zab-btn', 'generate_pdf_zabig.php', 'EndschiessenZabig');
    generatePDF('dif-btn', 'generate_pdf_diff.php', 'EndschiessenDifferenzler');
    generatePDF('anm-btn', 'generate_pdf_anmeldung.php', 'EndschiessenAnmeldungen');
    generatePDF('part-btn', 'generate_pdf_partner.php', 'EndschiessenPartner Rangliste');
    generatePDF('sieer-btn', 'generate_pdf_sieer.php', 'EndschiessenSie und Er');

    // Beim Ändern des Jahres im Dropdown beide Tabellen neu laden
    $('#yearSelect').on('change', function () {
        msvToast('Lade Daten für ' + $(this).val() + '...', 'info');
        loadenda();
        loadendb();
    });

    // Initialisierung beim Laden der Seite
    initializeYearDropdown();
    loadenda();
    loadendb();
});

</script>

<?
include 'footer.inc.php';
?>
