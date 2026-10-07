/* portal/js/foto-upload.js
 * Gemeinsamer Upload-Baustein der Foto-Galerie (Uebersicht anlaesse.php + Detail anlass.php).
 *
 * API:
 *   MSVFotoUpload.start(galerieId, files, {
 *     csrf:     Token (Pflicht),
 *     bar:      Selektor der Fortschrittsleiste (display:flex waehrend des Uploads),
 *     status:   Selektor des Text-Elements fuer «Lade hoch … 3 / 12»,
 *     onStart:  function()          – optional (z.B. FAB deaktivieren)
 *     onDone:   function(result)    – result = {ok, fail, pending, duplikate, total}
 *   })
 *   MSVFotoUpload.busy()  -> true waehrend eines Uploads
 *
 * Ein Foto pro Request, sequenziell (umgeht post_max_size, zeigt Fortschritt). Der
 * Bildschirm wird per Wake Lock wach gehalten, sonst pausieren Mobile-Browser den Upload.
 * Das Dateidatum (lastModified) geht mit -> Fallback fuer die Tageszuordnung ohne EXIF.
 */
(function () {
  'use strict';

  var uploading = false, wakeLock = null;

  function acquireWake() {
    try {
      if ('wakeLock' in navigator) {
        navigator.wakeLock.request('screen').then(function (w) { wakeLock = w; }).catch(function () {});
      }
    } catch (e) { /* nicht unterstuetzt */ }
  }
  function releaseWake() { if (wakeLock) { try { wakeLock.release(); } catch (e) {} wakeLock = null; } }
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible' && uploading) acquireWake();
  });

  function start(galerieId, files, opts) {
    opts = opts || {};
    files = Array.prototype.slice.call(files || []);
    if (!files.length || uploading) return;

    var total = files.length, done = 0, ok = 0, fail = 0, pending = 0, duplikate = 0;
    var $bar = opts.bar ? $(opts.bar) : $(), $status = opts.status ? $(opts.status) : $();

    uploading = true;
    acquireWake();
    $bar.css('display', 'flex');
    if (opts.onStart) opts.onStart();

    function finish() {
      uploading = false;
      releaseWake();
      $bar.hide();
      if (ok)        msvToast(ok + ' Foto' + (ok === 1 ? '' : 's') + ' hochgeladen.' + (pending ? ' Wartet auf Freigabe durch den Vorstand.' : ''), 'success');
      if (duplikate) msvToast(duplikate + ' Foto' + (duplikate === 1 ? ' war' : 's waren') + ' schon in der Galerie – übersprungen.', 'info');
      if (fail)      msvToast(fail + ' Foto' + (fail === 1 ? '' : 's') + ' fehlgeschlagen.', 'error');
      if (opts.onDone) opts.onDone({ ok: ok, fail: fail, pending: pending, duplikate: duplikate, total: total });
    }

    function next() {
      if (!files.length) { finish(); return; }
      var file = files.shift();
      $status.text('Lade hoch … ' + (done + 1) + ' / ' + total);

      var fd = new FormData();
      fd.append('galerie_id', galerieId);
      fd.append('datei', file);
      fd.append('csrf_token', opts.csrf || '');
      if (file.lastModified) fd.append('datei_mtime', String(file.lastModified));

      $.ajax({ url: '../api/foto_upload.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
        .done(function (r) {
          if (r && r.success) { ok++; if (r.status === 'pending') pending++; }
          else if (r && r.duplicate) { duplikate++; }
          else { fail++; if (r && r.message) msvToast(r.message, 'error'); }
        })
        .fail(function (xhr) {
          // Duplikat kommt als 409 mit JSON -> nicht als Fehler zaehlen
          var j = xhr && xhr.responseJSON;
          if (j && j.duplicate) { duplikate++; return; }
          fail++;
          if (j && j.message) msvToast(j.message, 'error');
        })
        .always(function () { done++; next(); });
    }
    next();
  }

  window.MSVFotoUpload = { start: start, busy: function () { return uploading; } };
})();
