<?php
// einzelrangierung.php
include 'dbconnect.inc.php';

// Seitenspezifische Styles
$page_specific_css = "
/* === EINZELRANGIERUNGEN STYLES === */
.ranking-card {
    background: #ffffff;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid #f1f5f9;
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
}

/* Spaltenbreiten für Einzelrangierungen */
.ranking-table .anlass-col { width: 25%; text-align: left; }
.ranking-table .mitglied-col { width: 20%; text-align: left; }
.ranking-table .rang-col { width: 8%; }
.ranking-table .resultat-col { width: 15%; }
.ranking-table .preis-col { width: 12%; }
.ranking-table .aktionen-col { width: 20%; }

/* Explizite Linksbündigkeit für erste Spalte */
.ranking-table td:first-child,
.ranking-table th:first-child {
    text-align: left !important;
}

.add-ranking-card {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 0.75rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
    transition: all 0.2s ease;
}

.add-ranking-card:hover {
    border-color: #007bff;
    background: #f0f8ff;
}

/* Mobile Anpassungen - Verbessert */
.table-responsive {
    overflow-x: auto;
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
        min-width: 800px; /* Verhindert vorzeitiges Umbrechen - breiter wegen mehr Spalten */
    }
    
    .ranking-table th,
    .ranking-table td {
        padding: 0.5rem 0.25rem;
        white-space: nowrap;
    }
}

/* Mittlere Bildschirme - mehr Platz nutzen */
@media (min-width: 769px) and (max-width: 1200px) {
    .ranking-table .anlass-col { width: 22%; }
    .ranking-table .mitglied-col { width: 18%; }
    .ranking-table .rang-col { width: 8%; }
    .ranking-table .resultat-col { width: 12%; }
    .ranking-table .preis-col { width: 10%; }
    .ranking-table .aktionen-col { width: 30%; }
}

/* Große Bildschirme - optimale Verteilung */
@media (min-width: 1201px) {
    .ranking-table .anlass-col { width: 25%; }
    .ranking-table .mitglied-col { width: 20%; }
    .ranking-table .rang-col { width: 8%; }
    .ranking-table .resultat-col { width: 15%; }
    .ranking-table .preis-col { width: 12%; }
    .ranking-table .aktionen-col { width: 20%; }
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

<div class="container-fluid">
    <div class="row">
        <div class="col-xxl-6 col-xl-8 col-lg-10 col-md-12 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-person-badge me-2"></i>
                            Einzelrangierungen
                        </h2>
                        <!--<p class="text-muted mb-0">Verwaltung der Einzelrangierungen von Mitgliedern</p>-->
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
                                Neue Einzelrangierung hinzufügen
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-2">
                                    <label for="anlassSelect" class="form-label">Anlass:</label>
                                    <select id="anlassSelect" class="form-select">
                                        <option value="">-- Anlass auswählen --</option>
                                        <!-- Optionen werden per JavaScript eingefügt -->
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="mitgliedSelect" class="form-label">Mitglied:</label>
                                    <select id="mitgliedSelect" class="form-select">
                                        <option value="">-- Mitglied auswählen --</option>
                                        <!-- Optionen werden per JavaScript eingefügt -->
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <label for="rangInput" class="form-label">Rang:</label>
                                    <input type="number" id="rangInput" class="form-control" min="1" max="999" placeholder="z.B. 1">
                                </div>
                                <div class="col-md-2">
                                    <label for="resultatInput" class="form-label">Resultat:</label>
                                    <input type="text" id="resultatInput" class="form-control" placeholder="z.B. 95.5 Pkt">
                                </div>
                                <div class="col-md-2">
                                    <label for="preisInput" class="form-label">Preis (CHF):</label>
                                    <input type="number" id="preisInput" class="form-control" min="0" step="0.05" placeholder="z.B. 100.00">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" id="saveRankingBtn" class="btn btn-compact-standard btn-primary w-100">
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
                        <div id="rankingsContainer">
                            <div class="ranking-card">
                                <h5 class="mb-3">
                                    <i class="bi bi-list-ul me-2"></i>
                                    Vorhandene Einzelrangierungen
                                </h5>
                                <div id="rankingsList">
                                    <!-- Wird per JavaScript geladen -->
                                    <div class="text-center py-4">
                                        <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                                        Lade Rangierungen...
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

<!-- Edit Rangierung Modal -->
<div class="modal fade" id="editRankingModal" tabindex="-1" aria-labelledby="editRankingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRankingModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>
                    Einzelrangierung bearbeiten
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
                    
                    <div class="mb-3">
                        <label for="editMitgliedName" class="form-label fw-bold">Mitglied:</label>
                        <input type="text" class="form-control" id="editMitgliedName" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
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
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="editResultatInput" class="form-label fw-bold">
                                    <i class="bi bi-bullseye me-1"></i>Resultat:
                                </label>
                                <input type="text" class="form-control" id="editResultatInput"
                                       placeholder="z.B. 95.5 Pkt">
                                <div class="invalid-feedback">
                                    Bitte geben Sie ein gültiges Resultat ein.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
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

    // Verfügbare Anlässe laden (alle JM-Anlässe)
    function loadAvailableDefinitions(year) {
        $('#anlassSelect').html('<option value="">Lade Anlässe...</option>');

        $.ajax({
            url: 'einzelrangierung/load_available_definitions.php',
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
                    $('#addNewBtn').prop('disabled', false);
                } else {
                    $('#anlassSelect').append('<option value="" disabled>Keine Anlässe verfügbar</option>');
                    $('#addNewBtn').prop('disabled', true);
                }
            },
            error: function () {
                $('#anlassSelect').html('<option value="" disabled>Fehler beim Laden</option>');
                msvToast('Fehler beim Laden der verfügbaren Anlässe', 'error');
            }
        });
    }

    // Verfügbare Mitglieder laden
    function loadAvailableMembers() {
        $('#mitgliedSelect').html('<option value="">Lade Mitglieder...</option>');

        $.ajax({
            url: 'einzelrangierung/load_available_members.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                $('#mitgliedSelect').html('<option value="">-- Mitglied auswählen --</option>');
                
                if (response.success && response.members.length > 0) {
                    response.members.forEach(function(member) {
                        const option = $('<option></option>')
                            .val(member.ID)
                            .text(member.Name + ' ' + member.Vorname);
                        $('#mitgliedSelect').append(option);
                    });
                } else {
                    $('#mitgliedSelect').append('<option value="" disabled>Keine Mitglieder verfügbar</option>');
                }
            },
            error: function () {
                $('#mitgliedSelect').html('<option value="" disabled>Fehler beim Laden</option>');
                msvToast('Fehler beim Laden der Mitglieder', 'error');
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
            url: 'einzelrangierung/load_existing_rankings.php',
            type: 'GET',
            data: { year: year },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.rankings.length > 0) {
                    displayRankings(response.rankings);
                    // PDF Export Button anzeigen
                    $('#exportPdfBtn').show();
                } else {
                    $('#rankingsList').html(`
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-info-circle me-2"></i>
                            Noch keine Einzelrangierungen für das Jahr ${year} erfasst.
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
                msvToast('Fehler beim Laden der Rangierungen', 'error');
            }
        });
    }

    // Rangierungen anzeigen
    function displayRankings(rankings) {
        let html = `
            <div class="table-responsive">
                <table class="table table-hover ranking-table">
                    <thead>
                        <tr>
                            <th class="anlass-col">Anlass</th>
                            <th class="mitglied-col">Mitglied</th>
                            <th class="rang-col text-center">Rang</th>
                            <th class="resultat-col text-center">Resultat</th>
                            <th class="preis-col text-end">Preis (CHF)</th>
                            <th class="aktionen-col text-center">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        rankings.forEach(function(ranking) {
            html += `
                <tr>
                    <td class="fw-medium">${ranking.anlass_bezeichnung}</td>
                    <td>${ranking.mitglied_name}</td>
                    <td class="text-center">
                        <span class="badge bg-primary">${ranking.rang}</span>
                    </td>
                    <td class="text-center">${ranking.resultat || '-'}</td>
                    <td class="text-end fw-bold">CHF ${parseFloat(ranking.preis).toFixed(2)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-primary me-1 edit-ranking"
                                data-id="${ranking.id}" data-rang="${ranking.rang}" data-preis="${ranking.preis}"
                                data-resultat="${ranking.resultat || ''}" data-anlass="${ranking.anlass_bezeichnung}"
                                data-mitglied="${ranking.mitglied_name}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger delete-ranking"
                                data-id="${ranking.id}" data-anlass="${ranking.anlass_bezeichnung}"
                                data-mitglied="${ranking.mitglied_name}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div>';
        $('#rankingsList').html(html);
    }

    // Event Handlers
    $('#addNewBtn').on('click', function() {
        $('#addRankingCard').slideDown();
        $(this).prop('disabled', true);
    });

    $('#saveRankingBtn').on('click', function() {
        const anlassId = $('#anlassSelect').val();
        const mitgliedId = $('#mitgliedSelect').val();
        const rang = $('#rangInput').val();
        const resultat = $('#resultatInput').val();
        const preis = $('#preisInput').val();

        if (!anlassId || !mitgliedId || !rang || !preis) {
            msvToast('Bitte alle Felder ausfüllen (Resultat ist optional)', 'warning');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        $.ajax({
            url: 'einzelrangierung/save_ranking.php',
            type: 'POST',
            data: {
                year: selectedYear,
                anlass_id: anlassId,
                mitglied_id: mitgliedId,
                rang: rang,
                resultat: resultat,
                preis: preis,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    msvToast('Einzelrangierung erfolgreich gespeichert', 'success');
                    
                    // Formular zurücksetzen
                    $('#anlassSelect').val('');
                    $('#mitgliedSelect').val('');
                    $('#rangInput').val('');
                    $('#resultatInput').val('');
                    $('#preisInput').val('');
                    $('#addRankingCard').slideUp();
                    $('#addNewBtn').prop('disabled', false);
                    
                    // Listen neu laden
                    loadExistingRankings(selectedYear);
                } else {
                    msvToast('Fehler beim Speichern: ' + (response.message || 'Unbekannter Fehler'), 'error');
                }
            },
            error: function() {
                msvToast('Fehler beim Speichern der Rangierung', 'error');
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
        const currentResultat = $(this).data('resultat');
        const currentPreis = $(this).data('preis');
        const anlassName = $(this).data('anlass');
        const mitgliedName = $(this).data('mitglied');
        
        // Modal mit aktuellen Werten füllen
        $('#editRankingId').val(id);
        $('#editAnlassName').val(anlassName);
        $('#editMitgliedName').val(mitgliedName);
        $('#editRangInput').val(currentRang);
        $('#editResultatInput').val(currentResultat);
        $('#editPreisInput').val(currentPreis);
        
        // Validation-Klassen zurücksetzen
        $('#editRankingForm')[0].classList.remove('was-validated');
        $('#editRangInput, #editResultatInput, #editPreisInput').removeClass('is-invalid');
        $('#editModalAlert').addClass('d-none');
        
        // Modal öffnen
        $('#editRankingModal').modal('show');
    });

    // Modal Save Button Event Handler
    $('#saveEditRankingBtn').on('click', function() {
        const form = $('#editRankingForm')[0];
        const id = $('#editRankingId').val();
        const newRang = $('#editRangInput').val();
        const newResultat = $('#editResultatInput').val();
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
            url: 'einzelrangierung/update_ranking.php',
            type: 'POST',
            data: {
                ranking_id: id,
                rang: parseInt(newRang),
                resultat: newResultat,
                preis: parseFloat(newPreis),
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    msvToast('Einzelrangierung erfolgreich aktualisiert', 'success');
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
        $('#editRangInput, #editResultatInput, #editPreisInput').removeClass('is-invalid');
        $('#editModalAlert').addClass('d-none');
    });

    // Delete-Button Event Handler
    $(document).on('click', '.delete-ranking', async function() {
        const id = $(this).data('id');
        const anlassName = $(this).data('anlass');
        const mitgliedName = $(this).data('mitglied');

        const result = await msvConfirmDelete(`Rangierung für "${mitgliedName}" bei "${anlassName}"`);
        if (!result.isConfirmed) return;

        $.ajax({
            url: 'einzelrangierung/delete_ranking.php',
            type: 'POST',
            data: {
                ranking_id: id,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    msvToast('Einzelrangierung erfolgreich gelöscht', 'success');
                    loadExistingRankings(selectedYear);
                } else {
                    msvToast('Fehler beim Löschen: ' + (response.message || 'Unbekannter Fehler'), 'error');
                }
            },
            error: function() {
                msvToast('Fehler beim Löschen der Rangierung', 'error');
            }
        });
    });

    // PDF Export Button Event Handler
    $('#exportPdfBtn').on('click', function() {
        if (!selectedYear) {
            msvToast('Bitte Jahr auswählen', 'warning');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Erstelle PDF...');

        $.ajax({
            url: 'einzelrangierung/export_rankings_pdf.php',
            type: 'POST',
            data: {
                year: selectedYear,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.pdf_url) {
                    msvToast('PDF erfolgreich erstellt', 'success');
                    
                    // PDF in neuem Tab öffnen
                    const link = document.createElement('a');
                    link.href = response.pdf_url;
                    link.target = '_blank';
                    link.download = response.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
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
        selectedYear = $(this).val();
        loadAvailableDefinitions(selectedYear);
        loadExistingRankings(selectedYear);
        $('#addRankingCard').slideUp();
        $('#addNewBtn').prop('disabled', false);
    });

    // Initialisierung
    initializeYearDropdown();
    loadAvailableDefinitions(currentYear);
    loadAvailableMembers();
    loadExistingRankings(currentYear);
});
</script>

<?php
include 'footer.inc.php';
?>