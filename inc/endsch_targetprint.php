<?php
// endsch_targetprint.php - Zielscheiben-Ausdruck für Wettkämpfe
require_once 'config.php';

$page_specific_css = '<link rel="stylesheet" href="endsch_targetprint/targetprint-styles.css?v=' . time() . '">';
include 'header.inc.php';

// Session-Kontrolle (nach header.inc.php, da dort die Session gestartet wird)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Stich-Definitionen aus Datenbank laden
$stichDefinitionen = [];
$programmNummerMapping = []; // nummer => stichname

try {
    $sql = "SELECT stich, nummer1, nummer2, nummer3 FROM interne_stichdefinition ORDER BY stich";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $stichName = $row['stich'];
        
        // Alle nummern1-3 erfassen
        if (!empty($row['nummer1'])) {
            $programmNummerMapping[$row['nummer1']] = $stichName;
        }
        if (!empty($row['nummer2'])) {
            $programmNummerMapping[$row['nummer2']] = $stichName;
        }
        if (!empty($row['nummer3'])) {
            $programmNummerMapping[$row['nummer3']] = $stichName;
        }
        
        $stichDefinitionen[] = [
            'name' => $stichName,
            'nummern' => array_filter([
                $row['nummer1'], 
                $row['nummer2'], 
                $row['nummer3']
            ])
        ];
    }
} catch (Exception $e) {
    error_log("Fehler beim Laden der Stich-Definitionen: " . $e->getMessage());
}
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
    
    .spinner-border {
        width: 3rem;
        height: 3rem;
        border: 0.3rem solid #f3f3f3;
        border-top: 0.3rem solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .stich-preview-card {
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
        background: #fff;
    }
    
    .stich-preview-card h5 {
        color: #007bff;
        margin-bottom: 0.5rem;
    }
    
    .stich-preview-card .badge {
        margin-right: 0.5rem;
    }
    
    .preview-table {
        font-size: 0.9rem;
    }
    
    .preview-table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }
    
    .btn-generate-pdf {
        padding: 0.75rem 1.5rem;
        font-size: 1.1rem;
    }
    
    /* Success Modal Styling */
    #successModal .modal-content {
        border-radius: 1rem;
        border: none;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }
    
    #successModal .modal-body {
        padding: 2rem;
    }
    
    #successModal .modal-footer {
        padding: 1rem 2rem 2rem;
        gap: 1rem;
    }
    
    #successModal .btn-lg {
        padding: 0.75rem 1.5rem;
        font-size: 1.1rem;
    }
</style>

<!-- Zielscheiben-Ausdruck Workflow -->
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-9 col-lg-10 col-md-12 col-12 ps-0">
            <div class="main-content-wrapper">
                <!-- Header -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-bullseye me-2"></i>
                            Zielscheiben-Ausdruck
                        </h2>
                        <p class="text-muted mt-1">CSV hochladen → Vorschau → PDF generieren</p>
                    </div>
                </div>
                
                <div class="content-background">
                    
                    <!-- Phase 1: Upload -->
                    <div id="phase1" class="workflow-phase active">
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Unterstützte Stiche:</strong> 
                            <?php 
                            $stichInfo = [];
                            foreach ($stichDefinitionen as $def) {
                                $nummern = implode(', ', $def['nummern']);
                                $stichInfo[] = $def['name'] . ' (' . $nummern . ')';
                            }
                            echo implode(' • ', $stichInfo);
                            ?>
                        </div>
                        
                        <div class="upload-area" id="uploadArea">
                            <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                            <h4 class="mt-3">CSV-Datei hier ablegen oder klicken zum Auswählen</h4>
                            <p class="text-muted mb-0">Unterstützte Formate: .csv</p>
                        </div>
                        
                        <!-- File Input AUSSERHALB des uploadArea! -->
                        <input type="file" id="fileInput" accept=".csv" style="display: none;">
                    </div>
                    
                    <!-- Phase 2: Preview & Generate -->
                    <div id="phase2" class="workflow-phase" style="display: none;">
                        <!-- File Info -->
                        <div id="fileInfo" class="alert alert-success mb-3" style="display: none;">
                            <!-- Wird dynamisch gefüllt -->
                        </div>
                        
                        <!-- Schützen-Auswahl -->
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-person me-2"></i>
                                    Schützen-Informationen
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="schuetzenName" class="form-label">
                                            <strong>Name des Schützen:</strong>
                                        </label>
                                        <input type="text" class="form-control" id="schuetzenName" 
                                               placeholder="z.B. Max Mustermann">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="jahrSelect" class="form-label">
                                            <strong>Jahr:</strong>
                                        </label>
                                        <select id="jahrSelect" class="form-select">
                                            <?php
                                            $currentYear = date('Y');
                                            for ($year = 2024; $year <= $currentYear + 1; $year++) {
                                                echo '<option value="' . $year . '"' .
                                                     ($year == $currentYear ? ' selected' : '') . '>' .
                                                     $year . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="bi bi-lightbulb me-1"></i>
                                        Der Name wird auf dem PDF angezeigt. Optional.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stiche Preview Container -->
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-eye me-2"></i>
                                    Gefundene Stiche
                                </h5>
                            </div>
                            <div class="card-body" id="stichePreviewContainer">
                                <!-- Wird dynamisch gefüllt -->
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="resetUpload()">
                                <i class="bi bi-arrow-left me-2"></i>Zurück
                            </button>
                            <button type="button" class="btn btn-success btn-generate-pdf" id="generatePdfBtn" 
                                    onclick="generatePDF()" disabled>
                                <i class="bi bi-file-earmark-pdf me-2"></i>PDF Generieren
                            </button>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="loading-spinner">
        <div class="spinner-border" role="status"></div>
        <h4 class="mt-3">PDF wird generiert...</h4>
        <p class="text-muted">Bitte warten, dies kann einen Moment dauern.</p>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="successModalBody">
                <!-- Wird dynamisch gefüllt -->
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-success btn-lg" id="downloadPdfBtn">
                    <i class="bi bi-download me-2"></i>PDF Herunterladen
                </button>
                <button type="button" class="btn btn-outline-primary btn-lg" data-bs-dismiss="modal" onclick="resetUpload();">
                    <i class="bi bi-arrow-clockwise me-2"></i>Neue CSV laden
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSRF Token und Stich-Definitionen für JavaScript -->
<script>
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
const STICH_DEFINITIONEN = <?php echo json_encode($stichDefinitionen); ?>;
const PROGRAMM_NUMMER_MAPPING = <?php echo json_encode($programmNummerMapping); ?>;

console.log('[TARGETPRINT] Geladene Stich-Definitionen:', STICH_DEFINITIONEN);
console.log('[TARGETPRINT] Programmnummer-Mapping:', PROGRAMM_NUMMER_MAPPING);
</script>

<!-- jQuery einbinden falls nicht vorhanden -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- JavaScript Module einbinden -->
<script src="endsch_targetprint/targetprint_handler.js?v=<?php echo time(); ?>"></script>

<script>
// Globale Variable für geparste Daten
let parsedData = null;

// Initialisierung
$(document).ready(function() {
    console.log('[TARGETPRINT] Initializing Zielscheiben-Ausdruck');
    
    // Upload Area Click Handler - mit e.stopPropagation()
    $('#uploadArea').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#fileInput').trigger('click');
    });
    
    // File Input Change Handler
    $('#fileInput').on('change', function(e) {
        e.stopPropagation();
        const file = e.target.files[0];
        if (file) {
            handleFileUpload(file);
        }
    });
    
    // Drag & Drop Handlers
    $('#uploadArea')
        .on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        })
        .on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        })
        .on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                handleFileUpload(files[0]);
            }
        });
});

// File Upload Handler
function handleFileUpload(file) {
    console.log('[TARGETPRINT] File uploaded:', file.name);
    
    // Validierung
    if (!file.name.endsWith('.csv')) {
        msvToast('Bitte nur CSV-Dateien hochladen', 'error');
        return;
    }
    
    // Datei lesen
    const reader = new FileReader();
    reader.onload = function(e) {
        const content = e.target.result;
        parseCSV(content, file.name);
    };
    reader.onerror = function() {
        msvToast('Fehler beim Lesen der Datei', 'error');
    };
    reader.readAsText(file);
}

// CSV Parser - Flexibel für alle DB-definierten Stiche
function parseCSV(content, filename) {
    console.log('[TARGETPRINT] Parsing CSV...');
    console.log('[TARGETPRINT] Verwende Programmnummer-Mapping:', PROGRAMM_NUMMER_MAPPING);
    
    try {
        const zeilen = content.split('\n');
        const alleStiche = [];
        let aktuellerStich = null;
        
        for (let i = 0; i < zeilen.length; i++) {
            const zeile = zeilen[i].trim();
            if (!zeile) continue;
            
            const teile = zeile.split(';');
            
            // Prüfe ob Header-Zeile (Programmnummer;Name;Datum;...)
            if (teile.length >= 3 && teile[2].match(/\d{2}\.\d{2}\.\d{4}/)) {
                // Aktuellen Stich speichern falls vorhanden (nur wenn Schüsse vorhanden)
                if (aktuellerStich !== null && aktuellerStich.schuesse.length > 0) {
                    alleStiche.push(aktuellerStich);
                }
                
                const programmNummer = teile[0].trim();
                
                // Prüfe ob Programmnummer in DB definiert ist
                if (PROGRAMM_NUMMER_MAPPING.hasOwnProperty(programmNummer)) {
                    const stichNameAusDB = PROGRAMM_NUMMER_MAPPING[programmNummer];
                    const stichNameAusCSV = teile[1].trim();
                    
                    aktuellerStich = {
                        programmNummer: programmNummer,
                        stichName: stichNameAusDB, // Name aus DB verwenden
                        stichNameCSV: stichNameAusCSV, // Original CSV-Name für Referenz
                        schuesse: [],
                        schussNummer: 1,
                        passe: null,
                        datum: teile[2].trim()
                    };
                    
                    console.log(`[TARGETPRINT] Neuer Stich erkannt: ${programmNummer} - ${stichNameAusDB}`);
                } else {
                    // Programmnummer nicht in DB definiert - trotzdem verarbeiten
                    console.warn(`[TARGETPRINT] Warnung: Programmnummer ${programmNummer} nicht in DB definiert`);
                    aktuellerStich = {
                        programmNummer: programmNummer,
                        stichName: teile[1].trim() || `Programm ${programmNummer}`,
                        stichNameCSV: teile[1].trim(),
                        schuesse: [],
                        schussNummer: 1,
                        passe: null,
                        datum: teile[2].trim()
                    };
                }
                continue;
            }
            
            // Überschriften-Zeile überspringen
            if (zeile.startsWith('Nr;Wettkampfschuss') || zeile.startsWith('Nr;')) {
                continue;
            }
            
            // Schuss-Daten parsen (wenn ein Stich aktiv ist)
            if (aktuellerStich !== null && teile.length >= 9) {
                try {
                    const wettkampfschuss = parseInt(teile[1]) || 0;
                    
                    // Wettkampfschüsse bevorzugen, aber auch andere wenn wettkampfschuss = 0
                    // (manche CSV haben eventuell nicht die Wettkampf-Spalte korrekt gefüllt)
                    const istWettkampf = wettkampfschuss === 1;
                    
                    // Wenn Wettkampfschuss-Info vorhanden ist, nur diese nehmen
                    // Wenn keine Info vorhanden (0), trotzdem hinzufügen falls erste Schüsse
                    if (istWettkampf || aktuellerStich.schuesse.length === 0) {
                        const passe = parseInt(teile[2]) || 0;
                        let wertung = parseInt(teile[3]) || 0;
                        let hunderter = parseInt(teile[4]) || 0;
                        
                        // Passe-Nummer speichern
                        if (aktuellerStich.passe === null && passe > 0) {
                            aktuellerStich.passe = passe;
                        }
                        
                        // Prüfe ob Kunst (523) oder Glück (524) - diese haben KEIN 100er-System
                        const istKunstOderGlueck = (aktuellerStich.programmNummer === '523' || 
                                                     aktuellerStich.programmNummer === '524');
                        
                        // Wertung umrechnen falls > 10 (100er-System) - ABER NICHT bei Kunst/Glück
                        if (!istKunstOderGlueck && wertung > 10) {
                            hunderter = wertung;
                            if (wertung >= 91) wertung = 10;
                            else if (wertung >= 81) wertung = 9;
                            else if (wertung >= 71) wertung = 8;
                            else if (wertung >= 61) wertung = 7;
                            else if (wertung >= 51) wertung = 6;
                            else if (wertung >= 41) wertung = 5;
                            else if (wertung >= 31) wertung = 4;
                            else if (wertung >= 21) wertung = 3;
                            else if (wertung >= 11) wertung = 2;
                            else wertung = 1;
                        }
                        
                        // X/Y Koordinaten parsen
                        const x = parseFloat(teile[7]) || 0.0;
                        const y = parseFloat(teile[8]) || 0.0;
                        
                        // Nur Schüsse mit gültigen Daten hinzufügen
                        if (x !== 0 || y !== 0 || wertung > 0) {
                            aktuellerStich.schuesse.push({
                                schuss_nr: aktuellerStich.schussNummer++,
                                wert: wertung,
                                hunderter: hunderter,
                                x: x,
                                y: y,
                                wettkampf: istWettkampf
                            });
                        }
                    }
                } catch (parseError) {
                    console.warn('[TARGETPRINT] Fehler beim Parsen von Schuss-Zeile:', parseError);
                    // Weiter machen mit nächster Zeile
                }
            }
        }
        
        // Letzten Stich speichern (nur wenn Schüsse vorhanden)
        if (aktuellerStich !== null && aktuellerStich.schuesse.length > 0) {
            alleStiche.push(aktuellerStich);
        }
        
        console.log('[TARGETPRINT] Parsed stiche:', alleStiche);
        console.log('[TARGETPRINT] Gefundene Programme:', alleStiche.map(s => `${s.programmNummer} (${s.stichName})`).join(', '));
        
        if (alleStiche.length === 0) {
            msvToast('Keine Stiche mit Schüssen gefunden', 'warning');
            return;
        }
        
        // Daten speichern
        parsedData = {
            filename: filename,
            alleStiche: alleStiche
        };
        
        // Vorschau anzeigen
        showPreview();
        
    } catch (error) {
        console.error('[TARGETPRINT] Parse error:', error);
        msvToast('Fehler beim Parsen der CSV: ' + error.message, 'error');
    }
}

// Vorschau anzeigen
function showPreview() {
    if (!parsedData) return;
    
    // File Info
    $('#fileInfo').html(`
        <i class="bi bi-file-earmark-check me-2"></i>
        <strong>Datei:</strong> ${parsedData.filename} 
        <span class="badge bg-success">${parsedData.alleStiche.length} Stiche gefunden</span>
    `).show();
    
    // Stiche Preview erstellen
    let previewHtml = '';
    parsedData.alleStiche.forEach((stich, index) => {
        const stichName = getStichName(stich.programmNummer);
        const passeTxt = stich.passe ? ` - ${stich.passe}. Passe` : '';
        
        // Statistik berechnen
        let total = 0;
        let max100er = 0;
        stich.schuesse.forEach(s => {
            total += s.wert;
            if (s.hunderter > max100er) max100er = s.hunderter;
        });
        
        previewHtml += `
            <div class="stich-preview-card">
                <h5>
                    ${stichName}${passeTxt}
                    <span class="badge bg-primary">${stich.programmNummer}</span>
                    <span class="badge bg-info">${stich.schuesse.length} Schüsse</span>
                </h5>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1">
                            <strong>Total:</strong> ${total} Punkte
                        </p>
                        <p class="mb-0">
                            <strong>100er:</strong> ${max100er}
                        </p>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">
                            Erste 3 Schüsse: 
                            ${stich.schuesse.slice(0, 3).map(s => s.wert).join(', ')}${stich.schuesse.length > 3 ? '...' : ''}
                        </small>
                    </div>
                </div>
            </div>
        `;
    });
    
    $('#stichePreviewContainer').html(previewHtml);
    
    // Phase wechseln
    $('#phase1').hide();
    $('#phase2').show();
    
    // Button aktivieren
    $('#generatePdfBtn').prop('disabled', false);
    
    msvToast('CSV erfolgreich geladen', 'success');
}

// Stich-Name aus Programmnummer (verwendet DB-Mapping)
function getStichName(programmNummer) {
    return PROGRAMM_NUMMER_MAPPING[programmNummer] || `Programm ${programmNummer}`;
}

// PDF generieren
function generatePDF() {
    if (!parsedData) {
        msvToast('Keine Daten zum Generieren', 'error');
        return;
    }
    
    const schuetzenName = $('#schuetzenName').val().trim();
    const jahr = $('#jahrSelect').val();
    
    console.log('[TARGETPRINT] Generating PDF...', {
        schuetzenName,
        jahr,
        sticheCount: parsedData.alleStiche.length
    });
    
    // Loading anzeigen
    $('#loadingOverlay').show();
    
    // AJAX Request
    $.ajax({
        url: 'endsch_targetprint/generate_pdf.php',
        method: 'POST',
        data: JSON.stringify({
            csrf_token: CSRF_TOKEN,
            alleStiche: parsedData.alleStiche,
            schuetzenName: schuetzenName,
            jahr: jahr
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            $('#loadingOverlay').hide();
            
            if (response.success && response.pdf_link) {
                msvToast('PDF erfolgreich generiert!', 'success');
                
                // Modal mit Download-Link anzeigen
                showSuccessModal(response.pdf_link, response.filename || 'Zielscheibe.pdf');
                
            } else {
                msvToast('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        },
        error: function(xhr, status, error) {
            $('#loadingOverlay').hide();
            console.error('[TARGETPRINT] AJAX Error:', error, xhr.responseText);
            msvToast('Fehler bei der PDF-Generierung: ' + error, 'error');
        }
    });
}

// Success Modal anzeigen
function showSuccessModal(pdfLink, filename) {
    // Modal-Inhalt erstellen
    $('#successModalBody').html(`
        <div class="text-center mb-3">
            <i class="bi bi-check-circle-fill" style="font-size: 4rem; color: #28a745;"></i>
        </div>
        <h5 class="text-center mb-3">PDF erfolgreich erstellt!</h5>
        <p class="text-center text-muted">${filename}</p>
    `);
    
    // Download-Link in Button einfügen
    $('#downloadPdfBtn').off('click').on('click', function() {
        window.open(pdfLink, '_blank');
    });
    
    // Modal anzeigen
    const modal = new bootstrap.Modal(document.getElementById('successModal'));
    modal.show();
}

// Upload zurücksetzen
function resetUpload() {
    $('#fileInput').val('');
    parsedData = null;
    $('#phase2').hide();
    $('#phase1').show();
    $('#schuetzenName').val('');
    $('#generatePdfBtn').prop('disabled', true);
}

</script>

<?php
include 'footer.inc.php';
?>
