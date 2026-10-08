<?php
// result_import_csv.php - Frontend für CSV Import
include 'dbconnect.inc.php';

// Lade Mitglieder für Dropdown
// Versuche Lizenznummer zu laden falls vorhanden
$sql = "SELECT * FROM mitglieder ORDER BY Name, Vorname";
$mitglieder_result = connect_db($sql);

$page_specific_css = "
/* Upload-Bereich: Klick, Enter oder Datei ablegen */
.upload-area { margin-bottom: 0; padding: 40px 24px; text-align: center; background: var(--ui-flaeche-2); border: 2px dashed var(--ui-feldrand); border-radius: var(--ui-rad-l); cursor: pointer; transition: border-color .2s, background-color .2s; }
.upload-area:hover { background: var(--ui-flaeche); border-color: #9aa6b6; }
.upload-area:focus-visible { outline: 2px solid var(--ui-akzent); outline-offset: 2px; }
.upload-area.dragover { background: var(--ui-gewaehlt); border-color: var(--ui-akzent); }
.upload-area > .bi { font-size: 2.5rem; color: var(--ui-text-3); }
.upload-area h4 { margin: 10px 0 4px; font-size: 1.05rem; font-weight: 600; color: var(--ui-text); }
.loading-overlay { position: fixed; inset: 0; z-index: 9999; display: flex; justify-content: center; align-items: center; background: rgba(26, 35, 50, .55); }
.loading-spinner { padding: 2rem; text-align: center; color: var(--ui-text); background: var(--ui-flaeche); border-radius: var(--ui-rad-l); }
.import-nav { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }
";
include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
$csrf = csrf_token();
?>

<!-- 3-Phasen CSV Import Workflow -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-default">
                <!-- Header -->
                <?php $page_title = 'CSV Import - Heim- und Kantimeisterschaft'; $page_title_after = '<button type="button" class="btn-help" data-help="heimkanti_import.uebersicht" aria-label="Hilfe"></button>'; include 'partials/page_header.inc.php'; ?>

                <div class="content-background">
                
                    <!-- Phase 1: Upload -->
                    <div id="phase1" class="workflow-phase active">
                        <div class="upload-area" id="uploadArea" role="button" tabindex="0" aria-label="CSV-Datei auswählen oder hier ablegen">
                            <i class="bi bi-cloud-upload" aria-hidden="true"></i>
                            <h4>CSV-Datei hier ablegen oder klicken zum Auswählen</h4>
                            <p class="text-muted mb-0">Unterstützte Formate: .csv</p>
                            <input type="file" id="fileInput" accept=".csv" style="display: none;">
                        </div>
                        
                        <!-- File Info wird hier angezeigt nach Upload -->
                        <div id="fileInfo" style="display: none;" class="alert alert-info">
                            <!-- Wird dynamisch gefüllt -->
                        </div>
                    </div>
                    
                    <!-- Phase 2: Program Selection (handled by csv_handler.js) -->
                    <div id="phase2" class="workflow-phase" style="display: none;">
                        <!-- Member/Year Selection -->
                        <div class="table-wrapper mb-4">
                            <h5 class="table-title">
                                <i class="bi bi-person-check me-2"></i>
                                Import-Einstellungen <button type="button" class="btn-help" data-help="heimkanti_import.einstellungen" aria-label="Hilfe"></button>
                            </h5>
                            <div class="p-3">
                                <div class="row">
                            <div class="col-md-6">
                                <label for="mitgliedSelect" class="form-label">
                                    <strong>Mitglied auswählen:</strong>
                                </label>
                                <select id="mitgliedSelect" class="form-select">
                                    <option value="">Bitte wählen...</option>
                                    <?php
                                    // Reset result pointer for reuse
                                    $mitglieder_result->data_seek(0);
                                    while ($row = $mitglieder_result->fetch_assoc()) {
                                        $dataAttrs = '';
                                        if (isset($row['Lizenznummer'])) {
                                            $dataAttrs = ' data-license="' . htmlspecialchars($row['Lizenznummer']) . '"';
                                        }
                                        echo '<option value="' . $row['ID'] . '"' . $dataAttrs . '>' .
                                             htmlspecialchars($row['Name'] . ' ' . $row['Vorname']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="jahrSelect" class="form-label">
                                    <strong>Jahr:</strong>
                                </label>
                                <select id="jahrSelect" class="form-select" data-msv-jahr>
                                    <?php
                                    $currentYear = date('Y');
                                    require_once __DIR__ . '/jahr.inc.php';
                                    $jahrStandard = msvJahrStandard(range((int)$currentYear, (int)$currentYear - 5));
                                    for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
                                        echo '<option value="' . $year . '"' .
                                             ($year == $jahrStandard ? ' selected' : '') . '>' .
                                             $year . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                            </div>
                        </div>
                        
                        <!-- Program Selection Container -->
                        <div id="programSelectionContainer">
                            <!-- Wird von csv_handler.js dynamisch gefüllt -->
                        </div>
                        
                        <!-- Phase 2 Navigation -->
                        <div class="import-nav">
                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="FileHandler.goToPhase(1)">
                                <i class="bi bi-arrow-left me-2"></i>Zurück
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" id="proceedToImportBtn" onclick="FileHandler.proceedToImport()" disabled>
                                <i class="bi bi-arrow-right me-2"></i>Weiter zum Import
                            </button>
                        </div>
                    </div>
                    
                    <!-- Phase 3 entfernt - direkter Modal-Aufruf nach Auswahl -->
                    
                    <!-- Debug/Raw Data Section (collapsed by default) -->
                    <div class="mt-4" id="debugSection" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">
                                <i class="bi bi-bug me-2"></i>
                                Debug Information
                            </h5>
                            <button class="btn btn-outline-info btn-sm" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#rawDataCollapse">
                                <i class="bi bi-code-slash me-2"></i>Rohdaten
                            </button>
                        </div>
                        
                        <!-- Raw Data Collapse -->
                        <div class="collapse" id="rawDataCollapse">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">CSV Rohdaten (erste 50 Zeilen)</h6>
                                </div>
                                <div class="card-body">
                                    <pre id="rawDataPreview" style="max-height: 400px; overflow-y: auto; font-size: 0.8rem; margin: 0;"></pre>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Program Overview -->
                        <div id="allPrograms" class="mt-3">
                            <!-- Wird von csv_handler.js gefüllt -->
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Confirmation Modal -->
<div class="modal fade" id="importConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-download me-2"></i>
                    Import bestätigen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalImportSummary">
                    <!-- Wird dynamisch gefüllt -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-outline-success btn-sm" id="confirmFinalImportBtn">
                    <i class="bi bi-check-circle me-2"></i>Bestätigen & Importieren
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSRF Token für JavaScript -->
<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
</script>

<!-- JavaScript Module einbinden -->
<script src="heimkanti_import/csv_handler.js?v=<?php echo @filemtime(__DIR__ . '/heimkanti_import/csv_handler.js') ?: '1'; ?>"></script>
<script src="heimkanti_import/import_manager.js?v=<?php echo @filemtime(__DIR__ . '/heimkanti_import/import_manager.js') ?: '1'; ?>"></script>
<script src="heimkanti_import/ui_helper.js?v=<?php echo @filemtime(__DIR__ . '/heimkanti_import/ui_helper.js') ?: '1'; ?>"></script>

<script>
// 3-Phasen Workflow Initialisierung
$(document).ready(function() {
    console.log('Initializing 3-Phase CSV Import Workflow');
    FileHandler.init();
    ImportManager.init();
    
    // Show debug section toggle
    $('#debugToggle').on('click', function() {
        $('#debugSection').toggle();
    });
});

// Workflow Helper
const WorkflowHelper = {
    updateProgress(activeStep) {
        $('.progress-step').removeClass('active completed');
        
        for (let i = 1; i <= 3; i++) {
            const step = $(`.progress-step[data-step="${i}"]`);
            if (i < activeStep) {
                step.addClass('completed');
            } else if (i === activeStep) {
                step.addClass('active');
            }
        }
    },
    
    showPhase(phaseNumber) {
        $('.workflow-phase').hide();
        $(`#phase${phaseNumber}`).show();
        this.updateProgress(phaseNumber);
    }
};

// Globaler Zugriff für csv_handler.js
window.WorkflowHelper = WorkflowHelper;

// Upload-Bereich auch per Tastatur (Enter / Leertaste) öffnen
$('#uploadArea').on('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $('#fileInput').trigger('click'); }
});
</script>

<?php
include 'footer.inc.php';
?>
