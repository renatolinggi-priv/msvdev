<?php
/**
 * Mitgliederverwaltung
 *
 * @description Moderne, sichere und benutzerfreundliche Oberfläche für die Mitgliederverwaltung
 * @version 2.0 - Verbesserte Sicherheit und UX
 * @author System Enhancement
 */

// Sichere Includes mit Fehlerbehandlung
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in mitgliederverwaltung.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Spezifische Styles für Mitgliederverwaltung
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
#mitgliederTable thead {
    position: sticky;
    top: 0;
    z-index: 4; /* Unter Navigation */
}

#mitgliederTable thead th {
    position: sticky;
    top: 0;
    background: var(--secondary-color);
    color: white;
    z-index: 4; /* Unter Navigation */
    border-bottom: 2px solid var(--secondary-color);
}

/* === TABELLEN BODY === */
#mitgliederTable tbody td {
    background: white;
    position: relative;
    z-index: 1; /* Niedrigster z-index */
}

/* Spezifische Spalten */
#mitgliederTable tbody td:nth-child(2),
#mitgliederTable tbody td:nth-child(12) {
    z-index: 1 !important;
}

/* Hover-Effekt für tbody */
#mitgliederTable tbody tr:hover td {
    background-color: rgba(108, 117, 125, 0.08);
}

/* Erweiterte Tabelle für mehr Spalten */

#mitgliederTable {
    min-width: 1400px;
    margin-bottom: 0;
}

/* === OPTIMIERTE SPALTENBREITEN === */
#mitgliederTable th:nth-child(1), #mitgliederTable td:nth-child(1) { min-width: 80px; } /* Lizenznr */
#mitgliederTable th:nth-child(2), #mitgliederTable td:nth-child(2) { min-width: 120px; } /* Name */
#mitgliederTable th:nth-child(3), #mitgliederTable td:nth-child(3) { min-width: 120px; } /* Vorname */
#mitgliederTable th:nth-child(4), #mitgliederTable td:nth-child(4) { min-width: 100px; } /* Geburtsdatum */
#mitgliederTable th:nth-child(5), #mitgliederTable td:nth-child(5) { min-width: 100px; } /* Waffe */
#mitgliederTable th:nth-child(6), #mitgliederTable td:nth-child(6) { min-width: 150px; } /* Strasse */
#mitgliederTable th:nth-child(7), #mitgliederTable td:nth-child(7) { min-width: 120px; } /* PLZ/Ort */
#mitgliederTable th:nth-child(8), #mitgliederTable td:nth-child(8) { min-width: 150px; } /* Email */
#mitgliederTable th:nth-child(9), #mitgliederTable td:nth-child(9) { min-width: 100px; } /* Telefon */
#mitgliederTable th:nth-child(10), #mitgliederTable td:nth-child(10) { min-width: 60px; } /* Aktiv */
#mitgliederTable th:nth-child(11), #mitgliederTable td:nth-child(11) { min-width: 60px; } /* Ehre */
#mitgliederTable th:nth-child(12), #mitgliederTable td:nth-child(12) { min-width: 60px; } /* Verstorben */
#mitgliederTable th:nth-child(13), #mitgliederTable td:nth-child(13) { min-width: 60px; } /* Aktionen */

/* === KOMPAKTERE TABELLE === */
#mitgliederTable th,
#mitgliederTable td {
    white-space: nowrap;
    font-size: 0.85rem; /* Etwas kleinere Schrift */
    padding: 0.4rem 0.4rem; /* Reduzierter Padding */
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

/* Einheitlicher Hover-Effekt für gesamte Zeile */
#mitgliederTable tbody tr:hover {
    background-color: rgba(108, 117, 125, 0.08) !important;
}

#mitgliederTable tbody tr:hover th,
#mitgliederTable tbody tr:hover td {
    background-color: rgba(108, 117, 125, 0.08) !important;
}

#mitgliederTable input,
#mitgliederTable select {
    font-size: 0.8rem; /* Kleinere Schrift */
    padding: 0.25rem 0.4rem; /* Weniger Padding */
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    transition: all 0.15s ease;
    height: 28px; /* Fixe Höhe */
}

#mitgliederTable input:focus,
#mitgliederTable select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.1);
    outline: none;
}

/* === KOMPAKTE ACTION BUTTONS === */
.btn-sm,
.deleteMitglied {
    padding: 0.2rem 0.4rem !important; /* Sehr kompakt */
    font-size: 0.75rem !important; /* Kleinere Schrift */
    border-radius: 0.25rem !important;
    line-height: 1.2 !important;
    height: 24px !important; /* Fixe kleine Höhe */
    min-width: auto !important;
}

/* Nur Icon für Löschen-Button */
.deleteMitglied {
    width: 28px !important;
    padding: 0.2rem !important;
}

.deleteMitglied i {
    font-size: 0.8rem !important;
}

/* Enhanced Modal Styling - Match endresultate.php Design */

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

.modal-footer .btn-outline-info {
    background: #0ea5e9 !important;
    color: white !important;
    border: 1px solid #0ea5e9 !important;
    box-shadow: 0 2px 6px rgba(14, 165, 233, 0.2) !important;
}

.modal-footer .btn-outline-info:hover {
    background: #0284c7 !important;
    border-color: #0284c7 !important;
    transform: translateY(-2px) scale(1.02) !important;
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3) !important;
}

.modal-footer .btn-outline-warning {
    background: #f59e0b !important;
    color: white !important;
    border: 1px solid #f59e0b !important;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.2) !important;
}

.modal-footer .btn-outline-warning:hover {
    background: #d97706 !important;
    border-color: #d97706 !important;
    transform: translateY(-2px) scale(1.02) !important;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3) !important;
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

/* === KOMPAKTE CHECKBOXEN === */
.form-check-input,
#mitgliederTable input[type='checkbox'] {
    width: 16px !important; /* Kleiner */
    height: 16px !important;
    min-width: 16px !important;
    min-height: 16px !important;
    border: 2px solid #dee2e6 !important;
    border-radius: 0.25rem !important;
    background-color: #ffffff !important;
    transition: all 0.2s ease !important;
    cursor: pointer !important;
    flex-shrink: 0;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
}

/* Checkbox Checked State mit Häkchen */
.form-check-input:checked,
#mitgliederTable input[type='checkbox']:checked {
    background-color: #28a745 !important;
    border-color: #28a745 !important;
    position: relative !important;
}

/* Häkchen Symbol angepasst */
.form-check-input:checked::after,
#mitgliederTable input[type='checkbox']:checked::after {
    content: 'âœ“' !important;
    position: absolute !important;
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -50%) !important;
    color: white !important;
    font-size: 12px !important; /* Kleiner */
    font-weight: bold !important;
    line-height: 1 !important;
}

.form-check-input:focus {
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1) !important;
    outline: none !important;
}

.form-check-input:hover {
    border-color: var(--secondary-color) !important;
    transform: scale(1.1) !important;
}

/* Table Checkboxen zentriert */
#mitgliederTable input[type='checkbox'] {
    margin: 0 auto !important;
    display: block !important;
}

/* Switch Styling für bessere Optik */
.form-switch .form-check-input {
    width: 2em !important;
    height: 1.2em !important;
    border-radius: 2em !important;
    background-color: #dee2e6 !important;
    border: 2px solid #dee2e6 !important;
}

.form-switch .form-check-input:checked {
    background-color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
}

/* Import Area Enhancement */
.import-area {
    border: 2px dashed #dee2e6;
    border-radius: 1rem;
    padding: 3rem;
    text-align: center;
    background: #ffffff;
    transition: all 0.3s ease;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
}

.import-area:hover {
    border-color: var(--primary-color);
    background: #f8fafe;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.import-area.dragging {
    border-color: #28a745;
    background: #d4edda;
    transform: scale(1.02);
}

.import-area i {
    color: var(--secondary-color);
    margin-bottom: 1rem;
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
        <div class="col-xl-10 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-people-fill me-2"></i>
                            Mitgliederverwaltung
                        </h2>
                        <p class="text-muted mb-0">Verwaltung der Vereinsmitglieder</p>
                    </div>
                </div>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <!-- Action Toolbar -->
                    <div class="row mb-2 mb-md-4">
                        <div class="col-md-6 d-none d-md-block">
                            <div class="search-wrapper">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Mitglieder durchsuchen...">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Button Toolbar -->
                            <div class="button-toolbar">
                                <div class="button-group">
                                    <button class="btn btn-compact-standard btn-outline-success" onclick="$('#newMemberModal').modal('show')">
                                        <i class="bi bi-person-plus me-2"></i>
                                        Neues Mitglied
                                    </button>
                                    <button class="btn btn-compact-standard btn-outline-primary" onclick="saveMitglieder()">
                                        <i class="bi bi-save me-2"></i>
                                        Speichern
                                    </button>
                                    <a href="mitgliederverwaltung/export_csv.php" class="btn btn-compact-standard btn-outline-info">
                                        <i class="bi bi-download me-2"></i>
                                        CSV Export
                                    </a>
                                    <button class="btn btn-compact-standard btn-outline-warning" onclick="$('#importModal').modal('show')">
                                        <i class="bi bi-upload me-2"></i>
                                        CSV Import
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabelle -->
                    <form id="mitgliederForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="table-wrapper">
                            <h5 class="table-title d-none d-md-block">
                                <i class="bi bi-people me-2"></i>
                                Mitglieder
                            </h5>

                            <!-- Desktop: Tabelle -->
                            <div class="desktop-table-container">
                                <div class="table-container">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0" id="mitgliederTable">
                                    <thead>
                                        <tr>
                                            <th scope="col">
                                                <i class="bi bi-hash me-1"></i>Lizenznr.
                                            </th>
                                            <th scope="col">Name</th>
                                            <th scope="col">Vorname</th>
                                            <th scope="col">Geburtsdatum</th>
                                            <th scope="col">Waffe</th>
                                            <th scope="col">Strasse</th>
                                            <th scope="col">PLZ/Ort</th>
                                            <th scope="col">Email</th>
                                            <th scope="col">Telefon</th>
                                            <th scope="col" class="text-center">Aktiv</th>
                                            <th scope="col" class="text-center">Ehre</th>
                                            <th scope="col" class="text-center">Verst.</th>
                                            <th scope="col" class="text-center">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody id="mitgliederTbody">
                                        <tr>
                                            <td colspan="12" class="text-center py-4">
                                                <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                                                Lade Mitglieder...
                                            </td>
                                        </tr>
                                    </tbody>
                                    </table>
                                </div>
                            </div>
                            </div>

                            <!-- Mobile: Cards -->
                            <div class="mobile-cards-container" id="mobileMitgliederContainer">
                                <div class="mobile-search">
                                    <div class="position-relative">
                                        <i class="bi bi-search search-icon"></i>
                                        <input type="text" class="form-control" placeholder="Mitglieder suchen..."
                                               oninput="filterMobileMitglieder(this)">
                                    </div>
                                </div>
                                <div class="mobile-cards-scroll" id="mobileMitgliederCards">
                                    <!-- Cards werden per JavaScript generiert -->
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal für neues Mitglied -->
<div class="modal fade" id="newMemberModal" tabindex="-1" aria-labelledby="newMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="newMemberModalLabel">
                    <i class="bi bi-person-plus me-2"></i>Neues Mitglied hinzufügen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form id="newMemberForm">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label for="memberId" class="form-label fw-bold">
                                <i class="bi bi-hash me-1"></i>Lizenznummer
                            </label>
                            <input type="number" class="form-control form-control-sm" id="memberId" name="id" required>
                        </div>
                        <div class="col-md-4">
                            <label for="memberVorname" class="form-label fw-bold">
                                <i class="bi bi-person me-1"></i>Vorname
                            </label>
                            <input type="text" class="form-control form-control-sm" id="memberVorname" name="vorname" required>
                        </div>
                        <div class="col-md-5">
                            <label for="memberName" class="form-label fw-bold">
                                <i class="bi bi-person-badge me-1"></i>Name
                            </label>
                            <input type="text" class="form-control form-control-sm" id="memberName" name="name" required>
                        </div>
                    </div>
                    
                    <div class="row g-2 mt-2">
                        <div class="col-md-6">
                            <label for="memberBirthday" class="form-label fw-bold">
                                <i class="bi bi-calendar3 me-1"></i>Geburtsdatum
                            </label>
                            <input type="date" class="form-control form-control-sm" id="memberBirthday" name="birthday" required>
                        </div>
                        <div class="col-md-6">
                            <label for="newWaffenSelect" class="form-label fw-bold">
                                <i class="bi bi-crosshair me-1"></i>Waffe
                            </label>
                            <select class="form-select form-select-sm" name="waffenid" id="newWaffenSelect" required>
                                <option value="">Bitte wählen...</option>
                            </select>
                        </div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <h6 class="text-secondary mb-2">
                        <i class="bi bi-geo-alt me-2"></i>Adresse
                    </h6>
                    
                    <div class="row g-2">
                        <div class="col-12">
                            <label for="memberStrasse" class="form-label fw-bold">Strasse</label>
                            <input type="text" class="form-control form-control-sm" id="memberStrasse" name="strasse">
                        </div>
                        <div class="col-md-4">
                            <label for="memberPlz" class="form-label fw-bold">PLZ</label>
                            <input type="text" class="form-control form-control-sm" id="memberPlz" name="plz">
                        </div>
                        <div class="col-md-8">
                            <label for="memberOrt" class="form-label fw-bold">Ort</label>
                            <input type="text" class="form-control form-control-sm" id="memberOrt" name="ort">
                        </div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <h6 class="text-secondary mb-2">
                        <i class="bi bi-telephone me-2"></i>Kontakt
                    </h6>
                    
                    <div class="row g-2">
                        <div class="col-12">
                            <label for="memberEmail" class="form-label fw-bold">
                                <i class="bi bi-envelope me-1"></i>Email
                            </label>
                            <input type="email" class="form-control form-control-sm" id="memberEmail" name="email">
                        </div>
                        <div class="col-md-6">
                            <label for="memberTelefon" class="form-label fw-bold">
                                <i class="bi bi-telephone me-1"></i>Telefon
                            </label>
                            <input type="text" class="form-control form-control-sm" id="memberTelefon" name="telefon">
                        </div>
                        <div class="col-md-6">
                            <label for="memberMobile" class="form-label fw-bold">
                                <i class="bi bi-phone me-1"></i>Mobile
                            </label>
                            <input type="text" class="form-control form-control-sm" id="memberMobile" name="mobile">
                        </div>
                        <div class="col-12">
                            <label for="memberNotizen" class="form-label fw-bold">
                                <i class="bi bi-chat-text me-1"></i>Notizen
                            </label>
                            <textarea class="form-control form-control-sm" id="memberNotizen" name="notizen" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="status" value="1" id="memberStatus" checked>
                                <label class="form-check-label fw-bold" for="memberStatus">
                                    <i class="bi bi-check-circle me-1"></i>Aktives Mitglied
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ehrenmitglied" value="1" id="memberEhre">
                                <label class="form-check-label fw-bold" for="memberEhre">
                                    <i class="bi bi-award me-1"></i>Ehrenmitglied
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Abbrechen
                    </button>
                    <button type="submit" class="btn btn-outline-success">
                        <i class="bi bi-save me-2"></i>Mitglied speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal für CSV Import -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="importModalLabel">
                    <i class="bi bi-upload me-2"></i>CSV Import
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info border-0" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                    <h6 class="alert-heading">
                        <i class="bi bi-info-circle me-2"></i>CSV Format
                    </h6>
                    <p class="mb-2">Die Datei sollte folgende Spalten enthalten (Trennzeichen: Semikolon):</p>
                    <code class="d-block p-2 bg-light rounded">ID;Vorname;Name;Geburtsdatum;WaffenID;Status;Ehrenmitglied;Strasse;PLZ;Ort;Email;Telefon;Mobile;Notizen;Verstorben</code>
                </div>
                
                <div class="import-area" id="dropZone">
                    <i class="bi bi-cloud-upload d-block"></i>
                    <h6 class="mt-3 mb-2">Datei hier ablegen oder klicken</h6>
                    <p class="text-muted">Unterstützte Formate: CSV</p>
                    <input type="file" id="csvFile" accept=".csv" style="display: none;">
                </div>
                
                <div id="importPreview" class="mt-4" style="display: none;">
                    <h6 class="text-secondary mb-3">
                        <i class="bi bi-eye me-2"></i>Vorschau der zu importierenden Daten:
                    </h6>
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-hover" id="previewTable">
                            <thead class="table-light"></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-outline-success" id="confirmImport" style="display: none;">
                    <i class="bi bi-check-circle me-2"></i>Import starten
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    // === GLOBALE VARIABLEN ===
    let csvData = null;
    let modalOpening = false;

    // === INITIALISIERUNG ===
    loadMitglieder();
    loadWaffen();
    setupEventHandlers();
    setupModalEventHandlers();
    setupCSVImport();

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

    // Mitglieder laden mit verbesserter Fehlerbehandlung
    function loadMitglieder() {
        $('#mitgliederTbody').html(`
            <tr>
                <td colspan="12" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                    Lade Mitglieder...
                </td>
            </tr>
        `);

        $.ajax({
            url: 'mitgliederverwaltung/load_mitglieder_form.php',
            type: 'GET',
            timeout: 10000,
            success: function(data) {
                $('#mitgliederTbody').html(data);
                msvToast('Mitglieder erfolgreich geladen', 'success');
                // Mobile Cards generieren
                buildMobileMitgliederCards();
            },
            error: function(xhr, status, error) {
                console.error('Fehler beim Laden der Mitglieder:', error);
                let errorMessage = 'Fehler beim Laden der Mitglieder';
                
                if (status === 'timeout') {
                    errorMessage = 'Zeitüberschreitung beim Laden der Daten';
                } else if (xhr.status === 404) {
                    errorMessage = 'Datei nicht gefunden';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server-Fehler';
                }
                
                $('#mitgliederTbody').html(`
                    <tr>
                        <td colspan="12" class="text-center text-danger py-4">
                            <i class="bi bi-exclamation-triangle me-2"></i> ${errorMessage}
                            <br><small>Status: ${xhr.status} - ${error}</small>
                        </td>
                    </tr>
                `);
                showMessage(errorMessage, 'danger');
            }
        });
    }

    // Waffen laden
    function loadWaffen() {
        // Versuche zuerst load_waffen_options.php (mit 's')
        $.get('mitgliederverwaltung/load_waffen_options.php', function(data) {
            $('#newWaffenSelect').html('<option value="">Bitte wählen...</option>' + data);
        }).fail(function() {
            // Fallback auf load_waffen_option.php (ohne 's')
            $.get('mitgliederverwaltung/load_waffen_option.php', function(data) {
                $('#newWaffenSelect').html('<option value="">Bitte wählen...</option>' + data);
            }).fail(function() {
                console.log('Waffenliste konnte nicht geladen werden - beide Dateien nicht gefunden');
                // Kein Toast bei Fehler, da es nur ein optionales Feature ist
            });
        });
    }

    // === EVENT HANDLERS ===
    function setupEventHandlers() {
        // Suche - angepasst für Input-Felder
        $('#searchInput').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $('#mitgliederTbody tr').filter(function() {
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

        // Neues Mitglied Form
        $('#newMemberForm').on('submit', saveNewMember);

        // Löschen mit verbesserter Bestätigung
        $(document).on('click', '.deleteMitglied', handleDeleteMember);
    }

    // === MODAL EVENT HANDLERS ===
    function setupModalEventHandlers() {
        // Verhindere mehrfaches Öffnen
        $('#newMemberModal').on('show.bs.modal', function(e) {
            if (modalOpening) {
                e.preventDefault();
                return false;
            }
            modalOpening = true;
        });
        
        $('#newMemberModal').on('shown.bs.modal', function() {
            modalOpening = false;
            // Fokus auf erstes Eingabefeld setzen
            $('#memberId').focus();
        });
        
        $('#newMemberModal').on('hidden.bs.modal', function() {
            // Form zurücksetzen
            $('#newMemberForm')[0].reset();
        });

        // Import Modal
        $('#importModal').on('hidden.bs.modal', function() {
            $('#importPreview').hide();
            $('#confirmImport').hide();
            csvData = null;
        });
    }

    // === SPEICHER-FUNKTIONEN ===

    // Mitglieder speichern
    window.saveMitglieder = function() {
        const $saveBtn = $('button[onclick="saveMitglieder()"]');
        const originalText = $saveBtn.html();
        $saveBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
        
        $.ajax({
            url: 'mitgliederverwaltung/save_mitglieder.php',
            type: 'POST',
            data: $('#mitgliederForm').serialize(),
            success: function(response) {
                msvToast('Alle Änderungen erfolgreich gespeichert!', 'success');
                setTimeout(() => loadMitglieder(), 1000);
            },
            error: function(xhr, status, error) {
                msvToast('Fehler beim Speichern der Änderungen', 'error');
            },
            complete: function() {
                $saveBtn.prop('disabled', false).html(originalText);
            }
        });
    };

    // Neues Mitglied speichern
    function saveNewMember(e) {
        e.preventDefault();
        
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
        
        $.ajax({
            url: 'mitgliederverwaltung/add_mitglied.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        msvToast('Neues Mitglied erfolgreich hinzugefügt!', 'success');
                        $('#newMemberModal').modal('hide');
                        $('#newMemberForm')[0].reset();
                        setTimeout(() => loadMitglieder(), 1000);
                    } else {
                        msvToast('Fehler: ' + (result.message || 'Unbekannter Fehler'), 'error');
                    }
                } catch (e) {
                    msvToast('Neues Mitglied erfolgreich hinzugefügt!', 'success');
                    $('#newMemberModal').modal('hide');
                    $('#newMemberForm')[0].reset();
                    setTimeout(() => loadMitglieder(), 1000);
                }
            },
            error: function(xhr, status, error) {
                msvToast('Verbindungsfehler beim Hinzufügen des Mitglieds', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }

    // Mitglied löschen
    async function handleDeleteMember() {
        const id = $(this).data('id');
        // Desktop: Name aus Tabellenzellen; Mobile: Name aus Card-Header
        let memberName = '';
        const $tr = $(this).closest('tr');
        if ($tr.length) {
            memberName = $tr.find('td:nth-child(2)').text() + ' ' + $tr.find('td:nth-child(3)').text();
        } else {
            const $card = $(this).closest('.mobile-card');
            memberName = $card.find('.mobile-card-header .fw-bold').text();
        }

        const result = await msvConfirmDelete(`Mitglied "${memberName}"`);
        if (!result.isConfirmed) return;

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: 'mitgliederverwaltung/delete_mitglied.php',
            type: 'POST',
            data: {
                id: id,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                msvToast('Mitglied erfolgreich gelöscht', 'success');
                setTimeout(() => loadMitglieder(), 1000);
            },
            error: function(xhr, status, error) {
                msvToast('Fehler beim Löschen des Mitglieds', 'error');
                $btn.prop('disabled', false).html(originalText);
            }
        });
    }

    // === CSV IMPORT FUNKTIONALITÄT ===
    function setupCSVImport() {
        const dropZone = document.getElementById('dropZone');
        
        if (!dropZone) return;

        dropZone.onclick = () => document.getElementById('csvFile').click();

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragging'));
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragging'));
        });

        dropZone.addEventListener('drop', e => handleFiles(e.dataTransfer.files));
        $('#csvFile').on('change', e => handleFiles(e.target.files));

        $('#confirmImport').on('click', confirmImport);
    }

    function handleFiles(files) {
        if (files.length > 0 && files[0].name.endsWith('.csv')) {
            parseCSV(files[0]);
        } else {
            msvToast('Bitte nur CSV-Dateien hochladen', 'warning');
        }
    }

    function parseCSV(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const lines = e.target.result.split('\n').filter(line => line.trim());
            
            if (lines.length < 2) {
                msvToast('CSV-Datei ist leer', 'error');
                return;
            }
            
            csvData = [];
            const headers = lines[0].split(';');
            
            for (let i = 1; i < lines.length; i++) {
                const values = lines[i].split(';');
                const row = {};
                headers.forEach((header, index) => {
                    row[header.trim()] = values[index]?.trim() || '';
                });
                csvData.push(row);
            }
            
            showPreview(headers, csvData.slice(0, 5));
            msvToast(`${csvData.length} Datensätze erkannt`, 'info');
        };
        reader.readAsText(file, 'UTF-8');
    }

    function showPreview(headers, data) {
        const thead = $('#previewTable thead').empty();
        const tbody = $('#previewTable tbody').empty();
        
        thead.append('<tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr>');
        
        data.forEach(row => {
            tbody.append('<tr>' + headers.map(h => `<td>${row[h.trim()] || ''}</td>`).join('') + '</tr>');
        });
        
        $('#importPreview').show();
        $('#confirmImport').show();
    }

    function confirmImport() {
        if (!csvData) return;
        
        const $btn = $('#confirmImport');
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Importiere...');
        
        $.ajax({
            url: 'mitgliederverwaltung/import_csv.php',
            type: 'POST',
            data: {
                csvData: JSON.stringify(csvData),
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                try {
                    const res = JSON.parse(response);
                    msvToast(`Import erfolgreich: ${res.imported} neue Mitglieder, ${res.updated} aktualisiert`, 'success');
                } catch (e) {
                    msvToast('Import erfolgreich abgeschlossen', 'success');
                }
                $('#importModal').modal('hide');
                $('#importPreview').hide();
                csvData = null;
                setTimeout(() => loadMitglieder(), 1000);
            },
            error: function(xhr, status, error) {
                msvToast('Fehler beim Import der Daten', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    }

});

// === MOBILE OPTIMIERUNG ===
function buildMobileMitgliederCards() {
    const isMobile = window.matchMedia('(max-width: 767.98px)');
    if (!isMobile.matches) return;

    const container = document.getElementById('mobileMitgliederCards');
    if (!container) return;

    const tbody = document.querySelector('#mitgliederTable tbody');
    const rows = tbody.querySelectorAll('tr');

    let html = '';
    rows.forEach((row, idx) => {
        const inputs = row.querySelectorAll('input, select');
        if (inputs.length < 13) return; // Skip loading rows

        // Daten extrahieren
        const id = inputs[0].value || '';
        const name = inputs[1].value || '';
        const vorname = inputs[2].value || '';
        const geburtsdatum = inputs[3].value || '';
        const waffenSelect = inputs[4];
        const strasse = inputs[5].value || '';
        const plz = inputs[6].value || '';
        const ort = inputs[7].value || '';
        const email = inputs[8].value || '';
        const telefon = inputs[9].value || '';
        const aktiv = inputs[10].checked;
        const ehre = inputs[11].checked;
        const verstorben = inputs[12].checked;

        const waffentext = waffenSelect.options[waffenSelect.selectedIndex]?.text || '';

        // Card HTML generieren
        html += `
        <div class="mobile-card" data-member-id="${id}">
            <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                <div>
                    <div class="fw-bold">${name} ${vorname}</div>
                    <small class="text-muted">Lizenz: ${id} | ${waffentext}</small>
                    <div class="mt-1">
                        <span class="badge ${aktiv ? 'bg-success' : 'bg-secondary'} me-1">
                            ${aktiv ? '✓ Aktiv' : 'Inaktiv'}
                        </span>
                        ${ehre ? '<span class="badge bg-warning text-dark me-1">★ Ehrenmitglied</span>' : ''}
                        ${verstorben ? '<span class="badge bg-dark">† Verstorben</span>' : ''}
                    </div>
                </div>
                <i class="bi bi-chevron-down"></i>
            </div>
            <div class="mobile-card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Vorname</label>
                    <input type="text" class="form-control" name="${inputs[2].name}" value="${vorname}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Name</label>
                    <input type="text" class="form-control" name="${inputs[1].name}" value="${name}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Lizenznummer</label>
                    <input type="text" class="form-control" name="${inputs[0].name}" value="${id}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Geburtsdatum</label>
                    <input type="date" class="form-control" name="${inputs[3].name}" value="${geburtsdatum}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Waffe</label>
                    <select class="form-select" name="${waffenSelect.name}">
                        ${Array.from(waffenSelect.options).map(opt =>
                            `<option value="${opt.value}" ${opt.selected ? 'selected' : ''}>${opt.text}</option>`
                        ).join('')}
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Strasse</label>
                    <input type="text" class="form-control" name="${inputs[5].name}" value="${strasse}">
                </div>
                <div class="row">
                    <div class="col-4 mb-3">
                        <label class="form-label fw-bold">PLZ</label>
                        <input type="text" class="form-control" name="${inputs[6].name}" value="${plz}">
                    </div>
                    <div class="col-8 mb-3">
                        <label class="form-label fw-bold">Ort</label>
                        <input type="text" class="form-control" name="${inputs[7].name}" value="${ort}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <input type="email" class="form-control" name="${inputs[8].name}" value="${email}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Telefon</label>
                    <input type="tel" class="form-control" name="${inputs[9].name}" value="${telefon}">
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="${inputs[10].name}" value="1" ${aktiv ? 'checked' : ''}>
                        <label class="form-check-label fw-bold">Aktives Mitglied</label>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="${inputs[11].name}" value="1" ${ehre ? 'checked' : ''}>
                        <label class="form-check-label fw-bold">Ehrenmitglied</label>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="${inputs[12].name}" value="1" ${verstorben ? 'checked' : ''}>
                        <label class="form-check-label fw-bold">Verstorben</label>
                    </div>
                </div>
                <button type="button" class="btn btn-danger w-100 deleteMitglied" data-id="${id}">
                    <i class="bi bi-trash me-2"></i>Mitglied löschen
                </button>
            </div>
        </div>`;
    });

    container.innerHTML = html || '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Mitglieder vorhanden</div></div>';
}

function filterMobileMitglieder(input) {
    const query = input.value.toLowerCase();
    const cards = document.querySelectorAll('#mobileMitgliederCards .mobile-card');

    cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(query) ? '' : 'none';
    });
}

// Responsive: Cards bei Resize neu generieren
const isMobile = window.matchMedia('(max-width: 767.98px)');
isMobile.addEventListener('change', function() {
    if (isMobile.matches) {
        buildMobileMitgliederCards();
    }
});
</script>

<style>
/* Mobile-Optimierung für Mitgliederverwaltung */
@media (max-width: 767.98px) {
    /* WCAG AAA Touch Targets: Alle Form-Elemente */
    .form-control,
    .form-select {
        min-height: 48px !important;
        font-size: 16px !important; /* Verhindert iOS Auto-Zoom */
    }

    /* Alle Buttons */
    .btn {
        min-height: 48px !important;
        font-size: 16px !important;
        padding: 0.5rem 1rem !important;
    }

    /* Modal Buttons */
    .modal-footer .btn {
        min-height: 48px !important;
        font-size: 16px !important;
    }

    /* Inputs in Mobile Cards größer */
    .mobile-card-body .form-control,
    .mobile-card-body .form-select {
        min-height: 48px !important;
        font-size: 16px !important;
    }

    /* Form-Switches größer */
    .mobile-card-body .form-check-input {
        width: 3em !important;
        height: 1.5em !important;
    }

    /* Löschen-Button prominent */
    .mobile-card-body .deleteMitglied {
        min-height: 48px !important;
        font-size: 1rem !important;
        margin-top: 1rem;
    }

    /* Labels bold */
    .mobile-card-body .form-label {
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }

    /* Search Input */
    .search-wrapper .form-control {
        min-height: 48px !important;
        font-size: 16px !important;
    }

    /* Compact Action Buttons im Header */
    .btn-compact-standard {
        min-height: 48px !important;
        font-size: 14px !important;
        padding: 0.5rem 0.75rem !important;
    }
}
</style>

<?php include 'footer.inc.php'; ?>