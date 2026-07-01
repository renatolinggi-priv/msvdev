<?php
// wanderpreise_regeln.php - Wanderpreise Zuordnungsregeln (Hybrid-Pattern)
include 'dbconnect.inc.php';
require_once __DIR__ . '/wanderpreise/regel_builder.inc.php'; // Registry + Schema-Referenz fuer den Builder

$wp_wettbewerbe = wp_wettbewerb_registry();
$wp_schema_ref  = wp_regel_schema_reference();

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
.hybrid-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

.hybrid-table thead th {
    padding: 0.75rem; font-size: 0.75rem; font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.5px; color: var(--secondary-color);
    background-color: #f8f9fa;
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
    padding: 0.5rem 0.75rem; vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

/* Code-Badge (.code-badge) jetzt zentral in css/msv-styles.css. */

/* --- Spalten --- */
.regel-name { font-weight: 500; color: #1e293b; }

.regel-desc {
    display: block;
    color: #64748b; font-size: 0.85rem;
    white-space: normal;            /* mehrzeilig umbrechen statt abschneiden */
    overflow-wrap: anywhere; word-break: break-word;
    line-height: 1.35;
}

/* --- Flag-Dot --- */
/* Base/off/hover (.flag-dot/.flag-dot:hover/.flag-dot.off) zentral in css/msv-styles.css.
   Nur grüne .on-Variante als Seiten-Override (zentral ist blau). */
.flag-dot.on  { background: #22c55e; color: #fff; }

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

/* Aktions-Card (.action-card/.action-card-header/.action-chevron) jetzt zentral in css/msv-styles.css. */

/* === SLIDE PANEL ===
   Container/.panel-overlay/.panel-header/.panel-body jetzt zentral in css/msv-styles.css.
   Breite = Default 500px. .panel-footer + .panel-header h5 + .panel-label (mit uppercase)
   bleiben seitenspezifisch (Footer bzw. abweichende Label-Optik). */
.panel-header h5 { margin: 0; font-size: 1rem; font-weight: 600; color: #1e293b; }

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

/* Skeleton (.skeleton/@keyframes) jetzt zentral in css/msv-styles.css. */

/* === MOBILE === */
@media (max-width: 767.98px) {
    .desktop-table-container { display: none !important; }
    .mobile-cards-container  { display: block !important; }
    /* Slide-Panel Mobile-Vollbreite jetzt zentral in css/msv-styles.css */
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

    /* Touch-Targets (form-control/select/.btn min-height 48px) jetzt zentral in css/msv-styles.css */
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
    <div class="col-12 ps-0">
      <div class="main-content-wrapper">

        <?php
        $page_title = 'Wanderpreise Zuordnungsregeln';
        include 'partials/page_header.inc.php';
        ?>

        <div class="content-background">

          <!-- Neue Regel Button -->
          <div class="mb-4 d-none d-md-block">
            <button type="button" class="btn btn-outline-success btn-sm" id="btnNeueRegel">
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

          <!-- Hinweis: Beispiele/Vorlagen sind jetzt direkt im Bearbeiten-Panel
               (Regel-Typ "Geführt" + "Vorlage aus bestehender Regel"). Die alte
               Beispiel-Akkordeon-Liste verwies auf nicht existierende Tabellen
               und wurde entfernt. -->

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

      <!-- Regel-Typ: gefuehrt vs. Experte -->
      <div class="mb-3">
        <label class="panel-label">Regel-Typ</label>
        <select name="regel_typ" id="regelTyp" class="form-select">
          <option value="einzelwettbewerb">Gef&uuml;hrt: Bester in einem Wettbewerb</option>
          <option value="baukasten">Baukasten: Bedingungen &amp; Sortierung selbst zusammenstellen</option>
          <option value="custom">Experte: eigenes SQL</option>
        </select>
        <div class="form-text" style="font-size:0.75rem;">
          &bdquo;Gef&uuml;hrt&ldquo; und &bdquo;Baukasten&ldquo; erzeugen das SQL automatisch. &bdquo;Experte&ldquo; f&uuml;r Sonderf&auml;lle (z.&nbsp;B. Jahresmeister).
        </div>
      </div>

      <!-- Builder-Felder (nur bei "Geführt") -->
      <div id="builderFields">
        <div class="mb-3">
          <label class="panel-label">Wettbewerb <span class="text-danger">*</span></label>
          <select name="wettbewerb" id="builderWettbewerb" class="form-select">
            <?php foreach ($wp_wettbewerbe as $wpKey => $wpDef): ?>
            <option value="<?= htmlspecialchars($wpKey) ?>" data-cat="<?= !empty($wpDef['category']) ? '1' : '0' ?>"><?= htmlspecialchars($wpDef['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3" id="builderKategorieRow">
          <label class="panel-label">Kategorie</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="kategorie" id="katAlle" value="" checked>
              <label class="form-check-label" for="katAlle">Alle</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="kategorie" id="katA" value="A">
              <label class="form-check-label" for="katA">Kat. A</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="kategorie" id="katB" value="B">
              <label class="form-check-label" for="katB">Kat. B</label>
            </div>
          </div>
        </div>

        <!-- Nur einfacher Modus -->
        <div class="mb-3" id="einzelOnlyFields">
          <label class="panel-label">Sieger ist</label>
          <select name="richtung" id="builderRichtung" class="form-select">
            <option value="DESC">H&ouml;chstes Resultat (Standard)</option>
            <option value="ASC">Niedrigstes Resultat</option>
          </select>
        </div>

        <!-- Baukasten: frei klickbare Bedingungen & Sortierung -->
        <div id="baukastenFields">
          <div class="mb-3">
            <label class="panel-label">Bedingungen
              <span class="text-muted" style="font-weight:400; text-transform:none;">(optional, mit UND verkn&uuml;pft)</span>
            </label>
            <div id="builderFilterRows"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="btnAddFilter">
              <i class="bi bi-plus-lg me-1"></i> Bedingung
            </button>
            <div class="form-text" style="font-size:0.72rem;">
              Jahr (= {jahr}) und &bdquo;kein Leertreffer&ldquo; werden automatisch erg&auml;nzt. Werte: Zahl oder <code>{jahr}</code>.
            </div>
          </div>
          <div class="mb-3">
            <label class="panel-label">Sortierung
              <span class="text-muted" style="font-weight:400; text-transform:none;">(Sieger = erste Zeile)</span>
            </label>
            <div id="builderSortRows"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="btnAddSort">
              <i class="bi bi-plus-lg me-1"></i> Sortierung
            </button>
          </div>
          <input type="hidden" name="filter_json" id="builderFilterJson" value="[]">
          <input type="hidden" name="sort_json" id="builderSortJson" value="[]">
        </div>
      </div>

      <!-- SQL: Experte = editierbar, Geführt = read-only Vorschau -->
      <div class="mb-3" id="sqlBlock">
        <label class="panel-label" id="sqlLabel">SQL-Query <span class="text-danger">*</span></label>
        <div class="form-text mb-2" id="sqlCustomHelp" style="font-size:0.75rem;">
          Muss <code>gewinner_id</code> zur&uuml;ckgeben. Optional: <code>resultat</code>, <code>rang</code><br>
          Platzhalter:
          <span class="code-badge" style="font-size:0.75rem;">{jahr}</span>
          <span class="code-badge" style="font-size:0.75rem;">{kategorie}</span>
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

    <!-- Experten-Werkzeuge: Vorlage aus bestehender Regel + Tabellen-Referenz -->
    <div id="expertenTools">
      <hr class="my-3" style="border-color: #e2e8f0;">
      <label class="panel-label"><i class="bi bi-clipboard me-1"></i> Vorlage aus bestehender Regel</label>
      <select id="vorlagePicker" class="form-select form-select-sm">
        <option value="">&ndash; Regel w&auml;hlen, um ihr SQL zu &uuml;bernehmen &ndash;</option>
      </select>
      <div class="form-text" style="font-size:0.72rem;">&Uuml;bernimmt das SQL einer bestehenden Regel als Startpunkt.</div>

      <div class="mt-3">
        <button class="btn btn-link text-muted p-0 small" type="button"
                data-bs-toggle="collapse" data-bs-target="#schemaRef">
          <i class="bi bi-table me-1"></i> Verf&uuml;gbare Tabellen &amp; Spalten
        </button>
        <div class="collapse mt-2" id="schemaRef">
          <div class="form-text mb-1" style="font-size:0.72rem;">
            Ausgabe: <code>gewinner_id</code> (Pflicht), optional <code>resultat</code>, <code>rang</code>, <code>bemerkung</code>.
          </div>
          <div class="table-responsive">
            <table class="table table-sm" style="font-size:0.72rem;">
              <thead><tr><th>Tabelle</th><th>Mitglied</th><th>Jahr</th><th>Spalten</th></tr></thead>
              <tbody>
                <?php foreach ($wp_schema_ref as $t): ?>
                <tr>
                  <td><code><?= htmlspecialchars($t['table']) ?></code></td>
                  <td><?= htmlspecialchars($t['member']) ?></td>
                  <td><?= htmlspecialchars($t['year']) ?></td>
                  <td><?= htmlspecialchars($t['cols']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="panel-footer">
    <button type="button" class="btn btn-outline-primary btn-sm" id="btnSaveRegel">
      <i class="bi bi-save me-1"></i> Speichern
    </button>
    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnDeleteRegel">
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

    // Wettbewerbs-/Spalten-Registry fuer den Baukasten (serverseitig erzeugt)
    const WP_WETTBEWERBE = <?= json_encode(wp_wettbewerbe_client(), JSON_UNESCAPED_UNICODE) ?>;

    // ============================================
    // LADEN
    // ============================================
    function loadRegeln() {
        $.getJSON('wanderpreise/get_regeln_json.php', function(data) {
            regelnData = data;
            renderDesktopTable();
            renderMobileCards();
            populateVorlagePicker();
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
                '    <button class="btn btn-outline-primary btn-edit-regel" data-tooltip="Bearbeiten" onclick="event.stopPropagation()">' +
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
            $('#btnSaveRegel').html('<i class="bi bi-save me-1"></i> Erstellen');
            // Builder-Defaults: gefuehrt, erster Wettbewerb, alle Kategorien
            $('#regelTyp').val('einzelwettbewerb');
            $('#builderWettbewerb').prop('selectedIndex', 0);
            $('#katAlle').prop('checked', true);
            $('#builderRichtung').val('DESC');
            $('#regelSql').val('');
            $('#builderFilterRows').empty();
            $('#builderSortRows').empty();
            applyRegelTyp('einzelwettbewerb');
        } else {
            $('#panelTitle').html('<i class="bi bi-pencil me-2"></i> Regel bearbeiten');
            $('#regelId').val(regel.id);
            $('#regelCode').val(regel.regel_code);
            $('#regelName').val(regel.regel_name);
            $('#regelBeschreibung').val(regel.regel_beschreibung || '');
            $('#regelSql').val(regel.sql_query || '');
            $('#regelAktiv').prop('checked', regel.aktiv == 1);
            $('#btnDeleteRegel').removeClass('d-none');
            $('#btnSaveRegel').html('<i class="bi bi-save me-1"></i> Speichern');

            // Regel-Typ + Builder-Felder aus gespeicherten Parametern rekonstruieren
            const typ = regel.regel_typ || 'custom';
            $('#regelTyp').val(typ);
            $('#builderFilterRows').empty();
            $('#builderSortRows').empty();
            if (typ !== 'custom') {
                let p = {};
                try { p = regel.regel_params ? JSON.parse(regel.regel_params) : {}; } catch (e) { p = {}; }
                if (p.wettbewerb) $('#builderWettbewerb').val(p.wettbewerb);
                const kat = (p.kategorie || '').replace('Kat. ', '');
                $('input[name="kategorie"][value="' + kat + '"]').prop('checked', true);
                $('#builderRichtung').val(p.richtung === 'ASC' ? 'ASC' : 'DESC');
                if (typ === 'baukasten') {
                    // Wettbewerb muss gesetzt sein, bevor Spalten-Selects befuellt werden
                    (p.filter || []).forEach(function(f) { addFilterRow(f.col, f.op, f.val); });
                    (p.sort   || []).forEach(function(s) { addSortRow(s.col, s.dir); });
                }
            }
            applyRegelTyp(typ);

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
        if ($('#regelTyp').val() === 'baukasten') collectBaukasten();
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
            kategorie: $('input[name="kategorie"]:checked').val() || '',
            csrf_token: $('input[name="csrf_token"]').val()
        }, function(response) {
            if (response.success) {
                $result.html(renderTestResult(response));
            } else {
                $result.html('<div class="alert alert-danger mb-0 py-2 px-3" style="font-size:0.85rem;">' +
                    '<i class="bi bi-x-circle me-2"></i>' + escHtml(response.message || 'Fehler') + '</div>');
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
    // REGEL-TYP / GEFUEHRTER BUILDER
    // ============================================
    const CSRF = $('input[name="csrf_token"]').val();
    let previewTimer = null;

    // Kategorie-Zeile nur zeigen, wenn der Wettbewerb sie unterstuetzt
    function syncKategorieVisibility() {
        const cat = $('#builderWettbewerb option:selected').data('cat');
        if (String(cat) === '1') {
            $('#builderKategorieRow').show();
        } else {
            $('#builderKategorieRow').hide();
            $('#katAlle').prop('checked', true); // ohne Kategorie -> alle
        }
    }

    // UI je nach Regel-Typ umschalten (gefuehrt / baukasten / Experte)
    window.applyRegelTyp = function(typ) {
        const builder = (typ !== 'custom');
        $('#builderFields').toggle(builder);
        $('#expertenTools').toggle(!builder);
        $('#sqlCustomHelp').toggle(!builder);
        $('#einzelOnlyFields').toggle(typ === 'einzelwettbewerb');
        $('#baukastenFields').toggle(typ === 'baukasten');
        $('#sqlLabel').html(builder
            ? 'SQL-Vorschau <span class="text-muted" style="font-weight:400;">(automatisch erzeugt)</span>'
            : 'SQL-Query <span class="text-danger">*</span>');
        $('#regelSql').prop('readonly', builder).prop('required', !builder);
        if (builder) {
            syncKategorieVisibility();
            if (typ === 'baukasten') {
                if ($('#builderSortRows .bk-row').length === 0) addSortRow('score', 'DESC');
                bkRefreshColumns();
                collectBaukasten();
            }
            refreshPreview();
        }
    };

    // SQL-Vorschau vom Server holen (einzige Quelle der SQL-Generierung)
    function refreshPreview() {
        if ($('#regelTyp').val() === 'custom') return;
        $.post('wanderpreise/build_regel_preview.php', {
            regel_typ:   $('#regelTyp').val(),
            wettbewerb:  $('#builderWettbewerb').val(),
            kategorie:   $('input[name="kategorie"]:checked').val() || '',
            richtung:    $('#builderRichtung').val(),
            filter_json: $('#builderFilterJson').val(),
            sort_json:   $('#builderSortJson').val(),
            csrf_token:  CSRF
        }, function(resp) {
            $('#regelSql').val(resp.success ? resp.sql : ('-- Vorschau-Fehler: ' + (resp.message || 'unbekannt')));
        }, 'json');
    }

    function debouncedPreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(refreshPreview, 150);
    }

    $('#regelTyp').on('change', function() { applyRegelTyp($(this).val()); });
    $('#builderWettbewerb').on('change', function() {
        syncKategorieVisibility();
        bkRefreshColumns();
        collectBaukasten();
        debouncedPreview();
    });
    $('input[name="kategorie"]').on('change', debouncedPreview);
    $('#builderRichtung').on('change', debouncedPreview);

    // ============================================
    // BEDINGUNGS-BAUKASTEN (Filter & Sortierung)
    // ============================================
    const BK_OPS = [
        { v: '=',  t: '= (gleich)' },
        { v: '!=', t: '≠ (ungleich)' },
        { v: '>',  t: '> (grösser)' },
        { v: '>=', t: '≥ (grösser/gleich)' },
        { v: '<',  t: '< (kleiner)' },
        { v: '<=', t: '≤ (kleiner/gleich)' },
        { v: 'IS NOT NULL', t: 'ist erfasst' },
        { v: 'IS NULL',     t: 'ist leer' }
    ];

    function bkColOptions(wkey, selected) {
        const cols = (WP_WETTBEWERBE[wkey] && WP_WETTBEWERBE[wkey].columns) || [];
        return cols.map(function(c) {
            return '<option value="' + c.key + '"' + (c.key === selected ? ' selected' : '') + '>' + escHtml(c.label) + '</option>';
        }).join('');
    }
    function bkOpOptions(selected) {
        return BK_OPS.map(function(o) {
            return '<option value="' + o.v + '"' + (o.v === selected ? ' selected' : '') + '>' + o.t + '</option>';
        }).join('');
    }

    function bkToggleValInput($row) {
        const op = $row.find('.bk-op').val();
        const isNull = (op === 'IS NULL' || op === 'IS NOT NULL');
        $row.find('.bk-val').toggle(!isNull).prop('disabled', isNull);
    }

    function addFilterRow(col, op, val) {
        const wkey = $('#builderWettbewerb').val();
        const $row = $(
            '<div class="bk-row d-flex gap-1 align-items-center mb-1">' +
            '  <select class="form-select form-select-sm bk-col">' + bkColOptions(wkey, col || 'score') + '</select>' +
            '  <select class="form-select form-select-sm bk-op" style="max-width:11rem;">' + bkOpOptions(op || '>=') + '</select>' +
            '  <input type="text" class="form-control form-control-sm bk-val" style="max-width:6.5rem;" value="' + escHtml(val || '') + '" placeholder="Zahl / {jahr}">' +
            '  <button type="button" class="btn btn-sm btn-outline-danger bk-del" tabindex="-1" title="Entfernen">&times;</button>' +
            '</div>'
        );
        $('#builderFilterRows').append($row);
        bkToggleValInput($row);
    }

    function addSortRow(col, dir) {
        const wkey = $('#builderWettbewerb').val();
        const $row = $(
            '<div class="bk-row d-flex gap-1 align-items-center mb-1">' +
            '  <select class="form-select form-select-sm bk-col">' + bkColOptions(wkey, col || 'score') + '</select>' +
            '  <select class="form-select form-select-sm bk-dir" style="max-width:13rem;">' +
            '    <option value="DESC"' + ((dir || 'DESC') === 'DESC' ? ' selected' : '') + '>absteigend (höchste zuerst)</option>' +
            '    <option value="ASC"'  + (dir === 'ASC' ? ' selected' : '') + '>aufsteigend (niedrigste zuerst)</option>' +
            '  </select>' +
            '  <button type="button" class="btn btn-sm btn-outline-danger bk-del" tabindex="-1" title="Entfernen">&times;</button>' +
            '</div>'
        );
        $('#builderSortRows').append($row);
    }

    // Spalten-Selects bei Wettbewerb-Wechsel neu befuellen (Auswahl wenn moeglich behalten)
    function bkRefreshColumns() {
        const wkey = $('#builderWettbewerb').val();
        $('#baukastenFields .bk-col').each(function() {
            const cur = $(this).val();
            $(this).html(bkColOptions(wkey, cur));
            if ($(this).val() === null) $(this).html(bkColOptions(wkey, 'score')); // Spalte fehlt -> score
        });
    }

    // Zeilen -> Hidden-JSON-Felder
    function collectBaukasten() {
        const filter = [];
        $('#builderFilterRows .bk-row').each(function() {
            const col = $(this).find('.bk-col').val();
            const op  = $(this).find('.bk-op').val();
            const isNull = (op === 'IS NULL' || op === 'IS NOT NULL');
            const val = isNull ? '' : ($(this).find('.bk-val').val() || '').trim();
            if (col && op) filter.push({ col: col, op: op, val: val });
        });
        const sort = [];
        $('#builderSortRows .bk-row').each(function() {
            const col = $(this).find('.bk-col').val();
            const dir = $(this).find('.bk-dir').val();
            if (col) sort.push({ col: col, dir: dir });
        });
        $('#builderFilterJson').val(JSON.stringify(filter));
        $('#builderSortJson').val(JSON.stringify(sort));
    }

    $('#btnAddFilter').on('click', function() { addFilterRow(); collectBaukasten(); debouncedPreview(); });
    $('#btnAddSort').on('click',   function() { addSortRow();   collectBaukasten(); debouncedPreview(); });
    $('#builderFilterRows, #builderSortRows').on('change', '.bk-col, .bk-op, .bk-dir', function() {
        bkToggleValInput($(this).closest('.bk-row'));
        collectBaukasten(); debouncedPreview();
    });
    $('#builderFilterRows').on('input', '.bk-val', function() { collectBaukasten(); debouncedPreview(); });
    $('#builderFilterRows, #builderSortRows').on('click', '.bk-del', function() {
        $(this).closest('.bk-row').remove();
        collectBaukasten(); debouncedPreview();
    });

    // ============================================
    // VORLAGE AUS BESTEHENDER REGEL (Experten-Modus)
    // ============================================
    function populateVorlagePicker() {
        const $sel = $('#vorlagePicker');
        $sel.find('option:gt(0)').remove();
        regelnData.forEach(function(r) {
            $sel.append('<option value="' + r.id + '">' +
                escHtml(r.regel_name) + ' (' + escHtml(r.regel_code) + ')</option>');
        });
    }

    $('#vorlagePicker').on('change', function() {
        const id = $(this).val();
        if (id) {
            const r = regelnData.find(function(x) { return String(x.id) === String(id); });
            if (r) {
                $('#regelTyp').val('custom');
                applyRegelTyp('custom');
                $('#regelSql').val(r.sql_query || '');
                msvToast('Vorlage \u00fcbernommen (Experten-Modus)', 'info');
            }
        }
        $(this).val('');
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

    // Test-Ergebnis als kompakte Tabelle rendern (statt nur einer Textzeile)
    function renderTestResult(resp) {
        if (!resp.rows || resp.rows.length === 0) {
            return '<div class="alert alert-warning mb-0 py-2 px-3" style="font-size:0.85rem;">' +
                   '<i class="bi bi-info-circle me-2"></i>' + escHtml(resp.message || 'Kein Ergebnis') + '</div>';
        }
        const cols = resp.columns && resp.columns.length ? resp.columns : Object.keys(resp.rows[0]);
        let h = '<div class="alert alert-success mb-2 py-2 px-3" style="font-size:0.85rem;">' +
                '<i class="bi bi-check-circle me-2"></i>' + escHtml(resp.message) + '</div>';
        h += '<div class="table-responsive"><table class="table table-sm table-striped mb-1" style="font-size:0.78rem;">';
        h += '<thead><tr>';
        cols.forEach(function(c) { h += '<th>' + escHtml(c) + '</th>'; });
        h += '</tr></thead><tbody>';
        resp.rows.forEach(function(row) {
            h += '<tr>';
            cols.forEach(function(c) {
                const v = row[c];
                h += '<td>' + escHtml(v === null || v === undefined ? '' : String(v)) + '</td>';
            });
            h += '</tr>';
        });
        h += '</tbody></table></div>';
        if (resp.total && resp.total > resp.rows.length) {
            h += '<div class="form-text" style="font-size:0.72rem;">Zeige ' + resp.rows.length + ' von ' + resp.total + ' Zeilen.</div>';
        }
        return h;
    }

    // ============================================
    // INIT
    // ============================================
    loadRegeln();
});
</script>

<?php include 'footer.inc.php'; ?>
