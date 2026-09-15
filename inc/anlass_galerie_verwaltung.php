<?php
/**
 * Foto-Galerien verwalten (Admin/Vorstand).
 *
 * Oben: Auswahl zum Freischalten einer Galerie für einen JM-Anlass.
 * Darunter: die bereits eingerichteten Galerien. Pro Galerie öffnet „Details" eine
 * kombinierte Ansicht: Einstellungen bearbeiten + Uploader-Übersicht + Fotos nach
 * Tagen (mit Uploader & Aufnahmedatum) + Moderation.
 *
 * Backend: api/anlass_galerie_admin.php · foto_moderate.php · galerie_programm_upload.php
 */
require_once 'dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';

// Zugriffsschutz: nur Vorstand/Admin (vor header.inc.php, da dieser Output erzeugt)
if (!(isAdmin() || isVorstand()) && (int)($_SESSION['user_id'] ?? 0) !== 1) {
    header('Location: home.php');
    exit();
}

$page_specific_css = <<<'CSS'
.ag-enable-box { background:#f8fafc; border:1px solid #e7ebf1; border-radius:0.75rem; padding:0.85rem 1rem; margin-bottom:1.4rem; }
.ag-enable-box h6 { color:#475569; margin-bottom:0.6rem; }
.ag-enable-select { min-width:280px; flex:1 1 280px; }
.ag-section-title { font-size:0.95rem; font-weight:700; color:#334155; margin:0 0 0.6rem; }
.ag-card { border:1px solid #e7ebf1; border-radius:0.75rem; padding:0.85rem 1rem; margin-bottom:0.75rem; background:#fff; border-left:4px solid #2563eb; }
.ag-name { font-weight:600; color:#1f2937; }
.ag-meta { font-size:0.8rem; color:#94a3b8; }
.ag-chip { font-size:0.7rem; padding:0.15rem 0.5rem; border-radius:1rem; font-weight:600; }
.ag-chip.on  { background:#d1f4dd; color:#1e7e44; }
.ag-chip.off { background:#fde2e2; color:#c0392b; }
.ag-chip.warn{ background:#fff3cd; color:#8a6d3b; }
.ag-chip.info{ background:#dbe4f7; color:#2d4373; }
.ag-empty { text-align:center; color:#94a3b8; padding:2rem 1rem; border:1px dashed #e2e8f0; border-radius:0.75rem; }
.ag-empty i { font-size:1.8rem; display:block; margin-bottom:.5rem; }
.ag-edit-box { background:#f8fafc; border:1px solid #eef1f6; border-radius:0.6rem; padding:0.75rem 0.85rem; margin-bottom:1rem; }
.ag-edit-box .form-check-label { font-size:0.82rem; }
.ag-prog-group { max-width:300px; }
.ag-uploaders { display:flex; flex-wrap:wrap; align-items:center; gap:0.35rem; margin-bottom:0.85rem; }
.ag-uploaders-label { font-size:0.72rem; color:#94a3b8; font-weight:600; margin-right:0.2rem; }
.ag-uploader-chip { font-size:0.72rem; background:#eef2f7; color:#334155; border-radius:1rem; padding:0.15rem 0.55rem; }
.ag-uploader-chip b { color:#2563eb; }
.ag-uploader-chip i { color:#94a3b8; }
.ag-modinfo { font-size:0.78rem; background:#f8fafc; border:1px solid #eef1f6; border-radius:0.5rem; padding:0.5rem 0.7rem; }
.ag-modinfo.warn { background:#fff8e6; border-color:#ffe2a8; color:#8a6d3b; }
.mod-day { font-size:0.78rem; font-weight:700; color:#3b5998; text-transform:uppercase; letter-spacing:0.03em; margin:1rem 0 0.45rem; padding-bottom:0.25rem; border-bottom:2px solid #eef2f7; }
.mod-day:first-of-type { margin-top:0; }
.mod-day-count { color:#94a3b8; font-weight:600; }
.mod-grid { display:flex; flex-wrap:wrap; gap:0.6rem; min-height:48px; }
.mod-grid:empty { border:2px dashed #d7dde6; border-radius:0.5rem; justify-content:center; align-items:center; }
.mod-grid:empty::after { content:'Fotos hierher ziehen'; color:#94a3b8; font-size:0.72rem; }
.mod-manual { color:#7c3aed; font-weight:600; }
.mod-item { position:relative; width:140px; border:1px solid #e5e7eb; border-radius:0.5rem; overflow:hidden; background:#f8fafc; cursor:grab; user-select:none; -webkit-user-select:none; }
.mod-item:active { cursor:grabbing; }
.mod-item img { width:100%; aspect-ratio:1/1; object-fit:cover; display:block; cursor:pointer; }
.mod-item .mod-bar { display:flex; gap:0.25rem; padding:0.35rem; }
.mod-item .mod-bar .btn { flex:1; cursor:pointer; }
.mod-state { position:absolute; top:4px; left:4px; font-size:0.62rem; padding:0.1rem 0.4rem; border-radius:0.4rem; font-weight:700; }
.mod-state.pending { background:#fff3cd; color:#8a6d3b; }
.mod-state.approved{ background:#d1f4dd; color:#1e7e44; }
.mod-state.rejected{ background:#fde2e2; color:#c0392b; }
.mod-cap { font-size:0.64rem; color:#475569; padding:0.3rem 0.45rem 0.45rem; line-height:1.3; border-top:1px dashed #e5e7eb; }
.mod-cap .warn { color:#b45309; font-weight:600; }
.mod-cap i { color:#94a3b8; }
.mod-ghost { opacity:0.35; }
.mod-chosen { outline:2px solid #2563eb; outline-offset:-2px; }
.mod-cover-btn { position:absolute; top:4px; right:4px; z-index:3; width:26px; height:26px; border:none; border-radius:50%; background:rgba(0,0,0,0.5); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.8rem; cursor:pointer; }
.mod-cover-btn.active { background:#f59e0b; }
@media (max-width:480px){ .mod-item { width:calc(50% - 0.3rem); } }
CSS;

include 'header.inc.php';

$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
$db   = getDB();

$selected_year = (int) ($_GET['year'] ?? date('Y'));

$years = $db->query("SELECT DISTINCT year FROM JMDefinition ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);
$years = array_map('intval', $years);
if (!in_array((int) date('Y'), $years, true)) array_unshift($years, (int) date('Y'));
if (!in_array($selected_year, $years, true)) { $years[] = $selected_year; rsort($years); }

$stmt = $db->prepare(
    "SELECT d.ID AS jmdefinition_id, d.Bezeichnung AS name, d.Schiesstage, d.Adresse,
            g.id AS galerie_id, g.freigeschaltet, g.moderation_aktiv, g.upload_offen,
            g.beschreibung, g.programm_dateiname,
            (SELECT COUNT(*) FROM anlass_fotos f WHERE f.galerie_id = g.id) AS total,
            (SELECT COUNT(*) FROM anlass_fotos f WHERE f.galerie_id = g.id AND f.status = 'pending') AS pending
       FROM JMDefinition d
       LEFT JOIN anlass_galerie g ON g.jmdefinition_id = d.ID
      WHERE d.year = ? AND d.hidden = 0
      ORDER BY d.Reihenfolge, d.Bezeichnung"
);
$stmt->execute([$selected_year]);

// Aufteilen: noch ohne Galerie (Auswahl oben) vs. bereits eingerichtet (Liste darunter)
$ohneGalerie = [];
$mitGalerie  = [];
foreach ($stmt->fetchAll() as $a) {
    if ($a['galerie_id'] !== null) $mitGalerie[] = $a;
    else $ohneGalerie[] = $a;
}

// Jahr-Filter rechts im Seitenkopf (auch mobil sichtbar)
ob_start(); ?>
<form method="get" class="d-flex align-items-center gap-2">
  <label class="text-muted small mb-0" for="agYear">Jahr</label>
  <select name="year" id="agYear" class="form-select form-select-sm export-year-select" onchange="this.form.submit()">
    <?php foreach ($years as $y): ?>
      <option value="<?= $y ?>" <?= $y === $selected_year ? 'selected' : '' ?>><?= $y ?></option>
    <?php endforeach; ?>
  </select>
</form>
<?php
$page_actions     = ob_get_clean();
$page_show_mobile = true;
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-default">

        <?php $page_title = 'Foto-Galerien'; include 'partials/page_header.inc.php'; ?>

        <input type="hidden" id="csrfToken" value="<?= $csrf ?>">

        <!-- Galerie freischalten -->
        <div class="ag-enable-box">
          <h6><i class="bi bi-plus-circle me-2"></i>Galerie freischalten</h6>
          <?php if ($ohneGalerie): ?>
            <div class="d-flex flex-wrap align-items-center gap-2">
              <label class="visually-hidden" for="agEnableSelect">Anlass</label>
              <select id="agEnableSelect" class="form-select form-select-sm ag-enable-select">
                <option value="">Anlass auswählen …</option>
                <?php foreach ($ohneGalerie as $a): ?>
                  <option value="<?= (int) $a['jmdefinition_id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-outline-success btn-sm" id="agEnableBtn"><i class="bi bi-plus-lg me-1"></i>Freischalten</button>
            </div>
            <div class="form-text">Mitglieder können danach im Portal Fotos zu diesem Anlass hochladen.</div>
          <?php else: ?>
            <p class="text-muted small mb-0">Für <?= $selected_year ?> haben bereits alle Anlässe eine Galerie – oder es sind keine Anlässe erfasst.</p>
          <?php endif; ?>
        </div>

        <!-- Eingerichtete Galerien -->
        <div class="ag-section-title"><i class="bi bi-images me-2"></i>Eingerichtete Galerien <?= $selected_year ?> <span class="badge bg-secondary ms-1" id="agCount"><?= count($mitGalerie) ?></span></div>

        <div id="agList">
        <?php if (!$mitGalerie): ?>
          <div class="ag-empty" id="agEmpty"><i class="bi bi-camera"></i>Keine Galerie für <?= $selected_year ?> freigeschaltet</div>
        <?php else: ?>
          <?php foreach ($mitGalerie as $a):
            $gid   = (int) $a['galerie_id'];
            $hasSt = trim((string) ($a['Schiesstage'] ?? '')) !== '';
          ?>
          <div class="ag-card" id="ag-<?= $gid ?>" data-gid="<?= $gid ?>">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
              <div>
                <div class="ag-name"><?= htmlspecialchars($a['name']) ?></div>
                <div class="ag-meta">
                  <?php if (!empty($a['Adresse'])): ?><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($a['Adresse']) ?> &middot; <?php endif; ?>
                  <?php if ($hasSt): ?><span class="text-success"><i class="bi bi-calendar-check me-1"></i>Schiesstage erfasst</span>
                  <?php else: ?><span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>keine Schiesstage – Fotos werden nach Datum gruppiert</span><?php endif; ?>
                </div>
                <div class="mt-2 d-flex flex-wrap gap-1 align-items-center ag-chips">
                  <span class="ag-chip <?= $a['freigeschaltet'] ? 'on' : 'off' ?>" data-chip="fg"><?= $a['freigeschaltet'] ? 'Sichtbar' : 'Verborgen' ?></span>
                  <span class="ag-chip <?= $a['moderation_aktiv'] ? 'warn' : 'on' ?>" data-chip="mod"><?= $a['moderation_aktiv'] ? 'Moderation an' : 'Moderation aus' ?></span>
                  <span class="ag-chip <?= $a['upload_offen'] ? 'on' : 'off' ?>" data-chip="up"><?= $a['upload_offen'] ? 'Upload offen' : 'Upload zu' ?></span>
                  <span class="ag-chip info" data-chip="total"><?= (int) $a['total'] ?> Foto(s)</span>
                  <span class="ag-chip warn" data-chip="pending" <?= (int) $a['pending'] > 0 ? '' : 'hidden' ?>><?= (int) $a['pending'] ?> wartend</span>
                </div>
              </div>
              <div class="text-nowrap d-flex gap-1">
                <a class="btn btn-sm btn-outline-info" href="../portal/anlass.php?id=<?= $gid ?>" target="_blank" rel="noopener" data-tooltip="Galerie im Portal ansehen" aria-label="Galerie im Portal ansehen"><i class="bi bi-box-arrow-up-right"></i></a>
                <button type="button" class="btn btn-sm btn-outline-primary btn-details"
                  data-gid="<?= $gid ?>"
                  data-name="<?= htmlspecialchars($a['name'], ENT_QUOTES) ?>"
                  data-fg="<?= (int) $a['freigeschaltet'] ?>"
                  data-mod="<?= (int) $a['moderation_aktiv'] ?>"
                  data-up="<?= (int) $a['upload_offen'] ?>"
                  data-besch="<?= htmlspecialchars($a['beschreibung'] ?? '', ENT_QUOTES) ?>"
                  data-progname="<?= htmlspecialchars($a['programm_dateiname'] ?? '', ENT_QUOTES) ?>"
                  data-tooltip="Details, bearbeiten & moderieren">
                  <i class="bi bi-card-list me-1"></i>Details<span class="badge bg-warning text-dark ms-1 ag-pending-badge" <?= (int) $a['pending'] > 0 ? '' : 'hidden' ?>><?= (int) $a['pending'] ?></span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger btn-del-gal" data-gid="<?= $gid ?>" data-name="<?= htmlspecialchars($a['name'], ENT_QUOTES) ?>" data-tooltip="Galerie löschen" aria-label="Galerie löschen"><i class="bi bi-trash"></i></button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Detail-Modal: Einstellungen + Uploader-Übersicht + Fotos nach Tagen + Moderation -->
<div class="modal fade" id="agModModal" tabindex="-1" aria-labelledby="agModTitle">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="agModTitle"><i class="bi bi-images me-2"></i>Galerie-Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <p class="fw-semibold mb-2" id="agModName"></p>
        <input type="hidden" id="agModGid">

        <!-- Einstellungen bearbeiten -->
        <div class="ag-edit-box">
          <div class="row g-2 mb-2">
            <div class="col-12 col-md-4"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="agSetFg"><label class="form-check-label" for="agSetFg">Sichtbar</label></div></div>
            <div class="col-12 col-md-4"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="agSetUp"><label class="form-check-label" for="agSetUp">Upload offen</label></div></div>
            <div class="col-12 col-md-4"><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="agSetMod"><label class="form-check-label" for="agSetMod">Fotos bewilligen (Moderation)</label></div></div>
          </div>
          <label class="visually-hidden" for="agSetBesch">Beschreibung</label>
          <textarea class="form-control form-control-sm mb-2" id="agSetBesch" rows="2" placeholder="Beschreibung (optional)"></textarea>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <div class="text-muted small">Programm-PDF: <span id="agSetProgName" class="fw-medium text-dark">—</span>
              <button type="button" class="btn btn-link btn-sm text-danger p-0 ms-1 d-none" id="agSetProgRemove">entfernen</button>
            </div>
            <div class="input-group input-group-sm ag-prog-group">
              <label class="visually-hidden" for="agSetProgFile">Programm-PDF wählen</label>
              <input type="file" class="form-control" id="agSetProgFile" accept="application/pdf,.pdf">
              <button class="btn btn-outline-success btn-sm" type="button" id="agSetProgUpload" data-tooltip="PDF hochladen" aria-label="PDF hochladen"><i class="bi bi-upload"></i></button>
            </div>
          </div>
          <div class="text-end">
            <button type="button" class="btn btn-sm btn-outline-primary" id="agSetSave"><i class="bi bi-save me-1"></i>Einstellungen speichern</button>
          </div>
        </div>

        <div id="agModInfo" class="ag-modinfo mb-3 d-none"></div>
        <div id="agModUploaders" class="ag-uploaders"></div>

        <div class="d-flex flex-wrap gap-2 mb-3">
          <button type="button" class="btn btn-sm btn-outline-success" id="agModApproveAll"><i class="bi bi-check-all me-1"></i>Alle freigeben</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="agModRematch" data-tooltip="Fotos anhand der Schiesstage neu zuordnen"><i class="bi bi-arrow-repeat me-1"></i>Tage neu zuordnen</button>
          <button type="button" class="btn btn-sm btn-outline-danger ms-auto" id="agModDeleteAll" data-tooltip="Alle Fotos dieser Galerie löschen"><i class="bi bi-trash me-1"></i>Alle Fotos löschen</button>
        </div>

        <div id="agModLoading" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
        <div id="agModEmpty" class="ag-empty d-none"><i class="bi bi-camera"></i>Keine Fotos in dieser Galerie gefunden</div>
        <div id="agModGrid"></div>
      </div>
    </div>
  </div>
</div>

<!-- SortableJS (Foto-Reihenfolge, Tag-Wechsel) -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
(function () {
  var CSRF = document.getElementById('csrfToken').value;
  var modModal = new bootstrap.Modal(document.getElementById('agModModal'));
  var agCoverId = null; // aktuell gesetztes Vorschaubild der offenen Galerie

  function agEsc(s) { return $('<span>').text(s == null ? '' : String(s)).html(); }
  function agFmtD(d) { return d ? String(d).substring(0, 10).split('-').reverse().join('.') : null; }
  function ajaxMsg(xhr, fb) { return (xhr && xhr.responseJSON && xhr.responseJSON.message) || (xhr && xhr.status === 401 ? 'Sitzung abgelaufen – bitte neu anmelden' : fb); }
  // Gemeinsamer POST-Wrapper: JSON, Fehler-Toast bei Netz-/Serverfehler
  function agPost(url, data, ok, failMsg) {
    return $.post(url, $.extend({ csrf_token: CSRF }, data), null, 'json')
      .done(function (r) { if (r && r.success) ok(r); else msvToast((r && r.message) || failMsg, 'error'); })
      .fail(function (xhr) { msvToast(ajaxMsg(xhr, failMsg), 'error'); });
  }
  var API_GAL = '../api/anlass_galerie_admin.php', API_MOD = '../api/foto_moderate.php', API_PROG = '../api/galerie_programm_upload.php';

  // --- Galerie freischalten (neue Karte -> Seite neu laden) ---
  $('#agEnableBtn').on('click', function () {
    var jmid = $('#agEnableSelect').val();
    if (!jmid) { msvToast('Bitte einen Anlass auswählen', 'warning'); return; }
    var $b = $(this), orig = $b.html();
    $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    agPost(API_GAL, { action: 'enable', jmdefinition_id: jmid }, function (r) {
      msvToast(r.message, 'success'); setTimeout(function () { location.reload(); }, 500);
    }, 'Galerie konnte nicht freigeschaltet werden').always(function () { $b.prop('disabled', false).html(orig); });
  });

  // --- Details öffnen (Einstellungen vorfüllen + Fotos laden) ---
  $(document).on('click', '.btn-details', function () {
    var $b = $(this);
    $('#agModGid').val($b.data('gid'));
    $('#agModName').text($b.data('name'));
    $('#agSetFg').prop('checked', $b.data('fg') == 1);
    $('#agSetUp').prop('checked', $b.data('up') == 1);
    $('#agSetMod').prop('checked', $b.data('mod') == 1);
    $('#agSetBesch').val($b.data('besch') || '');
    var prog = $b.data('progname') || '';
    $('#agSetProgName').text(prog || '—');
    $('#agSetProgRemove').toggleClass('d-none', !prog);
    $('#agSetProgFile').val('');
    modModal.show();
    loadModeration($b.data('gid'));
  });

  // --- Einstellungen speichern: Karte per DOM aktualisieren (kein Reload, Modal bleibt offen) ---
  $('#agSetSave').on('click', function () {
    var $btn = $(this), orig = $btn.html(), gid = $('#agModGid').val();
    var fg = $('#agSetFg').is(':checked') ? 1 : 0, up = $('#agSetUp').is(':checked') ? 1 : 0, mod = $('#agSetMod').is(':checked') ? 1 : 0;
    var besch = $('#agSetBesch').val();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    agPost(API_GAL, { action: 'update', galerie_id: gid, freigeschaltet: fg, upload_offen: up, moderation_aktiv: mod, beschreibung: besch }, function (r) {
      msvToast(r.message, 'success');
      var $card = $('#ag-' + gid);
      $card.find('[data-chip="fg"]').attr('class', 'ag-chip ' + (fg ? 'on' : 'off')).text(fg ? 'Sichtbar' : 'Verborgen');
      $card.find('[data-chip="mod"]').attr('class', 'ag-chip ' + (mod ? 'warn' : 'on')).text(mod ? 'Moderation an' : 'Moderation aus');
      $card.find('[data-chip="up"]').attr('class', 'ag-chip ' + (up ? 'on' : 'off')).text(up ? 'Upload offen' : 'Upload zu');
      $card.find('.btn-details').data({ fg: fg, up: up, mod: mod, besch: besch }).attr({ 'data-fg': fg, 'data-up': up, 'data-mod': mod, 'data-besch': besch });
    }, 'Einstellungen konnten nicht gespeichert werden').always(function () { $btn.prop('disabled', false).html(orig); });
  });

  // --- Programm-PDF hochladen / entfernen ---
  $('#agSetProgUpload').on('click', function () {
    var f = document.getElementById('agSetProgFile').files[0];
    if (!f) { msvToast('Bitte eine PDF-Datei wählen', 'warning'); return; }
    var fd = new FormData();
    fd.append('galerie_id', $('#agModGid').val()); fd.append('datei', f); fd.append('csrf_token', CSRF);
    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({ url: API_PROG, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
      .done(function (r) {
        if (r && r.success) { msvToast(r.message, 'success'); $('#agSetProgName').text(r.dateiname); $('#agSetProgRemove').removeClass('d-none'); $('#agSetProgFile').val(''); $('#ag-' + $('#agModGid').val() + ' .btn-details').data('progname', r.dateiname); }
        else msvToast((r && r.message) || 'Upload fehlgeschlagen', 'error');
      })
      .fail(function (xhr) { msvToast(ajaxMsg(xhr, xhr.status === 413 ? 'Datei zu gross für den Server' : 'Upload fehlgeschlagen'), 'error'); })
      .always(function () { $btn.prop('disabled', false).html('<i class="bi bi-upload"></i>'); });
  });
  $('#agSetProgRemove').on('click', function () {
    agPost(API_PROG, { action: 'remove', galerie_id: $('#agModGid').val() }, function (r) {
      msvToast(r.message, 'success'); $('#agSetProgName').text('—'); $('#agSetProgRemove').addClass('d-none');
      $('#ag-' + $('#agModGid').val() + ' .btn-details').data('progname', '');
    }, 'Programm-PDF konnte nicht entfernt werden');
  });

  // --- Galerie löschen: Karte entfernen, Zähler nachführen ---
  $(document).on('click', '.btn-del-gal', function () {
    var gid = $(this).data('gid'), name = $(this).data('name');
    msvConfirmDelete('Galerie „' + name + '" inkl. aller Fotos').then(function (res) {
      if (!res.isConfirmed) return;
      agPost(API_GAL, { action: 'delete', galerie_id: gid }, function (r) {
        msvToast(r.message, 'success');
        $('#ag-' + gid).slideUp(200, function () {
          $(this).remove();
          var n = $('#agList .ag-card').length;
          $('#agCount').text(n);
          if (!n) $('#agList').html('<div class="ag-empty"><i class="bi bi-camera"></i>Keine Galerie freigeschaltet</div>');
        });
      }, 'Galerie konnte nicht gelöscht werden');
    });
  });

  // --- Foto-Kachel bauen ---
  function buildModItem(f, segMap) {
    var ad = agFmtD(f.aufnahme_zeit);
    var dayLabel = (f.tag_index != null) ? (segMap[f.tag_index] || ('Tag ' + f.tag_index))
                   : (f.tag_datum ? ('eigener Tag, ' + agFmtD(f.tag_datum)) : 'Weitere Fotos');
    var cap = '<div class="mod-cap">' +
      (f.uploader ? '<i class="bi bi-person"></i> ' + agEsc(f.uploader) : '<span class="warn">unbekannter Uploader</span>') + '<br>' +
      '<i class="bi bi-camera"></i> ' + (ad ? agEsc(ad) : '<span class="warn">kein Datum</span>') +
      ' → ' + agEsc(dayLabel) + (f.tag_manuell ? ' <span class="mod-manual">· manuell</span>' : '') + '</div>';
    var stateText = { pending: 'Wartet', approved: 'Freigegeben', rejected: 'Abgelehnt' }[f.status] || f.status;
    return '<div class="mod-item" id="modf-' + agEsc(f.id) + '">' +
      '<span class="mod-state ' + agEsc(f.status) + '">' + agEsc(stateText) + '</span>' +
      '<img src="' + agEsc(f.thumb_url) + '" loading="lazy" data-full="' + agEsc(f.full_url) + '" alt="">' +
      (f.status === 'approved' ? '<button type="button" class="mod-cover-btn' + (agCoverId === f.id ? ' active' : '') + '" data-id="' + agEsc(f.id) + '" data-tooltip="Als Vorschaubild der Galerie festlegen" aria-label="Als Vorschaubild festlegen"><i class="bi bi-star' + (agCoverId === f.id ? '-fill' : '') + '"></i></button>' : '') +
      cap +
      '<div class="mod-bar">' +
        '<button type="button" class="btn btn-sm btn-outline-success mod-approve" data-id="' + agEsc(f.id) + '" data-tooltip="Freigeben" aria-label="Freigeben"><i class="bi bi-check-lg"></i></button>' +
        '<button type="button" class="btn btn-sm btn-outline-danger mod-reject" data-id="' + agEsc(f.id) + '" data-tooltip="Ablehnen" aria-label="Ablehnen"><i class="bi bi-x-lg"></i></button>' +
        '<button type="button" class="btn btn-sm btn-outline-danger mod-del" data-id="' + agEsc(f.id) + '" data-tooltip="Löschen" aria-label="Löschen"><i class="bi bi-trash"></i></button>' +
      '</div></div>';
  }

  function currentIds() { return $('#agModGrid .mod-item').map(function () { return this.id.replace('modf-', ''); }).get(); }

  // Drag&Drop-Reihenfolge speichern (galerie-weite Position über alle Tage)
  function saveOrder(then) {
    var ids = currentIds();
    if (!ids.length) return;
    agPost(API_MOD, { action: 'reorder', galerie_id: $('#agModGid').val(), ids: ids.join(',') }, function () { if (then) then(); else msvToast('Reihenfolge gespeichert', 'success'); }, 'Fehler beim Sortieren');
  }

  // SortableJS: Reihenfolge innerhalb eines Tages UND Verschieben zwischen Tagen (gemeinsame group).
  // Ein in einen anderen Tag gezogenes Foto bekommt den Ziel-Tag fest (tag_manuell=1).
  function initSortable() {
    if (!window.Sortable) return;
    $('#agModGrid .mod-grid').each(function () {
      Sortable.create(this, {
        group: 'agfotos', animation: 150, draggable: '.mod-item',
        filter: '.mod-bar, .mod-bar *, .mod-cover-btn, .mod-cover-btn *', preventOnFilter: false,
        forceFallback: true, fallbackTolerance: 4, ghostClass: 'mod-ghost', chosenClass: 'mod-chosen',
        onEnd: function (evt) {
          if (evt.from === evt.to) { saveOrder(); return; }
          var gid = $('#agModGid').val(), fid = evt.item.id.replace('modf-', '');
          agPost(API_MOD, { action: 'move_day', galerie_id: gid, foto_id: fid, tag_index: $(evt.to).attr('data-tagindex') || '', tag_datum: $(evt.to).attr('data-tagdatum') || '' }, function () {
            saveOrder(function () { msvToast('Foto in anderen Tag verschoben', 'success'); loadModeration(gid); });
          }, 'Fehler beim Verschieben').fail(function () { loadModeration(gid); });
        }
      });
    });
  }

  function loadModeration(gid) {
    $('#agModLoading').removeClass('d-none');
    $('#agModEmpty').addClass('d-none');
    $('#agModInfo').addClass('d-none').removeClass('warn');
    $('#agModUploaders').empty();
    $('#agModGrid').empty();
    agPost(API_MOD, { action: 'list', galerie_id: gid }, function (r) {
      agCoverId = r.cover_foto_id || null;

      var segMap = {};
      (r.schiesstage || []).forEach(function (s) { segMap[s.index] = s.label; });
      if (r.schiesstage && r.schiesstage.length) {
        $('#agModInfo').removeClass('warn d-none').html('<strong>Erkannte Schiesstage:</strong> ' + r.schiesstage.map(function (s) { return agEsc(s.label); }).join(' · '));
      } else {
        $('#agModInfo').addClass('warn').removeClass('d-none').html('<i class="bi bi-exclamation-triangle me-1"></i>Keine Schiesstage erkannt – Fotos werden nach ihrem Aufnahmedatum gruppiert. Trage die Schiesstage beim JM-Anlass ein (mit Monatsname, z.B. „27. Juni 2026") und klicke dann „Tage neu zuordnen".');
      }

      // Zähler auf der Karte nachführen
      var pending = (r.fotos || []).filter(function (f) { return f.status === 'pending'; }).length;
      var $card = $('#ag-' + gid);
      $card.find('[data-chip="total"]').text((r.fotos || []).length + ' Foto(s)');
      $card.find('[data-chip="pending"]').text(pending + ' wartend').prop('hidden', !pending);
      $card.find('.ag-pending-badge').text(pending).prop('hidden', !pending);

      if (!r.fotos || !r.fotos.length) { $('#agModEmpty').removeClass('d-none'); return; }

      var upc = {};
      r.fotos.forEach(function (f) { var u = f.uploader || 'Unbekannt'; upc[u] = (upc[u] || 0) + 1; });
      $('#agModUploaders').html('<span class="ag-uploaders-label">Hochgeladen von:</span> ' + Object.keys(upc).sort().map(function (u) {
        return '<span class="ag-uploader-chip"><i class="bi bi-person"></i> ' + agEsc(u) + ' <b>' + upc[u] + '</b></span>';
      }).join(''));

      function pad4(n) { return ('000' + n).slice(-4); }
      var groups = {}, order = [];
      function ensureGroup(key, label, tagindex, tagdatum, sortKey) {
        if (!groups[key]) { groups[key] = { label: label, tagindex: tagindex, tagdatum: tagdatum, sortKey: sortKey, fotos: [] }; order.push(key); }
        return groups[key];
      }
      (r.schiesstage || []).forEach(function (s) { ensureGroup('d' + s.index, s.label, s.index, s.datum, '1_' + pad4(s.index)); });
      r.fotos.forEach(function (f) {
        if (f.tag_index != null) ensureGroup('d' + f.tag_index, segMap[f.tag_index] || ('Tag ' + f.tag_index), f.tag_index, f.tag_datum, '1_' + pad4(f.tag_index)).fotos.push(f);
        else if (f.tag_datum) ensureGroup('date_' + f.tag_datum, agFmtD(f.tag_datum), '', f.tag_datum, '2_' + f.tag_datum).fotos.push(f);
        else ensureGroup('rest', 'Weitere Fotos (kein Tag)', '', '', '3').fotos.push(f);
      });
      ensureGroup('rest', 'Weitere Fotos (kein Tag)', '', '', '3');
      order.sort(function (a, b) { return groups[a].sortKey < groups[b].sortKey ? -1 : (groups[a].sortKey > groups[b].sortKey ? 1 : 0); });

      var html = '<div class="text-muted small mb-2"><i class="bi bi-arrows-move me-1"></i>Tipp: Foto ziehen = Reihenfolge · in einen anderen Tag ziehen = Tag wechseln (überschreibt den Zeitstempel; Foto wird „manuell") · <i class="bi bi-star"></i> = Vorschaubild.</div>';
      order.forEach(function (key) {
        var g = groups[key];
        html += '<div class="mod-day">' + agEsc(g.label) + ' <span class="mod-day-count">(' + g.fotos.length + ')</span></div>';
        html += '<div class="mod-grid" data-tagindex="' + agEsc(g.tagindex == null ? '' : g.tagindex) + '" data-tagdatum="' + agEsc(g.tagdatum || '') + '">' + g.fotos.map(function (f) { return buildModItem(f, segMap); }).join('') + '</div>';
      });
      $('#agModGrid').html(html);
      initSortable();
    }, 'Fotos konnten nicht geladen werden').always(function () { $('#agModLoading').addClass('d-none'); });
  }

  function modAction(action, id) {
    agPost(API_MOD, { action: action, id: id }, function () {
      if (action === 'delete') { $('#modf-' + id).fadeOut(200, function () { $(this).remove(); }); }
      else {
        var label = action === 'approve' ? 'approved' : 'rejected';
        $('#modf-' + id + ' .mod-state').attr('class', 'mod-state ' + label).text(action === 'approve' ? 'Freigegeben' : 'Abgelehnt');
      }
    }, 'Aktion fehlgeschlagen');
  }
  $(document).on('click', '.mod-approve', function () { modAction('approve', $(this).data('id')); });
  $(document).on('click', '.mod-reject', function () { modAction('reject', $(this).data('id')); });
  $(document).on('click', '.mod-del', function () {
    var id = $(this).data('id');
    msvConfirmDelete('dieses Foto').then(function (res) { if (res.isConfirmed) modAction('delete', id); });
  });

  $('#agModApproveAll').on('click', function () {
    var gid = $('#agModGid').val();
    agPost(API_MOD, { action: 'approve_all', galerie_id: gid }, function (r) { msvToast(r.message, 'success'); loadModeration(gid); }, 'Freigeben fehlgeschlagen');
  });
  $('#agModRematch').on('click', function () {
    var gid = $('#agModGid').val();
    agPost(API_MOD, { action: 'rematch', galerie_id: gid }, function (r) { msvToast(r.message, 'success'); loadModeration(gid); }, 'Neuzuordnung fehlgeschlagen');
  });
  $('#agModDeleteAll').on('click', function () {
    var gid = $('#agModGid').val();
    msvConfirmDelete('ALLE Fotos dieser Galerie').then(function (res) {
      if (!res.isConfirmed) return;
      agPost(API_MOD, { action: 'delete_all', galerie_id: gid }, function (r) { msvToast(r.message, 'success'); loadModeration(gid); }, 'Fehler beim Löschen');
    });
  });

  // Klick aufs Thumbnail -> Vollbild in neuem Tab
  $(document).on('click', '#agModGrid img', function () { window.open($(this).data('full'), '_blank', 'noopener'); });

  // Vorschaubild (Cover) festlegen / entfernen
  $(document).on('click', '.mod-cover-btn', function (e) {
    e.stopPropagation();
    var $b = $(this), id = $b.data('id'), makeCover = !$b.hasClass('active');
    agPost(API_GAL, { action: 'set_cover', galerie_id: $('#agModGid').val(), foto_id: makeCover ? id : 0 }, function (r) {
      msvToast(r.message, 'success');
      agCoverId = r.cover_foto_id;
      $('#agModGrid .mod-cover-btn').removeClass('active').find('i').attr('class', 'bi bi-star');
      if (agCoverId) $('#agModGrid .mod-cover-btn[data-id="' + agCoverId + '"]').addClass('active').find('i').attr('class', 'bi bi-star-fill');
    }, 'Vorschaubild konnte nicht gesetzt werden');
  });
})();
</script>

<?php include 'footer.inc.php'; ?>
