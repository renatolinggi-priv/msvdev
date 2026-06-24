<?php
// portal/jsk_betreuung.php - Board: offene Jungschuetzen-Anfragen uebernehmen ("Tinder")
$portal_page_title = 'Jungschützen betreuen';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();

// Jungschuetzen haben hier nichts zu suchen (Rollenweiche faengt sie ohnehin ab)
if (isJungschuetze()) { header('Location: jsk_dashboard.php'); exit; }

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$featureAktiv = jskFeatureAktiv();

// Ist der/die Eingeloggte aktivierter Betreuer?
$istBetreuer = false;
try {
    $s = $db->prepare('SELECT jsk_betreuung FROM benachrichtigung_prefs WHERE user_id = ?');
    $s->execute([$userId]);
    $istBetreuer = ((int) $s->fetchColumn() === 1);
} catch (Throwable $e) { $istBetreuer = false; }

$anfragen = [];
if ($featureAktiv && $istBetreuer) {
    $stmt = $db->prepare(
        "SELECT a.id, a.datum, a.zeit, a.bemerkung, a.status, a.betreut_von_user_id,
                j.Vorname, j.Name, bu.full_name AS betreuer_name
           FROM jsk_betreuung_anfragen a
           JOIN jungschuetzen j ON j.id = a.jungschuetze_id
           LEFT JOIN users bu ON bu.id = a.betreut_von_user_id
          WHERE a.datum >= CURDATE() AND a.status IN ('offen','vergeben')
          ORDER BY a.datum ASC, a.id ASC"
    );
    $stmt->execute();
    $anfragen = $stmt->fetchAll();
}

include 'portal_header.php';
$csrf_token = ensureCsrfToken();
?>

<style>
.jsk-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1rem; }
.jsk-tile { border:1px solid #e2e8f0; border-radius:1rem; padding:1.25rem; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.06); transition:box-shadow .2s; }
.jsk-tile:hover { box-shadow:0 6px 18px rgba(0,0,0,0.10); }
.jsk-tile.vergeben { opacity:0.7; background:#f8fafc; }
.jsk-tile .name { font-weight:700; font-size:1.05rem; }
.jsk-tile .datum { color:#0d9488; font-weight:600; }
.jsk-tile .bem { font-size:.85rem; color:#64748b; margin-top:.4rem; }
.jsk-empty { text-align:center; color:#94a3b8; padding:2.5rem 1rem; }
</style>

<div class="container py-4" style="max-width:920px;">
  <h4 class="mb-3"><i class="bi bi-people me-2"></i>Jungschützen betreuen</h4>

  <?php if (!$featureAktiv): ?>
    <div class="alert alert-warning"><i class="bi bi-info-circle me-2"></i>Die Jungschützen-Betreuung ist derzeit deaktiviert.</div>
  <?php elseif (!$istBetreuer): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span><i class="bi bi-bell me-2"></i>Aktiviere „Jungschützen-Betreuung" in deinen Einstellungen, um Anfragen zu sehen und zu übernehmen.</span>
      <a href="benachrichtigungen.php" class="btn btn-outline-primary btn-sm">Einstellungen</a>
    </div>
  <?php else: ?>
    <p class="text-muted">Wer zuerst übernimmt, betreut den Jungschützen. Bereits vergebene Termine sind ausgegraut.</p>

    <?php if (!$anfragen): ?>
      <div class="jsk-tile jsk-empty">
        <i class="bi bi-emoji-smile d-block mb-2" style="font-size:2rem;"></i>
        Aktuell keine offenen Anfragen. Du wirst benachrichtigt, sobald sich jemand meldet.
      </div>
    <?php else: ?>
      <div class="jsk-grid" id="jskGrid">
      <?php foreach ($anfragen as $a):
        $datumDe = date('d.m.Y', strtotime($a['datum']));
        $offen = ($a['status'] === 'offen');
        $mine = ((int) $a['betreut_von_user_id'] === $userId);
      ?>
        <div class="jsk-tile <?= $offen ? '' : 'vergeben' ?>" data-id="<?= (int) $a['id'] ?>">
          <div class="name"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($a['Vorname'] . ' ' . $a['Name']) ?></div>
          <div class="datum mt-1"><i class="bi bi-calendar-event me-1"></i><?= $datumDe ?><?= $a['zeit'] ? ' · ' . htmlspecialchars($a['zeit']) : '' ?></div>
          <?php if ($a['bemerkung']): ?><div class="bem"><i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars($a['bemerkung']) ?></div><?php endif; ?>
          <div class="mt-3">
            <?php if ($offen): ?>
              <button class="btn btn-primary btn-sm w-100 js-claim" data-id="<?= (int) $a['id'] ?>"><i class="bi bi-hand-thumbs-up me-1"></i>Ich kümmere mich</button>
            <?php elseif ($mine): ?>
              <div class="small text-success mb-2"><i class="bi bi-check-circle me-1"></i>Du betreust diesen Jungschützen</div>
              <button class="btn btn-outline-secondary btn-sm w-100 js-release" data-id="<?= (int) $a['id'] ?>"><i class="bi bi-arrow-counterclockwise me-1"></i>Freigeben</button>
            <?php else: ?>
              <div class="small text-muted"><i class="bi bi-person-check me-1"></i>Betreut von <strong><?= htmlspecialchars($a['betreuer_name'] ?? 'einem Mitglied') ?></strong></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script>
(function () {
  var csrf = <?php echo json_encode($csrf_token); ?>;
  function action(act, id, confirmMsg, btn) {
    var go = function () {
      if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
      fetch('../api/jsk_betreuung.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ action: act, id: id, csrf_token: csrf })
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.success) { msvToast(data.message, 'success'); setTimeout(function(){ location.reload(); }, 700); }
        else { msvToast(data.message || 'Fehler', 'error'); setTimeout(function(){ location.reload(); }, 1200); }
      }).catch(function () { msvToast('Fehler bei der Verarbeitung', 'error'); if (btn) btn.disabled = false; });
    };
    if (confirmMsg) { msvConfirm(confirmMsg).then(function (r) { if (r.isConfirmed) go(); }); }
    else go();
  }

  document.querySelectorAll('.js-claim').forEach(function (b) {
    b.addEventListener('click', function () { action('claim', this.getAttribute('data-id'), null, this); });
  });
  document.querySelectorAll('.js-release').forEach(function (b) {
    b.addEventListener('click', function () { action('release', this.getAttribute('data-id'), 'Betreuung wirklich freigeben?', this); });
  });
})();
</script>

<?php include 'portal_footer.php'; ?>
