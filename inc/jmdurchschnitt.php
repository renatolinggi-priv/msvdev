<?php
// jmdurchschnitt.php
include 'dbconnect.inc.php';

// Seitenspezifische Styles
$page_specific_css = "
/* === DURCHSCHNITT RESULTATE STYLES === */
.definition-selection-card {
    background: var(--ui-flaeche-2);
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--ui-rand);
}

.definition-checkbox {
    margin-bottom: 0.75rem;
    padding: 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
}

.definition-checkbox:hover {
    background-color: var(--ui-flaeche-2);
    border-color: var(--ui-akzent);
}

.definition-checkbox input:checked + label {
    font-weight: 600;
    color: var(--ui-akzent-dunkel);
}

.calculation-info {
    background: var(--ui-akzent-hell);
    border: 1px solid #bcd2f7;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border-radius: 0.375rem;
}

.result-preview-table {
    font-size: 0.85rem;
}

.result-preview-table th {
    background-color: var(--light-color);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    padding: 0.75rem;
    color: var(--secondary-color);
    border-bottom: 1px solid var(--ui-linie);
}

.result-preview-table td {
    padding: 0.5rem 0.75rem;
}

/* Spaltenbreiten für Durchschnittstabelle */
.result-preview-table .rang-col { width: 10%; }
.result-preview-table .name-col { width: 50%; }
.result-preview-table .punkte-col { width: 20%; }
.result-preview-table .verwendet-col { width: 20%; }

.average-score {
    font-weight: 700;
    color: var(--ui-akzent-dunkel);
}

.teilnehmer-count {
    font-size: 0.8rem;
    color: var(--ui-text-2);
}

/* Mobile Anpassungen */
@media (max-width: 768px) {
    .definition-selection-card {
        padding: 1rem;
    }

    .result-preview-table {
        font-size: 0.8rem;
    }

    .result-preview-table th,
    .result-preview-table td {
        padding: 0.5rem 0.25rem;
    }
}

/* Mobile Cards: zählende Resultate grün markieren (analog Desktop table-success) */
@media (max-width: 767.98px) {
    .mobile-card.card-used {
        border-color: var(--ui-ok-rand);
    }
    .mobile-card.card-used .mobile-card-header {
        background-color: var(--ui-ok-bg);
    }
}
";

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
?>

<!-- Select2 (für Schiessanlass-Auswahl) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
#anlassSelect + .select2-container { width: 100% !important; }
/* Kompaktere (kleinere) Schrift für dieses Select2-Feld */
#anlassSelect + .select2-container .select2-selection { min-height: calc(1.4em + 0.45rem + 2px) !important; }
#anlassSelect + .select2-container .select2-selection__rendered,
#anlassSelect + .select2-container .select2-selection__placeholder { font-size: 0.8rem !important; }
.select2-anlass-dropdown .select2-results__option,
.select2-anlass-dropdown .select2-search__field {
    font-size: 0.8rem !important;
    line-height: 1.25 !important;
}
.select2-anlass-dropdown .select2-results__option {
    padding-top: 0.3rem !important;
    padding-bottom: 0.3rem !important;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <!-- Äusserer weisser Container -->
            <div class="main-content-wrapper content-width-wide">
                <!-- Header ausserhalb des inneren Containers -->
                <?php
                $page_title = 'Sektionsabrechnungen';
                $page_title_after = '<button type="button" class="btn-help" data-help="jmdurchschnitt.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                // Ausgabe in der Kopf-Card wie auf allen Seiten; gesperrt, bis ein Anlass mit Resultaten berechnet ist
                $page_actions = '<div class="btn-group btn-group-sm" role="group" aria-label="Sektionsabrechnung">'
                    . '<button type="button" id="exportPdfBtn" class="btn btn-outline-info" disabled data-tooltip="Zuerst einen Anlass mit Resultaten wählen"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Sektionsabrechnung</span></button>'
                    . '<button type="button" class="btn btn-outline-info msv-druck" data-druck-blocked="1" data-druck-doctype="jmdurchschnitt" data-druck-label="JM Durchschnitte" aria-label="Sektionsabrechnung direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>'
                    . '</div>';
                $page_show_mobile = true;
                include 'partials/page_header.inc.php'; ?>
                
                <!-- Weisser Hintergrund-Container -->
                <div class="content-background">
                    <form id="durchschnittForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        
                        <!-- Konfiguration des gewählten Jahres -->
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-3">

                            <label for="zaehlendeInput" class="form-label fw-bold mb-0 text-nowrap"
                                   data-tooltip="Anzahl der besten Resultate, die in den Durchschnitt einfliessen (bei vielen Teilnehmern greift weiterhin die Hälfte-Regel).">
                                <i class="bi bi-list-ol me-1"></i>Zählende Resultate: <button type="button" class="btn-help" data-help="jmdurchschnitt.zaehlende" aria-label="Hilfe"></button>
                            </label>
                            <input type="number" id="zaehlendeInput" class="form-control form-control-sm"
                                   style="width: 80px;" min="1" max="99" step="1">
                            <button type="button" id="saveConfigBtn" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-save me-1"></i>Speichern
                            </button>
                            <small id="configHint" class="text-muted ms-1"></small>
                        </div>

                        <!-- Berechnungslogik Info -->
                        <!--
                        <div class="calculation-info">
                            <h6 class="mb-2">
                                <i class="bi bi-info-circle me-2"></i>Berechnungslogik:
                            </h6>
                            <ul class="mb-0 small">
                                <li><strong>Bis 13 Teilnehmer:</strong> Die besten 6 Resultate werden für den Durchschnitt verwendet</li>
                                <li><strong>Ab 14 Teilnehmer:</strong> Die Hälfte der besten Resultate (abgerundet)</li>
                                <li><strong>Zuschlagsberechnung:</strong> (Summe zählende + Zuschlag × Summe nicht-zählende ÷ 100) ÷ Anzahl zählende</li>
                                <li><strong>Streicher:</strong> Werden nicht in die Berechnung einbezogen</li>
                            </ul>
                        </div>
-->
                        <!-- JM Definition Auswahl -->
                        <div class="definition-selection-card">
                            <div class="row align-items-end">
                                <div class="col-md-8 col-lg-6">
                                    <label for="anlassSelect" class="form-label fw-bold">
                                        <i class="bi bi-target me-1"></i>Schiessanlass auswählen: <button type="button" class="btn-help" data-help="jmdurchschnitt.berechnung" aria-label="Hilfe"></button>
                                    </label>
                                    <select id="anlassSelect" class="form-select">
                                        <option value="">-- Bitte Anlass auswählen --</option>
                                        <!-- Optionen werden per JavaScript eingefügt -->
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Ergebnis-Vorschau -->
                        <div id="resultsContainer" style="display: none;">
                            <!-- Ladeanzeige -->
                            <div id="loadingIndicator" style="display: none;" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Berechne...</span>
                                </div>
                                <p class="mt-2 text-muted">Berechne Durchschnitt...</p>
                            </div>

                            <!-- Keine Resultate Meldung -->
                            <div id="noResultsMessage" style="display: none;" class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Für diesen Anlass sind noch keine Resultate vorhanden.
                            </div>

                            <div class="table-wrapper">
                                <h5 class="table-title" id="resultTitle">
                                    <i class="bi bi-table me-2"></i>
                                    Durchschnittsresultat
                                </h5>

                                <!-- Desktop: Tabelle -->
                                <div class="desktop-table-container">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0 result-preview-table" id="durchschnittTabelle">
                                            <thead>
                                                <tr>
                                                    <th class="rang-col">Rang</th>
                                                    <th class="name-col">Name</th>
                                                    <th class="punkte-col">Punkte</th>
                                                    <th class="verwendet-col">Verwendet</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Wird per JavaScript gefüllt -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Mobile: Cards -->
                                <div class="mobile-cards-container" id="mobileCardsDurchschnitt">
                                    <div class="mobile-search">
                                        <div class="position-relative">
                                            <i class="bi bi-search search-icon"></i>
                                            <input type="text" class="form-control" placeholder="Suchen..."
                                                   oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsDurchschnitt')">
                                        </div>
                                    </div>
                                    <div class="mobile-cards-scroll">
                                        <!-- Cards werden per JavaScript generiert -->
                                    </div>
                                </div>

                                <!-- Zusammenfassung -->
                                <div id="summaryCard" class="mt-3 mx-3 mb-3" style="display: none;">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row text-center g-3">
                                                <div class="col-6 col-md-2">
                                                    <strong>Teilnehmer:</strong><br>
                                                    <span id="totalParticipants" class="h5">-</span>
                                                </div>
                                                <div class="col-6 col-md-2">
                                                    <strong>Pflichtteilnehmer:</strong><br>
                                                    <span id="usedResults" class="h5">-</span>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <strong>Durchschnitt:</strong><br>
                                                    <span id="averageScore" class="h5">-</span>
                                                </div>
                                                <div class="col-6 col-md-2">
                                                    <strong>Beteiligungszuschlag:</strong><br>
                                                    <span id="bonusPoints" class="h5">-</span>
                                                </div>
                                                <div class="col-12 col-md-3">
                                                    <strong>Endergebnis:</strong><br>
                                                    <span id="finalResult" class="h3 fw-bold">-</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    var currentYear = new Date().getFullYear();
    var selectedDefinition = null;

    // Jahr-Dropdown initialisieren
    function initializeYearDropdown() {
        msvJahrAuswahl('#yearSelect');
    }

    // Konfiguration (Anzahl zählende Resultate) für ein Jahr laden
    function loadConfig(year) {
        $('#configHint').text('');
        $.ajax({
            url: 'jmdurchschnitt/get_config.php',
            type: 'GET',
            data: { year: year },
            dataType: 'json',
            error: function (xhr) { msvToast(msvXhrMessage(xhr, 'Einstellung konnte nicht geladen werden'), 'error'); },
            success: function (response) {
                if (response.success) {
                    $('#zaehlendeInput').val(response.anzahl_zaehlende);
                    if (response.inherited) {
                        $('#configHint').text(
                            response.source_year
                                ? 'übernommen aus ' + response.source_year
                                : 'Standardwert'
                        );
                    } else {
                        $('#configHint').text('');
                    }
                } else {
                    msvToast(response.message || 'Einstellung konnte nicht geladen werden', 'error');
                }
            }
        });
    }

    // Select2 auf dem Anlass-Dropdown (neu) aufsetzen – nach jedem Befüllen nötig
    function applyAnlassSelect2() {
        const $sel = $('#anlassSelect');
        if ($sel.hasClass('select2-hidden-accessible')) {
            $sel.select2('destroy');
        }
        $sel.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: '-- Bitte Anlass auswählen --',
            dropdownCssClass: 'select2-anlass-dropdown',
            language: {
                noResults: function () { return 'Keine Treffer'; },
                searching: function () { return 'Suche…'; }
            }
        });
    }

    // Verfügbare JM-Definitionen laden
    function loadAvailableDefinitions(year) {
        const $sel = $('#anlassSelect');
        if ($sel.hasClass('select2-hidden-accessible')) {
            $sel.select2('destroy');
        }
        $sel.html('<option value="">Lade Anlässe...</option>');

        $.ajax({
            url: 'jmdurchschnitt/load_available_definitions.php',
            type: 'GET',
            data: { year: year },
            dataType: 'json',
            success: function (response) {
                $sel.html('<option value="">-- Bitte Anlass auswählen --</option>');

                if (response.success && response.definitions.length > 0) {
                    response.definitions.forEach(function(def) {
                        const option = $('<option></option>')
                            .val(def.ID)
                            .text(`${def.Bezeichnung} (Max: ${def.Maxpunkte}, Beteiligungszuschlag: ${def.Zuschlag || 0})`);
                        $sel.append(option);
                    });
                } else {
                    $sel.append('<option value="" disabled>Keine Anlässe verfügbar</option>');
                }
                applyAnlassSelect2();
            },
            error: function () {
                $sel.html('<option value="" disabled>Fehler beim Laden</option>');
                applyAnlassSelect2();
                msvToast('Fehler beim Laden der verfügbaren Anlässe', 'error');
            }
        });
    }

    // Anlass-Auswahl überwachen und automatisch berechnen
    $('#anlassSelect').on('change', function() {
        selectedDefinition = $(this).val();
        
        jmdAusgabeBereit(false);
        $('#resultsContainer').hide();
        $('#summaryCard').hide();
        $('#noResultsMessage').hide();
        $('#loadingIndicator').hide();
        
        // Automatisch berechnen wenn ein Anlass ausgewählt wurde
        if (selectedDefinition) {
            calculateAverages();
        }
    });

    // Durchschnitte berechnen (als separate Funktion)
    function calculateAverages() {
        if (!selectedDefinition) {
            return;
        }

        // Ladeanzeige zeigen
        $('#resultsContainer').show();
        $('#loadingIndicator').show();
        $('#noResultsMessage').hide();
        $('#summaryCard').hide();

        $.ajax({
            url: 'jmdurchschnitt/calculate_averages.php',
            type: 'POST',
            data: {
                year: $('#yearSelect').val(),
                definition_id: selectedDefinition,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                $('#loadingIndicator').hide();
                
                if (response.success) {
                    if (response.result && response.result.alle_resultate && response.result.alle_resultate.length > 0) {
                        displayResults(response.result);
                        jmdAusgabeBereit(true);
                    } else {
                        // Keine Resultate vorhanden
                        $('#noResultsMessage').show();
                        jmdAusgabeBereit(false);
                    }
                } else {
                    $('#noResultsMessage').show();
                    msvToast(response.message || 'Die Berechnung hat nicht geklappt. Bitte die Seite neu laden.', 'error');
                }
            },
            error: function() {
                $('#loadingIndicator').hide();
                $('#noResultsMessage').show();
                msvToast('Fehler bei der Berechnung', 'error');
            }
        });
    }

    // Ergebnisse anzeigen
    function displayResults(result) {
        // Titel aktualisieren
        $('#resultTitle').html(`<i class="bi bi-table me-2"></i>${result.anlass_name} - Durchschnittsresultat`);
        
        // Tabelle füllen
        let html = '';
        result.alle_resultate.forEach(function(teilnehmer, index) {
            const isUsed = index < result.verwendete_resultate;
            const cssClass = isUsed ? 'table-success' : '';
            const verwendetText = isUsed ? '✓' : '✗';
            
            html += `
                <tr class="${cssClass}">
                    <td class="fw-bold">${index + 1}</td>
                    <td>${teilnehmer.name}</td>
                    <td class="text-center">${teilnehmer.punkte}</td>
                    <td class="text-center">${verwendetText}</td>
                </tr>
            `;
        });
        
        $('#durchschnittTabelle tbody').html(html);
        
        buildMobileCardsDurchschnitt();
        // Zusammenfassung aktualisieren
        $('#totalParticipants').text(result.teilnehmer_anzahl);
        $('#usedResults').text(result.verwendete_resultate);
        $('#averageScore').text(result.durchschnitt);
        $('#bonusPoints').text(result.zuschlag);
        $('#finalResult').text(result.endergebnis);
        
        $('#summaryCard').show();
        $('#resultsContainer').show();
    }

    // PDF Export
    // PDF und Direktdruck nur, wenn ein Anlass mit Resultaten berechnet ist – beide gemeinsam sperren
    function jmdAusgabeBereit(an) {
        $('#exportPdfBtn').prop('disabled', !an)
            .attr('data-tooltip', an ? 'Sektionsabrechnung des gewählten Anlasses' : 'Zuerst einen Anlass mit Resultaten wählen');
        $('.msv-druck[data-druck-doctype="jmdurchschnitt"]').attr('data-druck-blocked', an ? '0' : '1');
        if (window.MsvDruck) MsvDruck.refresh();
    }

    // Ausgabe-Baustein msvAusgabe: sperren, Spinner, Download, Toast
    $('#exportPdfBtn').on('click', function() {
        const jahr = $('#yearSelect').val();
        const anlass = $('#anlassSelect option:selected').text().trim();
        msvAusgabe(this, {
            url: 'jmdurchschnitt/export_averages_pdf.php',
            method: 'POST',
            csrf: true,
            data: {
                year: jahr,
                definition_id: selectedDefinition,
                orientation: window.MsvDruck ? MsvDruck.orientierung('jmdurchschnitt', 'portrait') : 'portrait' // Format aus dem Druckprofil
            },
            titel: 'Sektionsabrechnung ' + anlass + ' ' + jahr,
            name: 'Sektionsabrechnung_' + anlass + '_' + jahr,
            fehler: 'Die Durchschnitte konnten nicht als PDF erstellt werden. Bitte nochmals versuchen.'
        });
    });

    // Konfiguration speichern
    $('#saveConfigBtn').on('click', function() {
        const $btn = $(this);
        const year = $('#yearSelect').val();
        const anzahl = parseInt($('#zaehlendeInput').val(), 10);

        if (isNaN(anzahl) || anzahl < 1 || anzahl > 99) {
            msvToast('Bitte eine Anzahl zwischen 1 und 99 eingeben', 'error');
            return;
        }

        $btn.prop('disabled', true);
        $.ajax({
            url: 'jmdurchschnitt/save_config.php',
            type: 'POST',
            data: {
                year: year,
                anzahl_zaehlende: anzahl,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#configHint').text('');
                    msvToast('Einstellung gespeichert', 'success');
                    // Bereits berechnetes Ergebnis mit neuem Wert aktualisieren
                    if (selectedDefinition) {
                        calculateAverages();
                    }
                } else {
                    msvToast(response.message || 'Fehler beim Speichern', 'error');
                }
            },
            error: function() {
                msvToast('Fehler beim Speichern der Einstellung', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Jahr-Änderung
    $('#yearSelect').on('change', function() {
        const selectedYear = $(this).val();
        loadConfig(selectedYear);
        loadAvailableDefinitions(selectedYear);
        selectedDefinition = null;
        jmdAusgabeBereit(false);
        $('#resultsContainer').hide();
        $('#summaryCard').hide();
        $('#noResultsMessage').hide();
        $('#loadingIndicator').hide();
    });

    // Initialisierung
    initializeYearDropdown();
    loadConfig($('#yearSelect').val());
    loadAvailableDefinitions($('#yearSelect').val());

    // Tooltip aktivieren
    if (window.bootstrap && bootstrap.Tooltip) {
        $('[data-bs-toggle="tooltip"]').each(function () {
            new bootstrap.Tooltip(this);
        });
    }
});

    // Mobile Cards Builder für Durchschnittstabelle
    function buildMobileCardsDurchschnitt() {
        MSVMobileCards.initResponsive(function () {
            MSVMobileCards.buildCards('#durchschnittTabelle', '#mobileCardsDurchschnitt', {
                titleColumns: [0, 1],
                summaryColumns: [2],
                customCardClass: function (row, cells) {
                    // "Verwendet"-Spalte (Index 3): zählende Resultate grün markieren – wie Desktop
                    return (cells[3]?.textContent || '').trim() === '✓' ? 'card-used' : '';
                }
            });
        });
    }

</script>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray): gleicher POST wie der Export-Button (year, definition_id, csrf_token) → JSON {pdf_url}
MsvDruck.resolve('jmdurchschnitt', () => {
    const jahr = $('#yearSelect').val();
    const definitionId = $('#anlassSelect').val();
    if (!definitionId) { msvToast('Bitte zuerst einen Anlass wählen', 'warning'); return null; }
    const anlass = $('#anlassSelect option:selected').text().trim();
    return {
        url: 'jmdurchschnitt/export_averages_pdf.php',
        method: 'POST',
        body: new URLSearchParams({ year: jahr, definition_id: definitionId, orientation: MsvDruck.orientierung('jmdurchschnitt', 'portrait'), csrf_token: $('input[name="csrf_token"]').val() }),
        jobName: 'JM Durchschnitt ' + anlass + ' ' + jahr
    };
});
</script>
<?
include 'footer.inc.php';
?>
