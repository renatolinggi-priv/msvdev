<?php
// cup2.php
include 'dbconnect.inc.php';

// Seitenspezifische Styles definieren (aus cup2/style.css)
$page_specific_css = '
/* Cup2 spezifische Styles */

/* Seite nicht 100% breit und linksbündig */
body > .container-fluid {
    max-width: 1200px;
    margin-left: 0;
    margin-right: auto;
    padding-left: 15px;
    padding-right: 15px;
}

/* Navbar auch linksbündig */
.navbar > .container-fluid {
    max-width: 1400px;
    margin-left: 0;
    margin-right: auto;
}

/* Nur spezifische Bereiche verkleinern */
.drag-container,
.participant-item, 
.winner-item,
.pair-item,
#participant-list,
#pair-list,
#pair-list-round2,
#winner-list,
#final-section,
#standcupfinal-container,
.pair-partner,
.result-input,
.low-shot-input,
.form-inline label {
    font-size: 12px;
}

/* Layout und Container */
.drag-container {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}

/* Teilnehmer und Winner Listen schmaler machen */
.participant, #winner-list {
    flex: 0 0 300px;
    max-width: 300px;
    margin: 0 10px;
    background-color: #fff;
    border: 1px solid #dee2e6;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Paarungen bekommen den restlichen Platz */
.pair {
    flex: 1;
    min-width: 0;
    margin: 0 10px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Teilnehmer Items */
.participant-item, .winner-item {
    background-color: #f8f9fa;
    cursor: move;
    padding: 10px;
    margin: 5px 0;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    transition: all 0.2s;
}

.participant-item:hover, .winner-item:hover {
    background-color: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}

/* Winner item special style */
.winner-item {
    background-color: #d4edda;
    border-left: 4px solid #28a745;
}

/* Paar Items */
.pair-item {
    background-color: #fff;
    border: 1px solid #dee2e6;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 4px;
    position: relative;
}

.pair-row {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.pair-row:last-child {
    margin-bottom: 0;
}

.pair-partner {
    flex: 4;
    background-color: #f8f9fa;
    padding: 10px;
    margin-right: 10px;
    border-radius: 4px;
    min-height: 40px;
    border: 2px dashed #dee2e6;
    transition: all 0.2s;
}

.pair-partner:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
}

.pair-partner.ui-droppable-hover {
    background-color: #e3f2fd;
    border-color: #2196F3;
    border-style: solid;
}

/* Input Felder */
.result-input, .low-shot-input {
    flex: 2;
    margin-right: 10px;
    max-width: 80px;
}

.result-input:last-child, .low-shot-input:last-child {
    margin-right: 0;
}

/* Buttons */
.remove-pair {
    position: absolute;
    top: 5px;
    right: 5px;
    background: #dc3545;
    color: white;
    border: none;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    padding: 0;
    transition: all 0.2s;
}

.remove-pair:hover {
    background: #c82333;
    transform: scale(1.1);
}

.btn-manual-winner {
    background: rgb(2, 185, 75) !important;
    color: white !important;
    border: none !important;
    padding: 3px 8px !important;
    border-radius: 4px !important;
    font-size: 11px !important;
    margin-left: 5px !important;
    cursor: pointer !important;
    display: inline-block !important;
    width: auto !important;
    min-width: auto !important;
    max-width: fit-content !important;
    flex: none !important;
}

.btn-manual-winner:hover {
    background: #f57c00 !important;
}

/* Badges und Divider */
.manual-winner-badge {
    background: #ff9800;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    margin-left: 5px;
    display: inline-block;
}

.pair-divider {
    margin: 15px 0;
    border: 0;
    border-top: 1px solid #dee2e6;
}

/* Final Round Styles */
.final-round-container {
    background-color: #fff3cd;
    border: 2px solid #ffeaa7;
}

.final-round-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    padding: 10px;
    background: white;
    border-radius: 4px;
}

.final-round-partner {
    flex: 4;
    font-weight: bold;
    margin-right: 10px;
}

.final-round-result, .final-round-low-shot {
    flex: 2;
    margin-right: 10px;
    max-width: 80px;
}

.remove-final-round {
    background: #dc3545;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
}

/* Standcup Final Styles */
.standcup-final-container {
    background-color: #d1ecf1;
    border: 2px solid #bee5eb;
    margin-top: 20px;
}

.standcup-final-container .form-group {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.standcup-final-container label {
    flex: 2;
    font-weight: bold;
    margin-right: 10px;
}

.standcup-final-container .final-partner {
    flex: 3;
    margin-right: 10px;
}

.standcup-final-container .result-input {
    flex: 1;
    max-width: 80px;
}

/* Animations */
@keyframes dropSuccess {
    0% {
        background-color: #c8e6c9;
    }
    100% {
        background-color: transparent;
    }
}

.drop-success {
    animation: dropSuccess 0.5s ease;
}

/* Loading */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Form inline adjustments */
.form-inline {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.form-inline .form-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-inline label {
    margin-bottom: 0;
    font-weight: 600;
}

/* Visuelles Feedback beim Drag & Drop */
.ui-draggable-dragging {
    opacity: 0.8;
    cursor: grabbing !important;
    z-index: 1000;
    transform: scale(1.05);
}

/* Round 2 section */
#round2-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 3px solid #dee2e6;
}

/* Responsive */
@media (max-width: 768px) {
    .drag-container {
        flex-direction: column;
    }
    
    .participant, .pair, #winner-list {
        margin: 10px 0;
        flex: 1;
        max-width: 100%;
    }
    
    .form-inline {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .form-inline .form-group {
        width: 100%;
        margin-bottom: 10px;
    }
}
    .participant-item,
.winner-item,
.ui-draggable-dragging {
    will-change: transform;
    transform: translateZ(0);
    backface-visibility: hidden;
}

/* Cursor-Optimierung */
.participant-item,
.winner-item {
    cursor: grab;
}

.ui-draggable-dragging {
    cursor: grabbing !important;
}



.participant-item,
.winner-item {
    will-change: transform;
    transform: translateZ(0);
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    cursor: grab;
    user-select: none;
    -webkit-user-select: none;
}

.participant-item:active,
.winner-item:active {
    cursor: grabbing;
}

/* Optimierung für Dragging */
.ui-draggable-dragging {
    cursor: grabbing !important;
    opacity: 0.9 !important;
    will-change: transform;
    transform: translateZ(0);
    box-shadow: 0 5px 15px rgba(0,0,0,0.3) !important;
}

/* Drop-Zone Hover Optimierung */
.ui-droppable-hover {
    background-color: #e3f2fd !important;
    border-color: #2196F3 !important;
    border-style: solid !important;
    transform: scale(1.02);
    transition: transform 0.15s ease;
}

/* Keine Transitions während des Draggings */
.ui-draggable-dragging,
.ui-draggable-dragging * {
    transition: none !important;
}
';

// Header einbinden
include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!-- jQuery UI von cdn.jsdelivr.net laden (CSP-konform) -->
<script src="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/themes/base/jquery-ui.min.css">

<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-auto">
            <h4 class="mb-0" style="color: var(--secondary-color);">
                <i class="bi bi-trophy me-2"></i>
                CUP Resultaterfassung
            </h4>
        </div>
        <div class="col-auto ms-auto">
            <div id="message"></div>
        </div>
    </div>

    <!-- Dropdown für Jahresauswahl -->
    <div class="row mb-3">
        <div class="col-md-3">
            <label for="yearSelect" class="form-label fw-bold">
                <i class="bi bi-calendar3 me-1"></i>Jahr auswählen:
            </label>
            <select id="yearSelect" class="form-select">
                <!-- Optionen werden per JavaScript eingefügt -->
            </select>
        </div>
    </div>

    <!-- Kontrollbuttons -->
    <div class="form-inline text-left mb-4">
        <div class="form-group">
            <label for="pair-count">Anzahl der Paarungen:</label>
            <input type="number" id="pair-count" class="form-control" min="1" max="10" style="width: 80px;">
        </div>
        <div class="form-group">
            <label for="pair-size">Paarungsgröße:</label>
            <select id="pair-size" class="form-control">
                <option value="2">Zweier-Paarung</option>
                <option value="3">Dreier-Paarung</option>
            </select>
        </div>
        <button id="generate-pairs" class="btn btn-outline-success">
            <i class="bi bi-plus-circle me-1"></i>Paarungen generieren
        </button>
        <button id="delete-btn" class="btn btn-outline-danger">
            <i class="bi bi-trash me-1"></i>Aktuelle Resultate Löschen
        </button>
        <button id="save-pairs" class="btn btn-outline-primary btn-save">
            <i class="bi bi-save me-1"></i>Paarungen speichern
        </button>
    </div>

    <!-- Drag & Drop Container -->
    <div class="drag-container">
        <div class="participant text-left">
            <h4><i class="bi bi-people me-2"></i>Teilnehmer</h4>
            <div id="participant-list" class="list-group"></div>
        </div>
        <div class="pair text-left">
            <h5><i class="bi bi-diagram-2 me-2"></i>Paarungen Runde 1</h5>
            <div id="pair-list"></div>
        </div>
    </div>

    <!-- Runde 2 Section -->
    <div id="round2-section" class="text-left" style="display:none;">
        <div class="drag-container">
            <div id="winner-list" class="participant"></div>
            <div id="pair-list-round2" class="pair"></div>
        </div>
    </div>

    <!-- Final & Standcup Container -->
    <div class="row mt-4">
        <!-- Finalrunde Container -->
        <div class="col-md-6">
            <div id="final-section" class="final-round-container pair text-left" style="display:none;">
                <h5><i class="bi bi-trophy-fill me-2"></i>Finalrunde</h5>
                <div id="final-list"></div>
                <div class="mt-3">
                    <button class="btn btn-outline-info pdf-btn">
                        <i class="bi bi-file-pdf me-1"></i>PDF
                    </button>
                    <button id="save-final" class="btn btn-outline-primary btn-save">
                        <i class="bi bi-save me-1"></i>Paarungen speichern
                    </button>
                </div>
                <div id="pdf-link" class="mt-3"></div>
            </div>
        </div>

        <!-- Standcup Final Container -->
        <div class="col-md-6">
            <div id="standcupfinal-container" class="standcup-final-container pair text-left" style="display:none;">
                <h5><i class="bi bi-award me-2"></i>Standcup Final</h5>
                <form id="standcup-final-form">
                    <div class="form-group">
                        <label for="participant1-name" id="participant1-club" class="col-form-label">MSV Wilen</label>
                        <input type="text" id="participant1-name" name="participant1-name" 
                               class="form-control final-partner" required>
                        <input type="number" id="participant1-result" name="participant1-result" 
                               class="form-control result-input" required>
                    </div>
                    <div class="form-group">
                        <label for="participant2-name" id="participant2-club" class="col-form-label">SV Wollerau</label>
                        <input type="text" id="participant2-name" name="participant2-name" 
                               class="form-control final-partner" required>
                        <input type="number" id="participant2-result" name="participant2-result" 
                               class="form-control result-input" required>
                    </div>
                    <div class="form-group">
                        <label for="participant3-name" id="participant3-club" class="col-form-label">SV Freienbach</label>
                        <input type="text" id="participant3-name" name="participant3-name" 
                               class="form-control final-partner" required>
                        <input type="number" id="participant3-result" name="participant3-result" 
                               class="form-control result-input" required>
                    </div>
                    <div class="text-center mt-3">
                        <button type="button" id="save-standcupfinal" class="btn btn-outline-primary">
                            <i class="bi bi-save me-1"></i>Standcup Final speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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

<!-- Cup2 spezifisches JavaScript -->
<script>
$(document).ready(function() {
    // Globale Variablen
    const basePath = '';
    const currentYear = new Date().getFullYear();
    var pairIdToDelete = null;
    var pairItemToDelete = null;
    var isFinalRound = false;

    // Toast Container hinzufügen falls nicht vorhanden
    if ($('#toast-container').length === 0) {
        $('body').append('<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>');
    }

    // Toast-Funktion
    function showToast(message, type = 'info') {
        const colors = {
            'success': '#28a745',
            'error': '#dc3545',
            'warning': '#ffc107',
            'info': '#6c757d'
        };
        
        const icons = {
            'success': 'bi-check-circle',
            'error': 'bi-exclamation-circle',
            'warning': 'bi-exclamation-triangle',
            'info': 'bi-info-circle'
        };
        
        const toast = $('<div>')
            .css({
                'background-color': colors[type] || colors.info,
                'color': 'white',
                'padding': '12px 20px',
                'margin-bottom': '10px',
                'border-radius': '6px',
                'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                'opacity': '0',
                'transform': 'translateX(100%)',
                'transition': 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
                'font-weight': '500',
                'display': 'flex',
                'align-items': 'center',
                'min-width': '250px'
            })
            .html(`<i class="bi ${icons[type]} me-2"></i>${message}`);
        
        $('#toast-container').append(toast);
        
        setTimeout(() => {
            toast.css({
                'opacity': '1',
                'transform': 'translateX(0)'
            });
        }, 100);
        
        setTimeout(() => {
            toast.css({
                'opacity': '0',
                'transform': 'translateX(100%)'
            });
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Legacy showMessage Funktion
    function showMessage(message, type) {
        const typeMap = {
            'danger': 'error',
            'success': 'success',
            'warning': 'warning',
            'info': 'info'
        };
        showToast(message, typeMap[type] || 'info');
    }

    // Initialisierung des Jahres-Dropdowns
    function initializeYearDropdown() {
        const yearSelect = $('#yearSelect').empty();
        for (let year = 2024; year <= currentYear + 1; year++) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }
    }

    // Teilnehmer laden
    function loadParticipants() {
        var selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'cup2/fetch_participants.php',
            method: 'GET',
            data: { year: selectedYear },
            success: function(response) {
                var participants;
                if (typeof response === 'string') {
                    try {
                        var parsed = JSON.parse(response);
                        if (parsed.success && parsed.data) {
                            participants = parsed.data;
                        } else {
                            participants = parsed;
                        }
                    } catch (e) {
                        console.error("JSON parse error:", e);
                        return;
                    }
                } else if (response.data) {
                    participants = response.data;
                } else {
                    participants = response;
                }
                
                $('#participant-list').empty();
                participants.forEach(function(participant) {
                    $('#participant-list').append(
                        '<div class="participant-item list-group-item" data-id="' + 
                        participant.ID + '">' + participant.Name + ' ' + 
                        participant.Vorname + '</div>'
                    );
                });
                
                $('.participant-item').draggable({
    helper: 'clone',
    revert: 'invalid',
    appendTo: 'body',
    containment: 'window',
    distance: 5,
    scroll: false,
    start: function(event, ui) {
        $(ui.helper).css({
            'z-index': 9999,
            'position': 'absolute',
            'pointer-events': 'none'
        });
    }
});
                
                initializeDroppable();
                removeUsedParticipants();
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                showToast('Fehler beim Laden der Teilnehmer', 'error');
            }
        });
    }

    // Droppable initialisieren
function initializeDroppable() {
    $('.pair-partner').each(function() {
        // Nur initialisieren wenn noch nicht droppable
        if (!$(this).data('ui-droppable')) {
            $(this).droppable({
                accept: '.participant-item, .winner-item',
                hoverClass: 'ui-droppable-hover',
                tolerance: 'pointer',
                drop: function(event, ui) {
                    var partnerId = ui.helper.data('id');
                    var partnerText = ui.helper.text();
                    $(this).attr('data-id', partnerId).text(partnerText);
                    
                    $(this).addClass('drop-success');
                    setTimeout(() => {
                        $(this).removeClass('drop-success');
                    }, 500);
                    
                    $('#participant-list .participant-item[data-id="' + partnerId + '"]').remove();
                    $('#winner-list .winner-item[data-id="' + partnerId + '"]').remove();
                    ui.helper.remove();
                }
            });
        }
    });
}

    // Verwendete Teilnehmer entfernen
    function removeUsedParticipants() {
        $('#pair-list .pair-partner, #pair-list-round2 .pair-partner').each(function() {
            var usedId = $(this).data('id');
            if (usedId) {
                $('#participant-list .participant-item[data-id="' + usedId + '"]').remove();
            }
        });
    }

    // Paarungen generieren
    $('#generate-pairs').click(function() {
        var pairCount = $('#pair-count').val();
        var pairSize = $('#pair-size').val();
        generatePairSlots(pairCount, '#pair-list', pairSize);
    });

    function generatePairSlots(pairCount, targetList, pairSize) {
        if (pairCount <= 0) return;
        
        for (var i = 0; i < pairCount; i++) {
            var pairHtml = '<div class="pair-item">';
            pairHtml += '<button class="remove-pair">&times;</button>';
            
            if (pairSize == 3) {
                pairHtml += '<div class="pair-row">';
                pairHtml += '<div class="pair-partner new-pair-partner" data-id=""></div>';
                pairHtml += '<input type="number" class="result-input form-control" placeholder="R" min="0" max="100">';
                pairHtml += '<input type="number" class="low-shot-input form-control" placeholder="TS" min="0" max="100">';
                pairHtml += '<div class="pair-partner new-pair-partner" data-id=""></div>';
                pairHtml += '<input type="number" class="result-input form-control" placeholder="R" min="0" max="100">';
                pairHtml += '<input type="number" class="low-shot-input form-control" placeholder="TS" min="0" max="100">';
                pairHtml += '</div>';
                pairHtml += '<div class="pair-row pair-row-third">';
                pairHtml += '<div class="pair-partner new-pair-partner" data-id=""></div>';
                pairHtml += '<input type="number" class="result-input form-control" placeholder="R" min="0" max="100">';
                pairHtml += '<input type="number" class="low-shot-input form-control" placeholder="TS" min="0" max="100">';
                pairHtml += '<div style="flex: 4;"></div>';
                pairHtml += '<div style="flex: 2;"></div>';
                pairHtml += '</div>';
            } else {
                pairHtml += '<div class="pair-row">';
                pairHtml += '<div class="pair-partner new-pair-partner" data-id=""></div>';
                pairHtml += '<input type="number" class="result-input form-control" placeholder="R" min="0" max="100">';
                pairHtml += '<input type="number" class="low-shot-input form-control" placeholder="TS" min="0" max="100">';
                pairHtml += '<div class="pair-partner new-pair-partner" data-id=""></div>';
                pairHtml += '<input type="number" class="result-input form-control" placeholder="R" min="0" max="100">';
                pairHtml += '<input type="number" class="low-shot-input form-control" placeholder="TS" min="0" max="100">';
                pairHtml += '</div>';
            }
            
            pairHtml += '</div>';
            pairHtml += '<hr class="pair-divider">';
            $(targetList).append(pairHtml);
        }
        
    $('.new-pair-partner').each(function() {
    if (!$(this).data('ui-droppable')) {
        $(this).droppable({
            accept: '.participant-item, .winner-item',
            tolerance: 'pointer',
            hoverClass: 'ui-droppable-hover',
            drop: function(event, ui) {
                var partnerId = ui.helper.data('id');
                var partnerText = ui.helper.text();
                $(this).attr('data-id', partnerId).text(partnerText);
                ui.helper.remove();
                $('#participant-list .participant-item[data-id="' + partnerId + '"]').remove();
                $('#winner-list .winner-item[data-id="' + partnerId + '"]').remove();
            }
        });
    }
});
    }

    // Event für das Löschen aller Ergebnisse
    $('#delete-btn').on('click', function(e) {
        e.preventDefault();
        $('#confirmActionDeleteAll').show();
        $('#confirmActionDeleteSingle').hide();
        $('#confirmModal').modal('show');
    });

    // Event-Handler für das Bestätigen des Löschens aller Ergebnisse
    $('#confirmActionDeleteAll').on('click', function() {
        $.ajax({
            url: 'cup2/delete_cup.php',
            method: 'POST',
            success: function(response) {
                showMessage('Alle aktuellen Resultate erfolgreich gelöscht', 'success');
                location.reload();
            },
            error: function(xhr, status, error) {
                console.error('Fehler beim Löschen der aktuellen Resultate:', error);
                showMessage('Fehler beim Löschen', 'danger');
            }
        });
        $('#confirmModal').modal('hide');
    });

    // Paarungen speichern
    $('.btn-save').click(function() {
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
        
        var pairsRound1 = [];
        var pairsRound2 = [];
        var finalResults = [];
        
        // Runde 1 sammeln
        $('#pair-list .pair-item').each(function() {
            var pair = [];
            var partners = $(this).find('.pair-partner');
            var results = $(this).find('.result-input');
            var lowshots = $(this).find('.low-shot-input');
            
            partners.each(function() {
                var id = $(this).data('id');
                if (id) pair.push(id);
            });
            
            if (pair.length >= 2) {
                results.each(function() {
                    pair.push($(this).val() || null);
                });
                lowshots.each(function() {
                    pair.push($(this).val() || null);
                });
                pairsRound1.push(pair);
            }
        });
        
        // Runde 2 sammeln
        $('#pair-list-round2 .pair-item').each(function() {
            var pair = [];
            var partners = $(this).find('.pair-partner');
            var results = $(this).find('.result-input');
            var lowshots = $(this).find('.low-shot-input');
            
            partners.each(function() {
                var id = $(this).data('id');
                if (id) pair.push(id);
            });
            
            if (pair.length >= 2) {
                results.each(function() {
                    pair.push($(this).val() || null);
                });
                lowshots.each(function() {
                    pair.push($(this).val() || null);
                });
                pairsRound2.push(pair);
            }
        });
        
        var selectedYear = $('#yearSelect').val();
        var promises = [];
        
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
        
        finalResults = saveFinalRound();
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
            showToast('Alle Daten erfolgreich gespeichert!', 'success');
            $('.btn-save').prop('disabled', false).html('<i class="bi bi-save me-1"></i>Paarungen speichern');
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        }).fail(function() {
            showToast('Fehler beim Speichern!', 'error');
            $('.btn-save').prop('disabled', false).html('<i class="bi bi-save me-1"></i>Paarungen speichern');
        });
    });

    function saveFinalRound() {
        var finalResults = [];
        $('#final-list .final-round-item').each(function() {
            var finalResult = [];
            var $partner = $(this).find('.final-round-partner');
            var $result = $(this).find('.final-round-result');
            var $lowShot = $(this).find('.final-round-low-shot');
            
            var id = $partner.data('id');
            var result = $result.val();
            var lowShot = $lowShot.val();
            
            if (id && (result || result === 0)) {
                finalResult.push(id);
                finalResult.push(result);
                finalResult.push(lowShot || 0);
                finalResults.push(finalResult);
            }
        });
        return finalResults;
    }

    // Gespeicherte Paarungen laden
    function loadSavedPairs(round, targetList, callback) {
        var selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'cup2/fetch_pairs.php',
            method: 'GET',
            data: {
                round: round,
                year: selectedYear
            },
            success: function(data) {
                var pairs = JSON.parse(data);
                $(targetList).empty();
                
                if (round === 2 && pairs.length > 0) {
                    $('#round2-section').show();
                    $(targetList).append('<h5><i class="bi bi-diagram-2 me-2"></i>Paarungen Runde 2</h5>');
                }
                
                pairs.forEach(function(pair) {
                    var pairHtml = `<div class="pair-item" data-pair-id="${pair.ID}">
                        <button class="remove-pair">&times;</button>
                        <div class="pair-row">
                            <div class="pair-partner" data-id="${pair.Participant1}">
                                ${pair.Name1} ${pair.Vorname1}
                            </div>
                            <input type="number" class="result-input form-control" placeholder="R" min="0" max="100" value="${pair.Result1 || ''}">
                            <input type="number" class="low-shot-input form-control" placeholder="TS" min="0" max="100" value="${pair.LowShot1 || ''}">
                            <div class="pair-partner" data-id="${pair.Participant2}">
                                ${pair.Name2} ${pair.Vorname2}
                            </div>
                            <input type="number" class="result-input form-control" placeholder="R" min="0" max="100" value="${pair.Result2 || ''}">
                            <input type="number" class="low-shot-input form-control" placeholder="TS" min="0" max="100" value="${pair.LowShot2 || ''}">
                        </div>`;
                    
                    if (pair.Participant3 && pair.Participant3 !== "NULL") {
                        pairHtml += `<div class="pair-row pair-row-third">
                            <div class="pair-partner" data-id="${pair.Participant3}">
                                ${pair.Name3} ${pair.Vorname3}
                            </div>
                            <input type="number" class="result-input form-control" placeholder="R" min="0" max="100" value="${pair.Result3 || ''}">
                            <input type="number" class="low-shot-input form-control" placeholder="TS" min="0" max="100" value="${pair.LowShot3 || ''}">
                            <div style="flex: 4;"></div>
                            <div style="flex: 2;"></div>
                        </div>`;
                    }
                    
                    pairHtml += '</div><hr class="pair-divider">';
                    $(targetList).append(pairHtml);
                    
                    if (pair.ManualWinner) {
                        $(targetList).find('.pair-item').last().find('.pair-row').first().append(
                            '<span class="manual-winner-badge" title="' + (pair.ManualWinnerReason || '') + '">Q</span>'
                        );
                    }
                });
                
                removeUsedParticipants();
                initializeDroppable();
                bindRemovePairEvents();
                
                if (round === 1 || round === 2) {
                    setTimeout(function() {
                        addManualWinnerButtons(round);
                    }, 100);
                }
                
                if (typeof callback === 'function') {
                    callback();
                }
            }
        });
    }

    // Gewinner für Runde 2 laden
    function loadWinnersForRound2() {
        var selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'cup2/fetch_winners.php',
            method: 'GET',
            data: { year: selectedYear },
            success: function(data) {
                var winners = JSON.parse(data);
                $('#winner-list').empty();
                
                if (winners.length > 0) {
                    $('#round2-section').show();
                    $('#winner-list').append('<h4><i class="bi bi-award me-2"></i>Gewinner der 1. Runde</h4>');
                    
                    winners.forEach(function(winner) {
                        $('#winner-list').append(
                            '<div class="winner-item list-group-item participant-item" data-id="' + 
                            winner.ID + '">' + winner.Name + ' ' + winner.Vorname + '</div>'
                        );
                    });
                    
                   $('.winner-item').draggable({
    helper: 'clone',
    revert: 'invalid',
    appendTo: 'body',
    containment: 'window',
    distance: 5,
    scroll: false,
    start: function(event, ui) {
        $(ui.helper).css({
            'z-index': 9999,
            'position': 'absolute',
            'pointer-events': 'none'
        });
    }
});
                    
                    removeUsedParticipants();
                    
                    var remainingWinners = $('#winner-list .winner-item').length;
                    if (remainingWinners > 0) {
                        var neededPairs = Math.ceil(remainingWinners / 2);
                        var emptyPairs = 0;
                        
                        $('#pair-list-round2 .pair-item').each(function() {
                            var hasParticipants = $(this).find('.pair-partner[data-id]').filter(function() {
                                return $(this).data('id');
                            }).length;
                            if (hasParticipants === 0) {
                                emptyPairs++;
                            }
                        });
                        
                        var pairsToGenerate = neededPairs - emptyPairs;
                        if (pairsToGenerate > 0) {
                            if (remainingWinners % 2 !== 0 && remainingWinners >= 3) {
                                generatePairSlots(1, '#pair-list-round2', 3);
                                pairsToGenerate--;
                            }
                            if (pairsToGenerate > 0) {
                                generatePairSlots(pairsToGenerate, '#pair-list-round2', 2);
                            }
                        }
                    }
                    
                    $('#winner-list').droppable({
                        accept: '.pair-partner',
                        drop: function(event, ui) {
                            var partnerId = ui.helper.data('id');
                            var partnerText = ui.helper.text();
                            $(this).append(
                                '<div class="winner-item list-group-item pair-partner" data-id="' + 
                                partnerId + '">' + partnerText + '</div>'
                            );
                            ui.helper.remove();
                        }
                    });
                } else {
                    $('#winner-list').hide();
                }
                
                loadFinalists();
            }
        });
    }

    // Finalisten laden
    function loadFinalists() {
        var selectedYear = $('#yearSelect').val();
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
                            `<div class="final-round-item">
                                <div class="final-round-partner" data-id="${finalist.ID}">
                                    ${finalist.Name} ${finalist.Vorname}
                                </div>
                                <input type="number" class="final-round-result form-control" placeholder="Ergebnis" min="0" max="100" value="${finalist.Result || ''}">
                                <input type="number" class="final-round-low-shot form-control" placeholder="TS" min="0" max="100" value="${finalist.LowShot || ''}">
                                <button class="remove-final-round">&times;</button>
                            </div>`
                        );
                    });
                    $('#final-section').show();
                    
                    let resultsRecorded = finalists.some(function(item) {
                        return (item.Result !== null && item.Result !== undefined && item.Result.toString().trim() !== '');
                    });
                    if (resultsRecorded) {
                        loadStandcupFinalData();
                    }
                }
                checkKatBFinalist();
            },
            error: function(xhr, status, error) {
                console.error('Fehler beim Laden der Finalergebnisse:', error);
            }
        });
    }

    // PDF Button
    $(document).on('click', '.pdf-btn', function(e) {
        e.preventDefault();
        var selectedYear = $('#yearSelect').val();
        
        $.ajax({
            url: 'cup2/rangcup.php',
            type: 'GET',
            data: { year: selectedYear },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#pdf-link').html(
                        '<a href="cup2/' + response.pdf_link + '" target="_blank" class="btn btn-success">' +
                        '<i class="bi bi-download me-1"></i>PDF herunterladen</a>'
                    );
                } else {
                    showToast('Fehler: ' + response.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showToast('Fehler beim Generieren des PDFs', 'error');
            }
        });
    });

    // Remove Pair Events binden
    function bindRemovePairEvents() {
        $('.remove-pair').off('click').on('click', function() {
            pairItemToDelete = $(this).closest('.pair-item');
            pairIdToDelete = pairItemToDelete.data('pair-id');
            isFinalRound = $(this).closest('#final-section').length > 0;
            
            $('#confirmActionDeleteSingle').show();
            $('#confirmActionDeleteAll').hide();
            $('#confirmModal').modal('show');
        });
    }

    // Einzelne Paarung löschen
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
        }
        
        pairIdToDelete = null;
        pairItemToDelete = null;
        isFinalRound = false;
        $('#confirmModal').modal('hide');
    });

    // Manuelle Gewinner Buttons
    function addManualWinnerButtons(round) {
        var targetList = round === 1 ? '#pair-list' : '#pair-list-round2';
        
        $(targetList + ' .pair-item').each(function() {
            var $pairItem = $(this);
            var pairId = $pairItem.data('pair-id');
            
            if (pairId && $pairItem.find('.btn-manual-winner').length === 0) {
                var hasManualWinner = $pairItem.find('.manual-winner-badge').length > 0;
                var $button = $('<button>')
                    .addClass('btn-manual-winner')
                    .text(hasManualWinner ? 'Nachrücker entfernen' : 'Verlierer nachrücken')
                    .click(function(e) {
                        e.preventDefault();
                        toggleManualWinner(pairId, $pairItem, round, hasManualWinner);
                    });
                $pairItem.find('.remove-pair').after($button);
            }
        });
    }

    // Manuelle Gewinner Toggle
    function toggleManualWinner(pairId, $pairItem, round, currentlyHasManualWinner) {
        var loserId = null;
        var loserName = '';
        var participants = [];
        
        var $pairRows = $pairItem.find('.pair-row');
        if ($pairRows.length === 1) {
            // 2er-Paarung
            var $partners = $pairRows.first().find('.pair-partner');
            var $results = $pairRows.first().find('.result-input');
            var $lowshots = $pairRows.first().find('.low-shot-input');
            
            if ($partners.eq(0).data('id')) {
                participants.push({
                    id: $partners.eq(0).data('id'),
                    name: $partners.eq(0).text().trim(),
                    result: parseInt($results.eq(0).val()) || 0,
                    lowshot: parseInt($lowshots.eq(0).val()) || 0
                });
            }
            
            if ($partners.eq(1).data('id')) {
                participants.push({
                    id: $partners.eq(1).data('id'),
                    name: $partners.eq(1).text().trim(),
                    result: parseInt($results.eq(1).val()) || 0,
                    lowshot: parseInt($lowshots.eq(1).val()) || 0
                });
            }
        } else {
            // 3er-Paarung
            var $firstRow = $pairRows.eq(0);
            var $partners1 = $firstRow.find('.pair-partner');
            var $results1 = $firstRow.find('.result-input');
            var $lowshots1 = $firstRow.find('.low-shot-input');
            
            if ($partners1.eq(0).data('id')) {
                participants.push({
                    id: $partners1.eq(0).data('id'),
                    name: $partners1.eq(0).text().trim(),
                    result: parseInt($results1.eq(0).val()) || 0,
                    lowshot: parseInt($lowshots1.eq(0).val()) || 0
                });
            }
            
            if ($partners1.eq(1).data('id')) {
                participants.push({
                    id: $partners1.eq(1).data('id'),
                    name: $partners1.eq(1).text().trim(),
                    result: parseInt($results1.eq(1).val()) || 0,
                    lowshot: parseInt($lowshots1.eq(1).val()) || 0
                });
            }
            
            var $secondRow = $pairRows.eq(1);
            var $partner3 = $secondRow.find('.pair-partner').first();
            var $result3 = $secondRow.find('.result-input').first();
            var $lowshot3 = $secondRow.find('.low-shot-input').first();
            
            if ($partner3.data('id')) {
                participants.push({
                    id: $partner3.data('id'),
                    name: $partner3.text().trim(),
                    result: parseInt($result3.val()) || 0,
                    lowshot: parseInt($lowshot3.val()) || 0
                });
            }
        }
        
        // Verlierer ermitteln
        if (participants.length === 2) {
            if (participants[0].result < participants[1].result ||
                (participants[0].result === participants[1].result && participants[0].lowshot < participants[1].lowshot)) {
                loserId = participants[0].id;
                loserName = participants[0].name;
            } else {
                loserId = participants[1].id;
                loserName = participants[1].name;
            }
        } else if (participants.length === 3) {
            participants.sort(function(a, b) {
                if (a.result !== b.result) return a.result - b.result;
                return a.lowshot - b.lowshot;
            });
            loserId = participants[0].id;
            loserName = participants[0].name;
        }
        
        if (!loserId) {
            showToast('Bitte erst alle Ergebnisse eintragen!', 'warning');
            return;
        }
        
        var hasResults = participants.every(p => p.result > 0);
        if (!hasResults) {
            showToast('Bitte erst alle Ergebnisse eintragen!', 'warning');
            return;
        }
        
        var winnerId = currentlyHasManualWinner ? null : loserId;
        var reason = currentlyHasManualWinner ? '' : 'Nachrücker';
        
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
                    showToast(winnerId ? loserName + ' wurde als Nachrücker gesetzt!' : 'Nachrücker wurde entfernt!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showToast('Fehler: ' + response.error, 'error');
                }
            },
            error: function() {
                showToast('Verbindungsfehler', 'error');
            }
        });
    }

    // Standcup Final speichern
    $('#save-standcupfinal').click(function() {
        const participant1Name = $('#participant1-name').val();
        const participant1Result = $('#participant1-result').val();
        const participant2Name = $('#participant2-name').val();
        const participant2Result = $('#participant2-result').val();
        const participant3Name = $('#participant3-name').val();
        const participant3Result = $('#participant3-result').val();
        
        if (!participant1Name || !participant2Name || !participant3Name) {
            showToast('Bitte alle Teilnehmernamen eingeben', 'warning');
            return;
        }
        
        if (!participant1Result || !participant2Result || !participant3Result) {
            showToast('Bitte alle Ergebnisse eingeben', 'warning');
            return;
        }
        
        $.ajax({
            url: 'cup2/save_standcupfinal.php',
            method: 'POST',
            data: {
                participant1_name: participant1Name,
                participant1_result: participant1Result,
                participant2_name: participant2Name,
                participant2_result: participant2Result,
                participant3_name: participant3Name,
                participant3_result: participant3Result,
                year: $('#yearSelect').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Standcup Final erfolgreich gespeichert!', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    if (response.errors.length > 0) {
                        showToast('Fehler: ' + response.errors.join(', '), 'error');
                    }
                }
            },
            error: function(xhr, status, error) {
                showToast('Verbindungsfehler: ' + error, 'error');
            }
        });
    });

    // Standcup Final Daten laden
    function loadStandcupFinalData() {
        var selectedYear = $('#yearSelect').val();
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
                        $('#standcupfinal-container').show();
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error("Fehler beim Laden der Standcup Final-Daten: " + error);
            }
        });
    }

    // Kat B Finalist prüfen
    function checkKatBFinalist() {
        var selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'cup2/check_katb_finalist.php',
            method: 'GET',
            data: { year: selectedYear },
            dataType: 'json',
            success: function(response) {
                if (response.has_single_katb_winner && response.katb_finalist) {
                    var resultValue = response.katb_finalist.Result !== null ? response.katb_finalist.Result : '';
                    var lowShotValue = response.katb_finalist.LowShot !== null ? response.katb_finalist.LowShot : '';
                    
                    var infoHtml = '<div class="alert alert-info" role="alert">';
                    infoHtml += '<strong>Automatische Finalqualifikation:</strong> ';
                    infoHtml += response.katb_finalist.Name + ' ' + response.katb_finalist.Vorname;
                    infoHtml += ' qualifiziert sich automatisch für das Finale.';
                    infoHtml += '</div>';
                    
                    if ($('#katb-info').length === 0) {
                        $('h4:contains("CUP Resultaterfassung")').after('<div id="katb-info">' + infoHtml + '</div>');
                    }
                    
                    if ($('#final-list .final-round-partner[data-id="' + response.katb_finalist.ID + '"]').length === 0) {
                        var newItem = $('<div class="final-round-item">' +
                            '<div class="final-round-partner" data-id="' + response.katb_finalist.ID + '">' +
                            response.katb_finalist.Name + ' ' + response.katb_finalist.Vorname +
                            '</div>' +
                            '<input type="number" class="final-round-result form-control" placeholder="Ergebnis" min="0" max="100">' +
                            '<input type="number" class="final-round-low-shot form-control" placeholder="TS" min="0" max="100">' +
                            '<button class="remove-final-round">&times;</button>' +
                            '</div>');
                        
                        $('#final-list').append(newItem);
                        newItem.find('.final-round-result').val(resultValue);
                        newItem.find('.final-round-low-shot').val(lowShotValue);
                        $('#final-section').show();
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error("Kat. B Check Error:", error);
            }
        });
    }

    // Check for Final Participants
    function checkForFinalParticipants() {
        $.ajax({
            url: 'cup2/check_final_participants.php',
            method: 'GET',
            success: function(response) {
                if (response.trim().toLowerCase() === 'true' || response.trim() === '1') {
                    $('#standcupfinal-container').show();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error checking final participants:', error);
            }
        });
    }

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

    // Jahr-Dropdown Change Event
    $('#yearSelect').on('change', function() {
        const selectedYear = $(this).val();
        initializePage();
        checkKatBFinalist();
    });

    // Initialisierung beim Laden
    initializeYearDropdown();
    initializePage();
    checkKatBFinalist();
    checkForFinalParticipants();
});
</script>

<?php
include 'footer.inc.php';
?>