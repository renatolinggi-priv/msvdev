<?php
// result_import_csv.php - Frontend für CSV Import
include 'dbconnect.inc.php';

// Session-Kontrolle
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Lade Mitglieder für Dropdown
// Versuche Lizenznummer zu laden falls vorhanden
$sql = "SELECT * FROM mitglieder ORDER BY Name, Vorname";
$mitglieder_result = connect_db($sql);

include 'header.inc.php';
?>

<style>
    /* Inhaltsbreite begrenzen, damit die Seite auf grossen Bildschirmen nicht zu breit wird */
    .main-content-wrapper { max-width: 980px; }

    .upload-area {
        border: 2px dashed #dee2e6;
        border-radius: 0.75rem;
        padding: 3rem;
        text-align: center;
        background-color: #f8f9fa;
        transition: all 0.3s ease;
        cursor: pointer;
        margin-bottom: 2rem;
    }
    
    .upload-area:hover {
        border-color: #6c757d;
        background-color: #e9ecef;
    }
    
    .upload-area.dragover {
        border-color: #0d6efd;
        background-color: #e7f1ff;
    }
    
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }
    
    .loading-spinner {
        background: white;
        padding: 2rem;
        border-radius: 0.5rem;
        text-align: center;
        color: #333;
    }
</style>

<!-- 3-Phasen CSV Import Workflow -->
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-7 col-lg-8 col-md-10 col-12 ps-0">
            <div class="main-content-wrapper">
                <!-- Header -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-file-earmark-arrow-up me-2"></i>
                            CSV Import - Heim- und Kantimeisterschaft
                        </h2>
                        <p class="text-muted mt-1">3-Phasen-Workflow: Upload → Auswahl → Import</p>
                    </div>
                </div>
                
                    <!-- Phase 1: Upload -->
                    <div id="phase1" class="workflow-phase active">
                        <div class="upload-area" id="uploadArea">
                            <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                            <h4 class="mt-3">CSV-Datei hier ablegen oder klicken zum Auswählen</h4>
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
                                Import-Einstellungen
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
                                <select id="jahrSelect" class="form-select">
                                    <?php
                                    $currentYear = date('Y');
                                    for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
                                        echo '<option value="' . $year . '"' .
                                             ($year == $currentYear ? ' selected' : '') . '>' .
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
                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="FileHandler.goToPhase(1)">
                                <i class="bi bi-arrow-left me-2"></i>Zurück
                            </button>
                            <button type="button" class="btn btn-success" id="proceedToImportBtn" onclick="FileHandler.proceedToImport()" disabled>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-success" id="confirmFinalImportBtn">
                    <i class="bi bi-check-circle me-2"></i>Bestätigen & Importieren
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSRF Token für JavaScript -->
<script>
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
</script>

<!-- JavaScript Module einbinden -->
<script src="heimkanti_import/csv_handler.js?v=<?php echo time(); ?>"></script>
<script src="heimkanti_import/import_manager.js?v=<?php echo time(); ?>"></script>
<script src="heimkanti_import/ui_helper.js?v=<?php echo time(); ?>"></script>

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
</script>

<?php
include 'footer.inc.php';
?>
