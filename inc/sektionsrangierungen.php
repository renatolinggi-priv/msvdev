<?php
// sektionsrangierungen.php
include 'dbconnect.inc.php';
include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<style>
/* Sektionsrangierungen - Desktop Styles */

/* Flex-Layout für Form */
#rankingForm {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-height: 0;
}

/* Sticky Header */
#sektionsrangTable thead th {
  position: sticky;
  top: 0;
  z-index: 10;
  background-color: #f8f9fa;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-7 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4 d-none d-md-flex">
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

                        <!-- Jahr-Auswahl + Aktionen nebeneinander -->
                        <div class="d-flex flex-wrap gap-3 align-items-start mb-4">

                        <!-- Jahr-Auswahl (ohne Card) -->
                        <div class="d-flex align-items-center gap-2">
                            <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                                <i class="bi bi-calendar3 me-1"></i>Jahr:
                            </label>
                            <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;">
                                <!-- Optionen via JS -->
                            </select>
                        </div>

                        <!-- Aktionsbereich (Bootstrap Collapse) -->
                        <div class="card action-card mb-0">
                            <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
                                 data-bs-toggle="collapse" data-bs-target="#sektionsrangActions"
                                 aria-expanded="false" aria-controls="sektionsrangActions">
                                <span class="fw-semibold"><i class="bi bi-tools me-2"></i>Aktionen</span>
                                <i class="bi bi-chevron-down action-chevron"></i>
                            </div>
                            <div class="collapse" id="sektionsrangActions">
                                <div class="card-body pt-2 pb-3 px-3">
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <button type="button" id="addNewBtn" class="btn btn-primary w-100" disabled>
                                                <i class="bi bi-plus-circle me-2"></i>Neue Rangierung
                                            </button>
                                        </div>
                                        <div class="col-12">
                                            <button type="button" id="exportPdfBtn" class="btn btn-outline-danger w-100" style="display: none;">
                                                <i class="bi bi-file-pdf me-1"></i>PDF Export
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        </div><!-- Ende flex-row Jahr+Aktionen -->

                        <!-- Neue Rangierung hinzufügen (versteckt) -->
                        <div class="add-ranking-card mb-3" id="addRankingCard" style="display: none;">
                            <h6 class="mb-3">
                                <i class="bi bi-plus-circle me-2"></i>
                                Neue Rangierung hinzufügen
                            </h6>

                            <div class="row">
                                <div class="col-md-4">
                                    <label for="anlassSelect" class="form-label">Anlass:</label>
                                    <select id="anlassSelect" class="form-select">
                                        <option value="">-- Anlass auswählen --</option>
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

                        <!-- Tabelle (einheitlich wie heimresultate.php) -->
                        <div class="table-wrapper">
                            <div class="desktop-table-container">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="sektionsrangTable">
                                    <thead>
                                        <tr>
                                            <th scope="col" style="min-width: 200px;">
                                                <i class="bi bi-calendar-event me-1"></i>Anlass
                                            </th>
                                            <th scope="col" class="text-center">Rang</th>
                                            <th scope="col" class="text-center">Preis (CHF)</th>
                                            <th scope="col" class="text-center text-nowrap">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody id="rankingsList">
                                        <!-- Wird per JavaScript gefüllt -->
                                    </tbody>
                                </table>
                            </div>
                            </div>
                            <!-- Mobile Cards Container -->
                            <div class="mobile-cards-container" id="mobileCardsSektionsrang">
                                <div class="mobile-search-container">
                                    <input type="text" class="form-control mobile-search-input" placeholder="Suchen...">
                                </div>
                                <div class="mobile-scroll-container">
                                    <!-- Cards werden hier eingefügt -->
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
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="editPreisInput" class="form-label fw-bold">
                                    <i class="bi bi-cash me-1"></i>Preis (CHF):
                                </label>
                                <input type="number" class="form-control" id="editPreisInput"
                                       min="0" step="0.05" placeholder="z.B. 100.00" required>
                            </div>
                        </div>
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

<!-- Gemeinsame Bibliothek einbinden -->
<script src="js/msv-resultate-common.js"></script>

<script>
// Initialisierung mit der gemeinsamen Bibliothek
$(document).ready(function() {
    // Initialisiere den Manager für Heim (verwende bekannten Typ)
    const heimManager = MSV.init('heim');
    
    // Global Scroll aktivieren
    MSV.enableGlobalScroll();
});
</script>

<script>
$(document).ready(function () {
    var currentYear = new Date().getFullYear();
    var selectedYear = currentYear;

    // Jahr-Dropdown initialisieren
    function initializeYearDropdown() {
        const yearSelect = $('#yearSelect').empty();
        for (let year = currentYear; year >= currentYear - 3; year--) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) option.prop('selected', true);
            yearSelect.append(option);
        }
    }

    // Verfügbare Anlässe laden
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

                $('#addNewBtn').prop('disabled', response.definitions.length === 0);
            },
            error: function () {
                $('#anlassSelect').html('<option value="" disabled>Fehler beim Laden</option>');
                msvToast('Fehler beim Laden der verfügbaren Anlässe', 'error');
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
                    $('#exportPdfBtn').show();
                } else {
                    $('#rankingsList').html(`
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-info-circle me-2"></i>
                            Noch keine Rangierungen für das Jahr ${year} erfasst.
                        </div>
                    `);
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

    // Rangierungen anzeigen - EXAKT wie heimresultate.php
    function displayRankings(rankings) {
        let totalPrize = 0;
        let html = '';

        rankings.forEach(function(ranking) {
            const preisNum = Number(ranking.preis) || 0;
            totalPrize += preisNum;
            html += `
                <tr>
                    <td class="text-start">${ranking.bezeichnung}</td>
                    <td class="text-center">
                        <span class="badge bg-primary">${ranking.rang}</span>
                    </td>
                    <td class="text-center">CHF ${preisNum.toFixed(2)}</td>
                    <td class="text-center text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-ranking"
                                data-id="${ranking.id}" 
                                data-rang="${ranking.rang}" 
                                data-preis="${preisNum}"
                                data-anlass="${ranking.bezeichnung}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-ranking"
                                data-id="${ranking.id}" 
                                data-anlass="${ranking.bezeichnung}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        // Total-Zeile
        html += `
                <tr class="table-secondary fw-bold">
                    <td>TOTAL</td>
                    <td class="text-center">-</td>
                    <td class="text-center">CHF ${totalPrize.toFixed(2)}</td>
                    <td class="text-center">-</td>
                </tr>
        `;

        $('#rankingsList').html(html);
        buildMobileCardsSektionsrang();
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
            msvToast('Bitte alle Felder ausfüllen', 'warning');
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
                    msvToast('Rangierung erfolgreich gespeichert', 'success');

                    $('#anlassSelect').val('');
                    $('#rangInput').val('');
                    $('#preisInput').val('');
                    $('#addRankingCard').slideUp();
                    $('#addNewBtn').prop('disabled', false);

                    loadAvailableDefinitions(selectedYear);
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

    // Edit-Button Event Handler
    $(document).on('click', '.edit-ranking', function() {
        const id = $(this).data('id');
        const currentRang = $(this).data('rang');
        const currentPreis = $(this).data('preis');
        const anlassName = $(this).data('anlass');

        $('#editRankingId').val(id);
        $('#editAnlassName').val(anlassName);
        $('#editRangInput').val(currentRang);
        $('#editPreisInput').val(currentPreis);

        $('#editRankingModal').modal('show');
    });

    // Modal Save Button
    $('#saveEditRankingBtn').on('click', function() {
        const id = $('#editRankingId').val();
        const newRang = $('#editRangInput').val();
        const newPreis = $('#editPreisInput').val();

        if (!newRang || !newPreis) {
            msvToast('Bitte alle Felder ausfüllen', 'warning');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

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
                    msvToast('Rangierung erfolgreich aktualisiert', 'success');
                    $('#editRankingModal').modal('hide');
                    loadExistingRankings(selectedYear);
                } else {
                    msvToast('Fehler beim Aktualisieren: ' + (response.message || 'Unbekannter Fehler'), 'error');
                }
            },
            error: function() {
                msvToast('Fehler beim Aktualisieren der Rangierung', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Delete-Button Event Handler
    $(document).on('click', '.delete-ranking', async function() {
        const id = $(this).data('id');
        const anlassName = $(this).data('anlass');

        const result = await msvConfirmDelete(`Rangierung für "${anlassName}"`);
        if (!result.isConfirmed) return;

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
                    msvToast('Rangierung erfolgreich gelöscht', 'success');
                    loadAvailableDefinitions(selectedYear);
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

    // PDF Export Button
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
            url: 'sektionsrangierungen/export_rankings_pdf.php',
            type: 'POST',
            data: {
                year: selectedYear,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.pdf_url) {
                    msvToast('PDF erfolgreich erstellt', 'success');

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
    loadExistingRankings(currentYear);
});

    // Mobile Cards Builder für Sektionsrangierungen
    function buildMobileCardsSektionsrang() {
        MSVMobileCards.initResponsive({
            tableId: 'sektionsrangTable',
            mobileContainerId: 'mobileCardsSektionsrang',
            titleColumns: [0],
            summaryColumns: [2],
            rankColumn: 1
        });
    }

</script>

<?
include 'footer.inc.php';
?>
