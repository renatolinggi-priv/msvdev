<?php
// wanderpreise.php - Hauptseite für Wanderpreise-Verwaltung
require_once 'wanderpreise/wanderpreise_config.php';
require_once 'dbconnect.inc.php';
require_once __DIR__ . '/csrf.inc.php'; // csrf_token(), Session über session_config


// Seiten-CSS: nur Aufbau dieser Seite; Kopf-Card, Tabellen-Card, Filter, Status und Leerzustand aus css/msv-ui.css
$page_specific_css = "
/* Kurze Liste: die Seite scrollt, nicht die Tabelle (globale Mindest-/Maximalhöhe aus resultate-unified.css aufheben) */
#wanderpreisTableContainer .table-responsive { max-height: none !important; min-height: 0 !important; overflow-y: visible !important; }
/* Tabelle läuft randlos in der Tabellen-Card; Aussenspalten bündig mit dem Kartenkopf */
#wanderpreiseTable { margin: 0; }
#wanderpreiseTable th, #wanderpreiseTable td { padding: 10px 12px; vertical-align: middle; }
#wanderpreiseTable th:first-child, #wanderpreiseTable td:first-child { padding-left: var(--ui-pad); width: 38%; text-align: left; font-weight: 400; background-color: transparent; }
#wanderpreiseTable th:last-child, #wanderpreiseTable td:last-child { padding-right: var(--ui-pad); }
#wanderpreiseTable tbody tr.wanderpreis-row { cursor: pointer; }

/* Name öffnet die Historie (Knopf, damit per Tastatur erreichbar), darunter die Nebenzeile */
.wp-name { padding: 0; border: 0; background: none; font-weight: 600; color: var(--ui-text); text-align: left; }
.wp-name:hover, .wp-name:focus-visible { color: var(--ui-akzent-dunkel); text-decoration: underline; text-underline-offset: 3px; }
.wp-meta, .wp-sub { display: block; margin-top: 2px; font-size: .8rem; color: var(--ui-text-2); }
.wp-meta { color: var(--ui-text-3); }
.wp-gewinner { font-weight: 600; color: var(--ui-text); }
.wp-sub.wp-definitiv { color: var(--ui-ok-fg); font-weight: 600; }
tr.wp-ausser > td, .wp-karte.wp-ausser { color: var(--ui-text-2); }
tr.wp-ausser .wp-name { font-weight: 500; color: var(--ui-text-2); }
.wp-aktionen { width: 1%; white-space: nowrap; text-align: right; }

/* Handy: Karten statt Tabelle (mobile-cards.css), Suche über die ganze Breite */
.wp-karte .mobile-card-header { align-items: flex-start; gap: 10px; }
.wp-karte .mobile-card-value { text-align: right; }
@media (max-width: 767.98px) {
    .ui-tab-kopf .ui-suche { width: 100%; margin-left: 0; height: 40px; }
    .mobile-cards-scroll { padding: 10px; }
}
";

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
        <div class="col-12 ps-0">
            <!-- Äusserer weisser Container -->
            <div class="main-content-wrapper content-width-default">
                <!-- Header ausserhalb des inneren Containers -->
                <?php
                $page_title = 'Wanderpreise';
                $page_title_after = '<button type="button" class="btn-help" data-help="wanderpreise.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                // Zweite Zeile: Stand für das gewählte Jahr (füllt das Skript nach dem Laden)
                $page_extra = '<div class="ui-fortschritt" id="wpFortschritt"><span class="ui-zahl" id="wpStandText">…</span>'
                    . '<span class="ui-balken" aria-hidden="true"><span id="wpBalken"></span></span></div>'
                    . '<div class="ui-chips" id="wpChips"></div>';
                $page_show_mobile = true;
                ob_start(); ?>
<button type="button" class="btn-help" data-help="wanderpreise.aktionen" aria-label="Hilfe zu den Aktionen"></button>
<button type="button" id="autoZuordnungButton" class="btn btn-outline-primary btn-sm" data-tooltip="Gewinner aller Preise mit Regel für das gewählte Jahr ermitteln"><i class="bi bi-magic me-1" aria-hidden="true"></i>Auto-Zuordnung</button>
<button type="button" id="zuordnungButton" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#zuordnungModal"><i class="bi bi-person-check me-1" aria-hidden="true"></i>Zuordnen</button>
<div class="dropdown">
  <button type="button" class="btn btn-outline-info btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-file-earmark-arrow-down me-1"></i>Dokumente</button>
  <ul class="dropdown-menu dropdown-menu-end">
    <li><h6 class="dropdown-header">Listen &amp; Berichte</h6></li>
    <li><button type="button" class="dropdown-item export-btn" data-export-type="csv"><i class="bi bi-file-earmark-spreadsheet me-2"></i>CSV</button></li>
    <li><button type="button" class="dropdown-item export-btn" data-export-type="pdf-all"><i class="bi bi-file-earmark-pdf me-2"></i>PDF Alle</button></li>
    <li><button type="button" class="dropdown-item export-btn" data-export-type="pdf-jm"><i class="bi bi-file-earmark-pdf me-2"></i>JM Preise</button></li>
    <li><button type="button" class="dropdown-item export-btn" data-export-type="pdf-mitglieder-info"><i class="bi bi-people-fill me-2"></i>Mitglieder</button></li>
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">Gravur-Aufträge</h6></li>
    <li><button type="button" class="dropdown-item export-btn" data-export-type="pdf-schnitzerei"><i class="bi bi-file-earmark-pdf me-2"></i>Schnitzerei</button></li>
    <li><button type="button" class="dropdown-item export-btn" data-export-type="pdf-akura"><i class="bi bi-file-earmark-pdf me-2"></i>Akura</button></li>
  </ul>
</div>
<div class="dropdown">
  <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Weitere</button>
  <ul class="dropdown-menu dropdown-menu-end">
    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#addWanderpreisModal"><i class="bi bi-plus-circle me-2"></i>Wanderpreis anlegen</button></li>
    <li><button type="button" id="vergangeneGewinnerButton" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#vergangeneGewinnerModal"><i class="bi bi-clock-history me-2"></i>Frühere Gewinner nachtragen</button></li>
    <li><hr class="dropdown-divider"></li>
    <li><a class="dropdown-item" href="wanderpreise_regeln.php"><i class="bi bi-sliders me-2"></i>Wanderpreis-Regeln</a></li>
  </ul>
</div>
<?php $page_actions = ob_get_clean();
                include 'partials/page_header.inc.php'; ?>

                <!-- Tabellen-Card: Stand je Preis für das gewählte Jahr -->
                <section class="ui-karte" aria-labelledby="wpTabTitel">
                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel" id="wpTabTitel">Wanderpreise <span id="wpTabJahr"></span></span>
                        <div class="ui-filter" role="group" aria-label="Nach Stand filtern">
                            <button type="button" data-filter="alle" aria-pressed="true">Alle <span id="wpNAlle">0</span></button>
                            <button type="button" data-filter="offen" aria-pressed="false">Offen <span id="wpNOffen">0</span></button>
                            <button type="button" data-filter="vergeben" aria-pressed="false">Vergeben <span id="wpNVergeben">0</span></button>
                            <button type="button" data-filter="ausser" aria-pressed="false" data-tooltip="Später angeschafft oder in einem früheren Jahr definitiv gewonnen">Nicht im Umlauf <span id="wpNAusser">0</span></button>
                        </div>
                        <label class="ui-suche">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <span class="visually-hidden">Wanderpreis oder Gewinner suchen</span>
                            <input type="search" id="wpSuche" placeholder="Wanderpreis oder Gewinner" autocomplete="off">
                        </label>
                    </div>
                    <div id="wanderpreisTableContainer" aria-live="polite">
                        <div class="ui-leerzustand"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Wanderpreise werden geladen …</div>
                    </div>
                </section>
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <form id="addWanderpreisForm" method="post">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

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
                                <i class="bi bi-hash me-1"></i>Min. Gewinne: <button type="button" class="btn-help" data-help="wanderpreise.definitiv" aria-label="Hilfe"></button>
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
                                <option value="MSV Wilen">MSV Wilen</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="auto_verknuepfung"
                                    name="auto_verknuepfung">
                                <label class="form-check-label" for="auto_verknuepfung">
                                    <i class="bi bi-magic me-1"></i>Auto-Zuordnung aktivieren <button type="button" class="btn-help" data-help="wanderpreise.autozuordnung" aria-label="Hilfe"></button>
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
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="submit" form="addWanderpreisForm" class="btn btn-outline-primary btn-sm">
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
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
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="saveZuordnung">
                    <i class="bi bi-save me-1"></i>Zuordnung speichern
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Export Jahr-Auswahl -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">
                    <i class="bi bi-download"></i> Export auswählen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
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
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" id="startExport">
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <form id="vergangeneGewinnerForm">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

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
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-outline-success btn-sm" id="saveVergangenerGewinner">
                    <i class="bi bi-plus-circle me-1"></i>Gewinner eintragen
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
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
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Schliessen
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" id="exportHistoryBtn">
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <form id="editWanderpreisForm">
                    <input type="hidden" id="edit_wanderpreis_id" name="wanderpreis_id">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

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
                                <i class="bi bi-hash me-1"></i>Min. Anzahl Gewinne bis definitiv: <button type="button" class="btn-help" data-help="wanderpreise.definitiv" aria-label="Hilfe"></button>
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
                                <option value="MSV Wilen">MSV Wilen</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_auto_verknuepfung"
                                    name="auto_verknuepfung">
                                <label class="form-check-label" for="edit_auto_verknuepfung">
                                    <i class="bi bi-magic me-1"></i>Automatische Zuordnung aktivieren <button type="button" class="btn-help" data-help="wanderpreise.autozuordnung" aria-label="Hilfe"></button>
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
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="saveEditWanderpreis">
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
                <small class="text-muted">Ist das Jahr leer oder 0, werden alle Jahre berücksichtigt.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-outline-success btn-sm" id="confirmAutoZuordnung">
                    Ja, starten
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
                    // optional: msvToast('Jahre konnten nicht geladen werden', 'error');
                });
        });

        // Beim Öffnen des Modals einmalig initialisieren (falls WP schon vorausgewählt ist)
        $('#vergangeneGewinnerModal').on('shown.bs.modal', function () {
            const wpId = Number($('#vg_wanderpreis').val() || 0);
            if (wpId) $('#vg_wanderpreis').trigger('change');
        });

        var currentExportType = null;




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
            $('#modalExportJahr').val(wpJahr());
            $('#exportModal').modal('show');
        });

        // Export starten
$('#startExport').on('click', function () {
    var jahr = $('#modalExportJahr').val();

    if (!jahr || jahr < 1900 || jahr > 2100) {
        msvToast('Bitte ein gültiges Jahr eingeben (1900-2100)', 'error');
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
        msvToast('CSV Export gestartet!', 'success');
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
                    msvToast(successMsg, 'success');
                    $('#exportModal').modal('hide');
                } else if (response.error) {
                    msvToast('Fehler: ' + response.error, 'error');
                }
            },
            error: function (xhr) {
                msvToast(msvXhrMessage(xhr, 'Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.'), 'error');
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
        // Gewähltes Jahr (zentrale Jahresauswahl in der Kopf-Card)
        function wpJahr() {
            return parseInt($('#yearSelect').val(), 10) || new Date().getFullYear();
        }

        // Filter (Segment) und Suche wirken auf Tabelle und Handy-Karten gemeinsam
        var wpFilter = 'alle';
        function wpFilterAnwenden() {
            var q = ($('#wpSuche').val() || '').trim().toLowerCase();
            var $eintraege = $('#wanderpreiseTable tbody tr.wanderpreis-row, #mobileWanderpreiseCards .wp-karte');
            var sichtbar = 0;
            $eintraege.each(function () {
                var ok = (wpFilter === 'alle' || this.getAttribute('data-status') === wpFilter)
                      && (q === '' || this.textContent.toLowerCase().indexOf(q) !== -1);
                this.hidden = !ok;
                if (ok && this.tagName === 'TR') sichtbar++;
            });
            if (!$('#wanderpreiseTable').length) {   // Handy: Karten zählen
                sichtbar = $('#mobileWanderpreiseCards .wp-karte').filter(function () { return !this.hidden; }).length;
            }
            var leer = {
                offen: 'Für ' + wpJahr() + ' ist kein Wanderpreis mehr offen.',
                vergeben: 'Für ' + wpJahr() + ' ist noch kein Wanderpreis vergeben.',
                ausser: 'Alle Wanderpreise sind ' + wpJahr() + ' im Umlauf.'
            };
            var text = q !== '' ? 'Kein Wanderpreis passt zu «' + q + '».' : (leer[wpFilter] || 'Keine Wanderpreise in dieser Auswahl.');
            $('.wp-keine').each(function () { this.hidden = sichtbar > 0; });
            $('.wp-keine-text').text(text);
        }

        // Stand-Zeile in der Kopf-Card und Zähler im Filter
        function wpStandZeigen() {
            var d = document.getElementById('wpStandDaten');
            var j = wpJahr();
            $('#wpTabJahr').text(j);
            if (!d) { $('#wpStandText').text(''); $('#wpChips').empty(); return; }
            var n = { alle: +d.dataset.alle, offen: +d.dataset.offen, vergeben: +d.dataset.vergeben, ausser: +d.dataset.ausser };
            $('#wpNAlle').text(n.alle); $('#wpNOffen').text(n.offen); $('#wpNVergeben').text(n.vergeben); $('#wpNAusser').text(n.ausser);
            var imUmlauf = n.offen + n.vergeben;
            if (imUmlauf === 0) {
                $('#wpStandText').text('Für ' + j + ' ist kein Wanderpreis im Umlauf.');
                $('#wpBalken').css('width', '0');
            } else {
                $('#wpStandText').html(n.vergeben + ' <span>von ' + imUmlauf + ' für ' + j + ' vergeben</span>');
                $('#wpBalken').css('width', Math.round(100 * n.vergeben / imUmlauf) + '%');
            }
            $('#wpChips').html(n.offen > 0
                ? '<span class="ui-chip"><span class="ui-punkt"></span><b>' + n.offen + '</b> offen</span>'
                : (imUmlauf > 0 ? '<span class="ui-status ok"><span class="ui-punkt"></span>alle vergeben</span>' : ''));
        }

        function loadWanderpreise() {
            var j = wpJahr();
            $('#wpTabJahr').text(j);
            $('#wanderpreisTableContainer').html('<div class="ui-leerzustand"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Wanderpreise ' + j + ' werden geladen …</div>');

            $.ajax({
                url: 'wanderpreise/load_wanderpreise.php',
                method: 'GET',
                data: { jahr: j },
                success: function (response) {
                    $('#wanderpreisTableContainer').html(response);
                    wpStandZeigen();
                    wpFilterAnwenden();
                },
                error: function (xhr) {
                    $('#wanderpreisTableContainer').html('<div class="ui-leerzustand text-danger"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>'
                        + msvEsc(msvXhrMessage(xhr, 'Die Wanderpreise konnten nicht geladen werden.'))
                        + '<div class="mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" id="wpNeuLaden">Nochmals laden</button></div></div>');
                    $('#wpStandText').text(''); $('#wpChips').empty();
                }
            });
        }
        $(document).on('click', '#wpNeuLaden', function () { loadWanderpreise(); });

        $('.ui-filter [data-filter]').on('click', function () {
            wpFilter = this.getAttribute('data-filter');
            $('.ui-filter [data-filter]').attr('aria-pressed', 'false');
            this.setAttribute('aria-pressed', 'true');
            wpFilterAnwenden();
        });
        $('#wpSuche').on('input', wpFilterAnwenden);

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
                    msvToast(res.message || 'Wanderpreis gespeichert.', 'success');
                    $('#addWanderpreisModal').modal('hide');
                    location.reload();
                } else {
                    msvToast((res && res.message) ? res.message : 'Fehler beim Speichern.', 'error');
                }
            }).fail(function (xhr, status, error) {
                msvToast('Fehler beim Speichern: ' + (xhr.responseJSON?.message || error), 'error');
            });
        });

        // Wanderpreise für Modal laden
        function loadWanderpreiseForModal() {
            $.ajax({
                url: 'wanderpreise/get_wanderpreise_list.php',
                method: 'GET',
                success: function (response) {
                    $('#modal_wanderpreis').html('<option value="">Wanderpreis auswählen...</option>' + response);
                },
                error: function (xhr) { msvToast(msvXhrMessage(xhr, 'Wanderpreise konnten nicht geladen werden'), 'error'); }
            });
        }

        // VERBESSERTE Mitglieder für Modal laden - MIT FILTERUNG UND SELECT2
        function loadMitgliederForModal() {
            $.ajax({
                url: 'wanderpreise/get_mitglieder_list.php',
                method: 'GET',
                error: function (xhr) { msvToast(msvXhrMessage(xhr, 'Mitglieder konnten nicht geladen werden'), 'error'); },
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

        // Zuordnen schlägt das gewählte Jahr vor
        $('#zuordnungModal').on('show.bs.modal', function () {
            $('#modal_jahr').val(wpJahr());
        });

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
                    },
                    error: function (xhr) {
                        $('#bisherige_gewinner_container').html('<p class="text-danger">' + msvEsc(msvXhrMessage(xhr, 'Bisherige Gewinner konnten nicht geladen werden')) + '</p>');
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
                            msvToast('Gewinner erfolgreich zugeordnet!', 'success');
                            $('#zuordnungModal').modal('hide');
                            $('#zuordnungForm')[0].reset();
                            loadWanderpreise();
                        } else {
                            msvToast(jsonResponse.message || 'Das hat nicht geklappt. Bitte nochmals versuchen; bleibt der Fehler, die Seite neu laden.', 'error');
                        }
                    } catch (e) {
                        msvToast('Fehler beim Zuordnen des Gewinners', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    msvToast('Fehler beim Zuordnen des Gewinners', 'error');
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
                        msvToast(msg, 'success');
                        if (typeof loadWanderpreise === 'function') {
                            loadWanderpreise();
                        } else {
                            location.reload();
                        }
                    } else {
                        msvToast(res?.message || 'Das hat nicht geklappt. Bitte nochmals versuchen; bleibt der Fehler, die Seite neu laden.', 'error');
                    }
                })
                .fail(function (xhr) {
                    msvToast(msvXhrMessage(xhr, 'Die automatische Zuordnung hat nicht geklappt. Bitte die Liste prüfen und nochmals versuchen.'), 'error');
                })
                .always(function () {
                    $btn.prop('disabled', false).html(originalText);
                });
        }

        // 1) Klick auf Haupt-Button: Modal öffnen, Jahr anzeigen
        $('#autoZuordnungButton').off('click').on('click', function () {
            var jahr = wpJahr();   // gewähltes Jahr der Seite (vorher: Kalenderjahr)
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

        // Wanderpreis löschen — Bestätigung via SweetAlert2, dann AJAX
        $(document).on('click', '.delete-wanderpreis', function () {
            const wpId = $(this).data('id');
            if (!wpId) {
                msvToast('Fehler: Keine Wanderpreis-ID vorhanden.', 'error');
                return;
            }
            const name = $(this).closest('[data-bezeichnung]').data('bezeichnung') || 'diesen Wanderpreis';
            msvConfirmDelete(msvEsc(name)).then(function (res) {
                if (!res.isConfirmed) return;

                const csrf = $('input[name="csrf_token"]').val() || window.CSRF_TOKEN || '';

                $.ajax({
                    url: 'wanderpreise/delete_wanderpreis.php',
                    method: 'POST',
                    dataType: 'json', // <- erzwingt JSON, kein try/catch nötig
                    data: { wanderpreis_id: wpId, csrf_token: csrf }
                })
                    .done(function (res) {
                        if (res && res.success) {
                            msvToast(res.message || 'Wanderpreis erfolgreich gelöscht', 'success');
                            loadWanderpreise();
                        } else {
                            msvToast(res?.message || 'Löschen hat nicht geklappt. Bitte die Liste neu laden und prüfen.', 'error');
                        }
                    })
                    .fail(function (xhr) {
                        let msg = msvXhrMessage(xhr, 'Löschen hat nicht geklappt. Bitte die Liste neu laden und prüfen.');
                        if (xhr.status === 409) {
                            // typischer FK-Fehler (z. B. verknüpfte Gewinner/Historie)
                            msg = 'Löschen nicht möglich: Es existieren verknüpfte Datensätze (z. B. Gewinner/Historie).';
                        }
                        msvToast('Fehler beim Löschen: ' + msg, 'error');
                    });
            });
        });

        // Gewinner-/Historie-Modal per Button öffnen
        $(document).on('click', '.view-gewinner', function (e) {
            e.preventDefault();
            e.stopPropagation(); // Damit der Zeilenklick nicht doppelt feuert
            const id = $(this).data('id');
            // Bezeichnung aus Zeile bzw. Handy-Karte (für den Modal-Titel)
            const bezeichnung = $(this).closest('[data-bezeichnung]').data('bezeichnung') || '';
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
                        msvToast('Vergangener Gewinner erfolgreich eingetragen!', 'success');
                        $('#vergangeneGewinnerModal').modal('hide');
                        $('#vergangeneGewinnerForm')[0].reset();
                        loadWanderpreise();
                    } else {
                        msvToast((json && json.message) || 'Das hat nicht geklappt. Bitte nochmals versuchen; bleibt der Fehler, die Seite neu laden.', 'error');
                    }
                },
                error: function (xhr) {
                    let msg = 'Fehler beim Eintragen des vergangenen Gewinners';
                    try {
                        const j = JSON.parse(xhr.responseText);
                        if (j && j.message) msg = 'Fehler: ' + j.message;
                    } catch (e) { }
                    msvToast(msg, 'error');
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
                                                    data-tooltip="Löschen">
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
                            msvToast(data.message || 'Die Daten konnten nicht geladen werden. Bitte die Seite neu laden.', 'error');
                        }
                    } catch (e) {
                        msvToast('Fehler beim Verarbeiten der Historie-Daten', 'error');
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
                    msvToast('Fehler beim Laden der Wanderpreis-Historie', 'error');
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

    msvToast('Historie-PDF erfolgreich erstellt!', 'success');
  } else {
    msvToast(response.message || 'Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', 'error');
  }
},

                    error: function () {
                        msvToast('Fehler beim Erstellen des Historie-PDFs', 'error');
                    },
                    complete: function () {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            }
        });

        // Initial laden: zentrale Jahresauswahl (merkt sich das Jahr seitenübergreifend)
        msvJahrAuswahl('#yearSelect');
        $('#yearSelect').on('change', loadWanderpreise);
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
                },
                error: function (xhr) { msvToast(msvXhrMessage(xhr, 'Wanderpreise konnten nicht geladen werden'), 'error'); }
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
                msvToast('Fehler: Keine Wanderpreis-ID gefunden', 'error');
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
                        msvToast('Fehler beim Laden des Wanderpreises: ' + response.message, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    <?php if (WANDERPREISE_DEBUG): ?>
                    console.error('Fehler beim Laden des Wanderpreises:', error, xhr.responseText);
                    <?php endif; ?>
                    msvToast('Fehler beim Laden des Wanderpreises', 'error');
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
                            msvToast('Fehler beim Verarbeiten der Server-Antwort', 'error');
                            return;
                        }
                    }

                    if (jsonResponse.success) {
                        msvToast('Wanderpreis erfolgreich aktualisiert!', 'success');
                        $('#editWanderpreisModal').modal('hide');
                        loadWanderpreise();
                    } else {
                        msvToast(jsonResponse.message || 'Das hat nicht geklappt. Bitte nochmals versuchen; bleibt der Fehler, die Seite neu laden.', 'error');
                    }
                },
                error: function (xhr) {
                    msvToast(msvXhrMessage(xhr, 'Der Wanderpreis konnte nicht gespeichert werden. Bitte nochmals versuchen.'), 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });
    });

    // Klick auf "Löschen" in der Historien-Tabelle: Bestätigung via SweetAlert2, dann Löschen
    $(document).on('click', '.btn-delete-historie', function () {
        const id = parseInt($(this).data('id'), 10) || 0;
        const wpid = parseInt($(this).data('wpid'), 10) || 0;

        msvConfirmDelete('diesen Eintrag').then(function (res) {
            if (!res.isConfirmed) return;

            const csrfToken = $('input[name="csrf_token"]').first().val() || '';

            if (!id) {
                (window.showToast ? showToast : alert)('Kein gültiger Eintrags-ID gefunden.', 'error');
                return;
            }
            if (!csrfToken) {
                (window.showToast ? showToast : alert)('Sitzung abgelaufen – bitte die Seite neu laden und nochmals versuchen.', 'error');
                return;
            }

            $.ajax({
                url: '/inc/wanderpreise/delete_vergangener_gewinner.php',
                method: 'POST',
                dataType: 'json',
                data: { id: id, csrf_token: csrfToken, wanderpreis_id: wpid }, // wpid mitgeben (falls Backend das braucht)
                headers: { 'Accept': 'application/json' }, // optional
                error: function (xhr) { msvToast(msvXhrMessage(xhr, 'Eintrag konnte nicht gelöscht werden'), 'error'); },
                success: function (json) {
                    if (json && json.success) {
                        (window.showToast ? showToast : alert)('Eintrag gelöscht', 'success');

                        // Wanderpreis-ID & Bezeichnung verlässlich vom Modal holen
                        const $hist = $('#wanderpreisHistorieModal');
                        const reloadWpid = Number($hist.data('wanderpreisId')) || 0;
                        const bez = String($hist.data('bezeichnung') || '')
                            || $('#wanderpreisHistorieModalLabel').text()
                                .replace(/^.*Historie:\s*/, '').trim();

                        console.table({ reload_wpid: reloadWpid, reload_bez: bez }); // Debug

                        if (reloadWpid && typeof window.loadWanderpreisHistorie === 'function') {
                            window.loadWanderpreisHistorie(reloadWpid, bez);
                        } else {
                            // Fallback, falls irgendwas schief ist
                            (window.showToast ? showToast : alert)('Konnte Historie nicht aktualisieren (fehlende ID).', 'warning');
                        }

                        // Optional Hauptliste aktualisieren
                        if (typeof loadWanderpreise === 'function') loadWanderpreise();
                    } else {
                        (window.showToast ? showToast : alert)(
                            (json && json.message) || 'Das hat nicht geklappt. Bitte nochmals versuchen; bleibt der Fehler, die Seite neu laden.', 'error');
                    }
                }
            });
        });
    });

    // Debug-Info am Seitenende
    <?php if (WANDERPREISE_DEBUG): ?>
    console.log('Wanderpreise-Modul geladen');
    console.log('Environment:', '<?php echo WANDERPREISE_ENV; ?>');
    console.log('Debug Mode:', <?php echo WANDERPREISE_DEBUG ? 'true' : 'false'; ?>);
    <?php endif; ?>

</script>

<?php
include 'footer.inc.php';
?>
