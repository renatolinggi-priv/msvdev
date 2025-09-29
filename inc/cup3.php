<?php
// cup3.php - Moderne Version mit schlichtem Design
include 'dbconnect.inc.php';

// Body-Klasse für Cup3 definieren
$body_class = 'cup3-page';

// Header einbinden
include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!-- Cup3 spezifische Styles -->
<style>
:root {
    /* Schlichte Farben - nur Grautöne und dezente Akzente */
    --cup3-primary: #2c3e50;
    --cup3-secondary: #7f8c8d;
    --cup3-light-gray: #f8f9fa;
    --cup3-medium-gray: #e9ecef;
    --cup3-border-gray: #dee2e6;
    --cup3-text-muted: #6c757d;
    
    /* Subtile Schatten */
    --cup3-shadow-sm: 0 2px 4px rgba(0,0,0,0.04);
    --cup3-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --cup3-shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
    --cup3-shadow-hover: 0 12px 28px rgba(0,0,0,0.15);
    
    /* Animationen */
    --cup3-transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.cup3-page {
    background: #fafbfc;
    min-height: 100vh;
}

/* Header Section */
.cup3-header {
    background: white;
    border-bottom: 1px solid var(--cup3-border-gray);
    padding: 2rem 0;
    margin-bottom: 2rem;
    box-shadow: var(--cup3-shadow-sm);
}

.cup3-title {
    font-size: 1.75rem;
    font-weight: 600;
    color: var(--cup3-primary);
    letter-spacing: -0.5px;
    margin-bottom: 0.25rem;
}

.cup3-subtitle {
    color: var(--cup3-text-muted);
    font-size: 0.95rem;
}

/* Stats Grid */
.cup3-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.cup3-stat-card {
    background: white;
    border: 1px solid var(--cup3-border-gray);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    transition: var(--cup3-transition);
}

.cup3-stat-card:hover {
    box-shadow: var(--cup3-shadow-md);
    transform: translateY(-2px);
}

.cup3-stat-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--cup3-primary);
}

.cup3-stat-label {
    font-size: 0.75rem;
    color: var(--cup3-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.25rem;
}

/* Progress Bar */
.cup3-progress-card {
    background: white;
    border: 1px solid var(--cup3-border-gray);
    border-radius: 8px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}

.cup3-progress-bar {
    height: 4px;
    background: var(--cup3-medium-gray);
    border-radius: 2px;
    overflow: hidden;
    margin: 1rem 0;
}

.cup3-progress-fill {
    height: 100%;
    background: var(--cup3-primary);
    transition: width 0.3s ease;
}

/* Control Panel */
.cup3-control-panel {
    background: white;
    border: 1px solid var(--cup3-border-gray);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}

.cup3-control-group {
    display: flex;
    align-items: end;
    gap: 1rem;
    flex-wrap: wrap;
}

.cup3-form-group {
    flex: 1;
    min-width: 150px;
}

.cup3-form-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--cup3-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
    display: block;
}

.cup3-form-control {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--cup3-border-gray);
    border-radius: 6px;
    font-size: 0.875rem;
    transition: var(--cup3-transition);
    width: 100%;
}

.cup3-form-control:focus {
    outline: none;
    border-color: var(--cup3-primary);
    box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1);
}

/* Buttons */
.cup3-btn {
    padding: 0.625rem 1.25rem;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.875rem;
    border: 1px solid var(--cup3-border-gray);
    background: white;
    color: var(--cup3-primary);
    transition: var(--cup3-transition);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.cup3-btn:hover {
    background: var(--cup3-light-gray);
    border-color: var(--cup3-secondary);
    transform: translateY(-1px);
    box-shadow: var(--cup3-shadow-sm);
}

.cup3-btn-primary {
    background: var(--cup3-primary);
    color: white;
    border-color: var(--cup3-primary);
}

.cup3-btn-primary:hover {
    background: #34495e;
    border-color: #34495e;
}

.cup3-btn-danger {
    color: #e74c3c;
    border-color: #e74c3c;
}

.cup3-btn-danger:hover {
    background: #fef5f5;
}

.cup3-btn-success {
    color: #27ae60;
    border-color: #27ae60;
}

.cup3-btn-success:hover {
    background: #f0fdf4;
}

/* Action Bar */
.cup3-action-bar {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: white;
    border: 1px solid var(--cup3-border-gray);
    border-radius: 8px;
}

/* Drag Container */
.cup3-drag-container {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Participants Panel */
.cup3-participants-panel {
    background: white;
    border: 1px solid var(--cup3-border-gray);
    border-radius: 12px;
    padding: 1.25rem;
    height: fit-content;
    max-height: 600px;
    overflow-y: auto;
}

.cup3-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--cup3-medium-gray);
}

.cup3-panel-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--cup3-primary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.cup3-counter-badge {
    background: var(--cup3-light-gray);
    color: var(--cup3-text-muted);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Participant Items */
.cup3-participant-item {
    background: var(--cup3-light-gray);
    border: 1px solid var(--cup3-border-gray);
    border-radius: 6px;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    cursor: grab;
    transition: transform 0.1s ease, box-shadow 0.1s ease;  /* Nur kritische Properties */
    font-size: 0.875rem;
    will-change: transform;  /* Browser-Optimierung */
    -webkit-backface-visibility: hidden;  /* Verhindert Flackern */
    backface-visibility: hidden;
}

.cup3-participant-item:hover {
    background: white;
    box-shadow: var(--cup3-shadow-sm);
    transform: translateX(4px);
}

.cup3-participant-item.ui-draggable-dragging {
    cursor: grabbing !important;
    opacity: 0.8;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    z-index: 10000;
    pointer-events: none;  /* Verhindert Event-Konflikte */
}

/* Helper Clone Optimization */
.ui-draggable-helper {
    pointer-events: none !important;
    will-change: transform !important;
    transform: translateZ(0) !important;  /* Force GPU acceleration */
}

/* Winner Items */
.cup3-winner-item {
    background: #f0fdf4;
    border-left: 3px solid #27ae60;
}

/* Pairings Panel */
.cup3-pairings-panel {
    background: white;
    border: 1px solid var(--cup3-border-gray);
    border-radius: 12px;
    padding: 1.25rem;
}

/* Pairing Items */
.cup3-pairing-item {
    background: var(--cup3-light-gray);
    border: 1px solid var(--cup3-border-gray);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    position: relative;
}

.cup3-pairing-row {
    display: grid;
    grid-template-columns: 1fr 80px 30px 80px 1fr;
    gap: 0.75rem;
    align-items: center;
}

.cup3-pairing-slot {
    background: white;
    border: 2px dashed var(--cup3-border-gray);
    border-radius: 6px;
    padding: 0.625rem;
    min-height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.15s ease, background 0.15s ease;  /* Nur kritische Properties */
    font-size: 0.875rem;
    will-change: border-color, background;  /* Browser-Optimierung */
}

.cup3-pairing-slot[data-id]:not([data-id=""]) {
    border-style: solid;
    background: var(--cup3-light-gray);
}

.cup3-pairing-slot.ui-droppable-hover,
.cup3-pairing-slot.drag-over {
    border-color: var(--cup3-primary);
    background: #f0f4f8;
    border-style: solid;
    /* Kein transform/scale für bessere Performance */
}

.cup3-placeholder-text {
    color: #adb5bd;
    font-size: 0.8125rem;
}

.cup3-result-input {
    padding: 0.375rem;
    border: 1px solid var(--cup3-border-gray);
    border-radius: 4px;
    text-align: center;
    font-size: 0.875rem;
    transition: var(--cup3-transition);
}

.cup3-result-input:focus {
    outline: none;
    border-color: var(--cup3-primary);
}

.cup3-vs-separator {
    text-align: center;
    color: var(--cup3-text-muted);
    font-weight: 600;
    font-size: 0.75rem;
}

/* Remove Button */
.cup3-btn-remove {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    width: 24px;
    height: 24px;
    border-radius: 4px;
    border: 1px solid var(--cup3-border-gray);
    background: white;
    color: var(--cup3-text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--cup3-transition);
    font-size: 1rem;
    line-height: 1;
}

.cup3-btn-remove:hover {
    border-color: #e74c3c;
    color: #e74c3c;
    background: #fef5f5;
}

/* Manual Winner Button */
.cup3-btn-manual {
    position: absolute;
    top: 0.5rem;
    right: 35px;
    padding: 0.25rem 0.5rem;
    font-size: 0.7rem;
    background: #27ae60;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: var(--cup3-transition);
}

.cup3-btn-manual:hover {
    background: #229954;
}

/* Manual Winner Badge */
.cup3-manual-badge {
    background: #ff9800;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    margin-left: 5px;
    display: inline-block;
}

/* Final Round */
.cup3-final-round {
    background: linear-gradient(to right, #fffbf0, white);
    border-left: 4px solid #f39c12;
}

/* Standcup Final */
.cup3-standcup-container {
    background: white;
    border: 1px solid var(--cup3-border-gray);
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1.5rem;
}

/* Empty State */
.cup3-empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--cup3-text-muted);
}

.cup3-empty-icon {
    font-size: 2.5rem;
    color: var(--cup3-border-gray);
    margin-bottom: 1rem;
}

.cup3-empty-title {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--cup3-secondary);
    margin-bottom: 0.5rem;
}

.cup3-empty-text {
    font-size: 0.8125rem;
    color: var(--cup3-text-muted);
}

/* Toast */
.cup3-toast {
    position: fixed;
    top: 80px;
    right: 20px;
    background: white;
    border: 1px solid var(--cup3-border-gray);
    border-radius: 8px;
    padding: 1rem 1.5rem;
    box-shadow: var(--cup3-shadow-lg);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    z-index: 2000;
    animation: cup3SlideIn 0.3s ease;
}

@keyframes cup3SlideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .cup3-drag-container {
        grid-template-columns: 1fr;
    }
    
    .cup3-control-group {
        flex-direction: column;
    }
    
    .cup3-control-group .cup3-form-group {
        width: 100%;
    }
    
    .cup3-pairing-row {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    
    .cup3-action-bar {
        flex-direction: column;
    }
    
    .cup3-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Loading Spinner */
.cup3-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid var(--cup3-border-gray);
    border-radius: 50%;
    border-top-color: var(--cup3-primary);
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<!-- jQuery UI von cdn.jsdelivr.net laden (CSP-konform) -->
<script src="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/themes/base/jquery-ui.min.css">

<!-- Header Section -->
<div class="cup3-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="cup3-title">
                    <i class="bi bi-trophy" style="opacity: 0.7;"></i> CUP Resultaterfassung
                </h1>
                <p class="cup3-subtitle">Moderne Wettkampfverwaltung - Jahr <span id="currentYearDisplay">2024</span></p>
            </div>
            <div class="col-md-4 text-end">
                <div id="message"></div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Stats Overview -->
    <div class="cup3-stats-grid">
        <div class="cup3-stat-card">
            <div class="cup3-stat-value" id="statsParticipants">0</div>
            <div class="cup3-stat-label">Teilnehmer</div>
        </div>
        <div class="cup3-stat-card">
            <div class="cup3-stat-value" id="statsPairings">0</div>
            <div class="cup3-stat-label">Paarungen</div>
        </div>
        <div class="cup3-stat-card">
            <div class="cup3-stat-value" id="statsProgress">0%</div>
            <div class="cup3-stat-label">Abgeschlossen</div>
        </div>
        <div class="cup3-stat-card">
            <div class="cup3-stat-value" id="statsRounds">0</div>
            <div class="cup3-stat-label">Runden</div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="cup3-progress-card">
        <div class="d-flex justify-content-between align-items-center">
            <span class="cup3-form-label mb-0">Fortschritt</span>
            <span class="text-muted" style="font-size: 0.8125rem;" id="progressText">0 von 0 Paarungen</span>
        </div>
        <div class="cup3-progress-bar">
            <div class="cup3-progress-fill" id="progressBar" style="width: 0%;"></div>
        </div>
    </div>

    <!-- Control Panel -->
    <div class="cup3-control-panel">
        <div class="cup3-control-group">
            <div class="cup3-form-group">
                <label class="cup3-form-label" for="yearSelect">Jahr</label>
                <select class="cup3-form-control" id="yearSelect">
                    <!-- Options werden per JavaScript eingefügt -->
                </select>
            </div>
            <div class="cup3-form-group">
                <label class="cup3-form-label" for="pair-count">Anzahl Paarungen</label>
                <input type="number" class="cup3-form-control" id="pair-count" min="1" max="10" value="4">
            </div>
            <div class="cup3-form-group">
                <label class="cup3-form-label" for="pair-size">Paarungsgröße</label>
                <select class="cup3-form-control" id="pair-size">
                    <option value="2">2er Paarung</option>
                    <option value="3">3er Paarung</option>
                </select>
            </div>
            <button class="cup3-btn cup3-btn-success" id="generate-pairs">
                <i class="bi bi-plus-circle"></i> Generieren
            </button>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="cup3-action-bar">
        <button class="cup3-btn cup3-btn-primary" id="save-pairs">
            <i class="bi bi-save"></i> Speichern
        </button>
        <button class="cup3-btn" id="pdf-btn">
            <i class="bi bi-file-pdf"></i> PDF Export
        </button>
        <button class="cup3-btn" id="refresh-btn">
            <i class="bi bi-arrow-repeat"></i> Aktualisieren
        </button>
        <button class="cup3-btn cup3-btn-danger ms-auto" id="delete-btn">
            <i class="bi bi-trash"></i> Löschen
        </button>
    </div>

    <!-- Main Drag & Drop Container -->
    <div class="cup3-drag-container">
        <!-- Participants Panel -->
        <div class="cup3-participants-panel">
            <div class="cup3-panel-header">
                <h6 class="cup3-panel-title mb-0">Teilnehmer</h6>
                <span class="cup3-counter-badge" id="participantCount">0</span>
            </div>
            <div id="participant-list">
                <!-- Teilnehmer werden hier eingefügt -->
            </div>
        </div>

        <!-- Pairings Panel -->
        <div class="cup3-pairings-panel">
            <div class="cup3-panel-header">
                <h6 class="cup3-panel-title mb-0">Paarungen - Runde 1</h6>
                <span class="cup3-counter-badge" id="pairingCount">0 / 0</span>
            </div>
            <div id="pair-list">
                <!-- Paarungen werden hier eingefügt -->
            </div>
        </div>
    </div>

    <!-- Runde 2 Section -->
    <div id="round2-section" style="display:none;">
        <div class="cup3-drag-container">
            <!-- Winners Panel -->
            <div class="cup3-participants-panel">
                <div class="cup3-panel-header">
                    <h6 class="cup3-panel-title mb-0">Gewinner Runde 1</h6>
                    <span class="cup3-counter-badge" id="winnerCount">0</span>
                </div>
                <div id="winner-list">
                    <!-- Gewinner werden hier eingefügt -->
                </div>
            </div>

            <!-- Round 2 Pairings -->
            <div class="cup3-pairings-panel">
                <div class="cup3-panel-header">
                    <h6 class="cup3-panel-title mb-0">Paarungen - Runde 2</h6>
                    <span class="cup3-counter-badge" id="round2Count">0 / 0</span>
                </div>
                <div id="pair-list-round2">
                    <!-- Runde 2 Paarungen -->
                </div>
            </div>
        </div>
    </div>

    <!-- Final Section -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div id="final-section" class="cup3-pairings-panel" style="display:none;">
                <div class="cup3-panel-header">
                    <h6 class="cup3-panel-title mb-0">
                        <i class="bi bi-trophy-fill text-warning"></i> Finalrunde
                    </h6>
                </div>
                <div id="final-list">
                    <!-- Finalisten werden hier eingefügt -->
                </div>
                <div class="mt-3">
                    <button class="cup3-btn cup3-btn-primary" id="save-final">
                        <i class="bi bi-save"></i> Final speichern
                    </button>
                    <div id="pdf-link" class="mt-3"></div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div id="standcupfinal-container" class="cup3-standcup-container" style="display:none;">
                <div class="cup3-panel-header">
                    <h6 class="cup3-panel-title mb-0">
                        <i class="bi bi-award"></i> Standcup Final
                    </h6>
                </div>
                <form id="standcup-final-form">
                    <div class="mb-3">
                        <label class="cup3-form-label">MSV Wilen</label>
                        <div class="row g-2">
                            <div class="col-8">
                                <input type="text" class="cup3-form-control" id="participant1-name" 
                                       placeholder="Name eingeben" required>
                            </div>
                            <div class="col-4">
                                <input type="number" class="cup3-form-control" id="participant1-result" 
                                       placeholder="Resultat" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="cup3-form-label">SV Wollerau</label>
                        <div class="row g-2">
                            <div class="col-8">
                                <input type="text" class="cup3-form-control" id="participant2-name" 
                                       placeholder="Name eingeben" required>
                            </div>
                            <div class="col-4">
                                <input type="number" class="cup3-form-control" id="participant2-result" 
                                       placeholder="Resultat" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="cup3-form-label">SV Freienbach</label>
                        <div class="row g-2">
                            <div class="col-8">
                                <input type="text" class="cup3-form-control" id="participant3-name" 
                                       placeholder="Name eingeben" required>
                            </div>
                            <div class="col-4">
                                <input type="number" class="cup3-form-control" id="participant3-result" 
                                       placeholder="Resultat" required>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="cup3-btn cup3-btn-primary w-100" id="save-standcupfinal">
                        <i class="bi bi-save"></i> Standcup Final speichern
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" style="position: fixed; top: 80px; right: 20px; z-index: 9999;"></div>

<!-- Bestätigungs-Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>Bestätigung
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                Sind Sie sicher, dass Sie diese Aktion durchführen möchten?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-outline-danger" id="confirmActionDeleteAll" style="display:none;">
                    <i class="bi bi-check-circle me-1"></i>Bestätigen
                </button>
                <button type="button" class="btn btn-outline-danger" id="confirmActionDeleteSingle" style="display:none;">
                    <i class="bi bi-check-circle me-1"></i>Bestätigen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cup3 JavaScript -->
<script>
$(document).ready(function() {
    // Globale Variablen
    const currentYear = new Date().getFullYear();
    let pairIdToDelete = null;
    let pairItemToDelete = null;
    let totalParticipants = 0;
    let totalPairings = 0;
    let completedPairings = 0;

    // Toast-Funktion
    function showToast(message, type = 'info') {
        const typeClasses = {
            'success': 'text-success',
            'error': 'text-danger',
            'warning': 'text-warning',
            'info': 'text-muted'
        };
        
        const icons = {
            'success': 'bi-check-circle-fill',
            'error': 'bi-x-circle-fill',
            'warning': 'bi-exclamation-triangle-fill',
            'info': 'bi-info-circle-fill'
        };
        
        const toast = $('<div class="cup3-toast">')
            .html(`
                <i class="bi ${icons[type]} ${typeClasses[type]} fs-5"></i>
                <div>
                    <strong>${message}</strong>
                </div>
            `);
        
        $('#toast-container').append(toast);
        
        setTimeout(() => {
            toast.css('opacity', '0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Stats aktualisieren
    function updateStats() {
        // Teilnehmer zählen
        totalParticipants = $('#participant-list .cup3-participant-item').length;
        $('#statsParticipants').text(totalParticipants);
        $('#participantCount').text(totalParticipants);
        
        // Paarungen zählen
        totalPairings = $('.cup3-pairing-item').length;
        $('#statsPairings').text(totalPairings);
        
        // Komplettierte Paarungen zählen
        completedPairings = 0;
        $('.cup3-pairing-item').each(function() {
            const hasParticipants = $(this).find('.cup3-pairing-slot[data-id]:not([data-id=""])').length >= 2;
            const hasResults = $(this).find('.cup3-result-input').filter(function() {
                return $(this).val() !== '';
            }).length >= 2;
            
            if (hasParticipants && hasResults) {
                completedPairings++;
            }
        });
        
        // Progress berechnen
        const progress = totalPairings > 0 ? Math.round((completedPairings / totalPairings) * 100) : 0;
        $('#statsProgress').text(progress + '%');
        $('#progressBar').css('width', progress + '%');
        $('#progressText').text(`${completedPairings} von ${totalPairings} Paarungen`);
        
        // Runden zählen
        const hasRound2 = $('#round2-section').is(':visible') ? 1 : 0;
        const hasFinal = $('#final-section').is(':visible') ? 1 : 0;
        const rounds = 1 + hasRound2 + hasFinal;
        $('#statsRounds').text(rounds);
        
        // Paarung Counter
        const round1Filled = $('#pair-list .cup3-pairing-slot[data-id]:not([data-id=""])').length / 2;
        const round1Total = $('#pair-list .cup3-pairing-item').length;
        $('#pairingCount').text(`${Math.floor(round1Filled)} / ${round1Total}`);
    }

    // Jahr-Dropdown initialisieren
    function initializeYearDropdown() {
        const yearSelect = $('#yearSelect').empty();
        for (let year = 2024; year <= currentYear + 1; year++) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }
        $('#currentYearDisplay').text($('#yearSelect').val());
    }

    // Teilnehmer laden
    function loadParticipants() {
        const selectedYear = $('#yearSelect').val();
        $('#participant-list').html('<div class="text-center p-3"><span class="cup3-spinner"></span></div>');
        
        $.ajax({
            url: 'cup2/fetch_participants.php',
            method: 'GET',
            data: { year: selectedYear },
            success: function(response) {
                let participants;
                if (typeof response === 'string') {
                    try {
                        const parsed = JSON.parse(response);
                        participants = parsed.data || parsed;
                    } catch (e) {
                        console.error("JSON parse error:", e);
                        return;
                    }
                } else {
                    participants = response.data || response;
                }
                
                $('#participant-list').empty();
                participants.forEach(function(participant) {
                    $('#participant-list').append(
                        `<div class="cup3-participant-item" data-id="${participant.ID}">
                            <i class="bi bi-person"></i> ${participant.Name} ${participant.Vorname}
                        </div>`
                    );
                });
                
                initializeDraggable();
                removeUsedParticipants();
                updateStats();
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                showToast('Fehler beim Laden der Teilnehmer', 'error');
                $('#participant-list').html('<div class="cup3-empty-state"><i class="bi bi-exclamation-circle cup3-empty-icon"></i><div class="cup3-empty-text">Fehler beim Laden</div></div>');
            }
        });
    }

    // Draggable initialisieren - OPTIMIERT für Performance
    function initializeDraggable() {
        $('.cup3-participant-item, .cup3-winner-item').draggable({
            helper: 'clone',
            revert: false,  // Kein Revert = schneller
            appendTo: 'body',
            containment: false,  // Keine Containment-Checks = schneller
            distance: 2,  // Kleinere Distance = reagiert schneller
            scroll: false,
            cursor: 'grabbing',
            cursorAt: { left: 10, top: 10 },  // Cursor-Position fixieren
            zIndex: 10000,
            start: function(event, ui) {
                // Minimal CSS für Performance
                $(ui.helper).css({
                    'opacity': '0.8',
                    'box-shadow': '0 5px 15px rgba(0,0,0,0.3)'
                });
            },
            drag: function(event, ui) {
                // Throttle drag events für bessere Performance
                return true;
            }
        });
        
        initializeDroppable();
    }

    // Droppable initialisieren - OPTIMIERT
    function initializeDroppable() {
        $('.cup3-pairing-slot').each(function() {
            if (!$(this).data('ui-droppable')) {
                $(this).droppable({
                    accept: '.cup3-participant-item, .cup3-winner-item',
                    hoverClass: 'ui-droppable-hover',
                    tolerance: 'intersect',  // 'intersect' ist oft performanter als 'pointer'
                    greedy: true,  // Stoppt Event-Bubbling
                    drop: function(event, ui) {
                        const partnerId = ui.helper.data('id');
                        const partnerText = ui.helper.text().trim();
                        
                        // Direkte DOM-Manipulation für Performance
                        this.setAttribute('data-id', partnerId);
                        this.innerHTML = partnerText;
                        this.classList.add('occupied');
                        
                        // Batch DOM-Updates
                        requestAnimationFrame(() => {
                            $('#participant-list .cup3-participant-item[data-id="' + partnerId + '"]').remove();
                            $('#winner-list .cup3-winner-item[data-id="' + partnerId + '"]').remove();
                            updateStats();
                        });
                    },
                    over: function(event, ui) {
                        $(this).addClass('drag-over');
                    },
                    out: function(event, ui) {
                        $(this).removeClass('drag-over');
                    }
                });
            }
        });
    }

    // Verwendete Teilnehmer entfernen
    function removeUsedParticipants() {
        $('.cup3-pairing-slot').each(function() {
            const usedId = $(this).attr('data-id');
            if (usedId) {
                $('#participant-list .cup3-participant-item[data-id="' + usedId + '"]').remove();
                $('#winner-list .cup3-winner-item[data-id="' + usedId + '"]').remove();
            }
        });
    }

    // Paarungen generieren
    $('#generate-pairs').click(function() {
        const pairCount = parseInt($('#pair-count').val());
        const pairSize = $('#pair-size').val();
        
        if (pairCount <= 0) {
            showToast('Bitte geben Sie eine gültige Anzahl ein', 'warning');
            return;
        }
        
        generatePairSlots(pairCount, '#pair-list', pairSize);
        updateStats();
    });

    function generatePairSlots(pairCount, targetList, pairSize) {
        for (let i = 0; i < pairCount; i++) {
            let pairHtml = '<div class="cup3-pairing-item">';
            pairHtml += '<button class="cup3-btn-remove">×</button>';
            
            if (pairSize == 3) {
                // 3er Paarung
                pairHtml += `
                    <div class="cup3-pairing-row">
                        <div class="cup3-pairing-slot" data-id="">
                            <span class="cup3-placeholder-text">Teilnehmer ziehen</span>
                        </div>
                        <input type="number" class="cup3-result-input" placeholder="—">
                        <div class="cup3-vs-separator">vs</div>
                        <input type="number" class="cup3-result-input" placeholder="—">
                        <div class="cup3-pairing-slot" data-id="">
                            <span class="cup3-placeholder-text">Teilnehmer ziehen</span>
                        </div>
                    </div>
                    <div class="cup3-pairing-row mt-2">
                        <div class="cup3-pairing-slot" data-id="">
                            <span class="cup3-placeholder-text">Teilnehmer ziehen</span>
                        </div>
                        <input type="number" class="cup3-result-input" placeholder="—">
                        <div style="grid-column: span 3;"></div>
                    </div>`;
            } else {
                // 2er Paarung
                pairHtml += `
                    <div class="cup3-pairing-row">
                        <div class="cup3-pairing-slot" data-id="">
                            <span class="cup3-placeholder-text">Teilnehmer ziehen</span>
                        </div>
                        <input type="number" class="cup3-result-input" placeholder="—">
                        <div class="cup3-vs-separator">vs</div>
                        <input type="number" class="cup3-result-input" placeholder="—">
                        <div class="cup3-pairing-slot" data-id="">
                            <span class="cup3-placeholder-text">Teilnehmer ziehen</span>
                        </div>
                    </div>`;
            }
            
            pairHtml += '</div>';
            $(targetList).append(pairHtml);
        }
        
        initializeDroppable();
        bindRemovePairEvents();
    }

    // Remove Pair Events
    function bindRemovePairEvents() {
        $('.cup3-btn-remove').off('click').on('click', function() {
            pairItemToDelete = $(this).closest('.cup3-pairing-item');
            pairIdToDelete = pairItemToDelete.data('pair-id');
            
            $('#confirmActionDeleteSingle').show();
            $('#confirmActionDeleteAll').hide();
            $('#confirmModal').modal('show');
        });
    }

    // Löschen bestätigen
    $('#confirmActionDeleteSingle').click(function() {
        if (pairIdToDelete) {
            $.ajax({
                url: 'cup2/delete_pair.php',
                method: 'POST',
                data: { pair_id: pairIdToDelete },
                success: function(response) {
                    loadSavedPairs(1, '#pair-list', function() {
                        loadSavedPairs(2, '#pair-list-round2', function() {
                            loadWinnersForRound2();
                        });
                    });
                    showToast('Paarung erfolgreich gelöscht', 'success');
                },
                error: function(xhr, status, error) {
                    showToast('Fehler beim Löschen der Paarung', 'error');
                }
            });
        } else if (pairItemToDelete) {
            pairItemToDelete.remove();
            updateStats();
            showToast('Paarung entfernt', 'success');
        }
        
        pairIdToDelete = null;
        pairItemToDelete = null;
        $('#confirmModal').modal('hide');
    });

    // Alle löschen
    $('#delete-btn').click(function() {
        $('#confirmActionDeleteAll').show();
        $('#confirmActionDeleteSingle').hide();
        $('#confirmModal').modal('show');
    });

    $('#confirmActionDeleteAll').click(function() {
        $.ajax({
            url: 'cup2/delete_cup.php',
            method: 'POST',
            success: function(response) {
                showToast('Alle Resultate gelöscht', 'success');
                setTimeout(() => location.reload(), 1500);
            },
            error: function(xhr, status, error) {
                showToast('Fehler beim Löschen', 'error');
            }
        });
        $('#confirmModal').modal('hide');
    });

    // Paarungen speichern
    $('#save-pairs, #save-final').click(function() {
        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="cup3-spinner"></span> Speichere...');
        
        const pairsRound1 = [];
        const pairsRound2 = [];
        const finalResults = [];
        
        // Runde 1 sammeln
        $('#pair-list .cup3-pairing-item').each(function() {
            const pair = [];
            $(this).find('.cup3-pairing-slot').each(function() {
                const id = $(this).attr('data-id');
                if (id) pair.push(id);
            });
            
            if (pair.length >= 2) {
                $(this).find('.cup3-result-input').each(function() {
                    pair.push($(this).val() || null);
                });
                // Für Cup3 vereinfacht - keine Low-Shot Felder
                pair.push(0, 0); // Platzhalter für Low-Shot Werte
                if (pair.length > 6) pair.push(0); // Für 3er Paarung
                pairsRound1.push(pair);
            }
        });
        
        // Runde 2 sammeln
        $('#pair-list-round2 .cup3-pairing-item').each(function() {
            const pair = [];
            $(this).find('.cup3-pairing-slot').each(function() {
                const id = $(this).attr('data-id');
                if (id) pair.push(id);
            });
            
            if (pair.length >= 2) {
                $(this).find('.cup3-result-input').each(function() {
                    pair.push($(this).val() || null);
                });
                pair.push(0, 0);
                if (pair.length > 6) pair.push(0);
                pairsRound2.push(pair);
            }
        });
        
        // Final sammeln
        $('#final-list .cup3-final-item').each(function() {
            const finalResult = [];
            const id = $(this).find('.cup3-final-partner').attr('data-id');
            const result = $(this).find('.cup3-final-result').val();
            
            if (id && result) {
                finalResult.push(id, result, 0); // 0 für Low-Shot
                finalResults.push(finalResult);
            }
        });
        
        const selectedYear = $('#yearSelect').val();
        const promises = [];
        
        if (pairsRound1.length > 0) {
            promises.push(
                $.ajax({
                    url: 'cup2/save_pairs.php',
                    method: 'POST',
                    data: {
                        pairs: JSON.stringify(pairsRound1),
                        year: selectedYear,
                        round: 1
                    }
                })
            );
        }
        
        if (pairsRound2.length > 0) {
            promises.push(
                $.ajax({
                    url: 'cup2/save_pairs.php',
                    method: 'POST',
                    data: {
                        pairs: JSON.stringify(pairsRound2),
                        year: selectedYear,
                        round: 2
                    }
                })
            );
        }
        
        if (finalResults.length > 0) {
            promises.push(
                $.ajax({
                    url: 'cup2/save_finalresults.php',
                    method: 'POST',
                    data: {
                        pairs: JSON.stringify(finalResults),
                        year: selectedYear
                    }
                })
            );
        }
        
        $.when.apply($, promises).done(function() {
            showToast('Erfolgreich gespeichert!', 'success');
            $btn.prop('disabled', false).html(originalHtml);
            setTimeout(() => location.reload(), 1500);
        }).fail(function() {
            showToast('Fehler beim Speichern!', 'error');
            $btn.prop('disabled', false).html(originalHtml);
        });
    });

    // Gespeicherte Paarungen laden
    function loadSavedPairs(round, targetList, callback) {
        const selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'cup2/fetch_pairs.php',
            method: 'GET',
            data: {
                round: round,
                year: selectedYear
            },
            success: function(data) {
                const pairs = JSON.parse(data);
                $(targetList).empty();
                
                if (round === 2 && pairs.length > 0) {
                    $('#round2-section').show();
                }
                
                pairs.forEach(function(pair) {
                    let pairHtml = `<div class="cup3-pairing-item" data-pair-id="${pair.ID}">
                        <button class="cup3-btn-remove">×</button>`;
                    
                    if (pair.ManualWinner) {
                        pairHtml += '<button class="cup3-btn-manual">Nachrücker entfernen</button>';
                    } else {
                        pairHtml += '<button class="cup3-btn-manual">Verlierer nachrücken</button>';
                    }
                    
                    pairHtml += `
                        <div class="cup3-pairing-row">
                            <div class="cup3-pairing-slot occupied" data-id="${pair.Participant1}">
                                ${pair.Name1} ${pair.Vorname1}
                            </div>
                            <input type="number" class="cup3-result-input" value="${pair.Result1 || ''}">
                            <div class="cup3-vs-separator">vs</div>
                            <input type="number" class="cup3-result-input" value="${pair.Result2 || ''}">
                            <div class="cup3-pairing-slot occupied" data-id="${pair.Participant2}">
                                ${pair.Name2} ${pair.Vorname2}
                            </div>
                        </div>`;
                    
                    if (pair.Participant3 && pair.Participant3 !== "NULL") {
                        pairHtml += `
                            <div class="cup3-pairing-row mt-2">
                                <div class="cup3-pairing-slot occupied" data-id="${pair.Participant3}">
                                    ${pair.Name3} ${pair.Vorname3}
                                </div>
                                <input type="number" class="cup3-result-input" value="${pair.Result3 || ''}">
                                <div style="grid-column: span 3;"></div>
                            </div>`;
                    }
                    
                    if (pair.ManualWinner) {
                        pairHtml += '<span class="cup3-manual-badge" title="' + (pair.ManualWinnerReason || '') + '">Q</span>';
                    }
                    
                    pairHtml += '</div>';
                    $(targetList).append(pairHtml);
                });
                
                removeUsedParticipants();
                initializeDroppable();
                bindRemovePairEvents();
                bindManualWinnerEvents();
                updateStats();
                
                if (typeof callback === 'function') {
                    callback();
                }
            }
        });
    }

    // Gewinner für Runde 2 laden
    function loadWinnersForRound2() {
        const selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'cup2/fetch_winners.php',
            method: 'GET',
            data: { year: selectedYear },
            success: function(data) {
                const winners = JSON.parse(data);
                $('#winner-list').empty();
                
                if (winners.length > 0) {
                    $('#round2-section').show();
                    
                    winners.forEach(function(winner) {
                        $('#winner-list').append(
                            `<div class="cup3-participant-item cup3-winner-item" data-id="${winner.ID}">
                                <i class="bi bi-trophy"></i> ${winner.Name} ${winner.Vorname}
                            </div>`
                        );
                    });
                    
                    $('#winnerCount').text(winners.length);
                    initializeDraggable();
                    removeUsedParticipants();
                }
                
                loadFinalists();
            }
        });
    }

    // Finalisten laden
    function loadFinalists() {
        const selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'cup2/fetch_final_results.php',
            method: 'GET',
            data: { year: selectedYear },
            dataType: 'json',
            success: function(finalists) {
                $('#final-list').empty();
                if (!finalists.error && finalists.length > 0) {
                    finalists.forEach(function(finalist) {
                        $('#final-list').append(
                            `<div class="cup3-final-item cup3-pairing-item cup3-final-round">
                                <div class="cup3-final-partner" data-id="${finalist.ID}">
                                    <i class="bi bi-trophy-fill text-warning"></i> 
                                    ${finalist.Name} ${finalist.Vorname}
                                </div>
                                <input type="number" class="cup3-final-result cup3-result-input" 
                                       placeholder="Ergebnis" value="${finalist.Result || ''}">
                                <button class="cup3-btn-remove">×</button>
                            </div>`
                        );
                    });
                    $('#final-section').show();
                    
                    if (finalists.some(f => f.Result)) {
                        loadStandcupFinalData();
                    }
                }
                checkKatBFinalist();
            }
        });
    }

    // Manual Winner Events
    function bindManualWinnerEvents() {
        $('.cup3-btn-manual').off('click').on('click', function(e) {
            e.preventDefault();
            const $pairItem = $(this).closest('.cup3-pairing-item');
            const pairId = $pairItem.data('pair-id');
            const hasManualWinner = $pairItem.find('.cup3-manual-badge').length > 0;
            
            toggleManualWinner(pairId, $pairItem, hasManualWinner);
        });
    }

    function toggleManualWinner(pairId, $pairItem, currentlyHasManualWinner) {
        // Vereinfachte Version für Cup3
        const winnerId = currentlyHasManualWinner ? null : 1; // Dummy ID
        const reason = currentlyHasManualWinner ? '' : 'Nachrücker';
        
        $.ajax({
            url: 'cup2/set_manual_winner.php',
            method: 'POST',
            data: {
                pair_id: pairId,
                winner_id: winnerId,
                reason: reason
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(winnerId ? 'Nachrücker gesetzt!' : 'Nachrücker entfernt!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Fehler: ' + response.error, 'error');
                }
            }
        });
    }

    // PDF Export
    $('#pdf-btn').click(function(e) {
        e.preventDefault();
        const selectedYear = $('#yearSelect').val();
        
        $.ajax({
            url: 'cup2/rangcup.php',
            type: 'GET',
            data: { year: selectedYear },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#pdf-link').html(
                        `<a href="cup2/${response.pdf_link}" target="_blank" class="cup3-btn cup3-btn-success">
                            <i class="bi bi-download"></i> PDF herunterladen
                        </a>`
                    );
                    showToast('PDF erfolgreich erstellt', 'success');
                } else {
                    showToast('Fehler: ' + response.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Fehler beim Generieren des PDFs', 'error');
            }
        });
    });

    // Standcup Final speichern
    $('#save-standcupfinal').click(function() {
        const data = {
            participant1_name: $('#participant1-name').val(),
            participant1_result: $('#participant1-result').val(),
            participant2_name: $('#participant2-name').val(),
            participant2_result: $('#participant2-result').val(),
            participant3_name: $('#participant3-name').val(),
            participant3_result: $('#participant3-result').val(),
            year: $('#yearSelect').val()
        };
        
        if (!data.participant1_name || !data.participant2_name || !data.participant3_name) {
            showToast('Bitte alle Namen eingeben', 'warning');
            return;
        }
        
        if (!data.participant1_result || !data.participant2_result || !data.participant3_result) {
            showToast('Bitte alle Ergebnisse eingeben', 'warning');
            return;
        }
        
        $.ajax({
            url: 'cup2/save_standcupfinal.php',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Standcup Final gespeichert!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Fehler beim Speichern', 'error');
                }
            }
        });
    });

    // Standcup Final Daten laden
    function loadStandcupFinalData() {
        const selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'cup2/fetch_standcup_final.php',
            method: 'GET',
            data: { year: selectedYear },
            dataType: 'json',
            success: function(data) {
                if (data.length > 0) {
                    data.forEach(function(entry) {
                        if (entry.club === 'MSV Wilen') {
                            $('#participant1-name').val(entry.ParticipantName);
                            $('#participant1-result').val(entry.Result);
                        } else if (entry.club === 'SV Wollerau') {
                            $('#participant2-name').val(entry.ParticipantName);
                            $('#participant2-result').val(entry.Result);
                        } else if (entry.club === 'SV Freienbach') {
                            $('#participant3-name').val(entry.ParticipantName);
                            $('#participant3-result').val(entry.Result);
                        }
                    });
                    $('#standcupfinal-container').show();
                }
            }
        });
    }

    // Kat B Finalist prüfen
    function checkKatBFinalist() {
        const selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'cup2/check_katb_finalist.php',
            method: 'GET',
            data: { year: selectedYear },
            dataType: 'json',
            success: function(response) {
                if (response.has_single_katb_winner && response.katb_finalist) {
                    if ($('#final-list .cup3-final-partner[data-id="' + response.katb_finalist.ID + '"]').length === 0) {
                        $('#final-list').append(
                            `<div class="cup3-final-item cup3-pairing-item cup3-final-round">
                                <div class="cup3-final-partner" data-id="${response.katb_finalist.ID}">
                                    <i class="bi bi-trophy-fill text-warning"></i> 
                                    ${response.katb_finalist.Name} ${response.katb_finalist.Vorname}
                                    <span class="cup3-manual-badge">Kat. B</span>
                                </div>
                                <input type="number" class="cup3-final-result cup3-result-input" 
                                       value="${response.katb_finalist.Result || ''}">
                                <button class="cup3-btn-remove">×</button>
                            </div>`
                        );
                        $('#final-section').show();
                    }
                }
            }
        });
    }

    // Jahr-Dropdown Change Event
    $('#yearSelect').on('change', function() {
        $('#currentYearDisplay').text($(this).val());
        initializePage();
    });

    // Aktualisieren Button
    $('#refresh-btn').click(function() {
        location.reload();
    });

    // Seite initialisieren
    function initializePage() {
        $('#round2-section').hide();
        $('#final-section').hide();
        loadParticipants();
        loadSavedPairs(1, '#pair-list', function() {
            loadSavedPairs(2, '#pair-list-round2', function() {
                loadWinnersForRound2();
            });
        });
    }

    // Input Change Events für Stats
    $(document).on('input', '.cup3-result-input', function() {
        updateStats();
    });

    // Initialisierung
    initializeYearDropdown();
    initializePage();
});
</script>

<?php
include 'footer.inc.php';
?>