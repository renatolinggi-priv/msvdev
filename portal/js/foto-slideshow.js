/* portal/js/foto-slideshow.js
 * Vollbild-Slideshow fuer Anlass-Galerien (Crossfade + Ken-Burns + Autoplay).
 * API: MSVSlideshow.start(gruppen, gruppenIndex, fotoIndex)
 *   gruppen = Antwort von api/foto_list.php (gruppen[].fotos[]).
 * Es werden nur freigegebene Fotos (status 'approved') abgespielt.
 */
(function () {
  'use strict';

  var INTERVAL = 5000;   // ms pro Bild
  var slides = [];       // [{id,url,title,day}]
  var idx = 0;
  var activeLayer = 0;
  var playing = true;
  var timer = null;
  var lastDay = null;
  var dayTitleTimer = null;
  var els = null;

  function build() {
    if (els) return els;
    var ov = document.createElement('div');
    ov.className = 'ss-overlay';
    ov.innerHTML =
      '<div class="ss-stage"><div class="ss-layer ss-l0"></div><div class="ss-layer ss-l1"></div></div>' +
      '<div class="ss-top">' +
        '<div class="ss-day"><span></span></div>' +
        '<div class="ss-topright"><span class="ss-counter"></span>' +
          '<button class="ss-btn ss-close" aria-label="Schliessen">&times;</button></div>' +
      '</div>' +
      '<div class="ss-daytitle"><span></span></div>' +
      '<div class="ss-controls">' +
        '<button class="ss-btn ss-add" aria-label="Fotos hinzufügen" style="display:none"><i class="bi bi-camera-fill"></i></button>' +
        '<button class="ss-btn ss-prev" aria-label="Zurück"><i class="bi bi-chevron-left"></i></button>' +
        '<button class="ss-btn ss-btn-lg ss-play" aria-label="Play/Pause"><i class="bi bi-pause-fill"></i></button>' +
        '<button class="ss-btn ss-next" aria-label="Weiter"><i class="bi bi-chevron-right"></i></button>' +
        '<button class="ss-btn ss-full" aria-label="Vollbild"><i class="bi bi-arrows-fullscreen"></i></button>' +
      '</div>';
    document.body.appendChild(ov);

    els = {
      ov: ov,
      layers: [ov.querySelector('.ss-l0'), ov.querySelector('.ss-l1')],
      daytitle: ov.querySelector('.ss-daytitle'),
      daytitleSpan: ov.querySelector('.ss-daytitle span'),
      counter: ov.querySelector('.ss-counter'),
      day: ov.querySelector('.ss-day span'),
      play: ov.querySelector('.ss-play'),
      add: ov.querySelector('.ss-add')
    };

    ov.querySelector('.ss-close').addEventListener('click', close);
    ov.querySelector('.ss-prev').addEventListener('click', function () { manual(-1); });
    ov.querySelector('.ss-next').addEventListener('click', function () { manual(1); });
    els.play.addEventListener('click', togglePlay);
    ov.querySelector('.ss-full').addEventListener('click', toggleFull);
    return els;
  }

  function show(newIdx, fade) {
    idx = (newIdx + slides.length) % slides.length;
    var slide = slides[idx];
    var target = 1 - activeLayer;
    var layer = els.layers[target];

    var img = new Image();
    img.onload = apply;
    img.onerror = apply;
    img.src = slide.url;

    function apply() {
      layer.style.backgroundImage = 'url("' + slide.url + '")';
      // Ken-Burns neu starten
      layer.classList.remove('kb');
      void layer.offsetWidth; // reflow
      layer.classList.add('kb');

      els.layers[activeLayer].classList.remove('active');
      layer.classList.add('active');
      activeLayer = target;

      // Counter + persistentes Tages-Label (oben)
      els.counter.textContent = (idx + 1) + ' / ' + slides.length;
      els.day.textContent = slide.day || '';

      // Tages-Titel beim Wechsel (und beim ersten Bild)
      if (slide.day && slide.day !== lastDay) {
        showDayTitle(slide.day);
      }
      lastDay = slide.day;

      // naechstes Bild vorladen
      var nx = slides[(idx + 1) % slides.length];
      if (nx) { var p = new Image(); p.src = nx.url; }
    }
    if (fade === false) apply();
  }

  function showDayTitle(text) {
    els.daytitleSpan.textContent = text;
    els.daytitle.classList.add('show');
    clearTimeout(dayTitleTimer);
    dayTitleTimer = setTimeout(function () { els.daytitle.classList.remove('show'); }, 2200);
  }

  function nextAuto() { show(idx + 1, true); }

  function startTimer() {
    stopTimer();
    if (playing && slides.length > 1) timer = setInterval(nextAuto, INTERVAL);
  }
  function stopTimer() { if (timer) { clearInterval(timer); timer = null; } }

  function manual(dir) { show(idx + dir, true); startTimer(); }

  function togglePlay() {
    playing = !playing;
    els.play.innerHTML = playing ? '<i class="bi bi-pause-fill"></i>' : '<i class="bi bi-play-fill"></i>';
    startTimer();
  }

  function toggleFull() {
    var ov = els.ov;
    if (!document.fullscreenElement) {
      (ov.requestFullscreen || ov.webkitRequestFullscreen || function () {}).call(ov);
    } else {
      (document.exitFullscreen || document.webkitExitFullscreen || function () {}).call(document);
    }
  }

  function onKey(e) {
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowRight') manual(1);
    else if (e.key === 'ArrowLeft') manual(-1);
    else if (e.key === ' ') { e.preventDefault(); togglePlay(); }
    else if (e.key === 'f' || e.key === 'F') toggleFull();
  }

  function close() {
    stopTimer();
    clearTimeout(dayTitleTimer);
    document.removeEventListener('keydown', onKey);
    if (document.fullscreenElement) { (document.exitFullscreen || function () {}).call(document); }
    els.ov.classList.remove('show');
    document.body.style.overflow = '';
    // Speicher freigeben
    setTimeout(function () {
      if (els) { els.layers[0].style.backgroundImage = ''; els.layers[1].style.backgroundImage = ''; }
    }, 350);
  }

  function start(gruppen, gi, fi, opts) {
    build();
    // Nur freigegebene Fotos
    slides = [];
    var clickedId = null;
    (gruppen || []).forEach(function (grp) {
      grp.fotos.forEach(function (f) {
        if (f.status && f.status !== 'approved') return;
        slides.push({ id: f.id, url: f.full_url, title: f.titel, day: grp.label });
      });
    });
    if (gruppen && gruppen[gi] && gruppen[gi].fotos[fi]) clickedId = gruppen[gi].fotos[fi].id;

    if (!slides.length) { return; }

    var startIdx = 0;
    if (clickedId != null) {
      for (var i = 0; i < slides.length; i++) { if (slides[i].id === clickedId) { startIdx = i; break; } }
    }

    lastDay = null;
    activeLayer = 0;
    els.layers[0].classList.remove('active');
    els.layers[1].classList.remove('active');
    playing = true;
    els.play.innerHTML = '<i class="bi bi-pause-fill"></i>';

    // Optionaler „Fotos hinzufügen"-Knopf (vom Aufrufer übergeben -> pausiert + Callback)
    var addCb = opts && opts.onAddPhotos;
    if (els.add) {
      els.add.style.display = addCb ? '' : 'none';
      els.add.onclick = addCb ? function () {
        if (playing) { playing = false; els.play.innerHTML = '<i class="bi bi-play-fill"></i>'; stopTimer(); }
        addCb(opts.galerieId);
      } : null;
    }

    els.ov.classList.add('show');
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKey);

    show(startIdx, false);
    startTimer();
  }

  window.MSVSlideshow = { start: start };
})();
