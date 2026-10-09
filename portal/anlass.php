<?php
// portal/anlass.php – eine Foto-Galerie: Tagesweise Grid, Upload, Slideshow.
// Daten werden per api/foto_list.php geladen (gleiche Quelle wie die Slideshow).
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/fotogalerie.inc.php';
requireLogin();

$db  = getDB();
$gid = (int) ($_GET['id'] ?? 0);
$g   = $gid > 0 ? fotoGalerieLaden($db, $gid) : null;

if (!$g || (empty($g['freigeschaltet']) && !isVorstand()) || !fotoFeatureAktiv()) {
    header('Location: anlaesse.php');
    exit;
}

$portal_page_title = $g['anlass_name'];
include 'portal_header.php';
$csrf = $_SESSION['csrf_token'] ?? '';
?>

<link rel="stylesheet" href="css/foto-slideshow.css?v=<?php echo @filemtime(__DIR__ . '/css/foto-slideshow.css'); ?>">
<style>
.an-head { display:flex; justify-content:space-between; align-items:flex-start; gap:0.75rem; flex-wrap:wrap; margin-bottom:0.85rem; }
.an-title { margin:0; font-weight:700; font-size:1.15rem; line-height:1.2; }  /* h1: Browser-Abstand und -Grösse neutralisiert */
.an-sub { font-size:0.82rem; color:#64748b; margin-top:0.2rem; }
.an-actions { display:flex; gap:0.4rem; flex-wrap:wrap; }
.an-desc { font-size:0.88rem; color:#475569; background:#f8fafc; border:1px solid #eef2f7; border-radius:0.6rem; padding:0.6rem 0.8rem; margin-bottom:1rem; }
.an-daygroup { margin-bottom:1.4rem; }
.an-daytitle { font-size:0.8rem; font-weight:700; color:#3b5998; text-transform:uppercase; letter-spacing:0.03em; margin-bottom:0.5rem; padding-bottom:0.3rem; border-bottom:2px solid #eef2f7; }
.an-photos { display:grid; grid-template-columns:repeat(auto-fill,minmax(105px,1fr)); gap:0.45rem; }
@media (max-width:575.98px){ .an-photos { grid-template-columns:repeat(3,1fr); gap:0.35rem; } }
.an-photo { position:relative; aspect-ratio:1/1; border-radius:0.5rem; overflow:hidden; background:#eef2f7; cursor:pointer; }
.an-photo img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .2s; }
.an-photo:hover img { transform:scale(1.04); }
.an-photo .an-pending { position:absolute; top:4px; left:4px; font-size:0.6rem; font-weight:700; background:#fff3cd; color:#8a6d3b; padding:0.1rem 0.4rem; border-radius:0.4rem; z-index:1; }
.an-photo .an-rejected { position:absolute; top:4px; left:4px; font-size:0.6rem; font-weight:700; background:#fde2e2; color:#c0392b; padding:0.1rem 0.4rem; border-radius:0.4rem; z-index:1; }
.an-photo.is-rejected img { opacity:0.45; filter:grayscale(60%); }
.an-photo .an-del, .an-photo .an-titel { position:absolute; top:4px; width:24px; height:24px; border:none; border-radius:50%; background:rgba(0,0,0,0.55); color:#fff; font-size:0.7rem; display:none; align-items:center; justify-content:center; z-index:2; }
.an-photo .an-del { right:4px; }
.an-photo .an-titel { right:32px; }
.an-photo:hover .an-del, .an-photo:hover .an-titel, .an-photo.mine .an-del, .an-photo.mine .an-titel { display:flex; }
@media (hover:hover) { .an-photo.mine .an-del, .an-photo.mine .an-titel { display:none; } .an-photo.mine:hover .an-del, .an-photo.mine:hover .an-titel { display:flex; } }
/* Foto-Knopf (Tastatur): sieht aus wie bisher das Bild allein */
.an-photo .an-open { display:block; width:100%; height:100%; padding:0; margin:0; border:0; border-radius:0; background:none; color:inherit; font:inherit; cursor:pointer; -webkit-appearance:none; appearance:none; }
.an-photo .an-open:focus-visible { outline:3px solid var(--primary-color); outline-offset:-3px; }
/* Tastatur: Stift und Papierkorb erscheinen, sobald die Kachel den Tastaturfokus hat (sonst nicht erreichbar) */
.an-photo:has(:focus-visible) .an-del, .an-photo:has(:focus-visible) .an-titel { display:flex; }
.an-photo .an-cap { position:absolute; left:0; right:0; bottom:0; padding:0.2rem 0.35rem; font-size:0.62rem; line-height:1.25; color:#fff; background:linear-gradient(to top, rgba(0,0,0,0.65), rgba(0,0,0,0)); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; pointer-events:none; }
.an-empty { text-align:center; color:#94a3b8; padding:2.5rem 1rem; border:1px solid #e2e8f0; border-radius:0.85rem; background:#fff; }
.an-uploadbar { display:none; align-items:center; gap:0.6rem; background:#eef6ff; border:1px solid #cfe2ff; border-radius:0.6rem; padding:0.5rem 0.8rem; margin-bottom:1rem; font-size:0.85rem; }
/* Schwebender Upload-Button (FAB) */
.an-upload-fab {
  position:fixed; right:1.5rem; bottom:1.5rem; z-index:1045;
  width:56px; height:56px; border-radius:50%; border:none;
  background:var(--primary-color); color:#fff; font-size:1.4rem;
  box-shadow:0 6px 18px rgba(59,89,152,0.45);
  display:flex; align-items:center; justify-content:center;
  transition:transform .15s, background .15s;
}
.an-upload-fab:hover, .an-upload-fab:focus { background:#2d4373; color:#fff; transform:scale(1.06); }
.an-upload-fab .spinner-border { width:1.4rem; height:1.4rem; }
@media (max-width: 767.98px) {
  /* über dem Dashboard-Zurück-FAB stapeln (dieser sitzt unten rechts bei 1.25rem) */
  .an-upload-fab { right:1.25rem; bottom:calc(1.25rem + env(safe-area-inset-bottom, 0px) + 60px); }
}
</style>

<div class="container py-2">

  <a href="anlaesse.php" class="text-decoration-none small text-muted d-inline-block mb-2"><i class="bi bi-arrow-left me-1"></i>Alle Galerien</a>

  <div class="an-head">
    <div>
      <h1 class="an-title"><i class="bi bi-images me-2" aria-hidden="true"></i><?= htmlspecialchars($g['anlass_name']) ?></h1>
      <div class="an-sub">
        <i class="bi bi-calendar3 me-1"></i><?= (int) $g['jahr'] ?>
        <?php if (!empty($g['Adresse'])): ?> &middot; <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($g['Adresse']) ?><?php endif; ?>
      </div>
    </div>
    <div class="an-actions">
      <?php if (!empty($g['programm_dateipfad'])): ?>
        <a class="btn btn-sm btn-outline-club" href="../api/foto_serve.php?programm=<?= (int) $g['id'] ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>Programm</a>
      <?php endif; ?>
      <button type="button" class="btn btn-sm btn-club" id="anSlideshowBtn" disabled><i class="bi bi-play-circle me-1"></i>Slideshow</button>
      <input type="file" id="anFileInput" accept="<?= htmlspecialchars(fotoAcceptAttribut()) ?>" multiple hidden>
    </div>
  </div>

  <?php if (!empty($g['beschreibung'])): ?>
    <div class="an-desc"><?= nl2br(htmlspecialchars($g['beschreibung'])) ?></div>
  <?php endif; ?>

  <div class="an-uploadbar" id="anUploadBar">
    <div class="spinner-border spinner-border-sm text-primary"></div>
    <span id="anUploadStatus">Lade hoch …</span>
    <span class="text-muted ms-auto" style="font-size:0.8rem;"><i class="bi bi-phone me-1"></i>Bildschirm bleibt an – bitte App offen lassen</span>
  </div>

  <div id="anLoading" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
  <div id="anContent"></div>
</div>

<button type="button" class="an-upload-fab d-none" id="anUploadFab" aria-label="Fotos hochladen" title="Fotos hochladen">
  <i class="bi bi-camera-fill" aria-hidden="true"></i>
</button>

<script>
window.MSV_GALLERY = {
  id: <?= (int) $g['id'] ?>,
  csrf: <?= json_encode($csrf) ?>
};
</script>
<script src="js/foto-slideshow.js?v=<?php echo @filemtime(__DIR__ . '/js/foto-slideshow.js'); ?>"></script>
<script src="js/foto-upload.js?v=<?php echo @filemtime(__DIR__ . '/js/foto-upload.js'); ?>"></script>
<script>
(function () {
  var GID = window.MSV_GALLERY.id, CSRF = window.MSV_GALLERY.csrf;
  var data = null;
  var esc = msvEsc;

  // Lösch-Bestätigung mit eigenem Text (zentrales kompaktes Design aus msv-toast.js)
  function anConfirm(html, confirmText) {
    return msvConfirmDelete('', { html: html, confirmText: confirmText });
  }

  function load() {
    $('#anLoading').show();
    $.getJSON('../api/foto_list.php?id=' + GID, function (r) {
      $('#anLoading').hide();
      if (!r.success) { $('#anContent').html('<div class="an-empty">' + esc(r.message || 'Fehler') + '</div>'); return; }
      data = r;
      $('#anUploadFab').toggleClass('d-none', !r.can_upload);
      render();
    }).fail(function () { $('#anLoading').hide(); $('#anContent').html('<div class="an-empty">Fehler beim Laden.</div>'); });
  }

  function totalPhotos() {
    if (!data) return 0;
    return data.gruppen.reduce(function (n, g) { return n + g.fotos.length; }, 0);
  }

  function render() {
    var n = totalPhotos();
    $('#anSlideshowBtn').prop('disabled', n === 0);

    if (n === 0) {
      $('#anContent').html('<div class="an-empty"><i class="bi bi-camera d-block mb-2" style="font-size:2rem;"></i>Noch keine Fotos.' +
        (data.can_upload ? '<br><span class="small">Sei der Erste und lade deine Fotos hoch!</span>' : '') + '</div>');
      return;
    }

    var html = '';
    data.gruppen.forEach(function (grp, gi) {
      html += '<div class="an-daygroup"><div class="an-daytitle">' + esc(grp.label) + '</div><div class="an-photos">';
      grp.fotos.forEach(function (f, fi) {
        html += '<div class="an-photo' + (f.status === 'rejected' ? ' is-rejected' : '') + (f.mine ? ' mine' : '') + '" data-gi="' + gi + '" data-fi="' + fi + '">' +
          (f.status === 'pending' ? '<span class="an-pending">wartet auf Freigabe</span>' : '') +
          (f.status === 'rejected' ? '<span class="an-rejected">abgelehnt</span>' : '') +
          '<button type="button" class="an-open" aria-label="Foto öffnen"><img src="' + f.thumb_url + '" loading="lazy" alt=""></button>' +
          (f.titel ? '<span class="an-cap">' + esc(f.titel) + '</span>' : '') +
          (f.mine ? '<button type="button" class="an-titel" data-id="' + f.id + '" data-titel="' + esc(f.titel || '') + '" title="Bildunterschrift" aria-label="Bildunterschrift bearbeiten"><i class="bi bi-pencil" aria-hidden="true"></i></button>' +
                    '<button type="button" class="an-del" data-id="' + f.id + '" title="Löschen" aria-label="Foto löschen"><i class="bi bi-trash" aria-hidden="true"></i></button>' : '') +
          '</div>';
      });
      html += '</div></div>';
    });
    $('#anContent').html(html);
  }

  // Foto anklicken (oder .an-open per Tastatur) -> Slideshow ab diesem Bild (nur freigegebene Bilder in der Show)
  $('#anContent').on('click', '.an-photo', function (e) {
    if ($(e.target).closest('.an-del, .an-titel').length) return;
    var gi = +$(this).data('gi'), fi = +$(this).data('fi');
    MSVSlideshow.start(data.gruppen, gi, fi);
  });

  $('#anSlideshowBtn').on('click', function () {
    if (totalPhotos() > 0) MSVSlideshow.start(data.gruppen, 0, 0);
  });

  // Eigenes Foto loeschen
  $('#anContent').on('click', '.an-del', function (e) {
    e.stopPropagation();
    var id = $(this).data('id');
    anConfirm('Möchtest du <strong>dieses Foto</strong> wirklich löschen?').then(function (res) {
      if (!res.isConfirmed) return;
      $.post('../api/foto_delete.php', { id: id, csrf_token: CSRF }, function (r) {
        if (r.success) { msvToast(r.message, 'success'); load(); }
        else { msvToast(r.message, 'error'); }
      }, 'json');
    });
  });

  // Bildunterschrift des eigenen Fotos setzen/ändern
  $('#anContent').on('click', '.an-titel', function (e) {
    e.stopPropagation();
    var id = $(this).data('id'), alt = $(this).attr('data-titel') || '';
    msvSwal.fire({
      title: 'Bildunterschrift', input: 'text', inputValue: alt, inputAttributes: { maxlength: 120 },
      inputPlaceholder: 'z.B. Siegerehrung am Sonntag', showCancelButton: true,
      confirmButtonText: 'Speichern', cancelButtonText: 'Abbrechen'
    }).then(function (res) {
      if (!res.isConfirmed) return;
      msvPost('../api/foto_titel.php', { id: id, titel: res.value || '' }, function (r) {
        msvToast(r.message, 'success'); load();
      }, { csrf: CSRF, failMsg: 'Bildunterschrift konnte nicht gespeichert werden' });
    });
  });

  // ---- Upload (gemeinsamer Baustein js/foto-upload.js) ----
  $('#anUploadFab').on('click', function () { $('#anFileInput').trigger('click'); });

  $('#anFileInput').on('change', function () {
    var files = Array.prototype.slice.call(this.files || []);
    this.value = '';
    if (!files.length) return;
    MSVFotoUpload.start(GID, files, {
      csrf: CSRF, bar: '#anUploadBar', status: '#anUploadStatus',
      onStart: function () { $('#anUploadFab').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>'); },
      onDone:  function () { $('#anUploadFab').prop('disabled', false).html('<i class="bi bi-camera-fill"></i>'); load(); }
    });
  });

  load();
})();
</script>

<?php include 'portal_footer.php'; ?>
