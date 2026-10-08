/**
 * Slide-Panels (.hybrid-edit-panel u.a., Partial inc/partials/side_panel.inc.php)
 * für Tastatur und Screenreader: Die Seiten öffnen/schliessen ihre Panels weiterhin
 * selbst über die Klasse .open; dieses Skript beobachtet das und
 *  - setzt beim Öffnen den Fokus ins Panel (auf das Panel selbst, damit auf dem Handy
 *    keine Tastatur aufspringt), sofern die Seite nicht schon ein Feld fokussiert hat,
 *  - hält Tab/Shift+Tab im offenen Panel,
 *  - gibt beim Schliessen den Fokus an das auslösende Element zurück.
 * Geschlossene Panels sind per CSS visibility:hidden und damit nicht mehr per Tab erreichbar.
 */
(function () {
  'use strict';

  const SEL = '.hybrid-edit-panel, .mv-edit-panel, .anlass-panel, .add-sieger-panel, .side-panel';
  const FOKUSSIERBAR = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), '
    + 'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
  const ausloeser = new WeakMap();

  function offenesPanel() {
    const offen = document.querySelectorAll(SEL.split(',').map(s => s.trim() + '.open').join(','));
    return offen.length ? offen[offen.length - 1] : null;
  }

  function sichtbareFelder(panel) {
    return Array.from(panel.querySelectorAll(FOKUSSIERBAR))
      .filter(el => el.offsetParent !== null || el.getClientRects().length > 0);
  }

  function geoeffnet(panel) {
    const vorher = document.activeElement;
    if (vorher && !panel.contains(vorher)) ausloeser.set(panel, vorher);
    if (!panel.hasAttribute('tabindex')) panel.setAttribute('tabindex', '-1');
    // Kurz warten: viele Seiten fokussieren nach dem Öffnen selbst ein Feld.
    setTimeout(function () {
      if (panel.classList.contains('open') && !panel.contains(document.activeElement)) {
        panel.focus({ preventScroll: true });
      }
    }, 60);
  }

  function geschlossen(panel) {
    const ziel = ausloeser.get(panel);
    ausloeser.delete(panel);
    const fokusWarDrin = panel.contains(document.activeElement) || document.activeElement === document.body;
    if (fokusWarDrin && ziel && ziel.isConnected && typeof ziel.focus === 'function') {
      ziel.focus({ preventScroll: true });
    }
  }

  function start() {
    const zustand = new WeakMap();
    document.querySelectorAll(SEL).forEach(p => zustand.set(p, p.classList.contains('open')));

    new MutationObserver(function (mutationen) {
      mutationen.forEach(function (m) {
        const p = m.target;
        if (!(p instanceof Element) || !p.matches(SEL)) return;
        const offen = p.classList.contains('open');
        if (zustand.get(p) === offen) return;
        zustand.set(p, offen);
        if (offen) geoeffnet(p); else geschlossen(p);
      });
    }).observe(document.body, { subtree: true, attributes: true, attributeFilter: ['class'] });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      const panel = offenesPanel();
      if (!panel) return;
      // Dialoge über dem Panel (SweetAlert, Bootstrap-Modal, Hilfe) führen den Fokus selbst.
      if (document.querySelector('.swal2-container, .modal.show')) return;
      const felder = sichtbareFelder(panel);
      if (!felder.length) { e.preventDefault(); panel.focus(); return; }
      const erstes = felder[0];
      const letztes = felder[felder.length - 1];
      const aktiv = document.activeElement;
      if (!panel.contains(aktiv)) { e.preventDefault(); erstes.focus(); return; }
      if (e.shiftKey && (aktiv === erstes || aktiv === panel)) { e.preventDefault(); letztes.focus(); }
      else if (!e.shiftKey && aktiv === letztes) { e.preventDefault(); erstes.focus(); }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
