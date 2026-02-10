<?php
// jmdefinition.php â€“ überarbeitet
include 'dbconnect.inc.php';

// Seitenspezifische Styles (leichtgewichtig, nutzt deine Global-Tokens)
$page_specific_css = <<<'CSS'
/* ===== JM Definition â€“ kompakt & robust ===== */

/* --- Wrapper/Viewport --- */
.table-wrapper {
  border: 1px solid #e2e8f0;
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
  /* wichtig: Header darf überstehen, sonst überdecken sticky-cols den Header */
  overflow: visible;
}
.table-title {
  position: sticky; top: 0; z-index: 8;              /* über dem thead */
  margin: 0; padding: 1rem 1.25rem; font-weight: 600;
  color: var(--th-text);
  border-bottom: 2px solid var(--cell-border);
  background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
}
.table-responsive { position: relative; overflow: auto; }

/* --- Tabelle Grundlayout --- */
#jmdefinitionTabelle {
  width: 100%;
  table-layout: fixed;
  /* feste Breiten für die sticky-Offsets */
  --col1: 110px;    /* Nr. (1. Spalte) */
  --col2: 280px;    /* Bezeichnung (2. Spalte) */
}
#jmdefinitionTabelle thead th {
  position: sticky; top: 0; z-index: 6;              /* Header über Body-Sticky */
  padding: 1rem .75rem;
  vertical-align: middle;
  font-weight: 600;
  text-transform: uppercase;
  font-size: .75rem;
  letter-spacing: .5px;
  color: #475569;
  background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
  border-bottom: 2px solid #e2e8f0;
}

/* --- Spaltenbreiten (Kern) --- */
#jmdefinitionTabelle th:nth-child(1),
#jmdefinitionTabelle td:nth-child(1) { width: var(--col1); min-width: var(--col1); max-width: var(--col1); }
#jmdefinitionTabelle th:nth-child(2),
#jmdefinitionTabelle td:nth-child(2) { width: var(--col2); min-width: var(--col2); max-width: var(--col2); }

#jmdefinitionTabelle th:nth-child(3),  #jmdefinitionTabelle td:nth-child(3)  { width: 20%; min-width: 200px; }
#jmdefinitionTabelle th:nth-child(4),  #jmdefinitionTabelle td:nth-child(4)  { width: 20%; min-width: 200px; }
#jmdefinitionTabelle th:nth-child(5),  #jmdefinitionTabelle td:nth-child(5)  { width: 8%;  text-align: center; }
#jmdefinitionTabelle th:nth-child(6),  #jmdefinitionTabelle td:nth-child(6)  { width: 8%;  text-align: center; }
#jmdefinitionTabelle th:nth-child(7),
#jmdefinitionTabelle th:nth-child(8),
#jmdefinitionTabelle th:nth-child(9),
#jmdefinitionTabelle th:nth-child(10),
#jmdefinitionTabelle th:nth-child(11),
#jmdefinitionTabelle td:nth-child(7),
#jmdefinitionTabelle td:nth-child(8),
#jmdefinitionTabelle td:nth-child(9),
#jmdefinitionTabelle td:nth-child(10),
#jmdefinitionTabelle td:nth-child(11) { width: 6%; text-align: center; }

/* --- Sticky: 1 (Nr.), 2 (Bezeichnung), letzte (Aktion) --- */
/* Header-Zellen */
#jmdefinitionTabelle thead th:first-child {
  position: sticky; left: 0; z-index: 7;
  border-right: 2px solid var(--cell-border);
  box-shadow: 2px 0 0 rgba(0,0,0,.02);
}
#jmdefinitionTabelle thead th:nth-child(2) {
  position: sticky; left: var(--col1); z-index: 7;
  border-right: 2px solid var(--cell-border);
  box-shadow: 2px 0 0 rgba(0,0,0,.02);
}
#jmdefinitionTabelle thead th:last-child {
  position: sticky; right: 0; z-index: 7;
  border-left: 2px solid var(--cell-border);
  box-shadow: -2px 0 0 rgba(0,0,0,.02);
}
/* Body-Zellen */
#jmdefinitionTabelle tbody td:first-child {
  position: sticky; left: 0; z-index: 3;
  border-right: 2px solid #eef2f7;
  box-shadow: 2px 0 0 rgba(0,0,0,.02);
  text-align: center; font-weight: 700; color: var(--info);
  cursor: grab;
}
#jmdefinitionTabelle tbody td:first-child:active { cursor: grabbing; }
#jmdefinitionTabelle tbody td:first-child::before {
  content: 'â‹®â‹®'; color: #cbd5e1; font-size: .9rem; margin-right: .25rem; vertical-align: middle;
}
#jmdefinitionTabelle tbody td:nth-child(2) {
  position: sticky; left: var(--col1); z-index: 3;
  border-right: 2px solid #eef2f7;
  box-shadow: 2px 0 0 rgba(0,0,0,.02);
}
#jmdefinitionTabelle tbody td:last-child {
  position: sticky; right: 0; z-index: 3;
  border-left: 2px solid #eef2f7;
  box-shadow: -2px 0 0 rgba(0,0,0,.02);
}

/* --- Einheitlicher Zeilen-Hintergrund (inkl. Sticky) --- */
#jmdefinitionTabelle tbody tr {
  --row-bg: #fff;                         /* Default */
  --row-hover: rgba(99,102,241,0.06);     /* sehr zartes Indigo */
  background-color: var(--row-bg);
}
#jmdefinitionTabelle tbody tr:nth-child(even) { --row-bg: rgba(248,249,250,.5); }
#jmdefinitionTabelle tbody tr:hover { --row-bg: var(--row-hover); }

#jmdefinitionTabelle tbody td,
#jmdefinitionTabelle tbody th { background-color: var(--row-bg) !important; }

/* leichte Nähte nur als Linie, keine harten Schatten */
#jmdefinitionTabelle tbody tr:hover td:first-child { box-shadow: 1px 0 0 rgba(0,0,0,.02); }
#jmdefinitionTabelle tbody tr:hover td:last-child  { box-shadow: -1px 0 0 rgba(0,0,0,.02); }

/* --- zarte vertikale Trenner an sinnvollen Stellen --- */
#jmdefinitionTabelle td:nth-child(5),
#jmdefinitionTabelle td:nth-child(11) { border-left: 2px solid #f1f3f4; }

/* --- Inputs kompakt & ruhig --- */
#jmdefinitionTabelle input[type="text"],
#jmdefinitionTabelle input[type="number"],
#jmdefinitionTabelle textarea {
  padding: .35rem .5rem;
  font-size: .9rem;
  border-radius: .4rem;
  border: 1px solid #e2e8f0;
  background: #fff;
  transition: border-color var(--transition), box-shadow var(--transition), background-color var(--transition);
}
#jmdefinitionTabelle textarea { min-height: 5.5rem; resize: vertical; }
#jmdefinitionTabelle input:hover,
#jmdefinitionTabelle textarea:hover { border-color: #93c5fd; background: #f8fafc; }
#jmdefinitionTabelle input:focus,
#jmdefinitionTabelle textarea:focus {
  border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(59,130,246,.15); outline: none;
}

/* --- Checkboxen etwas größer & klickfreundlich --- */
#jmdefinitionTabelle .form-check-input {
  width: 1.1rem; height: 1.1rem; margin-top: .15rem;
  border: 2px solid #cbd5e1;
}
#jmdefinitionTabelle .form-check-input:checked { background-color: #3b82f6; border-color: #3b82f6; }

/* --- Drag & Drop (Helper/Placeholder) --- */
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

/* --- Toolbar & Buttons (leicht) --- */
.button-toolbar {
  display: flex; flex-wrap: wrap; gap: .5rem; align-items: center;
  background: #fff; border: 1px solid #e2e8f0; border-radius: var(--radius); box-shadow: var(--shadow-sm);
  padding: 1.25rem;
}
.btn-compact { padding: .45rem .75rem; font-size: .875rem; }

/* --- Kompakter Tabellenmodus (optional) --- */
.table--compact { font-size: .92rem; }
.table--compact th, .table--compact td { padding: .55rem .6rem; }

/* --- Skeleton Loader (beim Laden) --- */
.skeleton {
  height: 20px; border-radius: 4px;
  background: linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);
  background-size: 200% 100%; animation: loading 1.5s infinite;
}
@keyframes loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* --- Responsiv: Sticky bei schmalen Viewports deaktivieren --- */
@media (max-width: 1200px) {
  #jmdefinitionTabelle thead th:first-child,
  #jmdefinitionTabelle thead th:nth-child(2),
  #jmdefinitionTabelle thead th:last-child,
  #jmdefinitionTabelle tbody td:first-child,
  #jmdefinitionTabelle tbody td:nth-child(2),
  #jmdefinitionTabelle tbody td:last-child {
    position: static; box-shadow: none;
  }
}
/* Einheitlicher Zeilenhover â€“ inkl. Sticky und Formfelder */
#jmdefinitionTabelle {
  /* Bootstrap-Defaults neutralisieren, damit nur unsere Farben gelten */
  --bs-table-hover-bg: transparent;
  --bs-table-striped-bg: transparent;
}

/* Alle Body-Zellen bekommen dieselbe Hintergrund-Var */
#jmdefinitionTabelle tbody tr {
  --row-bg: #fff;
  --row-hover: rgba(99,102,241,0.06); /* zartes Indigo */
}
#jmdefinitionTabelle tbody tr:nth-child(even) { --row-bg: rgba(248,249,250,.5); }

#jmdefinitionTabelle tbody > tr > * {
  background-color: var(--row-bg) !important;
  transition: background-color var(--transition);
}
#jmdefinitionTabelle tbody tr:hover > * {
  background-color: var(--row-hover) !important;
}

/* Formfelder im Hover: transparent, damit der Zeilenhover durchscheint */
#jmdefinitionTabelle tbody tr:hover input[type="text"],
#jmdefinitionTabelle tbody tr:hover input[type="number"],
#jmdefinitionTabelle tbody tr:hover textarea,
#jmdefinitionTabelle tbody tr:hover .form-check-input {
  background-color: transparent !important;
}

/* Im Fokus weiterhin weiß für Kontrast/Lesbarkeit */
#jmdefinitionTabelle tbody tr:hover input:focus,
#jmdefinitionTabelle tbody tr:hover textarea:focus {
  background-color: #fff !important;
}

/* optionale, sehr dezente Naht an den sticky-Kanten beim Hover */
#jmdefinitionTabelle tbody tr:hover td:first-child { box-shadow: 1px 0 0 rgba(0,0,0,.02); }
#jmdefinitionTabelle tbody tr:hover td:last-child  { box-shadow: -1px 0 0 rgba(0,0,0,.02); }

/* ===== Mobile-Optimierungen (â‰¤ 768px) ===== */
@media (max-width: 768px) {
  /* Sticky abschalten, Schatten/Trenner reduzieren */
  #jmdefinitionTabelle thead th,
  #jmdefinitionTabelle tbody td {
    position: static !important;
    box-shadow: none !important;
  }

  /* Schrift & Abstände kompakter, Touch-Ziele größer */
  #jmdefinitionTabelle { font-size: .93rem; }
  #jmdefinitionTabelle th, #jmdefinitionTabelle td { padding: .5rem .6rem; }
  .btn, .form-check-input { min-height: 44px; min-width: 44px; }

  /* Weniger wichtige Spalten ausblenden (Zuschlag + Checkbox-Spalten) */
  /* sichtÂ­bar bleiben: 1=Nr, 2=Bezeichnung, 4=Schiesstage, 11=Aktion */
  /* 3=Adresse, 5=Max, 6=Zuschlag, 7..10=Checkboxen werden versteckt */
  #jmdefinitionTabelle th:nth-child(3),
  #jmdefinitionTabelle td:nth-child(3),
  #jmdefinitionTabelle th:nth-child(5),
  #jmdefinitionTabelle td:nth-child(5),
  #jmdefinitionTabelle th:nth-child(6),
  #jmdefinitionTabelle td:nth-child(6),
  #jmdefinitionTabelle th:nth-child(7),
  #jmdefinitionTabelle td:nth-child(7),
  #jmdefinitionTabelle th:nth-child(8),
  #jmdefinitionTabelle td:nth-child(8),
  #jmdefinitionTabelle th:nth-child(9),
  #jmdefinitionTabelle td:nth-child(9),
  #jmdefinitionTabelle th:nth-child(10),
  #jmdefinitionTabelle td:nth-child(10) {
    display: none !important;
  }

  /* Inputs/Textareas auf Mobile über volle Zellenbreite */
  #jmdefinitionTabelle textarea,
  #jmdefinitionTabelle input[type="text"],
  #jmdefinitionTabelle input[type="number"] {
    width: 100%;
  }

  /* Aktion-Spalte etwas breiter machen für Mehr-Button + Löschen etc. */
  #jmdefinitionTabelle th:last-child,
  #jmdefinitionTabelle td:last-child {
    width: 110px;
    min-width: 110px;
  }

  /* „Mehr"-Panel: eingeblendete, ausgeblendete Spalten als Block unter der Zeile */
  .jm-row-more {
    display: none;
    background: rgba(248,249,250,.6);
    border-top: 1px solid #eef2f7;
    padding: .75rem .75rem 1rem;
  }
  tr.jm-expanded + tr.jm-row-more { display: table-row; }
  .jm-row-more td {
    padding: .6rem .75rem !important;
    background: transparent !important;
  }
  .jm-row-more .jm-more-grid {
    display: grid;
    grid-template-columns: 1fr;
    row-gap: .5rem;
  }
  .jm-row-more .jm-more-item label {
    font-size: .8rem; color: #64748b; display: block; margin-bottom: .15rem;
  }
}

/* Modal auf Handy bildschirmfüllend */
@media (max-width: 576px) {
  .modal-dialog { margin: 0; max-width: 100%; height: 100%; }
  .modal-content { height: 100%; border-radius: 0; }
}

CSS;

/* Header bindet $page_specific_css ein */
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
        <div class="row mb-4">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary);">
              <i class="bi bi-trophy me-2"></i>Jahresmeisterschaft Definition
            </h2>
          </div>
        </div>

        <div class="content-background">
          <form id="jmdefinitionForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <!-- Jahr-Auswahl -->
            <div class="year-selection-card">
              <div class="row align-items-center">
                <div class="col-md-5">
                  <label for="yearSelect" class="form-label fw-bold">
                    <i class="bi bi-calendar3 me-1"></i> Jahr auswählen:
                  </label>
                  <select id="yearSelect" class="form-select"></select>
                </div>
              </div>
            </div>

            <!-- Buttons -->
<div class="button-toolbar">
  <div class="btn-row d-flex flex-wrap gap-2">
    <button type="button"
            class="btn btn-outline-success btn-compact"
            data-bs-toggle="modal" data-bs-target="#newAnlassModal">
      <i class="bi bi-plus-lg"></i>
      <span class="ms-1">Neuer Anlass</span>
    </button>

    <button type="button" id="sortByDateButton"
            class="btn btn-outline-primary btn-compact"
            title="Sortiert alle Anlässe nach dem ersten Datum im Feld Schiesstage">
      <i class="bi bi-sort-numeric-down"></i>
      <span class="ms-1">Nach Datum sortieren</span>
    </button>

    <button type="submit" class="btn btn-outline-primary btn-compact">
      <i class="bi bi-save"></i>
      <span class="ms-1">Speichern</span>
    </button>

    <!-- Export: einzelne Buttons statt Dropdown -->
    <button type="button" id="exportPdfButton"
            class="btn btn-outline-info btn-compact">
      <i class="bi bi-file-pdf"></i>
      <span class="ms-1">JM als PDF</span>
    </button>

    <button type="button" id="exportPdfDraftButton"
            class="btn btn-outline-warning btn-compact"
            title="PDF mit Wasserzeichen 'Entwurf'">
      <i class="bi bi-file-pdf"></i>
      <span class="ms-1">PDF Entwurf</span>
    </button>

    <button type="button" id="exportWordFragebogen"
            class="btn btn-outline-info btn-compact">
      <i class="bi bi-file-word"></i>
      <span class="ms-1">Fragebogen</span>
    </button>

    <button type="button" id="exportICSAll"
            class="btn btn-outline-info btn-compact">
      <i class="bi bi-calendar-plus"></i>
      <span class="ms-1">ICS-Datei</span>
    </button>
  </div>

  <!-- Download-Link/Status rechts -->
  <div id="pdfDownloadLink" class="ms-auto"></div>
</div>

            <div id="message"></div>

            <!-- Tabelle -->
            <div class="table-wrapper">
              <h5 class="table-title"><i class="bi bi-trophy me-2"></i> Jahresmeisterschaft Definition</h5>
              <div class="table-responsive">
                <table class="table table-hover table-striped table-borderless table--compact" id="jmdefinitionTabelle">

                  <thead>
                    <tr>
                      <th scope="col" style="min-width: 100px;">
                        <span class="d-flex align-items-center">
                          <i class="bi bi-grip-vertical me-1" aria-hidden="true"></i><span>Nr.</span>
                        </span>
                      </th>
                      <th scope="col" style="min-width: 200px;"><i class="bi bi-tag me-1"></i>Bezeichnung</th>
                      <th scope="col" style="min-width: 200px;"><i class="bi bi-geo-alt me-1"></i>Adresse</th>
                      <th scope="col" style="min-width: 200px;"><i class="bi bi-calendar-event me-1"></i>Schiesstage</th>
                      <th scope="col" class="text-center"><i class="bi bi-bullseye me-1"></i>Max</th>
                      <th scope="col" class="text-center"><i class="bi bi-plus-square me-1"></i>Zuschlag</th>
                      <th scope="col" class="text-center" title="Streicher: Wird aus der Wertung genommen"><i class="bi bi-dash-circle" aria-label="Streicher"></i></th>
                      <th scope="col" class="text-center" title="Erweitert JM"><i class="bi bi-plus-circle"></i></th>
                      <th scope="col" class="text-center" title="Info"><i class="bi bi-info-circle"></i></th>
                      <th scope="col" class="text-center" title="Gruppenwettkampf"><i class="bi bi-people"></i></th>
                      <th scope="col" class="text-center" title="Aktion"><i class="bi bi-gear"></i></th>
                    </tr>
                  </thead>
                  <tbody><!-- dynamisch --></tbody>
                </table>
              </div>
            </div>

            <!-- Zusatztext -->
            <div class="row mt-4">
              <div class="col-lg-8">
                <div class="card-base card-primary">
                  <label for="zusatzText" class="form-label"><i class="bi bi-textarea-t me-1"></i> Infotext zur JM</label>
                  <textarea class="form-control" id="zusatzText" placeholder="Hier Zusatzinformationen eingeben..." rows="5"></textarea>
                </div>
              </div>
            </div>
          </form>
        </div> <!-- /.content-background -->
      </div> <!-- /.main-content-wrapper -->
    </div> <!-- /.col -->
  </div> <!-- /.row -->
</div> <!-- /.container-fluid -->

<!-- Modal: Neuer Anlass -->
<div class="modal fade" id="newAnlassModal" tabindex="-1" aria-labelledby="newAnlassModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newAnlassModalLabel"><i class="bi bi-plus-lg me-2"></i>Neuen Anlass hinzufügen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
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
          <textarea class="form-control textarea-large" id="neueJMDefinitionSchiesstage" placeholder="Freitag 14. März 2025 14:00 â€“ 17:00 Uhr&#10;Samstag 15. März 2025 08:00 â€“ 12:00 Uhr" rows="5"></textarea>
        </div>
        <div class="mb-3">
          <label for="neueJMDefinitionMaxpunkte" class="form-label">Maximalpunkte</label>
          <input type="number" class="form-control" id="neueJMDefinitionMaxpunkte" placeholder="100" min="0">
        </div>
        <div class="mb-3">
          <label for="neueJMDefinitionZuschlag" class="form-label">Zuschlag</label>
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
        <button type="button" class="btn btn-outline-secondary btn-compact" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i>Abbrechen</button>
        <button type="button" class="btn btn-outline-success btn-compact" id="jmdefinitionHinzufuegen"><i class="bi bi-plus-circle me-1"></i>Hinzufügen</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Löschen bestätigen -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteModalLabel">
          <i class="bi bi-exclamation-triangle text-warning me-2"></i>Bestätigung erforderlich
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center">
          <i class="bi bi-exclamation-triangle text-warning me-3" style="font-size: 2rem;"></i>
          <div>
            <strong>Möchtest du diesen Anlass wirklich löschen?</strong>
            <br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden.</small>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-compact" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-1"></i>Abbrechen
        </button>
        <button type="button" class="btn btn-outline-danger btn-compact" id="confirmDeleteButton">
          <i class="bi bi-trash me-1"></i>Löschen
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// jQuery, Bootstrap & jQuery UI kommen aus dem Header

$(function () {
  // Tooltips (auch für dynamische Inhalte später erneut initialisieren)
  document.querySelectorAll('[data-bs-toggle="tooltip"], [title]').forEach(el => new bootstrap.Tooltip(el));

  const showSuccessToast=m=>msvToast(m,'success'), showErrorToast=m=>msvToast(m,'error'), showWarningToast=m=>msvToast(m,'warning'), showInfoToast=m=>msvToast(m,'info');

  // Ajax-Fehler global
  $(document).ajaxError(function (e, xhr, settings, err) {
    console.error('Ajax Error:', { url: settings.url, err, response: xhr.responseText });
    let msg='Ein Fehler ist aufgetreten';
    if (xhr.status===0) msg='Keine Internetverbindung';
    else if (xhr.status===404) msg='Seite nicht gefunden';
    else if (xhr.status===500) msg='Serverfehler â€“ bitte später erneut versuchen';
    else if (xhr.status===403) msg='Keine Berechtigung';
    showErrorToast(msg);
  });

  // Basispfad automatisch ermitteln:
  // Läuft diese Seite aus /inc/? Dann ohne Präfix. Sonst 'inc/' voranstellen.
  const basePath = (/\/inc(\/|$)/.test(window.location.pathname)) ? '' : 'inc/';

  const currentYear = new Date().getFullYear();

  // Year-Select
  function initializeYearDropdown(){
    const $sel = $('#yearSelect').empty();
    const currentMonth = new Date().getMonth() + 1; // 1-12
    // Ab Oktober (Monat 10) auch Folgejahr anzeigen
    const maxYear = (currentMonth >= 10) ? currentYear + 1 : currentYear;
    
    for (let y=2024; y<=maxYear; y++){
      const $o = $('<option/>').val(y).text(y);
      if (y===currentYear) $o.prop('selected', true);
      $sel.append($o);
    }
  }

  // Skeleton
  function showSkeleton(){
    const row = `
      <tr class="skeleton-row">
        <td><div class="skeleton" style="width:30px;"></div></td>
        <td><div class="skeleton" style="width:80%;"></div></td>
        <td><div class="skeleton" style="width:70%;"></div></td>
        <td><div class="skeleton" style="width:90%;"></div></td>
        <td><div class="skeleton" style="width:40px;"></div></td>
        <td colspan="6"><div class="skeleton" style="width:50%;"></div></td>
      </tr>`;
    $('#jmdefinitionTabelle tbody').html(row.repeat(5));
  }

  // Laden
  function loadJMDefinition(year){
    showSkeleton();
    $.get(basePath + 'jmdefinition/load_jmdefinition_form.php', { year }, function(html){
      $('#jmdefinitionTabelle tbody').html(html);

      // Sortable
      $('#jmdefinitionTabelle tbody').sortable({
        handle: 'td:first-child',
        axis: 'y',
        tolerance: 'pointer',
        delay: 40,
        distance: 3,
        scroll: true,
        scrollSensitivity: 45,
        scrollSpeed: 20,
        forcePlaceholderSize: true,
        placeholder: 'jm-row-placeholder',
        helper: function (e, tr) {
          const $originals = tr.children();
          const $helper = tr.clone();
          $helper.children().each(function (index) {
            $(this).width($originals.eq(index).outerWidth());
          });
          return $helper;
        },
        start: function (e, ui) {
          ui.item.addClass('jm-row-dragging');
        },
        stop: function (e, ui) {
          ui.item.removeClass('jm-row-dragging');
        },
        update: function () {
          const order = [];
          $('#jmdefinitionTabelle tbody tr').each(function () {
            const id = $(this).attr('id')?.replace('row','');
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

      // Tooltips auf dynamischen Inhalten
      document.querySelectorAll('#jmdefinitionTabelle [data-bs-toggle="tooltip"], #jmdefinitionTabelle [title]').forEach(el => new bootstrap.Tooltip(el));
    }).fail(()=>{
      $('#jmdefinitionTabelle tbody').html('<tr><td colspan="11" class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden</td></tr>');
      showErrorToast('Fehler beim Laden der Daten');
    });
  }

  function loadZusatztext(){
    $.getJSON(basePath + 'jmdefinition/load_jminformation.php', function(resp){
      if (resp && resp.success) $('#zusatzText').val(resp.text);
    });
  }

  // Änderungs-Tracking
  let hasChanges = false;
  $('body').on('change input', '#jmdefinitionTabelle input, #jmdefinitionTabelle textarea, #jmdefinitionTabelle select, #zusatzText', function(){
    hasChanges = true;
  });
  $(window).on('beforeunload', function(){ if (hasChanges) return 'Du hast ungespeicherte Änderungen. Wirklich verlassen?'; });

  // Speichern
  $('#jmdefinitionForm').on('submit', function(e){
    e.preventDefault();
    const $btn = $(this).find('button[type="submit"]');
    const txt  = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

    const $prog = $('<div class="progress" style="height:3px; position:fixed; top:0; left:0; right:0; z-index:9999;"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div></div>');
    $('body').append($prog);
    let w=0; const intv = setInterval(()=>{ w+=10; $prog.find('.progress-bar').css('width', w+'%'); if (w>=90) clearInterval(intv); },100);

    const order = [];
    $('#jmdefinitionTabelle tbody tr').each(function(){
      const id = $(this).attr('id') ? $(this).attr('id').replace('row','') : '';
      if (id) order.push(id);
    });

    const formData = $(this).serialize() + '&order=' + order.join(',') + '&year=' + $('#yearSelect').val();
    const zusatzText = $('#zusatzText').val();

    $.post(basePath + 'jmdefinition/save_jmdefinition.php', formData)
     .done(function(){
        showSuccessToast('Änderungen gespeichert!');
        hasChanges=false;
        $.post(basePath + 'jmdefinition/save_jminformation.php', {
          zusatztext: zusatzText,
          csrf_token: $('input[name="csrf_token"]').val()
        }).always(()=> setTimeout(()=> loadJMDefinition($('#yearSelect').val()), 500));
     })
     .fail(()=> showErrorToast('Fehler beim Speichern'))
     .always(function(){
        $btn.prop('disabled', false).html(txt);
        clearInterval(intv);
        $prog.find('.progress-bar').css('width','100%');
        setTimeout(()=> $prog.remove(), 500);
     });
  });

  // Validierung Modal
  function validateAnlass(){
    const bez = $('#neueJMDefinitionBezeichnung').val().trim();
    const max = $('#neueJMDefinitionMaxpunkte').val();
    const erw = $('#neueJMDefinitionErweitert').is(':checked');
    const info= $('#neueJMDefinitionInfo').is(':checked');
    if (!bez){ showWarningToast('Bitte Anlassname eingeben'); $('#neueJMDefinitionBezeichnung').focus(); return false; }
    if (!max && !erw && !info){ showWarningToast('Bitte Maximalpunkte oder eine Option wählen'); return false; }
    return true;
  }

  // Hinzufügen
  $('#jmdefinitionHinzufuegen').on('click', function(){
    if (!validateAnlass()) return;
    const $b = $(this), t = $b.html();
    $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Hinzufügen...');

    const payload = {
      bezeichnung: $('#neueJMDefinitionBezeichnung').val(),
      schiesstage: $('#neueJMDefinitionSchiesstage').val(),
      adresse:     $('#neueJMDefinitionAdresse').val(),
      maxpunkte:   $('#neueJMDefinitionMaxpunkte').val(),
      zuschlag:    $('#neueJMDefinitionZuschlag').val()||0,
      streicher:   $('#neueJMDefinitionStreicher').is(':checked') ? 1 : 0,
      erweitert:   $('#neueJMDefinitionErweitert').is(':checked') ? 1 : 0,
      info:        $('#neueJMDefinitionInfo').is(':checked') ? 1 : 0,
      gruppe:      $('#neueJMDefinitionGruppe').is(':checked') ? 1 : 0,
      year:        $('#yearSelect').val(),
      csrf_token:  $('input[name="csrf_token"]').val()
    };

    $.post(basePath + 'jmdefinition/add_jmdefinition.php', payload)
      .done(function(){
        $('#newAnlassModal').modal('hide');
        $('#neueJMDefinitionBezeichnung, #neueJMDefinitionAdresse, #neueJMDefinitionSchiesstage, #neueJMDefinitionMaxpunkte, #neueJMDefinitionZuschlag').val('');
        $('#neueJMDefinitionStreicher, #neueJMDefinitionErweitert, #neueJMDefinitionInfo, #neueJMDefinitionGruppe').prop('checked', false);
        showSuccessToast('Anlass hinzugefügt!');
        loadJMDefinition($('#yearSelect').val());
      })
      .fail(()=> showErrorToast('Fehler beim Hinzufügen'))
      .always(()=> $b.prop('disabled', false).html(t));
  });

  // Löschen mit Modal
  let deleteId = null;
  $(document).on('click', '.deleteJMDefinition', function(){
    deleteId = $(this).data('id');
    if (deleteId) {
      $('#confirmDeleteModal').modal('show');
    }
  });

  $('#confirmDeleteButton').on('click', function(){
    if (!deleteId) return;
    const $btn = $(this);
    const originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');

    $.post(basePath + 'jmdefinition/delete_jmdefinition.php', {
      id: deleteId, 
      csrf_token: $('input[name="csrf_token"]').val()
    })
    .done(function(resp){
      $('#confirmDeleteModal').modal('hide');
      showSuccessToast('Eintrag gelöscht');
      loadJMDefinition($('#yearSelect').val());
    })
    .fail(function(xhr){
      let msg = 'Fehler beim Löschen';
      try {
        const resp = JSON.parse(xhr.responseText);
        if (resp.message) msg = resp.message;
      } catch(e) {}
      showErrorToast(msg);
    })
    .always(function(){
      $btn.prop('disabled', false).html(originalText);
      deleteId = null;
    });
  });

  // Hilfsfunktion für direkten Download
  function triggerDownload(url){
    const a = document.createElement('a');
    a.href = url;
    a.download = '';
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  /* PDF Export */
  $('#exportPdfButton').on('click', function(e){
    e.preventDefault();
    const $btn = $(this);
    const originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
    
    const year = $('#yearSelect').val();
    $.getJSON(basePath + 'jmdefinition/export_jmdefinition_pdf.php', { year })
      .done(function(resp){
        if (resp && resp.success && resp.pdf_link){
          triggerDownload(resp.pdf_link);
          showSuccessToast('PDF wird heruntergeladen');
        } else { 
          showErrorToast(resp.message || 'PDF konnte nicht generiert werden'); 
        }
      })
      .fail(()=> showErrorToast('PDF-Fehler'))
      .always(()=> $btn.prop('disabled', false).html(originalText));
  });

  /* PDF Entwurf Export */
  $('#exportPdfDraftButton').on('click', function(e){
    e.preventDefault();
    const $btn = $(this);
    const originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
    
    const year = $('#yearSelect').val();
    $.getJSON(basePath + 'jmdefinition/export_jmdefinition_pdf.php', { year, draft: 1 })
      .done(function(resp){
        if (resp && resp.success && resp.pdf_link){
          triggerDownload(resp.pdf_link);
          showSuccessToast('Entwurf-PDF wird heruntergeladen');
        } else { 
          showErrorToast(resp.message || 'PDF konnte nicht generiert werden'); 
        }
      })
      .fail(()=> showErrorToast('PDF-Fehler'))
      .always(()=> $btn.prop('disabled', false).html(originalText));
  });

  /* Word Export */
  $('#exportWordFragebogen').on('click', function(e){
    e.preventDefault();
    const $btn = $(this);
    const originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
    
    const year = $('#yearSelect').val();
    $.getJSON(basePath + 'jmdefinition/export_fragebogen.php', { year })
      .done(function(resp){
        if (resp && resp.success && resp.word_link){
          triggerDownload(resp.word_link);
          showSuccessToast('Fragebogen wird heruntergeladen');
        } else { 
          showErrorToast(resp.message || 'Fragebogen konnte nicht generiert werden'); 
        }
      })
      .fail(()=> showErrorToast('Word-Fehler'))
      .always(()=> $btn.prop('disabled', false).html(originalText));
  });

  /* ICS Export */
  $('#exportICSAll').on('click', function(e){
    e.preventDefault();
    const $btn = $(this);
    const originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
    
    $.getJSON(basePath + 'jmdefinition/export_all_ics.php')
      .done(function(resp){
        if (resp && resp.success && resp.ics_link){
          triggerDownload(resp.ics_link);
          showSuccessToast('ICS wird heruntergeladen');
        } else { 
          showErrorToast(resp.message || 'ICS konnte nicht generiert werden'); 
        }
      })
      .fail(()=> showErrorToast('ICS-Fehler'))
      .always(()=> $btn.prop('disabled', false).html(originalText));
  });

  /* Nach Datum sortieren */
  $('#sortByDateButton').on('click', function(e){
    e.preventDefault();
    const $btn = $(this);
    const originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Sortiere...');
    
    $.post(basePath + 'jmdefinition/sort_by_date.php', {
      year: $('#yearSelect').val(),
      csrf_token: $('input[name="csrf_token"]').val()
    })
      .done(function(resp){
        if (resp && resp.success){
          showSuccessToast('Anlässe nach Datum sortiert');
          loadJMDefinition($('#yearSelect').val());
        } else { 
          showErrorToast(resp.message || 'Sortierung fehlgeschlagen'); 
        }
      })
      .fail(()=> showErrorToast('Fehler beim Sortieren'))
      .always(()=> $btn.prop('disabled', false).html(originalText));
  });

  // Jahrwechsel
  $('#yearSelect').on('change', function(){
    const y = $(this).val();
    loadJMDefinition(y);
    loadZusatztext();
  });

  // Shortcuts
  $(document).on('keydown', function(e){
    if ((e.ctrlKey || e.metaKey) && e.key==='s'){ e.preventDefault(); $('#jmdefinitionForm').trigger('submit'); }
    if ((e.ctrlKey || e.metaKey) && e.key==='n'){ e.preventDefault(); $('#newAnlassModal').modal('show'); }
    if (e.key==='Escape'){ $('.modal.show').modal('hide'); }
  });

  // Fokus
  $('#newAnlassModal').on('shown.bs.modal', ()=> $('#neueJMDefinitionBezeichnung').trigger('focus'));

  // Start
  initializeYearDropdown();
  loadJMDefinition(currentYear);
  loadZusatztext();
});
</script>

<?php
include 'footer.inc.php';
?>
