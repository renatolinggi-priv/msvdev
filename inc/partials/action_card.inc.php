<?php
/**
 * Aktionen-Collapse-Card (Bootstrap). CSS zentral: .action-card/.action-card-header/.action-chevron.
 * Ersetzt das pro Seite kopierte
 *   <div class="card action-card mb-0"><div class="card-header action-card-header …" data-bs-toggle="collapse" …>…
 *
 * Vor dem include setzen:
 *   $ac_id    (string) Pflicht – id des Collapse-Targets (pro Seite eindeutig; JS/Bootstrap nutzt sie)
 *   $ac_body  (string) Pflicht – HTML der Buttons (typisch <div class="row g-2">…</div>), via ob_start()/ob_get_clean()
 * Optional:
 *   $ac_title       (string) Default 'Aktionen' (darf Markup enthalten)
 *   $ac_icon        (string) Bootstrap-Icon-Klasse, Default 'bi-tools'
 *   $ac_card_class  (string) Zusatzklassen fürs äussere .card, Default 'mb-0'
 *   $ac_body_class  (string) Klassen fürs .card-body, Default 'pt-2 pb-3 px-3'
 */
$ac_id_v    = $ac_id ?? 'actions';
$ac_title_v = $ac_title ?? 'Aktionen';
$ac_icon_v  = $ac_icon ?? 'bi-tools';
$ac_cardcls = $ac_card_class ?? 'mb-0';
$ac_bodycls = $ac_body_class ?? 'pt-2 pb-3 px-3';
$ac_body_v  = $ac_body ?? '';
$ac_id_esc  = htmlspecialchars($ac_id_v, ENT_QUOTES, 'UTF-8');
?>
<div class="card action-card <?= htmlspecialchars($ac_cardcls, ENT_QUOTES, 'UTF-8') ?>">
  <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
       data-bs-toggle="collapse" data-bs-target="#<?= $ac_id_esc ?>"
       aria-expanded="false" aria-controls="<?= $ac_id_esc ?>">
    <span class="fw-semibold"><i class="bi <?= htmlspecialchars($ac_icon_v, ENT_QUOTES, 'UTF-8') ?> me-2"></i><?= $ac_title_v ?></span>
    <i class="bi bi-chevron-down action-chevron"></i>
  </div>
  <div class="collapse" id="<?= $ac_id_esc ?>">
    <div class="card-body <?= htmlspecialchars($ac_bodycls, ENT_QUOTES, 'UTF-8') ?>">
      <?= $ac_body_v ?>
    </div>
  </div>
</div>
<?php
unset($ac_id, $ac_title, $ac_icon, $ac_card_class, $ac_body_class, $ac_body);
