<?php
// portal/einsatz_anwesenheit.php – Anwesenheit bei Arbeitseinsätzen erfassen (Vorstand/Admin, für das Handy).
// Plan wählen → Schicht wählen (Standard: heute bzw. nächstgelegene) → pro Person «Da» / «Nicht da» antippen.
// Datenquelle: einsatz_plan_slots.anwesend (Migration 054), API api/einsatz_anwesenheit.php.
$portal_page_title = 'Anwesenheit';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
if (!(isAdmin() || isVorstand())) { header('Location: dashboard.php'); exit(); }
require_once __DIR__ . '/../inc/einsatzplanung/plan_helpers.inc.php';

$db   = getDB();
$csrf = ensureCsrfToken();
$heute = date('Y-m-d');

// Pläne inkl. Entwürfe (die Seite ist nur für Vorstand/Admin – so lässt sich die Erfassung vor der Freigabe testen),
// nächstgelegener Termin zuerst
$plaene = [];
try {
    $plaene = $db->query("SELECT p.id, p.titel, p.jahr, p.status,
                                 (SELECT MIN(ABS(DATEDIFF(t.datum, CURDATE()))) FROM einsatz_plan_termine t WHERE t.plan_id = p.id) AS abstand
                            FROM einsatz_plaene p WHERE p.layout = 'funktion_x_termin'
                           ORDER BY abstand IS NULL, abstand, p.jahr DESC, p.titel")->fetchAll();
} catch (Throwable $e) { $plaene = []; }

$planId = (int)($_GET['id'] ?? 0);
if ($planId <= 0 && $plaene) $planId = (int)$plaene[0]['id'];
$plan = null;
try { $plan = $planId > 0 ? ep_plan_laden($db, $planId) : null; } catch (Throwable $e) { $plan = null; }

$termin = null; $mitglieder = [];
if ($plan) {
    $mitglieder = ep_mitglieder_map($db);
    $terminId = (int)($_GET['termin'] ?? 0);
    foreach ($plan['termine'] as $t) if ((int)$t['id'] === $terminId) $termin = $t;
    if (!$termin && $plan['termine']) {   // nächstgelegene Schicht zum heutigen Datum
        usort($plan['termine'], fn($a, $b) => abs(strtotime($a['datum']) - strtotime($heute)) <=> abs(strtotime($b['datum']) - strtotime($heute)) ?: strcmp($a['zeit_von'] ?? '', $b['zeit_von'] ?? ''));
        $termin = $plan['termine'][0];
        usort($plan['termine'], fn($a, $b) => strcmp($a['datum'] . ($a['zeit_von'] ?? ''), $b['datum'] . ($b['zeit_von'] ?? '')));
    }
}

// Personen der Schicht: feste Positionen mit Name, gruppiert nach Funktion
$zeilen = []; $stat = ['da' => 0, 'nein' => 0, 'offen' => 0];
if ($plan && $termin) {
    $funk = []; foreach ($plan['funktionen'] as $f) $funk[(int)$f['id']] = $f;
    foreach ($plan['slots'] as $s) {
        if ((int)$s['termin_id'] !== (int)$termin['id'] || !ep_slot_fix($s)) continue;
        $f = $funk[(int)$s['funktion_id']] ?? null; if (!$f) continue;
        $zeilen[] = ['slot' => $s, 'funktion' => $f, 'name' => ep_slot_text($s, $mitglieder)];
        if ($s['anwesend'] === null) $stat['offen']++; elseif ((int)$s['anwesend'] === 1) $stat['da']++; else $stat['nein']++;
    }
    usort($zeilen, fn($a, $b) => [(int)$a['funktion']['sort'], (int)$a['slot']['pos']] <=> [(int)$b['funktion']['sort'], (int)$b['slot']['pos']]);
}
include 'portal_header.php';
?>

<style>
.an-wrap { max-width: 640px; }
.an-select { margin-bottom: .6rem; }
.an-termine { display: flex; gap: .4rem; overflow-x: auto; padding-bottom: .3rem; margin-bottom: .8rem; }
.an-termine a { flex: 0 0 auto; border: 1px solid var(--p-border, #e2e8f0); border-radius: 999px; padding: .3rem .8rem; font-size: .82rem; color: #334155; text-decoration: none; background: #fff; white-space: nowrap; }
.an-termine a.aktiv { background: var(--primary-color, #2d4373); color: #fff; border-color: var(--primary-color, #2d4373); }
.an-stat { display: flex; gap: .5rem; margin-bottom: .8rem; font-size: .8rem; }
.an-stat span { border-radius: .5rem; padding: .25rem .6rem; background: #f1f5f9; color: #475569; }
.an-stat .da { background: #dcfce7; color: #15803d; } .an-stat .nein { background: #fee2e2; color: #b91c1c; }
.an-fn { font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; font-weight: 700; margin: .8rem 0 .3rem; }
.an-row { display: flex; align-items: center; gap: .6rem; background: #fff; border: 1px solid var(--p-border, #e2e8f0); border-radius: .7rem; padding: .5rem .6rem; margin-bottom: .4rem; }
.an-row.da { border-color: #86efac; background: #f0fdf4; } .an-row.nein { border-color: #fca5a5; background: #fef2f2; }
.an-name { flex: 1; min-width: 0; font-weight: 600; font-size: .95rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.an-name small { display: block; font-weight: 400; color: #94a3b8; font-size: .72rem; }
.an-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; background: #c62828; }
.an-dot.freienbach { background: #3b5998; } .an-dot.wollerau { background: #2e7d32; }
.an-btn { flex: 0 0 auto; width: 3.1rem; height: 2.7rem; border-radius: .6rem; border: 1px solid #cbd5e1; background: #fff; font-size: 1.25rem; color: #94a3b8; display: flex; align-items: center; justify-content: center; }
.an-btn:active { transform: scale(.96); }
.an-row.da .an-btn.ja { background: #16a34a; border-color: #16a34a; color: #fff; }
.an-row.nein .an-btn.no { background: #dc2626; border-color: #dc2626; color: #fff; }
.an-alle { display: flex; gap: .5rem; margin: .6rem 0 1rem; }
.an-alle button { flex: 1; }
</style>

<div class="portal-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><h1><i class="bi bi-person-check me-2"></i>Anwesenheit</h1><p class="subtitle mb-0">Wer war da, wer nicht – pro Schicht antippen</p></div>
  <a href="einsatzplaene.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Einsatzpläne</a>
</div>

<div class="an-wrap">
<?php if (!$plaene): ?>
  <div class="p-card"><div class="p-card-body text-muted text-center py-4">Keine freigegebenen Einsatzpläne vorhanden.</div></div>
<?php else: ?>
  <form method="get" class="an-select">
    <select name="id" class="form-select" onchange="this.form.submit()">
      <?php foreach ($plaene as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === $planId ? 'selected' : '' ?>><?= htmlspecialchars($p['titel']) ?> (<?= (int)$p['jahr'] ?>)<?= $p['status'] === 'entwurf' ? ' · Entwurf' : '' ?></option><?php endforeach; ?>
    </select>
  </form>
  <?php if ($plan): ?>
  <div class="an-termine">
    <?php foreach ($plan['termine'] as $t): ?>
      <a href="einsatz_anwesenheit.php?id=<?= $planId ?>&termin=<?= (int)$t['id'] ?>" class="<?= $termin && (int)$t['id'] === (int)$termin['id'] ? 'aktiv' : '' ?>"><?= htmlspecialchars(ep_datum_kurz($t['datum'])) ?> <?= htmlspecialchars(ep_zeit_text($t)) ?><?= $t['datum'] === $heute ? ' · heute' : '' ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($termin): ?>
    <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf) ?>">
    <div class="an-stat"><span class="da"><i class="bi bi-check-lg"></i> <b id="anDa"><?= $stat['da'] ?></b> da</span><span class="nein"><i class="bi bi-x-lg"></i> <b id="anNein"><?= $stat['nein'] ?></b> nicht da</span><span><b id="anOffen"><?= $stat['offen'] ?></b> offen</span></div>
    <?php if (!$zeilen): ?>
      <div class="p-card"><div class="p-card-body text-muted text-center py-4">In dieser Schicht ist niemand eingeteilt.</div></div>
    <?php else: $letzteFn = null; foreach ($zeilen as $z): $s = $z['slot']; $f = $z['funktion']; $cls = $s['anwesend'] === null ? '' : ((int)$s['anwesend'] === 1 ? 'da' : 'nein'); ?>
      <?php if ($letzteFn !== (int)$f['id']): $letzteFn = (int)$f['id']; ?><div class="an-fn"><?= htmlspecialchars((trim((string)$f['gruppe']) !== '' ? $f['gruppe'] . ': ' : '') . $f['bezeichnung']) ?></div><?php endif; ?>
      <div class="an-row <?= $cls ?>" data-slot="<?= (int)$s['id'] ?>">
        <span class="an-dot <?= htmlspecialchars($s['verein']) ?>"></span>
        <div class="an-name"><?= htmlspecialchars($z['name']) ?><small><?= htmlspecialchars(EP_VEREINE[$s['verein']] ?? '') ?><?= trim((string)$s['bemerkung']) !== '' ? ' · ' . htmlspecialchars($s['bemerkung']) : '' ?></small></div>
        <button type="button" class="an-btn ja" data-wert="1" aria-label="da"><i class="bi bi-check-lg"></i></button>
        <button type="button" class="an-btn no" data-wert="0" aria-label="nicht da"><i class="bi bi-x-lg"></i></button>
      </div>
    <?php endforeach; ?>
      <div class="an-alle">
        <button type="button" class="btn btn-outline-success btn-sm" id="anAlleDa"><i class="bi bi-check2-all me-1"></i>Alle offenen als «da»</button>
      </div>
      <p class="text-muted small"><i class="bi bi-info-circle me-1"></i>Erneutes Antippen der aktiven Taste setzt auf «nicht erfasst» zurück. Auswertung im Admin unter Einsatzplanung.</p>
    <?php endif; ?>
  <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
</div>

<script>
(function () {
  const csrf = (document.getElementById('csrfToken') || {}).value || '';
  const planId = <?= (int)$planId ?>, terminId = <?= (int)($termin['id'] ?? 0) ?>;
  function zaehlen() {
    document.getElementById('anDa').textContent = document.querySelectorAll('.an-row.da').length;
    document.getElementById('anNein').textContent = document.querySelectorAll('.an-row.nein').length;
    document.getElementById('anOffen').textContent = document.querySelectorAll('.an-row:not(.da):not(.nein)').length;
  }
  function senden(data, ok) {
    const body = new URLSearchParams(Object.assign({ csrf_token: csrf }, data));
    fetch('../api/einsatz_anwesenheit.php', { method: 'POST', body, credentials: 'same-origin' })
      .then(r => r.json()).then(r => { if (r && r.success) ok(r); else msvToast((r && (r.message || r.error)) || 'Fehler', 'error'); })
      .catch(() => msvToast('Keine Verbindung', 'error'));
  }
  document.querySelectorAll('.an-row .an-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const row = this.closest('.an-row'), wert = this.dataset.wert;
      const aktiv = (wert === '1' && row.classList.contains('da')) || (wert === '0' && row.classList.contains('nein'));
      senden({ action: 'set', slot_id: row.dataset.slot, anwesend: aktiv ? '' : wert }, r => {
        row.classList.remove('da', 'nein');
        if (r.anwesend === 1) row.classList.add('da'); else if (r.anwesend === 0) row.classList.add('nein');
        zaehlen();
      });
    });
  });
  const alle = document.getElementById('anAlleDa');
  if (alle) alle.addEventListener('click', function () {
    senden({ action: 'alle', plan_id: planId, termin_id: terminId, anwesend: '1' }, r => {
      document.querySelectorAll('.an-row:not(.da):not(.nein)').forEach(row => row.classList.add('da'));
      zaehlen(); msvToast(r.message, 'success');
    });
  });
})();
</script>

<?php include 'portal_footer.php'; ?>
