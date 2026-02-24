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
}

/* Kompakte Buttons */
.btn-compact { padding: .45rem .75rem; font-size: .875rem; }

.add-event-card h5 {
    color: var(--secondary-color);
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

/* Action Button Gruppe - wie bei jmdefinition */
.button-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    align-items: center;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.25rem;
    margin-bottom: 1.25rem;
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
@media (max-width: 576px) {
    .add-event-card {
        padding: 1rem;
    }
    
    .button-toolbar {
        flex-direction: column;
    }
    
    .button-toolbar .btn {
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

/* === MOBILE OPTIMIZATION === */
@media (max-width: 767.98px) {
    /* Desktop-Tabelle ausblenden */
    .desktop-table-container {
        display: none !important;
    }

    /* Mobile Cards anzeigen */
    .mobile-cards-container {
        display: block !important;
    }

    /* Touch-friendly Form Controls (WCAG AAA + iOS Zoom Prevention) */
    .form-control, .form-control-sm, input, select {
        min-height: 48px !important;
        font-size: 16px !important;
    }

    /* Formular-Anpassungen für Mobile */
    .add-event-card {
        padding: 0.875rem;
    }

    .add-event-card h5 {
        font-size: 0.9rem;
    }

    /* Button-Anpassungen */
    .btn, .btn-compact {
        min-height: 48px !important;
        font-size: 16px !important;
        padding: 0.5rem 1rem !important;
    }

    .button-toolbar {
        flex-direction: column;
        padding: 0.875rem;
    }

    .button-toolbar .btn {
        width: 100%;
    }

    /* Container-Anpassungen */
    .main-content-wrapper {
        padding: 0.5rem;
        height: auto !important;
    }

    .content-background {
        padding: 0.5rem;
        overflow: visible !important;
        min-height: auto !important;
    }

    #eventsListContainer {
        overflow: visible !important;
        min-height: auto !important;
    }

    .events-list-card {
        overflow: visible !important;
        min-height: auto !important;
    }

    /* Formular in Spalten umbrechen */
    .add-event-card .row .col-md-2,
    .add-event-card .row .col-md-4 {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}

/* Desktop: Mobile Cards ausblenden */
@media (min-width: 768px) {
    .mobile-cards-container {
        display: none !important;
    }
}

/* === MOBILE EVENT CARDS === */

.mobile-event-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 0.6rem 0.875rem;
    margin-bottom: 1.75rem;
}

.mobile-event-card .event-action-btn {
    width: 32px !important;
    height: 32px !important;
    min-height: 32px !important;
    min-width: 32px !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 0.85rem !important;
}

.mobile-month-header {
    font-weight: 600;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
    padding: 0.75rem 0.25rem 0.25rem;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 0.4rem;
}
";

include 'header.inc.php';
?><style><?= $page_specific_css ?></style><?php

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
                <div class="row mb-4 d-none d-md-flex">
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
                    <!-- Jahr-Auswahl + Aktionen nebeneinander -->
                    <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <label for="eventYear" class="form-label fw-bold mb-0 text-nowrap">
                            <i class="bi bi-calendar3 me-1"></i>Jahr:
                        </label>
                        <select id="eventYear" name="event_year" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
                    </div>

                    <!-- Aktionsbereich (Bootstrap Collapse) -->
                    <div class="card action-card mb-0">
                        <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
                             data-bs-toggle="collapse" data-bs-target="#wichtigetermineActions"
                             aria-expanded="false" aria-controls="wichtigetermineActions">
                            <span class="fw-semibold"><i class="bi bi-tools me-2"></i>Aktionen</span>
                            <i class="bi bi-chevron-down action-chevron"></i>
                        </div>
                        <div class="collapse" id="wichtigetermineActions">
                            <div class="card-body pt-2 pb-3 px-3">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button type="button" id="generateIcsButton" class="btn btn-outline-secondary w-100">
                                            <i class="bi bi-calendar-plus me-1"></i>ICS generieren
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" id="generatePDFButton" class="btn btn-outline-danger w-100">
                                            <i class="bi bi-file-pdf me-1"></i>PDF generieren
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div><!-- Ende flex-row Jahr+Aktionen -->

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
                    <button type="button" class="btn btn-compact btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Abbrechen
                    </button>
                    <button type="submit" class="btn btn-compact btn-outline-primary" id="saveChangesButton">
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
                <button type="button" class="btn btn-compact btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-compact btn-outline-danger" id="confirmDeleteButton">
                    <i class="bi bi-trash me-1"></i>Löschen
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var eventIdToDelete = null;




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
                // Mobile Cards generieren
                if (typeof buildMobileEventsCards === 'function') {
                    buildMobileEventsCards();
                }
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
                msvToast('Fehler beim Laden der Termine', 'error');
            }
        });
    }

    // Jahresauswahl initialisieren
    function initializeYearDropdown() {
        const yearSelect = $('#eventYear').empty();
        const currentYear = new Date().getFullYear();
        for (let year = currentYear; year >= currentYear - 3; year--) {
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
                    msvToast("Fehler beim Verarbeiten der Serverantwort", 'error');
                    return;
                }

                if (jsonResponse.success) {
                    $('#editModal').modal('hide');
                    msvToast('Termin erfolgreich aktualisiert', 'success');
                    setTimeout(() => {
                        loadEvents($('#eventYear').val());
                    }, 300);
                } else {
                    msvToast("Fehler beim Aktualisieren: " + (jsonResponse.message || "Unbekannter Fehler"), 'error');
                }
            },
            error: function(xhr, status, error) {
                msvToast("Fehler beim Aktualisieren des Termins: " + error, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Hilfsfunktion für direkten Download
    function triggerDownload(url) {
        const a = document.createElement('a');
        a.href = url;
        a.download = '';
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

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
            dataType: 'json',
            success: function(data) {
                if (data.success && data.ics_link) {
                    triggerDownload(data.ics_link);
                    msvToast('ICS-Datei wird heruntergeladen', 'success');
                } else {
                    msvToast(data.message || 'Fehler beim Erstellen der ICS-Datei', 'error');
                }
            },
            error: function(xhr, status, error) {
                msvToast('Fehler beim Generieren der ICS-Datei', 'error');
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
                if (data.success && data.pdf_link) {
                    triggerDownload(data.pdf_link);
                    msvToast('PDF-Datei wird heruntergeladen', 'success');
                } else {
                    msvToast(data.message || 'Fehler beim Erstellen der PDF-Datei', 'error');
                }
            },
            error: function(xhr, status, error) {
                msvToast('Fehler beim Generieren der PDF-Datei', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
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
                    msvToast("Fehler beim Verarbeiten der Serverantwort", 'error');
                    return;
                }

                if (jsonResponse.success) {
                    $("#confirmModal").modal("hide");
                    msvToast('Termin erfolgreich gelöscht', 'success');
                    setTimeout(() => loadEvents($('#eventYear').val()), 500);
                } else {
                    msvToast("Fehler beim Löschen: " + (jsonResponse.message || "Unbekannter Fehler"), 'error');
                }
            },
            error: function(xhr, status, error) {
                msvToast("Fehler beim Löschen des Termins: " + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
                eventIdToDelete = null;
            }
        });
    });

    // Mobile Cards für Events generieren
    function buildMobileEventsCards() {
        const isMobile = window.matchMedia('(max-width: 767.98px)');
        if (!isMobile.matches) return;

        const table = document.querySelector('#eventsTable');
        if (!table) return;

        const scrollContainer = document.querySelector('#mobileEventsCards .mobile-cards-scroll');
        if (!scrollContainer) return;

        scrollContainer.innerHTML = '';
        const rows = table.querySelectorAll('tbody tr');

        if (rows.length === 0) {
            scrollContainer.innerHTML = `
                <div class="mobile-cards-empty">
                    <i class="bi bi-calendar-x"></i>
                    <div>Keine Termine vorhanden</div>
                </div>`;
            return;
        }

        let html = '';
        rows.forEach(row => {
            // Monats-Separator
            if (row.classList.contains('table-secondary')) {
                const monthText = row.querySelector('td')?.textContent?.trim() || '';
                html += `<div class="mobile-month-header" style="font-weight:700; font-size:0.95rem; line-height:1.3; color:#212529; padding:0.75rem 0.25rem 0.25rem; border-bottom:1px solid #e2e8f0; margin-bottom:0.4rem;">${monthText}</div>`;
                return;
            }

            const cells = row.querySelectorAll('td');
            if (cells.length < 4) return;

            const bezeichnung = cells[0].querySelector('.fw-semibold')?.textContent.trim()
                || cells[0].textContent.trim();

            // Datum-Zelle: Badge (Wochentag) + Datumstext
            const datumCell = cells[1];
            const wochentagBadge = datumCell.querySelector('.badge')?.outerHTML || '';
            const datumText = datumCell.textContent.trim();

            // Zeit-Badge
            const zeitBadge = cells[2].querySelector('.badge')?.outerHTML
                || cells[2].textContent.trim();

            // Buttons-Daten
            const editBtn = cells[3].querySelector('.edit-event');
            const eventId   = editBtn?.getAttribute('data-id')   || '';
            const eventName = editBtn?.getAttribute('data-name')  || '';
            const eventDate = editBtn?.getAttribute('data-date')  || '';
            const eventTime = editBtn?.getAttribute('data-time')  || '';

            const datumOnly = datumText.replace(datumCell.querySelector('.badge')?.textContent || '', '').trim();
            html += `
            <div class="mobile-event-card">
                <div class="d-flex justify-content-between align-items-start gap-2" style="margin-bottom:0.3rem;">
                    <span style="font-weight:600; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:#6c757d;">${bezeichnung}</span>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <button class="btn btn-outline-primary btn-sm edit-event event-action-btn"
                                data-id="${eventId}"
                                data-name="${eventName}"
                                data-date="${eventDate}"
                                data-time="${eventTime}">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm delete-event event-action-btn"
                                data-id="${eventId}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-1" style="font-size:0.82rem; color:#6c757d;">
                    ${wochentagBadge}
                    <span>${datumOnly}</span>
                    <span style="color:#ced4da;">·</span>
                    ${zeitBadge}
                </div>
            </div>`;
        });

        scrollContainer.innerHTML = html;
    }

    // Global filterMobileEvents function
    window.filterMobileEvents = function(searchInput) {
        const searchTerm = searchInput.value.toLowerCase();
        const cards = document.querySelectorAll('#mobileEventsCards .mobile-event-card');

        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    };

    // Jahreswechsel: Events neu laden wenn Jahr geändert wird
    $('#eventYear').on('change', function() {
        loadEvents($(this).val());
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