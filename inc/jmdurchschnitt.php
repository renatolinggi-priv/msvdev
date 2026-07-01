<?php
// jmdurchschnitt.php
include 'dbconnect.inc.php';

// Seitenspezifische Styles
$page_specific_css = "
/* === DURCHSCHNITT RESULTATE STYLES === */
.definition-selection-card {
    background: #ffffff;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid #f1f5f9;
}

.definition-checkbox {
    margin-bottom: 0.75rem;
    padding: 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
}

.definition-checkbox:hover {
    background-color: #f8f9fa;
    border-color: #007bff;
}

.definition-checkbox input:checked + label {
    font-weight: 600;
    color: #007bff;
}

.calculation-info {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
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
    border-bottom: 2px solid #dee2e6;
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
    color: #007bff;
}

.teilnehmer-count {
    font-size: 0.8rem;
    color: #6c757d;
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
        border-color: #198754;
    }
    .mobile-card.card-used .mobile-card-header {
        background-color: #d1e7dd;
    }
}
";

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
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
        <div class="col-xxl-7 col-xl-9 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <?php $page_title = 'Durchschnittsresultate JM'; include 'partials/page_header.inc.php'; ?>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <form id="durchschnittForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        
                        <!-- Jahr-Auswahl + Konfiguration -->
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                            <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                                <i class="bi bi-calendar3 me-1"></i>Jahr:
                            </label>
                            <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;">
                                <!-- Optionen werden per JavaScript eingefügt -->
                            </select>

                            <span class="mx-1 text-muted d-none d-md-inline">|</span>

                            <label for="zaehlendeInput" class="form-label fw-bold mb-0 text-nowrap"
                                   data-bs-toggle="tooltip"
                                   title="Anzahl der besten Resultate, die in den Durchschnitt einfließen (bei vielen Teilnehmern greift weiterhin die Hälfte-Regel).">
                                <i class="bi bi-list-ol me-1"></i>Zählende Resultate:
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
                                <div class="col-md-8">
                                    <label for="anlassSelect" class="form-label fw-bold">
                                        <i class="bi bi-target me-1"></i>Schießanlass auswählen:
                                    </label>
                                    <select id="anlassSelect" class="form-select">
                                        <option value="">-- Bitte Anlass auswählen --</option>
                                        <!-- Optionen werden per JavaScript eingefügt -->
                                    </select>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button type="button" id="exportPdfBtn" class="btn btn-outline-info btn-sm" disabled>
                                        <i class="bi bi-file-pdf me-2"></i>
                                        PDF exportieren
                                    </button>
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
                                                    <span id="totalParticipants" class="h5 text-primary">-</span>
                                                </div>
                                                <div class="col-6 col-md-2">
                                                    <strong>Pflichtteilnehmer:</strong><br>
                                                    <span id="usedResults" class="h5 text-info">-</span>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <strong>Durchschnitt:</strong><br>
                                                    <span id="averageScore" class="h5 text-warning">-</span>
                                                </div>
                                                <div class="col-6 col-md-2">
                                                    <strong>Beteiligungszuschlag:</strong><br>
                                                    <span id="bonusPoints" class="h5 text-secondary">-</span>
                                                </div>
                                                <div class="col-12 col-md-3">
                                                    <strong>Endergebnis:</strong><br>
                                                    <span id="finalResult" class="h3 text-success">-</span>
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
        const yearSelect = $('#yearSelect').empty();
        for (let year = currentYear; year >= currentYear - 3; year--) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }
    }

    // Konfiguration (Anzahl zählende Resultate) für ein Jahr laden
    function loadConfig(year) {
        $('#configHint').text('');
        $.ajax({
            url: 'jmdurchschnitt/get_config.php',
            type: 'GET',
            data: { year: year },
            dataType: 'json',
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
        
        $('#exportPdfBtn').prop('disabled', true);
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
                        $('#exportPdfBtn').prop('disabled', false);
                        msvToast('Durchschnitt erfolgreich berechnet', 'success');
                    } else {
                        // Keine Resultate vorhanden
                        $('#noResultsMessage').show();
                        $('#exportPdfBtn').prop('disabled', true);
                    }
                } else {
                    $('#noResultsMessage').show();
                    msvToast('Fehler bei der Berechnung: ' + (response.message || 'Unbekannter Fehler'), 'error');
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
    $('#exportPdfBtn').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...');

        $.ajax({
            url: 'jmdurchschnitt/export_averages_pdf.php',
            type: 'POST',
            data: {
                year: $('#yearSelect').val(),
                definition_id: selectedDefinition,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // PDF zum Download anbieten
                    const link = document.createElement('a');
                    link.href = response.pdf_url;
                    link.download = response.filename;
                    link.click();
                    msvToast('PDF erfolgreich generiert', 'success');
                } else {
                    msvToast('Fehler beim PDF-Export: ' + (response.message || 'Unbekannter Fehler'), 'error');
                }
            },
            error: function() {
                msvToast('Fehler beim PDF-Export', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
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
        $('#exportPdfBtn').prop('disabled', true);
        $('#resultsContainer').hide();
        $('#summaryCard').hide();
        $('#noResultsMessage').hide();
        $('#loadingIndicator').hide();
    });

    // Initialisierung
    initializeYearDropdown();
    loadConfig(currentYear);
    loadAvailableDefinitions(currentYear);

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

<?
include 'footer.inc.php';
?>
