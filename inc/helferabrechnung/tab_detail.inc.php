<?php
/** Tab «Detail» – alle Zuteilungen mit berechneten Stunden, Filter, Klick → Panel (ok/Korrektur/Bemerkung). Erwartet $a, $plan, $h. */
$termineOpt = []; foreach ($plan['termine'] as $t) $termineOpt[(int)$t['id']] = ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t);
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-2 ha-filter">
  <select class="form-select form-select-sm" id="haFVerein" style="width:auto"><option value="">Alle Vereine</option><?php foreach (EP_VEREINE as $k => $l): ?><option value="<?= $k ?>"><?= $h($l) ?></option><?php endforeach; ?></select>
  <select class="form-select form-select-sm" id="haFTermin" style="width:auto"><option value="">Alle Schichten</option><?php foreach ($termineOpt as $id => $l): ?><option value="<?= $id ?>"><?= $h($l) ?></option><?php endforeach; ?></select>
  <select class="form-select form-select-sm" id="haFStatus" style="width:auto">
    <option value="">Alle Positionen</option><option value="zaehlt">zählend</option><option value="ok">OK-Positionen</option><option value="nicht_da">nicht da</option><option value="offen">Anwesenheit nicht erfasst</option><option value="korrektur">mit Korrektur</option><option value="bemerkung">mit Bemerkung</option>
  </select>
  <input type="text" class="form-control form-control-sm" id="haFSuche" placeholder="Name / Funktion…" style="width:200px" autocomplete="off">
  <span class="text-muted small ms-auto" id="haFCount"></span>
  <a class="btn btn-outline-secondary btn-sm" href="einsatzplanung.php?id=<?= (int)$plan['id'] ?>" data-tooltip="Verein, Person und Anwesenheit werden im Einsatzplan gepflegt"><i class="bi bi-pencil-square me-1"></i>Einsatzplan</a>
</div>
<div class="table-wrapper">
  <div class="table-responsive">
    <table class="hybrid-table" id="haDetail">
      <thead><tr><th>Schicht</th><th>Funktion</th><th>Person</th><th>Verein</th><th class="text-center">OK</th><th class="text-center">Anw.</th><th class="text-end">Ansatz</th><th class="text-end">Korr.</th><th class="text-end">Stunden</th><th>Bemerkung</th></tr></thead>
      <tbody>
        <?php if (!$a['zuteilungen']) echo msv_empty_row(10, 'Keine besetzten Positionen gefunden'); ?>
        <?php foreach ($a['zuteilungen'] as $z): ?>
        <tr class="hybrid-row js-slot <?= $z['zaehlt'] ? '' : 'ha-zero' ?>" id="slot<?= $z['slot_id'] ?>"
            data-slot="<?= $z['slot_id'] ?>" data-verein="<?= $h($z['verein']) ?>" data-termin="<?= $z['termin_id'] ?>" data-ok="<?= $z['ok'] ? 1 : 0 ?>"
            data-anwesend="<?= $z['anwesend'] === null ? '' : $z['anwesend'] ?>" data-korrektur="<?= $z['korrektur'] === null ? '' : $h(ha_fmt($z['korrektur'])) ?>"
            data-bemerkung="<?= $h($z['bemerkung']) ?>" data-person="<?= $h($z['person']) ?>" data-funktion="<?= $h($z['funktion']) ?>" data-termin-label="<?= $h($z['termin_label']) ?>"
            data-grund="<?= $h($z['grund']) ?>" data-suche="<?= $h(mb_strtolower($z['person'] . ' ' . $z['funktion'])) ?>">
          <td class="text-nowrap"><?= $h($z['termin_label']) ?></td>
          <td><?= $h($z['funktion']) ?></td>
          <td class="fw-semibold"><?= $h($z['person']) ?></td>
          <td><span class="ha-v ha-v-<?= $h($z['verein']) ?>"><?= $h(EP_VEREINE[$z['verein']] ?? $z['verein']) ?></span></td>
          <td class="text-center"><?php if ($z['ok']): ?><span class="badge bg-warning text-dark">OK</span><?php endif; ?></td>
          <td class="text-center"><?php if ($z['anwesend'] === 1): ?><i class="bi bi-check-circle-fill text-success" data-tooltip="da"></i><?php elseif ($z['anwesend'] === 0): ?><i class="bi bi-x-circle-fill text-danger" data-tooltip="nicht da"></i><?php else: ?><i class="bi bi-dash-circle text-muted" data-tooltip="nicht erfasst"></i><?php endif; ?></td>
          <td class="text-end text-muted"><?= $h(ha_fmt($z['ansatz'])) ?></td>
          <td class="text-end"><?= $z['korrektur'] !== null ? $h(ha_fmt($z['korrektur'])) : '' ?></td>
          <td class="text-end fw-semibold"><?= $h(ha_fmt($z['stunden'])) ?><?php if ($z['grund'] === 'nicht_da'): ?> <small class="text-danger" data-tooltip="Anwesenheit «nicht da»">✗</small><?php elseif ($z['grund'] === 'ok'): ?> <small class="text-muted" data-tooltip="OK-Position, Schalter aus">OK</small><?php endif; ?></td>
          <td class="text-muted small"><?= $h($z['bemerkung']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="text-muted small">Zeile anklicken → OK-Kennzeichen, Stundenkorrektur und Bemerkung. Verein, Person und Anwesenheit werden im Einsatzplan gepflegt (Editor bzw. Anwesenheits-Erfassung).</div>
