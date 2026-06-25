<?php
// portal/termine.php – Wichtige Vereinstermine fuer Mitglieder (read-only)
// Datenquelle: wichtige_termine (alle Eintraege, inkl. fuer_jsk). Jungschuetzen
// haben ihre eigene Seite jsk_termine.php -> die Portal-Rollenweiche (portal_header)
// leitet sie hier ohnehin auf jsk_dashboard.php um.
$portal_page_title = 'Termine';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();

$db = getDB();

// Verfuegbare Jahre fuer die Auswahl (absteigend), plus aktuelles Jahr garantiert.
$jahre = [];
try {
    foreach ($db->query("SELECT DISTINCT year FROM wichtige_termine WHERE year IS NOT NULL ORDER BY year DESC") as $r) {
        $jahre[] = (int) $r['year'];
    }
} catch (Throwable $e) { $jahre = []; }
$aktuellesJahr = (int) date('Y');
if (!in_array($aktuellesJahr, $jahre, true)) { array_unshift($jahre, $aktuellesJahr); rsort($jahre); }

$jahr = isset($_GET['year']) && ctype_digit((string) $_GET['year']) ? (int) $_GET['year'] : $aktuellesJahr;
if (!in_array($jahr, $jahre, true)) { $jahr = $aktuellesJahr; }

// Termine des gewaehlten Jahres laden, dann aufteilen:
// Kommende (>= heute) zuoberst aufsteigend, Vergangene darunter (neueste zuerst).
$termine = [];
try {
    $st = $db->prepare("SELECT ID, name, `date`, `time`, fuer_jsk FROM wichtige_termine WHERE year = ? ORDER BY `date` ASC, `time` ASC");
    $st->execute([$jahr]);
    $termine = $st->fetchAll();
} catch (Throwable $e) { $termine = []; }

$wochentage = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
$monateKurz = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
$heute = strtotime('today');

$kommende = [];
$vergangene = [];
foreach ($termine as $t) {
    if (strtotime($t['date']) >= $heute) { $kommende[] = $t; }
    else { $vergangene[] = $t; }
}
$vergangene = array_reverse($vergangene); // neueste vergangene zuerst

include 'portal_header.php';
?>

<style>
.tm-list { max-width:640px; }
.tm-toolbar { display:flex; align-items:center; justify-content:space-between; gap:0.75rem; margin-bottom:1rem; max-width:640px; flex-wrap:wrap; }
.tm-year-select { max-width:140px; }
.tm-abo-btn { white-space:nowrap; }
.tm-section-label { font-size:0.7rem; text-transform:uppercase; letter-spacing:0.04em; color:#94a3b8; font-weight:700; margin:0.9rem 0 0.35rem; }
.tm-section-label:first-of-type { margin-top:0; }
.tm-card { display:flex; align-items:center; gap:0.65rem; border:1px solid #e2e8f0; border-radius:0.6rem;
  padding:0.4rem 0.65rem; margin-bottom:0.3rem; background:#fff; }
.tm-card--next { border-color:#3b5998; box-shadow:0 0 0 1px #3b5998; }
.tm-date { flex-shrink:0; width:46px; text-align:center; background:#3b5998; color:#fff; border-radius:0.45rem; padding:0.18rem 0; line-height:1.08; }
.tm-date .wd { font-size:0.6rem; text-transform:uppercase; opacity:0.85; letter-spacing:0.02em; }
.tm-date .d { font-size:1.05rem; font-weight:800; }
.tm-date .mo { font-size:0.6rem; text-transform:uppercase; opacity:0.85; letter-spacing:0.02em; }
.tm-body { min-width:0; flex:1; }
.tm-name { font-weight:600; font-size:0.88rem; line-height:1.2; }
.tm-meta { font-size:0.76rem; color:#64748b; display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap; }
.tm-badge-next { flex-shrink:0; font-size:0.66rem; font-weight:700; color:#3b5998; background:rgba(59,89,152,0.1); border-radius:999px; padding:0.12rem 0.5rem; }
.tm-badge-jsk { font-size:0.64rem; font-weight:600; color:#92400e; background:#fef3c7; border-radius:999px; padding:0.08rem 0.4rem; }
.tm-empty { text-align:center; color:#94a3b8; padding:2.5rem 1rem; border:1px solid #e2e8f0; border-radius:0.85rem; background:#fff; max-width:640px; }
.tm-card--past { opacity:0.55; }
.tm-card--past .tm-date { background:#94a3b8; }
</style>

<div class="container py-2">
  <div class="tm-toolbar">
    <h4 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Wichtige Termine</h4>
    <div class="d-flex align-items-center gap-2 ms-auto">
      <?php if (count($jahre) > 1): ?>
      <select class="form-select form-select-sm tm-year-select" onchange="location.href='termine.php?year=' + this.value">
        <?php foreach ($jahre as $y): ?>
          <option value="<?= $y ?>" <?= $y === $jahr ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <a href="kalender_abo.php" class="btn btn-sm btn-outline-primary tm-abo-btn"><i class="bi bi-calendar-plus me-1"></i>Abonnieren</a>
    </div>
  </div>

  <div class="tm-list">
    <?php if (!$termine): ?>
      <div class="tm-empty">
        <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem;"></i>
        Für <?= $jahr ?> sind keine Termine ausgeschrieben.
      </div>
    <?php else: ?>
      <?php
      // Karten-Renderer (vermeidet Duplikate zwischen kommend/vergangen)
      $renderCard = function (array $t, bool $past, bool $isNext) use ($wochentage, $monateKurz) {
          $ts = strtotime($t['date']);
          ?>
          <div class="tm-card<?= $past ? ' tm-card--past' : '' ?><?= $isNext ? ' tm-card--next' : '' ?>">
            <div class="tm-date">
              <div class="wd"><?= $wochentage[(int) date('w', $ts)] ?></div>
              <div class="d"><?= date('j', $ts) ?></div>
              <div class="mo"><?= $monateKurz[(int) date('n', $ts)] ?></div>
            </div>
            <div class="tm-body">
              <div class="tm-name"><?= htmlspecialchars($t['name']) ?></div>
              <div class="tm-meta">
                <?php if (!empty($t['time'])): ?><span><i class="bi bi-clock me-1"></i><?= htmlspecialchars(substr($t['time'], 0, 5)) ?></span><?php endif; ?>
                <?php if (!empty($t['fuer_jsk'])): ?><span class="tm-badge-jsk"><i class="bi bi-mortarboard me-1"></i>Jungschützen</span><?php endif; ?>
              </div>
            </div>
            <?php if ($isNext): ?><span class="tm-badge-next">Nächster</span><?php endif; ?>
          </div>
      <?php }; ?>

      <?php if ($kommende): ?>
        <div class="tm-section-label">Kommende Termine</div>
        <?php foreach ($kommende as $i => $t) $renderCard($t, false, $i === 0); ?>
      <?php endif; ?>

      <?php if ($vergangene): ?>
        <div class="tm-section-label">Vergangen</div>
        <?php foreach ($vergangene as $t) $renderCard($t, true, false); ?>
      <?php endif; ?>
    <?php endif; ?>

    <p class="text-muted small mt-3"><i class="bi bi-info-circle me-1"></i>Termine im eigenen Handy-Kalender? <a href="kalender_abo.php">Kalender abonnieren</a> – neue Termine erscheinen dann automatisch.</p>
  </div>
</div>

<?php include 'portal_footer.php'; ?>
