<?php
/** Tab «Manuelle Zeilen» – Vor-/Nacharbeiten, OK-Funktionen, Nachträge (einsatz_abr_zeilen). Erwartet $a, $h. */
$gruppen = ['vorarbeit' => [], 'ok_funktion' => [], 'einsatz' => []];
foreach ($a['manuell'] as $z) $gruppen[$z['kategorie']][] = $z;
$titel = ['vorarbeit' => ['Vor- & Nacharbeiten', 'bi-tools'], 'ok_funktion' => ['OK-Funktionen', 'bi-award'], 'einsatz' => ['Nachträge zu Einsätzen', 'bi-plus-circle']];
?>
<div class="d-flex justify-content-end gap-2 mb-2">
  <button type="button" class="btn btn-outline-primary btn-sm js-zeile-vorjahr" data-tooltip="Vor-/Nacharbeiten und OK-Funktionen des letzten Schlossturm-Plans übernehmen (Stunden als Startwert, Nachträge nicht)"><i class="bi bi-arrow-repeat me-1"></i>Aus Vorjahr übernehmen</button>
  <button type="button" class="btn btn-outline-success btn-sm js-zeile-neu"><i class="bi bi-plus-lg me-1"></i>Neue Zeile</button>
</div>
<?php foreach ($gruppen as $kat => $zeilen): [$lbl, $icon] = $titel[$kat]; $sum = array_sum(array_column($zeilen, 'stunden')); ?>
<div class="table-wrapper">
  <h5 class="table-title"><span><i class="bi <?= $icon ?> me-2"></i><?= $h($lbl) ?></span><span class="badge bg-secondary"><?= count($zeilen) ?> · <?= $h(ha_fmt($sum)) ?> h</span></h5>
  <div class="table-responsive">
    <table class="hybrid-table ha-zeilen">
      <thead><tr><th>Tätigkeit</th><th>Person</th><th>Verein</th><th class="text-end" style="width:90px">Stunden</th><th>Bemerkung</th><th style="width:40px"></th></tr></thead>
      <tbody>
        <?php if (!$zeilen) echo msv_empty_row(6, 'Keine Zeilen erfasst'); ?>
        <?php foreach ($zeilen as $z): ?>
        <tr class="hybrid-row js-zeile" id="zeile<?= (int)$z['id'] ?>"
            data-id="<?= (int)$z['id'] ?>" data-kategorie="<?= $h($z['kategorie']) ?>" data-taetigkeit="<?= $h($z['taetigkeit']) ?>" data-verein="<?= $h($z['verein']) ?>"
            data-mitglied-id="<?= (int)$z['mitglied_id'] ?>" data-name-text="<?= $h($z['name_text'] ?? '') ?>" data-stunden="<?= $h(ha_fmt((float)$z['stunden'])) ?>" data-bemerkung="<?= $h($z['bemerkung'] ?? '') ?>">
          <td class="fw-semibold"><?= $h($z['taetigkeit']) ?></td>
          <td><?= $h($z['person']) ?></td>
          <td><span class="ha-v ha-v-<?= $h($z['verein']) ?>"><?= $h(EP_VEREINE[$z['verein']] ?? $z['verein']) ?></span></td>
          <td class="text-end"><?= $h(ha_fmt((float)$z['stunden'])) ?></td>
          <td class="text-muted small"><?= $h($z['bemerkung'] ?? '') ?></td>
          <td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm js-zeile-delete" data-id="<?= (int)$z['id'] ?>" data-label="<?= $h($z['taetigkeit'] . ($z['person'] !== '' ? ' – ' . $z['person'] : '')) ?>" data-tooltip="Löschen"><i class="bi bi-trash"></i></button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>
<div class="text-muted small">Vor- & Nacharbeiten und OK-Funktionen zählen immer (eigene Zeilen der Abrechnung). Nachträge zählen zur Zeile «Einsätze» – für Einsätze, die nicht im Einsatzplan stehen (z.B. vergessener Einsatz aus dem Vorjahr).</div>
