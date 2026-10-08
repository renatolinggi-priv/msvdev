<?php
/**
 * Einheitlicher Admin-Seitenkopf als Kopf-Card (Titel + optional Untertitel, Zusatz neben dem Titel,
 * Aktionen rechts, zweite Zeile). Aussehen in css/msv-ui.css (.msv-kopf*).
 *
 * Vor dem include setzen:
 *   $page_title        (string)  Pflicht – darf Markup/Icons enthalten (statischer Entwickler-Text)
 *   $page_subtitle     (string)  optional – Untertitel unter dem Titel
 *   $page_title_after  (string)  optional – HTML direkt neben dem Titel (Hilfe-«?», Jahr-Auswahl)
 *   $page_actions      (string)  optional – HTML rechts (Knöpfe; Hilfe-«?» geht auch hier)
 *   $page_extra        (string)  optional – HTML als zweite Zeile über die ganze Breite (z.B. Fortschritt)
 *   $page_show_mobile  (bool)    optional – true zeigt den Kopf auch auf Mobile (Default: nur ab md);
 *                                 nötig, wenn der Kopf eine Auswahl wie das Jahr enthält
 */
$ph_title   = $page_title ?? '';
$ph_sub     = $page_subtitle ?? '';
$ph_after   = $page_title_after ?? '';
$ph_actions = $page_actions ?? '';
$ph_extra   = $page_extra ?? '';
$ph_vis     = !empty($page_show_mobile) ? 'd-flex' : 'd-none d-md-flex';
// Hilfe-«?» gehört neben den Titel: steht er (noch) am Anfang der Aktionen, dorthin verschieben
if ($ph_after === '' && preg_match('/^\s*(<button type="button" class="btn-help"[^>]*><\/button>)\s*/', $ph_actions, $ph_m)) {
    $ph_after   = $ph_m[1];
    $ph_actions = substr($ph_actions, strlen($ph_m[0]));
}
?>
<header class="msv-kopf <?= $ph_vis ?>">
  <div class="msv-kopf-titel">
    <h2 class="h4 mb-0 page-title"><?= $ph_title ?></h2>
    <?php if ($ph_after !== ''): ?>
      <div class="msv-kopf-neben"><?= $ph_after ?></div>
    <?php endif; ?>
    <?php if ($ph_sub !== ''): ?>
      <p class="msv-kopf-sub"><?= $ph_sub ?></p>
    <?php endif; ?>
  </div>
  <?php if ($ph_actions !== ''): ?>
    <div class="page-actions d-flex align-items-center gap-2 flex-wrap"><?= $ph_actions ?></div>
  <?php endif; ?>
  <?php if ($ph_extra !== ''): ?>
    <div class="msv-kopf-extra"><?= $ph_extra ?></div>
  <?php endif; ?>
</header>
<?php unset($page_subtitle, $page_title_after, $page_actions, $page_extra, $page_show_mobile); ?>
