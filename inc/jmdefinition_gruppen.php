<?php
// gruppenerfassung.php
include 'dbconnect.inc.php';

// Seitenspezifische Styles definieren
$page_specific_css = "
/* Gruppenerfassung-spezifische Styles */
.main-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 2rem;
    margin-bottom: 2rem;
}

.sidebar-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-left: 4px solid var(--info-color);
}

.group-creation-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    border-left: 4px solid var(--success-color);
}

.existing-groups-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-left: 4px solid var(--warning-color);
}

.card-title {
    color: var(--secondary-color);
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Drag & Drop Styling */
.draggable-member {
    cursor: move;
    margin: 3px;
    padding: 8px 12px;
    border: 2px solid #e9ecef;
    background: linear-gradient(135deg, var(--light-color) 0%, #ffffff 100%);
    border-radius: var(--border-radius);
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--dark-color);
    transition: all var(--transition-speed) ease;
    box-shadow: var(--box-shadow);
}

.draggable-member:hover {
    border-color: var(--secondary-color);
    background: linear-gradient(135deg, #e9ecef 0%, #ffffff 100%);
    transform: translateY(-1px);
    box-shadow: var(--box-shadow-hover);
}

.member-flex-item {
    width: 48%;
    box-sizing: border-box;
    margin: 1%;
}

.droppable-group {
    border: 2px dashed var(--secondary-color);
    border-radius: var(--border-radius);
    min-height: 150px;
    padding: 15px;
    background: linear-gradient(135deg, var(--light-color) 0%, #ffffff 100%);
    transition: all var(--transition-speed) ease;
}

.droppable-group.hovered {
    background: linear-gradient(135deg, #d4edda 0%, #ffffff 100%);
    border-color: var(--success-color);
    box-shadow: 0 0 20px rgba(40, 167, 69, 0.1);
}

.droppable-group p {
    color: var(--secondary-color);
    font-style: italic;
    margin: 0;
    text-align: center;
    padding: 2rem 0;
}

/* Verfügbare Mitglieder Container */
.available-members-container {
    background: var(--light-color);
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius);
    padding: 1rem;
    min-height: 150px;
    display: flex;
    flex-wrap: wrap;
    align-content: flex-start;
}

/* Bestehende Gruppen Cards */
.group-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius);
    margin-bottom: 0.75rem;
    box-shadow: var(--box-shadow);
    transition: all var(--transition-speed) ease;
}

.group-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--box-shadow-hover);
}

.group-card-header {
    background: linear-gradient(135deg, var(--light-color) 0%, #ffffff 100%);
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.group-card-body {
    padding: 0.75rem;
    display: flex;
    justify-content: between;
    align-items: center;
}

.group-card-title {
    font-weight: 600;
    color: var(--dark-color);
    margin: 0;
    font-size: 0.9rem;
}

.group-card-text {
    color: var(--secondary-color);
    font-size: 0.8rem;
    margin: 0.25rem 0 0 0;
}

.group-actions {
    display: flex;
    gap: 0.5rem;
    margin-left: auto;
}

.btn-icon {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
    border-radius: var(--border-radius);
}

/* UI Draggable States */
.ui-draggable-dragging {
    width: 180px !important;
    box-sizing: border-box;
    z-index: 9999 !important;
    transform: rotate(1deg) !important;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25) !important;
    opacity: 0.9 !important;
    border: 2px solid var(--secondary-color) !important;
    background: linear-gradient(135deg, #e9ecef 0%, #ffffff 100%) !important;
}

/* Hover-States für Drop-Zonen */
.droppable-group.ui-droppable-hover {
    background: linear-gradient(135deg, #d4edda 0%, #ffffff 100%);
    border-color: var(--success-color);
    box-shadow: 0 0 20px rgba(40, 167, 69, 0.2);
}

.available-members-container.ui-droppable-hover {
    background: linear-gradient(135deg, #fff3cd 0%, #ffffff 100%);
    border-color: var(--warning-color);
    box-shadow: 0 0 20px rgba(255, 193, 7, 0.2);
}

/* Drag-Placeholder */
.ui-sortable-placeholder {
    background: rgba(108, 117, 125, 0.1) !important;
    border: 2px dashed var(--secondary-color) !important;
    border-radius: var(--border-radius) !important;
    height: 40px !important;
    margin: 3px !important;
    visibility: visible !important;
}

/* Section Headers */
.section-header {
    background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%);
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
    border-left: 4px solid var(--secondary-color);
}

.section-title {
    color: var(--secondary-color);
    font-weight: 600;
    margin: 0;
    font-size: 0.95rem;
}

/* Responsive für Gruppenerfassung */
@media (max-width: 768px) {
    .main-card, .sidebar-card, .group-creation-card {
        padding: 1rem;
        margin: 0 0 2rem 0;
        border-radius: 0;
    }
    
    .member-flex-item {
        width: 100%;
        margin: 0.25rem 0;
    }
    
    .group-actions {
        flex-direction: column;
    }
}

/* Animationen */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.main-card, .sidebar-card, .group-creation-card, .existing-groups-card {
    animation: fadeIn 0.5s ease-out;
}
";

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-8 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-people me-2"></i>
                            Gruppenerfassung Jahresmeisterschaft
                        </h2>
                        <p class="text-muted mb-0">Teams zusammenstellen und verwalten</p>
                    </div>
                </div>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <!-- Jahr-Auswahl in eigener Card -->
                    <div class="year-selection-card">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <label for="yearSelect" class="form-label fw-bold">
                                    <i class="bi bi-calendar3 me-1"></i> Jahr auswählen:
                                </label>
                                <select id="yearSelect" class="form-select">
                                    <!-- Dynamisch per JS -->
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Nachrichten Container -->
                    <div id="message"></div>

                    <div class="row">
                        <!-- Sidebar: Anlass und bestehende Gruppen -->
                        <div class="col-lg-3">
                            <!-- Anlass auswählen -->
                            <div class="sidebar-card">
                                <h5 class="card-title">
                                    <i class="bi bi-calendar-event"></i>
                                    Anlass auswählen
                                </h5>
                                <select id="eventSelect" class="form-select">
                                    <option value="">Bitte wählen...</option>
                                </select>
                                
                                <button type="button" class="btn btn-compact-standard btn-outline-success w-100 mt-3" data-bs-toggle="modal" data-bs-target="#newAnlassModal">
                                    <i class="bi bi-plus-circle me-1"></i> Neuer Anlass
                                </button>
                            </div>

                            <!-- Bestehende Gruppen -->
                            <div class="existing-groups-card">
                                <h5 class="card-title">
                                    <i class="bi bi-collection"></i>
                                    Bestehende Gruppen
                                </h5>
                                <div id="existingGroups">
                                    <div class="text-center text-muted py-3">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Bitte zuerst einen Anlass wählen
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hauptbereich: Gruppe erstellen -->
                        <div class="col-lg-9">
                            <div class="group-creation-card">
                                <h5 class="card-title">
                                    <i class="bi bi-plus-square"></i>
                                    Neue Gruppe erstellen
                                </h5>
                                
                                <form id="newGroupForm">
                                    <input type="hidden" id="editGroupId" value="">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label for="gruppenname" class="form-label">
                                                <i class="bi bi-tag me-1"></i>Gruppenname:
                                            </label>
                                            <input type="text" id="gruppenname" class="form-control" placeholder="Name der Gruppe" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Verfügbare Mitglieder -->
                                        <div class="col-md-5">
                                            <div class="section-header">
                                                <h6 class="section-title">
                                                    <i class="bi bi-person-lines-fill me-1"></i>
                                                    Verfügbare Mitglieder
                                                </h6>
                                            </div>
                                            <div id="availableMembers" class="available-members-container">
                                                <div class="text-center text-muted w-100 py-3">
                                                    <i class="bi bi-person-plus me-2"></i>
                                                    Wähle zuerst einen Anlass
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Pfeil -->
                                        <div class="col-md-2 d-flex align-items-center justify-content-center">
                                            <div class="text-center text-muted">
                                                <i class="bi bi-arrow-right" style="font-size: 2rem;"></i>
                                                <div style="font-size: 0.8rem; margin-top: 0.5rem;">Drag & Drop</div>
                                            </div>
                                        </div>

                                        <!-- Gruppe zusammenstellen -->
                                        <div class="col-md-5">
                                            <div class="section-header">
                                                <h6 class="section-title">
                                                    <i class="bi bi-people-fill me-1"></i>
                                                    Gruppe zusammenstellen
                                                </h6>
                                            </div>
                                            <div id="groupMembers" class="droppable-group">
                                                <p class="text-muted">
                                                    <i class="bi bi-cursor me-2"></i>
                                                    Ziehe die Mitglieder hierher...
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="d-flex gap-3">
                                                <button type="submit" class="btn btn-compact-standard btn-outline-success" id="saveGruppe">
                                                    <i class="bi bi-save me-1"></i> Gruppe speichern
                                                </button>
                                                <button type="button" class="btn btn-compact-standard btn-outline-secondary" id="resetForm">
                                                    <i class="bi bi-arrow-clockwise me-1"></i> Zurücksetzen
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal für neuen Anlass -->
<div class="modal fade" id="newAnlassModal" tabindex="-1" aria-labelledby="newAnlassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newAnlassModalLabel">
                    <i class="bi bi-plus-circle"></i> Neuen Anlass hinzufügen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <label for="neueJMDefinitionBezeichnung" class="form-label">Anlassname *</label>
                        <input type="text" class="form-control mb-3" id="neueJMDefinitionBezeichnung" placeholder="z.B. Gruppenwettkampf">
                    </div>
                    <div class="col-md-6">
                        <label for="neueJMDefinitionMaxpunkte" class="form-label">Maximalpunkte</label>
                        <input type="number" class="form-control mb-3" id="neueJMDefinitionMaxpunkte" placeholder="100" min="0">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <label for="neueJMDefinitionSchiesstage" class="form-label">Schiesstage</label>
                        <textarea class="form-control mb-3" id="neueJMDefinitionSchiesstage" placeholder="z.B. Samstag 09:00-12:00..." rows="3"></textarea>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <label class="form-label">Optionen:</label>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="neueJMDefinitionStreicher">
                                    <label class="form-check-label" for="neueJMDefinitionStreicher">Streicher</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="neueJMDefinitionErweitert">
                                    <label class="form-check-label" for="neueJMDefinitionErweitert">Erweitert</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="neueJMDefinitionInfo">
                                    <label class="form-check-label" for="neueJMDefinitionInfo">Info</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="neueJMDefinitionGruppe" checked>
                                    <label class="form-check-label" for="neueJMDefinitionGruppe">Gruppenwettkampf</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-compact-standard btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-compact-standard btn-outline-success" id="jmdefinitionHinzufuegen">
                    <i class="bi bi-plus-circle me-1"></i>Anlass hinzufügen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery UI für Drag & Drop -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(document).ready(function() {
    // Toast Container hinzufügen
    if ($('#toast-container').length === 0) {
        $('body').append('<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>');
    }

    // Toast-Funktion
    function showToast(message, type = 'info') {
        const colors = {
            'success': '#28a745',
            'error': '#dc3545',
            'warning': '#ffc107',
            'info': '#6c757d'  // Geändert von blau zu grau
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
            .html(`<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>${message}`);
        
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

    ////////////////////////
    // 1) Initialisierung //
    ////////////////////////

    // a) Year-Dropdown
    function initializeYearDropdown() {
        const yearSelect = $('#yearSelect').empty();
        const currentYear = new Date().getFullYear();
        for (let year = 2024; year <= currentYear; year++) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }
    }
    initializeYearDropdown();

    /**
     * b) Drag & Drop Setup - Fehler-sicher
     */
    function setupDragDrop() {
        // Sicher prüfen und nur destroyen wenn bereits initialisiert
        try {
            $("#availableMembers .draggable-member").each(function() {
                if ($(this).hasClass('ui-draggable')) {
                    $(this).draggable("destroy");
                }
            });
            $("#groupMembers .draggable-member").each(function() {
                if ($(this).hasClass('ui-draggable')) {
                    $(this).draggable("destroy");
                }
            });
            if ($("#groupMembers").hasClass('ui-droppable')) {
                $("#groupMembers").droppable("destroy");
            }
            if ($("#availableMembers").hasClass('ui-droppable')) {
                $("#availableMembers").droppable("destroy");
            }
        } catch (e) {
            console.log("Cleanup Fehler (kann ignoriert werden):", e);
        }

        // Items in #availableMembers draggable machen
        $("#availableMembers .draggable-member").draggable({
            revert: "invalid",
            helper: "clone",
            cursor: "move",
            zIndex: 9999,
            appendTo: "body",
            distance: 5,
            delay: 100,
            opacity: 0.8,
            start: function(event, ui) {
                $(this).addClass('ui-draggable-dragging');
            },
            stop: function(event, ui) {
                $(this).removeClass('ui-draggable-dragging');
            }
        });

        // Items in #groupMembers draggable machen
        $("#groupMembers .draggable-member").draggable({
            revert: "invalid",
            helper: "clone",
            cursor: "move",
            zIndex: 9999,
            appendTo: "body",
            distance: 5,
            delay: 100,
            opacity: 0.8,
            start: function(event, ui) {
                $(this).addClass('ui-draggable-dragging');
            },
            stop: function(event, ui) {
                $(this).removeClass('ui-draggable-dragging');
            }
        });

        // Dropzone #groupMembers
        $("#groupMembers").droppable({
            accept: ".draggable-member",
            tolerance: "pointer",
            activeClass: "hovered",
            drop: function(event, ui) {
                var $member = $(ui.draggable);
                var memberID = $member.data("id");
                var memberText = $member.text();

                // Prüfen ob Member bereits in der Gruppe ist
                if ($(this).find('[data-id="' + memberID + '"]').length > 0) {
                    return;
                }

                var $newMember = $("<div></div>")
                    .addClass("draggable-member")
                    .attr("data-id", memberID)
                    .text(memberText);

                $(this).find("p.text-muted").remove();
                $(this).append($newMember);
                $member.remove();

                // Sofort draggable machen
                $newMember.draggable({
                    revert: "invalid",
                    helper: "clone",
                    cursor: "move",
                    zIndex: 9999,
                    appendTo: "body",
                    distance: 5,
                    delay: 100,
                    opacity: 0.8
                });
            }
        });

        // Dropzone #availableMembers
        $("#availableMembers").droppable({
            accept: ".draggable-member",
            tolerance: "pointer",
            activeClass: "hovered",
            drop: function(event, ui) {
                var $member = $(ui.draggable);
                var memberID = $member.data("id");
                var memberText = $member.text();
                
                // Prüfen ob Member bereits verfügbar ist
                if ($(this).find('[data-id="' + memberID + '"]').length > 0) {
                    return;
                }
                
                var $newMember = $("<div></div>")
                    .addClass("member-flex-item draggable-member")
                    .attr("data-id", memberID)
                    .text(memberText);
                
                $(this).append($newMember);
                $member.remove();
                
                // Sofort draggable machen
                $newMember.draggable({
                    revert: "invalid",
                    helper: "clone",
                    cursor: "move",
                    zIndex: 9999,
                    appendTo: "body",
                    distance: 5,
                    delay: 100,
                    opacity: 0.8
                });
            }
        });
    }

    // Einmal aufrufen
    setupDragDrop();

    // Beim Start: hole das Jahr aus dem Dropdown
    let selectedYear = $('#yearSelect').val() || new Date().getFullYear();
    loadEventDropdown(selectedYear);

    // Wenn das Jahr geändert wird: loadEventDropdown
    $('#yearSelect').on('change', function() {
        let year = $(this).val();
        loadEventDropdown(year);
    });

    ///////////////////////////
    // 2) Events & Funktionen//
    ///////////////////////////

    /**
     * loadEventDropdown => füllt #eventSelect - MIT DEBUG
     */
    function loadEventDropdown(year) {
        console.log("Loading events for year:", year); // DEBUG
        $.ajax({
            url: 'jmdefinition/load_jmdefinition_gruppen.php',
            method: 'GET',
            data: { year: year },
            dataType: 'json',
            success: function(data) {
                console.log("Received events data:", data); // DEBUG
                var eventSelect = $('#eventSelect').empty();
                if (data && data.length > 0) {
                    eventSelect.append($('<option></option>').val('').text('Bitte auswählen'));
                    data.forEach(function(ev) {
                        eventSelect.append($('<option></option>').val(ev.ID).text(ev.Bezeichnung));
                    });
                    showToast('Anlässe erfolgreich geladen', 'success');
                } else {
                    eventSelect.append($('<option></option>').val('').text('Keine Anlässe gefunden'));
                    showToast('Keine Gruppen-Anlässe gefunden', 'warning');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", xhr.responseText); // DEBUG
                showToast("Fehler beim Laden der Anlässe: " + error, 'error');
            }
        });
    }

    /**
     * loadExistingGroups => füllt #existingGroups
     */
    function loadExistingGroups(eventID, jahr) {
        $('#existingGroups').html(`
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                Lade Gruppen...
            </div>
        `);
        
        $.ajax({
            url: 'jmdefinition/load_gruppen.php',
            method: 'GET',
            data: { eventID: eventID, jahr: jahr },
            dataType: 'json',
            success: function(data) {
                let container = $("#existingGroups").empty();
                if (data && data.length > 0) {
                    data.forEach(function(group) {
                        let card = $(`
                            <div class="group-card" data-groupid="${group.ID}">
                                <div class="group-card-body">
                                    <div>
                                        <h6 class="group-card-title">${group.Gruppenname}</h6>
                                        <p class="group-card-text">Mitglieder: ${group.Mitglieder}</p>
                                    </div>
                                    <div class="group-actions">
                                        <button class="btn btn-outline-primary btn-icon edit-group" title="Gruppe bearbeiten">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-outline-danger btn-icon delete-group" title="Gruppe löschen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `);

                        // Event Handler
                        card.find('.edit-group').on('click', function() {
                            editGroup(group.ID);
                        });

                        card.find('.delete-group').on('click', function() {
                            deleteGroup(group.ID);
                        });

                        container.append(card);
                    });
                    showToast('Gruppen erfolgreich geladen', 'success');
                } else {
                    container.html(`
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-info-circle me-2"></i>
                            Für diesen Anlass gibt es noch keine Gruppen.
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                $("#existingGroups").html(`
                    <div class="text-center text-danger py-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Fehler beim Laden der Gruppen
                    </div>
                `);
                showToast("Fehler beim Laden der Gruppen: " + error, 'error');
            }
        });
    }

    /**
     * loadAvailableMembers => füllt #availableMembers - KORRIGIERT
     */
    function loadAvailableMembers(eventID, jahr) {
        $('#availableMembers').html(`
            <div class="text-center text-muted w-100 py-3">
                <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                Lade Mitglieder...
            </div>
        `);
        
        $.ajax({
            url: 'jmdefinition/load_gruppen_members.php',  // KORRIGIERT: richtige Datei
            method: 'GET',
            data: { eventID: eventID, jahr: jahr },
            dataType: 'json',
            success: function(data) {
                let availableContainer = $("#availableMembers").empty();
                if (data.length > 0) {
                    data.forEach(function(member) {
                        let $member = $("<div></div>")
                            .addClass("member-flex-item draggable-member")
                            .attr("data-id", member.ID)
                            .text(member.Name + " " + member.Vorname);
                        availableContainer.append($member);
                    });
                    setupDragDrop();
                    showToast('Mitglieder erfolgreich geladen', 'success');
                } else {
                    availableContainer.html(`
                        <div class="text-center text-muted w-100 py-3">
                            <i class="bi bi-person-x me-2"></i>
                            Keine verfügbaren Mitglieder gefunden.
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error Details:", xhr.responseText);
                $("#availableMembers").html(`
                    <div class="text-center text-danger w-100 py-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Fehler beim Laden der Mitglieder
                    </div>
                `);
                showToast("Fehler beim Laden der Mitglieder: " + error, 'error');
            }
        });
    }

    // #eventSelect => bei Änderung => Gruppen + Mitglieder laden
    $('#eventSelect').on('change', function() {
        let eventID = $(this).val();
        let jahr = $('#yearSelect').val();
        
        if (eventID) {
            loadExistingGroups(eventID, jahr);
            loadAvailableMembers(eventID, jahr);
        } else {
            $('#existingGroups').html(`
                <div class="text-center text-muted py-3">
                    <i class="bi bi-info-circle me-2"></i>
                    Bitte zuerst einen Anlass wählen
                </div>
            `);
            $('#availableMembers').html(`
                <div class="text-center text-muted w-100 py-3">
                    <i class="bi bi-person-plus me-2"></i>
                    Wähle zuerst einen Anlass
                </div>
            `);
        }
    });

    //////////////////////////
    // 3) Bearbeiten-Funktion
    //////////////////////////

    function editGroup(groupID) {
        showToast('Lade Gruppendaten...', 'info');
        $.ajax({
            url: 'jmdefinition/get_group_details.php',
            method: 'GET',
            data: { groupID: groupID },
            dataType: 'json',
            success: function(groupData) {
                if(!groupData || !groupData.ID) {
                    showToast("Keine Daten gefunden für Gruppe " + groupID, 'error');
                    return;
                }
                fillGroupEditForm(groupData);
                showToast('Gruppe wird bearbeitet', 'success');
            },
            error: function(xhr, status, error) {
                showToast("Fehler beim Laden der Gruppe: " + error, 'error');
            }
        });
    }

    function fillGroupEditForm(groupData) {
        $("#editGroupId").val(groupData.ID);
        $("#gruppenname").val(groupData.Gruppenname);
        $("#groupMembers").empty();

        if (!groupData.MemberIDs) {
            return;
        }

        let arrIDs = groupData.MemberIDs.split(",");
        let arrNames = groupData.MemberNames ? groupData.MemberNames.split("|") : [];

        arrIDs.forEach(function(mid, index) {
            mid = mid.trim();

            let $cand = $("#availableMembers .draggable-member[data-id='" + mid + "']");
            if ($cand.length) {
                let $clone = $("<div></div>")
                    .addClass("draggable-member")
                    .attr("data-id", mid)
                    .text($cand.text());
                $("#groupMembers").append($clone);
                $cand.remove();
            } else {
                let nameFallback = "Mitglied " + mid;
                
                if (arrNames[index]) {
                    nameFallback = arrNames[index].trim();
                }

                let $newElem = $("<div></div>")
                    .addClass("draggable-member")
                    .attr("data-id", mid)
                    .text(nameFallback);
                $("#groupMembers").append($newElem);
            }
        });

        setupDragDrop();
    }

    //////////////////////////
    // 4) Gruppe speichern  //
    //////////////////////////
    $("#newGroupForm").on("submit", function(e) {
        e.preventDefault();

        let $submitBtn = $('#saveGruppe');
        let originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        let editId = $("#editGroupId").val().trim(); 
        let eventID = $("#eventSelect").val();
        let jahr = $("#yearSelect").val();
        let gruppenname = $("#gruppenname").val().trim();

        let mitgliederIDs = [];
        $("#groupMembers .draggable-member").each(function() {
            mitgliederIDs.push($(this).data("id"));
        });

        if(!gruppenname || !eventID) {
            showToast("Bitte Gruppenname und Anlass wählen.", 'warning');
            $submitBtn.prop('disabled', false).html(originalText);
            return;
        }

        if(mitgliederIDs.length === 0) {
            showToast("Bitte mindestens ein Mitglied zur Gruppe hinzufügen.", 'warning');
            $submitBtn.prop('disabled', false).html(originalText);
            return;
        }

        let requestData = {
            eventID: eventID,
            jahr: jahr,
            gruppenname: gruppenname,
            mitglieder: mitgliederIDs,
            csrf_token: $('input[name="csrf_token"]').val()
        };

        if(editId) {
            requestData.editGroupId = editId;
        }

        $.ajax({
            url: 'jmdefinition/save_gruppen.php',
            method: 'POST',
            dataType: 'json',
            data: requestData,
            success: function(response) {
                if(response.success) {
                    showToast(editId ? "Gruppe erfolgreich aktualisiert!" : "Gruppe erfolgreich erstellt!", 'success');
                    resetForm();
                    setTimeout(() => {
                        loadExistingGroups(eventID, jahr);
                        loadAvailableMembers(eventID, jahr);
                    }, 500);
                } else if(response.error) {
                    showToast("Fehler: " + response.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast("Fehler beim Speichern der Gruppe: " + error, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    //////////////////////////
    // 5) Löschen-Funktion  //
    //////////////////////////
    function deleteGroup(groupId) {
        if(!confirm("Möchten Sie diese Gruppe wirklich löschen?")) {
            return;
        }
        
        showToast('Lösche Gruppe...', 'info');
        $.ajax({
            url: 'jmdefinition/delete_gruppe.php',
            method: 'POST',
            data: { 
                groupID: groupId,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('div.group-card[data-groupid="' + groupId + '"]').remove();
                    showToast("Gruppe wurde erfolgreich gelöscht.", 'success');
                    // Mitglieder neu laden
                    let eventID = $('#eventSelect').val();
                    let jahr = $('#yearSelect').val();
                    if (eventID) {
                        loadAvailableMembers(eventID, jahr);
                    }
                } else if (response.error) {
                    showToast("Fehler: " + response.error, 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast("Fehler beim Löschen der Gruppe: " + error, 'error');
            }
        });
    }

    // Formular zurücksetzen
    function resetForm() {
        $("#editGroupId").val("");
        $("#gruppenname").val("");
        $("#groupMembers").html(`
            <p class="text-muted">
                <i class="bi bi-cursor me-2"></i>
                Ziehe die Mitglieder hierher...
            </p>
        `);
    }

    $('#resetForm').on('click', function() {
        resetForm();
        let eventID = $('#eventSelect').val();
        let jahr = $('#yearSelect').val();
        if (eventID) {
            loadAvailableMembers(eventID, jahr);
        }
        showToast('Formular zurückgesetzt', 'info');
    });

    // Neuen Anlass hinzufügen
    $('#jmdefinitionHinzufuegen').click(function() {
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Hinzufügen...');

        var bezeichnung = $('#neueJMDefinitionBezeichnung').val().trim();
        var schiesstage = $('#neueJMDefinitionSchiesstage').val();
        var maxpunkte = $('#neueJMDefinitionMaxpunkte').val();
        var streicher = $('#neueJMDefinitionStreicher').is(':checked') ? 1 : 0;
        var erweitert = $('#neueJMDefinitionErweitert').is(':checked') ? 1 : 0;
        var info = $('#neueJMDefinitionInfo').is(':checked') ? 1 : 0;
        var gruppe = $('#neueJMDefinitionGruppe').is(':checked') ? 1 : 0;
        var year = $('#yearSelect').val() || new Date().getFullYear();

        if (!bezeichnung) {
            showToast('Bitte Anlassname eingeben', 'warning');
            $btn.prop('disabled', false).html(originalText);
            return;
        }

        $.ajax({
            url: 'jmdefinition/add_jmdefinition.php',
            type: 'POST',
            data: {
                bezeichnung: bezeichnung,
                schiesstage: schiesstage,
                maxpunkte: maxpunkte,
                streicher: streicher,
                erweitert: erweitert,
                info: info,
                gruppe: gruppe,
                adresse: '',
                year: year,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                showToast('Anlass erfolgreich hinzugefügt!', 'success');
                
                $('#newAnlassModal').modal('hide');
                
                // Modal-Felder zurücksetzen
                $('#neueJMDefinitionBezeichnung, #neueJMDefinitionSchiesstage, #neueJMDefinitionMaxpunkte').val('');
                $('#neueJMDefinitionStreicher, #neueJMDefinitionErweitert, #neueJMDefinitionInfo').prop('checked', false);
                $('#neueJMDefinitionGruppe').prop('checked', true);
                
                // Event-Dropdown neu laden
                setTimeout(() => loadEventDropdown(year), 500);
            },
            error: function() {
                showToast('Fehler beim Hinzufügen des Anlasses', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

<?php
include 'footer.inc.php';
?>