/**
 * Slide-Panels (.hybrid-edit-panel u.a., Partial inc/partials/side_panel.inc.php)
 * für Tastatur und Screenreader: Die Seiten öffnen/schliessen ihre Panels weiterhin
 * selbst über die Klasse .open; dieses Skript beobachtet das und
 *  - setzt beim Öffnen den Fokus ins Panel (auf das Panel selbst, damit auf dem Handy
 *    keine Tastatur aufspringt), sofern die Seite nicht schon ein Feld fokussiert hat,
 *  - hält Tab/Shift+Tab im offenen Panel,
 *  - gibt beim Schliessen den Fokus an das auslösende Element zurück.
 * Geschlossene Panels sind per CSS visibility:hidden und damit nicht mehr per Tab erreichbar.
 * Für Screenreader ist jedes Panel ein modaler Dialog, benannt nach seinem Titel.
 *
 * Ausserdem: Tabellen mit [data-zeilen-tastatur] lassen sich mit den Pfeiltasten durchgehen (Zeile
 * tr.ui-markiert, Ansage über eine Live-Region), Enter oder Leertaste öffnet die Zeile wie ein Klick.
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

  // Rolle, modal und Name (aus dem Titel im Panelkopf) für Screenreader
  function dialogRolle(panel) {
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    if (panel.hasAttribute('aria-labelledby') || panel.hasAttribute('aria-label')) return;
    const titel = panel.querySelector('.panel-header h6, .panel-header h5, .panel-header h4, .panel-header [id$="Title"]');
    if (!titel) return;
    if (!titel.id) titel.id = 'panelTitel' + Math.random().toString(36).slice(2, 8);
    panel.setAttribute('aria-labelledby', titel.id);
  }

  function geoeffnet(panel) {
    dialogRolle(panel);
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
    document.querySelectorAll(SEL).forEach(p => { zustand.set(p, p.classList.contains('open')); dialogRolle(p); });
    zeilenTastatur();

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

  // Tabellen mit [data-zeilen-tastatur] (Muster: Endschiessen erfassen). Die Tabelle selbst trägt den Fokus.
  function zeilenTastatur() {
    if (!document.querySelector('[data-zeilen-tastatur]')) return;
    const ansage = document.createElement('div');
    ansage.className = 'visually-hidden';
    ansage.setAttribute('aria-live', 'polite');
    document.body.appendChild(ansage);

    document.addEventListener('keydown', function (e) {
      const tab = e.target;
      if (!(tab instanceof Element) || !tab.matches('[data-zeilen-tastatur]')) return;
      const zeilen = Array.from(tab.querySelectorAll('tbody tr.hybrid-row')).filter(tr => tr.offsetParent !== null);
      if (!zeilen.length) return;
      const akt = zeilen.findIndex(tr => tr.classList.contains('ui-markiert'));
      if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(e.key)) {
        e.preventDefault();
        const neu = e.key === 'Home' ? 0 : e.key === 'End' ? zeilen.length - 1
          : akt < 0 ? 0 : Math.max(0, Math.min(zeilen.length - 1, akt + (e.key === 'ArrowDown' ? 1 : -1)));
        tab.querySelectorAll('tr.ui-markiert').forEach(tr => tr.classList.remove('ui-markiert'));
        zeilen[neu].classList.add('ui-markiert');
        zeilen[neu].scrollIntoView({ block: 'nearest' });
        const text = Array.from(zeilen[neu].cells).slice(0, 3).map(td => td.textContent.trim()).filter(Boolean).join(', ');
        ansage.textContent = text + ' (' + (neu + 1) + ' von ' + zeilen.length + ')';
      } else if ((e.key === 'Enter' || e.key === ' ') && akt >= 0) {
        e.preventDefault();
        zeilen[akt].click();
      }
    });
    // Markierung weg, wenn der Fokus woandershin geht – nicht beim Öffnen des Panels (Rückweg per Esc)
    document.addEventListener('focusout', function (e) {
      const tab = e.target;
      if (!(tab instanceof Element) || !tab.matches('[data-zeilen-tastatur]')) return;
      if (e.relatedTarget && e.relatedTarget.closest(SEL)) return;
      setTimeout(function () {
        if (offenesPanel()) return;
        tab.querySelectorAll('tr.ui-markiert').forEach(tr => tr.classList.remove('ui-markiert'));
      }, 100);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
