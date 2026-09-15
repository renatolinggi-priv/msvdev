<?php
// standbelegung.php – Import, Übersicht/Export und Art-Erkennung des Standbelegungsplans
// Aufbau (Review 09.2026): Daten in standbelegung/load_page_data.inc.php, Tabs als Partials,
// Logik in js/standbelegung.js, Konfiguration (Art-Codes/Regeln) in standbelegung/standbelegung_config.inc.php.
require_once 'dbconnect.inc.php';
require_once 'standbelegung/load_page_data.inc.php';

$page_specific_css = @file_get_contents(__DIR__ . '/../css/standbelegung.css') ?: '';

// Kopfzeile: Veröffentlichen-Button rechts (Partial-Slot)
$page_actions = '<button type="button" class="btn btn-outline-success btn-sm" data-action="publish"><i class="bi bi-megaphone me-1"></i>Veröffentlichen</button>';

include 'header.inc.php';

// Seitendaten fuer js/standbelegung.js – JSON_HEX_* verhindert </script>-Ausbruch aus Bezeichnungen
$sbInit = json_encode([
    'csrf'       => $_SESSION['csrf_token'],
    'artCodes'   => SB_ART_CODES,
    'kategorien' => SB_KATEGORIEN,
    'rules'      => SB_ART_RULES,
    'entries'    => $existingEntries,
    'keywords'   => $artKeywords,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-narrow">
                <?php $page_title = 'Standbelegung'; include 'partials/page_header.inc.php'; ?>

                <div class="content-background">
                    <ul class="nav nav-tabs mb-3" id="mainTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="import-tab" data-bs-toggle="tab" data-bs-target="#import" type="button"><i class="bi bi-cloud-upload me-1"></i> Import</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button">
                                <i class="bi bi-table me-1"></i> Übersicht &amp; Export
                                <span class="badge bg-secondary ms-1"><?= $stats[$currentYear]['total'] ?? 0 ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button"><i class="bi bi-gear me-1"></i> Art-Erkennung</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="mainTabsContent">
                        <?php
                        include 'standbelegung/tab_import.inc.php';
                        include 'standbelegung/tab_overview.inc.php';
                        include 'standbelegung/tab_keywords.inc.php';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading-Overlay (Excel-Parser / PDF-Upload) -->
<div id="loadingOverlay" class="loading-overlay" hidden>
    <div class="loading-box">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-3 fw-semibold" id="loadingText">Wird verarbeitet...</div>
    </div>
</div>

<!-- Slide-Panel: Eintrag bearbeiten/hinzufügen (zentrales Partial) -->
<?php
$panel_id         = 'editPanel';
$panel_overlay_id = 'editPanelOverlay';
$panel_close_id   = 'closeEditPanel';
$panel_width      = '440px';
$panel_title      = '<span id="editModalTitle"><i class="bi bi-pencil me-2"></i>Eintrag bearbeiten</span>';
ob_start(); ?>
        <input type="hidden" id="editId">
        <div class="mb-3">
            <label for="editDatum" class="panel-label">Datum *</label>
            <input type="date" class="form-control form-control-sm" id="editDatum" required>
        </div>
        <div class="mb-3">
            <label for="editKategorie" class="panel-label">Kategorie *</label>
            <select class="form-select form-select-sm" id="editKategorie" required>
                <?php foreach (SB_KATEGORIEN as $kat): ?><option value="<?= $kat ?>"><?= $kat ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="editBezeichnung" class="panel-label">Bezeichnung *</label>
            <input type="text" class="form-control form-control-sm" id="editBezeichnung" required>
        </div>
        <div class="row g-2 mb-3">
            <div class="col-6"><label for="editStartZeit" class="panel-label">Von</label><input type="time" class="form-control form-control-sm" id="editStartZeit"></div>
            <div class="col-6"><label for="editEndZeit" class="panel-label">Bis</label><input type="time" class="form-control form-control-sm" id="editEndZeit"></div>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="editInKalender">
            <label class="form-check-label" for="editInKalender"><i class="bi bi-calendar-check me-1"></i> Im Kalender anzeigen</label>
        </div>
<?php
$panel_body = ob_get_clean();
ob_start(); ?>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelEditPanel">Abbrechen</button>
        <button type="button" class="btn btn-outline-primary btn-sm" id="saveEntryBtn" data-action="entry-save"><i class="bi bi-save me-1"></i> Speichern</button>
<?php
$panel_footer = ob_get_clean();
include 'partials/side_panel.inc.php';
?>

<!-- Export-Vorschau (Schiesstagemeldung) -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalTitle">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalTitle"><i class="bi bi-file-earmark-excel text-success me-2"></i>Export-Vorschau Schiesstagemeldung</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3 small">Überprüfe und passe die <strong>Art</strong> für jeden Eintrag an, bevor du exportierst.</p>
                <div class="preview-table-wrapper" style="max-height:400px;">
                    <table class="table table-hover table-sm preview-table mb-0">
                        <thead><tr><th>Disziplin</th><th>Datum</th><th>Von</th><th>Bis</th><th style="width:100px;">Art</th><th>Anlass</th></tr></thead>
                        <tbody id="exportPreviewBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-outline-info btn-sm" id="executeExportBtn" data-action="export-execute"><i class="bi bi-download me-1"></i> Excel herunterladen</button>
            </div>
        </div>
    </div>
</div>

<script>window.SB_INIT = <?= $sbInit ?>;</script>
<script src="js/standbelegung.js?v=<?= (string)(@filemtime(__DIR__ . '/js/standbelegung.js') ?: '1') ?>"></script>

<?php include 'footer.inc.php'; ?>
