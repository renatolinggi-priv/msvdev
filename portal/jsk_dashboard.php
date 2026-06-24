<?php
// portal/jsk_dashboard.php - Übersicht fuer Jungschuetzen: eigene Schiess-Termin-Anmeldungen
$portal_page_title = 'Jungschützen';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();

// Nur Jungschuetzen (Admin zum Testen erlaubt)
if (!isJungschuetze() && !isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$db   = getDB();
$jsId = (int) (getJungschuetzeId() ?? 0);
$featureAktiv = jskFeatureAktiv();

$anfragen = [];
if ($jsId > 0) {
    $stmt = $db->prepare(
        "SELECT a.id, a.datum, a.zeit, a.bemerkung, a.status, a.betreut_am,
                bu.full_name AS betreuer_name
           FROM jsk_betreuung_anfragen a
           LEFT JOIN users bu ON bu.id = a.betreut_von_user_id
          WHERE a.jungschuetze_id = ?
          ORDER BY a.datum DESC, a.id DESC"
    );
    $stmt->execute([$jsId]);
    $anfragen = $stmt->fetchAll();
}

// Info-/Willkommensblock (aus settings)
$infoTitel = '';
$infoText  = '';
try {
    $st = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('jsk_info_titel','jsk_info_text')");
    $st->execute();
    foreach ($st->fetchAll() as $r) {
        if ($r['setting_key'] === 'jsk_info_titel') $infoTitel = (string) $r['setting_value'];
        if ($r['setting_key'] === 'jsk_info_text')  $infoText  = (string) $r['setting_value'];
    }
} catch (Throwable $e) { /* egal */ }

// Kommende JSK-Termine (wichtige_termine mit fuer_jsk = 1)
$termine = [];
try {
    $tt = $db->query("SELECT name, `date`, `time` FROM wichtige_termine WHERE fuer_jsk = 1 AND `date` >= CURDATE() ORDER BY `date` ASC, `time` ASC LIMIT 8");
    $termine = $tt->fetchAll();
} catch (Throwable $e) { $termine = []; }

include 'portal_header.php';
$csrf_token = ensureCsrfToken();

$statusBadge = function ($s) {
    switch ($s) {
        case 'offen':    return '<span class="badge bg-warning text-dark">Sucht Begleitung</span>';
        case 'vergeben': return '<span class="badge bg-success">Begleitung gefunden</span>';
        case 'abgesagt': return '<span class="badge bg-secondary">Abgesagt</span>';
        case 'erledigt': return '<span class="badge bg-info text-dark">Erledigt</span>';
        default:         return '<span class="badge bg-light text-dark">' . htmlspecialchars($s) . '</span>';
    }
};
?>

<style>
.jsk-hero { background: linear-gradient(135deg,#5eead4,#14b8a6); color:#0f3d38; border-radius:1rem; padding:1.5rem; margin-bottom:1.5rem; }
.jsk-card { border:1px solid #e2e8f0; border-radius:0.85rem; padding:1rem 1.25rem; margin-bottom:0.75rem; background:#fff; }
.jsk-card .datum { font-size:1.1rem; font-weight:700; }
.jsk-empty { text-align:center; color:#94a3b8; padding:2rem 1rem; }
</style>

<div class="container py-4" style="max-width:760px;">
  <div class="jsk-hero">
    <h4 class="mb-1"><i class="bi bi-bullseye me-2"></i>Hallo <?= htmlspecialchars($portal_user_name) ?>!</h4>
    <p class="mb-0">Melde dich für ein Schiesstraining an – ein Vereinsmitglied kümmert sich dann um dich.</p>
  </div>

  <?php if (trim($infoTitel) !== '' || trim($infoText) !== ''): ?>
    <div class="jsk-card" style="border-left:4px solid #14b8a6;">
      <?php if (trim($infoTitel) !== ''): ?>
        <div class="fw-bold mb-1"><i class="bi bi-info-circle me-1 text-info"></i><?= htmlspecialchars($infoTitel) ?></div>
      <?php endif; ?>
      <div class="text-muted"><?= nl2br(htmlspecialchars($infoText)) ?></div>
    </div>
  <?php endif; ?>

  <?php if ($termine): ?>
    <div class="jsk-card">
      <div class="fw-bold mb-2"><i class="bi bi-calendar3 me-1 text-info"></i>Nächste Jungschützen-Termine</div>
      <?php foreach ($termine as $i => $t): ?>
        <div class="d-flex justify-content-between py-2<?= $i ? ' border-top' : '' ?>">
          <span><i class="bi bi-calendar-event me-1 text-muted"></i><?= htmlspecialchars($t['name']) ?></span>
          <span class="text-muted text-nowrap ms-2"><?= date('d.m.Y', strtotime($t['date'])) ?><?= $t['time'] ? ' · ' . htmlspecialchars($t['time']) : '' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$featureAktiv): ?>
    <div class="alert alert-warning"><i class="bi bi-info-circle me-2"></i>Die Jungschützen-Betreuung ist derzeit deaktiviert. Bitte später erneut versuchen.</div>
  <?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Meine Anmeldungen</h5>
      <a href="jsk_termin.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Termin melden</a>
    </div>

    <?php if ($jsId <= 0): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Dein Konto ist noch keinem Jungschützen zugeordnet. Bitte wende dich an den Jungschützenleiter.</div>
    <?php elseif (!$anfragen): ?>
      <div class="jsk-card jsk-empty">
        <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem;"></i>
        Noch keine Anmeldungen. Melde deinen ersten Schiess-Termin an!
      </div>
    <?php else: ?>
      <div id="anfragenList">
      <?php foreach ($anfragen as $a):
        $datumDe = date('d.m.Y', strtotime($a['datum']));
        $istVergangen = strtotime($a['datum']) < strtotime('today');
        $kannAbsagen = in_array($a['status'], ['offen', 'vergeben'], true) && !$istVergangen;
      ?>
        <div class="jsk-card">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="datum"><i class="bi bi-calendar-event me-1"></i><?= $datumDe ?><?= $a['zeit'] ? ' · ' . htmlspecialchars($a['zeit']) : '' ?></div>
              <?php if ($a['bemerkung']): ?><div class="text-muted small mt-1"><?= htmlspecialchars($a['bemerkung']) ?></div><?php endif; ?>
              <div class="mt-2"><?= $statusBadge($a['status']) ?>
                <?php if ($a['status'] === 'vergeben' && $a['betreuer_name']): ?>
                  <span class="ms-1 small text-success"><i class="bi bi-person-check me-1"></i><?= htmlspecialchars($a['betreuer_name']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($kannAbsagen): ?>
              <button class="btn btn-outline-danger btn-sm js-cancel" data-id="<?= (int) $a['id'] ?>"><i class="bi bi-x-lg"></i></button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="alert alert-info mt-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
      <span><i class="bi bi-bell me-2"></i>Aktiviere Push-Benachrichtigungen, damit du erfährst, wenn dich jemand betreut.</span>
      <a href="benachrichtigungen.php" class="btn btn-outline-primary btn-sm">Einstellungen</a>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var csrf = <?php echo json_encode($csrf_token); ?>;
  document.querySelectorAll('.js-cancel').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = this.getAttribute('data-id');
      msvConfirm('Diese Anmeldung wirklich absagen?').then(function (r) {
        if (!r.isConfirmed) return;
        fetch('../api/jsk_anfrage.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
          body: JSON.stringify({ action: 'cancel', id: id, csrf_token: csrf })
        }).then(function (res) { return res.json(); }).then(function (data) {
          if (data.success) { msvToast(data.message, 'success'); setTimeout(function(){ location.reload(); }, 700); }
          else msvToast(data.message || 'Fehler', 'error');
        }).catch(function () { msvToast('Fehler bei der Verarbeitung', 'error'); });
      });
    });
  });
})();
</script>

<?php include 'portal_footer.php'; ?>
