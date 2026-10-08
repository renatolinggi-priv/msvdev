<?php
// endschrang.php – Endschiessen Ranglisten (Kat. A/B) und Dokumente
include 'dbconnect.inc.php';

// Seitenspezifische Styles: nur Aufbau dieser Seite; die Optik kommt aus css/msv-ui.css
$page_specific_css = '
/* Absenden vorbereiten: Prüfliste links, die drei Dokumente fürs Absenden rechts */
.es-absenden { margin-bottom: 14px; }
.es-abs-stand { margin-left: auto; }
.es-abs-inhalt { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 16px 40px; padding: 14px var(--ui-pad) 16px; align-items: start; }
.es-abs-liste { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.es-abs-liste li { display: flex; align-items: baseline; gap: 6px 12px; flex-wrap: wrap; }
.es-abs-liste .ui-status { min-width: 6.5rem; }
.es-abs-liste .ui-status:not(.ok):not(.offen) { color: var(--ui-text-2); }
.es-abs-liste .ui-status:not(.ok):not(.offen) .ui-punkt { background: var(--ui-feldrand); }
.es-abs-liste a { font-weight: 600; white-space: nowrap; }
.es-abs-doks { display: flex; flex-direction: column; gap: 8px; min-width: 16rem; }
.es-abs-doks-titel { font-size: .72rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--ui-text-2); }
.es-abs-hinweis { margin: 0; font-size: .8rem; color: var(--ui-warn-fg); }
.es-abs-zeit { margin-left: 8px; font-size: .75rem; color: var(--ui-text-2); white-space: nowrap; }
.es-abs-doks .es-abs-dok { flex: 1 1 auto; display: flex; align-items: center; gap: 6px; text-align: left; }
.es-abs-doks .es-abs-dok small { margin-left: auto; font-weight: 500; opacity: .8; }
@media (max-width: 767.98px) { .es-abs-inhalt { grid-template-columns: 1fr; } .es-abs-doks { min-width: 0; } }

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
#EndA tbody tr.rank-1 td, #EndB tbody tr.rank-1 td { background-color: color-mix(in srgb, var(--ui-gold-bg) 40%, var(--ui-flaeche)); }
#EndA tbody tr.rank-2 td, #EndB tbody tr.rank-2 td { background-color: color-mix(in srgb, var(--ui-silber-bg) 40%, var(--ui-flaeche)); }
#EndA tbody tr.rank-3 td, #EndB tbody tr.rank-3 td { background-color: color-mix(in srgb, var(--ui-bronze-bg) 40%, var(--ui-flaeche)); }
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
                $page_title = "Endschiessen Rangliste";
                $page_title_after = '<button type="button" class="btn-help" data-help="endschrang.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_actions = '<button id="redirect-btn" type="button" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Resultate bearbeiten</button>';
                $page_show_mobile = true;
                include 'partials/page_header.inc.php'; ?>
                <!-- Absenden vorbereiten: Prüfliste und die drei Dokumente fürs Absenden (eigene Karte, nicht in der Inhalts-Card) -->
                <section class="ui-karte es-absenden" aria-labelledby="absTitel">
                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel" id="absTitel"><i class="bi bi-flag me-1" aria-hidden="true"></i>Absenden vorbereiten <button type="button" class="btn-help" data-help="endschrang.absenden" aria-label="Hilfe"></button></span>
                        <span class="es-abs-stand" id="absStand" aria-live="polite"></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="absNeu" aria-label="Prüfliste neu prüfen" data-tooltip="Neu prüfen"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
                    </div>
                    <div class="es-abs-inhalt">
                        <ul class="es-abs-liste" id="absListe" aria-label="Prüfliste">
                            <li data-pruefung="stiche"><span class="ui-status"><span class="ui-punkt"></span>…</span><span>Stiche werden geprüft</span></li>
                            <li data-pruefung="partner"><span class="ui-status"><span class="ui-punkt"></span>…</span><span>Partnerinnen werden geprüft</span></li>
                            <li data-pruefung="wanderpreise"><span class="ui-status"><span class="ui-punkt"></span>…</span><span>Wanderpreise werden geprüft</span></li>
                        </ul>
                        <div class="es-abs-doks" role="group" aria-labelledby="absDoksTitel">
                            <div class="es-abs-doks-titel" id="absDoksTitel">Fürs Absenden</div>
                            <p class="es-abs-hinweis" id="absDokHinweis" hidden></p>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-info abs-btn es-abs-dok"><i class="bi bi-journal-bookmark-fill" aria-hidden="true"></i><span>Absendenbuch</span><small>Word</small></button>
                            </div>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-info absbk-btn es-abs-dok" data-tooltip="Absendenbuch als Broschüre: A5-Seiten paarweise auf A4 quer, in der Reihenfolge zum Falten"><i class="bi bi-book" aria-hidden="true"></i><span>Broschüre</span><small>PDF</small></button>
                                <button type="button" class="btn btn-sm btn-outline-info msv-druck" data-druck-doctype="absendenbuch" data-druck-label="Absendenbuch (Broschüre)" aria-label="Absendenbuch als Broschüre drucken"><i class="bi bi-printer"></i></button>
                            </div>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-info ges-btn es-abs-dok"><i class="bi bi-trophy" aria-hidden="true"></i><span>Gesamtrangliste</span><small>PDF</small></button>
                                <button type="button" class="btn btn-sm btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_gesamt.php" data-druck-job="Endschiessen Gesamtrangliste" aria-label="Gesamtrangliste drucken"><i class="bi bi-printer"></i></button>
                            </div>
                        </div>
                    </div>
                </section>
                <!-- Weisser Container für den Rest -->
                <div class="content-background">
                <!-- Dokumente erstellen (gruppiert); Jahr und «Resultate bearbeiten» stehen in der Kopf-Card -->
                <div class="export-toolbar mb-3">
                    <div class="export-toolbar-head">
                        <i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i>
                        <span>Weitere Ranglisten und Listen</span>
                        <button type="button" class="btn-help" data-help="endschrang.dokumente" aria-label="Hilfe"></button>
                    </div>
                    <div class="export-groups">
                        <!-- Gruppe: Ranglisten / Übersicht -->
                        <div class="export-group">
                            <div class="export-group-label">Übersicht</div>
                            <div class="export-group-btns">
                                <button class="btn btn-compact-standard btn-outline-info zwi-btn">
                                    <i class="bi bi-list-ol me-1"></i><span>Zwischen</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_zwischenrangliste.php" data-druck-job="Endschiessen Zwischenrangliste" aria-label="Zwischenrangliste drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info anm-btn">
                                    <i class="bi bi-person-plus me-1"></i><span>Anmeldung</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_anmeldung.php" data-druck-job="Endschiessen Anmeldung" aria-label="Anmeldung drucken"><i class="bi bi-printer"></i></button>
                            </div>
                        </div>
                        <!-- Gruppe: Einzelwettbewerbe -->
                        <div class="export-group">
                            <div class="export-group-label">Einzelwettbewerbe</div>
                            <div class="export-group-btns">
                                <button class="btn btn-compact-standard btn-outline-info end-btn">
                                    <i class="bi bi-bullseye me-1"></i><span>Endstich</span>
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
                                    <i class="bi bi-clover me-1"></i><span>Glück</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_glueck.php" data-druck-job="Endschiessen Glück" aria-label="Glück drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info zab-btn">
                                    <i class="bi bi-moon-stars me-1"></i><span>Zabig</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_zabig.php" data-druck-job="Endschiessen Zabig" aria-label="Zabig drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info dif-btn">
                                    <i class="bi bi-chat-square-text me-1"></i><span>Differenzler</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_diff.php" data-druck-job="Endschiessen Differenzler" aria-label="Differenzler drucken"><i class="bi bi-printer"></i></button>
                            </div>
                        </div>
                        <!-- Gruppe: Partner-Wettbewerbe -->
                        <div class="export-group">
                            <div class="export-group-label">Partner</div>
                            <div class="export-group-btns">
                                <button class="btn btn-compact-standard btn-outline-info part-btn">
                                    <i class="bi bi-heart me-1"></i><span>Partner</span>
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_partner.php" data-druck-job="Endschiessen Partner" aria-label="Partner drucken"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-compact-standard btn-outline-info sieer-btn">
                                    <i class="bi bi-people me-1"></i><span>Sie &amp; Er</span>
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
        window.location.href = 'endresultate.php?year=' + encodeURIComponent($('#yearSelect').val());
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
                msvToast(msvXhrMessage(xhr, 'Die Rangliste Kat. A konnte nicht geladen werden'), 'error');
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
                msvToast(msvXhrMessage(xhr, 'Die Rangliste Kat. B konnte nicht geladen werden'), 'error');
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
                        const filename = 'Endschiessen_' + documentName.replace(/\s+/g, '_') + '_' + selectedYear + '.pdf';
                        downloadFile(fullPath, filename);
                        msvToast(documentName + ' ' + selectedYear + ' heruntergeladen', 'success');
                    } else if (response.error) {
                        msvToast(response.error, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    msvToast(msvXhrMessage(xhr, documentName + ' konnte nicht erstellt werden'), 'error');
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
                    msvToast('Absendenbuch ' + selectedYear + ' heruntergeladen', 'success');
                } else {
                    msvToast(response.message || response.error || 'Das Absendenbuch konnte nicht erstellt werden', 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('Word generation error:', xhr.responseText);
                msvToast(msvXhrMessage(xhr, 'Das Absendenbuch konnte nicht erstellt werden'), 'error');
            },
            complete: function () {

                // Button wiederherstellen
                $button.prop('disabled', false);
                $button.html(originalHtml);
            }
        });
    });

    // Alle PDF-Buttons registrieren mit beschreibenden Namen
    generatePDF('ges-btn', 'generate_pdf_gesamt.php', 'Gesamtrangliste');
    generatePDF('zwi-btn', 'generate_pdf_zwischenrangliste.php', 'Zwischenrangliste');
    generatePDF('end-btn', 'generate_pdf_end.php', 'Rangliste Endstich');
    generatePDF('sch-btn', 'generate_pdf_schwini.php', 'Rangliste Schwini');
    generatePDF('kun-btn', 'generate_pdf_kunst.php', 'Rangliste Kunst');
    generatePDF('glu-btn', 'generate_pdf_glueck.php', 'Rangliste Glück');
    generatePDF('zab-btn', 'generate_pdf_zabig.php', 'Rangliste Zabig');
    generatePDF('dif-btn', 'generate_pdf_diff.php', 'Rangliste Differenzler');
    generatePDF('anm-btn', 'generate_pdf_anmeldung.php', 'Anmeldungen');
    generatePDF('part-btn', 'generate_pdf_partner.php', 'Rangliste Partner');
    generatePDF('sieer-btn', 'generate_pdf_sieer.php', 'Rangliste Sie und Er');

    // ===== Absenden vorbereiten: Prüfliste =====
    // Offene Stiche und Partnerinnen zählt sie aus den Ladern der Erfassungsseiten (dieselbe Regel wie dort:
    // «offen», solange ein gelöster Stich kein Resultat hat), die Wanderpreise aus endschrang/absenden_bereit.php.
    function absZeile(key, zustand, text, link) {
        const label = { ok: 'erledigt', offen: 'offen', leer: 'nichts zu tun', fehler: 'nicht geprüft' }[zustand];
        $('#absListe li[data-pruefung="' + key + '"]').html(
            '<span class="ui-status' + (zustand === 'ok' || zustand === 'offen' ? ' ' + zustand : '') + '"><span class="ui-punkt"></span>' + label + '</span>'
            + '<span>' + msvEsc(text) + '</span>'
            + (link ? '<a href="' + link.href + '">' + msvEsc(link.text) + ' <i class="bi bi-arrow-right" aria-hidden="true"></i></a>' : ''));
        return zustand;
    }
    function zeilenZaehlen(html) {
        const doc = new DOMParser().parseFromString('<table><tbody>' + html + '</tbody></table>', 'text/html');
        return { total: doc.querySelectorAll('tr[data-stand]').length, offen: doc.querySelectorAll('tr[data-stand="offen"]').length };
    }
    async function absendenPruefen() {
        const jahr = $('#yearSelect').val();
        if (!jahr) return;
        $('#absStand').html('<span class="ui-status"><span class="ui-punkt"></span>wird geprüft …</span>');
        const q = { year: jahr };
        const [end, part, wp] = await Promise.allSettled([
            $.get('endschresultate/load_endschresultate.php', q),
            $.get('endresultate_partner/load_partner_resultate.php', q),
            $.getJSON('endschrang/absenden_bereit.php', q)
        ]);
        if (jahr !== $('#yearSelect').val()) return; // inzwischen ein anderes Jahr gewählt
        const z = [];
        if (end.status === 'fulfilled') {
            const n = zeilenZaehlen(end.value);
            z.push(n.total === 0 ? absZeile('stiche', 'leer', 'Für ' + jahr + ' sind keine Stiche gelöst.')
                : n.offen === 0 ? absZeile('stiche', 'ok', 'Alle gelösten Stiche sind erfasst (' + n.total + ' Mitglieder).')
                : absZeile('stiche', 'offen', n.offen + ' von ' + n.total + ' Mitgliedern haben noch offene Stiche.', { href: 'endresultate.php?year=' + jahr, text: 'Zur Erfassung' }));
        } else z.push(absZeile('stiche', 'fehler', 'Die Stiche konnten nicht geprüft werden.'));
        if (part.status === 'fulfilled') {
            const n = zeilenZaehlen(part.value);
            z.push(n.total === 0 ? absZeile('partner', 'leer', 'Für ' + jahr + ' sind keine Partnerinnen gemeldet.')
                : n.offen === 0 ? absZeile('partner', 'ok', 'Alle Partnerinnen sind erfasst (' + n.total + ').')
                : absZeile('partner', 'offen', n.offen + ' von ' + n.total + ' Partnerinnen haben noch kein Resultat.', { href: 'endresultate_partner.php?year=' + jahr, text: 'Zu den Partnerinnen' }));
        } else z.push(absZeile('partner', 'fehler', 'Die Partnerinnen konnten nicht geprüft werden.'));
        const w = wp.status === 'fulfilled' && wp.value && wp.value.success ? wp.value.wanderpreise : null;
        z.push(!w ? absZeile('wanderpreise', 'fehler', 'Die Wanderpreise konnten nicht geprüft werden.')
            : w.total === 0 ? absZeile('wanderpreise', 'leer', 'Keine Wanderpreise im Umlauf.')
            : w.vergeben >= w.total ? absZeile('wanderpreise', 'ok', 'Alle ' + w.total + ' Wanderpreise sind für ' + jahr + ' vergeben.')
            : absZeile('wanderpreise', 'offen', w.vergeben + ' von ' + w.total + ' Wanderpreisen sind für ' + jahr + ' vergeben.', { href: 'wanderpreise.php', text: 'Zu den Wanderpreisen' }));
        const offen = z.filter(s => s === 'offen').length, fehler = z.filter(s => s === 'fehler').length;
        const zeit = new Date().toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit' });
        $('#absStand').html((offen
            ? '<span class="ui-status offen"><span class="ui-punkt"></span>' + (offen === 1 ? '1 Punkt offen' : offen + ' Punkte offen') + '</span>'
            : fehler ? '<span class="ui-status"><span class="ui-punkt"></span>nicht alles geprüft</span>'
            : '<span class="ui-status ok"><span class="ui-punkt"></span>bereit fürs Absenden</span>')
            + '<span class="es-abs-zeit">Stand ' + zeit + '</span>');
        // Die Dokumente lassen sich auch mit offenen Punkten erstellen; der Hinweis sagt, dass sie den heutigen Stand zeigen
        $('#absDokHinweis').text(offen ? 'Noch ' + (offen === 1 ? '1 Punkt' : offen + ' Punkte') + ' offen – die Dokumente zeigen den heutigen Stand.' : '').prop('hidden', !offen);
    }
    $('#absNeu').on('click', absendenPruefen);
    // Beim Ändern des Jahres im Dropdown beide Tabellen und die Prüfliste neu laden
    $('#yearSelect').on('change', function () {
        loadenda();
        loadendb();
        absendenPruefen();
    });

    // Initialisierung beim Laden der Seite
    initializeYearDropdown();
    loadenda();
    loadendb();
    absendenPruefen();
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
    try {
        const r = await fetch('absenden/generate_absendenbuch_pdf.php?year=' + encodeURIComponent(jahr), { credentials: 'same-origin' });
        let j = null;
        try { j = await r.json(); } catch (e) { /* keine JSON-Antwort, z.B. Fehlerseite */ }
        if (!r.ok || !j || !j.pdf_link) throw new Error((j && (j.message || j.error)) || ('Die Broschüre konnte nicht erstellt werden' + (r.ok ? '' : ' (Serverfehler ' + r.status + ')')));
        const a = document.createElement('a');
        a.href = j.pdf_link; a.download = 'Absendenbuch_' + jahr + '_Broschuere.pdf';
        document.body.appendChild(a); a.click(); a.remove();
        msvToast('Broschüre erstellt: ' + j.pages + ' Seiten auf ' + j.sheets + ' Blatt A4 (beidseitig)', 'success');
    } catch (err) {
        msvToast(err && err.message ? err.message : 'Die Broschüre konnte nicht erstellt werden', 'error');
    } finally {
        btn.disabled = false; btn.innerHTML = orig;
    }
});
</script>
<?php
include 'footer.inc.php';
?>
