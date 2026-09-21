<?php
/** Tab «Ansätze» – Pauschale je Schicht (einsatz_plan_termine.pauschale_std). Erwartet $plan, $h. */
?>
<div class="table-wrapper">
  <h5 class="table-title"><span><i class="bi bi-sliders me-2"></i>Abgerechnete Stunden je Schicht</span>
    <button type="button" class="btn btn-outline-primary btn-sm" id="haVorschlaegeAlle" data-tooltip="Samstag 5.00, Sonntag 3.00 – nur für Schichten ohne Pauschale"><i class="bi bi-magic me-1"></i>Vorschläge übernehmen</button></h5>
  <div class="table-responsive">
    <table class="hybrid-table" id="haAnsaetze">
      <thead><tr><th>Schicht</th><th>Zeit</th><th>Info</th><th class="text-end">Ist-Dauer</th><th style="width:150px">Pauschale (h)</th><th class="text-end">Vorschlag</th><th class="text-end">Ansatz</th></tr></thead>
      <tbody>
        <?php if (!$plan['termine']) echo msv_empty_row(7, 'Keine Schichten im Plan gefunden'); ?>
        <?php foreach ($plan['termine'] as $t): $vs = ha_pauschale_vorschlag($t); $hatP = isset($t['pauschale_std']) && $t['pauschale_std'] !== '' && $t['pauschale_std'] !== null; ?>
        <tr data-termin="<?= (int)$t['id'] ?>" data-vorschlag="<?= $h(ha_fmt($vs)) ?>">
          <td class="fw-semibold"><?= $h(ha_datum_lang($t['datum'])) ?><?php if ($t['bezeichnung']): ?> <small class="text-muted">· <?= $h($t['bezeichnung']) ?></small><?php endif; ?></td>
          <td><?= $h(ep_zeit_text($t)) ?></td>
          <td class="text-muted small"><?= $h($t['info'] ?? '') ?></td>
          <td class="text-end text-muted"><?= $h(ha_fmt(ep_termin_dauer($t))) ?></td>
          <td><input type="number" step="0.25" min="0" max="99.99" class="form-control form-control-sm ha-pauschale" value="<?= $hatP ? $h(ha_fmt((float)$t['pauschale_std'])) : '' ?>" placeholder="Schichtdauer"></td>
          <td class="text-end"><?php if (!$hatP): ?><button type="button" class="btn btn-outline-primary btn-sm ha-vorschlag" data-tooltip="Vorschlag übernehmen"><?= $h(ha_fmt($vs)) ?> <i class="bi bi-arrow-left-short"></i></button><?php else: ?><span class="text-muted"><?= $h(ha_fmt($vs)) ?></span><?php endif; ?></td>
          <td class="text-end fw-semibold ha-ansatz"><?= $h(ha_fmt(ep_termin_stunden($t))) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="text-muted small mt-2">Leeres Feld = Schichtdauer aus den Zeiten (wie bisher). Die Pauschale gilt für jede zählende Position der Schicht und wird bei der Kopie ins Folgejahr übernommen. Abweichungen für einzelne Personen im Tab «Detail» (Stundenkorrektur). Speichern erfolgt automatisch beim Verlassen des Feldes.</div>
</div>
