<?php
// sektionsrangierungen.php
include 'dbconnect.inc.php';
include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';

?>
<style>
/* === RANGIERUNGEN – modernes, einheitliches Design === */
.ranking-shell {
    background: transparent;
    border: 0;
    border-radius: 0;
    overflow: hidden;
}

.ranking-table { font-size: .85rem; margin-bottom: 0; }

.ranking-table thead th {
    background-color: var(--ui-flaeche-2);
    font-weight: 600;
    text-transform: uppercase;
    font-size: .75rem;
    letter-spacing: .5px;
    color: var(--ui-text-2);
    padding: .75rem;
    border-bottom: 1px solid var(--ui-linie);
    white-space: nowrap;
}

.ranking-table tbody td {
    padding: .5rem .75rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--ui-linie-zart);
}

.ranking-table td:first-child,
.ranking-table th:first-child { text-align: left !important; }

.ranking-table .ranking-row { cursor: pointer; transition: background .15s ease; }
.ranking-table .ranking-row:hover { background: var(--ui-flaeche-2); }

/* Globale .table-Regeln aus msv-styles/resultate-unified neutralisieren:
   keine vertikalen Gitterlinien, kein erzwungener Leerraum, keine 1.-Spalte-Tönung */
.ranking-table th, .ranking-table td { border-right: 0 !important; }
.ranking-table th:first-child, .ranking-table td:first-child {
    width: auto !important; background: transparent !important; font-weight: inherit;
}
.ranking-shell .table-responsive {
    min-height: 0 !important; max-height: none !important; overflow-y: visible !important;
}

/* Total-Zeile */
.ranking-table tr.total-row td {
    background: var(--ui-flaeche-2);
    border-top: 2px solid var(--ui-rand);
    font-weight: 700;
    color: var(--ui-text);
}

/* Rang-Badge (.rang-badge/.r1/.r2/.r3) und Leerzustand (.ui-leerzustand) kommen aus css/msv-ui.css. */

/* Preis-Zelle */
.preis-cell { font-weight: 700; color: var(--ui-text); white-space: nowrap; font-variant-numeric: tabular-nums; }

/* Aktionen: Löschen erst bei Hover deutlich sichtbar */
.ranking-table .row-actions .btn {
    opacity: .5; transition: opacity .15s ease;
    padding: .25rem .45rem; font-size: .8rem;
}
.ranking-table .ranking-row:hover .row-actions .btn { opacity: 1; }


/* Karte für neue Rangierung */
.add-ranking-card {
    background: var(--ui-flaeche-2);
    border: 1px solid var(--ui-rand);
    border-radius: var(--ui-rad-l);
    padding: 1.25rem;
}
.add-ranking-card h6 { color: var(--ui-text); font-weight: 600; }

/* Mobile */
@media (max-width: 767.98px) {
    .ranking-table { font-size: .82rem; min-width: 480px; }
    .ranking-table thead th, .ranking-table tbody td { padding: .55rem .6rem; white-space: nowrap; }
}

/* === EDIT SLIDE PANEL === */
.edit-panel { position: fixed; top: 0; right: -460px; width: 440px; height: 100vh; background: var(--ui-flaeche); box-shadow: -8px 0 30px rgba(0,0,0,0.12); z-index: 1060; transition: right 0.3s cubic-bezier(0.4,0,0.2,1); display: flex; flex-direction: column; }
.edit-panel.open { right: 0; }
.edit-panel-overlay { position: fixed; inset: 0; background: rgba(26, 35, 50, .28); z-index: 1055; opacity: 0; visibility: hidden; transition: all 0.3s; }
.edit-panel-overlay.show { opacity: 1; visibility: visible; }
.edit-panel-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--ui-rand); background: var(--ui-flaeche); flex-shrink: 0; }
.edit-panel-header h6 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--ui-text); }
.edit-panel-body { padding: 1.25rem; overflow-y: auto; flex: 1; }
.edit-panel-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--ui-text-2); margin-bottom: 0.35rem; }
@media (max-width: 767.98px) { .edit-panel { width: 100%; right: -100%; } }
</style>
<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <!-- Äusserer weisser Container -->
            <div class="main-content-wrapper content-width-wide">
                <!-- Header ausserhalb des inneren Containers -->
                <?php
$page_title = 'Sektionsrangierungen erfassen';
$page_show_mobile = true;
ob_start(); ?>
<button type="button" class="btn-help" data-help="sektionsrangierungen.uebersicht" aria-label="Hilfe"></button>
<label for="yearSelect" class="visually-hidden">Jahr</label>
<select id="yearSelect" class="form-select form-select-sm"></select>
<?php $page_title_after = ob_get_clean();
ob_start(); ?>
<button type="button" id="addNewBtn" class="btn btn-outline-success btn-sm" disabled><i class="bi bi-plus-circle me-1"></i>Hinzufügen</button>
<div id="rangAusgabe" class="btn-group btn-group-sm d-none" role="group" aria-label="Sektionsrangierungen">
<button type="button" id="exportPdfBtn" class="btn btn-outline-info"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Sektionsrangierungen</span></button>
<button type="button" id="printPdfBtn" class="btn btn-outline-info msv-druck" data-druck-doctype="sektionsrangierungen" data-druck-label="Sektionsrangierungen" aria-label="Sektionsrangierungen direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
</div>
<?php $page_actions = ob_get_clean();
include 'partials/page_header.inc.php'; ?>

                <!-- Weisser Hintergrund-Container -->
                <div class="content-background">
                    <form id="rankingForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">


                        <!-- Neue Rangierung hinzufügen (versteckt) -->
                        <div class="add-ranking-card mb-3" id="addRankingCard" style="display: none;">
                            <h6 class="mb-3">
                                <i class="bi bi-plus-circle me-2"></i>
                                Neue Rangierung hinzufügen <button type="button" class="btn-help" data-help="sektionsrangierungen.erfassen" aria-label="Hilfe"></button>
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
                                    <button type="button" id="saveRankingBtn" class="btn btn-sm btn-outline-primary w-100">
                                        <i class="bi bi-save me-1"></i>Speichern
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Vorhandene Rangierungen -->
                        <div class="ranking-shell">
                            <div class="desktop-table-container">
                                <div class="table-responsive">
                                    <table class="table ranking-table align-middle" id="sektionsrangTable">
                                        <thead>
                                            <tr>
                                                <th scope="col" style="min-width: 220px;">
                                                    <i class="bi bi-calendar-event me-1"></i>Anlass
                                                </th>
                                                <th scope="col" class="text-center" style="width: 90px;">Rang</th>
                                                <th scope="col" class="text-center" style="width: 150px;">Preis (CHF)</th>
                                                <th scope="col" class="text-center" style="width: 70px;"></th>
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
                    </form>
                </div><!-- /content-background -->
            </div><!-- /main-content-wrapper -->
        </div>
    </div>
</div>

<!-- Edit Slide-Panel -->
<div class="edit-panel-overlay" id="editPanelOverlay"></div>
<div class="edit-panel" id="editPanel">
    <div class="edit-panel-header">
        <h6><i class="bi bi-pencil-square me-2"></i>Rangierung bearbeiten</h6>
        <button class="btn btn-sm btn-outline-secondary" id="editPanelClose"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="edit-panel-body">
        <input type="hidden" id="editRankingId" value="">

        <div class="mb-3">
            <label class="edit-panel-label"><i class="bi bi-calendar-event me-1"></i>Anlass</label>
            <input type="text" class="form-control" id="editAnlassName" readonly>
        </div>

        <div class="mb-3">
            <label class="edit-panel-label"><i class="bi bi-trophy me-1"></i>Rang</label>
            <input type="number" class="form-control" id="editRangInput" min="1" max="999" placeholder="z.B. 1" required>
        </div>

        <div class="mb-3">
            <label class="edit-panel-label"><i class="bi bi-cash me-1"></i>Preis (CHF)</label>
            <input type="number" class="form-control" id="editPreisInput" min="0" step="0.05" placeholder="z.B. 100.00" required>
        </div>

        <hr>
        <button type="button" class="btn btn-outline-primary btn-sm w-100" id="saveEditRankingBtn">
            <i class="bi bi-save me-1"></i>Speichern
        </button>
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
        selectedYear = msvJahrAuswahl('#yearSelect');
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
            <tr><td colspan="4" class="ui-leerzustand">
                <div class="spinner-border spinner-border-sm me-2" style="color: var(--ui-text-2);"></div>
                Lade Rangierungen...
            </td></tr>
        `);

        $.ajax({
            url: 'sektionsrangierungen/load_existing_rankings.php',
            type: 'GET',
            data: { year: year },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.rankings.length > 0) {
                    displayRankings(response.rankings);
                    $('#rangAusgabe').removeClass('d-none');
                } else {
                    $('#rankingsList').html(`
                        <tr><td colspan="4" class="ui-leerzustand">
                            <i class="bi bi-trophy"></i>
                            Noch keine Rangierungen für das Jahr ${year} erfasst.
                        </td></tr>
                    `);
                    buildMobileCardsSektionsrang();
                    // Ohne Rangierungen weder PDF noch Direktdruck (sonst druckt man ein leeres Blatt)
                    $('#rangAusgabe').addClass('d-none');
                }
            },
            error: function () {
                $('#rankingsList').html(`
                    <tr><td colspan="4" class="ui-leerzustand text-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        Fehler beim Laden der Rangierungen.
                    </td></tr>
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
            const r = parseInt(ranking.rang, 10);
            const rcls = r === 1 ? 'r1' : r === 2 ? 'r2' : r === 3 ? 'r3' : '';
            html += `
                <tr class="ranking-row" data-id="${ranking.id}" data-rang="${ranking.rang}"
                    data-preis="${preisNum}" data-anlass="${ranking.bezeichnung}">
                    <td class="text-start fw-semibold">${ranking.bezeichnung}</td>
                    <td class="text-center">
                        <span class="rang-badge ${rcls}">${ranking.rang}</span>
                    </td>
                    <td class="text-center"><span class="preis-cell">CHF ${preisNum.toFixed(2)}</span></td>
                    <td class="text-center row-actions">
                        <button type="button" class="btn btn-outline-danger btn-sm delete-ranking" data-tooltip="Löschen"
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
                <tr class="total-row">
                    <td class="text-start"><i class="bi bi-cash-stack me-2"></i>Total</td>
                    <td class="text-center">–</td>
                    <td class="text-center"><span class="preis-cell">CHF ${totalPrize.toFixed(2)}</span></td>
                    <td></td>
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
                    msvToast(response.message || 'Speichern hat nicht geklappt. Bitte nochmals versuchen.', 'error');
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
        $('#editRangInput').val($row.data('rang'));
        $('#editPreisInput').val($row.data('preis'));

        openEditPanel();
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
                    closeEditPanel();
                    loadExistingRankings(selectedYear);
                } else {
                    msvToast(response.message || 'Ändern hat nicht geklappt. Bitte nochmals versuchen.', 'error');
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
                    msvToast(response.message || 'Löschen hat nicht geklappt. Bitte die Liste neu laden und prüfen.', 'error');
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

        // Ausgabe-Baustein msvAusgabe: sperren, Spinner, Download, Toast
        msvAusgabe(this, {
            url: 'sektionsrangierungen/export_rankings_pdf.php',
            method: 'POST',
            csrf: true,
            data: {
                year: selectedYear,
                orientation: window.MsvDruck ? MsvDruck.orientierung('sektionsrangierungen', 'portrait') : 'portrait' // Format aus dem Druckprofil
            },
            titel: 'Sektionsrangierungen ' + selectedYear,
            name: 'Sektionsrangierungen_' + selectedYear,
            fehler: 'Die Sektionsrangierungen konnten nicht erstellt werden. Bitte nochmals versuchen.'
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
    loadAvailableDefinitions(selectedYear);
    loadExistingRankings(selectedYear);
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

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray): gleicher POST wie der PDF-Button (year, csrf_token) → JSON {pdf_url}
MsvDruck.resolve('sektionsrangierungen', () => {
    const jahr = $('#yearSelect').val();
    return {
        url: 'sektionsrangierungen/export_rankings_pdf.php',
        method: 'POST',
        body: new URLSearchParams({ year: jahr, orientation: MsvDruck.orientierung('sektionsrangierungen', 'portrait'), csrf_token: $('input[name="csrf_token"]').val() }),
        jobName: 'Sektionsrangierungen ' + jahr
    };
});
</script>
<?
include 'footer.inc.php';
?>
