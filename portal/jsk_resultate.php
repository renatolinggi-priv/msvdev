<?php
// portal/jsk_resultate.php - Eigene Resultate des Jungschuetzen (read-only)
$portal_page_title = 'Meine Resultate';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();

if (!isJungschuetze() && !isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$db   = getDB();
$jsId = (int) (getJungschuetzeId() ?? 0);

// Verfuegbare Jahre (ueber alle JSK-Resultattabellen)
$jahre = [];
if ($jsId > 0) {
    try {
        $jq = $db->prepare(
            "SELECT Jahr FROM endstich_jung WHERE JungschuetzeID = ?
             UNION SELECT Jahr FROM schwini_jung WHERE JungschuetzeID = ?
             UNION SELECT Jahr FROM zabig_jung WHERE JungschuetzeID = ?
             ORDER BY Jahr DESC"
        );
        $jq->execute([$jsId, $jsId, $jsId]);
        $jahre = array_map('intval', array_column($jq->fetchAll(), 'Jahr'));
    } catch (Throwable $e) { $jahre = []; }
}
$jahr = isset($_GET['jahr']) ? (int) $_GET['jahr'] : (int) ($jahre[0] ?? date('Y'));

// Helfer: Summe ueber Felder eines Rows
$sumF = function ($row, $prefix, $from, $to) {
    if (!$row) return null;
    $s = 0; $any = false;
    for ($i = $from; $i <= $to; $i++) {
        $v = $row[$prefix . $i] ?? null;
        if ($v !== null && $v !== '') { $s += (int) $v; $any = true; }
    }
    return $any ? $s : null;
};

$endstich = null; $schwini = null; $zabig = null;
if ($jsId > 0) {
    try { $s = $db->prepare("SELECT * FROM endstich_jung WHERE JungschuetzeID = ? AND Jahr = ? LIMIT 1"); $s->execute([$jsId, $jahr]); $endstich = $s->fetch(); } catch (Throwable $e) {}
    try { $s = $db->prepare("SELECT * FROM schwini_jung WHERE JungschuetzeID = ? AND Jahr = ? LIMIT 1");  $s->execute([$jsId, $jahr]); $schwini  = $s->fetch(); } catch (Throwable $e) {}
    try { $s = $db->prepare("SELECT * FROM zabig_jung WHERE JungschuetzeID = ? AND Jahr = ? LIMIT 1");    $s->execute([$jsId, $jahr]); $zabig    = $s->fetch(); } catch (Throwable $e) {}
}

$endstichTotal = $sumF($endstich, 'Schuss', 1, 10);
$schwiniP1 = $sumF($schwini, 'P1Schuss', 1, 6);
$schwiniP2 = $sumF($schwini, 'P2Schuss', 1, 6);
$schwiniBest = ($schwiniP1 === null && $schwiniP2 === null) ? null : max((int) $schwiniP1, (int) $schwiniP2);
$zabigTotal = $sumF($zabig, 'ZSchuss', 1, 6);

$hatResultate = ($endstich || $schwini || $zabig);

include 'portal_header.php';
?>

<style>
.res-card { border:1px solid #e2e8f0; border-radius:1rem; padding:1.25rem; margin-bottom:1rem; background:#fff; }
.res-card h6 { font-weight:700; margin-bottom:0.75rem; }
.res-total { font-size:1.4rem; font-weight:800; color:#3b5998; }
.shot-grid { display:flex; flex-wrap:wrap; gap:0.4rem; }
.shot { min-width:34px; text-align:center; padding:0.3rem 0.4rem; border-radius:0.5rem; background:#f1f5f9; font-weight:600; font-variant-numeric:tabular-nums; }
.res-empty { text-align:center; color:#94a3b8; padding:2.5rem 1rem; }
</style>

<div class="container py-4" style="max-width:760px;">
  <div class="portal-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h1><i class="bi bi-graph-up me-2"></i>Meine Resultate</h1>
      <p class="subtitle mb-0">Deine Schiessresultate</p>
    </div>
    <?php if (count($jahre) > 0): ?>
    <form method="get" class="d-flex align-items-center gap-2">
      <label class="text-muted small mb-0">Jahr</label>
      <select name="jahr" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <?php foreach ($jahre as $y): ?>
          <option value="<?= $y ?>" <?= $y === $jahr ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($jsId <= 0): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Dein Konto ist keinem Jungschützen zugeordnet.</div>
  <?php elseif (!$hatResultate): ?>
    <div class="res-card res-empty">
      <i class="bi bi-clipboard-x d-block mb-2" style="font-size:2rem;"></i>
      Für <?= (int) $jahr ?> sind noch keine Resultate erfasst.
    </div>
  <?php else: ?>

    <?php if ($endstich): ?>
    <div class="res-card">
      <div class="d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-bullseye me-1 text-club"></i>Endschiessen</h6>
        <?php if ($endstichTotal !== null): ?><span class="res-total"><?= $endstichTotal ?></span><?php endif; ?>
      </div>
      <div class="shot-grid mt-2">
        <?php for ($i = 1; $i <= 10; $i++): $v = $endstich['Schuss' . $i]; ?>
          <span class="shot"><?= ($v === null || $v === '') ? '–' : (int) $v ?></span>
        <?php endfor; ?>
      </div>
      <?php if ($endstich['Tiefschuss'] !== null && $endstich['Tiefschuss'] !== ''): ?>
        <div class="small text-muted mt-2">Tiefschuss: <?= (int) $endstich['Tiefschuss'] ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($schwini): ?>
    <div class="res-card">
      <div class="d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-arrow-repeat me-1 text-club"></i>Schwingen</h6>
        <?php if ($schwiniBest !== null): ?><span class="res-total"><?= $schwiniBest ?></span><?php endif; ?>
      </div>
      <div class="mt-2">
        <div class="small fw-semibold text-muted">Passe 1 <?= $schwiniP1 !== null ? '(' . $schwiniP1 . ')' : '' ?></div>
        <div class="shot-grid mb-2">
          <?php for ($i = 1; $i <= 6; $i++): $v = $schwini['P1Schuss' . $i]; ?>
            <span class="shot"><?= ($v === null || $v === '') ? '–' : (int) $v ?></span>
          <?php endfor; ?>
        </div>
        <div class="small fw-semibold text-muted">Passe 2 <?= $schwiniP2 !== null ? '(' . $schwiniP2 . ')' : '' ?></div>
        <div class="shot-grid">
          <?php for ($i = 1; $i <= 6; $i++): $v = $schwini['P2Schuss' . $i]; ?>
            <span class="shot"><?= ($v === null || $v === '') ? '–' : (int) $v ?></span>
          <?php endfor; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($zabig): ?>
    <div class="res-card">
      <div class="d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-clock me-1 text-club"></i>Zabigstich</h6>
        <?php if ($zabigTotal !== null): ?><span class="res-total"><?= $zabigTotal ?></span><?php endif; ?>
      </div>
      <div class="shot-grid mt-2">
        <?php for ($i = 1; $i <= 6; $i++): $v = $zabig['ZSchuss' . $i]; ?>
          <span class="shot"><?= ($v === null || $v === '') ? '–' : (int) $v ?></span>
        <?php endfor; ?>
      </div>
      <?php if ($zabig['Ansage'] !== null && $zabig['Ansage'] !== ''): ?>
        <div class="small text-muted mt-2">Ansage: <?= (int) $zabig['Ansage'] ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <p class="text-muted small"><i class="bi bi-info-circle me-1"></i>Angezeigt werden deine erfassten Resultate. Bei Fragen wende dich an den Jungschützenleiter.</p>
  <?php endif; ?>
</div>

<?php include 'portal_footer.php'; ?>
