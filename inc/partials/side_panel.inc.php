<?php
/**
 * Slide-Panel (rechts einfahrend) – zentrale Struktur inkl. Overlay.
 * CSS liegt zentral in css/msv-styles.css (Klassen .panel-overlay/.panel-header/.panel-body …).
 *
 * Vor dem include setzen:
 *   $panel_body        (string)  Pflicht – HTML des Panel-Inhalts (ohne umschliessendes .panel-body)
 * Optional:
 *   $panel_id          (string)  DOM-id des Panels (JS toggelt .open darauf) – Default 'editPanel'
 *   $panel_class       (string)  Basis-/Zusatzklasse – Default 'hybrid-edit-panel'
 *   $panel_title       (string)  HTML im Header (darf Icons/<span id> enthalten)
 *   $panel_overlay_id  (string)  Default 'panelOverlay'
 *   $panel_close_id    (string)  Default 'panelClose'
 *   $panel_width       (string)  z.B. '360px' -> setzt --panel-width inline
 *   $panel_footer      (string)  HTML fuer optionalen .panel-footer
 *
 * Hinweis: Panel-Body typischerweise per ob_start()/ob_get_clean() in der Seite erfassen.
 * Die Variablen werden nach der Ausgabe zurueckgesetzt, damit mehrere Panels je Seite moeglich sind.
 */
$sp_id      = $panel_id ?? 'editPanel';
$sp_class   = $panel_class ?? 'hybrid-edit-panel';
$sp_title   = $panel_title ?? '';
$sp_overlay = $panel_overlay_id ?? 'panelOverlay';
$sp_close   = $panel_close_id ?? 'panelClose';
$sp_body    = $panel_body ?? '';
$sp_footer  = $panel_footer ?? '';
$sp_style   = isset($panel_width) && $panel_width !== ''
    ? ' style="--panel-width:' . htmlspecialchars($panel_width, ENT_QUOTES, 'UTF-8') . '"'
    : '';
?>
<div class="panel-overlay" id="<?= htmlspecialchars($sp_overlay, ENT_QUOTES, 'UTF-8') ?>"></div>
<div class="<?= htmlspecialchars($sp_class, ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($sp_id, ENT_QUOTES, 'UTF-8') ?>"<?= $sp_style ?>>
  <div class="panel-header">
    <h6 class="mb-0"><?= $sp_title ?></h6>
    <button class="btn btn-sm btn-outline-secondary" id="<?= htmlspecialchars($sp_close, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="panel-body">
    <?= $sp_body ?>
  </div>
  <?php if ($sp_footer !== ''): ?>
  <div class="panel-footer"><?= $sp_footer ?></div>
  <?php endif; ?>
</div>
<?php
// Aufruf-Variablen zuruecksetzen (mehrere Panels je Seite moeglich)
unset($panel_id, $panel_class, $panel_title, $panel_overlay_id,
      $panel_close_id, $panel_body, $panel_footer, $panel_width);
