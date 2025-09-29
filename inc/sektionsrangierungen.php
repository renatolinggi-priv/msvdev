<?php
// sektionsrangierungen.php
include 'dbconnect.inc.php';

// Seitenspezifische Styles
$page_specific_css = "
/* === SEKTIONSRANGIERUNGEN STYLES === */
/* Das Form muss in der Flex-Kette sein */
#rankingForm {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0 !important;
}

#rankingsContainer {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0 !important;
    overflow: hidden;
}
/* Flex-Layout für scrollbare Tabelle - überschreibt die table-wrapper defaults */
.table-wrapper {
    display: flex !important;
    flex-direction: column !important;
    flex: 1 1 auto !important;
    min-height: 0 !important;
    overflow: hidden !important;
    margin-bottom: 0 !important;
}

.ranking-card {
    background: #ffffff;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    padding: 1.5rem;
    margin-bottom: 0;
    border: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}

.ranking-table {
    font-size: 0.9rem;
}

.ranking-table th {
    background-color: var(--light-color);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    padding: 1rem 0.75rem;
    color: var(--secondary-color);
    border-bottom: 2px solid #dee2e6;
    position: sticky;
    top: 0;
    z-index: 10;
}

/* Spaltenbreiten für Sektionsrangierungen */
.ranking-table .anlass-col { width: 50%; text-align: left; }
.ranking-table .rang-col { width: 10%; }
.ranking-table .preis-col { width: 20%; }
.ranking-table .aktionen-col { width: 20%; }

/* Explizite Linksbündigkeit für erste Spalte - KEINE sticky spalte hier */
.ranking-table td:first-child,
.ranking-table th:first-child {
    text-align: left !important;
}

/* NUR Header-Zeile sticky machen, nicht die erste Spalte */
.ranking-table thead {
    position: sticky;
    top: 0;
    z-index: 20;
    background-color: var(--light-color);
}

.ranking-table thead th {
    background-color: var(--light-color) !important;
    position: sticky;
    top: 0;
}

/* Body-Zellen normale Hintergründe */
.ranking-table tbody td {
    background-color: #ffffff;
    position: static !important; /* Keine sticky Spalten im Body */
}

.ranking-table tbody tr.table-secondary td {
    background-color: #e9ecef !important;
}

.add-ranking-card {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 0.75rem;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.2s ease;
}

.add-ranking-card:hover {
    border-color: #007bff;
    background: #f0f8ff;
}

/* Mobile Anpassungen - Verbessert */
.table-responsive {
    flex: 1 1 auto;
    min-height: 0 !important;
    overflow: auto !important;
    -webkit-overflow-scrolling: touch;
}

/* Erst bei sehr kleinen Bildschirmen responsiv werden */
@media (max-width: 576px) {
    .ranking-card {
        padding: 1rem;
        margin-left: -15px;
        margin-right: -15px;
        border-radius: 0;
    }
    
    .ranking-table {
        font-size: 0.8rem;
        min-width: 600px; /* Verhindert vorzeitiges Umbrechen */
    }
    
    .ranking-table th,
    .ranking-table td {
        padding: 0.5rem 0.25rem;
        white-space: nowrap;
    }
}

/* Mittlere Bildschirme - mehr Platz nutzen */
@media (min-width: 769px) and (max-width: 1200px) {
    .ranking-table .anlass-col { width: 45%; }
    .ranking-table .rang-col { width: 12%; }
    .ranking-table .preis-col { width: 18%; }
    .ranking-table .aktionen-col { width: 25%; }
}

/* Action Buttons Styling - gleiche Größe wie in Endresultate */
.ranking-table td:last-child .btn {
    margin: 1px !important;
    padding: 0.25rem 0.4rem !important;
    font-size: 0.8rem !important;
}

.ranking-table td:last-child {
    white-space: nowrap !important;
}
";

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<link rel="stylesheet" href="../css/fixes/no-page-scroll-override.css">
<div class="container-fluid">
    <div class="row">
        <div class="col-xxl-6 col-xl-8 col-lg-10 col-md-12 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-trophy me-2"></i>
                            Sektionsrangierungen
                        </h2>
                        <p class="text-muted mb-0">Verwaltung der Rangierungen für JM-Anlässe</p>
                    </div>
                </div>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <form id="rankingForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        
                        <!-- Jahr-Auswahl -->
                        <div class="year-selection-card">
                            <div class="row align-items-center">
                                <div class="col-md-5">
                                    <label for="yearSelect" class="form-label fw-bold">
                                        <i class="bi bi-calendar3 me-1"></i>Jahr auswählen:
                                    </label>
                                    <select id="yearSelect" class="form-select">
                                        <!-- Optionen werden per JavaScript eingefügt -->
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Neue Rangierung hinzufügen -->
                        <div class="add-ranking-card" id="addRankingCard" style="display: none;">
                            <h6 class="mb-3">
                                <i class="bi bi-plus-circle me-2"></i>
                                Neue Rangierung hinzufügen
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="anlassSelect" class="form-label">Anlass:</label>
                                    <select id="anlassSelect" class="form-select">
                                        <option value="">-- Anlass auswählen --</option>
                                        <!-- Optionen werden per JavaScript eingefügt -->
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="rangInput" class="form-label">Rang:</label>
                                    <input type="number" id="rangInput" class="form-control" min="1" max="999" placeholder="z.B. 1">
                                </div>
                                <div class="col-md-3">
                                    <label for="preisInput" class="form-label">Preis (CHF):</label>
                                    <input type="number" id="preisInput" class="form-control" min="0" step="0.05" placeholder="z.B. 100.00">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" id="saveRankingBtn" class="btn btn-compact-standard btn-outline-primary w-100">
                                        <i class="bi bi-save me-1"></i>Speichern
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Button Toolbar -->
                        <div class="button-toolbar">
                            <div class="button-group">
                                <button type="button" id="addNewBtn" class="btn btn-compact-standard btn-outline-primary" disabled>
                                    <i class="bi bi-plus-circle me-2"></i>
                                    Neue Rangierung
                                </button>
                                <button type="button" class="btn btn-compact-standard btn-outline-success" id="exportPdfBtn" style="display: none;">
                                    <i class="bi bi-file-pdf me-2"></i>
                                    PDF Export
                                </button>
                            </div>
                        </div>

                        <!-- Vorhandene Rangierungen -->
                        <div id="rankingsContainer" style="display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden;">
                            <div class="ranking-card">
                                <h5 class="mb-3">
                                    <i class="bi bi-list-ul me-2"></i>
                                    Vorhandene Rangierungen
                                </h5>
                                <div class="table-wrapper" style="display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden;">
                                    <div class="table-responsive" id="rankingsList" style="flex: 1 1 auto; min-height: 0; overflow: auto;">
                                        <!-- Wird per JavaScript geladen -->
                                        <div class="text-center py-4">
                                            <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                                            Lade Rangierungen...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div><!-- /content-background -->
            </div><!-- /main-content-wrapper -->
        </div>
    </div>
</div>

<!-- Edit Rangierung Modal -->
<div class="modal fade" id="editRankingModal" tabindex="-1" aria-labelledby="editRankingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRankingModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>
                    Rangierung bearbeiten
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editRankingForm">
                    <input type="hidden" id="editRankingId" value="">
                    
                    <div class="mb-3">
                        <label for="editAnlassName" class="form-label fw-bold">Anlass:</label>
                        <input type="text" class="form-control" id="editAnlassName" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editRangInput" class="form-label fw-bold">
                                    <i class="bi bi-trophy me-1"></i>Rang:
                                </label>
                                <input type="number" class="form-control" id="editRangInput"
                                       min="1" max="999" placeholder="z.B. 1" required>
                                <div class="invalid-feedback">
                                    Bitte geben Sie einen gültigen Rang ein.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editPreisInput" class="form-label fw-bold">
                                    <i class="bi bi-cash me-1"></i>Preis (CHF):
                                </label>
                                <input type="number" class="form-control" id="editPreisInput"
                                       min="0" step="0.05" placeholder="z.B. 100.00" required>
                                <div class="invalid-feedback">
                                    Bitte geben Sie einen gültigen Preis ein.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info d-none" id="editModalAlert">
                        <i class="bi bi-info-circle me-2"></i>
                        <span id="editModalAlertText"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-primary" id="saveEditRankingBtn">
                    <i class="bi bi-check-circle me-1"></i>Speichern
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    var currentYear = new Date().getFullYear();
    var selectedYear = currentYear;
    
    // Viewport-Variablen setzen (wie bei heimresultate.php)
    function applyViewportVars() {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', vh + 'px');
        
        const headerEl = document.querySelector('.navbar, header, .site-header');
        const footerEl = document.querySelector('footer, .site-footer');
        
        const headerH = headerEl ? Math.round(headerEl.getBoundingClientRect().height) : 0;
        const footerH = footerEl ? Math.round(footerEl.getBoundingClientRect().height) : 0;
        
        document.documentElement.style.setProperty('--app-header', headerH + 'px');
        document.documentElement.style.setProperty('--app-footer', footerH + 'px');
    }
    
    // Initial und bei Resize
    applyViewportVars();
    window.addEventListener('resize', applyViewportVars);
    window.addEventListener('load', applyViewportVars);

    // Toast-Funktion
    function showToast(message, type = 'info') {
        if ($('#toast-container').length === 0) {
            $('body').append('<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>');
        }
        
        const toast = $('<div>')
            .addClass(`toast-message toast-${type}`)
            .html(`<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>${message}`);

        $('#toast-container').append(toast);

        setTimeout(() => {
            toast.addClass('show');
        }, 100);

        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Jahr-Dropdown initialisieren
    function initializeYearDropdown() {
        const yearSelect = $('#yearSelect').empty();
        for (let year = 2024; year <= currentYear; year++) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }
    }

    // Verfügbare Anlässe laden (noch keine Rangierung vorhanden)
    function loadAvailableDefinitions(year) {
        $('#anlassSelect').html('<option value="">Lade Anlässe...</option>');

        $.ajax({
            url: 'sektionsrangierungen/load_available_definitions.php',
            type: 'GET',
            data: { year: year },
            dataType: 'json',
            success: function (response) {
                $('#anlassSelect').html('<option value="">-- Anlass auswählen --</option>');
                
                if (response.success && response.definitions.length > 0) {
                    response.definitions.forEach(function(def) {
                        const option = $('<option></option>')
                            .val(def.ID)
                            .text(def.Bezeichnung);
                        $('#anlassSelect').append(option);
                    });
                } else {
                    $('#anlassSelect').append('<option value="" disabled>Alle Anlässe bereits bewertet</option>');
                }
                
                // Button aktivieren wenn Anlässe verfügbar
                $('#addNewBtn').prop('disabled', response.definitions.length === 0);
            },
            error: function () {
                $('#anlassSelect').html('<option value="" disabled>Fehler beim Laden</option>');
                showToast('Fehler beim Laden der verfügbaren Anlässe', 'error');
            }
        });
    }

    // Vorhandene Rangierungen laden
    function loadExistingRankings(year) {
        $('#rankingsList').html(`
            <div class="text-center py-4">
                <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                Lade Rangierungen...
            </div>
        `);

        $.ajax({
            url: 'sektionsrangierungen/load_existing_rankings.php',
            type: 'GET',
            data: { year: year },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.rankings.length > 0) {
                    displayRankings(response.rankings);
                    // Tabellenhöhe nach dem Laden neu berechnen
                    setTimeout(() => {
                        const event = new Event('resize');
                        window.dispatchEvent(event);
                    }, 100);
                    // PDF Export Button anzeigen
                    $('#exportPdfBtn').show();
                } else {
                    $('#rankingsList').html(`
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-info-circle me-2"></i>
                            Noch keine Rangierungen für das Jahr ${year} erfasst.
                        </div>
                    `);
                    // PDF Export Button verstecken
                    $('#exportPdfBtn').hide();
                }
            },
            error: function () {
                $('#rankingsList').html(`
                    <div class="text-center py-4 text-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Fehler beim Laden der Rangierungen.
                    </div>
                `);
                showToast('Fehler beim Laden der Rangierungen', 'error');
            }
        });
    }

    // Rangierungen anzeigen
    function displayRankings(rankings) {
        let totalPrize = 0;
        
        let html = `
                <table class="table table-hover ranking-table">
                    <thead>
                        <tr>
                            <th class="anlass-col">Anlass</th>
                            <th class="rang-col text-center">Rang</th>
                            <th class="preis-col text-end">Preis (CHF)</th>
                            <th class="aktionen-col text-center">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        rankings.forEach(function(ranking) {
            totalPrize += parseFloat(ranking.preis);
            html += `
                <tr>
                    <td class="fw-medium">${ranking.bezeichnung}</td>
                    <td class="text-center">
                        <span class="badge bg-primary">${ranking.rang}</span>
                    </td>
                    <td class="text-end fw-bold">CHF ${parseFloat(ranking.preis).toFixed(2)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-primary me-1 edit-ranking"
                                data-id="${ranking.id}" data-rang="${ranking.rang}" data-preis="${ranking.preis}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger delete-ranking"
                                data-id="${ranking.id}" data-anlass="${ranking.bezeichnung}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        
        // Total-Zeile hinzufügen
        html += `
                <tr class="table-secondary fw-bold">
                    <td class="fw-bold">TOTAL</td>
                    <td class="text-center">-</td>
                    <td class="text-end fw-bold">CHF ${totalPrize.toFixed(2)}</td>
                    <td class="text-center">-</td>
                </tr>
        `;
        
        html += '</tbody></table>';
        $('#rankingsList').html(html);
    }

    // Event Handlers
    $('#addNewBtn').on('click', function() {
        $('#addRankingCard').slideDown();
        $(this).prop('disabled', true);
    });

    $('#saveRankingBtn').on('click', function() {
        const anlassId = $('#anlassSelect').val();
        const rang = $('#rangInput').val();
        const preis = $('#preisInput').val();

        if (!anlassId || !rang || !preis) {
            showToast('Bitte alle Felder ausfüllen', 'warning');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        $.ajax({
            url: 'sektionsrangierungen/save_ranking.php',
            type: 'POST',
            data: {
                year: selectedYear,
                anlass_id: anlassId,
                rang: rang,
                preis: preis,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Rangierung erfolgreich gespeichert', 'success');
                    
                    // Formular zurücksetzen
                    $('#anlassSelect').val('');
                    $('#rangInput').val('');
                    $('#preisInput').val('');
                    $('#addRankingCard').slideUp();
                    $('#addNewBtn').prop('disabled', false);
                    
                    // Listen neu laden
                    loadAvailableDefinitions(selectedYear);
                    loadExistingRankings(selectedYear);
                } else {
                    showToast('Fehler beim Speichern: ' + (response.message || 'Unbekannter Fehler'), 'error');
                }
            },
            error: function() {
                showToast('Fehler beim Speichern der Rangierung', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Edit-Button Event Handler - Modal öffnen
    $(document).on('click', '.edit-ranking', function() {
        const id = $(this).data('id');
        const currentRang = $(this).data('rang');
        const currentPreis = $(this).data('preis');
        const anlassName = $(this).closest('tr').find('td:first').text();
        
        // Modal mit aktuellen Werten füllen
        $('#editRankingId').val(id);
        $('#editAnlassName').val(anlassName);
        $('#editRangInput').val(currentRang);
        $('#editPreisInput').val(currentPreis);
        
        // Validation-Klassen zurücksetzen
        $('#editRankingForm')[0].classList.remove('was-validated');
        $('#editRangInput, #editPreisInput').removeClass('is-invalid');
        $('#editModalAlert').addClass('d-none');
        
        // Modal öffnen
        $('#editRankingModal').modal('show');
    });

    // Modal Save Button Event Handler
    $('#saveEditRankingBtn').on('click', function() {
        const form = $('#editRankingForm')[0];
        const id = $('#editRankingId').val();
        const newRang = $('#editRangInput').val();
        const newPreis = $('#editPreisInput').val();
        
        // Form validieren
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        // Zusätzliche Validierung
        if (!newRang || !newPreis || isNaN(newRang) || isNaN(newPreis)) {
            $('#editModalAlert')
                .removeClass('d-none alert-info')
                .addClass('alert-danger')
                .find('#editModalAlertText')
                .text('Bitte geben Sie gültige Werte für Rang und Preis ein.');
            return;
        }
        
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
        
        // Update durchführen
        $.ajax({
            url: 'sektionsrangierungen/update_ranking.php',
            type: 'POST',
            data: {
                ranking_id: id,
                rang: parseInt(newRang),
                preis: parseFloat(newPreis),
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Rangierung erfolgreich aktualisiert', 'success');
                    $('#editRankingModal').modal('hide');
                    loadExistingRankings(selectedYear);
                } else {
                    $('#editModalAlert')
                        .removeClass('d-none alert-info')
                        .addClass('alert-danger')
                        .find('#editModalAlertText')
                        .text('Fehler beim Aktualisieren: ' + (response.message || 'Unbekannter Fehler'));
                }
            },
            error: function() {
                $('#editModalAlert')
                    .removeClass('d-none alert-info')
                    .addClass('alert-danger')
                    .find('#editModalAlertText')
                    .text('Fehler beim Aktualisieren der Rangierung');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Modal Reset beim Schließen
    $('#editRankingModal').on('hidden.bs.modal', function() {
        $('#editRankingForm')[0].classList.remove('was-validated');
        $('#editRangInput, #editPreisInput').removeClass('is-invalid');
        $('#editModalAlert').addClass('d-none');
    });

    // Delete-Button Event Handler
    $(document).on('click', '.delete-ranking', function() {
        const id = $(this).data('id');
        const anlassName = $(this).data('anlass');
        
        if (!confirm(`Möchten Sie die Rangierung für "${anlassName}" wirklich löschen?`)) {
            return;
        }
        
        $.ajax({
            url: 'sektionsrangierungen/delete_ranking.php',
            type: 'POST',
            data: {
                ranking_id: id,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Rangierung erfolgreich gelöscht', 'success');
                    loadAvailableDefinitions(selectedYear);
                    loadExistingRankings(selectedYear);
                } else {
                    showToast('Fehler beim Löschen: ' + (response.message || 'Unbekannter Fehler'), 'error');
                }
            },
            error: function() {
                showToast('Fehler beim Löschen der Rangierung', 'error');
            }
        });
    });

    // PDF Export Button Event Handler
    $('#exportPdfBtn').on('click', function() {
        if (!selectedYear) {
            showToast('Bitte Jahr auswählen', 'warning');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Erstelle PDF...');

        $.ajax({
            url: 'sektionsrangierungen/export_rankings_pdf.php',
            type: 'POST',
            data: {
                year: selectedYear,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.pdf_url) {
                    showToast('PDF erfolgreich erstellt', 'success');
                    
                    // PDF in neuem Tab öffnen
                    const link = document.createElement('a');
                    link.href = response.pdf_url;
                    link.target = '_blank';
                    link.download = response.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } else {
                    showToast('Fehler beim PDF-Export: ' + (response.message || 'Unbekannter Fehler'), 'error');
                }
            },
            error: function() {
                showToast('Fehler beim PDF-Export', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Jahr-Änderung
    $('#yearSelect').on('change', function() {
        selectedYear = $(this).val();
        loadAvailableDefinitions(selectedYear);
        loadExistingRankings(selectedYear);
        $('#addRankingCard').slideUp();
        $('#addNewBtn').prop('disabled', false);
    });

    // Initialisierung
    initializeYearDropdown();
    loadAvailableDefinitions(currentYear);
    loadExistingRankings(currentYear);
    
    // Global Scroll aktivieren (wie bei heimresultate.php)
    if (window.MSV && window.MSV.enableGlobalScroll) {
        window.MSV.enableGlobalScroll();
    } else {
        // Fallback: Eigene Scroll-Implementation wenn MSV nicht verfügbar
        document.addEventListener('wheel', function(e) {
            const tableContainer = document.querySelector('.table-responsive');
            
            if (tableContainer) {
                const deltaY = e.deltaY;
                
                // Prüfe ob die Tabelle scrollbar ist
                if (tableContainer.scrollHeight > tableContainer.clientHeight) {
                    tableContainer.scrollTop += deltaY;
                    e.preventDefault();
                }
            }
        }, { passive: false });
    }
});
</script>

<!-- Gemeinsame Bibliothek einbinden -->
<script src="js/msv-resultate-common.js"></script>

<?php
include 'footer.inc.php';
?>