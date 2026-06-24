<?php
// portal/jsk_termin.php - Jungschuetze meldet einen konkreten Schiess-Termin an
$portal_page_title = 'Schiess-Termin melden';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();

if (!isJungschuetze() && !isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$featureAktiv = jskFeatureAktiv();

// Kommende Mittwoche fuer die Schnellauswahl berechnen
$mittwoche = [];
$d = new DateTime('today');
// 3 = Mittwoch (ISO-8601: Mo=1 ... So=7)
$diff = (3 - (int) $d->format('N') + 7) % 7;
$d->modify('+' . $diff . ' days');
for ($i = 0; $i < 6; $i++) {
    $mittwoche[] = ['iso' => $d->format('Y-m-d'), 'label' => $d->format('d.m.Y')];
    $d->modify('+7 days');
}
$minDate = (new DateTime('today'))->format('Y-m-d');

include 'portal_header.php';
$csrf_token = ensureCsrfToken();
?>

<style>
.wd-quick { display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem; }
.wd-quick .btn { border-radius:2rem; }
.termin-card { border:1px solid #e2e8f0; border-radius:1rem; padding:1.5rem; background:#fff; }
</style>

<div class="container py-4" style="max-width:620px;">
  <h4 class="mb-3"><i class="bi bi-calendar-plus me-2"></i>Schiess-Termin melden</h4>

  <?php if (!$featureAktiv): ?>
    <div class="alert alert-warning"><i class="bi bi-info-circle me-2"></i>Die Jungschützen-Betreuung ist derzeit deaktiviert.</div>
    <a href="jsk_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
  <?php else: ?>
    <div class="termin-card">
      <p class="text-muted">Wähle ein Datum, an dem du schiessen möchtest. Mitglieder werden benachrichtigt und können die Begleitung übernehmen.</p>

      <label class="form-label fw-semibold">Schnellauswahl (Mittwoch)</label>
      <div class="wd-quick" id="quickDates">
        <?php foreach ($mittwoche as $m): ?>
          <button type="button" class="btn btn-outline-info btn-sm" data-date="<?= $m['iso'] ?>"><?= $m['label'] ?></button>
        <?php endforeach; ?>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="datum">Datum *</label>
        <input type="date" class="form-control" id="datum" min="<?= $minDate ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold" for="zeit">Uhrzeit (optional)</label>
        <input type="text" class="form-control" id="zeit" placeholder="z.B. 18:30 oder nachmittags">
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold" for="bemerkung">Bemerkung (optional)</label>
        <textarea class="form-control" id="bemerkung" rows="2" maxlength="500" placeholder="z.B. brauche eine Mitfahrgelegenheit"></textarea>
      </div>

      <div class="d-flex gap-2">
        <a href="jsk_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
        <button type="button" class="btn btn-primary flex-grow-1" id="submitBtn"><i class="bi bi-send me-1"></i>Anmelden</button>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var csrf = <?php echo json_encode($csrf_token); ?>;
  var dateInput = document.getElementById('datum');

  document.querySelectorAll('#quickDates button').forEach(function (b) {
    b.addEventListener('click', function () {
      dateInput.value = this.getAttribute('data-date');
      document.querySelectorAll('#quickDates button').forEach(function (x) { x.classList.remove('active'); });
      this.classList.add('active');
    });
  });

  var btn = document.getElementById('submitBtn');
  if (btn) btn.addEventListener('click', function () {
    var datum = dateInput.value;
    if (!datum) { msvToast('Bitte ein Datum wählen', 'warning'); return; }
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>...';
    fetch('../api/jsk_anfrage.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({
        action: 'create',
        datum: datum,
        zeit: document.getElementById('zeit').value,
        bemerkung: document.getElementById('bemerkung').value,
        csrf_token: csrf
      })
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (data.success) {
        msvToast(data.message, 'success');
        setTimeout(function () { location.href = 'jsk_dashboard.php'; }, 900);
      } else {
        msvToast(data.message || 'Fehler', 'error');
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-send me-1"></i>Anmelden';
      }
    }).catch(function () {
      msvToast('Fehler bei der Verarbeitung', 'error');
      btn.disabled = false; btn.innerHTML = '<i class="bi bi-send me-1"></i>Anmelden';
    });
  });
})();
</script>

<?php include 'portal_footer.php'; ?>
