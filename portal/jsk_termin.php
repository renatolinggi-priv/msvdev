<?php
// portal/jsk_termin.php - Jungschuetze stellt eine Schiessanfrage (konkretes Datum)
$portal_page_title = 'Schiessanfrage';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();

if (!isJungschuetze() && !isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$db = getDB();
$featureAktiv = jskFeatureAktiv();

// Geflaggte Jungschützen-Termine (aus „Wichtige Termine") für die Schnellauswahl
$jskTermine = [];
try {
    $tt = $db->query("SELECT ID, name, `date`, `time` FROM wichtige_termine WHERE fuer_jsk = 1 AND `date` >= CURDATE() ORDER BY `date` ASC, `time` ASC LIMIT 12");
    $jskTermine = $tt->fetchAll();
} catch (Throwable $e) { $jskTermine = []; }

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
  <div class="portal-page-header">
    <h1><i class="bi bi-calendar-plus me-2"></i>Schiessanfrage</h1>
    <p class="subtitle">Termin für ein Schiesstraining anfragen</p>
  </div>

  <?php if (!$featureAktiv): ?>
    <div class="alert alert-warning"><i class="bi bi-info-circle me-2"></i>Die Jungschützen-Betreuung ist derzeit deaktiviert.</div>
    <a href="jsk_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
  <?php else: ?>
    <div class="termin-card">
      <p class="text-muted">Wähle ein Datum, an dem du schiessen möchtest. Mitglieder werden benachrichtigt und können die Begleitung übernehmen.</p>
      <div class="alert alert-light border small py-2">
        <i class="bi bi-info-circle me-1"></i><strong>Wann brauche ich eine Schiessanfrage?</strong>
        An den ausgeschriebenen Jungschützen-Terminen ist die Leitung vor Ort – dort meldest du dich unter
        <a href="jsk_termine.php">Termine</a> einfach an oder ab. Eine Anfrage brauchst du, wenn du <em>zusätzlich</em>
        oder an einem anderen Tag schiessen willst und dafür eine Begleitung suchst.
      </div>

      <?php if ($jskTermine): ?>
        <label class="form-label fw-semibold">Geplante Jungschützen-Termine <span class="text-muted fw-normal small">(Begleitung zusätzlich anfragen)</span></label>
        <div class="mb-3" id="quickTermine">
          <?php foreach ($jskTermine as $t): $td = date('d.m.Y', strtotime($t['date'])); ?>
            <button type="button" class="btn btn-outline-club btn-sm w-100 text-start mb-1 quick-date"
                    data-date="<?= htmlspecialchars($t['date'], ENT_QUOTES) ?>"
                    data-termin="<?= (int) $t['ID'] ?>"
                    data-zeit="<?= htmlspecialchars($t['time'] ?? '', ENT_QUOTES) ?>">
              <i class="bi bi-calendar-event me-1"></i><strong><?= $td ?></strong>
              <?= $t['time'] ? ' · ' . htmlspecialchars($t['time']) : '' ?>
              · <?= htmlspecialchars($t['name']) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <div class="text-muted small mb-1">oder ein anderes Datum:</div>
      <?php endif; ?>

      <label class="form-label fw-semibold">Schnellauswahl (Mittwoch)</label>
      <div class="wd-quick" id="quickDates">
        <?php foreach ($mittwoche as $m): ?>
          <button type="button" class="btn btn-outline-club btn-sm quick-date" data-date="<?= $m['iso'] ?>"><?= $m['label'] ?></button>
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
        <button type="button" class="btn btn-club flex-grow-1" id="submitBtn"><i class="bi bi-send me-1"></i>Anmelden</button>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var csrf = <?php echo json_encode($csrf_token); ?>;
  var dateInput = document.getElementById('datum');
  var terminId = 0;   // Bezug zum ausgeschriebenen Termin (nur bei Schnellauswahl)

  dateInput.addEventListener('change', function () { terminId = 0; document.querySelectorAll('.quick-date').forEach(function (x) { x.classList.remove('active'); }); });
  document.querySelectorAll('.quick-date').forEach(function (b) {
    b.addEventListener('click', function () {
      dateInput.value = this.getAttribute('data-date');
      terminId = parseInt(this.getAttribute('data-termin') || '0', 10);
      var z = this.getAttribute('data-zeit');
      if (z) document.getElementById('zeit').value = z;
      document.querySelectorAll('.quick-date').forEach(function (x) { x.classList.remove('active'); });
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
        termin_id: terminId,
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
