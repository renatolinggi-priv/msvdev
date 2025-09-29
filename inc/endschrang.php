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

                <!-- PDF/Word Download Links -->
                <div id="pdf-link" class="mb-3"></div>
                <div id="word-link" class="mb-3"></div>

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

    // Redirect Button Handler
    document.addEventListener('DOMContentLoaded', function () {
        var redirectButton = document.getElementById('redirect-btn');
        if (redirectButton) {
            redirectButton.addEventListener('click', function () {
                console.log('Button clicked, redirecting...');
                window.location.href = 'https://jahresmeisterschaft.msvwilen.ch/inc/endresultate.php';
            });
        }
    });

    $(document).ready(function () {
        var basePath = '';

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
                }
            });
        }

        // Nachricht anzeigen
        function showMessage(message, type) {
            var messageDiv = $('#message');
            messageDiv.removeClass().addClass('alert alert-' + type).text(message).show();
            setTimeout(function () {
                messageDiv.fadeOut();
            }, 3000);
        }

        // Generische PDF-Generator Funktion
        // Generische PDF-Generator Funktion mit Ladeindikator
        function generatePDF(buttonClass, scriptName) {
            $(document).on('click', '.' + buttonClass, function (e) {
                e.preventDefault();
                var selectedYear = $('#yearSelect').val();
                var $button = $(this);
                var originalHtml = $button.html();

                // Ladeindikator anzeigen
                $button.prop('disabled', true);
                $button.html('<span class="loading-dots">...</span>');

                // PDF-Link Container leeren
                $('#pdf-link').empty();

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
                            const link = document.createElement('a');
                            link.href = 'endschrang/' + response.pdf_link;
                            link.download = response.pdf_link.split('/').pop(); // Dateiname extrahieren
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            
                            // PDF-Link Container leeren nach Download
                            $('#pdf-link').empty();
                        } else if (response.error) {
                            alert('Fehler: ' + response.error);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        alert('Fehler beim Generieren des PDFs: ' + error);
                    },
                    complete: function () {
                        // Button wiederherstellen
                        $button.prop('disabled', false);
                        $button.html(originalHtml);
                    }
                });
            });
        }

        // Spezieller Handler für Word-Dokument (Absendenbuch) mit Ladeindikator
        $(document).on('click', '.abs-btn', function (e) {
            e.preventDefault();
            var selectedYear = $('#yearSelect').val();
            var $button = $(this);
            var originalHtml = $button.html();

            // Ladeindikator anzeigen
            $button.prop('disabled', true);
            $button.html('<span class="loading-dots">...</span>');


            // Word-Link Container leeren
            $('#word-link').empty();

            $.ajax({
                url: 'absenden/generate_absendenbuch.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    year: selectedYear
                },
                success: function (response) {
                    if (response.word_link) {
                        // Word-Dokument direkt herunterladen
                        const link = document.createElement('a');
                        link.href = 'absenden/' + response.word_link;
                        link.download = response.word_link.split('/').pop(); // Dateiname extrahieren
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        // Link Container leeren nach Download
                        $('#word-link').empty();
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Word generation error:', xhr.responseText);
                    alert('Fehler beim Generieren des Word-Dokuments: ' + error);
                },
                complete: function () {
                    // Button wiederherstellen
                    $button.prop('disabled', false);
                    $button.html(originalHtml);
                }
            });
        });

        // Alle PDF-Buttons registrieren
        generatePDF('ges-btn', 'generate_pdf_gesamt.php');
        generatePDF('zwi-btn', 'generate_pdf_zwischenrangliste.php');
        generatePDF('end-btn', 'generate_pdf_end.php');
        generatePDF('sch-btn', 'generate_pdf_schwini.php');
        generatePDF('kun-btn', 'generate_pdf_kunst.php');
        generatePDF('glu-btn', 'generate_pdf_glueck.php');
        generatePDF('zab-btn', 'generate_pdf_zabig.php');
        generatePDF('dif-btn', 'generate_pdf_diff.php');
        generatePDF('anm-btn', 'generate_pdf_anmeldung.php');
        generatePDF('part-btn', 'generate_pdf_partner.php');
        generatePDF('sieer-btn', 'generate_pdf_sieer.php');
        // Beim Ändern des Jahres im Dropdown beide Tabellen neu laden
        $('#yearSelect').on('change', function () {
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