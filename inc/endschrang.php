<?php
// endschrang.php – Endschiessen Ranglisten (Kat. A/B) und Dokumente
include 'dbconnect.inc.php';

// Seitenspezifische Styles: nur Aufbau dieser Seite; die Optik kommt aus css/msv-ui.css
$page_specific_css = '
/* Ladesymbol der Export-Knöpfe */
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.rotating-icon { display: inline-block; animation: spin 1s linear infinite; font-size: .875rem; }

/* Ranglisten Kat. A / B: Zahlen zentriert, Name links, Total betont */
#EndA thead th, #EndB thead th,
#EndA tbody td, #EndB tbody td { text-align: center; }
#EndA thead th:nth-child(2), #EndB thead th:nth-child(2),
#EndA tbody td:nth-child(2), #EndB tbody td:nth-child(2) { text-align: left; font-weight: 500; }
#EndA tbody td:last-child, #EndB tbody td:last-child { font-weight: 700; color: var(--ui-text); background-color: var(--ui-flaeche-2); font-variant-numeric: tabular-nums; }
/* Podium: Top 3 leicht in Gold, Silber und Bronze getönt, Rang fett */
#EndA tbody tr.rank-1 td, #EndB tbody tr.rank-1 td { background-color: rgba(245, 158, 11, .09); }
#EndA tbody tr.rank-2 td, #EndB tbody tr.rank-2 td { background-color: rgba(148, 163, 184, .12); }
#EndA tbody tr.rank-3 td, #EndB tbody tr.rank-3 td { background-color: rgba(205, 127, 50, .09); }
#EndA tbody tr:is(.rank-1, .rank-2, .rank-3) td:first-child,
#EndB tbody tr:is(.rank-1, .rank-2, .rank-3) td:first-child { font-weight: 800; }
';
include 'header.inc.php';
?>

<!-- Header -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <!-- Äusserer weisser Container -->
            <div class="main-content-wrapper content-width-wide">
                <!-- Header ausserhalb des inneren Containers -->
                <?php
                $page_title = "Endschiessen Ranglisten";
                $page_title_after = '<button type="button" class="btn-help" data-help="endschrang.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_actions = '<button id="redirect-btn" type="button" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Resultate bearbeiten</button>';
                $page_show_mobile = true;
                include 'partials/page_header.inc.php'; ?>
                <!-- Weisser Container für den Rest -->
                <div class="content-background">
                <!-- Dokumente erstellen (gruppiert); Jahr und «Resultate bearbeiten» stehen in der Kopf-Card -->
                <div class="export-toolbar mb-3">
                    <div class="export-toolbar-head">
                        <i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i>
                        <span>Dokumente erstellen</span>
                        <button type="button" class="btn-help" data-help="endschrang.dokumente" aria-label="Hilfe"></button>
                    </div>
                    <div class="export-groups">
                        <!-- Gruppe: Ranglisten / Übersicht -->
                        <div class="export-group">
                            <div class="export-group-label">Ranglisten &amp; Übersicht</div>
                            <div class="export-group-btns">
                                <button class="btn btn-compact-standard btn-outline-info ges-btn">
                                    <i class="bi bi-trophy me-1"></i><span>Gesamt</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_gesamt.php" data-druck-job="Endschiessen Gesamtrangliste" aria-label="Gesamt drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info zwi-btn">
                                    <i class="bi bi-list-ol me-1"></i><span>Zwischen</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_zwischenrangliste.php" data-druck-job="Endschiessen Zwischenrangliste" aria-label="Zwischenrangliste drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info anm-btn">
                                    <i class="bi bi-person-plus me-1"></i><span>Anmeldung</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_anmeldung.php" data-druck-job="Endschiessen Anmeldung" aria-label="Anmeldung drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info abs-btn">
                                    <i class="bi bi-journal-bookmark-fill me-1"></i><span>Absendenbuch</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info absbk-btn" data-tooltip="Absendenbuch als Broschüren-PDF: A5-Seiten paarweise auf A4 quer, Reihenfolge zum Falten">
                                    <i class="bi bi-book me-1"></i><span>Broschüre</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="absendenbuch" data-druck-label="Absendenbuch (Broschüre)" aria-label="Absendenbuch als Broschüre drucken"><i class="bi bi-printer"></i></button>
                            </div>
                        </div>
                        <!-- Gruppe: Einzelwettbewerbe -->
                        <div class="export-group">
                            <div class="export-group-label">Einzelwettbewerbe</div>
                            <div class="export-group-btns">
                                <button class="btn btn-compact-standard btn-outline-info end-btn">
                                    <i class="bi bi-award me-1"></i><span>Endstich</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_end.php" data-druck-job="Endschiessen Endstich" aria-label="Endstich drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info sch-btn">
                                    <i class="bi bi-piggy-bank me-1"></i><span>Schwini</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_schwini.php" data-druck-job="Endschiessen Schwini" aria-label="Schwini drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info kun-btn">
                                    <i class="bi bi-palette me-1"></i><span>Kunst</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_kunst.php" data-druck-job="Endschiessen Kunst" aria-label="Kunst drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info glu-btn">
                                    <i class="bi bi-dice-3 me-1"></i><span>Glück</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_glueck.php" data-druck-job="Endschiessen Glück" aria-label="Glück drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info zab-btn">
                                    <i class="bi bi-cup-straw me-1"></i><span>Zabig</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_zabig.php" data-druck-job="Endschiessen Zabig" aria-label="Zabig drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info dif-btn">
                                    <i class="bi bi-sliders me-1"></i><span>Differenzler</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_diff.php" data-druck-job="Endschiessen Differenzler" aria-label="Differenzler drucken"><i class="bi bi-printer"></i></button>
                            </div>
                        </div>
                        <!-- Gruppe: Partner-Wettbewerbe -->
                        <div class="export-group">
                            <div class="export-group-label">Partner</div>
                            <div class="export-group-btns">
                                <button class="btn btn-compact-standard btn-outline-info part-btn">
                                    <i class="bi bi-people me-1"></i><span>Partner</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_partner.php" data-druck-job="Endschiessen Partner" aria-label="Partner drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info sieer-btn">
                                    <i class="bi bi-bullseye me-1"></i><span>Sie &amp; Er</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_sieer.php" data-druck-job="Endschiessen Sie und Er" aria-label="Sie und Er drucken"><i class="bi bi-printer"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Tabellenbereich Kat. A -->
                <div class="table-wrapper mb-4">
                    <h5 class="table-title">Endschiessen Kat. A <button type="button" class="btn-help" data-help="endschrang.wertung" aria-label="Hilfe"></button></h5>
                    <div class="desktop-table-container">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="EndA">
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
                    <h5 class="table-title">Endschiessen Kat. B <button type="button" class="btn-help" data-help="endschrang.kategorien" aria-label="Hilfe"></button></h5>
                    <div class="desktop-table-container">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="EndB">
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
        msvJahrAuswahl('#yearSelect');
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
                    year: selectedYear,
                    orientation: window.MsvDruck ? MsvDruck.orientierung('endschrang', 'portrait') : 'portrait' // Format aus dem Druckprofil
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

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray): ein Profil «Endschiessen Ranglisten» fuer alle PDFs dieser Seite.
// Die Generatoren liefern JSON {pdf_link: 'dat/…'} relativ zum Modul → linkPrefix.
MsvDruck.resolve('endschrang', (btn) => {
    const jahr = document.getElementById('yearSelect').value;
    return {
        url: 'endschrang/' + btn.dataset.druckScript + '?year=' + encodeURIComponent(jahr)
            + '&orientation=' + MsvDruck.orientierung('endschrang', 'portrait'),
        jobName: btn.dataset.druckJob + ' ' + jahr,
        linkPrefix: 'endschrang/'
    };
});

// Absendenbuch als Broschüre (absenden/generate_absendenbuch_pdf.php: DOCX → PDF → pdfjam --booklet).
// Broschüren brauchen beidseitigen Druck mit Wenden an der kurzen Seite; steht im Profil kein Duplex,
// wird «short-edge» gesetzt, damit die Rückseiten nicht auf dem Kopf stehen.
MsvDruck.resolve('absendenbuch', () => {
    const jahr = document.getElementById('yearSelect').value;
    const profil = MsvDruck.profil('absendenbuch');
    return {
        url: 'absenden/generate_absendenbuch_pdf.php?year=' + encodeURIComponent(jahr),
        jobName: 'Absendenbuch ' + jahr + ' (Broschüre)',
        orientation: 'landscape',
        duplex: (profil && profil.duplex) || 'short-edge'
    };
});

// Broschüren-PDF herunterladen (gleicher Endpunkt, zum Prüfen der Seitenfolge oder für die Druckerei)
$(document).on('click', '.absbk-btn', async function () {
    const btn = this, orig = btn.innerHTML, jahr = document.getElementById('yearSelect').value;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat rotating-icon me-1"></i><span>Broschüre</span>';
    msvToast('Broschüre wird erstellt…', 'info');
    try {
        const r = await fetch('absenden/generate_absendenbuch_pdf.php?year=' + encodeURIComponent(jahr), { credentials: 'same-origin' });
        const j = await r.json();
        if (!r.ok || !j.pdf_link) throw new Error(j.error || 'Keine Antwort vom Server');
        const a = document.createElement('a');
        a.href = j.pdf_link; a.download = 'Absendenbuch_' + jahr + '_Broschuere.pdf';
        document.body.appendChild(a); a.click(); a.remove();
        msvToast('Broschüre erstellt: ' + j.pages + ' Seiten auf ' + j.sheets + ' Blatt A4 (beidseitig)', 'success');
    } catch (err) {
        msvToast('Fehler: ' + (err && err.message ? err.message : err), 'error');
    } finally {
        btn.disabled = false; btn.innerHTML = orig;
    }
});
</script>
<?
include 'footer.inc.php';
?>
