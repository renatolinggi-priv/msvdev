<?php
// einzelrangierung.php
include 'dbconnect.inc.php';

// Seitenspezifische Styles
$page_specific_css = "
/* === RANGIERUNGEN – modernes, einheitliches Design === */
.ranking-shell {
    background: #ffffff;
    border: 1px solid #e9eef5;
    border-radius: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    overflow: hidden;
}

.ranking-table { font-size: .9rem; margin-bottom: 0; }

.ranking-table thead th {
    background: #f8fafc;
    font-weight: 600;
    text-transform: uppercase;
    font-size: .72rem;
    letter-spacing: .4px;
    color: #64748b;
    padding: .85rem 1rem;
    border-bottom: 1px solid #e9eef5;
    white-space: nowrap;
}

.ranking-table tbody td {
    padding: .7rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    white-space: nowrap;
}
.ranking-table tbody tr:last-child td { border-bottom: none; }

.ranking-table td:first-child,
.ranking-table th:first-child { text-align: left !important; }

.ranking-table .ranking-row { cursor: pointer; transition: background .15s ease; }
.ranking-table .ranking-row:hover { background: #f6faff; }

/* Globale .table-Regeln aus msv-styles/resultate-unified neutralisieren:
   keine vertikalen Gitterlinien, kein erzwungener Leerraum, keine 1.-Spalte-Tönung */
.ranking-table th, .ranking-table td { border-right: 0 !important; }
.ranking-table th:first-child, .ranking-table td:first-child {
    width: auto !important; background: transparent !important; font-weight: inherit;
}
.ranking-shell .table-responsive {
    min-height: 0 !important; max-height: none !important; overflow-y: visible !important;
}

/* Spaltenbreiten */
.ranking-table .anlass-col   { width: 32%; }
.ranking-table .mitglied-col { width: 24%; }
.ranking-table .rang-col     { width: 80px; }
.ranking-table .resultat-col { width: 14%; }
.ranking-table .preis-col    { width: 16%; }
.ranking-table .aktionen-col { width: 70px; }

/* Rang-Badge mit Medaillen-Farben */
.rang-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 34px; height: 30px; padding: 0 .55rem;
    border-radius: 999px; font-weight: 700; font-size: .82rem;
    background: #eef2f7; color: #475569;
}
.rang-badge.r1 { background: linear-gradient(135deg,#fde68a,#f59e0b); color: #7c2d12; box-shadow: 0 1px 2px rgba(245,158,11,.35); }
.rang-badge.r2 { background: linear-gradient(135deg,#e8edf3,#aab6c5); color: #1e293b; }
.rang-badge.r3 { background: linear-gradient(135deg,#fed7aa,#fb923c); color: #7c2d12; }

/* Preis-Zelle */
.preis-cell { font-weight: 700; color: #0f766e; white-space: nowrap; }

/* Aktionen: Löschen erst bei Hover deutlich sichtbar */
.ranking-table .row-actions .btn {
    opacity: .5; transition: opacity .15s ease;
    padding: .25rem .45rem; font-size: .8rem;
}
.ranking-table .ranking-row:hover .row-actions .btn { opacity: 1; }

/* Leer-/Lade-Zustand */
.ranking-empty { padding: 3rem 1rem; text-align: center; color: #94a3b8; }
.ranking-empty i { font-size: 2rem; display: block; margin-bottom: .5rem; opacity: .6; }

/* Karte für neue Rangierung */
.add-ranking-card {
    background: #f8fafc;
    border: 1px solid #e9eef5;
    border-radius: .85rem;
    padding: 1.25rem;
    margin-bottom: 1.25rem;
}
.add-ranking-card h6 { color: #334155; }

/* Mobile */
@media (max-width: 767.98px) {
    .ranking-table { font-size: .82rem; min-width: 720px; }
    .ranking-table thead th, .ranking-table tbody td { padding: .55rem .6rem; white-space: nowrap; }
}

/* === EDIT SLIDE PANEL === */
.edit-panel { position: fixed; top: 0; right: -460px; width: 440px; height: 100vh; background: #fff; box-shadow: -8px 0 30px rgba(0,0,0,0.12); z-index: 1060; transition: right 0.3s cubic-bezier(0.4,0,0.2,1); display: flex; flex-direction: column; }
.edit-panel.open { right: 0; }
.edit-panel-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 1055; opacity: 0; visibility: hidden; transition: all 0.3s; }
.edit-panel-overlay.show { opacity: 1; visibility: visible; }
.edit-panel-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; flex-shrink: 0; }
.edit-panel-header h6 { margin: 0; font-weight: 600; color: #1e293b; }
.edit-panel-body { padding: 1.25rem; overflow-y: auto; flex: 1; }
.edit-panel-label { display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.35rem; }
@media (max-width: 767.98px) { .edit-panel { width: 100%; right: -100%; } }
";

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xxl-8 col-xl-10 col-lg-12 col-md-12 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4 d-none d-md-flex">
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
                        
                        <!-- Jahr-Auswahl + Aktionen nebeneinander -->
                        <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
                            <div class="d-flex align-items-center gap-2">
                                <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                                    <i class="bi bi-calendar3 me-1"></i>Jahr:
                                </label>
                                <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;">
                                    <!-- Optionen werden per JavaScript eingefügt -->
                                </select>
                            </div>

                            <!-- Aktionsbereich (Bootstrap Collapse) -->
                            <div class="card action-card mb-0">
                                <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
                                     data-bs-toggle="collapse" data-bs-target="#einzelrangActions"
                                     aria-expanded="false" aria-controls="einzelrangActions">
                                    <span class="fw-semibold"><i class="bi bi-tools me-2"></i>Aktionen</span>
                                    <i class="bi bi-chevron-down action-chevron"></i>
                                </div>
                                <div class="collapse" id="einzelrangActions">
                                    <div class="card-body pt-2 pb-3 px-3">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <button type="button" id="addNewBtn" class="btn btn-outline-success btn-sm w-100" disabled>
                                                    <i class="bi bi-plus-circle me-1"></i>Hinzufügen
                                                </button>
                                            </div>
                                            <div class="col-6">
                                                <button type="button" id="exportPdfBtn" class="btn btn-outline-danger btn-sm w-100" style="display: none;">
                                                    <i class="bi bi-file-pdf me-1"></i>PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- Ende flex-row Jahr+Aktionen -->

                        <!-- Neue Rangierung hinzufügen -->
                        <div class="add-ranking-card" id="addRankingCard" style="display: none;">
                            <h6 class="mb-3">
                                <i class="bi bi-plus-circle me-2"></i>
                                Neue Einzelrangierung hinzufügen
                            </h6>

                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label for="anlassSelect" class="form-label">Anlass:</label>
                                    <select id="anlassSelect" class="form-select">
                                        <option value="">-- Anlass auswählen --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="mitgliedSelect" class="form-label">Mitglied:</label>
                                    <select id="mitgliedSelect" class="form-select">
                                        <option value="">-- Mitglied auswählen --</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="rangInput" class="form-label">Rang:</label>
                                    <input type="number" id="rangInput" class="form-control" min="1" max="999" placeholder="z.B. 1">
                                </div>
                                <div class="col-md-2">
                                    <label for="resultatInput" class="form-label">Resultat:</label>
                                    <input type="text" id="resultatInput" class="form-control" placeholder="z.B. 95.5">
                                </div>
                                <div class="col-md-2">
                                    <label for="preisInput" class="form-label">Preis (CHF):</label>
                                    <input type="number" id="preisInput" class="form-control" min="0" step="0.05" placeholder="z.B. 100">
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button type="button" id="saveRankingBtn" class="btn btn-primary btn-sm">
                                        <i class="bi bi-save me-1"></i>Speichern
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Vorhandene Rangierungen -->
                        <div id="rankingsContainer" class="ranking-shell">
                            <div class="desktop-table-container">
                                <div class="table-responsive">
                                    <table class="table ranking-table align-middle" id="einzelrangTable">
                                        <thead>
                                            <tr>
                                                <th class="anlass-col">Anlass</th>
                                                <th class="mitglied-col">Mitglied</th>
                                                <th class="rang-col text-center">Rang</th>
                                                <th class="resultat-col text-center">Resultat</th>
                                                <th class="preis-col text-end">Preis (CHF)</th>
                                                <th class="aktionen-col text-center"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="rankingsList">
                                            <!-- Wird per JavaScript geladen -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Mobile Cards Container -->
                            <div class="mobile-cards-container" id="mobileCardsEinzelrang">
                                <div class="mobile-search-container">
                                    <input type="text" class="form-control mobile-search-input" placeholder="Suchen...">
                                </div>
                                <div class="mobile-scroll-container">
                                    <!-- Cards werden hier eingefügt -->
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Slide-Panel -->
<div class="edit-panel-overlay" id="editPanelOverlay"></div>
<div class="edit-panel" id="editPanel">
    <div class="edit-panel-header">
        <h6><i class="bi bi-pencil-square me-2"></i>Einzelrangierung bearbeiten</h6>
        <button class="btn btn-sm btn-outline-secondary" id="editPanelClose"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="edit-panel-body">
        <input type="hidden" id="editRankingId" value="">

        <div class="mb-3">
            <label class="edit-panel-label"><i class="bi bi-calendar-event me-1"></i>Anlass</label>
            <input type="text" class="form-control" id="editAnlassName" readonly>
        </div>

        <div class="mb-3">
            <label class="edit-panel-label"><i class="bi bi-person me-1"></i>Mitglied</label>
            <input type="text" class="form-control" id="editMitgliedName" readonly>
        </div>

        <div class="mb-3">
            <label class="edit-panel-label"><i class="bi bi-trophy me-1"></i>Rang</label>
            <input type="number" class="form-control" id="editRangInput" min="1" max="999" placeholder="z.B. 1" required>
        </div>

        <div class="mb-3">
            <label class="edit-panel-label"><i class="bi bi-bullseye me-1"></i>Resultat</label>
            <input type="text" class="form-control" id="editResultatInput" placeholder="z.B. 95.5 Pkt">
        </div>

        <div class="mb-3">
            <label class="edit-panel-label"><i class="bi bi-cash me-1"></i>Preis (CHF)</label>
            <input type="number" class="form-control" id="editPreisInput" min="0" step="0.05" placeholder="z.B. 100.00" required>
        </div>

        <hr>
        <button type="button" class="btn btn-primary w-100" id="saveEditRankingBtn">
            <i class="bi bi-check-circle me-1"></i>Speichern
        </button>
    </div>
</div>

<script>
$(document).ready(function () {
    var currentYear = new Date().getFullYear();
    var selectedYear = currentYear;

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
            <tr><td colspan="6" class="ranking-empty">
                <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                Lade Rangierungen...
            </td></tr>
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
                        <tr><td colspan="6" class="ranking-empty">
                            <i class="bi bi-trophy"></i>
                            Noch keine Einzelrangierungen für das Jahr ${year} erfasst.
                        </td></tr>
                    `);
                    buildMobileCardsEinzelrang();
                    // PDF Export Button verstecken
                    $('#exportPdfBtn').hide();
                }
            },
            error: function () {
                $('#rankingsList').html(`
                    <tr><td colspan="6" class="ranking-empty text-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        Fehler beim Laden der Rangierungen.
                    </td></tr>
                `);
                msvToast('Fehler beim Laden der Rangierungen', 'error');
            }
        });
    }

    // Rangierungen anzeigen
    function displayRankings(rankings) {
        let html = '';
        
        rankings.forEach(function(ranking) {
            const r = parseInt(ranking.rang, 10);
            const rcls = r === 1 ? 'r1' : r === 2 ? 'r2' : r === 3 ? 'r3' : '';
            html += `
                <tr class="ranking-row" data-id="${ranking.id}" data-rang="${ranking.rang}" data-preis="${ranking.preis}"
                    data-resultat="${ranking.resultat || ''}" data-anlass="${ranking.anlass_bezeichnung}"
                    data-mitglied="${ranking.mitglied_name}">
                    <td class="fw-semibold">${ranking.anlass_bezeichnung}</td>
                    <td>${ranking.mitglied_name}</td>
                    <td class="text-center">
                        <span class="rang-badge ${rcls}">${ranking.rang}</span>
                    </td>
                    <td class="text-center">${ranking.resultat || '<span class="text-muted">–</span>'}</td>
                    <td class="text-end"><span class="preis-cell">CHF ${parseFloat(ranking.preis).toFixed(2)}</span></td>
                    <td class="text-center row-actions">
                        <button type="button" class="btn btn-outline-danger delete-ranking" data-tooltip="Löschen"
                                data-id="${ranking.id}" data-anlass="${ranking.anlass_bezeichnung}"
                                data-mitglied="${ranking.mitglied_name}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        
        $('#rankingsList').html(html);
        buildMobileCardsEinzelrang();
    }

    // "Hinzufügen" öffnet das Eingabeformular
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

    // Edit-Panel öffnen/schliessen
    function openEditPanel() { $('#editPanel').addClass('open'); $('#editPanelOverlay').addClass('show'); }
    function closeEditPanel() { $('#editPanel').removeClass('open'); $('#editPanelOverlay').removeClass('show'); }
    $('#editPanelClose, #editPanelOverlay').on('click', closeEditPanel);
    $(document).on('keydown', function(e) { if (e.key === 'Escape') closeEditPanel(); });

    // Zeilen-Klick öffnet Edit-Panel (nicht bei Klick auf Buttons)
    $(document).on('click', '.ranking-row', function(e) {
        if ($(e.target).closest('button').length) return;

        const $row = $(this);
        $('#editRankingId').val($row.data('id'));
        $('#editAnlassName').val($row.data('anlass'));
        $('#editMitgliedName').val($row.data('mitglied'));
        $('#editRangInput').val($row.data('rang'));
        $('#editResultatInput').val($row.data('resultat'));
        $('#editPreisInput').val($row.data('preis'));

        openEditPanel();
    });

    // Panel Save Button Event Handler
    $('#saveEditRankingBtn').on('click', function() {
        const id = $('#editRankingId').val();
        const newRang = $('#editRangInput').val();
        const newResultat = $('#editResultatInput').val();
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
                    closeEditPanel();
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

    // Mobile Cards Builder für Einzelrangierungen
    function buildMobileCardsEinzelrang() {
        MSVMobileCards.initResponsive({
            tableId: 'einzelrangTable',
            mobileContainerId: 'mobileCardsEinzelrang',
            titleColumns: [0, 1],
            summaryColumns: [4],
            rankColumn: 2
        });
    }

</script>

<?
include 'footer.inc.php';
?>
