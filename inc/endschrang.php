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

/* Kompaktere Buttons */
.btn-compact {
    white-space: nowrap;
    overflow: hidden;
    min-height: 32px !important;
    padding: 0.3rem 0.6rem !important;
    font-size: 0.8rem !important;
}
.btn-compact i.ms-auto { display: none !important; }

/* "Resultate bearbeiten" im Toolbar-Kopf kompakter (Desktop) */
@media (min-width: 768px) {
    #redirect-btn {
        min-height: 0 !important;
        padding: 0.2rem 0.6rem !important;
        font-size: 0.78rem !important;
        line-height: 1.4;
    }
    #redirect-btn i { font-size: 0.8rem !important; }
}

/* Export-Toolbar-Styles (.export-toolbar / .export-group*) sind zentral in css/msv-styles.css */

/* ==========================================
   TABELLEN-FEINSCHLIFF (Rang A/B)
   ========================================== */
/* Zahlenspalten zentrieren, Name linksbündig */
#EndA thead th, #EndB thead th,
#EndA tbody td, #EndB tbody td { text-align: center; }
#EndA thead th:nth-child(2), #EndB thead th:nth-child(2),
#EndA tbody td:nth-child(2), #EndB tbody td:nth-child(2) {
    text-align: left;
    font-weight: 500;
}
/* Total-Spalte hervorheben */
#EndA tbody td:last-child, #EndB tbody td:last-child {
    font-weight: 700;
    color: var(--secondary-color);
    background-color: rgba(99, 102, 241, 0.06);
}
#EndA thead th:last-child, #EndB thead th:last-child { color: var(--secondary-color); }
/* Zebra-Streifen */
#EndA tbody tr:nth-child(even) td, #EndB tbody tr:nth-child(even) td {
    background-color: rgba(241, 245, 249, 0.55);
}
/* Podium: Top-3 mit Medaillen-Akzent */
#EndA tbody tr.rank-1 td:first-child, #EndB tbody tr.rank-1 td:first-child { box-shadow: inset 4px 0 0 #f59e0b; }
#EndA tbody tr.rank-2 td:first-child, #EndB tbody tr.rank-2 td:first-child { box-shadow: inset 4px 0 0 #94a3b8; }
#EndA tbody tr.rank-3 td:first-child, #EndB tbody tr.rank-3 td:first-child { box-shadow: inset 4px 0 0 #cd7f32; }
#EndA tbody tr.rank-1 td, #EndB tbody tr.rank-1 td { background-color: rgba(245, 158, 11, 0.07); }
#EndA tbody tr.rank-2 td, #EndB tbody tr.rank-2 td { background-color: rgba(148, 163, 184, 0.07); }
#EndA tbody tr.rank-3 td, #EndB tbody tr.rank-3 td { background-color: rgba(205, 127, 50, 0.07); }
#EndA tbody tr.rank-1 td:first-child, #EndB tbody tr.rank-1 td:first-child,
#EndA tbody tr.rank-2 td:first-child, #EndB tbody tr.rank-2 td:first-child,
#EndA tbody tr.rank-3 td:first-child, #EndB tbody tr.rank-3 td:first-child { font-weight: 800; }

</style>

<!-- Header -->
<div class="container-fluid">
    <div class="row">
        <div class="col-xxl-8 col-xl-9 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <?php $page_title = "Endschiessen Ranglisten"; include 'partials/page_header.inc.php'; ?>
                <!-- Weißer Container für den Rest -->
                <div class="content-background">
                <!-- Jahr-Auswahl + Dokumente erstellen (eine kompakte Karte) -->
                <div class="export-toolbar mb-3">
                    <div class="export-toolbar-head">
                        <label for="yearSelect" class="export-year-label mb-0">
                            <i class="bi bi-calendar3 me-1"></i>Jahr:
                        </label>
                        <select id="yearSelect" class="form-select form-select-sm export-year-select">
                            <!-- Optionen werden per JavaScript eingefügt -->
                        </select>
                        <span class="export-toolbar-divider" aria-hidden="true"></span>
                        <i class="bi bi-file-earmark-arrow-down"></i>
                        <span>Dokumente erstellen</span>
                        <button id="redirect-btn" type="button" class="btn btn-outline-primary btn-sm ms-auto">
                            <i class="bi bi-pencil-square me-1"></i>Resultate bearbeiten
                        </button>
                    </div>
                    <div class="export-groups">
                        <!-- Gruppe: Ranglisten / Übersicht -->
                        <div class="export-group">
                            <div class="export-group-label">Ranglisten &amp; Übersicht</div>
                            <div class="export-group-btns">
                                <button class="btn btn-compact btn-outline-info ges-btn">
                                    <i class="bi bi-trophy me-1"></i><span>Gesamt</span>
                                </button>
                                <button class="btn btn-compact btn-outline-info zwi-btn">
                                    <i class="bi bi-list-ol me-1"></i><span>Zwischen</span>
                                </button>
                                <button class="btn btn-compact btn-outline-info anm-btn">
                                    <i class="bi bi-person-plus me-1"></i><span>Anmeldung</span>
                                </button>
                                <button class="btn btn-compact btn-outline-info abs-btn">
                                    <i class="bi bi-journal-bookmark-fill me-1"></i><span>Absendenbuch</span>
                                </button>
                            </div>
                        </div>
                        <!-- Gruppe: Einzelwettbewerbe -->
                        <div class="export-group">
                            <div class="export-group-label">Einzelwettbewerbe</div>
                            <div class="export-group-btns">
                                <button class="btn btn-compact btn-outline-info end-btn">
                                    <i class="bi bi-award me-1"></i><span>Endstich</span>
                                </button>
                                <button class="btn btn-compact btn-outline-info sch-btn">
                                    <i class="bi bi-piggy-bank me-1"></i><span>Schwini</span>
                                </button>
                                <button class="btn btn-compact btn-outline-info kun-btn">
                                    <i class="bi bi-palette me-1"></i><span>Kunst</span>
                                </button>
                                <button class="btn btn-compact btn-outline-info glu-btn">
                                    <i class="bi bi-dice-3 me-1"></i><span>Glück</span>
                                </button>
                                <button class="btn btn-compact btn-outline-info zab-btn">
                                    <i class="bi bi-cup-straw me-1"></i><span>Zabig</span>
                                </button>
                                <button class="btn btn-compact btn-outline-info dif-btn">
                                    <i class="bi bi-sliders me-1"></i><span>Differenzler</span>
                                </button>
                            </div>
                        </div>
                        <!-- Gruppe: Partner-Wettbewerbe -->
                        <div class="export-group">
                            <div class="export-group-label">Partner</div>
                            <div class="export-group-btns">
                                <button class="btn btn-compact btn-outline-info part-btn">
                                    <i class="bi bi-people me-1"></i><span>Partner</span>
                                </button>
                                <button class="btn btn-compact btn-outline-info sieer-btn">
                                    <i class="bi bi-bullseye me-1"></i><span>Sie &amp; Er</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Tabellenbereich Kat. A -->
                <div class="table-wrapper mb-4">
                    <h5 class="table-title">Endschiessen Kat. A</h5>
                    <div class="desktop-table-container">
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
                    <!-- Mobile Cards Container -->
                    <div class="mobile-cards-container" id="mobileCardsEndA">
                        <div class="mobile-search">
                            <div class="position-relative">
                                <i class="bi bi-search search-icon"></i>
                                <input type="text" class="form-control" placeholder="Suchen..."
                                       oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsEndA')">
                            </div>
                        </div>
                        <div class="mobile-cards-scroll">
                            <!-- Cards werden hier eingefügt -->
                        </div>
                    </div>
                </div>
                <!-- Tabellenbereich Kat. B -->
                <div class="table-wrapper mb-4">
                    <h5 class="table-title">Endschiessen Kat. B</h5>
                    <div class="desktop-table-container">
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
                    <!-- Mobile Cards Container -->
                    <div class="mobile-cards-container" id="mobileCardsEndB">
                        <div class="mobile-search">
                            <div class="position-relative">
                                <i class="bi bi-search search-icon"></i>
                                <input type="text" class="form-control" placeholder="Suchen..."
                                       oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsEndB')">
                            </div>
                        </div>
                        <div class="mobile-cards-scroll">
                            <!-- Cards werden hier eingefügt -->
                        </div>
                    </div>
                </div>
                </div><!-- /content-background -->
            </div><!-- /main-content-wrapper -->
        </div>
    </div>
</div>
<script>
$(document).ready(function () {
    var basePath = '';

    // Bearbeiten-Button → endresultate.php mit Jahresauswahl
    $('#redirect-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const year = $('#yearSelect').val();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Lade...');
        setTimeout(() => {
            window.location.href = 'endresultate.php?year=' + encodeURIComponent(year);
        }, 300);
    });

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
        for (let year = currentYear; year >= currentYear - 3; year--) {
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
                buildMobileCardsEndA();
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
                buildMobileCardsEndB();
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


    // Mobile Cards Builder für Kategorie A
    function buildMobileCardsEndA() {
        MSVMobileCards.initResponsive(function() {
            MSVMobileCards.buildCards('#EndA', '#mobileCardsEndA', {
                titleColumns: [0, 1],
                summaryColumns: [8],
                rankColumn: 0
            });
        });
    }

    // Mobile Cards Builder für Kategorie B
    function buildMobileCardsEndB() {
        MSVMobileCards.initResponsive(function() {
            MSVMobileCards.buildCards('#EndB', '#mobileCardsEndB', {
                titleColumns: [0, 1],
                summaryColumns: [8],
                rankColumn: 0
            });
        });
    }

</script>

<?
include 'footer.inc.php';
?>
