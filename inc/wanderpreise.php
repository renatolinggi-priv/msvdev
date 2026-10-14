<?php
// wanderpreise.php - Hauptseite für Wanderpreise-Verwaltung.
// Seit 1.68.29 mit zwei Ansichten: «Wanderpreise» (Stand je Preis, Zuordnung, Dokumente) und «Regeln»
// (?ansicht=regeln: Zuordnungsregeln für die Auto-Zuordnung, vorher wanderpreise_regeln.php, das hierher weiterleitet).
require_once 'wanderpreise/wanderpreise_config.php';
require_once 'dbconnect.inc.php';
require_once __DIR__ . '/csrf.inc.php'; // csrf_token(), Session über session_config
require_once __DIR__ . '/wanderpreise/regel_builder.inc.php'; // Registry + Schema-Referenz für den Regel-Builder

$__ansicht = (($_GET['ansicht'] ?? '') === 'regeln') ? 'regeln' : 'wanderpreise';
$wp_wettbewerbe = wp_wettbewerb_registry();
$wp_schema_ref  = wp_regel_schema_reference();


// Seiten-CSS: nur Aufbau dieser Seite; Kopf-Card, Tabellen-Card, Filter, Status und Leerzustand aus css/msv-ui.css
$page_specific_css = "
/* Kurze Liste: die Seite scrollt, nicht die Tabelle (globale Mindest-/Maximalhöhe aus resultate-unified.css aufheben) */
#wanderpreisTableContainer .table-responsive { max-height: none !important; min-height: 0 !important; overflow-y: visible !important; }
/* Tabelle läuft randlos in der Tabellen-Card; Aussenspalten bündig mit dem Kartenkopf */
#wanderpreiseTable { margin: 0; }
/* Ergebnis-Dialog der Auto-Zuordnung */
.wp-auto-summe { margin: 0 0 .5rem; }
.wp-auto-liste { list-style: none; margin: 0; padding: 0; text-align: left; max-height: 55vh; overflow-y: auto; }
.wp-auto-liste li { display: flex; gap: .55rem; align-items: flex-start; padding: .5rem .1rem; border-top: 1px solid var(--ui-rand, #e8ecf1); font-size: .85rem; line-height: 1.4; }
.wp-auto-liste li:first-child { border-top: 0; }
.wp-auto-liste .bi { flex: 0 0 auto; margin-top: .1rem; }
.wp-auto-preis { display: block; font-weight: 600; color: var(--ui-text, #212529); }
.wp-auto-text { display: block; color: var(--ui-text-2, #495057); }
.wp-auto-ok .bi { color: var(--ui-ok-fg, #166534); }
.wp-auto-fehler .bi { color: var(--ui-fehler, #b42318); }
.wp-auto-hinweis .bi { color: var(--ui-warn-punkt, #d97706); }
.wp-auto-info .bi { color: var(--ui-text-3, #667080); }
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

/* Ansichten «Wanderpreise» / «Regeln» */
.wp-ansicht-wanderpreise .wp-nur-regeln, .wp-ansicht-regeln .wp-nur-wanderpreise,
.wp-ansicht-regeln .msv-kopf-extra { display: none !important; }
.wp-aktionen { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 8px; }

/* Regeln (vorher wanderpreise_regeln.php) */
.regel-name { font-weight: 500; color: var(--ui-text); }
.regel-desc { display: block; color: var(--ui-text-2); font-size: 0.85rem; white-space: normal; overflow-wrap: anywhere; line-height: 1.35; }
.regel-fuer { display: block; margin-top: 2px; font-size: .8rem; color: var(--ui-text-2); }
.regel-fuer.keiner { color: var(--ui-text-3); }
.row-actions { display: flex; gap: 4px; justify-content: flex-end; }
.row-actions .btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
#regelnTable th:first-child, #regelnTable td:first-child { padding-left: var(--ui-pad); }
#regelnTable th:last-child, #regelnTable td:last-child { padding-right: var(--ui-pad); }
.fs-xs { font-size: 0.75rem; }
.fs-xxs { font-size: 0.72rem; }
.w-140 { width: 140px; }
.w-op { max-width: 11rem; } .w-val { max-width: 6.5rem; } .w-dir { max-width: 13rem; }
.sql-editor { font-family: var(--ui-mono); font-size: 0.85rem; background: var(--ui-flaeche-2); border: 1px solid var(--ui-feldrand); border-radius: 8px; tab-size: 4; line-height: 1.5; }
.sql-editor:focus { background: var(--ui-flaeche); border-color: var(--ui-akzent-dunkel); box-shadow: 0 0 0 1px var(--ui-akzent-dunkel); }
.panel-label .hint { font-weight: 400; text-transform: none; }
.mobile-card.regel-inaktiv { opacity: .75; }
.mobile-card .card-code { font-family: var(--ui-mono); background: var(--ui-akzent-hell); color: var(--ui-akzent-dunkel); padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; }
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
            <div class="main-content-wrapper content-width-default wp-ansicht-<?= $__ansicht ?>" id="wpSeite">
                <!-- Header ausserhalb des inneren Containers -->
                <?php
                $page_title = $__ansicht === 'regeln' ? 'Wanderpreis-Regeln' : 'Wanderpreise';
                $page_title_after = '<button type="button" class="btn-help" data-help="wanderpreise.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>'
                    . '<div class="ui-filter" role="group" aria-label="Ansicht" id="wpAnsicht">'
                    . '<button type="button" data-ansicht="wanderpreise" aria-pressed="' . ($__ansicht === 'wanderpreise' ? 'true' : 'false') . '">Wanderpreise</button>'
                    . '<button type="button" data-ansicht="regeln" aria-pressed="' . ($__ansicht === 'regeln' ? 'true' : 'false') . '">Regeln</button>'
                    . '</div>';
                // Zweite Zeile: Stand für das gewählte Jahr (füllt das Skript nach dem Laden)
                $page_extra = '<div class="ui-fortschritt" id="wpFortschritt"><span class="ui-zahl" id="wpStandText">…</span>'
                    . '<span class="ui-balken" aria-hidden="true"><span id="wpBalken"></span></span></div>'
                    . '<div class="ui-chips" id="wpChips"></div>';
                $page_show_mobile = true;
                ob_start(); ?>
<span class="wp-aktionen wp-nur-regeln">
<button type="button" class="btn-help" data-help="wanderpreise_regeln.uebersicht" aria-label="Hilfe zu den Regeln"></button>
<button type="button" class="btn btn-outline-success btn-sm" id="btnNeueRegel"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Neue Regel</button>
</span>
<span class="wp-aktionen wp-nur-wanderpreise">
<button type="button" class="btn-help" data-help="wanderpreise.aktionen" aria-label="Hilfe zu den Aktionen"></button>
<button type="button" id="autoZuordnungButton" class="btn btn-outline-primary btn-sm" data-tooltip="Gewinner aller Preise mit Regel für das gewählte Jahr ermitteln"><i class="bi bi-magic me-1" aria-hidden="true"></i>Auto-Zuordnung</button>
<button type="button" id="zuordnungButton" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#zuordnungModal"><i class="bi bi-person-check me-1" aria-hidden="true"></i>Zuordnen</button>
<div class="dropdown">
  <button type="button" class="btn btn-outline-info btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-file-earmark-arrow-down me-1"></i>Dokumente</button>
  <ul class="dropdown-menu dropdown-menu-end">
    <li><h6 class="dropdown-header">Listen &amp; Berichte</h6></li>
    <li><button type="button" class="dropdown-item export-btn" data-export-type="csv"><i class="bi bi-filetype-csv me-2" aria-hidden="true"></i>Liste (CSV)</button></li>
    <li><button type="button" class="dropdown-item export-btn" data-export-type="pdf-all"><i class="bi bi-file-earmark-pdf me-2" aria-hidden="true"></i>Jahresbericht</button></li>
    <li><button type="button" class="dropdown-item export-btn" data-export-type="pdf-jm"><i class="bi bi-file-earmark-pdf me-2" aria-hidden="true"></i>JM-Preise</button></li>
    <li><button type="button" class="dropdown-item export-btn" data-export-type="pdf-mitglieder-info"><i class="bi bi-file-earmark-pdf me-2" aria-hidden="true"></i>Mitglieder-Info</button></li>
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">Gravur-Aufträge</h6></li>
    <li><button type="button" class="dropdown-item export-btn" data-export-type="pdf-schnitzerei"><i class="bi bi-file-earmark-pdf me-2" aria-hidden="true"></i>Schnitzerei</button></li>
    <li><button type="button" class="dropdown-item export-btn" data-export-type="pdf-akura"><i class="bi bi-file-earmark-pdf me-2" aria-hidden="true"></i>Akura</button></li>
  </ul>
</div>
<div class="dropdown">
  <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Weitere</button>
  <ul class="dropdown-menu dropdown-menu-end">
    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#addWanderpreisModal"><i class="bi bi-plus-circle me-2"></i>Wanderpreis anlegen</button></li>
    <li><button type="button" id="vergangeneGewinnerButton" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#vergangeneGewinnerModal"><i class="bi bi-clock-history me-2"></i>Frühere Gewinner nachtragen</button></li>
  </ul>
</div>
</span>
<?php $page_actions = ob_get_clean();
                include 'partials/page_header.inc.php'; ?>

                <!-- Ansicht «Regeln»: Zuordnungsregeln der Auto-Zuordnung -->
                <section class="ui-karte wp-nur-regeln" aria-labelledby="regelnTitel">
                    <input type="hidden" id="csrfToken" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel" id="regelnTitel">Zuordnungsregeln <span class="badge bg-secondary ms-1" id="regelnCountBadge">0</span></span>
                        <label class="ui-suche d-none d-md-flex">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <span class="visually-hidden">Regeln suchen</span>
                            <input type="search" id="desktopSearch" placeholder="Regel oder Wanderpreis" autocomplete="off">
                        </label>
                    </div>
                    <!-- Desktop: Tabelle -->
                    <div class="desktop-table-container">
                        <div class="table-responsive">
                            <table class="hybrid-table" id="regelnTable">
                                <thead>
                                    <tr>
                                        <th class="w-140">Code</th>
                                        <th>Name und Wanderpreis</th>
                                        <th class="d-none d-lg-table-cell">Beschreibung</th>
                                        <th class="text-center" style="width:60px;">Status</th>
                                        <th class="text-end" style="width:120px;">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody id="regelnTableBody">
                                    <tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm me-2"></div>Lade Regeln …</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Handy: Karten -->
                    <div class="mobile-cards-container">
                        <div class="mobile-search">
                            <div class="position-relative">
                                <i class="bi bi-search search-icon"></i>
                                <input type="text" class="form-control" id="mobileSearch" placeholder="Suchen …" aria-label="Regeln suchen">
                            </div>
                        </div>
                        <div class="mobile-cards-scroll" id="mobileCardsScroll"></div>
                    </div>
                </section>

                <!-- Tabellen-Card: Stand je Preis für das gewählte Jahr -->
                <section class="ui-karte wp-nur-wanderpreise" aria-labelledby="wpTabTitel">
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
                    <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Historie</span>
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
<!-- Ansicht «Regeln»: Slide-Panel (zentrales Partial); 620px, damit der SQL-Editor Platz hat -->
<?php
$panel_id    = 'regelPanel';
$panel_width = '620px';
$panel_title = '<span id="panelTitle"><i class="bi bi-plus-circle me-2"></i>Neue Regel erstellen</span>';
ob_start(); ?>
    <form id="regelForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="id" id="regelId" value="">

      <div class="mb-3">
        <label class="panel-label" for="regelCode">Regel-Code <span class="text-danger">*</span></label>
        <input type="text" name="regel_code" id="regelCode" class="form-control form-control-sm" required placeholder="z.B. jahresmeister_300m" pattern="[A-Za-z0-9_\-]+" title="Nur Buchstaben, Zahlen, _ und -">
        <div class="form-text fs-xs">Eindeutiger Identifier ohne Leerzeichen; endet er auf A oder B, wird die Kategorie bei der Auto-Zuordnung daraus abgeleitet.</div>
      </div>
      <div class="mb-3">
        <label class="panel-label" for="regelName">Regel-Name <span class="text-danger">*</span></label>
        <input type="text" name="regel_name" id="regelName" class="form-control form-control-sm" required placeholder="z.B. Jahresmeister 300m">
      </div>
      <div class="mb-3">
        <label class="panel-label" for="regelBeschreibung">Beschreibung</label>
        <textarea name="regel_beschreibung" id="regelBeschreibung" class="form-control form-control-sm" rows="2" placeholder="Was macht diese Regel..."></textarea>
      </div>
      <p class="regel-fuer mb-3" id="regelVerwendet" hidden></p>

      <div class="mb-3">
        <label class="panel-label" for="regelTyp">Regel-Typ <button type="button" class="btn-help" data-help="wanderpreise_regeln.typ" aria-label="Hilfe"></button></label>
        <select name="regel_typ" id="regelTyp" class="form-select form-select-sm">
          <option value="einzelwettbewerb">Geführt: Bester in einem Wettbewerb</option>
          <option value="baukasten">Baukasten: Bedingungen &amp; Sortierung selbst zusammenstellen</option>
          <option value="custom">Experte: eigenes SQL</option>
        </select>
        <div class="form-text fs-xs">„Geführt" und „Baukasten" erzeugen das SQL automatisch. „Experte" für Sonderfälle (z. B. Jahresmeister); nur lesende SELECT-Abfragen sind erlaubt.</div>
      </div>

      <!-- Builder-Felder (nur bei Geführt/Baukasten) -->
      <div id="builderFields">
        <div class="mb-3">
          <label class="panel-label" for="builderWettbewerb">Wettbewerb <span class="text-danger">*</span></label>
          <select name="wettbewerb" id="builderWettbewerb" class="form-select form-select-sm">
            <?php foreach ($wp_wettbewerbe as $wpKey => $wpDef): ?>
            <option value="<?= htmlspecialchars($wpKey) ?>" data-cat="<?= !empty($wpDef['category']) ? '1' : '0' ?>"><?= htmlspecialchars($wpDef['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3" id="builderKategorieRow">
          <span class="panel-label">Kategorie</span>
          <div class="d-flex gap-3">
            <div class="form-check"><input class="form-check-input" type="radio" name="kategorie" id="katAlle" value="" checked><label class="form-check-label" for="katAlle">Alle</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="kategorie" id="katA" value="A"><label class="form-check-label" for="katA">Kat. A</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="kategorie" id="katB" value="B"><label class="form-check-label" for="katB">Kat. B</label></div>
          </div>
        </div>
        <div class="mb-3" id="einzelOnlyFields">
          <label class="panel-label" for="builderRichtung">Sieger ist</label>
          <select name="richtung" id="builderRichtung" class="form-select form-select-sm">
            <option value="DESC">Höchstes Resultat (Standard)</option>
            <option value="ASC">Niedrigstes Resultat</option>
          </select>
        </div>
        <div id="baukastenFields">
          <div class="mb-3">
            <span class="panel-label">Bedingungen <span class="hint text-muted">(optional, mit UND verknüpft)</span> <button type="button" class="btn-help" data-help="wanderpreise_regeln.baukasten" aria-label="Hilfe"></button></span>
            <div id="builderFilterRows"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="btnAddFilter"><i class="bi bi-plus-lg me-1"></i>Bedingung</button>
            <div class="form-text fs-xxs">Jahr (= {jahr}) und „kein Leertreffer" werden automatisch ergänzt. Werte: Zahl oder <code>{jahr}</code>.</div>
          </div>
          <div class="mb-3">
            <span class="panel-label">Sortierung <span class="hint text-muted">(Sieger = erste Zeile)</span></span>
            <div id="builderSortRows"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="btnAddSort"><i class="bi bi-plus-lg me-1"></i>Sortierung</button>
          </div>
          <input type="hidden" name="filter_json" id="builderFilterJson" value="[]">
          <input type="hidden" name="sort_json" id="builderSortJson" value="[]">
        </div>
      </div>

      <!-- SQL: Experte = editierbar, Geführt = read-only Vorschau -->
      <div class="mb-3" id="sqlBlock">
        <label class="panel-label" id="sqlLabel" for="regelSql">SQL-Query <span class="text-danger">*</span> <button type="button" class="btn-help" data-help="wanderpreise_regeln.sql" aria-label="Hilfe"></button></label>
        <div class="form-text mb-2 fs-xs" id="sqlCustomHelp">
          Muss <code>gewinner_id</code> zurückgeben. Optional: <code>resultat</code>, <code>rang</code>, <code>bemerkung</code>.<br>
          Platzhalter: <span class="code-badge fs-xs">{jahr}</span> <span class="code-badge fs-xs">{kategorie}</span> <span class="code-badge fs-xs">{wanderpreis_id}</span><br>
          Erlaubt: <code>SET @var = …;</code> gefolgt von genau einem <code>SELECT</code>/<code>WITH</code>. Schreibende Befehle werden abgelehnt.
        </div>
        <textarea name="sql_query" id="regelSql" class="form-control sql-editor" rows="10" required placeholder="SELECT m.ID AS gewinner_id, ... FROM ..."></textarea>
      </div>

      <div class="mb-3">
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnTestSql"><i class="bi bi-play-fill me-1"></i>SQL testen</button>
        <span class="form-text fs-xs ms-2">mit dem oben gewählten Jahr</span>
        <div id="testResult" class="mt-2" hidden></div>
      </div>

      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" name="aktiv" id="regelAktiv" checked>
        <label class="form-check-label" for="regelAktiv">Regel ist aktiv</label>
      </div>
    </form>

    <!-- Experten-Werkzeuge: Vorlage aus bestehender Regel + Tabellen-Referenz -->
    <div id="expertenTools">
      <hr class="my-3">
      <label class="panel-label" for="vorlagePicker"><i class="bi bi-clipboard me-1"></i>Vorlage aus bestehender Regel</label>
      <select id="vorlagePicker" class="form-select form-select-sm">
        <option value="">– Regel wählen, um ihr SQL zu übernehmen –</option>
      </select>
      <div class="form-text fs-xxs">Übernimmt das SQL einer bestehenden Regel als Startpunkt.</div>
      <div class="mt-3">
        <button class="btn btn-link text-muted p-0 small" type="button" data-bs-toggle="collapse" data-bs-target="#schemaRef" aria-expanded="false" aria-controls="schemaRef">
          <i class="bi bi-table me-1"></i>Verfügbare Tabellen &amp; Spalten
        </button>
        <div class="collapse mt-2" id="schemaRef">
          <div class="form-text mb-1 fs-xxs">Ausgabe: <code>gewinner_id</code> (Pflicht), optional <code>resultat</code>, <code>rang</code>, <code>bemerkung</code>.</div>
          <div class="table-responsive">
            <table class="table table-sm fs-xxs">
              <thead><tr><th>Tabelle</th><th>Mitglied</th><th>Jahr</th><th>Spalten</th></tr></thead>
              <tbody>
                <?php foreach ($wp_schema_ref as $t): ?>
                <tr><td><code><?= htmlspecialchars($t['table']) ?></code></td><td><?= htmlspecialchars($t['member']) ?></td><td><?= htmlspecialchars($t['year']) ?></td><td><?= htmlspecialchars($t['cols']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
<?php
$panel_body = ob_get_clean();
ob_start(); ?>
    <button type="button" class="btn btn-outline-primary btn-sm" id="btnSaveRegel"><i class="bi bi-save me-1"></i>Speichern</button>
    <button type="button" class="btn btn-outline-danger btn-sm d-none ms-auto" id="btnDeleteRegel"><i class="bi bi-trash me-1"></i>Löschen</button>
<?php
$panel_footer = ob_get_clean();
include 'partials/side_panel.inc.php';
?>

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

        // Berichte aus dem Menü «Dokumente»: für das oben gewählte Jahr, direkt herunterladen
        // (Ausgabe-Baustein msvAusgabe; Spinner am Knopf «Dokumente», weil sich das Menü schliesst)
        $('.export-btn').on('click', function () {
            var jahr = wpJahr();
            var berichte = {
                'csv':                 { url: 'wanderpreise/export_wanderpreise.php', data: { jahr: jahr }, name: 'Wanderpreise_Liste_' + jahr, titel: 'Wanderpreise Liste ' + jahr + ' (CSV)' },
                'pdf-all':             { data: { type: 'jahresreport' }, name: 'Wanderpreise_Jahresbericht_' + jahr, titel: 'Wanderpreise Jahresbericht ' + jahr },
                'pdf-jm':              { data: { type: 'top3' }, name: 'Wanderpreise_JM-Preise_' + jahr, titel: 'JM-Preise ' + jahr },
                'pdf-mitglieder-info': { data: { type: 'mitglieder-info' }, name: 'Wanderpreise_Mitglieder-Info_' + jahr, titel: 'Wanderpreise Mitglieder-Info ' + jahr },
                'pdf-schnitzerei':     { data: { type: 'jahresreport', hersteller: 'Schnitzerei Heinz Schild' }, name: 'Gravur_Schnitzerei_' + jahr, titel: 'Gravur-Auftrag Schnitzerei ' + jahr },
                'pdf-akura':           { data: { type: 'jahresreport', hersteller: 'Akura Einsiedeln' }, name: 'Gravur_Akura_' + jahr, titel: 'Gravur-Auftrag Akura ' + jahr }
            };
            var b = berichte[$(this).data('export-type')];
            if (!b) return;
            msvAusgabe(this, {
                url: b.url || 'wanderpreise/generate_wanderpreise_jahresreport.php',
                data: b.url ? b.data : Object.assign({ year: jahr }, b.data),
                ladeKnopf: $(this).closest('.dropdown').find('.dropdown-toggle')[0],
                name: b.name,
                titel: b.titel,
                fehler: 'Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.'
            });
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

        // Ergebnis der Auto-Zuordnung als Dialog: pro Wanderpreis eine Zeile, Fehler zuoberst.
        // Die Zeilen kommen als «Status – Preis: Text» vom Server (auto_zuordnung.php).
        function zeigeAutoZuordnungErgebnis(res, jahr) {
            var arten = {
                'Fehler':       { cls: 'fehler',  icon: 'bi-x-circle-fill',             rang: 0 },
                'Hinweis':      { cls: 'hinweis', icon: 'bi-exclamation-triangle-fill', rang: 1 },
                'OK':           { cls: 'ok',      icon: 'bi-check-circle-fill',         rang: 2 },
                'Übersprungen': { cls: 'info',    icon: 'bi-skip-forward-circle',       rang: 3 },
                'Info':         { cls: 'info',    icon: 'bi-dash-circle',               rang: 3 }
            };
            var zeilen = (Array.isArray(res.details) ? res.details : []).map(function (d) {
                var m = String(d).match(/^(\S+) – (.+?): (.*)$/);
                var art = (m && arten[m[1]]) || arten['Info'];
                return { art: art, preis: m ? m[2] : '', text: m ? m[3] : String(d) };
            });
            zeilen.sort(function (a, b) { return a.art.rang - b.art.rang; });

            var anz = res.zuordnungen || 0, fehler = res.fehler || 0;
            var titel = anz ? (anz === 1 ? '1 Gewinner zugeordnet' : anz + ' Gewinner zugeordnet') : 'Keine neue Zuordnung';
            var icon = fehler ? 'warning' : (anz ? 'success' : 'info');

            var html = '<p class="wp-auto-summe">Jahr ' + msvEsc(jahr)
                + (fehler ? ' · <strong>' + fehler + ' Fehler</strong>' : '') + '</p>';
            if (zeilen.length) {
                html += '<ul class="wp-auto-liste">' + zeilen.map(function (z) {
                    return '<li class="wp-auto-' + z.art.cls + '"><i class="bi ' + z.art.icon + '" aria-hidden="true"></i><div>'
                        + (z.preis ? '<span class="wp-auto-preis">' + msvEsc(z.preis) + '</span>' : '')
                        + '<span class="wp-auto-text">' + msvEsc(z.text) + '</span></div></li>';
                }).join('') + '</ul>';
            } else {
                html += '<p class="mb-0">Kein Wanderpreis hat eine automatische Verknüpfung für dieses Jahr.</p>';
            }

            msvSwal.fire({ icon: icon, title: titel, html: html, width: '34rem', confirmButtonText: 'Schliessen' });
        }

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
                        zeigeAutoZuordnungErgebnis(res, jahr);
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
                        const r = xhr.responseJSON || {};
                        if (xhr.status === 409 && r.gewinner_count) {
                            // Preis mit Gewinnern bleibt (Geschichte geht nicht verloren) – gleicher Dialog wie bei Mitgliedern/JM-Anlässen
                            msvLoeschenGesperrt({
                                text: '<strong>' + msvEsc(name) + '</strong> hat schon Gewinner und bleibt deshalb erhalten.',
                                bezuege: { 'Gewinner': r.gewinner_count },
                                hinweis: 'Ist der Preis definitiv gewonnen, erscheint er im Filter «Nicht im Umlauf».'
                            });
                            return;
                        }
                        msvToast(msvXhrMessage(xhr, 'Löschen hat nicht geklappt. Bitte die Liste neu laden und prüfen.'), 'error');
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

                    // 1) per value (Regel-Code) versuchen
                    $edit.val(wanted);

                    // 2) Fallback: alte Einträge speicherten die Regel-ID statt des Codes
                    if ($edit.val() == null) {
                        const $optId = $edit.find('option').filter(function () {
                            return String($(this).data('id')) === wanted;
                        }).first();
                        if ($optId.length) $edit.val($optId.val());
                    }

                    // 3) Fallback: per Sicht-Text matchen (falls Backend nur Namen liefert)
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
                // Ausgabe-Baustein msvAusgabe: sperren, Spinner, Download, Toast
                const name = modalTitle.replace(/^\s*Historie[:\s–-]*/i, '').trim();
                msvAusgabe(this, {
                    url: 'wanderpreise/export_wanderpreis_historie.php',
                    data: { wanderpreis_id: wanderpreisId },
                    titel: 'Historie ' + (name || 'Wanderpreis'),
                    name: 'Wanderpreis_Historie_' + (name || wanderpreisId),
                    fehler: 'Die Historie konnte nicht als PDF erstellt werden. Bitte nochmals versuchen.'
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

<script>
/* ===== Ansicht «Wanderpreise» / «Regeln» (Titel = Menütext = Browser-Tab folgt der Ansicht) ===== */
$(function() {
    const titel = { wanderpreise: 'Wanderpreise', regeln: 'Wanderpreis-Regeln' };
    function zeigeAnsicht(ansicht) {
        const $seite = $('#wpSeite');
        $seite.toggleClass('wp-ansicht-regeln', ansicht === 'regeln').toggleClass('wp-ansicht-wanderpreise', ansicht !== 'regeln');
        $('#wpAnsicht button').each(function() { this.setAttribute('aria-pressed', String(this.getAttribute('data-ansicht') === ansicht)); });
        $seite.find('.page-title').first().text(titel[ansicht]);
        document.title = titel[ansicht] + ' - MSV Wilen';
        const url = new URL(location.href);
        if (ansicht === 'regeln') url.searchParams.set('ansicht', 'regeln'); else url.searchParams.delete('ansicht');
        history.replaceState(null, '', url);
    }
    $('#wpAnsicht').on('click', 'button', function() { zeigeAnsicht(this.getAttribute('data-ansicht')); });
    zeigeAnsicht($('#wpSeite').hasClass('wp-ansicht-regeln') ? 'regeln' : 'wanderpreise');
});

/* ===== Ansicht «Regeln»: Zuordnungsregeln (vorher wanderpreise_regeln.php) ===== */
$(function() {
    const CSRF = document.getElementById('csrfToken').value;
    const WP_WETTBEWERBE = <?= json_encode(wp_wettbewerbe_client(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    let regelnData = [];
    let previewTimer = null;

    const escHtml = s => $('<span>').text(s == null ? '' : String(s)).html();
    const ajaxMsg = (xhr, fb) => (xhr && xhr.responseJSON && xhr.responseJSON.message) || (xhr && xhr.status === 401 ? 'Sitzung abgelaufen – bitte neu anmelden' : fb);
    const findRegel = id => regelnData.find(r => String(r.id) === String(id));
    // Welche Wanderpreise die Regel verwenden («für …»); «(Auto-Zuordnung aus)», wenn dort ausgeschaltet
    function verwendetText(r) {
        const v = Array.isArray(r.verwendet_von) ? r.verwendet_von : [];
        if (!v.length) return '';
        return 'für ' + v.map(w => w.name + (w.auto ? '' : ' (Auto-Zuordnung aus)')).join(', ');
    }
    function verwendetHtml(r) {
        const t = verwendetText(r);
        return t ? '<span class="regel-fuer">' + escHtml(t) + '</span>' : '<span class="regel-fuer keiner">von keinem Wanderpreis verwendet</span>';
    }

    // ---------- Laden / Rendern ----------
    function loadRegeln() {
        $.getJSON('wanderpreise/get_regeln_json.php')
            .done(function(data) {
                regelnData = Array.isArray(data) ? data : [];
                renderDesktopTable();
                renderMobileCards();
                populateVorlagePicker();
                $('#regelnCountBadge').text(regelnData.length);
                $('#desktopSearch').trigger('input');
            })
            .fail(function(xhr) {
                $('#regelnTableBody').html('<tr><td colspan="5" class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden</td></tr>');
                msvToast(ajaxMsg(xhr, 'Regeln konnten nicht geladen werden'), 'error');
            });
    }

    const EMPTY_ROW = '<tr class="msv-empty-row"><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox d-block mb-2" style="font-size:1.6rem;opacity:.5;"></i>Keine Regeln gefunden</td></tr>';

    function renderDesktopTable() {
        if (!regelnData.length) { $('#regelnTableBody').html(EMPTY_ROW); return; }
        $('#regelnTableBody').html(regelnData.map(function(r) {
            const statusDot = Number(r.aktiv) === 1
                ? '<span class="flag-dot ok on" data-tooltip="Aktiv"><i class="bi bi-check2"></i></span>'
                : '<span class="flag-dot off" data-tooltip="Inaktiv"><i class="bi bi-pause"></i></span>';
            return '<tr class="hybrid-row" data-id="' + escHtml(r.id) + '">' +
                '<td><span class="code-badge">' + escHtml(r.regel_code) + '</span></td>' +
                '<td><span class="regel-name">' + escHtml(r.regel_name) + '</span>' + verwendetHtml(r) + '</td>' +
                '<td class="d-none d-lg-table-cell"><span class="regel-desc">' + escHtml(r.regel_beschreibung || '–') + '</span></td>' +
                '<td class="text-center">' + statusDot + '</td>' +
                '<td><div class="row-actions">' +
                '<button type="button" class="btn btn-outline-primary btn-sm" data-action="test" data-tooltip="SQL testen" aria-label="SQL testen"><i class="bi bi-play-fill"></i></button>' +
                '<button type="button" class="btn btn-outline-primary btn-sm" data-action="edit" data-tooltip="Bearbeiten" aria-label="Bearbeiten"><i class="bi bi-pencil"></i></button>' +
                '<button type="button" class="btn btn-outline-danger btn-sm" data-action="delete" data-tooltip="Löschen" aria-label="Löschen"><i class="bi bi-trash"></i></button>' +
                '</div></td></tr>';
        }).join(''));
    }

    function renderMobileCards() {
        if (!regelnData.length) { $('#mobileCardsScroll').html('<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Regeln gefunden</div></div>'); return; }
        $('#mobileCardsScroll').html(regelnData.map(function(r) {
            const aktiv = Number(r.aktiv) === 1;
            return '<div class="mobile-card' + (aktiv ? '' : ' regel-inaktiv') + '" data-id="' + escHtml(r.id) + '">' +
                '<div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">' +
                '<div><span class="card-code">' + escHtml(r.regel_code) + '</span> <span class="badge ' + (aktiv ? 'bg-success' : 'bg-secondary') + ' ms-1">' + (aktiv ? 'aktiv' : 'inaktiv') + '</span>' +
                '<div class="fw-bold mt-1">' + escHtml(r.regel_name) + '</div>' + verwendetHtml(r) +
                (r.regel_beschreibung ? '<small class="text-muted">' + escHtml(r.regel_beschreibung) + '</small>' : '') + '</div>' +
                '<i class="bi bi-chevron-down"></i></div>' +
                '<div class="mobile-card-body"><div class="d-flex gap-2">' +
                '<button type="button" class="btn btn-outline-primary btn-sm flex-fill" data-action="test"><i class="bi bi-play-fill me-1"></i>Testen</button>' +
                '<button type="button" class="btn btn-outline-primary btn-sm flex-fill" data-action="edit"><i class="bi bi-pencil me-1"></i>Bearbeiten</button>' +
                '<button type="button" class="btn btn-outline-danger btn-sm flex-fill" data-action="delete"><i class="bi bi-trash me-1"></i>Löschen</button>' +
                '</div></div></div>';
        }).join(''));
    }

    // Aktionen (delegiert) – Desktop-Zeile und Mobile-Card
    $('#regelnTableBody').on('click', 'tr.hybrid-row', function(e) {
        if ($(e.target).closest('[data-action]').length) return;
        const r = findRegel($(this).data('id')); if (r) openPanel('edit', r);
    });
    $(document).on('click', '#regelnTableBody [data-action], #mobileCardsScroll [data-action]', function(e) {
        e.stopPropagation();
        const r = findRegel($(this).closest('[data-id]').data('id'));
        if (!r) return;
        const a = $(this).data('action');
        if (a === 'test') { openPanel('edit', r); $('#btnTestSql').trigger('click'); }
        else if (a === 'edit') openPanel('edit', r);
        else if (a === 'delete') deleteRegel(r.id);
    });

    // ---------- Slide-Panel ----------
    function openPanel(mode, regel) {
        if (mode === 'new') {
            $('#panelTitle').html('<i class="bi bi-plus-circle me-2"></i>Neue Regel erstellen');
            $('#regelForm')[0].reset();
            $('#regelId').val('');
            $('#regelAktiv').prop('checked', true);
            $('#btnDeleteRegel').addClass('d-none');
            $('#btnSaveRegel').html('<i class="bi bi-save me-1"></i>Erstellen');
            $('#regelTyp').val('einzelwettbewerb');
            $('#builderWettbewerb').prop('selectedIndex', 0);
            $('#katAlle').prop('checked', true);
            $('#builderRichtung').val('DESC');
            $('#regelSql').val('');
            $('#regelVerwendet').prop('hidden', true).text('');
            $('#builderFilterRows, #builderSortRows').empty();
            applyRegelTyp('einzelwettbewerb');
            $('#regelnTableBody .hybrid-row').removeClass('selected');
        } else {
            $('#panelTitle').html('<i class="bi bi-pencil me-2"></i>Regel bearbeiten');
            $('#regelId').val(regel.id);
            $('#regelCode').val(regel.regel_code);
            $('#regelName').val(regel.regel_name);
            $('#regelBeschreibung').val(regel.regel_beschreibung || '');
            $('#regelSql').val(regel.sql_query || '');
            $('#regelAktiv').prop('checked', Number(regel.aktiv) === 1);
            const fuer = verwendetText(regel);
            $('#regelVerwendet').prop('hidden', false).toggleClass('keiner', !fuer).text(fuer ? fuer.charAt(0).toUpperCase() + fuer.slice(1) : 'Von keinem Wanderpreis verwendet');
            $('#btnDeleteRegel').removeClass('d-none');
            $('#btnSaveRegel').html('<i class="bi bi-save me-1"></i>Speichern');

            const typ = regel.regel_typ || 'custom';
            $('#regelTyp').val(typ);
            $('#builderFilterRows, #builderSortRows').empty();
            if (typ !== 'custom') {
                let p = {};
                try { p = regel.regel_params ? JSON.parse(regel.regel_params) : {}; } catch (e) { p = {}; }
                if (p.wettbewerb) $('#builderWettbewerb').val(p.wettbewerb);
                $('input[name="kategorie"][value="' + (p.kategorie || '').replace('Kat. ', '') + '"]').prop('checked', true);
                $('#builderRichtung').val(p.richtung === 'ASC' ? 'ASC' : 'DESC');
                if (typ === 'baukasten') {
                    (p.filter || []).forEach(f => addFilterRow(f.col, f.op, f.val));
                    (p.sort || []).forEach(s => addSortRow(s.col, s.dir));
                }
            }
            applyRegelTyp(typ);
            $('#regelnTableBody .hybrid-row').removeClass('selected');
            $('#regelnTableBody .hybrid-row[data-id="' + regel.id + '"]').addClass('selected');
        }
        $('#testResult').prop('hidden', true).empty();
        $('#panelOverlay').addClass('show');
        $('#regelPanel').addClass('open');
        document.body.style.overflow = 'hidden';
    }
    function closePanel() {
        $('#regelPanel').removeClass('open');
        $('#panelOverlay').removeClass('show');
        document.body.style.overflow = '';
        $('#regelnTableBody .hybrid-row').removeClass('selected');
    }
    $('#panelClose, #panelOverlay').on('click', closePanel);
    $(document).on('keydown', e => { if (e.key === 'Escape' && $('#regelPanel').hasClass('open')) closePanel(); });
    $('#btnNeueRegel').on('click', () => openPanel('new'));

    // ---------- Speichern / Löschen ----------
    $('#btnSaveRegel').on('click', function() {
        const $form = $('#regelForm');
        if ($('#regelTyp').val() === 'baukasten') collectBaukasten();
        if (!$form[0].checkValidity()) { $form[0].reportValidity(); return; }
        const $btn = $(this), orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Speichere...');
        $.post('wanderpreise/save_regel.php', $form.serialize(), null, 'json')
            .done(r => { if (r && r.success) { msvToast(r.message || 'Gespeichert', 'success'); closePanel(); loadRegeln(); } else msvToast((r && r.message) || 'Fehler beim Speichern', 'error'); })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Speichern'), 'error'))
            .always(() => $btn.prop('disabled', false).html(orig));
    });

    async function deleteRegel(id) {
        const r = findRegel(id);
        const fuer = r ? verwendetText(r) : '';
        const res = await msvConfirmDelete(r ? 'Regel „' + r.regel_name + '"' + (fuer ? ' (' + fuer + ')' : '') : 'diese Regel');
        if (!res.isConfirmed) return;
        $.post('wanderpreise/delete_regel.php', { id, csrf_token: CSRF }, null, 'json')
            .done(resp => { if (resp && resp.success) { msvToast('Regel gelöscht', 'success'); closePanel(); loadRegeln(); } else msvToast((resp && resp.message) || 'Fehler beim Löschen', 'error'); })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Löschen'), 'error'));
    }
    $('#btnDeleteRegel').on('click', function() { const id = $('#regelId').val(); if (id) deleteRegel(id); });

    // ---------- SQL testen (mit dem oben gewählten Jahr) ----------
    $('#btnTestSql').on('click', function() {
        const sql = $('#regelSql').val();
        if (!sql.trim()) { msvToast('Bitte SQL eingeben', 'warning'); return; }
        const jahr = parseInt($('#yearSelect').val(), 10) || new Date().getFullYear();
        const $result = $('#testResult').prop('hidden', false).html('<div class="alert alert-light border mb-0 py-2 px-3 small"><span class="spinner-border spinner-border-sm me-2"></span>Teste SQL...</div>');
        $.post('wanderpreise/test_regel_sql.php', { sql, jahr, kategorie: $('input[name="kategorie"]:checked').val() || '', csrf_token: CSRF }, null, 'json')
            .done(resp => $result.html(resp && resp.success ? renderTestResult(resp) : '<div class="alert alert-danger mb-0 py-2 px-3 small"><i class="bi bi-x-circle me-2"></i>' + escHtml((resp && resp.message) || 'Fehler') + '</div>'))
            .fail(xhr => $result.html('<div class="alert alert-danger mb-0 py-2 px-3 small">' + escHtml(ajaxMsg(xhr, 'Fehler beim Testen')) + '</div>'));
    });

    // ---------- Regel-Typ / geführter Builder ----------
    function syncKategorieVisibility() {
        const cat = $('#builderWettbewerb option:selected').data('cat');
        const show = String(cat) === '1';
        $('#builderKategorieRow').toggle(show);
        if (!show) $('#katAlle').prop('checked', true);
    }
    function applyRegelTyp(typ) {
        const builder = typ !== 'custom';
        $('#builderFields').toggle(builder);
        $('#expertenTools').toggle(!builder);
        $('#sqlCustomHelp').toggle(!builder);
        $('#einzelOnlyFields').toggle(typ === 'einzelwettbewerb');
        $('#baukastenFields').toggle(typ === 'baukasten');
        $('#sqlLabel').html(builder ? 'SQL-Vorschau <span class="hint text-muted">(automatisch erzeugt)</span>' : 'SQL-Query <span class="text-danger">*</span>');
        $('#regelSql').prop('readonly', builder).prop('required', !builder);
        if (builder) {
            syncKategorieVisibility();
            if (typ === 'baukasten') { if (!$('#builderSortRows .bk-row').length) addSortRow('score', 'DESC'); bkRefreshColumns(); collectBaukasten(); }
            refreshPreview();
        }
    }
    function refreshPreview() {
        if ($('#regelTyp').val() === 'custom') return;
        $.post('wanderpreise/build_regel_preview.php', {
            regel_typ: $('#regelTyp').val(), wettbewerb: $('#builderWettbewerb').val(),
            kategorie: $('input[name="kategorie"]:checked').val() || '', richtung: $('#builderRichtung').val(),
            filter_json: $('#builderFilterJson').val(), sort_json: $('#builderSortJson').val(), csrf_token: CSRF
        }, null, 'json')
            .done(resp => $('#regelSql').val(resp && resp.success ? resp.sql : ('-- Vorschau-Fehler: ' + ((resp && resp.message) || 'unbekannt'))))
            .fail(xhr => $('#regelSql').val('-- Vorschau-Fehler: ' + ajaxMsg(xhr, 'Server nicht erreichbar')));
    }
    const debouncedPreview = () => { clearTimeout(previewTimer); previewTimer = setTimeout(refreshPreview, 150); };

    $('#regelTyp').on('change', function() { applyRegelTyp(this.value); });
    $('#builderWettbewerb').on('change', function() { syncKategorieVisibility(); bkRefreshColumns(); collectBaukasten(); debouncedPreview(); });
    $('input[name="kategorie"], #builderRichtung').on('change', debouncedPreview);

    // ---------- Baukasten (Filter & Sortierung) ----------
    const BK_OPS = [
        { v: '=', t: '= (gleich)' }, { v: '!=', t: '≠ (ungleich)' }, { v: '>', t: '> (grösser)' }, { v: '>=', t: '≥ (grösser/gleich)' },
        { v: '<', t: '< (kleiner)' }, { v: '<=', t: '≤ (kleiner/gleich)' }, { v: 'IS NOT NULL', t: 'ist erfasst' }, { v: 'IS NULL', t: 'ist leer' }
    ];
    const bkColOptions = (wkey, sel) => ((WP_WETTBEWERBE[wkey] && WP_WETTBEWERBE[wkey].columns) || []).map(c => '<option value="' + escHtml(c.key) + '"' + (c.key === sel ? ' selected' : '') + '>' + escHtml(c.label) + '</option>').join('');
    const bkOpOptions = sel => BK_OPS.map(o => '<option value="' + o.v + '"' + (o.v === sel ? ' selected' : '') + '>' + o.t + '</option>').join('');
    function bkToggleValInput($row) { const op = $row.find('.bk-op').val(); const isNull = op === 'IS NULL' || op === 'IS NOT NULL'; $row.find('.bk-val').toggle(!isNull).prop('disabled', isNull); }
    function addFilterRow(col, op, val) {
        const $row = $('<div class="bk-row d-flex gap-1 align-items-center mb-1">' +
            '<select class="form-select form-select-sm bk-col" aria-label="Spalte">' + bkColOptions($('#builderWettbewerb').val(), col || 'score') + '</select>' +
            '<select class="form-select form-select-sm bk-op w-op" aria-label="Operator">' + bkOpOptions(op || '>=') + '</select>' +
            '<input type="text" class="form-control form-control-sm bk-val w-val" value="' + escHtml(val || '') + '" placeholder="Zahl / {jahr}" aria-label="Wert">' +
            '<button type="button" class="btn btn-sm btn-outline-danger bk-del" tabindex="-1" data-tooltip="Entfernen" aria-label="Bedingung entfernen">&times;</button></div>');
        $('#builderFilterRows').append($row);
        bkToggleValInput($row);
    }
    function addSortRow(col, dir) {
        $('#builderSortRows').append('<div class="bk-row d-flex gap-1 align-items-center mb-1">' +
            '<select class="form-select form-select-sm bk-col" aria-label="Spalte">' + bkColOptions($('#builderWettbewerb').val(), col || 'score') + '</select>' +
            '<select class="form-select form-select-sm bk-dir w-dir" aria-label="Richtung"><option value="DESC"' + ((dir || 'DESC') === 'DESC' ? ' selected' : '') + '>absteigend (höchste zuerst)</option><option value="ASC"' + (dir === 'ASC' ? ' selected' : '') + '>aufsteigend (niedrigste zuerst)</option></select>' +
            '<button type="button" class="btn btn-sm btn-outline-danger bk-del" tabindex="-1" data-tooltip="Entfernen" aria-label="Sortierung entfernen">&times;</button></div>');
    }
    function bkRefreshColumns() {
        const wkey = $('#builderWettbewerb').val();
        $('#baukastenFields .bk-col').each(function() { const cur = $(this).val(); $(this).html(bkColOptions(wkey, cur)); if ($(this).val() === null) $(this).html(bkColOptions(wkey, 'score')); });
    }
    function collectBaukasten() {
        const filter = [], sort = [];
        $('#builderFilterRows .bk-row').each(function() {
            const col = $(this).find('.bk-col').val(), op = $(this).find('.bk-op').val();
            const isNull = op === 'IS NULL' || op === 'IS NOT NULL';
            if (col && op) filter.push({ col, op, val: isNull ? '' : ($(this).find('.bk-val').val() || '').trim() });
        });
        $('#builderSortRows .bk-row').each(function() { const col = $(this).find('.bk-col').val(); if (col) sort.push({ col, dir: $(this).find('.bk-dir').val() }); });
        $('#builderFilterJson').val(JSON.stringify(filter));
        $('#builderSortJson').val(JSON.stringify(sort));
    }
    $('#btnAddFilter').on('click', () => { addFilterRow(); collectBaukasten(); debouncedPreview(); });
    $('#btnAddSort').on('click', () => { addSortRow(); collectBaukasten(); debouncedPreview(); });
    $('#builderFilterRows, #builderSortRows').on('change', '.bk-col, .bk-op, .bk-dir', function() { bkToggleValInput($(this).closest('.bk-row')); collectBaukasten(); debouncedPreview(); });
    $('#builderFilterRows').on('input', '.bk-val', () => { collectBaukasten(); debouncedPreview(); });
    $('#builderFilterRows, #builderSortRows').on('click', '.bk-del', function() { $(this).closest('.bk-row').remove(); collectBaukasten(); debouncedPreview(); });

    // ---------- Vorlage aus bestehender Regel (Experten-Modus) ----------
    function populateVorlagePicker() {
        const $sel = $('#vorlagePicker');
        $sel.find('option:gt(0)').remove();
        regelnData.forEach(r => $sel.append('<option value="' + escHtml(r.id) + '">' + escHtml(r.regel_name) + ' (' + escHtml(r.regel_code) + ')</option>'));
    }
    $('#vorlagePicker').on('change', function() {
        const r = findRegel(this.value);
        if (r) { $('#regelTyp').val('custom'); applyRegelTyp('custom'); $('#regelSql').val(r.sql_query || ''); msvToast('Vorlage übernommen (Experten-Modus)', 'info'); }
        $(this).val('');
    });

    // ---------- Suche (findet auch den Wanderpreis, für den eine Regel gilt) ----------
    $('#desktopSearch').on('input', function() {
        const term = this.value.toLowerCase();
        let n = 0;
        $('#regelnTableBody tr.hybrid-row').each(function() { const hit = $(this).text().toLowerCase().includes(term); $(this).toggle(hit); if (hit) n++; });
        $('#regelnCountBadge').text(term ? n + ' / ' + regelnData.length : regelnData.length);
    });
    $('#mobileSearch').on('input', function() {
        const term = this.value.toLowerCase();
        $('#mobileCardsScroll .mobile-card').each(function() { $(this).toggle($(this).text().toLowerCase().includes(term)); });
    });

    // ---------- Test-Ergebnis ----------
    function renderTestResult(resp) {
        if (!resp.rows || !resp.rows.length) return '<div class="alert alert-warning mb-0 py-2 px-3 small"><i class="bi bi-info-circle me-2"></i>' + escHtml(resp.message || 'Kein Ergebnis') + '</div>';
        const cols = resp.columns && resp.columns.length ? resp.columns : Object.keys(resp.rows[0]);
        let h = '<div class="alert alert-success mb-2 py-2 px-3 small"><i class="bi bi-check-circle me-2"></i>' + escHtml(resp.message) + '</div>';
        h += '<div class="table-responsive"><table class="table table-sm table-striped mb-1 fs-xs"><thead><tr>' + cols.map(c => '<th>' + escHtml(c) + '</th>').join('') + '</tr></thead><tbody>';
        resp.rows.forEach(row => { h += '<tr>' + cols.map(c => '<td>' + escHtml(row[c] == null ? '' : String(row[c])) + '</td>').join('') + '</tr>'; });
        h += '</tbody></table></div>';
        if (resp.total && resp.total > resp.rows.length) h += '<div class="form-text fs-xxs">Zeige ' + resp.rows.length + ' von ' + resp.total + ' Zeilen.</div>';
        return h;
    }

    loadRegeln();
});
</script>

<?php
include 'footer.inc.php';
?>
