<?php
// portal/jsk_termine.php – Jungschützen-Termine (aus „Wichtige Termine" mit fuer_jsk = 1)
// Jungschütze gibt pro kommendem Termin an, ob er teilnimmt (Standard: ja).
$portal_page_title = 'Termine';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();

if (!isJungschuetze() && !isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$db   = getDB();
$jsId = (int) (getJungschuetzeId() ?? 0);

// Eine Liste: kommende zuerst (aufsteigend), dann vergangene (absteigend)
$termine = [];
try {
    $kommende   = $db->query("SELECT ID, name, `date`, `time` FROM wichtige_termine WHERE fuer_jsk = 1 AND `date` >= CURDATE() ORDER BY `date` ASC, `time` ASC")->fetchAll();
    $vergangene = $db->query("SELECT ID, name, `date`, `time` FROM wichtige_termine WHERE fuer_jsk = 1 AND `date` < CURDATE() ORDER BY `date` DESC, `time` DESC")->fetchAll();
    $termine = array_merge($kommende, $vergangene);
} catch (Throwable $e) { $termine = []; }

// Bisherige Teilnahme-Antworten des Jungschützen (fehlt -> Standard teilnehmen)
$teilnahmeMap = [];
if ($jsId > 0) {
    try {
        $tm = $db->prepare("SELECT termin_id, teilnahme FROM jsk_termin_teilnahme WHERE jungschuetze_id = ?");
        $tm->execute([$jsId]);
        foreach ($tm->fetchAll() as $r) $teilnahmeMap[(int) $r['termin_id']] = (int) $r['teilnahme'];
    } catch (Throwable $e) { $teilnahmeMap = []; }
}

$wochentage = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
$heute = strtotime('today');

include 'portal_header.php';
$csrf_token = ensureCsrfToken();
?>

<style>
.tm-list { max-width:640px; }
.tm-card { display:flex; align-items:center; gap:0.9rem; border:1px solid #e2e8f0; border-radius:0.85rem;
  padding:0.75rem 1rem; margin-bottom:0.6rem; background:#fff; }
.tm-date { flex-shrink:0; width:54px; text-align:center; background:#3b5998; color:#fff; border-radius:0.6rem; padding:0.35rem 0; line-height:1.1; }
.tm-date .d { font-size:1.1rem; font-weight:800; }
.tm-date .wd { font-size:0.68rem; text-transform:uppercase; opacity:0.85; }
.tm-body { min-width:0; flex:1; }
.tm-name { font-weight:600; }
.tm-time { font-size:0.85rem; color:#64748b; }
.tm-rsvp { flex-shrink:0; text-align:right; }
.tm-rsvp .form-check-input { cursor:pointer; }
.tm-rsvp .form-check-input:checked { background-color:#3b5998; border-color:#3b5998; }
.tm-rsvp-label { display:block; font-size:0.72rem; color:#64748b; }
.tm-empty { text-align:center; color:#94a3b8; padding:2.5rem 1rem; border:1px solid #e2e8f0; border-radius:0.85rem; background:#fff; }
.tm-card--past { opacity:0.55; }
.tm-card--past .tm-date { background:#94a3b8; }
</style>

<div class="container py-4">
  <div class="portal-page-header">
    <h1><i class="bi bi-calendar3 me-2"></i>Jungschützen-Termine</h1>
    <p class="subtitle">Trainings und Anlässe der Jungschützen</p>
  </div>

  <div class="tm-list">
    <?php if (!$termine): ?>
      <div class="tm-empty">
        <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem;"></i>
        Es sind keine Termine ausgeschrieben.
      </div>
    <?php else: ?>
      <?php foreach ($termine as $t):
        $ts = strtotime($t['date']);
        $past = $ts < $heute;
        $tid = (int) $t['ID'];
        $teilnimmt = !array_key_exists($tid, $teilnahmeMap) || $teilnahmeMap[$tid] === 1;
      ?>
        <div class="tm-card<?= $past ? ' tm-card--past' : '' ?>">
          <div class="tm-date">
            <div class="d"><?= date('d.m', $ts) ?></div>
            <div class="wd"><?= $wochentage[(int) date('w', $ts)] ?> <?= date('y', $ts) ?></div>
          </div>
          <div class="tm-body">
            <div class="tm-name"><?= htmlspecialchars($t['name']) ?></div>
            <?php if ($t['time']): ?><div class="tm-time"><i class="bi bi-clock me-1"></i><?= htmlspecialchars($t['time']) ?></div><?php endif; ?>
          </div>
          <?php if (!$past && $jsId > 0): ?>
            <div class="tm-rsvp">
              <div class="form-check form-switch m-0 d-flex justify-content-end">
                <input class="form-check-input tm-teilnahme" type="checkbox" data-termin="<?= $tid ?>" <?= $teilnimmt ? 'checked' : '' ?>>
              </div>
              <span class="tm-rsvp-label"><?= $teilnimmt ? 'Ich nehme teil' : 'Nicht dabei' ?></span>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <p class="text-muted small mt-3"><i class="bi bi-info-circle me-1"></i>Standardmässig bist du angemeldet. Schalte einen Termin aus, wenn du nicht teilnimmst.</p>
  </div>
</div>

<script>
(function () {
  var csrf = <?php echo json_encode($csrf_token); ?>;
  document.querySelectorAll('.tm-teilnahme').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var self = this;
      var val = self.checked ? 1 : 0;
      var label = self.closest('.tm-rsvp').querySelector('.tm-rsvp-label');
      fetch('../api/jsk_teilnahme.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ action: 'set', termin_id: self.getAttribute('data-termin'), teilnahme: val, csrf_token: csrf })
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d.success) { label.textContent = val ? 'Ich nehme teil' : 'Nicht dabei'; msvToast(val ? 'Du nimmst teil' : 'Abgemeldet', 'success'); }
        else { msvToast(d.message || 'Fehler', 'error'); self.checked = !self.checked; }
      }).catch(function () { msvToast('Fehler bei der Verarbeitung', 'error'); self.checked = !self.checked; });
    });
  });
})();
</script>

<?php include 'portal_footer.php'; ?>
