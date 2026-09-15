<?php
/**
 * Einheitlicher Admin-Seitenkopf (Titel + optionaler Untertitel + optionale Aktionen rechts).
 *
 * Vor dem include setzen:
 *   $page_title        (string)  Pflicht – darf Markup/Icons enthalten (statischer Entwickler-Text)
 *   $page_subtitle     (string)  optional – Untertitel in .text-muted
 *   $page_actions      (string)  optional – HTML rechts vom Titel (Jahr-Select, Button …);
 *                                 damit entfallen die handgebauten Köpfe auf Seiten mit Kopf-Aktion
 *   $page_show_mobile  (bool)    optional – true zeigt den Kopf auch auf Mobile (Default: nur ab md,
 *                                 wie bisher); sinnvoll, wenn $page_actions einen Filter enthält
 *
 * Ersetzt das pro Seite kopierte
 *   <div class="row mb-4 d-none d-md-flex">…<h2 class="h4 mb-0" style="color:var(--secondary-color)">…
 * Farbe kommt zentral aus .page-title (css/msv-styles.css).
 */
$ph_title   = $page_title ?? '';
$ph_sub     = $page_subtitle ?? '';
$ph_actions = $page_actions ?? '';
$ph_vis     = !empty($page_show_mobile) ? 'd-flex' : 'd-none d-md-flex';
?>
<div class="row mb-4 <?= $ph_vis ?>">
  <div class="col-md-12<?= $ph_actions !== '' ? ' d-flex justify-content-between align-items-center flex-wrap gap-2' : '' ?>">
    <div>
      <h2 class="h4 mb-0 page-title"><?= $ph_title ?></h2>
      <?php if ($ph_sub !== ''): ?>
        <p class="text-muted mb-0"><?= $ph_sub ?></p>
      <?php endif; ?>
    </div>
    <?php if ($ph_actions !== ''): ?>
      <div class="page-actions d-flex align-items-center gap-2 flex-wrap"><?= $ph_actions ?></div>
    <?php endif; ?>
  </div>
</div>
<?php unset($page_subtitle, $page_actions, $page_show_mobile); ?>
