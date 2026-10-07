/* portal/js/foto-slideshow.js
 * Vollbild-Slideshow fuer Anlass-Galerien (Crossfade + Ken-Burns + Autoplay).
 * API: MSVSlideshow.start(gruppen, gruppenIndex, fotoIndex, opts)
 *   gruppen = Antwort von api/foto_list.php (gruppen[].fotos[]).
 *   opts.galerieId + opts.onAddPhotos -> «Fotos hinzufügen»-Knopf im Player
 * Es werden nur freigegebene Fotos (status 'approved') abgespielt.
 *
 * Bedienung: Pfeile/Wischen = Bild wechseln, Leertaste/Play = Pause, Tippen auf das Bild
 * blendet die Steuerung aus/ein, Tempo-Knopf wechselt das Intervall (wird im Browser gemerkt),
 * F/Vollbild-Knopf (nur wo der Browser Vollbild fuer Elemente kann – iPhone nicht).
 */
(function () {
  'use strict';

  var INTERVALS = [3000, 5000, 8000, 12000];
  var STORAGE_KEY = 'msv_ss_interval';
  var interval = loadInterval();
  var slides = [];       // [{id,url,title,day}]
  var idx = 0;
  var activeLayer = 0;
  var playing = true;
  var timer = null;
  var lastDay = null;
  var dayTitleTimer = null;
  var els = null;
  var errorStreak = 0;   // aufeinanderfolgende Ladefehler -> Endlosschleife verhindern
  var showToken = 0;     // verwirft veraltete onload-Callbacks bei schnellem Weiterklicken

  function loadInterval() {
    try {
      var v = parseInt(localStorage.getItem(STORAGE_KEY), 10);
      if (INTERVALS.indexOf(v) >= 0) return v;
    } catch (e) {}
    return 5000;
  }
  function saveInterval(v) { try { localStorage.setItem(STORAGE_KEY, String(v)); } catch (e) {} }

  // Bildgroesse nach Anzeige: auf Handys reicht die Medium-Version (1280 px), auf
  // Beamer/Desktop/Tablet die volle (2560 px). Massstab = groesste Bildschirmkante in Geraetepixeln.
  function pickUrl(f) {
    var px = Math.max(window.screen.width || 0, window.screen.height || 0) * (window.devicePixelRatio || 1);
    return (px <= 1600 && f.medium_url) ? f.medium_url : f.full_url;
  }

  function fullscreenAvailable() {
    var ov = els.ov;
    return !!(document.fullscreenEnabled || document.webkitFullscreenEnabled) &&
           !!(ov.requestFullscreen || ov.webkitRequestFullscreen);
  }

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
      '<div class="ss-caption"><span></span></div>' +
      '<div class="ss-controls">' +
        '<button class="ss-btn ss-add" aria-label="Fotos hinzufügen" style="display:none"><i class="bi bi-camera-fill"></i></button>' +
        '<button class="ss-btn ss-prev" aria-label="Zurück"><i class="bi bi-chevron-left"></i></button>' +
        '<button class="ss-btn ss-btn-lg ss-play" aria-label="Play/Pause"><i class="bi bi-pause-fill"></i></button>' +
        '<button class="ss-btn ss-next" aria-label="Weiter"><i class="bi bi-chevron-right"></i></button>' +
        '<button class="ss-btn ss-speed" aria-label="Tempo"><span></span></button>' +
        '<button class="ss-btn ss-full" aria-label="Vollbild"><i class="bi bi-arrows-fullscreen"></i></button>' +
      '</div>';
    document.body.appendChild(ov);

    els = {
      ov: ov,
      stage: ov.querySelector('.ss-stage'),
      layers: [ov.querySelector('.ss-l0'), ov.querySelector('.ss-l1')],
      daytitle: ov.querySelector('.ss-daytitle'),
      daytitleSpan: ov.querySelector('.ss-daytitle span'),
      caption: ov.querySelector('.ss-caption'),
      captionSpan: ov.querySelector('.ss-caption span'),
      counter: ov.querySelector('.ss-counter'),
      day: ov.querySelector('.ss-day span'),
      play: ov.querySelector('.ss-play'),
      speed: ov.querySelector('.ss-speed'),
      speedLabel: ov.querySelector('.ss-speed span'),
      full: ov.querySelector('.ss-full'),
      add: ov.querySelector('.ss-add')
    };

    ov.querySelector('.ss-close').addEventListener('click', close);
    ov.querySelector('.ss-prev').addEventListener('click', function () { manual(-1); });
    ov.querySelector('.ss-next').addEventListener('click', function () { manual(1); });
    els.play.addEventListener('click', togglePlay);
    els.speed.addEventListener('click', cycleSpeed);
    els.full.addEventListener('click', toggleFull);
    if (!fullscreenAvailable()) els.full.style.display = 'none';
    updateSpeedLabel();

    // Tippen/Klicken auf das Bild: Steuerung ein-/ausblenden. Wischen: Bild wechseln.
    var tx = 0, ty = 0, t0 = 0, moved = false;
    els.stage.addEventListener('touchstart', function (e) {
      if (e.touches.length !== 1) return;
      tx = e.touches[0].clientX; ty = e.touches[0].clientY; t0 = Date.now(); moved = false;
    }, { passive: true });
    els.stage.addEventListener('touchmove', function (e) {
      if (Math.abs(e.touches[0].clientX - tx) > 10 || Math.abs(e.touches[0].clientY - ty) > 10) moved = true;
    }, { passive: true });
    els.stage.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - tx, dy = e.changedTouches[0].clientY - ty;
      if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.3) { manual(dx < 0 ? 1 : -1); return; }
      if (!moved && Date.now() - t0 < 400) toggleUi();
    });
    // Maus/Trackpad: Klick auf das Bild = Steuerung ein/aus (Touch loest zusaetzlich click aus -> entprellen)
    var lastTouchEnd = 0;
    els.stage.addEventListener('touchend', function () { lastTouchEnd = Date.now(); });
    els.stage.addEventListener('click', function () { if (Date.now() - lastTouchEnd > 500) toggleUi(); });
    return els;
  }

  function toggleUi() { els.ov.classList.toggle('ss-ui-hidden'); }
  function showUi() { els.ov.classList.remove('ss-ui-hidden'); }

  function updateSpeedLabel() { els.speedLabel.textContent = (interval / 1000) + ' s'; }
  function cycleSpeed() {
    var i = INTERVALS.indexOf(interval);
    interval = INTERVALS[(i + 1) % INTERVALS.length];
    saveInterval(interval);
    updateSpeedLabel();
    startTimer();
  }

  function show(newIdx, fade) {
    if (!slides.length) return;
    idx = (newIdx + slides.length) % slides.length;
    var slide = slides[idx];
    var target = 1 - activeLayer;
    var layer = els.layers[target];
    var token = ++showToken;

    var img = new Image();
    img.onload = function () { if (token !== showToken) return; errorStreak = 0; apply(); };
    img.onerror = function () {
      if (token !== showToken) return;
      // Kaputtes/geloeschtes Bild ueberspringen statt schwarze Flaeche zeigen
      errorStreak++;
      if (errorStreak < slides.length) { show(idx + 1, fade); }
      else { errorStreak = 0; els.counter.textContent = 'Bilder nicht ladbar'; }
    };
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

      // Bildunterschrift (unten, nur wenn vorhanden)
      els.captionSpan.textContent = slide.title || '';
      els.caption.classList.toggle('show', !!slide.title);

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
    if (playing && slides.length > 1) timer = setInterval(nextAuto, interval);
  }
  function stopTimer() { if (timer) { clearInterval(timer); timer = null; } }

  function manual(dir) { show(idx + dir, true); startTimer(); }

  function setPlaying(on) {
    playing = on;
    els.play.innerHTML = playing ? '<i class="bi bi-pause-fill"></i>' : '<i class="bi bi-play-fill"></i>';
    startTimer();
  }
  function togglePlay() { setPlaying(!playing); }

  function toggleFull() {
    var ov = els.ov;
    if (!document.fullscreenElement && !document.webkitFullscreenElement) {
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
    else if (e.key === 'f' || e.key === 'F') { if (fullscreenAvailable()) toggleFull(); }
    else if (e.key === 'h' || e.key === 'H') toggleUi();
    else if (e.key === '+' ) cycleSpeed();
  }

  function close() {
    stopTimer();
    clearTimeout(dayTitleTimer);
    showToken++;
    document.removeEventListener('keydown', onKey);
    if (document.fullscreenElement || document.webkitFullscreenElement) {
      (document.exitFullscreen || document.webkitExitFullscreen || function () {}).call(document);
    }
    els.ov.classList.remove('show');
    showUi();
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
        slides.push({ id: f.id, url: pickUrl(f), title: f.titel, day: grp.label });
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
    errorStreak = 0;
    els.layers[0].classList.remove('active');
    els.layers[1].classList.remove('active');
    els.caption.classList.remove('show');
    showUi();
    playing = true;
    els.play.innerHTML = '<i class="bi bi-pause-fill"></i>';

    // Optionaler „Fotos hinzufügen"-Knopf (vom Aufrufer übergeben -> pausiert + Callback)
    var addCb = opts && opts.onAddPhotos;
    if (els.add) {
      els.add.style.display = addCb ? '' : 'none';
      els.add.onclick = addCb ? function () {
        if (playing) setPlaying(false);
        addCb(opts.galerieId);
      } : null;
    }

    els.ov.classList.add('show');
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKey);

    show(startIdx, false);
    startTimer();
  }

  window.MSVSlideshow = { start: start, close: close };
})();
