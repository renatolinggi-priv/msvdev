<?php
// check_resultscsv.php - CSV Viewer für alle Stiche
include 'dbconnect.inc.php';

// Session-Kontrolle
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_specific_css = '<link rel="stylesheet" href="inc/check_resultscsv/workflow-styles.css?v=' . time() . '">';
include 'header.inc.php';
?>
<link rel="stylesheet" href="../css/fixes/no-page-scroll-override.css">

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
        position: relative;
    }
    
    .upload-area:hover {
        border-color: #6c757d;
        background-color: #e9ecef;
    }
    
    .upload-area.dragover {
        border-color: #0d6efd;
        background-color: #e7f1ff;
    }
    
    /* Toast Messages - from endsch_import */
    #toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }
    
    .toast-message {
        background: #fff;
        border-radius: 8px;
        padding: 12px 20px;
        margin-bottom: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        min-width: 250px;
        border-left: 4px solid;
    }
    
    .toast-message.show {
        opacity: 1;
        transform: translateX(0);
    }
    
    .toast-success { border-left-color: #28a745; }
    .toast-warning { border-left-color: #ffc107; }
    .toast-error { border-left-color: #dc3545; }
    .toast-info { border-left-color: #17a2b8; }
    
    /* Fix für Scroll-Layout */
    #resultsContainer {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
    }
    
    #stichProgramsContainer {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .results-table-wrapper {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
        margin-bottom: 0; /* Kein extra Abstand */
    }
    
    .results-table-responsive {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: auto;
        border-bottom: 1px solid #dee2e6;
    }
    
    /* Card-Footer immer unten fixiert */
    .results-table-wrapper .card-footer {
        flex-shrink: 0;
        position: sticky;
        bottom: 0;
        background: white;
        z-index: 10;
        border-top: 2px solid #dee2e6;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-7 col-lg-11 col-12 ps-0">
            <div class="main-content-wrapper">
                <!-- Header -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            CSV Resultate Viewer
                        </h2>
                        <p class="text-muted mt-1">Lade eine CSV-Datei hoch, um alle Stiche und deren Details anzuzeigen</p>
                    </div>
                </div>
                
                <div class="content-background">
            
                    <!-- Upload Area -->
                    <div id="uploadPhase">
                        <div class="upload-area" id="uploadArea">
                            <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                            <h4 class="mt-3">CSV-Datei hier ablegen oder klicken zum Auswählen</h4>
                            <p class="text-muted mb-0">Unterstützte Formate: .csv</p>
                            <input type="file" id="fileInput" accept=".csv" style="display: none;">
                        </div>
                    </div>
            
                    <!-- File Info -->
                    <div id="fileInfo" class="alert alert-info mb-3" style="display: none;">
                        <!-- Wird dynamisch gefüllt -->
                    </div>
                    
                    <!-- Results Container -->
                    <div id="resultsContainer" style="display: none;">
                        <div class="row" id="stichProgramsContainer">
                            <!-- Wird dynamisch gefüllt -->
                        </div>
                    </div>
                    
                    <!-- Debug/Raw Data Section -->
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

<!-- Toast Container -->
<div id="toast-container"></div>

<!-- jQuery einbinden falls nicht vorhanden -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- JavaScript Module einbinden -->
<script src="check_resultscsv/ui_helper.js?v=<?php echo time(); ?>"></script>
<script src="check_resultscsv/csv_viewer.js?v=<?php echo time(); ?>"></script>

<script>
// Scroll-Höhen-Berechnung optimiert
function calculateScrollHeight() {
    const wrapper = document.querySelector('.results-table-wrapper');
    const responsive = document.querySelector('.results-table-responsive');
    const footer = document.querySelector('.results-table-wrapper .card-footer');
    
    if (!wrapper || !responsive) return;
    
    // Gesamte verfügbare Höhe berechnen
    const wrapperRect = wrapper.getBoundingClientRect();
    const viewportHeight = window.innerHeight;
    const topOffset = wrapperRect.top;
    const bottomPadding = 10; // Minimaler Abstand zum Browser-Rand
    
    // Höhe für den gesamten Wrapper
    const totalAvailableHeight = viewportHeight - topOffset - bottomPadding;
    wrapper.style.maxHeight = `${totalAvailableHeight}px`;
    
    // Höhe für den scrollbaren Bereich (minus Header und Footer)
    const header = wrapper.querySelector('.card-header');
    const headerHeight = header ? header.offsetHeight : 0;
    const footerHeight = footer ? footer.offsetHeight : 0;
    
    const scrollAreaHeight = totalAvailableHeight - headerHeight - footerHeight - 2; // -2 für Borders
    responsive.style.maxHeight = `${scrollAreaHeight}px`;
    responsive.style.height = `${scrollAreaHeight}px`;
}

// Scroll-Ereignisse für die gesamte Seite abfangen
function setupGlobalScroll() {
    // Verhindere Scrollen auf body/html
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
    
    // Leite Scroll-Events an die Tabelle weiter
    window.addEventListener('wheel', function(e) {
        const scrollContainer = document.querySelector('.results-table-responsive');
        if (scrollContainer && scrollContainer.offsetParent !== null) {
            e.preventDefault();
            scrollContainer.scrollTop += e.deltaY;
        }
    }, { passive: false });
}

$(document).ready(function() {
    console.log('[CSV-VIEWER] Initializing CSV Viewer');
    CSVViewer.init();
    
    // Global Scroll Setup
    setupGlobalScroll();
    
    // Scroll-Höhen bei Bedarf neu berechnen
    window.addEventListener('resize', calculateScrollHeight);
    
    // Initial berechnen nach kurzer Verzögerung
    setTimeout(calculateScrollHeight, 200);
});
</script>

<?php
include 'footer.inc.php';
?>
