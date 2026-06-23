/**
 * MSV Tooltips – Globales Tooltip-System
 * Ersetzt Browser-Default title-Tooltips durch gestylte DOM-Elemente.
 * Verwendet data-tooltip="..." statt title="..."
 */
(function($) {
  'use strict';

  const $msvTip = $('<div class="msv-tooltip">').appendTo('body').hide();

  $(document).on('mouseenter', '[data-tooltip]', function() {
    const text = this.getAttribute('data-tooltip');
    if (!text) return;
    $msvTip.text(text).show();
    const rect = this.getBoundingClientRect();
    const tw = $msvTip.outerWidth();
    let left = rect.left + rect.width / 2 - tw / 2;
    if (left + tw > window.innerWidth - 8) left = window.innerWidth - tw - 8;
    if (left < 8) left = 8;
    $msvTip.css({ top: rect.bottom + 8, left: left });
  });

  $(document).on('mouseleave', '[data-tooltip]', function() {
    $msvTip.hide();
  });
})(jQuery);
