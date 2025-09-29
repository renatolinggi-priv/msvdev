<?php
// wichtigetermine.php
include 'dbconnect.inc.php';

// Seitenspezifische Styles definieren
$page_specific_css = "
/* Wichtige Termine-spezifische Styles */

/* CSS Variables für dynamische Höhen */
:root {
    --app-header: 76px; /* Standard navbar height */
    --app-footer: 0px;
}

/* Flex-Layout für volle Höhennutzung */
.main-content-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 0 !important;
    height: calc(100vh - var(--app-header) - var(--app-footer) - 20px) !important;
    margin-bottom: 0 !important;
}

.content-background {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0 !important;
    overflow: hidden;
}

/* Events Container wird flex */
#eventsListContainer {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
}

/* Moderne Tabellen-Styles */
.table {
    border: none;
    margin-bottom: 0;
    table-layout: fixed; /* Verhindert Spaltenverschiebungen */
}

.table thead th {
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    padding: 1rem;
    background-color: #f8f9fa;
    position: relative; /* Entfernt sticky, das Probleme verursachen kann */
}

.table tbody tr {
    transition: background-color 0.2s ease; /* Nur Hintergrundfarbe animieren */
    border-bottom: 1px solid #f1f3f4;
}

.table tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.04);
    /* transform entfernt - das verursacht das Springen */
}

.table tbody tr.table-secondary {
    background-color: #f8f9fa;
    font-size: 0.85rem;
    border-top: 2px solid #dee2e6;
    border-bottom: 2px solid #dee2e6;
}

.table tbody tr.table-secondary:hover {
    background-color: #f8f9fa;
}

.table tbody td {
    padding: 0.75rem;
    vertical-align: middle;
    border: none;
}

/* Badge Styles */
.badge {
    font-weight: 500;
    padding: 0.35em 0.65em;
}

/* Button Group in Tabelle */
.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.2rem;
}

/* Table Wrapper für Flex-Layout */
.table-wrapper {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0 !important;
    margin-bottom: 0 !important;
    overflow: hidden !important;
}

/* Responsive Table Container */
.table-responsive {
    flex: 1 1 auto;
    min-height: 0 !important;
    overflow: auto !important;
    border-radius: 0 0 var(--border-radius) var(--border-radius);
    -webkit-overflow-scrolling: touch;
    /* Höhe wird dynamisch per JS gesetzt */
}

/* Hover-Effekte für Action Buttons */
.btn-outline-primary:hover,
.btn-outline-danger:hover {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.add-event-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1rem 1.25rem;
    margin-bottom: 1.25rem;
    border-left: 4px solid var(--success-color);
}

.add-event-card h5 {
    color: var(--success-color);
    margin-bottom: 0.75rem;
    font-weight: 600;
    font-size: 0.95rem;
}

.events-list-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    overflow: hidden;
    margin-bottom: 0;
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}

.events-header {
    background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%);
    padding: 1.5rem;
    border-bottom: 1px solid #dee2e6;
    margin: 0;
    color: var(--dark-color);
    font-weight: 600;
    font-size: 1.1rem;
    flex-shrink: 0;
}

/* Action Button Gruppe */
.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

/* Export Links */
.export-links {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius);
    padding: 0.5rem 0.75rem;
    margin-top: 0.5rem;
    font-size: 0.9rem;
}

.export-links a {
    color: var(--success-color);
    text-decoration: none;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.export-links a:hover {
    color: var(--success-color);
    text-decoration: underline;
}

/* Custom Close Button */
.custom-close {
    background: none;
    border: none;
    color: var(--secondary-color);
    font-size: 1.5rem;
    opacity: 0.7;
    transition: all var(--transition-speed) ease;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.custom-close:hover {
    opacity: 1;
    background-color: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
    transform: scale(1.1);
}

/* Responsive für Wichtige Termine */
@media (max-width: 768px) {
    .add-event-card {
        padding: 1rem;
        margin: 0 -0.5rem 1.5rem -0.5rem;
        border-radius: 0;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        width: 100%;
    }
}

.spinner-border {
    color: var(--secondary-color) !important;
}

/* Animationen */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.add-event-card, .events-list-card {
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
        <div class="col-xl-10 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-calendar-event me-2"></i>
                            Wichtige Termine
                        </h2>
                        <p class="text-muted mb-0">Termine verwalten und exportieren</p>
                    </div>
                </div>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <!-- Nachrichten Container -->
                    <div id="message"></div>

                    <!-- Neuen Termin hinzufügen -->
                    <div class="add-event-card">
                        <h5>
                            <i class="bi bi-plus-circle me-2"></i>
                            Neuen Termin hinzufügen
                        </h5>
                        
                        <form id="addEventForm" method="POST" action="add_event.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            
                            <div class="row mb-2">
                                <div class="col-md-3">
                                    <label for="eventYear" class="form-label small mb-1">
                                        <i class="bi bi-calendar3 me-1"></i>Jahr:
                                    </label>
                                    <select id="eventYear" name="event_year" class="form-control form-control-sm" required>
                                        <!-- Optionen werden dynamisch per JS eingefügt -->
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label for="eventName" class="form-label small mb-1">
                                        <i class="bi bi-tag me-1"></i>Bezeichnung:
                                    </label>
                                    <input type="text" id="eventName" name="event_name" class="form-control form-control-sm" required placeholder="Event Bezeichnung">
                                </div>

                                <div class="col-md-3">
                                    <label for="eventDate" class="form-label small mb-1">
                                        <i class="bi bi-calendar-date me-1"></i>Datum:
                                    </label>
                                    <input type="date" id="eventDate" name="event_date" class="form-control form-control-sm" required>
                                </div>

                                <div class="col-md-2">
                                    <label for="eventTime" class="form-label small mb-1">
                                        <i class="bi bi-clock me-1"></i>Zeit:
                                    </label>
                                    <input type="text" id="eventTime" name="event_time" class="form-control form-control-sm" required placeholder="08:00-12:00">
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" class="btn btn-compact-standard btn-outline-success">
                                    <i class="bi bi-plus-circle me-1"></i> Termin hinzufügen
                                </button>
                                <button type="button" id="generateIcsButton" class="btn btn-compact-standard btn-outline-info">
                                    <i class="bi bi-calendar-plus me-1"></i> ICS generieren
                                </button>
                                <button type="button" id="generatePDFButton" class="btn btn-compact-standard btn-outline-info">
                                    <i class="bi bi-file-pdf me-1"></i> PDF generieren
                                </button>
                            </div>
                        </form>

                        <div id="icsDownloadLink" class="export-links" style="display: none;">
                            <i class="bi bi-download me-2"></i>
                            <a href="" id="downloadLink" target="_blank">Hier herunterladen</a>
                        </div>
                    </div>

                    <!-- Container für die Events des aktuellen Jahres -->
                    <div id="eventsListContainer">
                        <div class="events-list-card">
                            <div class="events-header">
                                <i class="bi bi-list me-2"></i>
                                Termine Liste
                            </div>
                            <div class="p-4 text-center">
                                <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                                Lade Termine...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal zum Bearbeiten eines Events -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="editEventForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">
                        <i class="bi bi-pencil-square"></i> Event bearbeiten
                    </h5>
                    <button type="button" class="custom-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="bi bi-x"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="editEventId" name="event_id">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group mb-3">
                        <label for="editName" class="form-label">
                            <i class="bi bi-tag me-1"></i> Bezeichnung:
                        </label>
                        <input type="text" id="editName" name="event_name" class="form-control" required>
                    </div>

                    <div class="form-group mb-3">
                        <label for="editDate" class="form-label">
                            <i class="bi bi-calendar-date me-1"></i> Datum:
                        </label>
                        <input type="date" id="editDate" name="event_date" class="form-control" required>
                    </div>

                    <div class="form-group mb-3">
                        <label for="editTime" class="form-label">
                            <i class="bi bi-clock me-1"></i> Zeit:
                        </label>
                        <input type="text" id="editTime" name="event_time" class="form-control" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-compact-standard btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Abbrechen
                    </button>
                    <button type="submit" class="btn btn-compact-standard btn-outline-primary" id="saveChangesButton">
                        <i class="bi bi-check-circle me-1"></i> Speichern
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal zur Bestätigung für das Löschen eines Events -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="bi bi-exclamation-triangle text-warning"></i> Bestätigung erforderlich
                </h5>
                <button type="button" class="custom-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle text-warning me-3" style="font-size: 2rem;"></i>
                    <div>
                        <strong>Möchtest du diesen Termin wirklich löschen?</strong>
                        <br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-compact-standard btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-compact-standard btn-outline-danger" id="confirmDeleteButton">
                    <i class="bi bi-check-circle me-1"></i>Bestätigen
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var eventIdToDelete = null;

    // Toast Container hinzufügen (falls nicht vorhanden)
    if ($('#toast-container').length === 0) {
        $('body').append('<div id="toast-container"></div>');
    }

    // Toast-Funktion mit korrekter Positionierung
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
        
        $('#toast-container').css({
            'position': 'fixed',
            'top': '80px',
            'right': '20px',
            'z-index': '9999',
            'max-width': '350px'
        });
        
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
                'min-width': '250px',
                'max-width': '350px'
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

    // Höhenberechnung für Tabelle
    function calculateTableHeight() {
        const tableResp = $('.table-responsive');
        if (!tableResp.length) return;
        
        // Navbar Höhe
        const navbar = $('.navbar');
        const navbarHeight = navbar.length ? navbar.outerHeight() : 76;
        
        // Position der Tabelle
        const tableTop = tableResp.offset().top;
        
        // Footer und Padding
        const bottomPadding = 30;
        
        // Verfügbare Höhe berechnen
        const viewportHeight = window.innerHeight;
        const availableHeight = viewportHeight - tableTop - bottomPadding;
        const maxHeight = Math.max(300, availableHeight);
        
        // Höhe setzen
        tableResp.css({
            'max-height': maxHeight + 'px',
            'overflow-y': 'auto'
        });
    }

    // Events für das aktuelle Jahr laden
    function loadEvents(year) {
        $('#eventsListContainer').html(`
            <div class="events-list-card">
                <div class="events-header">
                    <i class="bi bi-list me-2"></i>
                    Termine Liste
                </div>
                <div class="p-4 text-center">
                    <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                    Lade Termine...
                </div>
            </div>
        `);
        
        $.ajax({
            url: 'wichtigetermine/load_events.php',
            method: 'GET',
            data: { year: year },
            success: function(response) {
                $('#eventsListContainer').html(response);
                // Höhe nach dem Laden neu berechnen
                setTimeout(calculateTableHeight, 100);
            },
            error: function(xhr, status, error) {
                $('#eventsListContainer').html(`
                    <div class="events-list-card">
                        <div class="events-header">
                            <i class="bi bi-list me-2"></i>
                            Termine Liste
                        </div>
                        <div class="p-4 text-center text-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Fehler beim Laden der Termine
                        </div>
                    </div>
                `);
                showToast('Fehler beim Laden der Termine', 'error');
            }
        });
    }

    // Jahresauswahl initialisieren
    function initializeYearDropdown() {
        const yearSelect = $('#eventYear').empty();
        const currentYear = new Date().getFullYear();
        for (let year = 2024; year <= currentYear; year++) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }
    }

    // Window Resize Handler
    let resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(calculateTableHeight, 150);
    });

    // Global Scroll auf Tabelle umleiten
    document.addEventListener('wheel', function(e) {
        const tableContainer = $('.table-responsive')[0];
        if (tableContainer && tableContainer.scrollHeight > tableContainer.clientHeight) {
            tableContainer.scrollTop += e.deltaY;
            e.preventDefault();
        }
    }, { passive: false });

    // Event bearbeiten Modal
    $(document).on("click", ".edit-event", function() {
        var eventId = $(this).data('id');
        var eventName = $(this).data('name');
        var eventDate = $(this).data('date');
        var eventTime = $(this).data('time');

        $('#editEventId').val(eventId);
        $('#editName').val(eventName);
        $('#editDate').val(eventDate);
        $('#editTime').val(eventTime);

        $('#editModal').modal('show');
    });

    // Event bearbeiten Form Submit
    $('#editEventForm').on('submit', function(e) {
        e.preventDefault();
        
        var $submitBtn = $('#saveChangesButton');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        var eventId = $('#editEventId').val();
        var eventName = $('#editName').val();
        var eventDate = $('#editDate').val();
        var eventTime = $('#editTime').val();

        $.ajax({
            url: 'wichtigetermine/update_event.php',
            method: 'POST',
            data: {
                event_id: eventId,
                event_name: eventName,
                event_date: eventDate,
                event_time: eventTime,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                let jsonResponse;
                try {
                    jsonResponse = typeof response === "object" ? response : JSON.parse(response);
                } catch (e) {
                    showToast("Fehler beim Verarbeiten der Serverantwort", 'error');
                    return;
                }

                if (jsonResponse.success) {
                    $('#editModal').modal('hide');
                    showToast('Termin erfolgreich aktualisiert', 'success');
                    setTimeout(() => {
                        loadEvents($('#eventYear').val());
                    }, 300);
                } else {
                    showToast("Fehler beim Aktualisieren: " + (jsonResponse.message || "Unbekannter Fehler"), 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast("Fehler beim Aktualisieren des Termins: " + error, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // ICS generieren
    $('#generateIcsButton').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
        
        var eventYear = $('#eventYear').val();

        $.ajax({
            url: 'wichtigetermine/export_all_ics.php',
            method: 'GET',
            data: { year: eventYear },
            success: function(response) {
                var data = JSON.parse(response);
                if (data.success) {
                    $('#icsDownloadLink').show();
                    $('#downloadLink').attr('href', data.ics_link);
                    showToast('ICS-Datei erfolgreich erstellt', 'success');
                } else {
                    showToast('Fehler beim Erstellen der ICS-Datei', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Fehler beim Generieren der ICS-Datei', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // PDF generieren
    $('#generatePDFButton').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
        
        var eventYear = $('#eventYear').val();

        $.ajax({
            url: 'wichtigetermine/create_pdf.php',
            method: 'GET',
            data: { year: eventYear },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    $('#icsDownloadLink').show();
                    $('#downloadLink').attr('href', data.pdf_link);
                    showToast('PDF-Datei erfolgreich erstellt', 'success');
                } else {
                    showToast('Fehler beim Erstellen der PDF-Datei', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Fehler beim Generieren der PDF-Datei: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Neuen Termin hinzufügen
    $('#addEventForm').on('submit', function(e) {
        e.preventDefault();
        
        var $submitBtn = $(this).find('button[type="submit"]');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Hinzufügen...');

        var eventYear = $('#eventYear').val();
        var eventName = $('#eventName').val().trim();
        var eventDate = $('#eventDate').val();
        var eventTime = $('#eventTime').val().trim();

        if (!eventName || !eventDate || !eventTime) {
            showToast('Bitte alle Felder ausfüllen', 'warning');
            $submitBtn.prop('disabled', false).html(originalText);
            return;
        }

        $.ajax({
            url: 'wichtigetermine/add_event.php',
            method: 'POST',
            data: {
                event_year: eventYear,
                event_name: eventName,
                event_date: eventDate,
                event_time: eventTime,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                showToast('Termin erfolgreich hinzugefügt!', 'success');
                $('#eventName').val('');
                $('#eventDate').val('');
                $('#eventTime').val('');
                setTimeout(() => loadEvents(eventYear), 500);
            },
            error: function(xhr, status, error) {
                showToast('Fehler beim Hinzufügen des Termins', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Event löschen
    $(document).on("click", ".delete-event", function() {
        eventIdToDelete = $(this).data('id');
        $("#confirmModal").modal('show');
    });

    // Löschen bestätigen
    $("#confirmDeleteButton").on("click", function() {
        if (!eventIdToDelete) return;
        
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');

        $.ajax({
            url: 'wichtigetermine/delete_event.php',
            method: 'POST',
            data: {
                event_id: eventIdToDelete,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                let jsonResponse;
                try {
                    jsonResponse = typeof response === "object" ? response : JSON.parse(response);
                } catch (e) {
                    showToast("Fehler beim Verarbeiten der Serverantwort", 'error');
                    return;
                }

                if (jsonResponse.success) {
                    $("#confirmModal").modal("hide");
                    showToast('Termin erfolgreich gelöscht', 'success');
                    setTimeout(() => loadEvents($('#eventYear').val()), 500);
                } else {
                    showToast("Fehler beim Löschen: " + (jsonResponse.message || "Unbekannter Fehler"), 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast("Fehler beim Löschen des Termins: " + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
                eventIdToDelete = null;
            }
        });
    });

    // Initialisierung
    initializeYearDropdown();
    const currentYear = new Date().getFullYear();
    loadEvents(currentYear);
    
    // Initiale Höhenberechnung nach kurzer Verzögerung
    setTimeout(calculateTableHeight, 200);
});
</script>

<?php
include 'footer.inc.php';
?>