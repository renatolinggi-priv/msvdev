<?php
// portal/einsatzplan.php – Einsatzplan als Tabelle (read-only) für Mitglieder.
// Datenquelle: Einsatzplanung (einsatz_plaene/…, Migration 050) über inc/einsatzplanung/plan_helpers.inc.php.
// Sichtbar ab Status «freigegeben»; Entwürfe sehen nur Vorstand/Admin (mit Hinweis).
// Eigene Positionen sind hervorgehoben; Fremdvereine erscheinen mit Namen oder als «SV Freienbach»/«SV Wollerau».
$portal_page_title = 'Einsatzplan';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
if (isJungschuetze()) { header('Location: jsk_dashboard.php'); exit(); }
require_once __DIR__ . '/../inc/einsatzplanung/plan_helpers.inc.php';

$db     = getDB();
$planId = (int)($_GET['id'] ?? 0);
$plan   = null;
$mitglieder = [];
try {
    $plan = $planId > 0 ? ep_plan_laden($db, $planId) : null;
    if ($plan) { $plan = ep_plan_ohne_vorschlaege($plan); $mitglieder = ep_mitglieder_map($db); }   // Vorschläge sind Vorstandsintern
} catch (Throwable $e) { $plan = null; }

$istVorstand = isAdmin() || isVorstand();
if ($plan && $plan['status'] === 'entwurf' && !$istVorstand) $plan = null;
$me = (int)($_SESSION['mitglied_id'] ?? 0);
if ($plan) $portal_page_title = $plan['titel'];

include 'portal_header.php';
?>

<style>
.epp-wrap { overflow-x: auto; }
.epp-grid { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.85rem; background: #fff; }
.epp-grid th, .epp-grid td { border: 1px solid var(--p-border, #e2e8f0); padding: 0.4rem 0.5rem; vertical-align: top; min-width: 150px; }
.epp-grid thead th { background: #f8f9fa; font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 600; }
.epp-grid thead th .epp-bez { text-transform: none; font-size: 0.85rem; color: #1e293b; }
.epp-grid thead th .epp-datum { text-transform: none; font-size: 0.82rem; color: #334155; font-weight: 500; }
.epp-grid thead th .epp-zeit { text-transform: none; font-weight: 400; }
.epp-grid .epp-fn { min-width: 180px; font-weight: 600; background: #fcfcfd; color: #1e293b; }
.epp-grid .epp-gruppe td { background: #f1f5f9; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; color: #475569; padding: 0.25rem 0.5rem; }
.epp-slot { display: block; padding: 0.1rem 0; white-space: nowrap; }
.epp-slot.ph { color: #94a3b8; font-style: italic; }
.epp-slot.fb { color: #2d4373; }
.epp-slot.wo { color: #2e7d32; }
.epp-slot.me { font-weight: 700; color: var(--primary-color, #2d4373); background: #fff7e6; border-radius: 0.3rem; padding: 0.1rem 0.35rem; margin-left: -0.35rem; }
.epp-card { border: 1px solid var(--p-border, #e2e8f0); border-radius: var(--p-radius, 0.6rem); background: #fff; margin-bottom: var(--p-3, 0.75rem); box-shadow: var(--p-shadow, none); }
.epp-card-head { padding: 0.5rem 0.75rem; background: #f8f9fa; border-bottom: 1px solid var(--p-border, #e2e8f0); border-radius: var(--p-radius, 0.6rem) var(--p-radius, 0.6rem) 0 0; }
.epp-card-head strong { display: block; }
.epp-card-body { padding: 0.4rem 0.75rem; }
.epp-row { display: flex; gap: 0.5rem; padding: 0.25rem 0; border-bottom: 1px dashed #eef1f6; font-size: 0.85rem; }
.epp-row:last-child { border-bottom: 0; }
.epp-row .epp-row-fn { flex: 0 0 44%; color: #64748b; }
.epp-legend { display: flex; flex-wrap: wrap; gap: 0.75rem 1rem; font-size: 0.75rem; color: #64748b; margin-top: 0.6rem; }
.epp-fuss { font-size: 0.85rem; color: #475569; margin-top: 1rem; }
.epp-fuss p { margin-bottom: 0.2rem; }
</style>

<div class="portal-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h1><i class="bi bi-person-lines-fill me-2"></i><?= htmlspecialchars($plan ? $plan['titel'] : 'Einsatzplan') ?></h1>
    <?php if ($plan): ?><p class="subtitle mb-0"><?= htmlspecialchars(EP_TYPEN[$plan['typ']] ?? '') ?> <?= (int)$plan['jahr'] ?><?php if ($plan['status'] === 'freigegeben'): ?> · Namen der anderen Vereine können noch folgen<?php elseif ($plan['status'] === 'entwurf'): ?> · <strong>Entwurf</strong> (nur Vorstand sichtbar)<?php endif; ?></p><?php endif; ?>
  </div>
  <a href="einsatzplaene.php<?= $plan ? '?year=' . (int)$plan['jahr'] : '' ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Einsatzpläne</a>
</div>

<?php if (!$plan): ?>
  <div class="p-card"><div class="p-card-body text-center text-muted py-4"><i class="bi bi-inbox d-block mb-2" style="font-size:1.6rem;opacity:.5"></i>Dieser Einsatzplan ist nicht verfügbar.</div></div>
<?php else:
  $termine = $plan['termine'];
  $slotHtml = function (?array $s) use ($mitglieder, $me): string {
      if (!$s) return '<span class="epp-slot ph">–</span>';
      $text = ep_slot_text($s, $mitglieder);
      $cls = 'epp-slot';
      if ($text === '') { $text = 'offen'; $cls .= ' ph'; }
      elseif (($s['verein'] ?? 'msv') !== 'msv') { $cls .= ' ' . ($s['verein'] === 'wollerau' ? 'wo' : 'fb'); if (trim((string)$s['name_text']) === '') $cls .= ' ph'; }
      if ($me > 0 && (int)($s['mitglied_id'] ?? 0) === $me) $cls .= ' me';
      return '<span class="' . $cls . '">' . htmlspecialchars($text) . '</span>';
  };
  if ($plan['layout'] === 'funktion_x_termin'):
    $gruppen = ep_funktionen_gruppiert($plan['funktionen']);
?>
  <!-- Desktop: Tabelle -->
  <div class="epp-wrap d-none d-md-block">
    <table class="epp-grid">
      <thead><tr><th class="epp-fn">Funktion</th>
        <?php foreach ($termine as $t): ?><th><?php if (trim((string)$t['bezeichnung']) !== ''): ?><div class="epp-bez"><?= htmlspecialchars($t['bezeichnung']) ?></div><?php endif; ?><div class="epp-datum"><?= htmlspecialchars(ep_datum_lang($t['datum'])) ?></div><div class="epp-zeit"><?= htmlspecialchars(ep_zeit_text($t)) ?></div></th><?php endforeach; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($gruppen as $g): ?>
        <?php if ($g['gruppe'] !== ''): ?><tr class="epp-gruppe"><td colspan="<?= count($termine) + 1 ?>"><?= htmlspecialchars($g['gruppe']) ?></td></tr><?php endif; ?>
        <?php foreach ($g['funktionen'] as $f): $anz = max(1, (int)$f['anzahl']); ?>
        <tr><td class="epp-fn"><?= htmlspecialchars($f['bezeichnung']) ?></td>
          <?php foreach ($termine as $t): ?><td><?php for ($p = 1; $p <= $anz; $p++) echo $slotHtml($plan['slot_index'][$t['id'] . '|' . $f['id'] . '|' . $p] ?? null); ?></td><?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <!-- Mobile: Karte pro Termin -->
  <div class="d-md-none">
    <?php foreach ($termine as $t): ?>
    <div class="epp-card">
      <div class="epp-card-head"><strong><?= htmlspecialchars(trim((string)$t['bezeichnung']) !== '' ? $t['bezeichnung'] . ' · ' : '') . htmlspecialchars(ep_datum_lang($t['datum'])) ?></strong><span class="text-muted small"><?= htmlspecialchars(ep_zeit_text($t)) ?></span></div>
      <div class="epp-card-body">
        <?php foreach ($gruppen as $g): foreach ($g['funktionen'] as $f): $anz = max(1, (int)$f['anzahl']); ?>
          <div class="epp-row"><div class="epp-row-fn"><?= htmlspecialchars(($g['gruppe'] !== '' ? $g['gruppe'] . ': ' : '') . $f['bezeichnung']) ?></div>
            <div><?php for ($p = 1; $p <= $anz; $p++) echo $slotHtml($plan['slot_index'][$t['id'] . '|' . $f['id'] . '|' . $p] ?? null); ?></div></div>
        <?php endforeach; endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="epp-legend"><span><span class="epp-slot me" style="display:inline;margin:0">Dein Einsatz</span></span><span class="fb">SV Freienbach</span><span class="wo">SV Wollerau</span><span class="ph">kursiv = Name folgt</span></div>

<?php else: $personen = ep_chilbi_personen($plan, $mitglieder); ?>
  <!-- Desktop: Personen × Schichten -->
  <div class="epp-wrap d-none d-md-block">
    <table class="epp-grid">
      <thead><tr><th class="epp-fn">Name</th>
        <?php foreach ($termine as $t): ?><th><div class="epp-datum"><?= htmlspecialchars(ep_datum_kurz($t['datum'])) ?></div><div class="epp-zeit"><?= htmlspecialchars(ep_zeit_text($t)) ?></div></th><?php endforeach; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($personen as $p): $m = $p['mitglied']; $istIch = $me > 0 && (int)($m['ID'] ?? 0) === $me; ?>
        <tr><td class="epp-fn <?= $istIch ? 'text-primary' : '' ?>"><?= htmlspecialchars(ep_name_vorname($m)) ?><?= $istIch ? ' <i class="bi bi-person-check"></i>' : '' ?></td>
          <?php foreach ($termine as $t): $z = $p['zellen'][(int)$t['id']] ?? []; ?><td><?= $z ? '<span class="epp-slot' . ($istIch ? ' me' : '') . '">' . htmlspecialchars(implode(' / ', array_map(fn($f) => $f['bezeichnung'], $z))) . '</span>' : '<span class="epp-slot ph">–</span>' ?></td><?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$personen): ?><tr><td colspan="<?= count($termine) + 1 ?>" class="text-center text-muted py-3">Noch niemand eingeteilt.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <!-- Mobile: Karte pro Schicht -->
  <div class="d-md-none">
    <?php foreach ($termine as $t): ?>
    <div class="epp-card">
      <div class="epp-card-head"><strong><?= htmlspecialchars(ep_datum_kurz($t['datum'])) ?></strong><span class="text-muted small"><?= htmlspecialchars(ep_zeit_text($t)) ?></span></div>
      <div class="epp-card-body">
        <?php $n = 0; foreach ($personen as $p): $z = $p['zellen'][(int)$t['id']] ?? []; if (!$z) continue; $n++; $m = $p['mitglied']; $istIch = $me > 0 && (int)($m['ID'] ?? 0) === $me; ?>
          <div class="epp-row"><div class="epp-row-fn <?= $istIch ? 'fw-bold text-primary' : '' ?>"><?= htmlspecialchars(ep_name_vorname($m)) ?></div><div><?= htmlspecialchars(implode(' / ', array_map(fn($f) => $f['bezeichnung'], $z))) ?></div></div>
        <?php endforeach; if ($n === 0): ?><div class="text-muted small py-1">Niemand eingeteilt.</div><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

  <?php if (trim((string)$plan['fusstext']) !== ''): ?>
  <div class="epp-fuss"><?php foreach (preg_split('/\r?\n/', trim($plan['fusstext'])) as $i => $z): ?><p class="<?= $i === 0 ? 'fw-semibold' : '' ?>"><?= htmlspecialchars(trim($z)) ?></p><?php endforeach; ?></div>
  <?php endif; ?>
  <p class="text-muted small mt-3"><i class="bi bi-info-circle me-1"></i>Deine Einsätze findest du auch unter <a href="meine_einsaetze.php">Meine Einsätze</a> – dort kannst du sie abtauschen.</p>
<?php endif; ?>

<?php include 'portal_footer.php'; ?>
