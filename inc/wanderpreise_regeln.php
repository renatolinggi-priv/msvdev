<?php
// wanderpreise_regeln.php – Wanderpreis-Regeln (Zuordnung der Gewinner per SQL-Regel; geführt, Baukasten oder Experte)
include 'dbconnect.inc.php';
require_once __DIR__ . '/wanderpreise/regel_builder.inc.php'; // Registry + Schema-Referenz fuer den Builder

$wp_wettbewerbe = wp_wettbewerb_registry();
$wp_schema_ref  = wp_regel_schema_reference();

// Nur seitenspezifische Klassen; Tabelle, Titel, Panel, Flag-Dots, Code-Badge, Aktions-Card kommen aus msv-styles.css
$page_specific_css = <<<'CSS'
.title-search { width: 200px; }
.title-search input { font-size: 0.85rem; border-radius: 20px; padding-left: 2rem; }
.title-search .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem; }
.regel-name { font-weight: 500; color: #1e293b; }
.regel-desc { display: block; color: #64748b; font-size: 0.85rem; white-space: normal; overflow-wrap: anywhere; line-height: 1.35; }
.flag-dot.on { background: #22c55e; color: #fff; } /* Status "aktiv" grün statt zentral blau */
.row-actions { display: flex; gap: 4px; justify-content: flex-end; }
.row-actions .btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
.fs-xs { font-size: 0.75rem; }
.fs-xxs { font-size: 0.72rem; }
.w-140 { width: 140px; } .w-200 { width: 200px; }
.w-op { max-width: 11rem; } .w-val { max-width: 6.5rem; } .w-dir { max-width: 13rem; }
.sql-editor { font-family: 'Courier New', monospace; font-size: 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; tab-size: 4; line-height: 1.5; }
.sql-editor:focus { background: #fff; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.panel-label .hint { font-weight: 400; text-transform: none; }
.mobile-card.regel-inaktiv { opacity: .75; }
.mobile-card .card-code { font-family: 'Courier New', monospace; background: #eff6ff; color: #1e40af; padding: 2px 8px; border-radius: 6px; font-size: 12px; }
@media (max-width: 767.98px) { .title-search { display: none; } }
CSS;

include 'header.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-default">

        <?php $page_title = 'Wanderpreis-Regeln'; include 'partials/page_header.inc.php'; ?>

        <div class="content-background">
          <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <!-- Toolbar: Neue Regel (auch mobil sichtbar) -->
          <div class="export-toolbar mb-3">
            <div class="export-toolbar-head">
              <i class="bi bi-diagram-3"></i>
              <span>Regeln bestimmen, wer einen Wanderpreis gewinnt</span>
              <button type="button" class="btn btn-outline-success btn-sm ms-auto" id="btnNeueRegel">
                <i class="bi bi-plus-lg me-1"></i>Neue Regel
              </button>
            </div>
          </div>

          <!-- Desktop: Tabelle -->
          <div class="desktop-table-container">
            <div class="table-wrapper">
              <h5 class="table-title">
                <span><i class="bi bi-list-ul me-2"></i>Zuordnungsregeln <span class="badge bg-secondary ms-1" id="regelnCountBadge">0</span></span>
                <span class="title-search position-relative">
                  <i class="bi bi-search search-icon"></i>
                  <input type="text" class="form-control form-control-sm" id="desktopSearch" placeholder="Suchen..." aria-label="Regeln suchen">
                </span>
              </h5>
              <div class="table-responsive">
                <table class="hybrid-table" id="regelnTable">
                  <thead>
                    <tr>
                      <th class="w-140">Code</th>
                      <th class="w-200">Name</th>
                      <th class="d-none d-lg-table-cell">Beschreibung</th>
                      <th class="text-center" style="width:60px;">Status</th>
                      <th class="text-end" style="width:120px;">Aktionen</th>
                    </tr>
                  </thead>
                  <tbody id="regelnTableBody">
                    <tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm me-2"></div>Lade Regeln...</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Mobile: Cards -->
          <div class="mobile-cards-container">
            <div class="mobile-search">
              <div class="position-relative">
                <i class="bi bi-search search-icon"></i>
                <input type="text" class="form-control" id="mobileSearch" placeholder="Suchen..." aria-label="Regeln suchen">
              </div>
            </div>
            <div class="mobile-cards-scroll" id="mobileCardsScroll"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Slide-Panel (zentrales Partial); 620px, damit der SQL-Editor Platz hat -->
<?php
$panel_id    = 'regelPanel';
$panel_width = '620px';
$panel_title = '<span id="panelTitle"><i class="bi bi-plus-circle me-2"></i>Neue Regel erstellen</span>';
ob_start(); ?>
    <form id="regelForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
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

      <div class="mb-3">
        <label class="panel-label" for="regelTyp">Regel-Typ</label>
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
            <span class="panel-label">Bedingungen <span class="hint text-muted">(optional, mit UND verknüpft)</span></span>
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
        <label class="panel-label" id="sqlLabel" for="regelSql">SQL-Query <span class="text-danger">*</span></label>
        <div class="form-text mb-2 fs-xs" id="sqlCustomHelp">
          Muss <code>gewinner_id</code> zurückgeben. Optional: <code>resultat</code>, <code>rang</code>, <code>bemerkung</code>.<br>
          Platzhalter: <span class="code-badge fs-xs">{jahr}</span> <span class="code-badge fs-xs">{kategorie}</span> <span class="code-badge fs-xs">{wanderpreis_id}</span><br>
          Erlaubt: <code>SET @var = …;</code> gefolgt von genau einem <code>SELECT</code>/<code>WITH</code>. Schreibende Befehle werden abgelehnt.
        </div>
        <textarea name="sql_query" id="regelSql" class="form-control sql-editor" rows="10" required placeholder="SELECT m.ID AS gewinner_id, ... FROM ..."></textarea>
      </div>

      <div class="mb-3">
        <button type="button" class="btn btn-outline-primary btn-sm" id="btnTestSql"><i class="bi bi-play-fill me-1"></i>SQL testen</button>
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

<script>
$(function() {
    const CSRF = document.getElementById('csrfToken').value;
    const WP_WETTBEWERBE = <?= json_encode(wp_wettbewerbe_client(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    let regelnData = [];
    let previewTimer = null;

    const escHtml = s => $('<span>').text(s == null ? '' : String(s)).html();
    const ajaxMsg = (xhr, fb) => (xhr && xhr.responseJSON && xhr.responseJSON.message) || (xhr && xhr.status === 401 ? 'Sitzung abgelaufen – bitte neu anmelden' : fb);
    const findRegel = id => regelnData.find(r => String(r.id) === String(id));

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
                ? '<span class="flag-dot on" data-tooltip="Aktiv"><i class="bi bi-check2"></i></span>'
                : '<span class="flag-dot off" data-tooltip="Inaktiv"><i class="bi bi-pause"></i></span>';
            return '<tr class="hybrid-row" data-id="' + escHtml(r.id) + '">' +
                '<td><span class="code-badge">' + escHtml(r.regel_code) + '</span></td>' +
                '<td><span class="regel-name">' + escHtml(r.regel_name) + '</span></td>' +
                '<td class="d-none d-lg-table-cell"><span class="regel-desc">' + escHtml(r.regel_beschreibung || '–') + '</span></td>' +
                '<td class="text-center">' + statusDot + '</td>' +
                '<td><div class="row-actions">' +
                '<button type="button" class="btn btn-outline-primary btn-sm" data-action="test" data-tooltip="SQL testen"><i class="bi bi-play-fill"></i></button>' +
                '<button type="button" class="btn btn-outline-primary btn-sm" data-action="edit" data-tooltip="Bearbeiten"><i class="bi bi-pencil"></i></button>' +
                '<button type="button" class="btn btn-outline-danger btn-sm" data-action="delete" data-tooltip="Löschen"><i class="bi bi-trash"></i></button>' +
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
                '<div class="fw-bold mt-1">' + escHtml(r.regel_name) + '</div>' +
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
            $('#builderFilterRows, #builderSortRows').empty();
            applyRegelTyp('einzelwettbewerb');
            $('.hybrid-row').removeClass('selected');
        } else {
            $('#panelTitle').html('<i class="bi bi-pencil me-2"></i>Regel bearbeiten');
            $('#regelId').val(regel.id);
            $('#regelCode').val(regel.regel_code);
            $('#regelName').val(regel.regel_name);
            $('#regelBeschreibung').val(regel.regel_beschreibung || '');
            $('#regelSql').val(regel.sql_query || '');
            $('#regelAktiv').prop('checked', Number(regel.aktiv) === 1);
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
            $('.hybrid-row').removeClass('selected');
            $('.hybrid-row[data-id="' + regel.id + '"]').addClass('selected');
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
        $('.hybrid-row').removeClass('selected');
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
        const res = await msvConfirmDelete(r ? 'Regel „' + r.regel_name + '"' : 'diese Regel');
        if (!res.isConfirmed) return;
        $.post('wanderpreise/delete_regel.php', { id, csrf_token: CSRF }, null, 'json')
            .done(resp => { if (resp && resp.success) { msvToast('Regel gelöscht', 'success'); closePanel(); loadRegeln(); } else msvToast((resp && resp.message) || 'Fehler beim Löschen', 'error'); })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Löschen'), 'error'));
    }
    $('#btnDeleteRegel').on('click', function() { const id = $('#regelId').val(); if (id) deleteRegel(id); });

    // ---------- SQL testen ----------
    $('#btnTestSql').on('click', function() {
        const sql = $('#regelSql').val();
        if (!sql.trim()) { msvToast('Bitte SQL eingeben', 'warning'); return; }
        const $result = $('#testResult').prop('hidden', false).html('<div class="alert alert-light border mb-0 py-2 px-3 small"><span class="spinner-border spinner-border-sm me-2"></span>Teste SQL...</div>');
        $.post('wanderpreise/test_regel_sql.php', { sql, jahr: new Date().getFullYear(), kategorie: $('input[name="kategorie"]:checked').val() || '', csrf_token: CSRF }, null, 'json')
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

    // ---------- Suche ----------
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

<?php include 'footer.inc.php'; ?>
