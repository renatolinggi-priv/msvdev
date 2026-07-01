<?php
// portal/anlaesse.php – Übersicht der freigeschalteten Foto-Galerien (Mitglieder).
// Cover antippen = Slideshow startet direkt (Overlay). Pro Karte zusätzlich ein
// Knopf „Fotos hinzufügen". Eine Detail-/Programm-Ansicht brauchen Mitglieder nicht.
// Jungschuetzen werden von der Portal-Rollenweiche ohnehin umgeleitet.
$portal_page_title = 'Foto-Galerien';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/fotogalerie.inc.php';
requireLogin();

$db = getDB();

$galerien = [];
if (fotoFeatureAktiv()) {
    try {
        $st = $db->query(
            "SELECT g.id, g.upload_offen, d.Bezeichnung AS name, d.year AS jahr,
                    (SELECT COUNT(*) FROM anlass_fotos f WHERE f.galerie_id = g.id AND f.status = 'approved') AS total,
                    COALESCE(
                      (SELECT f.id FROM anlass_fotos f WHERE f.id = g.cover_foto_id AND f.galerie_id = g.id AND f.status = 'approved'),
                      (SELECT f.id FROM anlass_fotos f WHERE f.galerie_id = g.id AND f.status = 'approved'
                         ORDER BY (f.tag_index IS NULL), f.tag_index, f.sortierung, f.aufnahme_zeit, f.id LIMIT 1)
                    ) AS cover_id
               FROM anlass_galerie g
               JOIN JMDefinition d ON d.ID = g.jmdefinition_id
              WHERE g.freigeschaltet = 1
              ORDER BY d.year DESC, d.Reihenfolge"
        );
        $galerien = $st->fetchAll();
    } catch (Throwable $e) { $galerien = []; }
}

include 'portal_header.php';
$csrf = $_SESSION['csrf_token'] ?? '';
?>

<link rel="stylesheet" href="css/foto-slideshow.css?v=<?php echo @filemtime(__DIR__ . '/css/foto-slideshow.css'); ?>">
<style>
.ga-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:0.7rem; }
.ga-card { position:relative; border:1px solid #e2e8f0; border-radius:0.6rem; overflow:hidden; background:#fff; transition:transform .15s, box-shadow .15s; cursor:pointer; }
.ga-card:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(0,0,0,0.10); }
.ga-cover { position:relative; aspect-ratio:4/3; background:#eef2f7; display:flex; align-items:center; justify-content:center; }
.ga-cover img { width:100%; height:100%; object-fit:cover; object-position:center center; display:block; }
.ga-cover .ph { color:#cbd5e1; font-size:2rem; }
.ga-play { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none; }
.ga-play i { width:46px; height:46px; border-radius:50%; background:rgba(0,0,0,0.45); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; }
.ga-body { padding:0.5rem 0.6rem; }
.ga-name { font-weight:600; font-size:0.85rem; line-height:1.2; }
.ga-meta { font-size:0.72rem; color:#64748b; margin-top:0.15rem; }
.ga-add { display:flex; align-items:center; justify-content:center; gap:0.35rem; width:100%; border:none; border-top:1px solid #eef2f7; background:#fff; color:var(--primary-color); font-weight:600; font-size:0.8rem; padding:0.5rem; cursor:pointer; }
.ga-add:hover { background:#f1f5fb; }
.ga-empty { text-align:center; color:#94a3b8; padding:2.5rem 1rem; border:1px solid #e2e8f0; border-radius:0.85rem; background:#fff; }
.ga-uploadbar { display:none; align-items:center; gap:0.6rem; background:#eef6ff; border:1px solid #cfe2ff; border-radius:0.6rem; padding:0.5rem 0.8rem; margin-bottom:1rem; font-size:0.85rem; }
@media (max-width:575.98px){ .ga-grid { grid-template-columns:1fr; gap:0.6rem; } }
</style>

<div class="container py-2">
  <div class="portal-page-header">
    <h1><i class="bi bi-images me-2"></i>Foto-Galerien</h1>
    <p class="subtitle">Bilder der Vereinsanlässe</p>
  </div>

  <div class="ga-uploadbar" id="gaUploadBar">
    <div class="spinner-border spinner-border-sm text-primary"></div>
    <span id="gaUploadStatus">Lade hoch …</span>
    <span class="text-muted ms-auto" style="font-size:0.78rem;"><i class="bi bi-phone me-1"></i>Bildschirm bleibt an – bitte App offen lassen</span>
  </div>

  <?php if (!$galerien): ?>
    <div class="ga-empty">
      <i class="bi bi-camera d-block mb-2" style="font-size:2rem;"></i>
      Es sind noch keine Galerien freigeschaltet.<br>
      <span class="small">Der Vorstand schaltet zu einem Anlass eine Galerie frei – dann kannst du hier deine Fotos hochladen.</span>
    </div>
  <?php else: ?>
    <p class="text-muted small mb-2"><i class="bi bi-play-circle me-1"></i>Auf ein Bild tippen startet die Slideshow.</p>
    <div class="ga-grid">
      <?php foreach ($galerien as $g): ?>
        <div class="ga-card" data-id="<?= (int) $g['id'] ?>">
          <div class="ga-cover">
            <?php if (!empty($g['cover_id'])): ?>
              <img src="../api/foto_serve.php?id=<?= (int) $g['cover_id'] ?>&size=full" loading="lazy" alt="">
              <span class="ga-play"><i class="bi bi-play-fill"></i></span>
            <?php else: ?>
              <i class="bi bi-camera ph"></i>
            <?php endif; ?>
          </div>
          <div class="ga-body">
            <div class="ga-name"><?= htmlspecialchars($g['name']) ?></div>
            <div class="ga-meta"><i class="bi bi-calendar3 me-1"></i><?= (int) $g['jahr'] ?> &middot; <?= (int) $g['total'] ?> Foto<?= ((int) $g['total'] === 1 ? '' : 's') ?></div>
          </div>
          <?php if (!empty($g['upload_offen'])): ?>
            <button class="ga-add" type="button"><i class="bi bi-camera-fill"></i>Fotos hinzufügen</button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<input type="file" id="gaFileInput" accept="image/jpeg,image/png,image/webp" multiple hidden>

<script>window.MSV_FOTO = { csrf: <?php echo json_encode($csrf); ?> };</script>
<script src="js/foto-slideshow.js?v=<?php echo @filemtime(__DIR__ . '/js/foto-slideshow.js'); ?>"></script>
<script>
(function () {
  var CSRF = window.MSV_FOTO.csrf;
  var uploadGid = null, uploading = false, wakeLock = null;

  function acquireWake() { try { if ('wakeLock' in navigator) navigator.wakeLock.request('screen').then(function (w) { wakeLock = w; }).catch(function () {}); } catch (e) {} }
  function releaseWake() { if (wakeLock) { try { wakeLock.release(); } catch (e) {} wakeLock = null; } }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible' && uploading) acquireWake(); });

  // Cover/Karte antippen -> Slideshow (ausser auf dem „Hinzufügen"-Knopf)
  $('.ga-card').on('click', function (e) {
    if ($(e.target).closest('.ga-add').length) return;
    startSlideshow($(this).data('id'));
  });
  $('.ga-add').on('click', function (e) {
    e.stopPropagation();
    startUpload($(this).closest('.ga-card').data('id'));
  });

  function startSlideshow(id) {
    $.getJSON('../api/foto_list.php?id=' + id, function (r) {
      if (!r.success) { msvToast(r.message || 'Fehler', 'error'); return; }
      var total = 0;
      r.gruppen.forEach(function (g) { g.fotos.forEach(function (f) { if (!f.status || f.status === 'approved') total++; }); });
      if (!total) { msvToast('Noch keine Fotos – füge welche hinzu!', 'info'); return; }
      MSVSlideshow.start(r.gruppen, 0, 0, { galerieId: id, onAddPhotos: r.can_upload ? startUpload : null });
    }).fail(function () { msvToast('Fehler beim Laden', 'error'); });
  }

  function startUpload(id) { uploadGid = id; $('#gaFileInput').trigger('click'); }

  $('#gaFileInput').on('change', function () {
    var files = Array.prototype.slice.call(this.files || []);
    this.value = '';
    if (files.length) uploadQueue(files);
  });

  function uploadQueue(files) {
    var total = files.length, done = 0, ok = 0, fail = 0, pending = 0, gid = uploadGid;
    uploading = true;
    acquireWake();
    $('#gaUploadBar').css('display', 'flex');
    function next() {
      if (!files.length) {
        uploading = false;
        releaseWake();
        $('#gaUploadBar').hide();
        if (ok)   msvToast(ok + ' Foto(s) hochgeladen.' + (pending ? ' Wartet auf Freigabe durch den Vorstand.' : ''), 'success');
        if (fail) msvToast(fail + ' Foto(s) fehlgeschlagen.', 'error');
        // Übersicht aktualisieren – aber nur, wenn keine Slideshow offen ist (sonst nicht stören)
        if (!document.querySelector('.ss-overlay.show')) { setTimeout(function () { location.reload(); }, 800); }
        return;
      }
      var file = files.shift();
      $('#gaUploadStatus').text('Lade hoch … ' + (done + 1) + ' / ' + total);
      var fd = new FormData();
      fd.append('galerie_id', gid);
      fd.append('datei', file);
      fd.append('csrf_token', CSRF);
      $.ajax({ url: '../api/foto_upload.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
        success: function (r) { if (r.success) { ok++; if (r.status === 'pending') pending++; } else { fail++; if (r.message) msvToast(r.message, 'error'); } },
        error: function () { fail++; },
        complete: function () { done++; next(); }
      });
    }
    next();
  }
})();
</script>

<?php include 'portal_footer.php'; ?>
