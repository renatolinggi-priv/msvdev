/**
 * Zentrale Jahresauswahl (Admin). Gegenstück: inc/jahr.inc.php
 *
 *   const jahr = msvJahrAuswahl('#yearSelect');                 // füllt und wählt das gemerkte Jahr
 *   msvJahrAuswahl(el, { plus1: true });                         // zusätzlich das Folgejahr (Planung)
 *   msvJahrAuswahl(el, { jahr: 2024 });                          // feste Vorauswahl (z.B. ?year=)
 *   <select data-msv-jahr> … </select>                           // serverseitig gefüllt: nur merken + Hinweis
 *
 * Angeboten werden alle Jahre mit Daten (window.MSV_JAHRE aus header.inc.php) plus das laufende.
 * Das gewählte Jahr gilt seitenübergreifend (Cookie msv_jahr, 4 Stunden). Steht die Auswahl nicht
 * auf dem laufenden Jahr, erscheint daneben ein Hinweis «Archiv 2024» bzw. «Planung 2027» mit
 * einem Knopf zurück zum laufenden Jahr – damit niemand unbemerkt im falschen Jahr erfasst.
 */
(function () {
  'use strict';

  const AKTUELL = new Date().getFullYear();
  const COOKIE = 'msv_jahr';

  function gemerkt() {
    const m = document.cookie.match(/(?:^|;\s*)msv_jahr=(\d{4})/);
    return m ? parseInt(m[1], 10) : null;
  }

  function merken(jahr) {
    const j = parseInt(jahr, 10);
    if (!j) return;
    if (j === AKTUELL) {
      document.cookie = COOKIE + '=; path=/; max-age=0; SameSite=Lax';
    } else {
      document.cookie = COOKIE + '=' + j + '; path=/; max-age=' + (4 * 3600) + '; SameSite=Lax';
    }
  }

  function element(sel) {
    if (!sel) return null;
    if (sel.jquery) return sel[0] || null;
    if (typeof sel === 'string') return document.querySelector(sel);
    return sel;
  }

  function hinweis(el) {
    const j = parseInt(el.value, 10);
    let pill = el.parentNode ? el.parentNode.querySelector('.msv-jahr-hinweis[data-fuer="' + (el.id || '') + '"]') : null;
    if (!j || j === AKTUELL) {
      if (pill) pill.remove();
      return;
    }
    if (!pill) {
      pill = document.createElement('span');
      pill.className = 'msv-jahr-hinweis';
      pill.setAttribute('data-fuer', el.id || '');
      pill.setAttribute('role', 'status');
      el.insertAdjacentElement('afterend', pill);
    }
    const archiv = j < AKTUELL;
    pill.classList.toggle('is-archiv', archiv);
    pill.classList.toggle('is-planung', !archiv);
    pill.innerHTML = '';
    const text = document.createElement('span');
    text.textContent = (archiv ? 'Archiv ' : 'Planung ') + j;
    pill.appendChild(text);
    if ([...el.options].some(o => parseInt(o.value, 10) === AKTUELL)) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = 'zu ' + AKTUELL;
      btn.setAttribute('aria-label', 'Zum laufenden Jahr ' + AKTUELL + ' wechseln');
      btn.addEventListener('click', function () {
        el.value = String(AKTUELL);
        el.dispatchEvent(new Event('change', { bubbles: true }));
      });
      pill.appendChild(btn);
    }
  }

  function verbinden(el) {
    if (el.dataset.msvJahrAktiv) return;
    el.dataset.msvJahrAktiv = '1';
    el.addEventListener('change', function () { merken(el.value); hinweis(el); });
  }

  window.msvJahrGewaehlt = function () {
    return gemerkt() || AKTUELL;
  };

  window.msvJahrAuswahl = function (sel, opts) {
    opts = opts || {};
    const el = element(sel);
    if (!el) return AKTUELL;

    let jahre = Array.isArray(window.MSV_JAHRE) ? window.MSV_JAHRE.map(Number) : [];
    jahre.push(AKTUELL);
    if (opts.plus1) jahre.push(AKTUELL + 1);
    jahre = [...new Set(jahre.filter(Boolean))].sort((a, b) => b - a);

    el.innerHTML = '';
    jahre.forEach(function (j) { el.add(new Option(String(j), String(j))); });

    let wahl = parseInt(opts.jahr, 10) || gemerkt() || AKTUELL;
    if (!jahre.includes(wahl)) wahl = AKTUELL;
    el.value = String(wahl);

    verbinden(el);
    hinweis(el);
    return wahl;
  };

  // Serverseitig gefüllte Auswahlen: nur merken und Hinweis zeigen
  function start() {
    document.querySelectorAll('select[data-msv-jahr]').forEach(function (el) {
      verbinden(el);
      hinweis(el);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
