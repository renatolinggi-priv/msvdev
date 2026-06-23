<?php
// wanderpreise_regeln.php - Wanderpreise Zuordnungsregeln (Hybrid-Pattern)
include 'dbconnect.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_specific_css = "
/* ===== Wanderpreise Regeln - Hybrid Layout ===== */

/* --- Table Wrapper --- */
.table-wrapper {
    border: 1px solid #e2e8f0;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    overflow: visible;
}

.table-title {
    position: sticky; top: 0; z-index: 8;
    margin: 0; padding: 1rem 1.25rem; font-weight: 600;
    color: var(--dark-color);
    border-bottom: 2px solid #e2e8f0;
    background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%);
    display: flex; justify-content: space-between; align-items: center;
}

.table-title .title-text {
    display: flex; align-items: center; gap: 0.5rem;
}

.table-title .title-search { width: 200px; }

.table-title .title-search input {
    font-size: 0.85rem; border-radius: 20px; border: 1px solid #cbd5e1;
    padding: 0.35rem 0.75rem 0.35rem 2rem; background: white;
}

.table-title .search-icon {
    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: 0.8rem;
}

/* --- Hybrid-Tabelle --- */
.hybrid-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }

.hybrid-table thead th {
    padding: 0.85rem 1rem; font-size: 0.75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;
    background: linear-gradient(180deg, #f8fafc, #eef2f7);
    border-bottom: 2px solid #e2e8f0;
    position: sticky; top: 0; z-index: 6;
}

.hybrid-table tbody tr.hybrid-row {
    cursor: pointer; transition: background 0.15s;
}

.hybrid-table tbody tr.hybrid-row:hover {
    background: rgba(99,102,241,0.05);
}

.hybrid-table tbody tr.hybrid-row.selected {
    background: rgba(59,130,246,0.08);
    box-shadow: inset 4px 0 0 #3b82f6;
}

.hybrid-table tbody td {
    padding: 0.85rem 1rem; vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

/* --- Code-Badge --- */
.code-badge {
    font-family: 'Courier New', monospace;
    background: #eff6ff; color: #1e40af;
    padding: 3px 10px; border-radius: 6px;
    font-size: 0.82rem; font-weight: 500;
    letter-spacing: 0.3px; white-space: nowrap;
}

/* --- Spalten --- */
.regel-name { font-weight: 500; color: #1e293b; }

.regel-desc {
    color: #64748b; font-size: 0.85rem;
    max-width: 250px; overflow: hidden;
    text-overflow: ellipsis; white-space: nowrap;
}

/* --- Flag-Dot --- */
.flag-dot {
    width: 28px; height: 28px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.7rem; cursor: default; transition: transform 0.15s;
}

.flag-dot:hover { transform: scale(1.15); }
.flag-dot.on  { background: #22c55e; color: #fff; }
.flag-dot.off { background: #f1f5f9; color: #cbd5e1; }

/* --- Aktions-Buttons in Tabelle --- */
.row-actions { display: flex; gap: 4px; justify-content: flex-end; }

.row-actions .btn {
    width: 32px; height: 32px; padding: 0;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 6px; font-size: 0.8rem; transition: all 0.15s;
}

.row-actions .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}

/* --- Count-Badge --- */
.count-badge {
    font-size: 0.8rem; color: #64748b;
    padding: 0.75rem 1.25rem; border-top: 1px solid #f1f5f9;
}

/* --- Action-Card --- */
.action-card { border-color: #e2e8f0; }
.action-card-header { cursor: pointer; user-select: none; background-color: #f8fafc; }
.action-card-header:hover { background-color: #f1f5f9; }
.action-chevron { transition: transform .2s ease; }
.action-card-header[aria-expanded=\"true\"] .action-chevron { transform: rotate(180deg); }

/* === SLIDE PANEL === */
.hybrid-edit-panel {
    position: fixed; top: 0; right: -520px; width: 500px; height: 100vh;
    background: #fff; box-shadow: -8px 0 30px rgba(0,0,0,0.12);
    z-index: 1060; transition: right 0.3s cubic-bezier(0.4,0,0.2,1);
    display: flex; flex-direction: column;
}

.hybrid-edit-panel.open { right: 0; }

.panel-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.3);
    z-index: 1055; opacity: 0; visibility: hidden; transition: all 0.3s;
}

.panel-overlay.show { opacity: 1; visibility: visible; }

.panel-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0;
    background: #f8fafc; flex-shrink: 0;
}

.panel-header h5 { margin: 0; font-size: 1rem; font-weight: 600; color: #1e293b; }

.panel-body {
    padding: 1.25rem; overflow-y: auto; flex: 1;
    -webkit-overflow-scrolling: touch;
}

.panel-footer {
    padding: 1rem 1.25rem; border-top: 1px solid #e2e8f0;
    background: #f8fafc; display: flex; gap: 0.5rem; flex-shrink: 0;
}

.panel-label {
    display: block; font-size: 0.8rem; font-weight: 600;
    color: #64748b; margin-bottom: 0.35rem;
    text-transform: uppercase; letter-spacing: 0.3px;
}

/* SQL Editor */
.sql-editor {
    font-family: 'Courier New', monospace; font-size: 0.85rem;
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 6px; tab-size: 4; line-height: 1.5;
}

.sql-editor:focus {
    background: #fff; border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

/* Vorlagen */
.vorlage-btn {
    font-size: 0.8rem; padding: 0.3rem 0.6rem; border-radius: 6px;
    border: 1px solid #e2e8f0; background: #f8fafc; color: #475569;
    cursor: pointer; transition: all 0.15s;
}

.vorlage-btn:hover {
    background: #eff6ff; border-color: #3b82f6; color: #1e40af;
}

/* Skeleton */
.skeleton {
    height: 20px; border-radius: 4px;
    background: linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);
    background-size: 200% 100%; animation: loading 1.5s infinite;
}

@keyframes loading {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* === MOBILE === */
@media (max-width: 767.98px) {
    .desktop-table-container { display: none !important; }
    .mobile-cards-container  { display: block !important; }
    .hybrid-edit-panel { width: 100vw; right: -100vw; }
    .table-title .title-search { display: none; }

    .mobile-search {
        background: white; padding: 0.75rem 1rem;
        border-bottom: 2px solid #e9ecef;
        margin: 0 -1rem 0.75rem -1rem;
    }

    .mobile-search .search-icon {
        position: absolute; left: 12px; top: 50%;
        transform: translateY(-50%); color: #94a3b8;
        pointer-events: none;
    }

    .mobile-search input {
        padding-left: 40px; border-radius: 20px;
        border: 2px solid #e2e8f0; font-size: 16px; min-height: 48px;
    }

    .mobile-cards-scroll {
        display: flex; flex-direction: column; gap: 0.75rem;
    }

    .mobile-regel-card {
        background: white; border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden;
        border-left: 4px solid #e2e8f0; transition: transform 0.15s;
    }

    .mobile-regel-card.aktiv   { border-left-color: #22c55e; }
    .mobile-regel-card.inaktiv { border-left-color: #94a3b8; }
    .mobile-regel-card:active  { transform: scale(0.98); }

    .mobile-regel-card .card-top {
        padding: 1rem; display: flex;
        justify-content: space-between; align-items: flex-start;
    }

    .mobile-regel-card .card-code {
        font-family: 'Courier New', monospace;
        background: #eff6ff; color: #1e40af;
        padding: 3px 10px; border-radius: 6px;
        font-size: 13px; font-weight: 500;
    }

    .mobile-regel-card .card-name {
        font-weight: 600; font-size: 16px; color: #1e293b; margin-top: 0.5rem;
    }

    .mobile-regel-card .card-desc {
        font-size: 14px; color: #64748b; margin-top: 0.25rem;
    }

    .mobile-regel-card .card-actions {
        display: flex; gap: 6px;
        position: absolute; top: 0.75rem; right: 0.75rem;
    }

    .mobile-regel-card .card-top {
        position: relative; padding-right: 7rem;
    }

    .mobile-regel-card .card-actions button {
        width: 36px; height: 36px; padding: 0; border-radius: 50%;
        border: 1.5px solid; background: white;
        font-size: 14px;
        display: inline-flex; align-items: center; justify-content: center;
        cursor: pointer; transition: all 0.15s;
    }

    .mobile-regel-card .card-actions .btn-test { border-color: #3b82f6; color: #3b82f6; }
    .mobile-regel-card .card-actions .btn-edit { border-color: #f59e0b; color: #92400e; }
    .mobile-regel-card .card-actions .btn-del  { border-color: #ef4444; color: #ef4444; }
    .mobile-regel-card .card-actions button:active { transform: scale(0.9); background: #f8fafc; }

    /* Touch-Targets */
    .form-control, .form-control-sm, input, select {
        min-height: 48px !important; font-size: 16px !important;
    }

    .btn { min-height: 48px !important; font-size: 16px !important; }
}

@media (min-width: 768px) {
    .mobile-cards-container  { display: none !important; }
    .desktop-table-container { display: block !important; }
}
";

// Header einbinden
include 'header.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-7 col-lg-9 col-md-11 col-12 ps-0">
      <div class="main-content-wrapper">

        <!-- Header (Desktop) -->
        <div class="row mb-4 d-none d-md-flex">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-gear me-2"></i> Wanderpreise Zuordnungsregeln
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">SQL-Regeln f&uuml;r automatische Gewinnerzuordnung</p>
          </div>
        </div>

        <div class="content-background">

          <!-- Neue Regel Button -->
          <div class="mb-4 d-none d-md-block">
            <button type="button" class="btn btn-success" id="btnNeueRegel">
              <i class="bi bi-plus-lg me-2"></i>Neue Regel
            </button>
          </div>

          <!-- === DESKTOP: Tabelle === -->
          <div class="desktop-table-container">
            <div class="table-wrapper">

              <!-- Tabellen-Titel mit integrierter Suche -->
              <h5 class="table-title">
                <span class="title-text">
                  <i class="bi bi-list-ul"></i>
                  Zuordnungsregeln
                  <span class="badge bg-light text-secondary" id="regelnCountBadge"
                        style="font-size:0.75rem; font-weight:500;">0</span>
                </span>
                <span class="title-search position-relative">
                  <i class="bi bi-search search-icon"></i>
                  <input type="text" class="form-control form-control-sm" id="desktopSearch"
                         placeholder="Suchen...">
                </span>
              </h5>

              <!-- Tabelle -->
              <div class="table-responsive">
                <table class="hybrid-table" id="regelnTable">
                  <thead>
                    <tr>
                      <th style="width: 140px;">Code</th>
                      <th style="width: 200px;">Name</th>
                      <th class="d-none d-lg-table-cell">Beschreibung</th>
                      <th style="width: 60px; text-align: center;">Status</th>
                      <th style="width: 120px; text-align: right;">Aktionen</th>
                    </tr>
                  </thead>
                  <tbody id="regelnTableBody">
                    <tr><td colspan="5" class="text-center py-4">
                      <div class="spinner-border spinner-border-sm me-2"></div>
                      Lade Regeln...
                    </td></tr>
                  </tbody>
                </table>
              </div>

              <!-- Z&auml;hler -->
              <div class="count-badge" id="regelnCount">
                <i class="bi bi-info-circle me-1"></i> 0 Regeln definiert
              </div>

            </div>
          </div>

          <!-- === MOBILE: Cards === -->
          <div class="mobile-cards-container">
            <div class="mobile-search">
              <div class="position-relative">
                <i class="bi bi-search search-icon"></i>
                <input type="text" class="form-control" id="mobileSearch"
                       placeholder="Suchen...">
              </div>
            </div>
            <div class="mobile-cards-scroll" id="mobileCardsScroll"></div>
          </div>

          <!-- Beispiel-Regeln (Collapsible, dezent unten) -->
          <div class="mt-4 d-none d-md-block">
            <button class="btn btn-link text-muted p-0 small" type="button"
                    data-bs-toggle="collapse" data-bs-target="#beispielRegeln">
              <i class="bi bi-lightbulb me-1"></i> Beispiel-Regeln anzeigen
            </button>
            <div class="collapse mt-2" id="beispielRegeln">
              <div class="accordion" id="beispielAccordion">
                <!-- Jahresmeister -->
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#beispiel1">
                      Jahresmeister (H&ouml;chste Gesamtpunktzahl)
                    </button>
                  </h2>
                  <div id="beispiel1" class="accordion-collapse collapse" data-bs-parent="#beispielAccordion">
                    <div class="accordion-body">
                      <pre class="sql-editor mb-0">SELECT
    m.ID AS gewinner_id,
    'Test-Resultat' AS resultat,
    '1. Rang' AS rang
FROM mitglieder m
WHERE m.Status = 1
ORDER BY m.ID DESC
LIMIT 1</pre>
                    </div>
                  </div>
                </div>
                <!-- Bester Endstich -->
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#beispiel2">
                      Bester Endstich
                    </button>
                  </h2>
                  <div id="beispiel2" class="accordion-collapse collapse" data-bs-parent="#beispielAccordion">
                    <div class="accordion-body">
                      <pre class="sql-editor mb-0">SELECT
    e.MitgliedID AS gewinner_id,
    CONCAT(e.Total, ' Punkte') AS resultat,
    '1. Rang Endstich' AS rang
FROM endresultate e
WHERE e.Jahr = {jahr}
    AND e.Total = (
        SELECT MAX(Total)
        FROM endresultate
        WHERE Jahr = {jahr}
    )
LIMIT 1</pre>
                    </div>
                  </div>
                </div>
                <!-- Bester Gruppenstich -->
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#beispiel3">
                      Bester Gruppenstich (spezifische Kategorie)
                    </button>
                  </h2>
                  <div id="beispiel3" class="accordion-collapse collapse" data-bs-parent="#beispielAccordion">
                    <div class="accordion-body">
                      <pre class="sql-editor mb-0">SELECT
    g.MitgliedID AS gewinner_id,
    CONCAT(g.Resultat, ' Punkte') AS resultat,
    CONCAT('1. Rang ', g.Kategorie) AS rang
FROM gruppenstiche g
INNER JOIN mitglieder m ON g.MitgliedID = m.ID
WHERE g.Jahr = {jahr}
    AND g.Kategorie = 'Gewehr 300m'
ORDER BY g.Resultat DESC
LIMIT 1</pre>
                    </div>
                  </div>
                </div>
                <!-- Fleissigster -->
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#beispiel4">
                      Fleissigster Sch&uuml;tze (meiste Teilnahmen)
                    </button>
                  </h2>
                  <div id="beispiel4" class="accordion-collapse collapse" data-bs-parent="#beispielAccordion">
                    <div class="accordion-body">
                      <pre class="sql-editor mb-0">SELECT
    MitgliedID AS gewinner_id,
    CONCAT(COUNT(*), ' Teilnahmen') AS resultat,
    'Fleisspreis' AS rang
FROM (
    SELECT MitgliedID FROM gruppenstiche WHERE Jahr = {jahr}
    UNION ALL
    SELECT MitgliedID FROM endresultate WHERE Jahr = {jahr}
    UNION ALL
    SELECT MitgliedID FROM feldschiessen WHERE Jahr = {jahr}
) AS teilnahmen
GROUP BY MitgliedID
ORDER BY COUNT(*) DESC
LIMIT 1</pre>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Overlay -->
<div class="panel-overlay" id="panelOverlay"></div>

<!-- Slide Panel -->
<div class="hybrid-edit-panel" id="regelPanel">
  <div class="panel-header">
    <h5 id="panelTitle">
      <i class="bi bi-plus-circle me-2"></i> Neue Regel erstellen
    </h5>
    <button class="btn-close" id="panelClose" aria-label="Schliessen"></button>
  </div>

  <div class="panel-body">
    <form id="regelForm">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="id" id="regelId" value="">

      <div class="mb-3">
        <label class="panel-label">Regel-Code <span class="text-danger">*</span></label>
        <input type="text" name="regel_code" id="regelCode" class="form-control"
               required placeholder="z.B. jahresmeister_300m">
        <div class="form-text" style="font-size:0.75rem;">Eindeutiger Identifier, keine Leerzeichen</div>
      </div>

      <div class="mb-3">
        <label class="panel-label">Regel-Name <span class="text-danger">*</span></label>
        <input type="text" name="regel_name" id="regelName" class="form-control"
               required placeholder="z.B. Jahresmeister 300m">
      </div>

      <div class="mb-3">
        <label class="panel-label">Beschreibung</label>
        <textarea name="regel_beschreibung" id="regelBeschreibung" class="form-control"
                  rows="2" placeholder="Was macht diese Regel..."></textarea>
      </div>

      <div class="mb-3">
        <label class="panel-label">SQL-Query <span class="text-danger">*</span></label>
        <div class="form-text mb-2" style="font-size:0.75rem;">
          Muss <code>gewinner_id</code> zur&uuml;ckgeben. Optional: <code>resultat</code>, <code>rang</code><br>
          Platzhalter:
          <span class="code-badge" style="font-size:0.75rem;">{jahr}</span>
          <span class="code-badge" style="font-size:0.75rem;">{wanderpreis_id}</span>
        </div>
        <textarea name="sql_query" id="regelSql" class="form-control sql-editor"
                  rows="10" required
                  placeholder="SELECT m.ID AS gewinner_id, ... FROM ..."></textarea>
      </div>

      <!-- SQL Test -->
      <div class="mb-3">
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnTestSql">
          <i class="bi bi-play-fill me-1"></i> SQL testen
        </button>
        <div id="testResult" class="mt-2" style="display:none;"></div>
      </div>

      <div class="mb-3">
        <label class="panel-label">Status</label>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="aktiv" id="regelAktiv" checked>
          <label class="form-check-label" for="regelAktiv">Regel ist aktiv</label>
        </div>
      </div>
    </form>

    <!-- Vorlagen-Buttons im Panel -->
    <hr class="my-3" style="border-color: #e2e8f0;">
    <div>
      <label class="panel-label">
        <i class="bi bi-clipboard me-1"></i> Vorlage einf&uuml;gen
      </label>
      <div class="d-flex flex-wrap gap-1 mt-1">
        <button class="vorlage-btn" data-vorlage="jahresmeister">Jahresmeister</button>
        <button class="vorlage-btn" data-vorlage="endstich">Bester Endstich</button>
        <button class="vorlage-btn" data-vorlage="gruppenstich">Gruppenstich</button>
        <button class="vorlage-btn" data-vorlage="fleissigster">Fleissigster</button>
      </div>
    </div>
  </div>

  <div class="panel-footer">
    <button type="button" class="btn btn-success" id="btnSaveRegel">
      <i class="bi bi-check-lg me-1"></i> Speichern
    </button>
    <button type="button" class="btn btn-outline-danger d-none" id="btnDeleteRegel">
      <i class="bi bi-trash me-1"></i> L&ouml;schen
    </button>
  </div>
</div>

<script>
$(document).ready(function() {

    // ============================================
    // DATEN-STORE
    // ============================================
    let regelnData = [];

    // ============================================
    // LADEN
    // ============================================
    function loadRegeln() {
        $.getJSON('wanderpreise/get_regeln_json.php', function(data) {
            regelnData = data;
            renderDesktopTable();
            renderMobileCards();
            $('#regelnCount').html(
                '<i class="bi bi-info-circle me-1"></i> ' + data.length + ' Regeln definiert'
            );
            $('#regelnCountBadge').text(data.length);
        }).fail(function() {
            $('#regelnTableBody').html(
                '<tr><td colspan="5" class="text-center text-danger py-4">' +
                '<i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden</td></tr>'
            );
        });
    }

    // ============================================
    // DESKTOP TABELLE RENDERN
    // ============================================
    function renderDesktopTable() {
        const $tbody = $('#regelnTableBody');
        $tbody.empty();

        if (regelnData.length === 0) {
            $tbody.html(
                '<tr><td colspan="5" class="text-center py-4 text-muted">' +
                '<i class="bi bi-inbox me-2"></i>Noch keine Regeln definiert</td></tr>'
            );
            return;
        }

        regelnData.forEach(function(regel) {
            const statusDot = regel.aktiv == 1
                ? '<span class="flag-dot on" data-tooltip="Aktiv"><i class="bi bi-check2"></i></span>'
                : '<span class="flag-dot off" data-tooltip="Inaktiv"><i class="bi bi-pause"></i></span>';

            const $tr = $('<tr class="hybrid-row" data-id="' + regel.id + '">' +
                '<td><span class="code-badge">' + escHtml(regel.regel_code) + '</span></td>' +
                '<td><span class="regel-name">' + escHtml(regel.regel_name) + '</span></td>' +
                '<td class="d-none d-lg-table-cell">' +
                '  <span class="regel-desc">' + escHtml(regel.regel_beschreibung || '\u2013') + '</span>' +
                '</td>' +
                '<td class="text-center">' + statusDot + '</td>' +
                '<td>' +
                '  <div class="row-actions">' +
                '    <button class="btn btn-outline-primary btn-test-sql" data-tooltip="SQL testen" onclick="event.stopPropagation()">' +
                '      <i class="bi bi-play-fill"></i></button>' +
                '    <button class="btn btn-outline-secondary btn-edit-regel" data-tooltip="Bearbeiten" onclick="event.stopPropagation()">' +
                '      <i class="bi bi-pencil"></i></button>' +
                '    <button class="btn btn-outline-danger btn-delete-regel" data-tooltip="L\u00f6schen" onclick="event.stopPropagation()">' +
                '      <i class="bi bi-trash"></i></button>' +
                '  </div>' +
                '</td>' +
                '</tr>');

            // Klick auf Zeile -> Panel oeffnen
            $tr.on('click', function() { openPanel('edit', regel); });

            // Button-Events
            $tr.find('.btn-test-sql').on('click', function() { testSqlInline(regel); });
            $tr.find('.btn-edit-regel').on('click', function() { openPanel('edit', regel); });
            $tr.find('.btn-delete-regel').on('click', function() { deleteRegel(regel.id); });

            $tbody.append($tr);
        });
    }

    // ============================================
    // MOBILE CARDS RENDERN
    // ============================================
    function renderMobileCards() {
        const $container = $('#mobileCardsScroll');
        $container.empty();

        if (regelnData.length === 0) {
            $container.html(
                '<div class="text-center text-muted py-4">' +
                '<i class="bi bi-inbox" style="font-size:2rem;"></i>' +
                '<div class="mt-2">Noch keine Regeln definiert</div></div>'
            );
            return;
        }

        regelnData.forEach(function(regel) {
            const statusClass = regel.aktiv == 1 ? 'aktiv' : 'inaktiv';
            const statusEmoji = regel.aktiv == 1 ? '\uD83D\uDFE2' : '\u26AA';

            const descHtml = regel.regel_beschreibung
                ? '<div class="card-desc">' + escHtml(regel.regel_beschreibung) + '</div>'
                : '';

            const $card = $(
                '<div class="mobile-regel-card ' + statusClass + '" data-id="' + regel.id + '">' +
                '  <div class="card-top">' +
                '    <div>' +
                '      <span class="card-code">' + escHtml(regel.regel_code) + '</span>' +
                '      <div class="card-name">' + escHtml(regel.regel_name) + '</div>' +
                '      ' + descHtml +
                '    </div>' +
                '    <div class="card-actions">' +
                '      <button class="btn-test"><i class="bi bi-play-fill"></i></button>' +
                '      <button class="btn-edit"><i class="bi bi-pencil"></i></button>' +
                '      <button class="btn-del"><i class="bi bi-trash"></i></button>' +
                '    </div>' +
                '  </div>' +
                '</div>'
            );

            $card.find('.card-top').on('click', function() { openPanel('edit', regel); });
            $card.find('.btn-test').on('click', function(e) { e.stopPropagation(); testSqlInline(regel); });
            $card.find('.btn-edit').on('click', function(e) { e.stopPropagation(); openPanel('edit', regel); });
            $card.find('.btn-del').on('click', function(e) { e.stopPropagation(); deleteRegel(regel.id); });

            $container.append($card);
        });
    }

    // ============================================
    // SLIDE PANEL
    // ============================================
    window.openPanel = function(mode, regel) {
        const $panel = $('#regelPanel');
        const $overlay = $('#panelOverlay');

        if (mode === 'new') {
            $('#panelTitle').html('<i class="bi bi-plus-circle me-2"></i> Neue Regel erstellen');
            $('#regelForm')[0].reset();
            $('#regelId').val('');
            $('#regelAktiv').prop('checked', true);
            $('#btnDeleteRegel').addClass('d-none');
            $('#btnSaveRegel').html('<i class="bi bi-check-lg me-1"></i> Erstellen');
        } else {
            $('#panelTitle').html('<i class="bi bi-pencil me-2"></i> Regel bearbeiten');
            $('#regelId').val(regel.id);
            $('#regelCode').val(regel.regel_code);
            $('#regelName').val(regel.regel_name);
            $('#regelBeschreibung').val(regel.regel_beschreibung || '');
            $('#regelSql').val(regel.sql_query || '');
            $('#regelAktiv').prop('checked', regel.aktiv == 1);
            $('#btnDeleteRegel').removeClass('d-none');
            $('#btnSaveRegel').html('<i class="bi bi-check-lg me-1"></i> Speichern');

            // Zeile in Tabelle markieren
            $('.hybrid-row').removeClass('selected');
            $('.hybrid-row[data-id="' + regel.id + '"]').addClass('selected');
        }

        // Test-Ergebnis zuruecksetzen
        $('#testResult').hide().empty();

        $overlay.addClass('show');
        $panel.addClass('open');
        document.body.style.overflow = 'hidden';
    };

    function closePanel() {
        $('#regelPanel').removeClass('open');
        $('#panelOverlay').removeClass('show');
        document.body.style.overflow = '';
        $('.hybrid-row').removeClass('selected');
    }

    // Panel schliessen Events
    $('#panelClose, #panelOverlay').on('click', closePanel);
    $(document).on('keydown', function(e) { if (e.key === 'Escape') closePanel(); });

    // Neue Regel Buttons
    $('#btnNeueRegel').on('click', function() { openPanel('new'); });

    // ============================================
    // SPEICHERN
    // ============================================
    $('#btnSaveRegel').on('click', function() {
        const $form = $('#regelForm');
        if (!$form[0].checkValidity()) { $form[0].reportValidity(); return; }

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span> Speichere...');

        $.post('wanderpreise/save_regel.php', $form.serialize(), function(response) {
            if (response.success) {
                msvToast(response.message, 'success');
                closePanel();
                loadRegeln();
            } else {
                msvToast('Fehler: ' + (response.message || 'Unbekannt'), 'error');
            }
        }, 'json').fail(function() {
            msvToast('Fehler beim Speichern', 'error');
        }).always(function() {
            $btn.prop('disabled', false).html(originalText);
        });
    });

    // ============================================
    // LOESCHEN
    // ============================================
    window.deleteRegel = async function(id) {
        const result = await msvConfirmDelete('diese Regel');
        if (!result.isConfirmed) return;

        $.post('wanderpreise/delete_regel.php', {
            id: id,
            csrf_token: $('input[name="csrf_token"]').val()
        }, function(response) {
            if (response.success) {
                msvToast('Regel gel\u00f6scht', 'success');
                closePanel();
                loadRegeln();
            } else {
                msvToast('Fehler: ' + (response.message || 'Unbekannt'), 'error');
            }
        }, 'json');
    };

    // Delete-Button im Panel
    $('#btnDeleteRegel').on('click', function() {
        const id = $('#regelId').val();
        if (id) deleteRegel(id);
    });

    // ============================================
    // SQL TESTEN (im Panel)
    // ============================================
    $('#btnTestSql').on('click', function() {
        const sql = $('#regelSql').val();
        if (!sql.trim()) { msvToast('Bitte SQL eingeben', 'warning'); return; }

        const $result = $('#testResult');
        $result.show().html(
            '<div class="alert alert-light border mb-0 py-2 px-3" style="font-size:0.85rem;">' +
            '<span class="spinner-border spinner-border-sm me-2"></span>Teste SQL...</div>'
        );

        $.post('wanderpreise/test_regel_sql.php', {
            sql: sql,
            jahr: new Date().getFullYear(),
            csrf_token: $('input[name="csrf_token"]').val()
        }, function(response) {
            if (response.success) {
                $result.html('<div class="alert alert-success mb-0 py-2 px-3" style="font-size:0.85rem;">' +
                    '<i class="bi bi-check-circle me-2"></i>' + response.message + '</div>');
            } else {
                $result.html('<div class="alert alert-danger mb-0 py-2 px-3" style="font-size:0.85rem;">' +
                    '<i class="bi bi-x-circle me-2"></i>' + response.message + '</div>');
            }
        }, 'json').fail(function() {
            $result.html('<div class="alert alert-danger mb-0 py-2 px-3" style="font-size:0.85rem;">Fehler beim Testen</div>');
        });
    });

    // Test inline (aus Tabelle/Card -> Panel oeffnen + testen)
    window.testSqlInline = function(regel) {
        openPanel('edit', regel);
        setTimeout(function() { $('#btnTestSql').click(); }, 350);
    };

    // ============================================
    // VORLAGEN
    // ============================================
    const vorlagen = {
        jahresmeister: "SELECT\n    m.ID AS gewinner_id,\n    'Test-Resultat' AS resultat,\n    '1. Rang' AS rang\nFROM mitglieder m\nWHERE m.Status = 1\nORDER BY m.ID DESC\nLIMIT 1",
        endstich: "SELECT\n    e.MitgliedID AS gewinner_id,\n    CONCAT(e.Total, ' Punkte') AS resultat,\n    '1. Rang Endstich' AS rang\nFROM endresultate e\nWHERE e.Jahr = {jahr}\n    AND e.Total = (SELECT MAX(Total) FROM endresultate WHERE Jahr = {jahr})\nLIMIT 1",
        gruppenstich: "SELECT\n    g.MitgliedID AS gewinner_id,\n    CONCAT(g.Resultat, ' Punkte') AS resultat,\n    CONCAT('1. Rang ', g.Kategorie) AS rang\nFROM gruppenstiche g\nINNER JOIN mitglieder m ON g.MitgliedID = m.ID\nWHERE g.Jahr = {jahr}\n    AND g.Kategorie = 'Gewehr 300m'\nORDER BY g.Resultat DESC\nLIMIT 1",
        fleissigster: "SELECT\n    MitgliedID AS gewinner_id,\n    CONCAT(COUNT(*), ' Teilnahmen') AS resultat,\n    'Fleisspreis' AS rang\nFROM (\n    SELECT MitgliedID FROM gruppenstiche WHERE Jahr = {jahr}\n    UNION ALL\n    SELECT MitgliedID FROM endresultate WHERE Jahr = {jahr}\n) AS teilnahmen\nGROUP BY MitgliedID\nORDER BY COUNT(*) DESC\nLIMIT 1"
    };

    $(document).on('click', '.vorlage-btn', function() {
        const key = $(this).data('vorlage');
        if (vorlagen[key]) {
            $('#regelSql').val(vorlagen[key]);
            msvToast('Vorlage eingef\u00fcgt', 'info');
        }
    });

    // ============================================
    // SUCHE
    // ============================================
    $('#desktopSearch').on('input', function() {
        const term = $(this).val().toLowerCase();
        $('.hybrid-row').each(function() {
            $(this).toggle($(this).text().toLowerCase().includes(term));
        });
    });

    $('#mobileSearch').on('input', function() {
        const term = $(this).val().toLowerCase();
        $('.mobile-regel-card').each(function() {
            $(this).toggle($(this).text().toLowerCase().includes(term));
        });
    });

    // ============================================
    // HELPER
    // ============================================
    function escHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ============================================
    // INIT
    // ============================================
    loadRegeln();
});
</script>

<?php include 'footer.inc.php'; ?>
