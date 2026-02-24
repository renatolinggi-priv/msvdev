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
    font-size: 0.9rem;
}

.result-preview-table th {
    background-color: var(--light-color);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    padding: 1rem 0.75rem;
    color: var(--secondary-color);
    border-bottom: 2px solid #dee2e6;
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
";

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-6 col-lg-8 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-calculator me-2"></i>
                            Durchschnittsresultate JM
                        </h2>
                        <p class="text-muted mb-0">Berechnung der Durchschnittsresultate basierend auf den besten Resultaten</p>
                    </div>
                </div>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <form id="durchschnittForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        
                        <!-- Jahr-Auswahl -->
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                                <i class="bi bi-calendar3 me-1"></i>Jahr:
                            </label>
                            <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;">
                                <!-- Optionen werden per JavaScript eingefügt -->
                            </select>
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
                                    <button type="button" id="exportPdfBtn" class="btn btn-compact-standard btn-outline-success" disabled>
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
                                <div class="table-responsive">
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
                                <!-- Mobile Cards Container -->
                                <div class="mobile-cards-container" id="mobileCardsDurchschnitt">
                                    <div class="mobile-search-container">
                                        <input type="text" class="form-control mobile-search-input" placeholder="Suchen...">
                                    </div>
                                    <div class="mobile-scroll-container">
                                        <!-- Cards werden hier eingefügt -->
                                    </div>
                                </div>
                                
                                <!-- Zusammenfassung -->
                                <div id="summaryCard" class="mt-3" style="display: none;">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row text-center">
                                                <div class="col-md-2">
                                                    <strong>Teilnehmer:</strong><br>
                                                    <span id="totalParticipants" class="h5 text-primary">-</span>
                                                </div>
                                                <div class="col-md-2">
                                                    <strong>Pflichtteilnehmer:</strong><br>
                                                    <span id="usedResults" class="h5 text-info">-</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <strong>Durchschnitt:</strong><br>
                                                    <span id="averageScore" class="h5 text-warning">-</span>
                                                </div>
                                                <div class="col-md-2">
                                                    <strong>Beteiligungszuschlag:</strong><br>
                                                    <span id="bonusPoints" class="h5 text-secondary">-</span>
                                                </div>
                                                <div class="col-md-3">
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

    // Verfügbare JM-Definitionen laden
    function loadAvailableDefinitions(year) {
        $('#anlassSelect').html('<option value="">Lade Anlässe...</option>');

        $.ajax({
            url: 'jmdurchschnitt/load_available_definitions.php',
            type: 'GET',
            data: { year: year },
            dataType: 'json',
            success: function (response) {
                $('#anlassSelect').html('<option value="">-- Bitte Anlass auswählen --</option>');
                
                if (response.success && response.definitions.length > 0) {
                    response.definitions.forEach(function(def) {
                        const option = $('<option></option>')
                            .val(def.ID)
                            .text(`${def.Bezeichnung} (Max: ${def.Maxpunkte}, Beteiligungszuschlag: ${def.Zuschlag || 0})`);
                        $('#anlassSelect').append(option);
                    });
                } else {
                    $('#anlassSelect').append('<option value="" disabled>Keine Anlässe verfügbar</option>');
                }
            },
            error: function () {
                $('#anlassSelect').html('<option value="" disabled>Fehler beim Laden</option>');
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

    // Jahr-Änderung
    $('#yearSelect').on('change', function() {
        const selectedYear = $(this).val();
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
    loadAvailableDefinitions(currentYear);
});

    // Mobile Cards Builder für Durchschnittstabelle
    function buildMobileCardsDurchschnitt() {
        MSVMobileCards.initResponsive({
            tableId: 'durchschnittTabelle',
            mobileContainerId: 'mobileCardsDurchschnitt',
            titleColumns: [0, 1],
            summaryColumns: [2],
            rankColumn: 0
        });
    }

</script>

<?
include 'footer.inc.php';
?>
