/**
 * MSV Tooltips – Globales Tooltip-System
 * Ersetzt Browser-Default title-Tooltips durch gestylte DOM-Elemente.
 * Verwendet data-tooltip="..." statt title="..."
 *
 * Erreichbar mit Maus (Hover), Tastatur (Fokus) und Touch (Tippen auf Elemente,
 * die selbst nichts auslösen, z.B. deaktivierte Buttons mit Begründung).
 * Icon-Buttons ohne sichtbaren Text bekommen den Tooltip-Text als aria-label,
 * damit Screenreader einen Namen vorlesen (auch für später eingefügte Elemente).
 */
(function($) {
  'use strict';

  // Das Skript lädt im <head>; ins DOM kommt das Element erst, wenn es <body> gibt.
  const $msvTip = $('<div class="msv-tooltip" id="msvTooltip" aria-hidden="true">').hide();
  let aktiv = null;
  let touchTimer = null;

  function zeigen(el) {
    const text = el.getAttribute('data-tooltip');
    if (!text) return;
    if (!$msvTip[0].isConnected && document.body) $msvTip.appendTo(document.body);
    aktiv = el;
    $msvTip.text(text).show();
    const rect = el.getBoundingClientRect();
    const tw = $msvTip.outerWidth();
    const th = $msvTip.outerHeight();
    let left = rect.left + rect.width / 2 - tw / 2;
    if (left + tw > window.innerWidth - 8) left = window.innerWidth - tw - 8;
    if (left < 8) left = 8;
    let top = rect.bottom + 8;
    if (top + th > window.innerHeight - 8) top = Math.max(8, rect.top - th - 8);
    $msvTip.css({ top: top, left: left });
  }

  function verbergen() {
    aktiv = null;
    clearTimeout(touchTimer);
    $msvTip.hide();
  }

  // Maus
  $(document).on('mouseenter', '[data-tooltip]', function() { zeigen(this); });
  $(document).on('mouseleave', '[data-tooltip]', verbergen);

  // Tastatur: nur bei sichtbarem Tastaturfokus, nicht nach jedem Mausklick
  $(document).on('focusin', '[data-tooltip]', function() {
    let tastatur = true;
    try { tastatur = this.matches(':focus-visible'); } catch (e) { /* alter Browser */ }
    if (tastatur) zeigen(this);
  });
  $(document).on('focusout', '[data-tooltip]', verbergen);
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && aktiv) verbergen(); });
  window.addEventListener('scroll', function() { if (aktiv) verbergen(); }, true);

  // Touch: Tippen auf ein Element, das selbst keine Aktion hat (deaktivierter Button,
  // Badge, Icon) zeigt den Tooltip für 3 Sekunden. Aktive Buttons/Links lösen wie
  // gewohnt ihre Aktion aus.
  function istAktivBedienbar(el) {
    if (el.matches(':disabled, .disabled, [aria-disabled="true"]')) return false;
    return el.matches('a[href], button, input, select, textarea, label, [role="button"], [onclick], [data-bs-toggle]');
  }
  function beiTouch(x, y) {
    const unter = document.elementsFromPoint ? document.elementsFromPoint(x, y) : [];
    for (const el of unter) {
      const ziel = el.closest ? el.closest('[data-tooltip]') : null;
      if (!ziel) continue;
      if (istAktivBedienbar(ziel)) return;
      zeigen(ziel);
      clearTimeout(touchTimer);
      touchTimer = setTimeout(verbergen, 3000);
      return;
    }
  }
  document.addEventListener('touchstart', function(e) {
    if (aktiv) verbergen();
    const t = e.touches && e.touches[0];
    if (t) beiTouch(t.clientX, t.clientY);
  }, { passive: true, capture: true });

  // Screenreader: Name für Elemente ohne sichtbaren Text
  function brauchtNamen(el) {
    if (el.hasAttribute('aria-labelledby')) return false;
    if (el.hasAttribute('aria-label') && !el.hasAttribute('data-msv-tip-label')) return false;
    return (el.textContent || '').replace(/\s+/g, '') === '';
  }
  function benennen(root) {
    const liste = [];
    if (root.nodeType === 1 && root.hasAttribute('data-tooltip')) liste.push(root);
    if (root.querySelectorAll) root.querySelectorAll('[data-tooltip]').forEach(function(el) { liste.push(el); });
    liste.forEach(function(el) {
      if (!brauchtNamen(el)) return;
      el.setAttribute('aria-label', el.getAttribute('data-tooltip') || '');
      el.setAttribute('data-msv-tip-label', '1');
    });
  }
  function start() {
    benennen(document.body);
    if (!window.MutationObserver) return;
    new MutationObserver(function(mutationen) {
      mutationen.forEach(function(m) {
        if (m.type === 'attributes') { benennen(m.target); return; }
        m.addedNodes.forEach(function(n) { if (n.nodeType === 1) benennen(n); });
      });
    }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-tooltip'] });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})(jQuery);
