<?php
// endsch_import.php – 3-Phasen CSV-Import für Endstich/Kunst/Glück/Zabig/Schwini
include 'dbconnect.inc.php';

// Lade Mitglieder für Dropdown
$sql = "SELECT * FROM mitglieder ORDER BY Name, Vorname";
$mitglieder_result = connect_db($sql);

$page_specific_css = "
/* Upload-Fläche (.upload-area) und Lade-Overlay kommen aus css/msv-ui.css */
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
                <?php $page_title = 'CSV Import - Endschiessen'; $page_title_after = '<button type="button" class="btn-help" data-help="endsch_import.uebersicht" aria-label="Hilfe"></button>'; include 'partials/page_header.inc.php'; ?>
                
                <div class="content-background">
                    
                    <!-- Phase 1: Upload -->
                    <div id="phase1" class="workflow-phase active">
                        <div class="upload-area" id="uploadArea" role="button" tabindex="0" aria-label="CSV-Datei auswählen oder hier ablegen">
                            <i class="bi bi-cloud-upload" aria-hidden="true"></i>
                            <h4>CSV-Datei hier ablegen oder klicken zum Auswählen</h4>
                            <p class="text-muted mb-0">Unterstützte Formate: .csv</p>
                            <input type="file" id="fileInput" accept=".csv" style="display: none;">
                        </div>
                    </div>
                    
                    <!-- Phase 2: Program Selection -->
                    <div id="phase2" class="workflow-phase" style="display: none;">
                        <!-- Scrollbarer Inner Container -->
                        <div>
                            <!-- File Info -->
                            <div id="fileInfo" class="alert alert-info mb-3">
                                <!-- Wird dynamisch gefüllt -->
                            </div>
                            <!-- Member/Year Selection -->
                            <div class="table-wrapper mb-4">
                                <h5 class="table-title">
                                    <i class="bi bi-person-check me-2"></i>
                                    Import-Einstellungen <button type="button" class="btn-help" data-help="endsch_import.einstellungen" aria-label="Hilfe"></button>
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
                                                $jahrStandard = msvJahrStandard(range(2024, (int)$currentYear + 1));
                                                for ($year = 2024; $year <= $currentYear + 1; $year++) {
                                                    echo '<option value="' . $year . '"' .
                                                         ($year == $jahrStandard ? ' selected' : '') . '>' .
                                                         $year . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div id="existingDataWarning" class="mt-3" style="display: none;">
                                        <div class="alert alert-warning">
                                            <i class="bi bi-exclamation-circle me-2"></i>
                                            <strong>Bestehende Daten:</strong>
                                            <span id="existingDataMessage"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Program Selection Container -->
                            <div id="programSelectionContainer" style="min-height: auto;">
                                <!-- Programme nach Stich gruppiert - wird dynamisch gefüllt -->
                                <div class="row" id="stichProgramsContainer" style="min-height: auto;">
                                    <!-- Wird dynamisch gefüllt -->
                                </div>
                            </div>
                            
                            <!-- Phase 2 Navigation -->
                            <div class="import-nav">
                                <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="WorkflowHelper.goToPhase(1)">
                                    <i class="bi bi-arrow-left me-2"></i>Zurück
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm" id="proceedToImportBtn" onclick="WorkflowHelper.proceedToImport()" disabled>
                                    <i class="bi bi-arrow-right me-2"></i>Weiter zum Import
                                </button>
                            </div>
                        </div>
                    </div>
                    
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
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Confirmation Modal (optional - für zusätzliche Bestätigung) -->
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
                <button type="button" class="btn btn-outline-success btn-sm" id="modalConfirmImportBtn">
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
<script src="endsch_import/ui_helper.js?v=<?php echo @filemtime(__DIR__ . '/endsch_import/ui_helper.js') ?: '1'; ?>"></script>
<script src="endsch_import/import_manager.js?v=<?php echo @filemtime(__DIR__ . '/endsch_import/import_manager.js') ?: '1'; ?>"></script>
<script src="endsch_import/csv_handler.js?v=<?php echo @filemtime(__DIR__ . '/endsch_import/csv_handler.js') ?: '1'; ?>"></script>

<script>
// 3-Phasen Workflow Initialisierung
$(document).ready(function() {
    console.log('[ENDSCH-MAIN] Initializing 3-Phase CSV Import Workflow for Endschiessen');
    
    // Prüfe ob alle erforderlichen Module geladen sind
    const requiredModules = ['FileHandler', 'ImportManagerSingle', 'UIHelper'];
    const missingModules = [];
    
    requiredModules.forEach(moduleName => {
        if (typeof window[moduleName] === 'undefined') {
            missingModules.push(moduleName);
            console.error(`[ENDSCH-MAIN] Module ${moduleName} not loaded!`);
        } else {
            console.log(`[ENDSCH-MAIN] Module ${moduleName} found`);
        }
    });
    
    if (missingModules.length > 0) {
        console.error('[ENDSCH-MAIN] Missing modules:', missingModules);
        const errorMsg = `Fehler beim Laden der Module: ${missingModules.join(', ')}. Bitte Seite neu laden.`;
        if (typeof UIHelper !== 'undefined') {
            UIHelper.showToast(errorMsg, 'error');
        } else {
            msvError(errorMsg);
        }
        return;
    }
    
    console.log('[ENDSCH-MAIN] All modules loaded successfully');
    
    // Init modules sequenziell mit Fehlerbehandlung
    Promise.resolve()
        .then(async () => {
            console.log('[ENDSCH-MAIN] Initializing FileHandler...');
            if (typeof FileHandler !== 'undefined' && FileHandler.init) {
                const result = await FileHandler.init();
                if (result === false) {
                    throw new Error('FileHandler initialization failed');
                }
                console.log('[ENDSCH-MAIN] FileHandler initialized successfully');
            }
        })
        .then(() => {
            console.log('[ENDSCH-MAIN] Initializing ImportManagerSingle...');
            if (typeof ImportManagerSingle !== 'undefined' && ImportManagerSingle.init) {
                const result = ImportManagerSingle.init();
                if (result === false) {
                    throw new Error('ImportManagerSingle initialization failed');
                }
                console.log('[ENDSCH-MAIN] ImportManagerSingle initialized successfully');
            }
        })
        .then(() => {
            console.log('[ENDSCH-MAIN] All modules initialized successfully');
        })
        .catch(error => {
            console.error('[ENDSCH-MAIN] Module initialization failed:', error);
            const errorMsg = `Initialisierung fehlgeschlagen: ${error.message}`;
            if (typeof UIHelper !== 'undefined') {
                UIHelper.showToast(errorMsg, 'error');
            } else {
                msvError(errorMsg);
            }
        });
    
    // Debug toggle
    $('#debugToggle').on('click', function() {
        $('#debugSection').toggle();
    });
    
    // Zusätzliche DOM-Checks
    setTimeout(() => {
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        
        if (!uploadArea) {
            console.error('[ENDSCH-MAIN] Upload area not found in DOM!');
            UIHelper.showToast('Upload-Bereich nicht gefunden. Seite neu laden.', 'error');
        }
        if (!fileInput) {
            console.error('[ENDSCH-MAIN] File input not found in DOM!');
            UIHelper.showToast('Datei-Input nicht gefunden. Seite neu laden.', 'error');
        }
        
        if (uploadArea && fileInput) {
            console.log('[ENDSCH-MAIN] All DOM elements verified');
        }
    }, 500);
});

// Workflow Helper - vereinheitlicht für beide Import-Module
const WorkflowHelper = {
    currentPhase: 1,
    
    updateProgress(activeStep) {
        $('.step').removeClass('active completed');
        
        for (let i = 1; i <= 2; i++) {
            const step = $(`.step[data-step="${i}"]`);
            if (i < activeStep) {
                step.addClass('completed');
            } else if (i === activeStep) {
                step.addClass('active');
            }
        }
    },
    
    showPhase(phaseNumber) {
        console.log(`[WorkflowHelper] Switching to Phase ${phaseNumber}`);
        
        // Hide all phases
        $('.workflow-phase').hide();
        
        // Show selected phase with animation
        $(`#phase${phaseNumber}`).fadeIn(300).addClass('fade-in');
        
        // Update progress indicator
        this.updateProgress(phaseNumber);
        
        // Store current phase
        this.currentPhase = phaseNumber;
        
        // Phase-specific actions
        switch(phaseNumber) {
            case 1:
                // Reset für neuen Upload
                this.resetUpload();
                break;
            case 2:
                // Aktiviere Programme-Auswahl
                this.initProgramSelection();
                break;
        }
    },
    
    goToPhase(phaseNumber) {
        this.showPhase(phaseNumber);
    },
    
    proceedToImport() {
        // Keine Prüfung mehr nötig - immer alle Programme importieren
        // Direkt das Import-Modal zeigen
        console.log('[WorkflowHelper] Zeige Import-Modal direkt für alle Programme');
        if (typeof ImportManagerSingle !== 'undefined' && ImportManagerSingle.showPreview) {
            ImportManagerSingle.showPreview();
        } else {
            console.error('[WorkflowHelper] ImportManagerSingle.showPreview nicht gefunden');
        }
    },
    
    resetUpload() {
        $('#fileInput').val('');
        $('#fileInfo').hide();
        $('#uploadArea').show();
    },
    
    initProgramSelection() {
        // Wird von csv_handler.js aufgerufen nach erfolgreichem Upload
        console.log('[WorkflowHelper] Initializing program selection');
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
