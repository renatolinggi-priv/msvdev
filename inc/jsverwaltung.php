<?php
/**
 * Jungschützenverwaltung
 *
 * @description Moderne, sichere und benutzerfreundliche Oberfläche für die Jungschützenverwaltung
 * @version 2.0 - Verbesserte Sicherheit und UX
 * @author System Enhancement
 */

// Sichere Includes mit Fehlerbehandlung
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in jsverwaltung.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Spezifische Styles für Jungschützenverwaltung
$page_specific_css = "

/* === NAVIGATION Z-INDEX FIX === */
/* Navigation muss immer über allen anderen Elementen sein */
.navbar {
    z-index: 1030 !important;
}

.dropdown-menu {
    z-index: 1040 !important;
}

/* === CONTAINER STYLES === */
.main-content-wrapper {
    position: relative;
}

.content-background {
    position: relative;
}

/* Container-fluid Anpassung */
.container-fluid {
    position: relative;
}

/* === TABELLEN CONTAINER === */
.table-wrapper {
    position: relative;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
}

/* Table Title mit niedrigem z-index */
.table-wrapper .table-title {
    position: relative;
    background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%);
    padding: 1rem;
    margin: 0;
    border-radius: 0.5rem 0.5rem 0 0;
    border-bottom: 2px solid #dee2e6;
    z-index: 5; /* Niedrig genug für Navigation */
}

/* Table Container mit Scrolling */
.table-container {
    max-height: calc(100vh - 400px);
    overflow: auto;
    position: relative;
    border-radius: 0 0 0.5rem 0.5rem;
}

/* === TABELLEN HEADER === */
#jungschuetzenTabelle thead {
    position: sticky;
    top: 0;
    z-index: 4; /* Unter Navigation */
}

#jungschuetzenTabelle thead th {
    position: sticky;
    top: 0;
    background: var(--secondary-color);
    color: white;
    z-index: 4; /* Unter Navigation */
    border-bottom: 2px solid var(--secondary-color);
}

/* === TABELLEN BODY === */
#jungschuetzenTabelle tbody td {
    background: white;
    position: relative;
    z-index: 1; /* Niedrigster z-index */
}

/* Hover-Effekt für tbody */
#jungschuetzenTabelle tbody tr:hover td {
    background-color: rgba(108, 117, 125, 0.08);
}

/* Erweiterte Tabelle für mehr Spalten */
#jungschuetzenTabelle {
    min-width: 1200px;
    margin-bottom: 0;
}

/* === OPTIMIERTE SPALTENBREITEN === */
#jungschuetzenTabelle th:nth-child(1), #jungschuetzenTabelle td:nth-child(1) { min-width: 120px; } /* AHV-Nummer */
#jungschuetzenTabelle th:nth-child(2), #jungschuetzenTabelle td:nth-child(2) { min-width: 120px; } /* Name */
#jungschuetzenTabelle th:nth-child(3), #jungschuetzenTabelle td:nth-child(3) { min-width: 120px; } /* Vorname */
#jungschuetzenTabelle th:nth-child(4), #jungschuetzenTabelle td:nth-child(4) { min-width: 120px; } /* Geburtsdatum */
#jungschuetzenTabelle th:nth-child(5), #jungschuetzenTabelle td:nth-child(5) { min-width: 150px; } /* Strasse */
#jungschuetzenTabelle th:nth-child(6), #jungschuetzenTabelle td:nth-child(6) { min-width: 80px; } /* PLZ */
#jungschuetzenTabelle th:nth-child(7), #jungschuetzenTabelle td:nth-child(7) { min-width: 120px; } /* Ort */
#jungschuetzenTabelle th:nth-child(8), #jungschuetzenTabelle td:nth-child(8) { min-width: 100px; } /* Kurs Nummer */
#jungschuetzenTabelle th:nth-child(9), #jungschuetzenTabelle td:nth-child(9) { min-width: 80px; } /* Aktionen */

/* === KOMPAKTERE TABELLE === */
#jungschuetzenTabelle th,
#jungschuetzenTabelle td {
    white-space: nowrap;
    font-size: 0.85rem; /* Etwas kleinere Schrift */
    padding: 0.4rem 0.4rem; /* Reduzierter Padding */
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

/* Einheitlicher Hover-Effekt für gesamte Zeile */
#jungschuetzenTabelle tbody tr:hover {
    background-color: rgba(108, 117, 125, 0.08) !important;
}

#jungschuetzenTabelle tbody tr:hover th,
#jungschuetzenTabelle tbody tr:hover td {
    background-color: rgba(108, 117, 125, 0.08) !important;
}

#jungschuetzenTabelle input,
#jungschuetzenTabelle select {
    font-size: 0.8rem; /* Kleinere Schrift */
    padding: 0.25rem 0.4rem; /* Weniger Padding */
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    transition: all 0.15s ease;
    height: 28px; /* Fixe Höhe */
}

#jungschuetzenTabelle input:focus,
#jungschuetzenTabelle select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.1);
    outline: none;
}

/* === KOMPAKTE ACTION BUTTONS === */
.btn-sm,
.deleteJungschuetze {
    padding: 0.2rem 0.4rem !important; /* Sehr kompakt */
    font-size: 0.75rem !important; /* Kleinere Schrift */
    border-radius: 0.25rem !important;
    line-height: 1.2 !important;
    height: 24px !important; /* Fixe kleine Höhe */
    min-width: auto !important;
}

/* Nur Icon für Löschen-Button */
.deleteJungschuetze {
    width: 28px !important;
    padding: 0.2rem !important;
}

.deleteJungschuetze i {
    font-size: 0.8rem !important;
}

/* Enhanced Modal Styling - Match mitgliederverwaltung.php Design */
/* Modal Animation */
.modal.fade .modal-dialog {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    transform: scale(0.9) translateY(-50px) !important;
}

.modal.show .modal-dialog {
    transform: scale(1) translateY(0) !important;
}

/* Modal Backdrop Blur Effect */
.modal-backdrop {
    backdrop-filter: blur(5px) !important;
    background-color: rgba(0, 0, 0, 0.5) !important;
}

/* Enhanced Modal Styling - Modern Design */
.modal-content {
    border: none !important;
    border-radius: 1.2rem !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15) !important;
    overflow: hidden !important;
}

.modal-header {
    background: #3b5998 !important;
    color: white !important;
    border-radius: 0 !important;
    padding: 1rem 1.5rem !important;
    border-bottom: none !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
}

.modal-header .modal-title {
    color: white !important;
    font-weight: 600 !important;
    font-size: 1.1rem !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
}

.modal-header .modal-title i {
    font-size: 1.2rem !important;
    opacity: 0.9 !important;
}

.modal-header .btn-close {
    background: rgba(255, 255, 255, 0.2) !important;
    border-radius: 50% !important;
    padding: 0.4rem !important;
    width: 28px !important;
    height: 28px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    opacity: 1 !important;
    transition: all 0.2s ease !important;
    position: relative !important;
}

.modal-header .btn-close::before,
.modal-header .btn-close::after {
    content: '' !important;
    position: absolute !important;
    width: 16px !important;
    height: 2px !important;
    background-color: white !important;
    top: 50% !important;
    left: 50% !important;
}

.modal-header .btn-close::before {
    transform: translate(-50%, -50%) rotate(45deg) !important;
}

.modal-header .btn-close::after {
    transform: translate(-50%, -50%) rotate(-45deg) !important;
}

.modal-header .btn-close:hover {
    background: rgba(255, 255, 255, 0.3) !important;
    transform: rotate(90deg) !important;
}

.modal-body {
    padding: 1.25rem !important;
    background: #ffffff !important;
    max-height: 65vh !important;
    overflow-y: auto !important;
}

.modal-footer {
    background: #f8f9fa !important;
    border-radius: 0 0 1.2rem 1.2rem !important;
    padding: 1rem 1.5rem !important;
    border-top: 1px solid #e2e8f0 !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
}

.modal-footer .btn {
    padding: 0.5rem 1.5rem !important;
    font-weight: 600 !important;
    border-radius: 0.5rem !important;
    border: none !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    text-transform: none !important;
    letter-spacing: 0.3px !important;
    font-size: 0.85rem !important;
    position: relative !important;
    overflow: hidden !important;
}

.modal-footer .btn::before {
    content: '' !important;
    position: absolute !important;
    top: 50% !important;
    left: 50% !important;
    width: 0 !important;
    height: 0 !important;
    border-radius: 50% !important;
    background: rgba(255, 255, 255, 0.3) !important;
    transform: translate(-50%, -50%) !important;
    transition: width 0.5s ease, height 0.5s ease !important;
}

.modal-footer .btn:hover::before {
    width: 300px !important;
    height: 300px !important;
}

.modal-footer .btn-outline-secondary {
    background: #ffffff !important;
    color: #64748b !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
}

.modal-footer .btn-outline-secondary:hover {
    background: #64748b !important;
    border-color: #64748b !important;
    color: white !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(100, 116, 139, 0.25) !important;
}

.modal-footer .btn-outline-success {
    background: #10b981 !important;
    color: white !important;
    border: 1px solid #10b981 !important;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.2) !important;
}

.modal-footer .btn-outline-success:hover {
    background: #059669 !important;
    border-color: #059669 !important;
    transform: translateY(-2px) scale(1.02) !important;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3) !important;
}

/* Enhanced form controls in modal */
.modal-body .form-control,
.modal-body .form-select {
    border: 2px solid #dee2e6 !important;
    border-radius: 0.5rem !important;
    padding: 0.75rem !important;
    font-weight: 500 !important;
    transition: all 0.2s ease !important;
    background: #ffffff !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}

.modal-body .form-control:focus,
.modal-body .form-select:focus {
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1), 0 4px 12px rgba(0, 0, 0, 0.1) !important;
    transform: scale(1.02) !important;
}

.modal-body .form-control:hover,
.modal-body .form-select:hover {
    border-color: var(--secondary-color) !important;
    background: #f8f9fa !important;
}

/* Search Enhancement */
.search-wrapper {
    position: relative;
}

.search-wrapper .input-group {
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.search-wrapper .input-group-text {
    background: var(--light-color);
    border: none;
    color: var(--secondary-color);
}

.search-wrapper .form-control {
    border: none;
    padding: 0.75rem 1rem;
    font-weight: 500;
}

.search-wrapper .form-control:focus {
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

";
include 'header.inc.php';

// CSRF Token generieren für sichere AJAX-Calls
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-12 col-lg-12 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-person-badge-fill me-2"></i>
                            Jungschützenverwaltung
                        </h2>
                        <p class="text-muted mb-0">Verwaltung der Jungschützen</p>
                    </div>
                </div>
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <!-- Action Toolbar -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="search-wrapper">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Jungschützen durchsuchen...">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Button Toolbar -->
                            <div class="button-toolbar">
                                <div class="button-group">
                                    <button class="btn btn-compact-standard btn-outline-success" onclick="$('#newJungschuetzeModal').modal('show')">
                                        <i class="bi bi-person-plus me-2"></i>
                                        Neuer Jungschütze
                                    </button>
                                    <button class="btn btn-compact-standard btn-outline-primary" onclick="saveJungschuetzen()">
                                        <i class="bi bi-save me-2"></i>
                                        Speichern
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Nachrichten Container -->
                    <div id="message"></div>
                    <!-- Tabelle -->
                    <form id="jungschuetzenForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="table-wrapper">
                            <h5 class="table-title">
                                <i class="bi bi-people me-2"></i>
                                Jungschützen
                            </h5>
                            <div class="table-container">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="jungschuetzenTabelle">
                                    <thead>
                                        <tr>
                                            <th scope="col">
                                                <i class="bi bi-credit-card me-1"></i>AHV-Nummer
                                            </th>
                                            <th scope="col">Name</th>
                                            <th scope="col">Vorname</th>
                                            <th scope="col">Geburtsdatum</th>
                                            <th scope="col">Strasse</th>
                                            <th scope="col">PLZ</th>
                                            <th scope="col">Ort</th>
                                            <th scope="col">Kurs Nummer</th>
                                            <th scope="col" class="text-center">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody id="jungschuetzenTbody">
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                                                Lade Jungschützen...
                                            </td>
                                        </tr>
                                    </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal für neuen Jungschützen -->
<div class="modal fade" id="newJungschuetzeModal" tabindex="-1" aria-labelledby="newJungschuetzeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="newJungschuetzeModalLabel">
                    <i class="bi bi-person-plus me-2"></i>Neuen Jungschützen hinzufügen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form id="newJungschuetzeForm">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="jungschuetzeAHV" class="form-label fw-bold">
                                <i class="bi bi-credit-card me-1"></i>AHV-Nummer
                            </label>
                            <input type="text" class="form-control form-control-sm" id="jungschuetzeAHV" name="ahvnummer" required>
                        </div>
                        <div class="col-md-6">
                            <label for="jungschuetzeKurs" class="form-label fw-bold">
                                <i class="bi bi-book me-1"></i>Kurs Nummer
                            </label>
                            <select class="form-select form-select-sm" id="jungschuetzeKurs" name="kursnummer" required>
                                <option value="">Kurs Nummer wählen</option>

                                <?php
                                for ($i = 1; $i <= 7; $i++) {
                                    echo "<option value='$i'>$i</option>";
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col-md-6">
                            <label for="jungschuetzeVorname" class="form-label fw-bold">
                                <i class="bi bi-person me-1"></i>Vorname
                            </label>
                            <input type="text" class="form-control form-control-sm" id="jungschuetzeVorname" name="vorname" required>
                        </div>
                        <div class="col-md-6">
                            <label for="jungschuetzeName" class="form-label fw-bold">
                                <i class="bi bi-person-badge me-1"></i>Name
                            </label>
                            <input type="text" class="form-control form-control-sm" id="jungschuetzeName" name="name" required>
                        </div>
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col-md-12">
                            <label for="jungschuetzeGeburtsdatum" class="form-label fw-bold">
                                <i class="bi bi-calendar3 me-1"></i>Geburtsdatum
                            </label>
                            <input type="date" class="form-control form-control-sm" id="jungschuetzeGeburtsdatum" name="geburtsdatum" required>
                        </div>
                    </div>
                    <hr class="my-3">
                    <h6 class="text-secondary mb-2">
                        <i class="bi bi-geo-alt me-2"></i>Adresse
                    </h6>
                    <div class="row g-2">
                        <div class="col-12">
                            <label for="jungschuetzeStrasse" class="form-label fw-bold">Strasse</label>
                            <input type="text" class="form-control form-control-sm" id="jungschuetzeStrasse" name="strasse" required>
                        </div>
                        <div class="col-md-4">
                            <label for="jungschuetzePLZ" class="form-label fw-bold">PLZ</label>
                            <input type="text" class="form-control form-control-sm" id="jungschuetzePLZ" name="plz" required>
                        </div>
                        <div class="col-md-8">
                            <label for="jungschuetzeOrt" class="form-label fw-bold">Ort</label>
                            <input type="text" class="form-control form-control-sm" id="jungschuetzeOrt" name="ort" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Abbrechen
                    </button>
                    <button type="submit" class="btn btn-outline-success">
                        <i class="bi bi-save me-2"></i>Jungschütze speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    // === GLOBALE VARIABLEN ===
    let modalOpening = false;

    // === INITIALISIERUNG ===
    loadJungschuetzen();
    setupEventHandlers();
    setupModalEventHandlers();

    // Nachricht anzeigen (wrapper für msvToast)
    function showMessage(message, type) {
        const typeMap = {
            'danger': 'error',
            'success': 'success',
            'warning': 'warning',
            'info': 'info'
        };
        msvToast(message, typeMap[type] || 'info');
    }

    // === DATEN-LADEN FUNKTIONEN ===
    // Jungschützen laden mit verbesserter Fehlerbehandlung
    function loadJungschuetzen() {
        $('#jungschuetzenTbody').html(`
            <tr>
                <td colspan="9" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                    Lade Jungschützen...
                </td>
            </tr>
        `);
        $.ajax({
            url: 'jsverwaltung/load_jungschuetzen.php',
            type: 'GET',
            timeout: 10000,
            success: function(data) {
                $('#jungschuetzenTbody').html(data);
                msvToast('Jungschützen erfolgreich geladen', 'success');
            },
            error: function(xhr, status, error) {
                console.error('Fehler beim Laden der Jungschützen:', error);
                let errorMessage = 'Fehler beim Laden der Jungschützen';
                if (status === 'timeout') {
                    errorMessage = 'Zeitüberschreitung beim Laden der Daten';
                } else if (xhr.status === 404) {
                    errorMessage = 'Datei nicht gefunden';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server-Fehler';
                }
                $('#jungschuetzenTbody').html(`
                    <tr>
                        <td colspan="9" class="text-center text-danger py-4">
                            <i class="bi bi-exclamation-triangle me-2"></i> ${errorMessage}
                            <br><small>Status: ${xhr.status} - ${error}</small>
                        </td>
                    </tr>
                `);
                showMessage(errorMessage, 'danger');
            }
        });
    }

    // === EVENT HANDLERS ===
    function setupEventHandlers() {

        // Suche - angepasst für Input-Felder
        $('#searchInput').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $('#jungschuetzenTbody tr').filter(function() {

                // Durchsuche alle Input-Felder und Select-Felder in der Zeile
                let rowText = '';
                $(this).find('input, select').each(function() {
                    if ($(this).attr('type') !== 'checkbox') {
                        rowText += $(this).val() + ' ';
                    }
                });

                // Zeige/verstecke Zeile basierend auf Suchergebnis
                $(this).toggle(rowText.toLowerCase().indexOf(value) > -1);
            });
        });

        // Neuer Jungschütze Form
        $('#newJungschuetzeForm').on('submit', saveNewJungschuetze);

        // Löschen mit verbesserter Bestätigung
        $(document).on('click', '.deleteJungschuetze', handleDeleteJungschuetze);
    }

    // === MODAL EVENT HANDLERS ===
    function setupModalEventHandlers() {

        // Verhindere mehrfaches Öffnen
        $('#newJungschuetzeModal').on('show.bs.modal', function(e) {
            if (modalOpening) {
                e.preventDefault();
                return false;
            }
            modalOpening = true;
        });
        $('#newJungschuetzeModal').on('shown.bs.modal', function() {
            modalOpening = false;

            // Fokus auf erstes Eingabefeld setzen
            $('#jungschuetzeAHV').focus();
        });
        $('#newJungschuetzeModal').on('hidden.bs.modal', function() {

            // Form zurücksetzen
            $('#newJungschuetzeForm')[0].reset();
        });
    }

    // === SPEICHER-FUNKTIONEN ===
    // Jungschützen speichern
    window.saveJungschuetzen = function() {
        const $saveBtn = $('button[onclick="saveJungschuetzen()"]');
        const originalText = $saveBtn.html();
        $saveBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
        $.ajax({
            url: 'jsverwaltung/save_jungschuetzen.php',
            type: 'POST',
            data: $('#jungschuetzenForm').serialize(),
            success: function(response) {
                msvToast('Alle Änderungen erfolgreich gespeichert!', 'success');
                setTimeout(() => loadJungschuetzen(), 1000);
            },
            error: function(xhr, status, error) {
                msvToast('Fehler beim Speichern der Änderungen', 'error');
            },
            complete: function() {
                $saveBtn.prop('disabled', false).html(originalText);
            }
        });
    };

    // Neuen Jungschützen speichern
    function saveNewJungschuetze(e) {
        e.preventDefault();
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
        $.ajax({
            url: 'jsverwaltung/add_jungschuetze.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.status === 'success') {
                        msvToast('Neuer Jungschütze erfolgreich hinzugefügt!', 'success');
                        $('#newJungschuetzeModal').modal('hide');
                        $('#newJungschuetzeForm')[0].reset();
                        setTimeout(() => loadJungschuetzen(), 1000);
                    } else {
                        msvToast('Fehler: ' + (result.message || 'Unbekannter Fehler'), 'error');
                    }
                } catch (e) {
                    msvToast('Neuer Jungschütze erfolgreich hinzugefügt!', 'success');
                    $('#newJungschuetzeModal').modal('hide');
                    $('#newJungschuetzeForm')[0].reset();
                    setTimeout(() => loadJungschuetzen(), 1000);
                }
            },
            error: function(xhr, status, error) {
                msvToast('Verbindungsfehler beim Hinzufügen des Jungschützen', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }

    // Jungschütze löschen
    async function handleDeleteJungschuetze() {
        const id = $(this).data('id');
        const memberName = $(this).closest('tr').find('td:nth-child(2)').text() + ' ' + $(this).closest('tr').find('td:nth-child(3)').text();

        const result = await msvConfirmDelete(`Jungschützen "${memberName}"`);
        if (!result.isConfirmed) return;

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm"></span>');
        $.ajax({
                url: 'jsverwaltung/delete_jungschuetze.php',
                type: 'POST',
                data: {
                    id: id,
                    csrf_token: $('input[name="csrf_token"]').val()
                },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.status === 'success') {
                            msvToast('Jungschütze erfolgreich gelöscht', 'success');
                            setTimeout(() => loadJungschuetzen(), 1000);
                        } else {
                            msvToast('Fehler: ' + (result.message || 'Unbekannter Fehler'), 'error');
                            $btn.prop('disabled', false).html(originalText);
                        }
                    } catch (e) {
                        msvToast('Jungschütze erfolgreich gelöscht', 'success');
                        setTimeout(() => loadJungschuetzen(), 1000);
                    }
                },
                error: function(xhr, status, error) {
                    msvToast('Fehler beim Löschen des Jungschützen', 'error');
                    $btn.prop('disabled', false).html(originalText);
                }
            });
    }
});

</script>

<?php
include 'footer.inc.php';
?>
