<?php
// portal/jsk_profil.php – Jungschütze: eigener Anzeigename + Passwort
$portal_page_title = 'Meine Daten';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();

if (!isJungschuetze() && !isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$db   = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$me = ['full_name' => '', 'username' => '', 'email' => ''];
try {
    $st = $db->prepare('SELECT full_name, username, email FROM users WHERE id = ?');
    $st->execute([$userId]);
    $row = $st->fetch();
    if ($row) $me = $row;
} catch (Throwable $e) { /* egal */ }

include 'portal_header.php';
$csrf_token = ensureCsrfToken();
?>

<style>
.profil-card { border:1px solid #e2e8f0; border-radius:1rem; padding:1.25rem; margin-bottom:1rem; background:#fff; }
.profil-card h6 { font-weight:700; }
</style>

<div class="container py-4" style="max-width:560px;">
  <div class="portal-page-header">
    <h1><i class="bi bi-person-vcard me-2"></i>Meine Daten</h1>
    <p class="subtitle">Profil und Kontaktangaben</p>
  </div>

  <div class="profil-card">
    <h6 class="mb-1"><i class="bi bi-person me-1 text-club"></i>Anzeigename</h6>
    <p class="text-muted small mb-2">Dieser Name erscheint im Jungschützenchat.</p>
    <div class="mb-2">
      <input type="text" class="form-control" id="fullName" maxlength="100" value="<?= htmlspecialchars($me['full_name'], ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <button type="button" class="btn btn-club btn-sm" id="saveNameBtn"><i class="bi bi-save me-1"></i>Name speichern</button>
    <div class="small text-muted mt-2">Benutzername: <strong><?= htmlspecialchars($me['username']) ?></strong><?= $me['email'] ? ' · ' . htmlspecialchars($me['email']) : '' ?></div>
  </div>

  <div class="profil-card">
    <h6 class="mb-2"><i class="bi bi-key me-1 text-club"></i>Passwort ändern</h6>
    <div class="mb-2">
      <input type="password" class="form-control" id="pwCurrent" placeholder="Aktuelles Passwort" autocomplete="current-password">
    </div>
    <div class="mb-2">
      <input type="password" class="form-control" id="pwNew" placeholder="Neues Passwort (min. 8 Zeichen)" autocomplete="new-password">
    </div>
    <button type="button" class="btn btn-outline-club btn-sm" id="savePwBtn"><i class="bi bi-shield-lock me-1"></i>Passwort ändern</button>
  </div>
</div>

<script>
(function () {
  var csrf = <?php echo json_encode($csrf_token); ?>;
  function post(payload) {
    payload.csrf_token = csrf;
    return fetch('../api/jsk_profil.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); });
  }

  document.getElementById('saveNameBtn').addEventListener('click', function () {
    var name = document.getElementById('fullName').value.trim();
    if (name.length < 2) { msvToast('Bitte einen gültigen Namen angeben', 'warning'); return; }
    var b = this; b.disabled = true;
    post({ action: 'save_name', full_name: name })
      .then(function (d) { msvToast(d.message || (d.success ? 'Gespeichert' : 'Fehler'), d.success ? 'success' : 'error'); })
      .catch(function () { msvToast('Fehler beim Speichern', 'error'); })
      .finally(function () { b.disabled = false; });
  });

  document.getElementById('savePwBtn').addEventListener('click', function () {
    var cur = document.getElementById('pwCurrent').value;
    var neu = document.getElementById('pwNew').value;
    if (neu.length < 8) { msvToast('Neues Passwort min. 8 Zeichen', 'warning'); return; }
    var b = this; b.disabled = true;
    post({ action: 'change_password', current: cur, neu: neu })
      .then(function (d) {
        msvToast(d.message || (d.success ? 'Geändert' : 'Fehler'), d.success ? 'success' : 'error');
        if (d.success) { document.getElementById('pwCurrent').value = ''; document.getElementById('pwNew').value = ''; }
      })
      .catch(function () { msvToast('Fehler', 'error'); })
      .finally(function () { b.disabled = false; });
  });
})();
</script>

<?php include 'portal_footer.php'; ?>
