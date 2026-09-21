<?php
/**
 * Tab «Abrechnung» (read-only) – erwartet $a (ha_abrechnung), $h (Escaper).
 * Wird von der Seite und von abrechnung_fragment.php eingebunden.
 */
$m = $a['matrix']; $k = $a['kennzahlen']; $okMit = $a['ok'];
$vereine = array_keys(EP_VEREINE);
?>
<?php foreach ($a['warnungen'] as $w): ?>
  <div class="alert alert-<?= $w['typ'] === 'status' ? 'secondary' : 'warning' ?> py-2 mb-2 ha-warn"><i class="bi bi-exclamation-triangle me-2"></i><?= $h($w['text']) ?></div>
<?php endforeach; ?>

<div class="table-wrapper">
  <h5 class="table-title"><span><i class="bi bi-calculator me-2"></i>Gesamtabrechnung pro Verein</span>
    <span class="badge <?= $okMit ? 'bg-warning text-dark' : 'bg-secondary' ?>"><?= $okMit ? 'OK-Einsätze mitgezählt' : 'ohne OK-Einsätze' ?></span></h5>
  <div class="table-responsive">
    <table class="hybrid-table ha-matrix">
      <thead><tr><th>Position</th><?php foreach ($vereine as $v): ?><th class="text-end"><?= $h(EP_VEREINE[$v]) ?></th><?php endforeach; ?><th class="text-end">Total</th></tr></thead>
      <tbody>
        <?php foreach (HA_ZEILEN as $key => $label): ?>
        <tr>
          <td><?= $h($label) ?><?php if ($key === 'einsaetze' && $m['nachtrag']): ?> <small class="text-muted">(inkl. Nachträge <?= $h(ha_fmt(array_sum($m['nachtrag']))) ?>)</small><?php endif; ?></td>
          <?php foreach ($vereine as $v): ?><td class="text-end"><?= $h(ha_fmt($m['vereine'][$v][$key])) ?></td><?php endforeach; ?>
          <td class="text-end fw-semibold"><?= $h(ha_fmt($m['gesamt'][$key])) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="ha-total">
          <td>Total Helferstunden</td>
          <?php foreach ($vereine as $v): ?><td class="text-end"><?= $h(ha_fmt($m['vereine'][$v]['total'])) ?></td><?php endforeach; ?>
          <td class="text-end"><?= $h(ha_fmt($m['gesamt']['total'])) ?></td>
        </tr>
        <tr class="ha-anteil">
          <td>Anteil in %</td>
          <?php foreach ($vereine as $v): ?><td class="text-end"><?= $h(number_format($m['vereine'][$v]['anteil'] * 100, 1, '.', '')) ?> %</td><?php endforeach; ?>
          <td class="text-end"><?= $m['gesamt']['total'] > 0 ? '100.0 %' : '–' ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="table-wrapper">
  <h5 class="table-title"><span><i class="bi bi-clock-history me-2"></i>Helferstunden je Schicht und Verein</span><span class="badge bg-secondary"><?= count($m['termine']) ?> Schichten</span></h5>
  <div class="table-responsive">
    <table class="hybrid-table ha-matrix">
      <thead><tr><th>Schicht</th><?php foreach ($vereine as $v): ?><th class="text-end"><?= $h(EP_VEREINE[$v]) ?></th><?php endforeach; ?><th class="text-end">Total</th></tr></thead>
      <tbody>
        <?php if (!$m['termine']) echo msv_empty_row(count($vereine) + 2, 'Keine Schichten gefunden'); ?>
        <?php foreach ($m['termine'] as $t): ?>
        <tr>
          <td><?= $h($t['label']) ?></td>
          <?php foreach ($vereine as $v): $c = $t['vereine'][$v]; ?>
            <td class="text-end"><?= $h(ha_fmt($c['stunden'])) ?> <small class="text-muted">(<?= (int)$c['positionen'] ?>)</small></td>
          <?php endforeach; ?>
          <td class="text-end fw-semibold"><?= $h(ha_fmt($t['total']['stunden'])) ?> <small class="text-muted">(<?= (int)$t['total']['positionen'] ?>)</small></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($m['nachtrag']): ?>
        <tr>
          <td>Nachträge (manuelle Zeilen)</td>
          <?php foreach ($vereine as $v): ?><td class="text-end"><?= $h(ha_fmt($m['nachtrag'][$v] ?? 0)) ?></td><?php endforeach; ?>
          <td class="text-end fw-semibold"><?= $h(ha_fmt(array_sum($m['nachtrag']))) ?></td>
        </tr>
        <?php endif; ?>
        <tr class="ha-total">
          <td>Einsätze total</td>
          <?php foreach ($vereine as $v): ?><td class="text-end"><?= $h(ha_fmt($m['vereine'][$v]['einsaetze'])) ?> <small>(<?= (int)$m['vereine'][$v]['positionen'] ?>)</small></td><?php endforeach; ?>
          <td class="text-end"><?= $h(ha_fmt($m['gesamt']['einsaetze'])) ?> <small>(<?= (int)$m['gesamt']['positionen'] ?>)</small></td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="text-muted small mt-1">Stunden (Anzahl zählende Positionen). Positionen mit Anwesenheit «nicht da»<?= $okMit ? '' : ' und OK-Positionen' ?> zählen nicht.</div>
</div>

<div class="table-wrapper">
  <h5 class="table-title"><span><i class="bi bi-info-circle me-2"></i>Kennzahlen</span></h5>
  <div class="row g-2 ha-kennzahlen">
    <?php
    $kz = [
        ['Besetzte Positionen (fest)', $k['positionen']],
        ['Davon zählend', $k['zaehlend']],
        ['OK-Positionen', $k['ok_positionen'] . ' · ' . ha_fmt($k['ok_stunden']) . ' h'],
        ['Anwesenheit «nicht da»', $k['nicht_da']],
        ['Anwesenheit nicht erfasst', $k['anwesenheit_offen']],
        ['Stundenkorrekturen', $k['korrekturen']],
        ['Positionen mit Bemerkung', $k['bemerkungen']],
        ['Manuelle Zeilen', $k['manuell']],
    ];
    foreach ($kz as [$lbl, $val]): ?>
      <div class="col-6 col-md-3"><div class="ha-kz"><div class="ha-kz-val"><?= $h($val) ?></div><div class="ha-kz-lbl"><?= $h($lbl) ?></div></div></div>
    <?php endforeach; ?>
  </div>
</div>
