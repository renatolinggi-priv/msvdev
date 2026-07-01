<?php
// jmdefinition.php – Hybrid Desktop Layout
include 'dbconnect.inc.php';

$page_specific_css = <<<'CSS'
/* ===== JM Definition – Hybrid Layout ===== */

/* --- Wrapper --- */
.table-wrapper {
  border: 1px solid #e2e8f0;
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
  overflow: visible;
}
.table-title {
  position: sticky; top: 0; z-index: 8;
  margin: 0; padding: 1rem 1.25rem; font-weight: 600;
  color: var(--th-text);
  border-bottom: 2px solid var(--cell-border);
  background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
}

/* --- Hybrid-Tabelle (Read-only) --- */
.hybrid-table {
  width: 100%; border-collapse: collapse; font-size: 0.9rem;
}
.hybrid-table thead th {
  padding: 0.85rem 1rem; font-size: 0.75rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.5px; color: var(--secondary-color);
  background-color: #f8f9fa;
  /* box-shadow statt border-bottom: verhindert Durchscheinen beim Scrollen
     unter dem sticky-Header (border-collapse:collapse-Pitfall, vgl. jmrang) */
  box-shadow: inset 0 -2px 0 #e2e8f0;
  position: sticky; top: 0; z-index: 6;
}
.hybrid-table tbody tr.hybrid-row {
  cursor: pointer; transition: background 0.15s;
}
.hybrid-table tbody tr.hybrid-row:hover {
  background: rgba(99,102,241,0.05);
  position: relative; z-index: 10;
}
.hybrid-table tbody tr.hybrid-row.selected {
  background: rgba(59,130,246,0.08);
  box-shadow: inset 4px 0 0 #3b82f6;
}
.hybrid-table tbody td {
  padding: 0.85rem 1rem; vertical-align: middle;
  border-bottom: 1px solid #f1f5f9;
}

/* Nr. Spalte */
.h-nr { font-weight: 700; color: #6366f1; text-align: center; white-space: nowrap; }
.drag-grip { color: #cbd5e1; cursor: grab; margin-right: 4px; font-size: 1.1rem; }
.drag-grip:active { cursor: grabbing; }

/* Inhaltsspalten */
.h-title { font-weight: 500; }
.h-addr { color: #64748b; font-size: 0.85rem; }
.h-dates { font-size: 0.85rem; }
.h-max { text-align: center; font-weight: 600; }

/* Flag-Dots (.flag-dots/.flag-dot*) jetzt zentral in css/msv-styles.css. */

/* Tooltip → global via msv-styles.css + msv-tooltips.js */

/* Slide-Panel (.hybrid-edit-panel/.panel-overlay/.panel-header/.panel-body/.panel-label)
   jetzt zentral in css/msv-styles.css. Breite = Default 500px. */

/* --- Drag & Drop --- */
.jm-row-dragging {
  background: #fff !important;
  box-shadow: 0 10px 24px rgba(0,0,0,.12);
  transform: scale(1.005);
  transition: none !important;
  cursor: grabbing !important;
}
.jm-row-placeholder {
  background: repeating-linear-gradient(45deg, #f1f5f9, #f1f5f9 10px, #e2e8f0 10px, #e2e8f0 20px) !important;
  border: 2px dashed #93c5fd !important;
}

/* Aktions-Card (.action-card/.action-card-header/.action-chevron) jetzt zentral in css/msv-styles.css. */

/* Skeleton Loader (.skeleton/@keyframes) jetzt zentral in css/msv-styles.css. */

/* --- Mobile Card Edit Layout --- */
.jm-edit-body { padding: 0.75rem 1rem 1rem !important; }
.jm-field-label {
  display: block; font-size: 0.8rem; font-weight: 600;
  color: #64748b; margin-bottom: 0.25rem;
}
.jm-edit-body textarea {
  width: 100% !important; min-height: 3rem !important; max-height: 8rem;
  font-size: 15px !important; resize: vertical;
}
.jm-edit-body input[type="text"],
.jm-edit-body input[type="number"] {
  width: 100% !important; font-size: 15px !important;
}
.jm-check-row {
  display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0;
}
.jm-check-row .form-check-input {
  min-width: 22px !important; min-height: 22px !important;
  width: 22px !important; height: 22px !important;
  margin: 0 !important; flex-shrink: 0;
}

/* Modal auf Handy bildschirmfuellend */
@media (max-width: 576px) {
  .modal-dialog { margin: 0; max-width: 100%; height: 100%; }
  .modal-content { height: 100%; border-radius: 0; }
}

/* --- Schiesstage-Builder --- */
.ssb-day {
  border: 1px solid #e2e8f0; border-radius: 8px;
  padding: 0.6rem 0.7rem; margin-bottom: 0.6rem; background: #f8fafc;
}
.ssb-day-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
.ssb-day-head .ssb-date { flex: 0 0 auto; max-width: 170px; }
.ssb-weekday {
  font-size: 0.8rem; font-weight: 600; color: #6366f1;
  flex: 1 1 auto; min-width: 0;
}
.ssb-windows { display: flex; flex-direction: column; gap: 0.4rem; }
.ssb-window { display: flex; align-items: center; gap: 0.4rem; }
.ssb-window .ssb-sep { color: #94a3b8; }
.ssb-window input[type="time"] { max-width: 110px; }
.ssb-window input.ssb-invalid { border-color: #ef4444; background: #fef2f2; }
.ssb-iconbtn {
  border: none; background: transparent; color: #cbd5e1;
  font-size: 1.1rem; line-height: 1; padding: 0 0.25rem; cursor: pointer;
  transition: color 0.15s;
}
.ssb-iconbtn:hover { color: #ef4444; }
.ssb-add-win {
  margin-top: 0.45rem; border: 1px dashed #cbd5e1; background: #fff;
  color: #64748b; border-radius: 6px; font-size: 0.78rem;
  padding: 0.2rem 0.6rem; cursor: pointer;
}
.ssb-add-win:hover { border-color: #6366f1; color: #6366f1; }
.ssb-add-day {
  width: 100%; border: 1px dashed #93c5fd; background: #eff6ff;
  color: #3b82f6; border-radius: 8px; font-weight: 600; font-size: 0.85rem;
  padding: 0.5rem; cursor: pointer; margin-top: 0.2rem;
}
.ssb-add-day:hover { background: #dbeafe; }
.ssb-tools { display: flex; justify-content: flex-end; margin-top: 0.5rem; }
.ssb-link {
  background: none; border: none; color: #64748b; font-size: 0.78rem;
  cursor: pointer; text-decoration: underline; padding: 0;
}
.ssb-link:hover { color: #6366f1; }
.ssb-raw-note {
  font-size: 0.78rem; color: #b45309; background: #fffbeb;
  border: 1px solid #fde68a; border-radius: 6px;
  padding: 0.35rem 0.55rem; margin-bottom: 0.5rem;
}
.ssb-empty { font-size: 0.82rem; color: #94a3b8; padding: 0.3rem 0 0.5rem; }

CSS;

include 'header.inc.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-12 col-lg-11 col-12 ps-0">
      <div class="main-content-wrapper">
        <?php $page_title = 'Jahresmeisterschaft Definition'; include 'partials/page_header.inc.php'; ?>

        <div class="content-background">
          <form id="jmdefinitionForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <!-- Jahr-Auswahl + Aktionen -->
            <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
              <div class="d-flex align-items-center gap-2">
                <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                  <i class="bi bi-calendar3 me-1"></i>Jahr:
                </label>
                <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
              </div>

              <!-- Aktionsbereich -->
<?php
              $ac_id = 'jmdefActions';
              ob_start();
              ?>
                    <div class="row g-2 mb-3">
                      <div class="col-6">
                        <button type="button" class="btn btn-outline-success btn-sm w-100"
                                data-bs-toggle="modal" data-bs-target="#newAnlassModal">
                          <i class="bi bi-plus-lg me-1"></i>Hinzufügen
                        </button>
                      </div>
                      <div class="col-6">
                        <button type="button" class="btn btn-outline-info btn-sm w-100"
                                data-bs-toggle="modal" data-bs-target="#copyYearModal"
                                data-tooltip="Anlässe vom Vorjahr übernehmen">
                          <i class="bi bi-calendar2-week me-1"></i>Vom Vorjahr
                        </button>
                      </div>
                    </div>
                    <div class="border-top pt-2 mb-1">
                      <small class="text-muted d-block mb-2"><i class="bi bi-gear me-1"></i>Verwalten</small>
                      <div class="row g-2">
                        <div class="col-6">
                          <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-save me-1"></i>Speichern
                          </button>
                        </div>
                        <div class="col-6">
                          <button type="button" id="sortByDateButton" class="btn btn-outline-secondary btn-sm w-100"
                                  data-tooltip="Sortiert alle Anlässe nach dem ersten Datum im Feld Schiesstage">
                            <i class="bi bi-sort-numeric-down me-1"></i>Sortieren
                          </button>
                        </div>
                        <div class="col-12">
                          <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="publishChangelogBtn">
                            <i class="bi bi-megaphone me-1"></i>Publizieren
                          </button>
                        </div>
                      </div>
                    </div>
                    <div class="border-top pt-2">
                      <small class="text-muted d-block mb-2"><i class="bi bi-download me-1"></i>Exporte</small>
                      <div class="row g-2">
                        <div class="col-6">
                          <button type="button" id="exportPdfButton" class="btn btn-outline-info btn-sm w-100">
                            <i class="bi bi-file-pdf me-1"></i>PDF
                          </button>
                        </div>
                        <div class="col-6">
                          <button type="button" id="exportPdfDraftButton" class="btn btn-outline-info btn-sm w-100"
                                  data-tooltip="PDF mit Wasserzeichen 'Entwurf'">
                            <i class="bi bi-file-pdf me-1"></i>Entwurf
                          </button>
                        </div>
                        <div class="col-6">
                          <button type="button" id="exportWordFragebogen" class="btn btn-outline-info btn-sm w-100">
                            <i class="bi bi-file-word me-1"></i>Fragen
                          </button>
                        </div>
                        <div class="col-6">
                          <button type="button" id="exportICSAll" class="btn btn-outline-info btn-sm w-100">
                            <i class="bi bi-calendar-plus me-1"></i>ICS
                          </button>
                        </div>
                      </div>
                      <div id="pdfDownloadLink" class="mt-2"></div>
                    </div>
              <?php
              $ac_body = ob_get_clean();
              include 'partials/action_card.inc.php';
              ?>
            </div>

            <!-- Desktop: Hybrid-Tabelle -->
            <div class="table-wrapper">
              <h5 class="table-title"><i class="bi bi-trophy me-2"></i>Jahresmeisterschaft Definition</h5>
              <div class="desktop-table-container">
                <table class="hybrid-table" id="jmHybridTabelle">
                  <thead>
                    <tr>
                      <th style="width:60px"><i class="bi bi-grip-vertical me-1"></i>Nr.</th>
                      <th style="width:25%"><i class="bi bi-tag me-1"></i>Bezeichnung</th>
                      <th style="width:20%"><i class="bi bi-geo-alt me-1"></i>Adresse</th>
                      <th style="width:28%"><i class="bi bi-calendar-event me-1"></i>Schiesstage</th>
                      <th style="width:60px; text-align:center"><i class="bi bi-bullseye me-1"></i>Max</th>
                      <th style="width:160px; text-align:center">Optionen</th>
                    </tr>
                  </thead>
                  <tbody><!-- dynamisch --></tbody>
                </table>
              </div>

              <!-- Mobile Cards -->
              <div class="mobile-cards-container" id="mobileJmdefinitionCards">
                <div class="mobile-search">
                  <div class="position-relative">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="form-control" placeholder="Suchen..." oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileJmdefinitionCards')">
                  </div>
                </div>
                <div class="mobile-cards-scroll"></div>
              </div>
            </div>

            <!-- Zusatztext + Parameter -->
            <div class="row mt-4">
              <div class="col-lg-8">
                <div class="card-base card-primary">
                  <label for="zusatzText" class="form-label"><i class="bi bi-textarea-t me-1"></i> Infotext zur JM</label>
                  <textarea class="form-control" id="zusatzText" placeholder="Hier Zusatzinformationen eingeben... Tipp: {anzahl_streicher} wird im PDF durch die aktuelle Anzahl ersetzt." rows="5"></textarea>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="card-base card-primary">
                  <label for="anzahlStreicherInput" class="form-label"><i class="bi bi-dash-circle me-1"></i> Anzahl Streicher</label>
                  <input type="number" class="form-control" id="anzahlStreicherInput" min="1" max="9" value="3">
                  <div class="form-text">Anzahl der schlechtesten Resultate, die aus der Wertung gestrichen werden.</div>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Slide-Panel (Edit) – zentrale Struktur via inc/partials/side_panel.inc.php -->
<?php
$panel_title = '<i class="bi bi-pencil-square me-2"></i>Anlass bearbeiten';
ob_start();
?>
    <div class="mb-3">
      <label class="panel-label">Bezeichnung</label>
      <textarea class="form-control" id="panelBezeichnung" rows="3"></textarea>
    </div>
    <div class="mb-3">
      <label class="panel-label"><i class="bi bi-geo-alt me-1"></i>Adresse</label>
      <textarea class="form-control" id="panelAdresse" rows="3"></textarea>
    </div>
    <div class="mb-3">
      <label class="panel-label"><i class="bi bi-calendar-event me-1"></i>Schiesstage</label>
      <textarea class="form-control ssb-raw" id="panelSchiesstage" rows="4"></textarea>
      <div class="ssb" id="panelSchiesstageBuilder"></div>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-6">
        <label class="panel-label"><i class="bi bi-bullseye me-1"></i>Max. Punkte</label>
        <input type="number" class="form-control" id="panelMaxpunkte" min="0">
      </div>
      <div class="col-6">
        <label class="panel-label"><i class="bi bi-plus-square me-1"></i>Zuschlag</label>
        <input type="number" class="form-control" id="panelZuschlag" min="0" max="99">
      </div>
    </div>
    <div class="mb-3">
      <label class="panel-label mb-2">Optionen</label>
      <div class="row g-2">
        <div class="col-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="panelStreicher">
            <label class="form-check-label" for="panelStreicher">Streicher</label>
          </div>
        </div>
        <div class="col-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="panelErweitert">
            <label class="form-check-label" for="panelErweitert">Erweitert JM</label>
          </div>
        </div>
        <div class="col-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="panelInfo">
            <label class="form-check-label" for="panelInfo">Info</label>
          </div>
        </div>
        <div class="col-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="panelGruppe">
            <label class="form-check-label" for="panelGruppe">Gruppenwettkampf</label>
          </div>
        </div>
      </div>
    </div>
    <hr>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-danger btn-sm flex-fill" id="panelDeleteBtn">
        <i class="bi bi-trash me-1"></i>Löschen
      </button>
    </div>
<?php
$panel_body = ob_get_clean();
include 'partials/side_panel.inc.php';
?>

<!-- Modal: Neuer Anlass -->
<div class="modal fade" id="newAnlassModal" tabindex="-1" aria-labelledby="newAnlassModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newAnlassModalLabel"><i class="bi bi-plus-lg me-2"></i>Neuen Anlass hinzufügen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="neueJMDefinitionBezeichnung" class="form-label">Anlassname *</label>
          <textarea class="form-control textarea-large" id="neueJMDefinitionBezeichnung" placeholder="z.B. Hanslin Gedenk Schiessen" rows="5"></textarea>
        </div>
        <div class="mb-3">
          <label for="neueJMDefinitionAdresse" class="form-label">Adresse</label>
          <textarea class="form-control textarea-large" id="neueJMDefinitionAdresse" placeholder="Schützenhaus XY&#10;Musterstrasse 1&#10;8000 Zürich" rows="5"></textarea>
        </div>
        <div class="mb-3">
          <label for="neueJMDefinitionSchiesstage" class="form-label">Schiesstage</label>
          <textarea class="form-control textarea-large ssb-raw" id="neueJMDefinitionSchiesstage" placeholder="Freitag 14. März 2025 14:00 – 17:00 Uhr&#10;Samstag 15. März 2025 08:00 – 12:00 Uhr" rows="5"></textarea>
          <div class="ssb" id="neueSchiesstageBuilder"></div>
        </div>
        <div class="mb-3">
          <label for="neueJMDefinitionMaxpunkte" class="form-label">Maximalpunkte</label>
          <input type="number" class="form-control" id="neueJMDefinitionMaxpunkte" placeholder="100" min="0">
        </div>
        <div class="mb-3">
          <label for="neueJMDefinitionZuschlag" class="form-label">Beteiligungszuschlag</label>
          <input type="number" class="form-control" id="neueJMDefinitionZuschlag" placeholder="0" min="0" max="99">
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-check mb-2">
              <input type="checkbox" class="form-check-input" id="neueJMDefinitionStreicher">
              <label class="form-check-label" for="neueJMDefinitionStreicher">Streicher</label>
            </div>
            <div class="form-check mb-2">
              <input type="checkbox" class="form-check-input" id="neueJMDefinitionErweitert">
              <label class="form-check-label" for="neueJMDefinitionErweitert">Erweiterte JM</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check mb-2">
              <input type="checkbox" class="form-check-input" id="neueJMDefinitionInfo">
              <label class="form-check-label" for="neueJMDefinitionInfo">Info</label>
            </div>
            <div class="form-check mb-2">
              <input type="checkbox" class="form-check-input" id="neueJMDefinitionGruppe">
              <label class="form-check-label" for="neueJMDefinitionGruppe">Gruppenwettkampf</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i>Abbrechen</button>
        <button type="button" class="btn btn-outline-success btn-sm" id="jmdefinitionHinzufuegen"><i class="bi bi-plus-circle me-1"></i>Hinzufügen</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Vom Vorjahr übernehmen -->
<div class="modal fade" id="copyYearModal" tabindex="-1" aria-labelledby="copyYearModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="copyYearModalLabel"><i class="bi bi-calendar2-week me-2"></i>Anlässe vom Vorjahr übernehmen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
          <label for="copySourceYear" class="form-label mb-0 fw-semibold text-nowrap">Quelljahr</label>
          <select id="copySourceYear" class="form-select form-select-sm" style="width:auto; min-width:90px;"></select>
          <span class="text-muted small ms-1">→ wird ins Jahr <strong id="copyTargetYearLabel"></strong> übernommen</span>
          <div class="form-check ms-auto mb-0">
            <input class="form-check-input" type="checkbox" id="copySelectAll">
            <label class="form-check-label small" for="copySelectAll">Alle auswählen</label>
          </div>
        </div>
        <p class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>Das Datum wird automatisch auf denselben Wochentag im Zieljahr verschoben. Bereits vorhandene Anlässe sind abgewählt.</p>
        <div id="copyList"><div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Lädt…</div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i>Abbrechen</button>
        <button type="button" class="btn btn-outline-info btn-sm" id="copyApplyBtn"><i class="bi bi-check2-circle me-1"></i>Übernehmen</button>
      </div>
    </div>
  </div>
</div>

<script>
$(function () {
  const showSuccessToast = m => msvToast(m, 'success'),
        showErrorToast   = m => msvToast(m, 'error'),
        showWarningToast = m => msvToast(m, 'warning');

  // Ajax-Fehler global
  $(document).ajaxError(function (e, xhr, settings, err) {
    console.error('Ajax Error:', { url: settings.url, err, response: xhr.responseText });
    let msg = 'Ein Fehler ist aufgetreten';
    if (xhr.status === 0)   msg = 'Keine Internetverbindung';
    else if (xhr.status === 404) msg = 'Seite nicht gefunden';
    else if (xhr.status === 500) msg = 'Serverfehler – bitte später erneut versuchen';
    else if (xhr.status === 403) msg = 'Keine Berechtigung';
    showErrorToast(msg);
  });

  const basePath = (/\/inc(\/|$)/.test(window.location.pathname)) ? '' : 'inc/';
  const currentYear = new Date().getFullYear();

  // ========== Schiesstage-Builder ==========
  // Erzeugt aus Datums-/Zeit-Pickern den kanonischen Schiesstage-Text
  // ("Wochentag TT. Monat JJJJ HH:MM – HH:MM Uhr"), den alle Parser akzeptieren.
  const SSB_MONTHS = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  const SSB_MONTHS_LC = SSB_MONTHS.map(m => m.toLowerCase());
  const SSB_WEEKDAYS = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
  const ssbPad = n => String(n).padStart(2, '0');

  // Leniente Erkennung bestehender Texte. Liefert {ok, days}.
  // ok=false => Text nicht eindeutig zerlegbar (Freitext-Fallback).
  function ssbParse(text, fallbackYear) {
    const fy = fallbackYear || currentYear;
    const norm = (text || '').replace(/–/g, '-'); // Gedankenstrich -> Bindestrich
    const lines = norm.split(/\r?\n/);
    const days = [];
    for (let raw of lines) {
      const line = raw.trim();
      if (!line) continue;
      // optionaler Wochentag (mit/ohne Komma), Tag., Monatsname, optional Jahr, Rest
      const m = line.match(/^(?:[A-Za-zÄÖÜäöü]+,?\s+)?(\d{1,2})\.\s*([A-Za-zÄÖÜäöü]+)(?:\s+(\d{4}))?\s*(.*)$/u);
      if (!m) return { ok: false };
      const day = parseInt(m[1], 10);
      const monIdx = SSB_MONTHS_LC.indexOf(m[2].toLowerCase());
      if (monIdx < 0 || day < 1 || day > 31) return { ok: false };
      const year = m[3] ? parseInt(m[3], 10) : fy;
      const rest = m[4] || '';
      const windows = [];
      const timeRe = /(\d{1,2})[:.](\d{2})\s*-\s*(\d{1,2})[:.](\d{2})/g;
      let tm;
      while ((tm = timeRe.exec(rest)) !== null) {
        windows.push({ start: ssbPad(tm[1]) + ':' + tm[2], end: ssbPad(tm[3]) + ':' + tm[4] });
      }
      // Rest enthält etwas, das keine Zeit ist (z.B. Ortsangabe) -> nicht abbildbar
      const restClean = rest.replace(/uhr/ig, '').replace(/[\s,]/g, '');
      if (windows.length === 0 && restClean !== '') return { ok: false };
      days.push({ date: year + '-' + ssbPad(monIdx + 1) + '-' + ssbPad(day), windows });
    }
    return { ok: true, days };
  }

  function ssbSerialize(days) {
    return (days || []).filter(d => d.date).map(d => {
      const p = d.date.split('-');
      const y = +p[0], mo = +p[1], da = +p[2];
      const wd = SSB_WEEKDAYS[new Date(y, mo - 1, da).getDay()];
      const head = wd + ' ' + ssbPad(da) + '. ' + SSB_MONTHS[mo - 1] + ' ' + y;
      const wins = (d.windows || []).filter(w => w.start && w.end);
      if (!wins.length) return head;
      return head + ' ' + wins.map(w => w.start + ' – ' + w.end + ' Uhr').join(', ');
    }).join('\n');
  }

  // Factory: bindet einen Builder an eine (versteckte) Textarea + Mount-Container.
  function createSchiesstageBuilder(textareaSel, mountSel) {
    const $ta = $(textareaSel);
    const $root = $(mountSel);
    let days = [];
    let rawMode = false;
    let suppressSync = false; // beim Laden keine Änderung signalisieren

    function syncOut() {
      if (rawMode || suppressSync) return;
      $ta.val(ssbSerialize(days));
      $ta[0].dispatchEvent(new Event('input', { bubbles: true }));
    }

    function readDom() {
      const arr = [];
      $root.find('.ssb-day').each(function () {
        const date = $(this).find('.ssb-date').val();
        const wins = [];
        $(this).find('.ssb-window').each(function () {
          wins.push({ start: $(this).find('.ssb-start').val(), end: $(this).find('.ssb-end').val() });
        });
        arr.push({ date, windows: wins });
      });
      return arr;
    }

    function weekdayOf(date) {
      if (!date) return '';
      const p = date.split('-');
      if (p.length !== 3) return '';
      return SSB_WEEKDAYS[new Date(+p[0], +p[1] - 1, +p[2]).getDay()] || '';
    }

    function render() {
      if (rawMode) {
        $ta.show();
        $root.html(
          '<div class="ssb-raw-note"><i class="bi bi-info-circle me-1"></i>Freitext-Modus – dieser Eintrag wird nicht automatisch strukturiert.</div>' +
          '<div class="ssb-tools"><button type="button" class="ssb-link ssb-try-builder">Strukturierte Eingabe versuchen</button></div>'
        );
        return;
      }
      $ta.hide();
      let html = '<div class="ssb-days">';
      if (!days.length) {
        html += '<div class="ssb-empty">Noch keine Schiesstage erfasst.</div>';
      }
      days.forEach((d, i) => {
        html += '<div class="ssb-day" data-i="' + i + '">' +
          '<div class="ssb-day-head">' +
            '<input type="date" class="form-control form-control-sm ssb-date" value="' + (d.date || '') + '">' +
            '<span class="ssb-weekday">' + weekdayOf(d.date) + '</span>' +
            '<button type="button" class="ssb-iconbtn ssb-del-day" title="Tag entfernen">&times;</button>' +
          '</div>' +
          '<div class="ssb-windows">';
        (d.windows || []).forEach(w => {
          const bad = w.start && w.end && w.end <= w.start ? ' ssb-invalid' : '';
          html += '<div class="ssb-window">' +
            '<input type="time" class="form-control form-control-sm ssb-start' + bad + '" value="' + (w.start || '') + '">' +
            '<span class="ssb-sep">–</span>' +
            '<input type="time" class="form-control form-control-sm ssb-end' + bad + '" value="' + (w.end || '') + '">' +
            '<button type="button" class="ssb-iconbtn ssb-del-win" title="Zeitfenster entfernen">&times;</button>' +
          '</div>';
        });
        html += '</div>' +
          '<button type="button" class="ssb-add-win"><i class="bi bi-plus"></i> Zeitfenster</button>' +
        '</div>';
      });
      html += '</div>' +
        '<button type="button" class="ssb-add-day"><i class="bi bi-calendar-plus me-1"></i>Schiesstag hinzufügen</button>' +
        '<div class="ssb-tools"><button type="button" class="ssb-link ssb-to-raw">Als Freitext bearbeiten</button></div>';
      $root.html(html);
    }

    // Events (delegiert an $root)
    $root.on('click', '.ssb-add-day', function () {
      days = readDom();
      days.push({ date: '', windows: [{ start: '', end: '' }] });
      render(); syncOut();
    });
    $root.on('click', '.ssb-del-day', function () {
      days = readDom();
      days.splice($(this).closest('.ssb-day').data('i'), 1);
      render(); syncOut();
    });
    $root.on('click', '.ssb-add-win', function () {
      days = readDom();
      days[$(this).closest('.ssb-day').data('i')].windows.push({ start: '', end: '' });
      render(); syncOut();
    });
    $root.on('click', '.ssb-del-win', function () {
      days = readDom();
      const di = $(this).closest('.ssb-day').data('i');
      const wi = $(this).closest('.ssb-day').find('.ssb-window').index($(this).closest('.ssb-window'));
      days[di].windows.splice(wi, 1);
      render(); syncOut();
    });
    $root.on('change', '.ssb-date', function () {
      const $day = $(this).closest('.ssb-day');
      $day.find('.ssb-weekday').text(weekdayOf($(this).val()));
      days = readDom(); syncOut();
    });
    $root.on('input change', '.ssb-start, .ssb-end', function () {
      days = readDom();
      const $w = $(this).closest('.ssb-window');
      const s = $w.find('.ssb-start').val(), e = $w.find('.ssb-end').val();
      $w.find('.ssb-start, .ssb-end').toggleClass('ssb-invalid', !!(s && e && e <= s));
      syncOut();
    });
    $root.on('click', '.ssb-to-raw', function () {
      days = readDom();
      rawMode = true;
      suppressSync = true; $ta.val(ssbSerialize(days)); suppressSync = false; // ohne Save-Trigger
      render();
    });
    $root.on('click', '.ssb-try-builder', function () {
      const res = ssbParse($ta.val());
      if (!res.ok) { showWarningToast('Text konnte nicht automatisch erkannt werden.'); return; }
      days = res.days; rawMode = false;
      render(); syncOut();
    });

    return {
      // Lädt bestehenden Text OHNE Save-Signal (Textarea bleibt im Original).
      load(text) {
        suppressSync = true;
        const res = ssbParse(text);
        if (res.ok) { days = res.days; rawMode = false; }
        else { days = []; rawMode = true; $ta.val(text || ''); }
        render();
        suppressSync = false;
      },
      clear() {
        suppressSync = true;
        days = []; rawMode = false; $ta.val('');
        render();
        suppressSync = false;
      }
    };
  }

  const panelSSB = createSchiesstageBuilder('#panelSchiesstage', '#panelSchiesstageBuilder');
  const neueSSB  = createSchiesstageBuilder('#neueJMDefinitionSchiesstage', '#neueSchiesstageBuilder');
  panelSSB.clear();
  neueSSB.clear();
  // Neu-Modal: Builder bei jedem Öffnen leeren
  $('#newAnlassModal').on('show.bs.modal', () => neueSSB.clear());

  // ========== Slide-Panel Steuerung ==========
  const JMEditPanel = {
    currentRowId: null,

    open(tr) {
      const id = tr.dataset.id;
      this.currentRowId = id;

      // Daten aus data-Attributen lesen
      $('#panelBezeichnung').val(tr.dataset.bezeichnung);
      $('#panelAdresse').val(tr.dataset.adresse);
      $('#panelSchiesstage').val(tr.dataset.schiesstage);
      panelSSB.load(tr.dataset.schiesstage || '');
      $('#panelMaxpunkte').val(tr.dataset.maxpunkte);
      $('#panelZuschlag').val(tr.dataset.zuschlag);
      $('#panelStreicher').prop('checked', tr.dataset.streicher === '1');
      $('#panelErweitert').prop('checked', tr.dataset.erweitert === '1');
      $('#panelInfo').prop('checked', tr.dataset.info === '1');
      $('#panelGruppe').prop('checked', tr.dataset.gruppe === '1');

      // Delete-Button konfigurieren
      $('#panelDeleteBtn').data('id', id);

      // Zeile markieren
      $('.hybrid-row').removeClass('selected');
      $(tr).addClass('selected');

      // Panel öffnen
      $('#editPanel').addClass('open');
      $('#panelOverlay').addClass('show');

      // Fokus auf erstes Feld
      setTimeout(() => $('#panelBezeichnung').focus(), 300);
    },

    close() {
      $('#editPanel').removeClass('open');
      $('#panelOverlay').removeClass('show');
      $('.hybrid-row').removeClass('selected');
      this.currentRowId = null;

      // Auto-Save bei ungespeicherten Änderungen
      if (hasChanges) {
        $('#jmdefinitionForm').submit();
      }
    },

    // Sync: Panel -> Hidden Inputs + Tabellen-TD
    syncField(fieldName, value) {
      if (!this.currentRowId) return;
      const id = this.currentRowId;
      const $row = $(`#row${id}`);

      // 1. Hidden Input updaten
      $row.find(`input[name="${fieldName}[${id}]"]`).val(value);

      // 2. data-Attribut updaten
      $row.attr(`data-${fieldName}`, value);

      // 3. Tabellen-TD updaten (Read-only Text)
      const cellMap = {
        bezeichnung: '.h-title',
        adresse: '.h-addr',
        schiesstage: '.h-dates',
        maxpunkte: '.h-max'
      };
      if (cellMap[fieldName]) {
        const displayText = value.replace(/\n/g, '<br>');
        $row.find(cellMap[fieldName]).html(displayText);
      }

      hasChanges = true;
    },

    // Sync: Checkbox-Toggle
    syncFlag(flagName, checked) {
      if (!this.currentRowId) return;
      const id = this.currentRowId;
      const $row = $(`#row${id}`);

      // 1. data-Attribut updaten
      $row.attr(`data-${flagName}`, checked ? '1' : '0');

      // 2. Hidden Input hinzufuegen/entfernen
      $row.find(`.flag-input[data-flag="${flagName}"]`).remove();
      if (checked) {
        $row.append(`<input type='hidden' name='${flagName}[${id}]' value='1' class='flag-input' data-flag='${flagName}'>`);
      }

      // 3. Flag-Dot in Tabelle updaten
      $row.find(`.flag-dot[data-flag="${flagName}"]`)
        .toggleClass('on', checked)
        .toggleClass('off', !checked);

      hasChanges = true;
    }
  };

  // Flag-Dot Tooltips → global via msv-tooltips.js

  // Panel-Events
  $(document).on('click', '.hybrid-row', function(e) {
    if ($(e.target).closest('.drag-grip').length) return;
    JMEditPanel.open(this);
  });
  $('#panelClose, #panelOverlay').on('click', () => JMEditPanel.close());
  $(document).on('keydown', e => {
    if (e.key === 'Escape') {
      if ($('#editPanel').hasClass('open')) {
        JMEditPanel.close();
        e.stopImmediatePropagation();
      }
    }
  });

  // Live-Sync bei Aenderungen im Panel
  $('#panelBezeichnung').on('input', function() { JMEditPanel.syncField('bezeichnung', this.value); });
  $('#panelAdresse').on('input', function() { JMEditPanel.syncField('adresse', this.value); });
  $('#panelSchiesstage').on('input', function() { JMEditPanel.syncField('schiesstage', this.value); });
  $('#panelMaxpunkte').on('input', function() { JMEditPanel.syncField('maxpunkte', this.value); });
  $('#panelZuschlag').on('input', function() { JMEditPanel.syncField('zuschlag', this.value); });

  // Checkbox-Sync
  $('#panelStreicher').on('change', function() { JMEditPanel.syncFlag('streicher', this.checked); });
  $('#panelErweitert').on('change', function() { JMEditPanel.syncFlag('erweitert', this.checked); });
  $('#panelInfo').on('change', function() { JMEditPanel.syncFlag('info', this.checked); });
  $('#panelGruppe').on('change', function() { JMEditPanel.syncFlag('gruppe', this.checked); });

  // Loeschen aus Panel
  $('#panelDeleteBtn').on('click', function() {
    const deleteId = $(this).data('id');
    const name = (($('#panelBezeichnung').val() || '').split('\n')[0] || '').trim() || 'diesen Anlass';
    JMEditPanel.close();
    deleteJMDefinitionById(deleteId, name);
  });

  // ========== Vom Vorjahr übernehmen ==========
  const COPY_WD = ['So','Mo','Di','Mi','Do','Fr','Sa'];
  let copyData = [];

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
      ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }
  function isoToDate(iso) { const p = iso.split('-').map(Number); return new Date(p[0], p[1]-1, p[2]); }
  function dateToIso(dt) {
    return dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2,'0') + '-' + String(dt.getDate()).padStart(2,'0');
  }
  function fmtShort(dt) {
    return COPY_WD[dt.getDay()] + ' ' + String(dt.getDate()).padStart(2,'0') + '.' + String(dt.getMonth()+1).padStart(2,'0') + '.';
  }
  // Verschiebt ein Datum auf denselben Wochentag im Zieljahr (schaltjahr-sicher).
  function shiftDateToYear(isoDate, targetYear) {
    const p = isoDate.split('-').map(Number);
    const src = new Date(p[0], p[1]-1, p[2]);
    let cand = new Date(targetYear, p[1]-1, p[2]);
    if (cand.getMonth() !== p[1]-1) cand = new Date(targetYear, p[1], 0); // 29.2. -> 28.2.
    let delta = src.getDay() - cand.getDay();
    if (delta > 3) delta -= 7; else if (delta < -3) delta += 7;
    cand.setDate(cand.getDate() + delta);
    return cand;
  }
  function smartRename(name, sourceYear, targetYear) {
    let n = String(name || '').replace(/^(\d+)\.(\s)/, (_, num, sp) => (parseInt(num,10)+1) + '.' + sp);
    n = n.replace(/\b(20\d{2})\b/g, yr => (parseInt(yr,10) === sourceYear ? String(targetYear) : yr));
    return n;
  }
  // Schiesstage parsen, jedes Datum verschieben, kanonisch serialisieren.
  function shiftSchiesstageInfo(text, sourceYear, targetYear) {
    if (!String(text || '').trim()) return { text: '', raw: false, preview: '' };
    const res = ssbParse(text, sourceYear);
    if (!res.ok) return { text: text, raw: true, preview: 'Sonderformat – Datum bitte prüfen' };
    const shifted = res.days.map(d => {
      if (!d.date) return d;
      return { date: dateToIso(shiftDateToYear(d.date, targetYear)), windows: d.windows };
    });
    const preview = res.days.map((d, i) => {
      if (!d.date) return '';
      return fmtShort(isoToDate(d.date)) + ' → ' + fmtShort(isoToDate(shifted[i].date));
    }).filter(Boolean).join(',  ');
    return { text: ssbSerialize(shifted), raw: false, preview };
  }
  function copyNorm(s) { return String(s || '').trim().toLowerCase().replace(/\s+/g, ' '); }

  function renderCopyList(events, sourceYear, targetYear) {
    const targetNames = new Set();
    $('#jmHybridTabelle tbody tr.hybrid-row').each(function () {
      const b = copyNorm($(this).attr('data-bezeichnung'));
      if (b) targetNames.add(b);
    });
    copyData = events.map(ev => {
      const newName = smartRename(ev.bezeichnung, sourceYear, targetYear);
      const sh = shiftSchiesstageInfo(ev.schiesstage, sourceYear, targetYear);
      const dup = targetNames.has(copyNorm(newName)) || targetNames.has(copyNorm(ev.bezeichnung));
      return Object.assign({}, ev, { newName, newSchiesstage: sh.text, preview: sh.preview, raw: sh.raw, dup });
    });
    if (!copyData.length) {
      $('#copyList').html('<div class="text-muted py-3 text-center">Keine Anlässe im Quelljahr.</div>');
      return;
    }
    let html = '<div class="list-group">';
    copyData.forEach((e, i) => {
      html += '<label class="list-group-item d-flex gap-2 align-items-start" style="cursor:pointer">' +
        '<input class="form-check-input flex-shrink-0 mt-1 copy-cb" type="checkbox" data-i="' + i + '"' + (e.dup ? '' : ' checked') + '>' +
        '<span class="flex-grow-1">' +
          '<span class="fw-semibold">' + escapeHtml(e.newName) + '</span>' +
          (e.dup ? ' <span class="badge bg-warning text-dark">bereits vorhanden</span>' : '') +
          (e.raw ? ' <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Datum prüfen</span>' : '') +
          '<br><small class="text-muted">' + (e.preview ? escapeHtml(e.preview) : '<em>kein Datum</em>') + '</small>' +
        '</span>' +
      '</label>';
    });
    html += '</div>';
    $('#copyList').html(html);
    syncCopySelectAll();
  }
  function syncCopySelectAll() {
    const total = $('#copyList .copy-cb').length;
    const checked = $('#copyList .copy-cb:checked').length;
    $('#copySelectAll').prop('checked', total > 0 && checked === total);
  }

  function loadCopyList() {
    const sourceYear = parseInt($('#copySourceYear').val(), 10);
    const targetYear = parseInt($('#yearSelect').val(), 10);
    $('#copyList').html('<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Lädt…</div>');
    $.getJSON(basePath + 'jmdefinition/copy_list.php', { year: sourceYear })
      .done(resp => {
        if (resp && resp.success) renderCopyList(resp.events, sourceYear, targetYear);
        else $('#copyList').html('<div class="text-danger py-3 text-center">Fehler beim Laden.</div>');
      })
      .fail(() => $('#copyList').html('<div class="text-danger py-3 text-center">Fehler beim Laden.</div>'));
  }

  $('#copyYearModal').on('show.bs.modal', function () {
    const targetYear = parseInt($('#yearSelect').val(), 10);
    $('#copyTargetYearLabel').text(targetYear);
    const $src = $('#copySourceYear').empty();
    for (let y = currentYear + 1; y >= currentYear - 3; y--) {
      if (y === targetYear) continue;
      const $o = $('<option/>').val(y).text(y);
      if (y === targetYear - 1) $o.prop('selected', true);
      $src.append($o);
    }
    loadCopyList();
  });
  $('#copySourceYear').on('change', loadCopyList);
  $('#copySelectAll').on('change', function () {
    $('#copyList .copy-cb').prop('checked', this.checked);
  });
  $(document).on('change', '.copy-cb', syncCopySelectAll);

  $('#copyApplyBtn').on('click', function () {
    const targetYear = parseInt($('#yearSelect').val(), 10);
    const chosen = [];
    $('#copyList .copy-cb:checked').each(function () { chosen.push(copyData[$(this).data('i')]); });
    if (!chosen.length) { showWarningToast('Bitte mindestens einen Anlass auswählen'); return; }
    const events = chosen.map(e => ({
      bezeichnung: e.newName, schiesstage: e.newSchiesstage, adresse: e.adresse,
      maxpunkte: e.maxpunkte, zuschlag: e.zuschlag, streicher: e.streicher,
      erweitert: e.erweitert, info: e.info, gruppe: e.gruppe
    }));
    const $b = $(this), t = $b.html();
    $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Übernehme…');
    $.post(basePath + 'jmdefinition/copy_from_year.php', {
      target_year: targetYear, events, csrf_token: $('input[name="csrf_token"]').val()
    })
      .done(resp => {
        if (resp && resp.success) {
          bootstrap.Modal.getInstance(document.getElementById('copyYearModal'))?.hide();
          showSuccessToast(resp.count + ' Anlass(e) übernommen');
          loadJMDefinition(targetYear);
        } else {
          showErrorToast(resp && resp.message ? resp.message : 'Fehler beim Übernehmen');
        }
      })
      .fail(() => showErrorToast('Fehler beim Übernehmen'))
      .always(() => $b.prop('disabled', false).html(t));
  });

  // ========== Year-Select ==========
  function initializeYearDropdown() {
    const $sel = $('#yearSelect').empty();
    for (let y = currentYear + 1; y >= currentYear - 3; y--) {
      const $o = $('<option/>').val(y).text(y);
      if (y === currentYear) $o.prop('selected', true);
      $sel.append($o);
    }
  }

  // ========== Skeleton ==========
  function showSkeleton() {
    const row = `
      <tr class="skeleton-row">
        <td><div class="skeleton" style="width:30px"></div></td>
        <td><div class="skeleton" style="width:80%"></div></td>
        <td><div class="skeleton" style="width:70%"></div></td>
        <td><div class="skeleton" style="width:90%"></div></td>
        <td><div class="skeleton" style="width:40px"></div></td>
        <td><div class="skeleton" style="width:60%"></div></td>
      </tr>`;
    $('#jmHybridTabelle tbody').html(row.repeat(5));
  }

  // ========== Laden ==========
  function loadJMDefinition(year, done) {
    JMEditPanel.close();
    showSkeleton();
    $.get(basePath + 'jmdefinition/load_jmdefinition_form.php', { year }, function(html) {
      $('#jmHybridTabelle tbody').html(html);

      // Sortable (Drag & Drop)
      $('#jmHybridTabelle tbody').sortable({
        handle: '.drag-grip',
        axis: 'y',
        tolerance: 'pointer',
        delay: 40,
        distance: 3,
        placeholder: 'jm-row-placeholder',
        helper: function(e, tr) {
          const $originals = tr.children();
          const $helper = tr.clone();
          $helper.children().each(function(i) {
            $(this).width($originals.eq(i).outerWidth());
          });
          return $helper;
        },
        start: function(e, ui) {
          JMEditPanel.close();
          ui.item.addClass('jm-row-dragging');
        },
        stop: function(e, ui) {
          ui.item.removeClass('jm-row-dragging');
        },
        update: function() {
          const order = [];
          $('#jmHybridTabelle tbody tr').each(function() {
            const id = $(this).attr('id')?.replace('row', '');
            if (id) order.push(id);
          });
          $.post(basePath + 'jmdefinition/update_order.php', {
            order,
            csrf_token: $('input[name="csrf_token"]').val()
          })
          .done(() => showSuccessToast('Reihenfolge aktualisiert'))
          .fail(() => showErrorToast('Fehler beim Aktualisieren'));
        }
      }).disableSelection();

      // Mobile Cards generieren
      MSVMobileCards.initResponsive(function() {
        MSVMobileCards.buildCards('#jmHybridTabelle', '#mobileJmdefinitionCards', {
          customHtml: function(row, cells, headers, idx) {
            if (cells.length < 6) return '';

            const d = row.dataset;
            const title = (d.bezeichnung || '').split('\n')[0] || d.bezeichnung;

            const deleteHtml = `<button class="btn btn-outline-danger btn-sm flex-fill deleteJMDefinition" data-id="${d.id}">
                   <i class="bi bi-trash me-1"></i>Löschen
                 </button>`;

            return `
              <div class="mobile-card" data-index="${idx}" data-row-id="${d.id}">
                <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                  <div>
                    <div class="fw-bold">${title}</div>
                    <small class="text-muted">Max: ${d.maxpunkte || '-'}</small>
                  </div>
                  <i class="bi bi-chevron-down"></i>
                </div>
                <div class="mobile-card-body jm-edit-body">
                  <div class="mb-2">
                    <label class="jm-field-label">Bezeichnung</label>
                    <textarea class="form-control mobile-sync" data-field="bezeichnung" data-row="${d.id}" rows="3">${d.bezeichnung || ''}</textarea>
                  </div>
                  <div class="mb-2">
                    <label class="jm-field-label"><i class="bi bi-geo-alt me-1"></i>Adresse</label>
                    <textarea class="form-control mobile-sync" data-field="adresse" data-row="${d.id}" rows="3">${d.adresse || ''}</textarea>
                  </div>
                  <div class="mb-2">
                    <label class="jm-field-label"><i class="bi bi-calendar-event me-1"></i>Schiesstage</label>
                    <textarea class="form-control mobile-sync" data-field="schiesstage" data-row="${d.id}" rows="4">${d.schiesstage || ''}</textarea>
                  </div>
                  <div class="row g-2 mb-2">
                    <div class="col-6">
                      <label class="jm-field-label">Max. Punkte</label>
                      <input type="number" class="form-control mobile-sync" data-field="maxpunkte" data-row="${d.id}" value="${d.maxpunkte || 0}" min="0">
                    </div>
                    <div class="col-6">
                      <label class="jm-field-label">Beteil.-Zuschlag</label>
                      <input type="number" class="form-control mobile-sync" data-field="zuschlag" data-row="${d.id}" value="${d.zuschlag || 0}" min="0" max="99">
                    </div>
                  </div>
                  <div class="row g-2 mb-3">
                    <div class="col-6"><div class="jm-check-row">
                      <input type="checkbox" class="form-check-input mobile-flag-sync" data-flag="streicher" data-row="${d.id}" ${d.streicher==='1'?'checked':''}>
                      <span class="small">Streicher</span>
                    </div></div>
                    <div class="col-6"><div class="jm-check-row">
                      <input type="checkbox" class="form-check-input mobile-flag-sync" data-flag="erweitert" data-row="${d.id}" ${d.erweitert==='1'?'checked':''}>
                      <span class="small">Erweitert JM</span>
                    </div></div>
                    <div class="col-6"><div class="jm-check-row">
                      <input type="checkbox" class="form-check-input mobile-flag-sync" data-flag="info" data-row="${d.id}" ${d.info==='1'?'checked':''}>
                      <span class="small">Info</span>
                    </div></div>
                    <div class="col-6"><div class="jm-check-row">
                      <input type="checkbox" class="form-check-input mobile-flag-sync" data-flag="gruppe" data-row="${d.id}" ${d.gruppe==='1'?'checked':''}>
                      <span class="small">Gruppen-WK</span>
                    </div></div>
                  </div>
                  <div class="d-flex gap-2">${deleteHtml}</div>
                </div>
              </div>`;
          }
        });
      });

      if (typeof done === 'function') done();

    }).fail(() => {
      $('#jmHybridTabelle tbody').html('<tr><td colspan="6" class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden</td></tr>');
      showErrorToast('Fehler beim Laden der Daten');
    });
  }

  // Mobile Cards -> Hidden Inputs Sync
  $(document).on('input', '.mobile-sync', function() {
    const rowId = $(this).data('row');
    const field = $(this).data('field');
    const val = $(this).val();
    const $row = $(`#row${rowId}`);
    $row.find(`input[name="${field}[${rowId}]"]`).val(val);
    $row.attr(`data-${field}`, val);
    hasChanges = true;
  });
  $(document).on('change', '.mobile-flag-sync', function() {
    const rowId = $(this).data('row');
    const flag = $(this).data('flag');
    const checked = $(this).is(':checked');
    const $row = $(`#row${rowId}`);
    $row.attr(`data-${flag}`, checked ? '1' : '0');
    $row.find(`.flag-input[data-flag="${flag}"]`).remove();
    if (checked) {
      $row.append(`<input type='hidden' name='${flag}[${rowId}]' value='1' class='flag-input' data-flag='${flag}'>`);
    }
    hasChanges = true;
  });

  function loadZusatztext() {
    $.getJSON(basePath + 'jmdefinition/load_jminformation.php', function(resp) {
      if (resp && resp.success) $('#zusatzText').val(resp.text);
    });
  }

  function loadParameter(year) {
    $.getJSON(basePath + 'jmdefinition/load_parameter.php', { year }, function(resp) {
      if (resp && resp.success) $('#anzahlStreicherInput').val(resp.excludeCount);
    });
  }

  // ========== Aenderungs-Tracking ==========
  let hasChanges = false;
  $('body').on('change input', '#zusatzText, #anzahlStreicherInput', function() {
    hasChanges = true;
  });
  $(window).on('beforeunload', function() {
    if (hasChanges) return 'Du hast ungespeicherte Änderungen. Wirklich verlassen?';
  });

  // ========== Speichern ==========
  $('#jmdefinitionForm').on('submit', function(e) {
    e.preventDefault();
    const $btn = $(this).find('button[type="submit"]');
    const txt = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

    const $prog = $('<div class="progress" style="height:3px; position:fixed; top:0; left:0; right:0; z-index:9999;"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div></div>');
    $('body').append($prog);
    let w = 0;
    const intv = setInterval(() => { w += 10; $prog.find('.progress-bar').css('width', w + '%'); if (w >= 90) clearInterval(intv); }, 100);

    const order = [];
    $('#jmHybridTabelle tbody tr').each(function() {
      const id = $(this).attr('id') ? $(this).attr('id').replace('row', '') : '';
      if (id) order.push(id);
    });

    const selectedYear = $('#yearSelect').val();
    const formData = $(this).serialize() + '&order=' + order.join(',') + '&year=' + selectedYear;
    const zusatzText = $('#zusatzText').val();
    const anzahlStreicher = parseInt($('#anzahlStreicherInput').val()) || 3;

    $.post(basePath + 'jmdefinition/save_jmdefinition.php', formData)
     .done(function(resp) {
        showSuccessToast('Änderungen gespeichert!');
        // Hinweis, falls einzelne Schiesstag-Zeilen nicht automatisch erkannt wurden
        try {
            var r = (typeof resp === 'string') ? JSON.parse(resp) : resp;
            if (r && Array.isArray(r.warnings) && r.warnings.length) {
                showWarningToast(r.warnings.length + ' Schiesstag-Zeile(n) nicht erkannt – bitte Format prüfen (z. B. „Samstag 12. April 2025 08:00 – 12:00 Uhr").');
            }
        } catch (e) { /* ignore */ }
        hasChanges = false;
        JMEditPanel.close();
        $.post(basePath + 'jmdefinition/save_jminformation.php', {
          zusatztext: zusatzText,
          csrf_token: $('input[name="csrf_token"]').val()
        });
        $.post(basePath + 'jmdefinition/save_parameter.php', {
          year: selectedYear,
          excludeCount: anzahlStreicher,
          csrf_token: $('input[name="csrf_token"]').val()
        }).always(() => setTimeout(() => loadJMDefinition(selectedYear), 500));
     })
     .fail(() => showErrorToast('Fehler beim Speichern'))
     .always(function() {
        $btn.prop('disabled', false).html(txt);
        clearInterval(intv);
        $prog.find('.progress-bar').css('width', '100%');
        setTimeout(() => $prog.remove(), 500);
     });
  });

  // ========== Neuer Anlass ==========
  function validateAnlass() {
    const bez = $('#neueJMDefinitionBezeichnung').val().trim();
    const max = $('#neueJMDefinitionMaxpunkte').val();
    const erw = $('#neueJMDefinitionErweitert').is(':checked');
    const info = $('#neueJMDefinitionInfo').is(':checked');
    if (!bez) { showWarningToast('Bitte Anlassname eingeben'); $('#neueJMDefinitionBezeichnung').focus(); return false; }
    if (!max && !erw && !info) { showWarningToast('Bitte Maximalpunkte oder eine Option wählen'); return false; }
    return true;
  }

  $('#jmdefinitionHinzufuegen').on('click', function() {
    if (!validateAnlass()) return;
    const $b = $(this), t = $b.html();
    $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Hinzufügen...');

    const payload = {
      bezeichnung: $('#neueJMDefinitionBezeichnung').val(),
      schiesstage: $('#neueJMDefinitionSchiesstage').val(),
      adresse: $('#neueJMDefinitionAdresse').val(),
      maxpunkte: $('#neueJMDefinitionMaxpunkte').val(),
      zuschlag: $('#neueJMDefinitionZuschlag').val() || 0,
      streicher: $('#neueJMDefinitionStreicher').is(':checked') ? 1 : 0,
      erweitert: $('#neueJMDefinitionErweitert').is(':checked') ? 1 : 0,
      info: $('#neueJMDefinitionInfo').is(':checked') ? 1 : 0,
      gruppe: $('#neueJMDefinitionGruppe').is(':checked') ? 1 : 0,
      year: $('#yearSelect').val(),
      csrf_token: $('input[name="csrf_token"]').val()
    };

    $.post(basePath + 'jmdefinition/add_jmdefinition.php', payload)
      .done(function() {
        $('#newAnlassModal').modal('hide');
        $('#neueJMDefinitionBezeichnung, #neueJMDefinitionAdresse, #neueJMDefinitionSchiesstage, #neueJMDefinitionMaxpunkte, #neueJMDefinitionZuschlag').val('');
        $('#neueJMDefinitionStreicher, #neueJMDefinitionErweitert, #neueJMDefinitionInfo, #neueJMDefinitionGruppe').prop('checked', false);
        neueSSB.clear();
        showSuccessToast('Anlass hinzugefügt!');
        loadJMDefinition($('#yearSelect').val());
      })
      .fail(() => showErrorToast('Fehler beim Hinzufügen'))
      .always(() => $b.prop('disabled', false).html(t));
  });

  // ========== Loeschen ==========
  $(document).on('click', '.deleteJMDefinition', function() {
    const deleteId = $(this).data('id');
    if (!deleteId) return;
    const name = ((($('#jmHybridTabelle tbody tr.hybrid-row[data-id="' + deleteId + '"]').attr('data-bezeichnung')) || '').split('\n')[0] || '').trim() || 'diesen Anlass';
    deleteJMDefinitionById(deleteId, name);
  });

  function deleteJMDefinitionById(deleteId, name) {
    if (!deleteId) return;
    msvConfirmDelete(name).then(function(res) {
      if (!res.isConfirmed) return;
      $.post(basePath + 'jmdefinition/delete_jmdefinition.php', {
        id: deleteId,
        csrf_token: $('input[name="csrf_token"]').val()
      })
      .done(function() {
        showSuccessToast('Eintrag gelöscht');
        loadJMDefinition($('#yearSelect').val());
      })
      .fail(function(xhr) {
        let msg = 'Fehler beim Löschen';
        try {
          const resp = JSON.parse(xhr.responseText);
          if (resp.message) msg = resp.message;
        } catch(e) {}
        showErrorToast(msg);
      });
    });
  }

  // ========== Exporte ==========
  function triggerDownload(url) {
    const a = document.createElement('a');
    a.href = url; a.download = '';
    document.body.appendChild(a); a.click(); a.remove();
  }

  $('#exportPdfButton').on('click', function(e) {
    e.preventDefault();
    const $btn = $(this), originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
    $.getJSON(basePath + 'jmdefinition/export_jmdefinition_pdf.php', { year: $('#yearSelect').val() })
      .done(function(resp) {
        if (resp && resp.success && resp.pdf_link) { triggerDownload(resp.pdf_link); showSuccessToast('PDF wird heruntergeladen'); }
        else { showErrorToast(resp.message || 'PDF konnte nicht generiert werden'); }
      })
      .fail(() => showErrorToast('PDF-Fehler'))
      .always(() => $btn.prop('disabled', false).html(originalText));
  });

  $('#exportPdfDraftButton').on('click', function(e) {
    e.preventDefault();
    const $btn = $(this), originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
    $.getJSON(basePath + 'jmdefinition/export_jmdefinition_pdf.php', { year: $('#yearSelect').val(), draft: 1 })
      .done(function(resp) {
        if (resp && resp.success && resp.pdf_link) { triggerDownload(resp.pdf_link); showSuccessToast('Entwurf-PDF wird heruntergeladen'); }
        else { showErrorToast(resp.message || 'PDF konnte nicht generiert werden'); }
      })
      .fail(() => showErrorToast('PDF-Fehler'))
      .always(() => $btn.prop('disabled', false).html(originalText));
  });

  $('#exportWordFragebogen').on('click', function(e) {
    e.preventDefault();
    const $btn = $(this), originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
    $.getJSON(basePath + 'jmdefinition/export_fragebogen.php', { year: $('#yearSelect').val() })
      .done(function(resp) {
        if (resp && resp.success && resp.word_link) { triggerDownload(resp.word_link); showSuccessToast('Fragebogen wird heruntergeladen'); }
        else { showErrorToast(resp.message || 'Fragebogen konnte nicht generiert werden'); }
      })
      .fail(() => showErrorToast('Word-Fehler'))
      .always(() => $btn.prop('disabled', false).html(originalText));
  });

  $('#exportICSAll').on('click', function(e) {
    e.preventDefault();
    const $btn = $(this), originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
    $.getJSON(basePath + 'jmdefinition/export_all_ics.php')
      .done(function(resp) {
        if (resp && resp.success && resp.ics_link) { triggerDownload(resp.ics_link); showSuccessToast('ICS wird heruntergeladen'); }
        else { showErrorToast(resp.message || 'ICS konnte nicht generiert werden'); }
      })
      .fail(() => showErrorToast('ICS-Fehler'))
      .always(() => $btn.prop('disabled', false).html(originalText));
  });

  // ========== Sortieren ==========
  $('#sortByDateButton').on('click', function(e) {
    e.preventDefault();
    const $btn = $(this), originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Sortiere...');
    $.post(basePath + 'jmdefinition/sort_by_date.php', {
      year: $('#yearSelect').val(),
      csrf_token: $('input[name="csrf_token"]').val()
    })
      .done(function(resp) {
        if (resp && resp.success) { showSuccessToast('Anlässe nach Datum sortiert'); loadJMDefinition($('#yearSelect').val()); }
        else { showErrorToast(resp.message || 'Sortierung fehlgeschlagen'); }
      })
      .fail(() => showErrorToast('Fehler beim Sortieren'))
      .always(() => $btn.prop('disabled', false).html(originalText));
  });

  // ========== Jahrwechsel ==========
  $('#yearSelect').on('change', function() {
    const y = $(this).val();
    loadJMDefinition(y);
    loadZusatztext();
    loadParameter(y);
  });

  // ========== Veröffentlichen ==========
  $('#publishChangelogBtn').on('click', async function() {
    const r = await msvConfirm('Änderung veröffentlichen?', 'Ein Eintrag wird auf der Website angezeigt.', 'Veröffentlichen');
    if (!r.isConfirmed) return;
    $.post(basePath + 'changelog_publish.php', {
        kategorie: 'definition',
        tabelle: 'JMDefinition',
        jahr: $('#yearSelect').val(),
        beschreibung: 'Jahresprogramm ' + $('#yearSelect').val() + ' aktualisiert',
        csrf_token: $('input[name="csrf_token"]').val()
    }).done(function(res) {
        if (res.success) msvToast(res.message, 'success');
        else msvToast(res.message || 'Fehler', 'error');
    }).fail(function() {
        msvToast('Veröffentlichung fehlgeschlagen', 'error');
    });
  });

  // ========== Shortcuts ==========
  $(document).on('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); $('#jmdefinitionForm').trigger('submit'); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') { e.preventDefault(); $('#newAnlassModal').modal('show'); }
  });

  $('#newAnlassModal').on('shown.bs.modal', () => $('#neueJMDefinitionBezeichnung').trigger('focus'));

  // ========== Start ==========
  initializeYearDropdown();
  loadJMDefinition(currentYear);
  loadZusatztext();
  loadParameter(currentYear);
});
</script>

<style>
@media (max-width: 767.98px) {
  .desktop-table-container { display: none !important; }
  .mobile-cards-container { display: block !important; }
  .hybrid-edit-panel, .panel-overlay { display: none !important; }
}
@media (min-width: 768px) {
  .mobile-cards-container { display: none !important; }
}
</style>

<?php include 'footer.inc.php'; ?>
