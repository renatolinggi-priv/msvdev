<?php
/**
 * Einheitlicher Admin-Seitenkopf (Titel + optionaler Untertitel).
 *
 * Vor dem include setzen:
 *   $page_title     (string)  Pflicht – darf Markup/Icons enthalten (statischer Entwickler-Text)
 *   $page_subtitle  (string)  optional – Untertitel in .text-muted
 *
 * Ersetzt das pro Seite kopierte
 *   <div class="row mb-4 d-none d-md-flex">…<h2 class="h4 mb-0" style="color:var(--secondary-color)">…
 * Farbe kommt jetzt zentral aus .page-title (css/msv-styles.css).
 */
$ph_title = $page_title ?? '';
$ph_sub   = $page_subtitle ?? '';
?>
<div class="row mb-4 d-none d-md-flex">
  <div class="col-md-12">
    <h2 class="h4 mb-0 page-title"><?= $ph_title ?></h2>
    <?php if ($ph_sub !== ''): ?>
      <p class="text-muted mb-0"><?= $ph_sub ?></p>
    <?php endif; ?>
  </div>
</div>
