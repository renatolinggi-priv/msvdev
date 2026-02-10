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

$page_specific_css = '';
include 'header.inc.php';
?>

<style>
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
    
    .result-card {
        background: #ffffff;
        border-radius: 0.75rem;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0;
    }
    
    .program-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 0.5rem;
        font-weight: 600;
        margin-right: 1rem;
    }
    
    .total-value {
        font-size: 2rem;
        font-weight: 700;
        color: #28a745;
    }
    
    .no-data {
        color: #6c757d;
        font-style: italic;
    }
    
    .file-info {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .program-overview-card {
        background: #f8f9fa;
        border-radius: 0.5rem;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        border: 1px solid #e2e8f0;
    }
    
    .program-selection-card {
        background: #ffffff;
        border: 2px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .program-selection-card:hover {
        border-color: #0d6efd;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .program-selection-card.selected {
        border-color: #28a745;
        background: #f0fdf4;
    }
    
    .program-selection-card.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .passe-assignment {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        background: #0d6efd;
        color: white;
        border-radius: 0.25rem;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .import-preview {
        background: #f8f9fa;
        border-radius: 0.5rem;
        padding: 1.5rem;
        margin-top: 1rem;
    }
    
    .warning-box {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .existing-data-warning {
        background: #f8d7da;
        border: 1px solid #dc3545;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }
</style>

<!-- Result Import CSV Hauptbereich -->
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-10 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-file-earmark-arrow-up me-2"></i>
                            CSV Import - Schiessprogramme
                        </h2>
                    </div>
                </div>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    
                    <!-- Upload Area -->
                    <div class="upload-area" id="uploadArea">
                        <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                        <h4 class="mt-3">CSV-Datei hier ablegen oder klicken zum Auswählen</h4>
                        <p class="text-muted mb-0">Unterstützte Formate: .csv</p>
                        <input type="file" id="fileInput" accept=".csv" style="display: none;">
                    </div>
                    
                    <!-- Results Container -->
                    <div id="resultsContainer" style="display: none;">
                        
                        <!-- File Info -->
                        <div class="file-info" id="fileInfo"></div>
                        
                        <!-- Import Section -->
                        <div id="importSection" style="display: none;">
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
                                                while ($row = $mitglieder_result->fetch_assoc()) {
                                                    // Füge data-attributes für bessere Suche hinzu
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
                                    
                                    <div id="warningSection" class="mt-3" style="display: none;">
                                        <div class="warning-box">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            <strong>Achtung:</strong> <span id="warningMessage"></span>
                                        </div>
                                    </div>
                                    
                                    <div id="existingDataWarning" class="mt-3" style="display: none;">
                                        <div class="existing-data-warning">
                                            <i class="bi bi-exclamation-circle me-2"></i>
                                            <strong>Bestehende Daten:</strong> 
                                            <span id="existingDataMessage"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Programm 133 Auswahl -->
                                <div class="col-md-6">
                                    <div class="table-wrapper">
                                        <h5 class="table-title">
                                            <i class="bi bi-target me-2"></i>
                                            Programm 133 - Auswahl für Import
                                        </h5>
                                        <div id="program133Selection" class="p-3">
                                            <!-- Wird dynamisch gefüllt -->
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Programm 134 Auswahl -->
                                <div class="col-md-6">
                                    <div class="table-wrapper">
                                        <h5 class="table-title">
                                            <i class="bi bi-target me-2"></i>
                                            Programm 134 - Auswahl für Import
                                        </h5>
                                        <div id="program134Selection" class="p-3">
                                            <!-- Wird dynamisch gefüllt -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Import Preview -->
                            <div class="import-preview" id="importPreview" style="display: none;">
                                <h5 class="mb-3">
                                    <i class="bi bi-eye me-2"></i>
                                    Import-Vorschau
                                </h5>
                                <div id="previewContent"></div>
                                
                                <div class="button-toolbar mt-3">
                                    <div class="button-group">
                                        <button class="btn btn-success" onclick="ImportManager.executeImport()">
                                            <i class="bi bi-check-circle me-2"></i>
                                            Import durchführen
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="ImportManager.resetImport()">
                                            <i class="bi bi-x-circle me-2"></i>
                                            Abbrechen
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hauptergebnisse -->
                        <h4 class="mb-3">
                            <i class="bi bi-search me-2"></i>
                            Gefundene Schiessprogramme
                        </h4>
                        
                        <div class="row">
                            <!-- Programm 133 -->
                            <div class="col-md-6">
                                <div class="table-wrapper">
                                    <h5 class="table-title">
                                        <i class="bi bi-target me-2"></i>
                                        Programm 133
                                        <span id="count133" class="badge bg-secondary float-end">0 gefunden</span>
                                    </h5>
                                    <div id="results133" class="p-3">
                                        <p class="no-data">Keine Daten gefunden</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Programm 134 -->
                            <div class="col-md-6">
                                <div class="table-wrapper">
                                    <h5 class="table-title">
                                        <i class="bi bi-target me-2"></i>
                                        Programm 134
                                        <span id="count134" class="badge bg-secondary float-end">0 gefunden</span>
                                    </h5>
                                    <div id="results134" class="p-3">
                                        <p class="no-data">Keine Daten gefunden</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Button Toolbar -->
                        <div class="button-toolbar mt-4">
                            <div class="button-group">
                                <button class="btn btn-compact-standard btn-outline-success" id="prepareImportBtn" 
                                        onclick="ImportManager.prepareImport()" disabled>
                                    <i class="bi bi-database-add me-2"></i>
                                    Für Import vorbereiten
                                </button>
                                <button class="btn btn-compact-standard btn-outline-info" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#rawDataCollapse">
                                    <i class="bi bi-code-slash me-2"></i>
                                    Rohdaten anzeigen
                                </button>
                                <button class="btn btn-compact-standard btn-outline-secondary" onclick="FileHandler.resetUpload()">
                                    <i class="bi bi-arrow-repeat me-2"></i>
                                    Neue Datei hochladen
                                </button>
                            </div>
                        </div>
                        
                        <!-- Übersicht aller Programme -->
                        <div class="table-wrapper mt-4">
                            <h5 class="table-title">
                                <i class="bi bi-list-ul me-2"></i>
                                Übersicht aller Programme im CSV
                            </h5>
                            <div id="allPrograms" class="p-3"></div>
                        </div>
                        
                        <!-- Raw Data Collapse -->
                        <div class="collapse mt-3" id="rawDataCollapse">
                            <div class="table-wrapper">
                                <h5 class="table-title">
                                    <i class="bi bi-file-text me-2"></i>
                                    Rohdaten (erste 50 Zeilen)
                                </h5>
                                <div class="p-3">
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

<!-- Confirm Import Modal -->
<div class="modal fade" id="confirmImportModal" tabindex="-1" aria-labelledby="confirmImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmImportModalLabel">
                    <i class="bi bi-question-circle me-2"></i>
                    Import bestätigen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Möchtest du die ausgewählten Programme wirklich importieren?</p>
                <div id="confirmImportDetails" class="mt-3">
                    <!-- Wird dynamisch gefüllt mit Import-Details -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>
                    Abbrechen
                </button>
                <button type="button" class="btn btn-success" id="confirmImportBtn">
                    <i class="bi bi-check-circle me-2"></i>
                    Ja, importieren
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
<script src="resultimport/csv_handler.js?v=<?php echo time(); ?>"></script>
<script src="resultimport/import_manager.js?v=<?php echo time(); ?>"></script>
<script src="resultimport/ui_helper.js?v=<?php echo time(); ?>"></script>

<script>
// Initialisierung
$(document).ready(function() {
    console.log('Initializing CSV Import Tool');
    FileHandler.init();
    ImportManager.init();
});
</script>

<?php
include 'footer.inc.php';
?>