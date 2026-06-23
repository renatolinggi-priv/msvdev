<?php
/* =============================================
 * FILE: /admin/aktualisierung.php
 * PURPOSE: Datenbank aktualisieren — ausstehende SQL-Migrationen anzeigen
 *          und ausfuehren (nur Admin).
 * ============================================= */
require_once __DIR__ . '/../inc/session_config.inc.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../inc/remember_me.inc.php';
require_once __DIR__ . '/../inc/migrations.inc.php';
if (!isset($_SESSION['user_id'])) {
    restoreSessionFromToken();
}

// Nur Admin
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin' || (int)($_SESSION['user_id'] ?? 0) === 1;
if (!$isAdmin) {
    http_response_code(403);
    die('Kein Zugriff');
}

// CSRF-Token sicherstellen
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$migrationsDir = __DIR__ . '/../migrations';
$db = getDB();

// POST-Aktionen
$results = null;
$baselineCount = null;
$flashError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $flashError = 'Ungültiger CSRF-Token — bitte Seite neu laden.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'baseline') {
            $baselineCount = markBaseline($db, $migrationsDir);
        } elseif ($action === 'run') {
            $results = runPendingMigrations($db, $migrationsDir);
        }
    }
}

// Zustand einsammeln
$applied = appliedMigrations($db);
$trackingEmpty = count($applied) === 0;
$files = migrationFiles($migrationsDir);
$pendingFiles = [];
$appliedFiles = [];
foreach ($files as $file) {
    $base = basename($file);
    if (in_array($base, $applied, true)) $appliedFiles[] = $base;
    else                                  $pendingFiles[] = $base;
}

$page_specific_css = <<<'CSS'
.akt-wrapper { max-width: 860px; }
.akt-card {
  border: 1px solid #e2e8f0; border-radius: 12px; background: #fff;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 1rem; overflow: hidden;
}
.akt-card-header {
  padding: 0.85rem 1.1rem; font-weight: 600; color: #334155;
  border-bottom: 1px solid #eef2f7; background: linear-gradient(135deg,#f8fafc,#eef2f7);
  display: flex; align-items: center; justify-content: space-between;
}
.akt-card-header .count {
  font-size: 0.75rem; font-weight: 600; background: #e2e8f0; color: #475569;
  padding: 1px 9px; border-radius: 10px;
}
.akt-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.akt-table th {
  text-align: left; padding: 0.45rem 1.1rem; font-size: 0.68rem; text-transform: uppercase;
  letter-spacing: 0.5px; color: #64748b; border-bottom: 1px solid #eef2f7; background: #f8fafc;
}
.akt-table td { padding: 0.45rem 1.1rem; border-bottom: 1px solid #f1f5f9; }
.akt-table tr:last-child td { border-bottom: none; }
.akt-table code { font-size: 0.82rem; color: #1e293b; }
.akt-collapse-chevron { transition: transform .2s ease; }
[aria-expanded="true"] .akt-collapse-chevron { transform: rotate(180deg); }
CSS;

// header.inc.php aus inc/ einbinden
chdir(__DIR__ . '/../inc');
include 'header.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-12 col-lg-11 col-12 ps-0">
      <div class="main-content-wrapper">

        <div class="row mb-3 d-none d-md-flex">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-database-gear me-2"></i>Datenbank aktualisieren
            </h2>
          </div>
        </div>

        <div class="content-background">
          <div class="akt-wrapper">

            <p class="text-muted small mb-3">
              Hier werden ausstehende Datenbank-Migrationen (SQL-Dateien aus dem Ordner
              <code>migrations/</code>) angewendet. Bereits angewendete Migrationen werden
              übersprungen.
            </p>

            <?php if ($flashError): ?>
              <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($baselineCount !== null): ?>
              <div class="alert alert-success">
                <i class="bi bi-check2-circle me-1"></i>
                Baseline gesetzt: <strong><?= (int)$baselineCount ?></strong> vorhandene Migration(en) als „bereits angewendet" markiert.
              </div>
            <?php endif; ?>

            <?php if ($results !== null): ?>
              <?php if (!empty($results['applied'])): ?>
                <div class="alert alert-success">
                  <strong><?= count($results['applied']) ?> Migration(en) angewendet:</strong>
                  <ul class="mb-0">
                    <?php foreach ($results['applied'] as $f): ?><li><code><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></code></li><?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
              <?php if (!empty($results['errors'])): ?>
                <div class="alert alert-danger">
                  <strong>Fehler (Lauf abgebrochen):</strong>
                  <ul class="mb-0">
                    <?php foreach ($results['errors'] as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
              <?php if (empty($results['applied']) && empty($results['errors'])): ?>
                <div class="alert alert-info">Keine ausstehenden Migrationen.</div>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($trackingEmpty && !empty($files)): ?>
              <!-- Erstmalige Einrichtung: Baseline -->
              <div class="akt-card" style="border-color:#fcd34d;">
                <div class="akt-card-header" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);">
                  <span><i class="bi bi-info-circle me-2 text-warning"></i>Erstmalige Einrichtung</span>
                </div>
                <div class="p-3">
                  <p class="mb-2">
                    Das Update-System wird zum ersten Mal verwendet. Die Datenbank enthält bereits
                    Tabellen aus zuvor manuell eingespielten Migrationen. Markiere die aktuell
                    vorhandenen <strong><?= count($files) ?></strong> Migrationsdatei(en) einmalig als
                    <em>bereits angewendet</em> — sie werden dabei <strong>nicht</strong> erneut ausgeführt.
                  </p>
                  <p class="text-muted small mb-3">
                    Danach werden nur noch neu hinzukommende Migrationen ausgeführt. Führe diesen
                    Schritt nur aus, wenn die bestehenden Migrationen tatsächlich schon in der DB sind
                    (Normalfall bei einer laufenden Installation).
                  </p>
                  <form method="POST" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="baseline">
                    <button type="submit" class="btn btn-warning">
                      <i class="bi bi-flag me-1"></i>Bestehende als angewendet markieren (Baseline)
                    </button>
                  </form>
                </div>
              </div>
            <?php endif; ?>

            <!-- Ausstehend -->
            <div class="akt-card">
              <div class="akt-card-header">
                <span><i class="bi bi-hourglass-split me-2"></i>Ausstehend <span class="count"><?= count($pendingFiles) ?></span></span>
                <?php if (!empty($pendingFiles) && !$trackingEmpty): ?>
                  <form method="POST" class="m-0" id="aktRunForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="run">
                    <button type="button" class="btn btn-success btn-sm" id="aktRunBtn"
                            data-pending="<?= count($pendingFiles) ?>">
                      <i class="bi bi-play-fill me-1"></i>Ausstehende ausführen
                    </button>
                  </form>
                <?php endif; ?>
              </div>
              <?php if (empty($pendingFiles)): ?>
                <p class="text-muted text-center mb-0 py-3">
                  <i class="bi bi-check2-circle me-1 text-success"></i>Alle Migrationen sind aktuell. Nichts zu tun.
                </p>
              <?php else: ?>
                <table class="akt-table">
                  <thead><tr><th>Datei</th><th style="width:120px">Status</th></tr></thead>
                  <tbody>
                    <?php foreach ($pendingFiles as $base): ?>
                      <tr>
                        <td><code><?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?></code></td>
                        <td><span class="badge bg-warning text-dark">ausstehend</span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>

            <!-- Bereits angewendet -->
            <div class="akt-card">
              <div class="akt-card-header" data-bs-toggle="collapse" data-bs-target="#aktAppliedBody"
                   aria-expanded="false" aria-controls="aktAppliedBody" style="cursor:pointer; user-select:none;">
                <span><i class="bi bi-check2-circle me-2 text-success"></i>Bereits angewendet <span class="count"><?= count($appliedFiles) ?></span></span>
                <i class="bi bi-chevron-down akt-collapse-chevron text-muted"></i>
              </div>
              <div class="collapse" id="aktAppliedBody">
                <?php if (empty($appliedFiles)): ?>
                  <p class="text-muted text-center mb-0 py-3">— noch keine —</p>
                <?php else: ?>
                  <table class="akt-table">
                    <thead><tr><th>Datei</th><th style="width:120px">Status</th></tr></thead>
                    <tbody>
                      <?php foreach ($appliedFiles as $base): ?>
                        <tr>
                          <td class="text-muted"><code><?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?></code></td>
                          <td><span class="badge bg-success">angewendet</span></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php endif; ?>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Nav-Links fixen (Seite liegt in /admin/, Navbar-Links zeigen auf /inc/)
document.querySelectorAll('.navbar a[href], .offcanvas-nav a[href], #logoutModal a[href]').forEach(a => {
  const href = a.getAttribute('href');
  if (href && !href.startsWith('http') && !href.startsWith('/') && !href.startsWith('#') && !href.startsWith('../') && !href.startsWith('javascript')) {
    a.setAttribute('href', '../inc/' + href);
  }
});

// Migrationen ausführen — Bestätigung über SweetAlert2-Modal (statt nativem confirm)
(function () {
  const btn = document.getElementById('aktRunBtn');
  if (!btn) return;
  btn.addEventListener('click', async function () {
    const count = parseInt(btn.getAttribute('data-pending'), 10) || 0;
    const result = await Swal.fire({
      icon: 'warning',
      title: 'Migrationen ausführen?',
      html: 'Es ' + (count === 1 ? 'wird <strong>1</strong> ausstehende Migration' : 'werden <strong>' + count + '</strong> ausstehende Migrationen')
            + ' auf der Datenbank ausgeführt.<br><span class="text-muted">Dieser Vorgang verändert die Datenbankstruktur und kann nicht automatisch rückgängig gemacht werden.</span>',
      showCancelButton: true,
      confirmButtonText: '<i class="bi bi-play-fill me-1"></i>Jetzt ausführen',
      cancelButtonText: 'Abbrechen',
      confirmButtonColor: '#198754',
      cancelButtonColor: '#6c757d',
      reverseButtons: true
    });
    if (result.isConfirmed) {
      document.getElementById('aktRunForm').submit();
    }
  });
})();
</script>

<?php
chdir(__DIR__ . '/../inc');
include 'footer.inc.php';
?>
