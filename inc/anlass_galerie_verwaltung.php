<?php
/**
 * Foto-Galerien verwalten (Admin/Vorstand).
 *
 * Oben: durchsuchbares Select2 zum Freischalten einer Galerie für einen JM-Anlass.
 * Darunter: die bereits eingerichteten Galerien. Pro Galerie öffnet „Details" eine
 * kombinierte Ansicht: Einstellungen bearbeiten + Uploader-Übersicht + Fotos nach
 * Tagen (mit Uploader & Aufnahmedatum) + Moderation.
 *
 * Backend: api/anlass_galerie_admin.php · foto_moderate.php · galerie_programm_upload.php
 */
try { include 'dbconnect.inc.php'; } catch (Exception $e) { die("System error."); }
require_once __DIR__ . '/../auth.php';

// Zugriffsschutz: nur Vorstand/Admin (vor header.inc.php, da dieser Output erzeugt)
if (!isset($_SESSION['user_id']) || !(isAdmin() || isVorstand())) {
    if (($_SESSION['user_id'] ?? 0) != 1) {
        header('Location: home.php');
        exit();
    }
}

$page_specific_css = <<<'CSS'
.main-content-wrapper { max-width: 1040px; }
.ag-enable-box { background:#f8fafc; border:1px solid #e7ebf1; border-radius:0.75rem; padding:0.85rem 1rem; margin-bottom:1.4rem; }
.ag-enable-box h6 { color:#475569; margin-bottom:0.6rem; }
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
.ag-edit-box { background:#f8fafc; border:1px solid #eef1f6; border-radius:0.6rem; padding:0.75rem 0.85rem; margin-bottom:1rem; }
.ag-edit-box .form-check-label { font-size:0.82rem; }
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
.mod-item .mod-bar .btn { cursor:pointer; }
@media (max-width:480px){ .mod-item { width:calc(50% - 0.3rem); } }
.mod-item img { width:100%; aspect-ratio:1/1; object-fit:cover; display:block; cursor:pointer; }
.mod-item .mod-bar { display:flex; gap:0.25rem; padding:0.35rem; }
.mod-item .mod-bar .btn { flex:1; }
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
.select2-container--bootstrap-5 .select2-selection { min-height:38px; }
CSS;

include 'header.inc.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
$db   = getDB();

$selected_year = (int) ($_GET['year'] ?? date('Y'));

$years = $db->query("SELECT DISTINCT year FROM JMDefinition ORDER BY year DESC")->fetchAll(PDO::FETCH_COLUMN);
$years = array_map('intval', $years);
if (!in_array((int) date('Y'), $years, true)) array_unshift($years, (int) date('Y'));
if (empty($years)) $years = [(int) date('Y')];

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

// Aufteilen: noch ohne Galerie (für Select2) vs. bereits eingerichtet (Liste darunter)
$ohneGalerie = [];
$mitGalerie  = [];
foreach ($stmt->fetchAll() as $a) {
    if ($a['galerie_id'] !== null) $mitGalerie[] = $a;
    else $ohneGalerie[] = $a;
}
?>

<!-- Select2 (durchsuchbares Dropdown) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <h2 class="h4 mb-0 page-title">Foto-Galerien</h2>
          <form method="get" class="d-flex align-items-center gap-2">
            <label class="text-muted small mb-0">Jahr</label>
            <select name="year" class="form-select form-select-sm" style="max-width:120px;" onchange="this.form.submit()">
              <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $y === $selected_year ? 'selected' : '' ?>><?= $y ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>

        <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Galerie freischalten -->
        <div class="ag-enable-box">
          <h6><i class="bi bi-plus-circle me-2"></i>Galerie freischalten</h6>
          <?php if ($ohneGalerie): ?>
            <div class="d-flex flex-wrap align-items-center gap-2">
              <select id="agEnableSelect" style="min-width:280px; flex:1 1 280px;">
                <option value=""></option>
                <?php foreach ($ohneGalerie as $a): ?>
                  <option value="<?= (int) $a['jmdefinition_id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-outline-success btn-sm" id="agEnableBtn"><i class="bi bi-plus-lg me-1"></i>Freischalten</button>
            </div>
            <div class="form-text">Mitglieder können danach im Portal Fotos zu diesem Anlass hochladen.</div>
          <?php else: ?>
            <p class="text-muted small mb-0">Für <?= $selected_year ?> haben bereits alle Anlässe eine Galerie – oder es sind keine Anlässe erfasst.</p>
          <?php endif; ?>
        </div>

        <!-- Eingerichtete Galerien -->
        <div class="ag-section-title"><i class="bi bi-images me-2"></i>Eingerichtete Galerien <?= $selected_year ?></div>

        <?php if (!$mitGalerie): ?>
          <div class="ag-empty"><i class="bi bi-camera d-block mb-2" style="font-size:1.8rem;"></i>Noch keine Galerie freigeschaltet.</div>
        <?php else: ?>
          <?php foreach ($mitGalerie as $a):
            $gid   = (int) $a['galerie_id'];
            $hasSt = trim((string) ($a['Schiesstage'] ?? '')) !== '';
          ?>
          <div class="ag-card" id="ag-<?= $gid ?>">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
              <div>
                <div class="ag-name"><?= htmlspecialchars($a['name']) ?></div>
                <div class="ag-meta">
                  <?php if (!empty($a['Adresse'])): ?><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($a['Adresse']) ?> &middot; <?php endif; ?>
                  <?php if ($hasSt): ?><span class="text-success"><i class="bi bi-calendar-check me-1"></i>Schiesstage erfasst</span>
                  <?php else: ?><span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>keine Schiesstage – Fotos werden nach Datum gruppiert</span><?php endif; ?>
                </div>
                <div class="mt-2 d-flex flex-wrap gap-1 align-items-center">
                  <span class="ag-chip <?= $a['freigeschaltet'] ? 'on' : 'off' ?>"><?= $a['freigeschaltet'] ? 'Sichtbar' : 'Verborgen' ?></span>
                  <span class="ag-chip <?= $a['moderation_aktiv'] ? 'warn' : 'on' ?>"><?= $a['moderation_aktiv'] ? 'Moderation an' : 'Moderation aus' ?></span>
                  <span class="ag-chip <?= $a['upload_offen'] ? 'on' : 'off' ?>"><?= $a['upload_offen'] ? 'Upload offen' : 'Upload zu' ?></span>
                  <span class="ag-chip info"><?= (int) $a['total'] ?> Foto(s)</span>
                  <?php if ((int) $a['pending'] > 0): ?><span class="ag-chip warn"><?= (int) $a['pending'] ?> wartend</span><?php endif; ?>
                </div>
              </div>
              <div class="text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="../portal/anlass.php?id=<?= $gid ?>" target="_blank" rel="noopener" title="Galerie im Portal ansehen"><i class="bi bi-box-arrow-up-right"></i></a>
                <button class="btn btn-sm btn-outline-primary btn-details"
                  data-gid="<?= $gid ?>"
                  data-name="<?= htmlspecialchars($a['name'], ENT_QUOTES) ?>"
                  data-fg="<?= (int) $a['freigeschaltet'] ?>"
                  data-mod="<?= (int) $a['moderation_aktiv'] ?>"
                  data-up="<?= (int) $a['upload_offen'] ?>"
                  data-besch="<?= htmlspecialchars($a['beschreibung'] ?? '', ENT_QUOTES) ?>"
                  data-progname="<?= htmlspecialchars($a['programm_dateiname'] ?? '', ENT_QUOTES) ?>"
                  title="Details, bearbeiten & moderieren">
                  <i class="bi bi-card-list me-1"></i>Details<?php if ((int) $a['pending'] > 0): ?> <span class="badge bg-warning text-dark"><?= (int) $a['pending'] ?></span><?php endif; ?>
                </button>
                <button class="btn btn-sm btn-outline-danger btn-del-gal" data-gid="<?= $gid ?>" data-name="<?= htmlspecialchars($a['name'], ENT_QUOTES) ?>" title="Galerie löschen"><i class="bi bi-trash"></i></button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<!-- Detail-Modal: Einstellungen + Uploader-Übersicht + Fotos nach Tagen + Moderation -->
<div class="modal fade" id="agModModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-images me-2"></i>Galerie-Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
          <textarea class="form-control form-control-sm mb-2" id="agSetBesch" rows="2" placeholder="Beschreibung (optional)"></textarea>
          <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="text-muted small">Programm-PDF: <span id="agSetProgName" class="fw-medium text-dark">—</span>
              <button type="button" class="btn btn-link btn-sm text-danger p-0 ms-1 d-none" id="agSetProgRemove">entfernen</button>
            </div>
            <div class="input-group input-group-sm" style="max-width:300px;">
              <input type="file" class="form-control" id="agSetProgFile" accept="application/pdf,.pdf">
              <button class="btn btn-outline-success btn-sm" type="button" id="agSetProgUpload" title="PDF hochladen"><i class="bi bi-upload"></i></button>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="agSetSave"><i class="bi bi-save me-1"></i>Einstellungen speichern</button>
          </div>
        </div>

        <div id="agModInfo" class="ag-modinfo mb-3 d-none"></div>
        <div id="agModUploaders" class="ag-uploaders"></div>

        <div class="d-flex flex-wrap gap-2 mb-3">
          <button class="btn btn-sm btn-outline-success" id="agModApproveAll"><i class="bi bi-check-all me-1"></i>Alle freigeben</button>
          <button class="btn btn-sm btn-outline-secondary" id="agModRematch" title="Fotos anhand der Schiesstage neu zuordnen"><i class="bi bi-arrow-repeat me-1"></i>Tage neu zuordnen</button>
          <button class="btn btn-sm btn-outline-danger ms-auto" id="agModDeleteAll" title="Alle Fotos dieser Galerie löschen"><i class="bi bi-trash me-1"></i>Alle Fotos löschen</button>
        </div>

        <div id="agModLoading" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
        <div id="agModEmpty" class="ag-empty d-none">Noch keine Fotos in dieser Galerie.</div>
        <div id="agModGrid"></div>
      </div>
    </div>
  </div>
</div>

<!-- Select2 + SortableJS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
(function () {
  var CSRF = document.getElementById('csrfToken').value;
  var modModal = new bootstrap.Modal(document.getElementById('agModModal'));
  var agCoverId = null; // aktuell gesetztes Vorschaubild der offenen Galerie

  function agEsc(s) { return $('<span>').text(s == null ? '' : s).html(); }
  function agFmtD(d) { return d ? String(d).substring(0, 10).split('-').reverse().join('.') : null; }

  // Select2 zum Freischalten
  if ($.fn.select2 && $('#agEnableSelect').length) {
    $('#agEnableSelect').select2({ theme: 'bootstrap-5', placeholder: 'Anlass auswählen …', allowClear: true, width: '100%' });
  }

  // --- Galerie freischalten ---
  $('#agEnableBtn').on('click', function () {
    var jmid = $('#agEnableSelect').val();
    if (!jmid) { msvToast('Bitte einen Anlass auswählen', 'warning'); return; }
    var $b = $(this);
    $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.post('../api/anlass_galerie_admin.php', { action: 'enable', jmdefinition_id: jmid, csrf_token: CSRF }, function (r) {
      if (r.success) { msvToast(r.message, 'success'); setTimeout(function () { location.reload(); }, 600); }
      else { msvToast(r.message, 'error'); $b.prop('disabled', false).html('<i class="bi bi-plus-lg me-1"></i>Freischalten'); }
    }, 'json').fail(function () { msvToast('Fehler', 'error'); $b.prop('disabled', false).html('<i class="bi bi-plus-lg me-1"></i>Freischalten'); });
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

  // --- Einstellungen speichern ---
  $('#agSetSave').on('click', function () {
    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.post('../api/anlass_galerie_admin.php', {
      action: 'update', galerie_id: $('#agModGid').val(),
      freigeschaltet: $('#agSetFg').is(':checked') ? 1 : 0,
      upload_offen: $('#agSetUp').is(':checked') ? 1 : 0,
      moderation_aktiv: $('#agSetMod').is(':checked') ? 1 : 0,
      beschreibung: $('#agSetBesch').val(), csrf_token: CSRF
    }, function (r) {
      if (r.success) { msvToast(r.message, 'success'); setTimeout(function () { location.reload(); }, 600); }
      else { msvToast(r.message, 'error'); }
    }, 'json').fail(function () { msvToast('Fehler beim Speichern', 'error'); })
      .always(function () { $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Einstellungen speichern'); });
  });

  // --- Programm-PDF hochladen / entfernen ---
  $('#agSetProgUpload').on('click', function () {
    var f = document.getElementById('agSetProgFile').files[0];
    if (!f) { msvToast('Bitte eine PDF-Datei wählen', 'warning'); return; }
    var fd = new FormData();
    fd.append('galerie_id', $('#agModGid').val());
    fd.append('datei', f);
    fd.append('csrf_token', CSRF);
    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({ url: '../api/galerie_programm_upload.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
      success: function (r) {
        if (r.success) { msvToast(r.message, 'success'); $('#agSetProgName').text(r.dateiname); $('#agSetProgRemove').removeClass('d-none'); $('#agSetProgFile').val(''); }
        else { msvToast(r.message, 'error'); }
      }, error: function () { msvToast('Upload fehlgeschlagen', 'error'); },
      complete: function () { $btn.prop('disabled', false).html('<i class="bi bi-upload"></i>'); }
    });
  });
  $('#agSetProgRemove').on('click', function () {
    $.post('../api/galerie_programm_upload.php', { action: 'remove', galerie_id: $('#agModGid').val(), csrf_token: CSRF }, function (r) {
      if (r.success) { msvToast(r.message, 'success'); $('#agSetProgName').text('—'); $('#agSetProgRemove').addClass('d-none'); }
      else { msvToast(r.message, 'error'); }
    }, 'json');
  });

  // --- Galerie löschen ---
  $(document).on('click', '.btn-del-gal', function () {
    var gid = $(this).data('gid'), name = $(this).data('name');
    msvConfirmDelete('Galerie "' + name + '" inkl. aller Fotos').then(function (res) {
      if (res.isConfirmed) {
        $.post('../api/anlass_galerie_admin.php', { action: 'delete', galerie_id: gid, csrf_token: CSRF }, function (r) {
          if (r.success) { msvToast(r.message, 'success'); setTimeout(function () { location.reload(); }, 600); }
          else { msvToast(r.message, 'error'); }
        }, 'json');
      }
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
      ' → ' + agEsc(dayLabel) + (f.tag_manuell ? ' <span class="mod-manual">· manuell</span>' : '') +
      '</div>';
    return '<div class="mod-item" id="modf-' + f.id + '">' +
      '<span class="mod-state ' + f.status + '">' + ({pending:'Wartet',approved:'Freigegeben',rejected:'Abgelehnt'}[f.status] || f.status) + '</span>' +
      '<img src="' + f.thumb_url + '" loading="lazy" data-full="' + f.full_url + '" alt="">' +
      (f.status === 'approved' ? '<button class="mod-cover-btn' + (agCoverId === f.id ? ' active' : '') + '" data-id="' + f.id + '" title="Als Vorschaubild der Galerie festlegen"><i class="bi bi-star' + (agCoverId === f.id ? '-fill' : '') + '"></i></button>' : '') +
      cap +
      '<div class="mod-bar">' +
        '<button class="btn btn-sm btn-outline-success mod-approve" data-id="' + f.id + '" title="Freigeben"><i class="bi bi-check-lg"></i></button>' +
        '<button class="btn btn-sm btn-outline-danger mod-reject" data-id="' + f.id + '" title="Ablehnen"><i class="bi bi-x-lg"></i></button>' +
        '<button class="btn btn-sm btn-outline-danger mod-del" data-id="' + f.id + '" title="Löschen"><i class="bi bi-trash"></i></button>' +
      '</div></div>';
  }

  // Drag&Drop-Reihenfolge speichern (galerie-weite Position über alle Tage)
  function saveOrder() {
    var ids = $('#agModGrid .mod-item').map(function () { return this.id.replace('modf-', ''); }).get();
    if (!ids.length) return;
    $.post('../api/foto_moderate.php', { action: 'reorder', galerie_id: $('#agModGid').val(), ids: ids.join(','), csrf_token: CSRF }, function (r) {
      if (r.success) { msvToast('Reihenfolge gespeichert', 'success'); }
      else { msvToast(r.message, 'error'); }
    }, 'json').fail(function () { msvToast('Fehler beim Sortieren', 'error'); });
  }

  // SortableJS: Reihenfolge innerhalb eines Tages UND Verschieben zwischen Tagen
  // (gemeinsame group). Wird ein Foto in einen anderen Tag gezogen, bekommt es den
  // Ziel-Tag fest zugewiesen (tag_manuell=1 -> überschreibt die EXIF-Zuordnung,
  // „Tage neu zuordnen" lässt es künftig in Ruhe).
  function initSortable() {
    if (!window.Sortable) return;
    $('#agModGrid .mod-grid').each(function () {
      Sortable.create(this, {
        group: 'agfotos',
        animation: 150, draggable: '.mod-item',
        filter: '.mod-bar, .mod-bar *, .mod-cover-btn, .mod-cover-btn *', preventOnFilter: false,
        forceFallback: true, fallbackTolerance: 4,
        ghostClass: 'mod-ghost', chosenClass: 'mod-chosen',
        onEnd: function (evt) {
          if (evt.from === evt.to) { saveOrder(); return; }
          // In anderen Tag verschoben -> Ziel-Tag zuweisen, dann Reihenfolge speichern + neu laden
          var gid = $('#agModGid').val();
          var fid = evt.item.id.replace('modf-', '');
          var ti = $(evt.to).attr('data-tagindex') || '';
          var td = $(evt.to).attr('data-tagdatum') || '';
          $.post('../api/foto_moderate.php', { action: 'move_day', galerie_id: gid, foto_id: fid, tag_index: ti, tag_datum: td, csrf_token: CSRF }, function (r) {
            if (!r.success) { msvToast(r.message, 'error'); loadModeration(gid); return; }
            var ids = $('#agModGrid .mod-item').map(function () { return this.id.replace('modf-', ''); }).get();
            $.post('../api/foto_moderate.php', { action: 'reorder', galerie_id: gid, ids: ids.join(','), csrf_token: CSRF }, function () {
              msvToast('Foto in anderen Tag verschoben', 'success');
              loadModeration(gid);
            }, 'json');
          }, 'json').fail(function () { msvToast('Fehler beim Verschieben', 'error'); loadModeration(gid); });
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
    $.post('../api/foto_moderate.php', { action: 'list', galerie_id: gid, csrf_token: CSRF }, function (r) {
      $('#agModLoading').addClass('d-none');
      if (!r.success) { msvToast(r.message, 'error'); return; }
      agCoverId = r.cover_foto_id || null;

      // Erkannte Schiesstage (Diagnose für die Tageszuordnung)
      var segMap = {};
      (r.schiesstage || []).forEach(function (s) { segMap[s.index] = s.label; });
      if (r.schiesstage && r.schiesstage.length) {
        $('#agModInfo').removeClass('warn d-none')
          .html('<strong>Erkannte Schiesstage:</strong> ' + r.schiesstage.map(function (s) { return agEsc(s.label); }).join(' · '));
      } else {
        $('#agModInfo').addClass('warn').removeClass('d-none')
          .html('<i class="bi bi-exclamation-triangle me-1"></i>Keine Schiesstage erkannt – Fotos werden nach ihrem Aufnahmedatum gruppiert. Trage die Schiesstage beim JM-Anlass ein (mit Monatsname, z.B. „27. Juni 2026") und klicke dann „Tage neu zuordnen".');
      }

      if (!r.fotos.length) { $('#agModEmpty').removeClass('d-none'); return; }

      // Uploader-Übersicht
      var upc = {};
      r.fotos.forEach(function (f) { var u = f.uploader || 'Unbekannt'; upc[u] = (upc[u] || 0) + 1; });
      var upHtml = Object.keys(upc).sort().map(function (u) {
        return '<span class="ag-uploader-chip"><i class="bi bi-person"></i> ' + agEsc(u) + ' <b>' + upc[u] + '</b></span>';
      }).join('');
      $('#agModUploaders').html('<span class="ag-uploaders-label">Hochgeladen von:</span> ' + upHtml);

      // Gruppen: ALLE Schiesstage als (evtl. leere) Drop-Ziele + Foto-Gruppen + „Weitere".
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
      ensureGroup('rest', 'Weitere Fotos (kein Tag)', '', '', '3'); // immer als Drop-Ziel
      order.sort(function (a, b) { return groups[a].sortKey < groups[b].sortKey ? -1 : (groups[a].sortKey > groups[b].sortKey ? 1 : 0); });

      var html = '<div class="text-muted small mb-2"><i class="bi bi-arrows-move me-1"></i>Tipp: Foto ziehen = Reihenfolge · in einen anderen Tag ziehen = Tag wechseln (überschreibt den Zeitstempel; Foto wird „manuell") · <i class="bi bi-star"></i> = Vorschaubild.</div>';
      order.forEach(function (key) {
        var g = groups[key];
        html += '<div class="mod-day">' + agEsc(g.label) + ' <span class="mod-day-count">(' + g.fotos.length + ')</span></div>';
        html += '<div class="mod-grid" data-tagindex="' + (g.tagindex == null ? '' : g.tagindex) + '" data-tagdatum="' + (g.tagdatum || '') + '">' + g.fotos.map(function (f) { return buildModItem(f, segMap); }).join('') + '</div>';
      });
      $('#agModGrid').html(html);
      initSortable();
    }, 'json').fail(function () { $('#agModLoading').addClass('d-none'); msvToast('Fehler beim Laden', 'error'); });
  }

  function modAction(action, id) {
    $.post('../api/foto_moderate.php', { action: action, id: id, csrf_token: CSRF }, function (r) {
      if (!r.success) { msvToast(r.message, 'error'); return; }
      if (action === 'delete') { $('#modf-' + id).fadeOut(); }
      else {
        var label = action === 'approve' ? 'approved' : 'rejected';
        var $st = $('#modf-' + id + ' .mod-state');
        $st.attr('class', 'mod-state ' + label).text(action === 'approve' ? 'Freigegeben' : 'Abgelehnt');
      }
    }, 'json').fail(function () { msvToast('Fehler', 'error'); });
  }
  $(document).on('click', '.mod-approve', function () { modAction('approve', $(this).data('id')); });
  $(document).on('click', '.mod-reject', function () { modAction('reject', $(this).data('id')); });
  $(document).on('click', '.mod-del', function () {
    var id = $(this).data('id');
    msvConfirmDelete('dieses Foto').then(function (res) { if (res.isConfirmed) modAction('delete', id); });
  });

  $('#agModApproveAll').on('click', function () {
    var gid = $('#agModGid').val();
    $.post('../api/foto_moderate.php', { action: 'approve_all', galerie_id: gid, csrf_token: CSRF }, function (r) {
      if (r.success) { msvToast(r.message, 'success'); loadModeration(gid); }
      else { msvToast(r.message, 'error'); }
    }, 'json');
  });
  $('#agModRematch').on('click', function () {
    var gid = $('#agModGid').val();
    $.post('../api/foto_moderate.php', { action: 'rematch', galerie_id: gid, csrf_token: CSRF }, function (r) {
      if (r.success) { msvToast(r.message, 'success'); loadModeration(gid); }
      else { msvToast(r.message, 'error'); }
    }, 'json');
  });

  $('#agModDeleteAll').on('click', function () {
    var gid = $('#agModGid').val();
    msvConfirmDelete('ALLE Fotos dieser Galerie').then(function (res) {
      if (!res.isConfirmed) return;
      $.post('../api/foto_moderate.php', { action: 'delete_all', galerie_id: gid, csrf_token: CSRF }, function (r) {
        if (r.success) { msvToast(r.message, 'success'); loadModeration(gid); }
        else { msvToast(r.message, 'error'); }
      }, 'json').fail(function () { msvToast('Fehler beim Löschen', 'error'); });
    });
  });

  // Klick aufs Thumbnail -> Vollbild in neuem Tab
  $(document).on('click', '#agModGrid img', function () { window.open($(this).data('full'), '_blank', 'noopener'); });

  // Vorschaubild (Cover) festlegen / entfernen
  $(document).on('click', '.mod-cover-btn', function (e) {
    e.stopPropagation();
    var $b = $(this), id = $b.data('id'), makeCover = !$b.hasClass('active');
    $.post('../api/anlass_galerie_admin.php', { action: 'set_cover', galerie_id: $('#agModGid').val(), foto_id: makeCover ? id : 0, csrf_token: CSRF }, function (r) {
      if (!r.success) { msvToast(r.message, 'error'); return; }
      msvToast(r.message, 'success');
      agCoverId = r.cover_foto_id;
      $('#agModGrid .mod-cover-btn').removeClass('active').find('i').attr('class', 'bi bi-star');
      if (agCoverId) {
        var $c = $('#agModGrid .mod-cover-btn[data-id="' + agCoverId + '"]');
        $c.addClass('active').find('i').attr('class', 'bi bi-star-fill');
      }
    }, 'json').fail(function () { msvToast('Fehler', 'error'); });
  });
})();
</script>

<?php include 'footer.inc.php'; ?>
