<?php
// wanderpreise.php - Hauptseite für Wanderpreise-Verwaltung
require_once 'wanderpreise/wanderpreise_config.php';
require_once 'dbconnect.inc.php';

// Session-Kontrolle
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Alle Styles sind jetzt zentral in msv-styles.css verwaltet
$page_specific_css = '';

include 'header.inc.php';

// Debug-Info nur in Development anzeigen
if (WANDERPREISE_DEBUG) {
    echo '<!-- Debug Mode: ON -->';
}
?>

<!-- Select2 CSS für suchbare Dropdowns -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
    rel="stylesheet" />

<!-- Header -->
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-10 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-award me-2"></i>
                            Wanderpreise verwalten
                        </h2>
                        <p class="text-muted mb-0" style="font-size: 0.85rem;">Wanderpreise erfassen und Gewinner
                            zuordnen</p>
                        <div id="message" class="mt-2"></div>
                    </div>
                </div>

                <!-- Weißer Container für den Rest -->
                <div class="content-background">

                    <!-- Verwaltungs-Buttons -->
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <h6 class="text-muted mb-2">
                                <i class="bi bi-tools me-1"></i> Verwaltung
                            </h6>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                            <button class="btn btn-compact btn-primary w-100" data-bs-toggle="modal"
                                data-bs-target="#addWanderpreisModal">
                                <i class="bi bi-plus-circle me-1"></i>
                                <span>Neuer Wanderpreis</span>
                            </button>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                            <button type="button" id="zuordnungButton" class="btn btn-compact btn-success w-100"
                                data-bs-toggle="modal" data-bs-target="#zuordnungModal">
                                <i class="bi bi-link-45deg me-1"></i>
                                <span>Gewinner zuordnen</span>
                            </button>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                            <button type="button" id="autoZuordnungButton" class="btn btn-compact btn-warning w-100">
                                <i class="bi bi-magic me-1"></i>
                                <span>Auto-Zuordnung</span>
                            </button>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                            <button type="button" id="vergangeneGewinnerButton" class="btn btn-compact btn-info w-100"
                                data-bs-toggle="modal" data-bs-target="#vergangeneGewinnerModal">
                                <i class="bi bi-calendar-plus me-1"></i>
                                <span>Vergangene Gewinner</span>
                            </button>
                        </div>
                    </div>

                    <!-- Export-Buttons -->
                    <div class="row g-2 mb-4">
                        <div class="col-12">
                            <h6 class="text-muted mb-2">
                                <i class="bi bi-download me-1"></i> Export & Berichte
                            </h6>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                            <button type="button" class="btn btn-compact btn-success w-100 export-btn"
                                data-export-type="csv">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i>
                                <span>CSV Export</span>
                            </button>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                            <button type="button" class="btn btn-compact btn-danger w-100 export-btn"
                                data-export-type="pdf-all">
                                <i class="bi bi-file-earmark-pdf me-1"></i>
                                <span>PDF Alle</span>
                            </button>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                            <button type="button" class="btn btn-compact btn-warning w-100 export-btn"
                                data-export-type="pdf-schnitzerei">
                                <i class="bi bi-file-earmark-pdf me-1"></i>
                                <span>PDF Schnitzerei</span>
                            </button>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                            <button type="button" class="btn btn-compact btn-info w-100 export-btn"
                                data-export-type="pdf-akura">
                                <i class="bi bi-file-earmark-pdf me-1"></i>
                                <span>PDF Akura</span>
                            </button>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                            <button type="button" class="btn btn-compact btn-primary w-100 export-btn"
                                data-export-type="pdf-jm">
                                <i class="bi bi-file-earmark-pdf me-1"></i>
                                <span>JM Preise</span>
                            </button>
                        </div>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2-4">
                            <button type="button" class="btn btn-compact btn-secondary w-100 export-btn"
                                data-export-type="pdf-mitglieder-info">
                                <i class="bi bi-people-fill me-1"></i>
                                <span>PDF Mitglieder-Info</span>
                            </button>
                        </div>
                    </div>

                    <!-- Wanderpreise Liste -->
                    <div class="table-wrapper mb-4">
                        <h5 class="table-title">
                            <i class="bi bi-list me-2"></i>
                            <span id="wanderpreisListTitle">Wanderpreise Übersicht</span>
                        </h5>
                        <div class="table-responsive">
                            <div id="wanderpreisTableContainer">
                                <div class="p-4 text-center">
                                    <div class="spinner-border spinner-border-sm me-2"
                                        style="color: var(--secondary-color);"></div>
                                    Lade Wanderpreise...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal für neuen Wanderpreis hinzufügen -->
<div class="modal fade" id="addWanderpreisModal" tabindex="-1" aria-labelledby="addWanderpreisModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addWanderpreisModalLabel">
                    <i class="bi bi-plus-circle"></i> Neuen Wanderpreis erfassen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <form id="addWanderpreisForm" method="post">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="bezeichnung" class="form-label">
                                <i class="bi bi-tag me-1"></i>Bezeichnung:
                            </label>
                            <input type="text" id="bezeichnung" name="bezeichnung" class="form-control" required
                                placeholder="z.B. Wanderbecher SV Muster">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="beschreibung" class="form-label">
                                <i class="bi bi-text-paragraph me-1"></i>Beschreibung:
                            </label>
                            <textarea id="beschreibung" name="beschreibung" class="form-control" rows="3"
                                placeholder="Detaillierte Beschreibung des Wanderpreises..."></textarea>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="beschaffung_jahr" class="form-label">
                                <i class="bi bi-calendar-date me-1"></i>Jahr:
                            </label>
                            <input type="number" id="beschaffung_jahr" name="beschaffung_jahr" class="form-control"
                                min="1900" max="2100" value="<?= date('Y') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="min_anzahl_gewinne" class="form-label">
                                <i class="bi bi-hash me-1"></i>Min. Gewinne:
                            </label>
                            <input type="number" id="min_anzahl_gewinne" name="min_anzahl_gewinne" class="form-control"
                                min="1" value="3" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="hersteller" class="form-label">
                                <i class="bi bi-building me-1"></i>Hersteller:
                            </label>
                            <select id="hersteller" name="hersteller" class="form-control">
                                <option value="">-- Auswählen --</option>
                                <option value="Schnitzerei Heinz Schild">Schnitzerei Heinz Schild</option>
                                <option value="Akura Einsiedeln">Akura Einsiedeln</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="auto_verknuepfung"
                                    name="auto_verknuepfung">
                                <label class="form-check-label" for="auto_verknuepfung">
                                    <i class="bi bi-magic me-1"></i>Auto-Zuordnung aktivieren
                                </label>
                            </div>
                            <div class="row mt-2" id="verknuepfung_details" style="display: none;">
                                <div class="col-6">
                                    <select class="form-control" id="verknuepfung_regel" name="verknuepfung_regel">
                                        <option value="">Regel...</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control" id="verknuepfung_jahr"
                                        name="verknuepfung_jahr" placeholder="Jahr">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="submit" form="addWanderpreisForm" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Speichern
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Gewinner-Zuordnung - MIT SUCHBAREN DROPDOWNS -->
<div class="modal fade" id="zuordnungModal" tabindex="-1" aria-labelledby="zuordnungModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="zuordnungModalLabel">
                    <i class="bi bi-person-check"></i> Gewinner zuordnen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <form id="zuordnungForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_wanderpreis" class="form-label">Wanderpreis:</label>
                            <select id="modal_wanderpreis" name="wanderpreis_id" class="form-control" required>
                                <option value="">Wanderpreis auswählen...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_jahr" class="form-label">Jahr:</label>
                            <input type="number" id="modal_jahr" name="jahr" class="form-control" min="1900" max="2100"
                                value="<?= date('Y') ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_gewinner" class="form-label">Gewinner:</label>
                            <select id="modal_gewinner" name="gewinner_id" class="form-select searchable-select"
                                required>
                                <option value="">Mitglied suchen/auswählen...</option>
                            </select>
                            <small class="text-muted">Tippe um nach Namen zu suchen</small>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_rang" class="form-label">Rang/Resultat:</label>
                            <input type="text" id="modal_rang" name="rang" class="form-control"
                                placeholder="z.B. 1. Rang oder 98 Punkte">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="modal_bemerkung" class="form-label">Bemerkung:</label>
                            <textarea id="modal_bemerkung" name="bemerkung" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </form>

                <!-- Bisherige Gewinner anzeigen -->
                <div class="mt-4">
                    <h6>Bisherige Gewinner:</h6>
                    <div id="bisherige_gewinner_container">
                        <p class="text-muted">Wähle einen Wanderpreis aus...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-primary" id="saveZuordnung">
                    <i class="bi bi-save me-1"></i>Zuordnung speichern
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal zur Bestätigung für das Löschen -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="bi bi-exclamation-triangle"></i> Bestätigung erforderlich
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle text-warning me-3" style="font-size: 2rem;"></i>
                    <div>
                        <strong>Möchtest du diesen Wanderpreis wirklich löschen?</strong>
                        <br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-outline-danger" id="confirmAction">
                    <i class="bi bi-trash me-1"></i>Löschen bestätigen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container"
    style="position: fixed; top: 80px; right: 20px; z-index: 9999; pointer-events: none; display: flex; flex-direction: column; align-items: flex-end;">
</div>

<!-- Modal für Export Jahr-Auswahl -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">
                    <i class="bi bi-download"></i> Export auswählen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-12">
                        <label for="modalExportJahr" class="form-label fw-bold">
                            <i class="bi bi-calendar3 me-1"></i> Jahr für Export auswählen:
                        </label>
                        <input type="number" id="modalExportJahr" class="form-control" min="1900" max="2100"
                            value="<?= date('Y') ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <p class="text-muted mb-0" style="font-size: 0.9rem;">
                            <i class="bi bi-info-circle me-1"></i>
                            Wähle das Jahr aus, für das du die Daten exportieren möchtest.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-primary" id="startExport">
                    <i class="bi bi-download me-1"></i>Export starten
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal für vergangene Gewinner - MIT SUCHBAREN DROPDOWNS -->
<div class="modal fade" id="vergangeneGewinnerModal" tabindex="-1" aria-labelledby="vergangeneGewinnerModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="vergangeneGewinnerModalLabel">
                    <i class="bi bi-calendar-plus"></i> Vergangene Gewinner eintragen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <form id="vergangeneGewinnerForm">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="vg_wanderpreis" class="form-label">Wanderpreis:</label>
                            <select id="vg_wanderpreis" name="wanderpreis_id" class="form-control" required>
                                <option value="">Wanderpreis auswählen...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="vg_jahr" class="form-label">Jahr:</label>
                            <input type="number" id="vg_jahr" name="jahr" class="form-control" min="1900" max="2100"
                                value="<?= date('Y') - 1 ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="vg_gewinner" class="form-label">Gewinner:</label>
                            <select id="vg_gewinner" name="gewinner_id" class="form-select searchable-select" required>
                                <option value="">Mitglied suchen/auswählen...</option>
                            </select>
                            <small class="text-muted">Tippe um nach Namen zu suchen</small>
                        </div>
                        <div class="col-md-6">
                            <label for="vg_rang" class="form-label">Rang/Resultat:</label>
                            <input type="text" id="vg_rang" name="rang" class="form-control"
                                placeholder="z.B. 1. Rang oder 98 Punkte">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="vg_resultat" class="form-label">Resultat (optional):</label>
                            <input type="text" id="vg_resultat" name="resultat" class="form-control"
                                placeholder="z.B. 385 Punkte">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="vg_bemerkung" class="form-label">Bemerkung (optional):</label>
                            <textarea id="vg_bemerkung" name="bemerkung" class="form-control" rows="2"
                                placeholder="z.B. Historische Aufzeichnung"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-primary" id="saveVergangenerGewinner">
                    <i class="bi bi-save me-1"></i>Gewinner eintragen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Wanderpreis-Details/Historie -->
<div class="modal fade" id="wanderpreisHistorieModal" tabindex="-1" aria-labelledby="wanderpreisHistorieModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="wanderpreisHistorieModalLabel">
                    <i class="bi bi-clock-history"></i> Wanderpreis-Historie
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <!-- Wanderpreis-Info -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6 class="card-title mb-2" id="historie_bezeichnung">
                                            <i class="bi bi-award me-2"></i>Wanderpreis-Bezeichnung
                                        </h6>
                                        <p class="card-text text-muted mb-1" id="historie_beschreibung">Beschreibung...
                                        </p>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i><span id="historie_jahr">Jahr</span> •
                                            <i class="bi bi-building me-1"></i><span
                                                id="historie_hersteller">Hersteller</span>
                                        </small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="d-flex flex-column align-items-end">
                                            <span class="badge bg-primary mb-2" id="historie_status">Status</span>
                                            <small class="text-muted">Min. <span id="historie_min_gewinne">3</span>
                                                Gewinne</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gewinner-Historie Tabelle -->
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-muted mb-3">
                            <i class="bi bi-people me-1"></i> Alle Gewinner
                            <span class="badge bg-secondary ms-2" id="historie_count">0</span>
                        </h6>

                        <div class="table-responsive">
                            <div id="historieTableContainer">
                                <div class="p-4 text-center">
                                    <div class="spinner-border spinner-border-sm me-2"
                                        style="color: var(--secondary-color);"></div>
                                    Lade Historie...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Schließen
                </button>
                <button type="button" class="btn btn-primary" id="exportHistoryBtn">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Historie als PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Wanderpreis-Bearbeitung -->
<div class="modal fade" id="editWanderpreisModal" tabindex="-1" aria-labelledby="editWanderpreisModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editWanderpreisModalLabel">
                    <i class="bi bi-pencil"></i> Wanderpreis bearbeiten
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <form id="editWanderpreisForm">
                    <input type="hidden" id="edit_wanderpreis_id" name="wanderpreis_id">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="edit_bezeichnung" class="form-label">
                                <i class="bi bi-tag me-1"></i>Bezeichnung:
                            </label>
                            <input type="text" id="edit_bezeichnung" name="bezeichnung" class="form-control" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="edit_beschreibung" class="form-label">
                                <i class="bi bi-text-paragraph me-1"></i>Beschreibung:
                            </label>
                            <textarea id="edit_beschreibung" name="beschreibung" class="form-control"
                                rows="3"></textarea>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_beschaffung_jahr" class="form-label">
                                <i class="bi bi-calendar-date me-1"></i>Anschaffungsjahr:
                            </label>
                            <input type="number" id="edit_beschaffung_jahr" name="beschaffung_jahr" class="form-control"
                                min="1900" max="2100" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_min_anzahl_gewinne" class="form-label">
                                <i class="bi bi-hash me-1"></i>Min. Anzahl Gewinne bis definitiv:
                            </label>
                            <input type="number" id="edit_min_anzahl_gewinne" name="min_anzahl_gewinne"
                                class="form-control" min="1" required>
                        </div>
                    </div>

                    <!-- Hersteller-Information Edit -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="edit_hersteller" class="form-label">
                                <i class="bi bi-building me-1"></i>Hersteller:
                            </label>
                            <select id="edit_hersteller" name="hersteller" class="form-control">
                                <option value="">-- Bitte auswählen --</option>
                                <option value="Schnitzerei Heinz Schild">Schnitzerei Heinz Schild</option>
                                <option value="Akura Einsiedeln">Akura Einsiedeln</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_auto_verknuepfung"
                                    name="auto_verknuepfung">
                                <label class="form-check-label" for="edit_auto_verknuepfung">
                                    <i class="bi bi-magic me-1"></i>Automatische Zuordnung aktivieren
                                </label>

                            </div>
                            <div class="row g-3 mt-2" id="edit_verknuepfung_details" style="display: none;">
                                <div class="col-12 col-md-6">
                                    <label for="edit_verknuepfung_regel" class="form-label">Regel</label>
                                    <select class="form-select" id="edit_verknuepfung_regel" name="verknuepfung_regel">
                                        <option value="">Regel auswählen...</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="edit_verknuepfung_jahr" class="form-label">Jahr</label>
                                    <input type="number" class="form-control" id="edit_verknuepfung_jahr"
                                        name="verknuepfung_jahr" placeholder="z. B. 2025" min="1900" max="2100"
                                        step="1">
                                </div>
                            </div>

                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-primary" id="saveEditWanderpreis">
                    <i class="bi bi-save me-1"></i>Änderungen speichern
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Modal: Auto-Zuordnung bestätigen -->
<div class="modal fade" id="autoZuordnungModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Automatische Zuordnung starten?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <p>Das ordnet die Gewinner gemäss Regeln zu.</p>
                <p class="mb-0">
                    <strong>Jahr:</strong> <span class="auto-year fw-semibold"></span>
                </p>
                <small class="text-muted">Hinweis: Option C aktiv – 0/leer = alle Jahre.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" id="confirmAutoZuordnung">
                    Ja, starten
                </button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="confirmDeleteHistorieModal" tabindex="-1" aria-labelledby="confirmDeleteHistorieLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="confirmDeleteHistorieLabel"><i class="bi bi-exclamation-triangle"></i>
                    Wirklich löschen?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <p id="confirmDeleteHistorieText">Soll dieser Eintrag wirklich gelöscht werden?</p>
                <input type="hidden" id="deleteHistorieId" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteHistorieYes">
                    <i class="bi bi-trash"></i> Ja, löschen
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Select2 JavaScript für suchbare Dropdowns -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function () {

        // Hilfsfunktion: finde die nächste freie Jahreszahl unterhalb startYear
        function getNextFreeYear(usedYears, startYear) {
            const used = new Set(usedYears.map(Number).filter(Boolean));
            let y = startYear;
            while (y >= 1900 && used.has(y)) y--;
            return y >= 1900 ? y : '';
        }

        // Auto-Vorschlag für Jahr beim Auswählen des Wanderpreises (Vergangener Gewinner)
        $(document).on('change', '#vg_wanderpreis', function () {
            const wpId = Number($(this).val() || 0);
            const yearFieldSel = '#vg_jahr';            // <- falls Dein Feld anders heisst, hier anpassen
            if (!wpId) { $(yearFieldSel).val(''); return; }

            const currentYear = new Date().getFullYear();
            const startYear = currentYear - 1;          // aktuelles Jahr nie vorschlagen

            $.getJSON('wanderpreise/get_wanderpreis_historie.php', { wanderpreis_id: wpId })
                .done(function (resp) {
                    if (!resp || !resp.success) return;
                    const usedYears = (resp.gewinner || []).map(g => Number(g.jahr));
                    const suggested = getNextFreeYear(usedYears, startYear);

                    // Input oder Select? – beides unterstützen
                    const $year = $(yearFieldSel);
                    $year.val(suggested);
                    // falls Select2/Select: .trigger('change') nicht vergessen
                    if ($year.is('select')) $year.trigger('change');
                })
                .fail(function () {
                    // optional: showToast('Jahre konnten nicht geladen werden', 'error');
                });
        });

        // Beim Öffnen des Modals einmalig initialisieren (falls WP schon vorausgewählt ist)
        $('#vergangeneGewinnerModal').on('shown.bs.modal', function () {
            const wpId = Number($('#vg_wanderpreis').val() || 0);
            if (wpId) $('#vg_wanderpreis').trigger('change');
        });

        var wanderpreisId = null;
        var currentExportType = null;

        // Toast Container hinzufügen falls nicht vorhanden
        if ($('#toast-container').length === 0) {
            $('body').append('<div id="toast-container" style="position: fixed; top: 80px; right: 20px; z-index: 9999; pointer-events: none; display: flex; flex-direction: column; align-items: flex-end;"></div>');
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
                    'padding': '12px 16px',
                    'margin-bottom': '8px',
                    'border-radius': '6px',
                    'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                    'opacity': '0',
                    'transform': 'translateX(100%)',
                    'transition': 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
                    'font-weight': '500',
                    'display': 'flex',
                    'align-items': 'center',
                    'min-width': '280px',
                    'max-width': '400px',
                    'width': 'auto',
                    'word-wrap': 'break-word',
                    'font-size': '14px',
                    'line-height': '1.4',
                    'pointer-events': 'auto',
                    'position': 'relative'
                })
                .html(`<i class="bi ${icons[type]} me-2" style="flex-shrink: 0;"></i><span>${message}</span>`);

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

        // Export-Button Klick Handler
        $('.export-btn').on('click', function () {
            currentExportType = $(this).data('export-type');

            // Modal-Titel anpassen je nach Export-Typ
            var exportTitle = '';
            switch (currentExportType) {
                case 'csv':
                    exportTitle = 'CSV Export';
                    break;
                case 'pdf-all':
                    exportTitle = 'PDF Export - Alle Wanderpreise';
                    break;
                case 'pdf-schnitzerei':
                    exportTitle = 'PDF Export - Schnitzerei Heinz Schild';
                    break;
                case 'pdf-akura':
                    exportTitle = 'PDF Export - Akura Einsiedeln';
                    break;
                case 'pdf-jm':
                    exportTitle = 'PDF Export - JM Preise';
                    break;
                case 'pdf-mitglieder-info':
                    exportTitle = 'PDF Export - Mitglieder-Info';
                    break;
            }

            $('#exportModalLabel').html('<i class="bi bi-download"></i> ' + exportTitle);
            $('#exportModal').modal('show');
        });

        // Export starten
$('#startExport').on('click', function () {
    var jahr = $('#modalExportJahr').val();

    if (!jahr || jahr < 1900 || jahr > 2100) {
        showToast('Bitte ein gültiges Jahr eingeben (1900-2100)', 'error');
        return;
    }

    var $btn = $(this);
    var originalText = $btn.html();
    $btn.prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-2"></span>Exportiere...');

    // Export ausführen basierend auf currentExportType
    if (currentExportType === 'csv') {
        // CSV Export
        window.open('wanderpreise/export_wanderpreise.php?jahr=' + jahr, '_blank');
        showToast('CSV Export gestartet!', 'success');
        $('#exportModal').modal('hide');
        $btn.prop('disabled', false).html(originalText);
        
    } else {
        // PDF Export - Einheitlicher Ansatz für alle PDF-Typen
        var params = {
            type: 'jahresreport',
            year: jahr
        };

        // Spezielle Parameter je nach Export-Typ
        switch(currentExportType) {
            case 'pdf-schnitzerei':
                params.hersteller = 'Schnitzerei Heinz Schild';
                break;
            case 'pdf-akura':
                params.hersteller = 'Akura Einsiedeln';
                break;
            case 'pdf-jm':
                params.type = 'top3';
                break;
            case 'pdf-mitglieder-info':
                params.type = 'mitglieder-info';
                break;
            // pdf-all: keine zusätzlichen Parameter
        }

        $.ajax({
            url: 'wanderpreise/generate_wanderpreise_jahresreport.php',
            type: 'GET',
            dataType: 'json',
            data: params,
            success: function (response) {
                if (response.pdf_link) {
                    // Debugging: Log the response to see what we're getting
                    console.log('PDF Link received:', response.pdf_link);
                    
                    // PDF direkt herunterladen
                    const link = document.createElement('a');
                    link.href = response.pdf_link;
                    link.download = response.pdf_link.split('/').pop();
                    
                    // Debugging: Log the link properties
                    console.log('Link href:', link.href);
                    console.log('Link download:', link.download);
                    
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    // Spezifische Erfolgsmeldung je nach Typ
                    var successMsg = 'PDF erfolgreich erstellt!';
                    if (currentExportType === 'pdf-akura') {
                        successMsg = 'Akura Gravur-Auftrag erfolgreich erstellt!';
                    } else if (currentExportType === 'pdf-schnitzerei') {
                        successMsg = 'Schnitzerei PDF erfolgreich erstellt!';
                    }
                    showToast(successMsg, 'success');
                    $('#exportModal').modal('hide');
                } else if (response.error) {
                    showToast('Fehler: ' + response.error, 'error');
                }
            },
            error: function (xhr, status, error) {
                showToast('Fehler beim Generieren: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    }

            // Button nach kurzer Zeit wiederherstellen
            setTimeout(function () {
                $btn.prop('disabled', false).html(originalText);
            }, 2000);
        });

        // Wanderpreise laden
        function loadWanderpreise() {
            $('#wanderpreisTableContainer').html(`
            <div class="p-4 text-center">
                <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                Lade Wanderpreise...
            </div>
        `);

            $.ajax({
                url: 'wanderpreise/load_wanderpreise.php',
                method: 'GET',
                success: function (response) {
                    <?php if (WANDERPREISE_DEBUG): ?>
                    console.log('Wanderpreis-Daten geladen:', response);
                    <?php endif; ?>
                    $('#wanderpreisTableContainer').html(response);
                    // Debug-Info nur in Development
        <?php if (WANDERPREISE_DEBUG): ?>
        console.log('Wanderpreise geladen');
        <?php endif; ?>
                },
                error: function (xhr, status, error) {
                    $('#wanderpreisTableContainer').html(`
                    <div class="p-4 text-center text-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Fehler beim Laden der Wanderpreise
                    </div>
                `);
                    <?php if (WANDERPREISE_DEBUG): ?>
                    console.error('Fehler beim Laden:', error);
                    <?php else: ?>
                    showToast('Fehler beim Laden der Wanderpreise', 'error');
                    <?php endif; ?>
                }
            });
        }

        // Neuen Wanderpreis hinzufügen
        // >>> PATCH: zuverlässiger Submit ohne doppeltes JSON.parse + disabled-Felder
        // Neuen Wanderpreis hinzufügen – korrektes Form-Target
        // Neuen Wanderpreis hinzufügen – nur Toast, kein alert()
        $('#addWanderpreisForm').on('submit', function (e) {
            e.preventDefault();

            var isEdit = !!$('#wanderpreis_id').val();
            var url = isEdit
                ? 'wanderpreise/update_wanderpreis.php'
                : 'wanderpreise/add_wanderpreis.php';

            // disabled Felder kurz aktivieren, damit sie serialisiert werden
            var $regel = $('#verknuepfung_regel');
            var $jahr = $('#verknuepfung_jahr');
            var dRegel = $regel.is(':disabled'), dJahr = $jahr.is(':disabled');
            if (dRegel) $regel.prop('disabled', false);
            if (dJahr) $jahr.prop('disabled', true); // falls bei dir disabled bleiben soll: anpassen

            var formData = $(this).serialize();

            if (dRegel) $regel.prop('disabled', true);
            if (dJahr) $jahr.prop('disabled', true);

            $.ajax({
                url: url,
                method: 'POST',
                dataType: 'json',
                data: formData
            }).done(function (res) {
                if (res && res.success) {
                    showToast(res.message || 'Wanderpreis gespeichert.', 'success');
                    $('#addWanderpreisModal').modal('hide');
                    location.reload();
                } else {
                    showToast((res && res.message) ? res.message : 'Fehler beim Speichern.', 'error');
                }
            }).fail(function (xhr, status, error) {
                showToast('Fehler beim Speichern: ' + (xhr.responseJSON?.message || error), 'error');
            });
        });





        // Wanderpreise für Modal laden
        function loadWanderpreiseForModal() {
            $.ajax({
                url: 'wanderpreise/get_wanderpreise_list.php',
                method: 'GET',
                success: function (response) {
                    $('#modal_wanderpreis').html('<option value="">Wanderpreis auswählen...</option>' + response);
                }
            });
        }

        // VERBESSERTE Mitglieder für Modal laden - MIT FILTERUNG UND SELECT2
        function loadMitgliederForModal() {
            $.ajax({
                url: 'wanderpreise/get_mitglieder_list.php',
                method: 'GET',
                success: function (response) {
                    // Basis-Option hinzufügen
                    let cleanedOptions = '<option value="">Mitglied suchen/auswählen...</option>';

                    // Response nach Waffenkategorien filtern
                    let tempDiv = $('<div>').html(response);
                    tempDiv.find('option').each(function () {
                        let optionText = $(this).text();
                        let optionValue = $(this).val();

                        // Filtere Waffenkategorien heraus
                        if (optionValue &&
                            !optionText.toLowerCase().includes('kat.') &&
                            !optionText.toLowerCase().includes('kategorie') &&
                            !optionText.toLowerCase().includes('waffe') &&
                            !optionText.includes('---') &&
                            optionText.trim().length > 0) {
                            cleanedOptions += `<option value="${optionValue}">${optionText}</option>`;
                        }
                    });

                    $('#modal_gewinner, #vg_gewinner').html(cleanedOptions);

                    // Select2 initialisieren
                    initializeSearchableSelects();
                }
            });
        }

        // Select2 für suchbare Dropdowns initialisieren
        function initializeSearchableSelects() {
            $('.searchable-select').select2({
                theme: 'bootstrap-5',
                placeholder: 'Mitglied suchen...',
                allowClear: true,
                width: '100%',
                dropdownParent: $('.modal.show'),
                language: {
                    noResults: function () {
                        return "Kein Mitglied gefunden";
                    },
                    searching: function () {
                        return "Suche...";
                    }
                }
            });

            // Alternative: Document-Level Event Listener
            $(document).on('click', '.select2-selection', function () {
                // Prüfen ob es ein searchable-select ist
                const selectElement = $(this).closest('.select2-container').prev('select');
                if (selectElement.hasClass('searchable-select')) {
                    setTimeout(function () {
                        const searchField = $('.select2-search__field:visible');
                        if (searchField.length > 0) {
                            searchField[0].focus();
                        }
                    }, 200);
                }
            });
        }

        // Modal-Events für Select2
        $('#zuordnungModal, #vergangeneGewinnerModal').on('shown.bs.modal', function () {
            // Select2 re-initialisieren wenn Modal geöffnet wird
            setTimeout(function () {
                initializeSearchableSelects();
            }, 200);
        });

        $('#zuordnungModal, #vergangeneGewinnerModal').on('hidden.bs.modal', function () {
            // Select2 zerstören wenn Modal geschlossen wird
            $('.searchable-select').select2('destroy');
        });

        // Wanderpreis im Modal geändert - bisherige Gewinner laden
        $('#modal_wanderpreis').on('change', function () {
            var wanderpreisId = $(this).val();
            if (wanderpreisId) {
                $.ajax({
                    url: 'wanderpreise/get_gewinner_history.php',
                    method: 'GET',
                    data: { wanderpreis_id: wanderpreisId },
                    success: function (response) {
                        $('#bisherige_gewinner_container').html(response);
                    }
                });
            } else {
                $('#bisherige_gewinner_container').html('<p class="text-muted">Wähle einen Wanderpreis aus...</p>');
            }
        });

        // Gewinner zuordnen
        $('#saveZuordnung').on('click', function () {
            var formData = $('#zuordnungForm').serialize();
            formData += '&csrf_token=' + $('input[name="csrf_token"]').val();

            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

            $.ajax({
                url: 'wanderpreise/add_gewinner.php',
                method: 'POST',
                data: formData,
                success: function (response) {
                    try {
                        const jsonResponse = JSON.parse(response);
                        if (jsonResponse.success) {
                            showToast('Gewinner erfolgreich zugeordnet!', 'success');
                            $('#zuordnungModal').modal('hide');
                            $('#zuordnungForm')[0].reset();
                            loadWanderpreise();
                        } else {
                            showToast('Fehler: ' + (jsonResponse.message || 'Unbekannter Fehler'), 'error');
                        }
                    } catch (e) {
                        showToast('Fehler beim Zuordnen des Gewinners', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    showToast('Fehler beim Zuordnen des Gewinners', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Automatische Zuordnung
        // Gemeinsame Funktion: führt die Auto-Zuordnung aus
        function executeAutoZuordnung(jahr, $triggerBtn) {
            var csrfToken = $('input[name="csrf_token"]').val() || window.CSRF_TOKEN || '';
            var $btn = $triggerBtn || $('#autoZuordnungButton');
            var originalText = $btn.html();

            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Verarbeite...');

            $.ajax({
                url: 'wanderpreise/auto_zuordnung.php', // ggf. Pfad anpassen
                method: 'POST',
                dataType: 'json',
                data: { csrf_token: csrfToken, jahr: jahr },
                beforeSend: function () {
                }
            })
                .done(function (res) {
                    if (res && res.success) {
                        var msg = res.message || 'Automatische Zuordnung erfolgreich!';
                        if (Array.isArray(res.details) && res.details.length) {
                            msg += '\n' + res.details.join('\n');
                        }
                        showToast(msg, 'success');
                        if (typeof loadWanderpreise === 'function') {
                            loadWanderpreise();
                        } else {
                            location.reload();
                        }
                    } else {
                        showToast('Fehler: ' + (res?.message || 'Unbekannter Fehler'), 'error');
                    }
                })
                .fail(function (xhr, status, error) {
                    showToast('Fehler bei der automatischen Zuordnung: ' + error, 'error');
                })
                .always(function () {
                    $btn.prop('disabled', false).html(originalText);
                });
        }

        // 1) Klick auf Haupt-Button: Modal öffnen, Jahr anzeigen
        $('#autoZuordnungButton').off('click').on('click', function () {
            var jahr = $('#jahrSelect').val() || new Date().getFullYear();
            $('#autoZuordnungModal .auto-year').text(jahr);

            // Jahr & Trigger-Button am Confirm-Button hinterlegen
            $('#confirmAutoZuordnung').data('jahr', jahr).data('triggerBtn', $(this));

            var modal = new bootstrap.Modal(document.getElementById('autoZuordnungModal'));
            modal.show();
        });

        // 2) Klick auf "Ja, starten": AJAX wirklich ausführen
        $('#confirmAutoZuordnung').off('click').on('click', function () {
            var jahr = $(this).data('jahr') || new Date().getFullYear();
            var $triggerBtn = $(this).data('triggerBtn') || $('#autoZuordnungButton');

            // UI-Feedback im Modal-Button
            var $confirm = $(this);
            var original = $confirm.html();
            $confirm.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Wird gestartet…');

            // Modal sofort schliessen (wir zeigen Status via Toast/Spinner am Haupt-Button)
            var modalEl = document.getElementById('autoZuordnungModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();

            executeAutoZuordnung(jahr, $triggerBtn);

            // Confirm-Button wieder zurücksetzen (falls Modal erneut geöffnet wird)
            setTimeout(function () {
                $confirm.prop('disabled', false).html(original);
            }, 300);
        });


        // Wanderpreis löschen
        $(document).on('click', '.delete-wanderpreis', function () {
            wanderpreisId = $(this).data('id');
            $('#confirmModal').modal('show');
        });

        // Löschen bestätigen (robust + echtes JSON + FK-Fehler sichtbar)
        $('#confirmAction').off('click').on('click', function () {
            if (!wanderpreisId) {
                showToast('Fehler: Keine Wanderpreis-ID vorhanden.', 'error');
                return;
            }

            const csrf = $('input[name="csrf_token"]').val() || window.CSRF_TOKEN || '';
            const $btn = $(this);
            const originalText = $btn.html();

            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');

            $.ajax({
                url: 'wanderpreise/delete_wanderpreis.php',
                method: 'POST',
                dataType: 'json', // <- erzwingt JSON, kein try/catch nötig
                data: { wanderpreis_id: wanderpreisId, csrf_token: csrf }
            })
                .done(function (res) {
                    if (res && res.success) {
                        $('#confirmModal').modal('hide');
                        showToast(res.message || 'Wanderpreis erfolgreich gelöscht', 'success');
                        loadWanderpreise();
                    } else {
                        showToast('Löschen fehlgeschlagen: ' + (res?.message || 'Unbekannter Fehler'), 'error');
                    }
                })
                .fail(function (xhr) {
                    let msg = (xhr.responseJSON && xhr.responseJSON.message) || xhr.responseText || xhr.statusText || 'Unbekannter Fehler';
                    if (xhr.status === 409) {
                        // typischer FK-Fehler (z. B. verknüpfte Gewinner/Historie)
                        msg = 'Löschen nicht möglich: Es existieren verknüpfte Datensätze (z. B. Gewinner/Historie).';
                    }
                    showToast('Fehler beim Löschen: ' + msg, 'error');
                })
                .always(function () {
                    $btn.prop('disabled', false).html(originalText);
                    wanderpreisId = null;
                });
        });



        // Gewinner-/Historie-Modal per Button öffnen
        $(document).on('click', '.view-gewinner', function (e) {
            e.preventDefault();
            e.stopPropagation(); // Damit der Zeilenklick nicht doppelt feuert
            const id = $(this).data('id');
            // Versuche Bezeichnung aus der Zeile zu nehmen (schön für Modal-Titel)
            const bezeichnung = $(this).closest('tr').data('bezeichnung') || '';
            if (id) {
                loadWanderpreisHistorie(id, bezeichnung);
                $('#wanderpreisHistorieModal').modal('show');
            }
        });

        // Vergangene Gewinner speichern
        $('#saveVergangenerGewinner').on('click', function () {
            var formData = $('#vergangeneGewinnerForm').serialize();

            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

            $.ajax({
                url: 'wanderpreise/add_vergangener_gewinner.php',
                method: 'POST',
                data: formData,
                dataType: 'json',            // <— jQuery parst JSON für dich
                success: function (json) {
                    if (json && json.success) {
                        showToast('Vergangener Gewinner erfolgreich eingetragen!', 'success');
                        $('#vergangeneGewinnerModal').modal('hide');
                        $('#vergangeneGewinnerForm')[0].reset();
                        loadWanderpreise();
                    } else {
                        showToast('Fehler: ' + ((json && json.message) || 'Unbekannter Fehler'), 'error');
                    }
                },
                error: function (xhr) {
                    let msg = 'Fehler beim Eintragen des vergangenen Gewinners';
                    try {
                        const j = JSON.parse(xhr.responseText);
                        if (j && j.message) msg = 'Fehler: ' + j.message;
                    } catch (e) { }
                    showToast(msg, 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalText);
                }
            });

        });

        // Automatische Verknüpfung Toggle
        $('#auto_verknuepfung, #edit_auto_verknuepfung').on('change', function () {
            var detailsId = $(this).attr('id') === 'auto_verknuepfung' ? '#verknuepfung_details' : '#edit_verknuepfung_details';
            var regelId = $(this).attr('id') === 'auto_verknuepfung' ? '#verknuepfung_regel' : '#edit_verknuepfung_regel';
            var jahrId = $(this).attr('id') === 'auto_verknuepfung' ? '#verknuepfung_jahr' : '#edit_verknuepfung_jahr';

            if ($(this).is(':checked')) {
                $(detailsId).slideDown(200);
            } else {
                $(detailsId).slideUp(200);
                $(regelId).val('');
                $(jahrId).val('');
            }
        });

        // Regeln für Dropdown laden (+ optional vorselektieren im Edit-Form)
        function loadRegelnForDropdown(selectedForEdit) {
            return $.ajax({
                url: 'wanderpreise/get_regeln_dropdown.php',
                method: 'GET',
                dataType: 'html'
            }).done(function (response) {
                const $add = $('#verknuepfung_regel');
                const $edit = $('#edit_verknuepfung_regel');

                const optionsHtml = '<option value="">Regel auswählen...</option>' + (response || '');
                $add.html(optionsHtml);
                $edit.html(optionsHtml);

                // Falls ein Wert für das Edit-Modal mitgegeben wurde -> vorselektieren
                if (selectedForEdit !== undefined && selectedForEdit !== null && selectedForEdit !== '') {
                    const wanted = String(selectedForEdit).trim();

                    // 1) per value (ID) versuchen
                    $edit.val(wanted);

                    // 2) Fallback: per Sicht-Text matchen (falls Backend nur Namen liefert)
                    if ($edit.val() == null) {
                        const $opt = $edit.find('option').filter(function () {
                            return $(this).text().trim() === wanted;
                        }).first();
                        if ($opt.length) $edit.val($opt.val());
                    }

                    $edit.trigger('change');
                }
            }).fail(function (xhr) {
                $('#verknuepfung_regel, #edit_verknuepfung_regel')
                    .html('<option value="">Fehler beim Laden der Regeln</option>');
            });
        }


        // Event-Handler für Wanderpreis-Details (Klick auf Tabellenzeile)
        $(document).on('click', '.wanderpreis-row', function (e) {
            // Verhindern dass Edit/Delete Buttons das Modal öffnen
            if ($(e.target).closest('.btn').length > 0) {
                return;
            }

            var wanderpreisId = $(this).data('wanderpreis-id');
            var bezeichnung = $(this).data('bezeichnung');

            if (wanderpreisId) {
                loadWanderpreisHistorie(wanderpreisId, bezeichnung);
                $('#wanderpreisHistorieModal').modal('show');
            }
        });

        // Wanderpreis-Historie laden
        function loadWanderpreisHistorie(wanderpreisId, bezeichnung) {

            $('#wanderpreisHistorieModal')
                .data('wanderpreisId', Number(wanderpreisId) || 0)
                .data('bezeichnung', bezeichnung || '');

            // Modal-Titel setzen
            $('#wanderpreisHistorieModalLabel').html(`
        <i class="bi bi-clock-history"></i> Historie: ${bezeichnung}
        
    `);

            // Wanderpreis-ID für PDF-Export speichern
            $('#exportHistoryBtn').data('wanderpreis-id', wanderpreisId);  // <-- Diese Zeile hinzufügen

            // Loading-State
            $('#historieTableContainer').html(`
        <div class="p-4 text-center">
            <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
            Lade Wanderpreis-Historie...
        </div>
    `);


            // Daten laden
            $.ajax({
                url: 'wanderpreise/get_wanderpreis_historie.php',
                method: 'GET',
                data: { wanderpreis_id: wanderpreisId },
                success: function (response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;

                        if (data.success) {
                            // Wanderpreis-Info füllen
                            $('#historie_bezeichnung').html(`
                            <i class="bi bi-award me-2"></i>${data.wanderpreis.bezeichnung}
                        `);
                            $('#historie_beschreibung').text(data.wanderpreis.beschreibung || 'Keine Beschreibung vorhanden');
                            $('#historie_jahr').text(data.wanderpreis.beschaffung_datum || 'Unbekannt');
                            $('#historie_hersteller').text(data.wanderpreis.hersteller || 'Nicht angegeben');
                            $('#historie_min_gewinne').text(data.wanderpreis.min_anzahl_gewinne || '3');

                            // Status-Badge
                            const gewinnerAnzahl = data.gewinner.length;
                            const minGewinne = data.wanderpreis.min_anzahl_gewinne || 3;
                            const status = gewinnerAnzahl >= minGewinne ? 'Definitiv' : 'Wandernd';
                            const statusClass = gewinnerAnzahl >= minGewinne ? 'bg-success' : 'bg-warning';

                            $('#historie_status').removeClass().addClass(`badge ${statusClass}`).text(status);
                            $('#historie_count').text(gewinnerAnzahl);

                            // Historie-Tabelle erstellen
                            if (data.gewinner.length > 0) {
                                let tableHtml = `
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="bi bi-calendar me-1"></i>Jahr</th>
                                            <th><i class="bi bi-person me-1"></i>Gewinner</th>
                                            <th><i class="bi bi-trophy me-1"></i>Rang/Resultat</th>
                                            <th><i class="bi bi-chat-text me-1"></i>Bemerkung</th>
                                            <th><i class="bi bi-chat-text me-1"></i></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            `;

                                data.gewinner.forEach(function (gewinner) {
                                    tableHtml += `
                                    <tr>
                                        <td><strong>${gewinner.jahr}</strong></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-person-circle me-2 text-muted"></i>
                                                ${gewinner.name}
                                            </div>
                                        </td>
                                        <td>
                                            ${gewinner.rang ? `<span class="badge bg-light text-dark">${gewinner.rang}</span>` : '<span class="text-muted">—</span>'}
                                        </td>
                                        <td>
                                            <small class="text-muted">${gewinner.bemerkung || '—'}</small>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger btn-delete-historie" 
                                                     data-id="${gewinner.eintrag_id}
                                                    data-jahr="${gewinner.jahr}"
                                                    data-name="${gewinner.name}"
                                                    data-wpid="${data.wanderpreis.id}"
                                                    title="Löschen">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                `;
                                });

                                tableHtml += `
                                    </tbody>
                                </table>
                            `;

                                $('#historieTableContainer').html(tableHtml);
                            } else {
                                $('#historieTableContainer').html(`
                                <div class="text-center p-4">
                                    <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                                    <h6 class="text-muted">Noch keine Gewinner</h6>
                                    <p class="text-muted mb-0">Für diesen Wanderpreis wurden noch keine Gewinner eingetragen.</p>
                                </div>
                            `);
                            }
                        } else {
                            showToast('Fehler beim Laden der Historie: ' + (data.message || 'Unbekannter Fehler'), 'error');
                        }
                    } catch (e) {
                        showToast('Fehler beim Verarbeiten der Historie-Daten', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    $('#historieTableContainer').html(`
                    <div class="text-center p-4 text-danger">
                        <i class="bi bi-exclamation-triangle display-4 mb-3"></i>
                        <h6>Fehler beim Laden</h6>
                        <p class="mb-0">Die Historie konnte nicht geladen werden.</p>
                    </div>
                `);
                    showToast('Fehler beim Laden der Wanderpreis-Historie', 'error');
                }
            });
        }
        window.loadWanderpreisHistorie = loadWanderpreisHistorie;
        // PDF-Export für Historie
        $('#exportHistoryBtn').on('click', function () {
            const modalTitle = $('#wanderpreisHistorieModalLabel').text();
            const wanderpreisId = $(this).data('wanderpreis-id'); // Wird beim Laden gesetzt

            if (wanderpreisId) {
                var $btn = $(this);
                var originalText = $btn.html();
                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-2"></span>Erstelle PDF...');

                $.ajax({
                    url: 'wanderpreise/export_wanderpreis_historie.php',
                    method: 'GET',
                    data: { wanderpreis_id: wanderpreisId },
                    dataType: 'json',
                   success: function (response) {
  if (response && response.pdf_link) {
    const link = document.createElement('a');
    link.href = response.pdf_link;
    link.download = response.pdf_link.split('/').pop();
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    showToast('Historie-PDF erfolgreich erstellt!', 'success');
  } else {
    showToast('Fehler beim Erstellen des PDFs: ' + (response.message || 'Unbekannter Fehler'), 'error');
  }
},

                    error: function () {
                        showToast('Fehler beim Erstellen des Historie-PDFs', 'error');
                    },
                    complete: function () {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            }
        });

        // Initial laden
        loadWanderpreise();
        loadWanderpreiseForModal();
        loadMitgliederForModal();
        loadRegelnForDropdown();

        // Wanderpreise und Mitglieder für vergangene Gewinner Modal laden
        function loadWanderpreiseForVergangen() {
            $.ajax({
                url: 'wanderpreise/get_wanderpreise_list.php',
                method: 'GET',
                success: function (response) {
                    $('#vg_wanderpreis').html('<option value="">Wanderpreis auswählen...</option>' + response);
                }
            });
        }

        function loadMitgliederForVergangen() {
            // Diese Funktion wird durch loadMitgliederForModal() abgedeckt
            loadMitgliederForModal();
        }

        // Modal öffnen Event
        $('#vergangeneGewinnerButton').on('click', function () {
            loadWanderpreiseForVergangen();
            loadMitgliederForVergangen();
        });

        // Wanderpreis bearbeiten
        $(document).on('click', '.edit-wanderpreis', function () {
            var wanderpreisId = $(this).data('id');

            // Prüfen ob ID vorhanden
            if (!wanderpreisId) {
                showToast('Fehler: Keine Wanderpreis-ID gefunden', 'error');
                return;
            }

            // Daten laden
            $.ajax({
                url: 'wanderpreise/get_wanderpreis.php',
                method: 'GET',
                data: { id: wanderpreisId },
                beforeSend: function () {
                },
                success: function (response) {

                    if (response.success) {
                        var data = response.data;

                        // Formular füllen
                        $('#edit_wanderpreis_id').val(data.id);
                        $('#edit_bezeichnung').val(data.bezeichnung);
                        $('#edit_beschreibung').val(data.beschreibung);
                        $('#edit_beschaffung_jahr').val(data.beschaffung_datum);
                        $('#edit_min_anzahl_gewinne').val(data.min_anzahl_gewinne);

                        // Hersteller-Daten füllen
                        $('#edit_hersteller').val(data.hersteller || '');


                        // Verknüpfungsdaten
                        // $('#edit_verknuepfung_regel').val(data.verknuepfung_regel);
                        $('#edit_verknuepfung_jahr').val(data.verknuepfung_jahr);
                        // Automatische Verknüpfung
                        if (data.auto_verknuepfung == 1) {
                            $('#edit_auto_verknuepfung').prop('checked', true);
                            $('#edit_verknuepfung_details').slideDown(200);
                        } else {
                            $('#edit_auto_verknuepfung').prop('checked', false);
                            $('#edit_verknuepfung_details').slideUp(200);
                        }

                        // Verknüpfungsdaten
                        $('#edit_verknuepfung_jahr').val(data.verknuepfung_jahr || '');

                        // Regeln laden und NACH dem Laden die gespeicherte Regel vorwählen
                        // (bevorzugt die ID, sonst der Name/Titel)
                        loadRegelnForDropdown(data.verknuepfung_regel_id ?? data.verknuepfung_regel)
                            .always(function () {
                                $('#editWanderpreisModal').modal('show');
                            });

                    } else {
                        showToast('Fehler beim Laden des Wanderpreises: ' + response.message, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    <?php if (WANDERPREISE_DEBUG): ?>
                    console.error('Fehler beim Laden des Wanderpreises:', error, xhr.responseText);
                    <?php endif; ?>
                    showToast('Fehler beim Laden des Wanderpreises', 'error');
                }
            });
        });

        // Änderungen speichern
        $('#saveEditWanderpreis').on('click', function () {
            var formData = $('#editWanderpreisForm').serialize();
            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

            $.ajax({
                url: 'wanderpreise/update_wanderpreis.php',
                method: 'POST',
                data: formData,
                beforeSend: function (xhr) {
                },
                success: function (response) {

                    // Überprüfen ob response bereits ein Objekt ist
                    let jsonResponse;
                    if (typeof response === 'object') {
                        jsonResponse = response;
                    } else {
                        try {
                            jsonResponse = JSON.parse(response);
                        } catch (e) {
                            showToast('Fehler beim Verarbeiten der Server-Antwort', 'error');
                            return;
                        }
                    }

                    if (jsonResponse.success) {
                        showToast('Wanderpreis erfolgreich aktualisiert!', 'success');
                        $('#editWanderpreisModal').modal('hide');
                        loadWanderpreise();
                    } else {
                        showToast('Fehler: ' + (jsonResponse.message || 'Unbekannter Fehler'), 'error');
                    }
                },
                error: function (xhr, status, error) {
                    showToast('Fehler beim Aktualisieren des Wanderpreises: ' + error, 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });
    });

    // 1) Klick auf "Löschen" in der Historien-Tabelle öffnet das Bestätigungsmodal
    $(document).on('click', '.btn-delete-historie', function () {
        const id = $(this).data('id');
        const jahr = $(this).data('jahr');
        const name = $(this).data('name');
        const wpid = $(this).data('wpid');

        $('#deleteHistorieId').val(id);
        $('#deleteHistorieWpId').val(wpid);  // Wanderpreis-ID speichern
        $('#confirmDeleteHistorieText').text(`Soll der Eintrag ${name} (${jahr}) wirklich gelöscht werden?`);

        const modal = new bootstrap.Modal(document.getElementById('confirmDeleteHistorieModal'));
        modal.show();
    });

    // 2) Bestätigung: tatsächliches Löschen via Backend
    $('#confirmDeleteHistorieYes').on('click', function () {
        const id = parseInt($('#deleteHistorieId').val(), 10) || 0;
        const wpid = parseInt($('#deleteHistorieWpId').val(), 10) || 0;
        const $btn = $(this).prop('disabled', true);

        const csrfToken = $('input[name="csrf_token"]').first().val() || '';

        if (!id) {
            (window.showToast ? showToast : alert)('Kein gültiger Eintrags-ID gefunden.', 'error');
            $btn.prop('disabled', false);
            return;
        }
        if (!csrfToken) {
            (window.showToast ? showToast : alert)('Kein CSRF-Token gefunden.', 'error');
            $btn.prop('disabled', false);
            return;
        }

        $.ajax({
            url: '/inc/wanderpreise/delete_vergangener_gewinner.php',
            method: 'POST',
            dataType: 'json',
            data: { id: id, csrf_token: csrfToken, wanderpreis_id: wpid }, // wpid mitgeben (falls Backend das braucht)
            headers: { 'Accept': 'application/json' }, // optional
            success: function (json) {
                if (json && json.success) {
                    (window.showToast ? showToast : alert)('Eintrag gelöscht', 'success');

                    // Bestätigungsmodal schließen
                    bootstrap.Modal.getInstance(
                        document.getElementById('confirmDeleteHistorieModal')
                    )?.hide();

                    // Wanderpreis-ID & Bezeichnung verlässlich vom Modal holen
                    const $hist = $('#wanderpreisHistorieModal');
                    const wpid = Number($hist.data('wanderpreisId')) || 0;
                    const bez = String($hist.data('bezeichnung') || '')
                        || $('#wanderpreisHistorieModalLabel').text()
                            .replace(/^.*Historie:\s*/, '').trim();

                    console.table({ reload_wpid: wpid, reload_bez: bez }); // Debug

                    if (wpid && typeof window.loadWanderpreisHistorie === 'function') {
                        window.loadWanderpreisHistorie(wpid, bez);
                    } else {
                        // Fallback, falls irgendwas schief ist
                        (window.showToast ? showToast : alert)('Konnte Historie nicht aktualisieren (fehlende ID).', 'warning');
                    }

                    // Optional Hauptliste aktualisieren
                    if (typeof loadWanderpreise === 'function') loadWanderpreise();
                } else {
                    (window.showToast ? showToast : alert)(
                        'Fehler: ' + ((json && json.message) || 'Unbekannter Fehler'), 'error');
                }
            },


            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });

    // Debug-Info am Seitenende
    <?php if (WANDERPREISE_DEBUG): ?>
    console.log('🏆 Wanderpreise-Modul geladen');
    console.log('Environment:', '<?php echo WANDERPREISE_ENV; ?>');
    console.log('Debug Mode:', <?php echo WANDERPREISE_DEBUG ? 'true' : 'false'; ?>);
    <?php endif; ?>

</script>

<?php
include 'footer.inc.php';
?>