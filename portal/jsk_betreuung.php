<?php
// portal/jsk_betreuung.php - Board: offene Jungschuetzen-Anfragen uebernehmen ("Tinder")
// Rendert ueber api/jsk_betreuung.php?action=list und aktualisiert sich alle 30 s selbst
// (frueher serverseitig gerendert -> wer das Board offen hatte, sah Vergaben erst nach Reload).
$portal_page_title = 'Jungschützen betreuen';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/chat.inc.php';
requireLogin();

// Jungschuetzen haben hier nichts zu suchen (Rollenweiche faengt sie ohnehin ab)
if (isJungschuetze()) { header('Location: jsk_dashboard.php'); exit; }

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$featureAktiv = jskFeatureAktiv();
$istLeiter    = isJskLeiter($db, $userId);
$istBetreuer  = jskIstBetreuer($db, $userId) || $istLeiter;

include 'portal_header.php';
$csrf_token = ensureCsrfToken();
?>

<style>
.jsk-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1rem; }
.jsk-tile { border:1px solid #e2e8f0; border-radius:1rem; padding:1.25rem; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.06); transition:box-shadow .2s; }
.jsk-tile:hover { box-shadow:0 6px 18px rgba(0,0,0,0.10); }
.jsk-tile.vergeben { opacity:0.7; background:#f8fafc; }
.jsk-tile .name { font-weight:700; font-size:1.05rem; }
.jsk-tile .datum { color:#2d4373; font-weight:600; }
.jsk-tile .bem { font-size:.85rem; color:#64748b; margin-top:.4rem; }
.jsk-tile .termin { font-size:.8rem; color:#3b5998; margin-top:.25rem; }
.jsk-tile .same-day { font-size:.78rem; color:#b45309; margin-top:.4rem; }
.jsk-empty { text-align:center; color:#94a3b8; padding:2.5rem 1rem; }
.jsk-refresh { font-size:.75rem; color:#94a3b8; }
</style>

<div class="container py-4" style="max-width:920px;">
  <div class="portal-page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <h1><i class="bi bi-people me-2"></i>Jungschützen betreuen</h1>
      <p class="subtitle">Offene Anfragen ansehen und übernehmen</p>
    </div>
    <span class="jsk-refresh" id="jskRefresh"></span>
  </div>

  <?php if (!$featureAktiv): ?>
    <div class="alert alert-warning"><i class="bi bi-info-circle me-2"></i>Die Jungschützen-Betreuung ist derzeit deaktiviert.</div>
  <?php elseif (!$istBetreuer): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span><i class="bi bi-bell me-2"></i>Aktiviere „Jungschützen-Betreuung" in deinen Einstellungen, um Anfragen zu sehen und zu übernehmen.</span>
      <a href="benachrichtigungen.php" class="btn btn-outline-primary btn-sm">Einstellungen</a>
    </div>
  <?php else: ?>
    <p class="text-muted">Wer zuerst übernimmt, betreut den Jungschützen. Bereits vergebene Termine sind ausgegraut. Die Liste aktualisiert sich automatisch.</p>
    <div id="jskBoard"><div class="jsk-tile jsk-empty"><span class="spinner-border spinner-border-sm me-2"></span>Lädt…</div></div>
  <?php endif; ?>
</div>

<?php if ($featureAktiv && $istBetreuer): ?>
<script>
(function () {
  var csrf = <?php echo json_encode($csrf_token); ?>;
  var API = '../api/jsk_betreuung.php';
  var esc = function (s) { return $('<div>').text(s == null ? '' : s).html(); };
  var timer = null, busy = false;

  function render(list, ts) {
    var box = document.getElementById('jskBoard');
    if (!list.length) {
      box.innerHTML = '<div class="jsk-tile jsk-empty"><i class="bi bi-emoji-smile d-block mb-2" style="font-size:2rem;"></i>'
        + 'Aktuell keine offenen Anfragen. Du wirst benachrichtigt, sobald sich jemand meldet.</div>';
    } else {
      var html = '<div class="jsk-grid">';
      list.forEach(function (a) {
        var offen = a.status === 'offen';
        html += '<div class="jsk-tile ' + (offen ? '' : 'vergeben') + '" data-id="' + a.id + '">'
          + '<div class="name"><i class="bi bi-person-circle me-1"></i>' + esc(a.name) + '</div>'
          + '<div class="datum mt-1"><i class="bi bi-calendar-event me-1"></i>' + esc(a.datum_de) + (a.zeit ? ' · ' + esc(a.zeit) : '') + '</div>'
          + (a.termin ? '<div class="termin"><i class="bi bi-flag me-1"></i>' + esc(a.termin) + '</div>' : '')
          + (a.bemerkung ? '<div class="bem"><i class="bi bi-chat-left-text me-1"></i>' + esc(a.bemerkung) + '</div>' : '')
          + '<div class="mt-3">';
        if (offen) {
          if (a.same_day) html += '<div class="same-day"><i class="bi bi-exclamation-circle me-1"></i>Du betreust an diesem Tag bereits ' + esc(a.same_day) + '.</div>';
          html += '<button class="btn btn-club btn-sm w-100 js-claim mt-1" data-id="' + a.id + '"><i class="bi bi-hand-thumbs-up me-1"></i>Ich kümmere mich</button>';
        } else if (a.mine) {
          html += '<div class="small text-success mb-2"><i class="bi bi-check-circle me-1"></i>Du betreust diesen Jungschützen</div>';
          if (a.hat_login) {
            var href = a.chat_conv > 0 ? 'chat.php?c=' + a.chat_conv : 'chat.php?anfrage=' + a.id;
            html += '<a href="' + href + '" class="btn btn-outline-club btn-sm w-100 mb-2"><i class="bi bi-chat-dots me-1"></i>Chat</a>';
          }
          html += '<button class="btn btn-outline-secondary btn-sm w-100 js-release" data-id="' + a.id + '"><i class="bi bi-arrow-counterclockwise me-1"></i>Freigeben</button>';
        } else {
          html += '<div class="small text-muted"><i class="bi bi-person-check me-1"></i>Betreut von <strong>' + esc(a.betreuer || 'einem Mitglied') + '</strong></div>';
        }
        html += '</div></div>';
      });
      html += '</div>';
      box.innerHTML = html;
    }
    document.getElementById('jskRefresh').textContent = ts ? 'Stand ' + ts : '';
  }

  function load() {
    if (busy) return Promise.resolve();
    return fetch(API + '?action=list').then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.success) render(d.anfragen || [], d.ts); })
      .catch(function () {});
  }
  function schedule() { clearTimeout(timer); timer = setTimeout(function () { if (document.visibilityState === 'visible') load().finally(schedule); else schedule(); }, 30000); }

  function action(act, id, confirmMsg, btn) {
    var go = function () {
      busy = true;
      if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
      fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ action: act, id: id, csrf_token: csrf })
      }).then(function (r) { return r.json(); }).then(function (data) {
        msvToast(data.message || (data.success ? 'OK' : 'Fehler'), data.success ? 'success' : 'error');
      }).catch(function () { msvToast('Fehler bei der Verarbeitung', 'error'); })
        .finally(function () { busy = false; load(); });
    };
    if (confirmMsg) { msvConfirm(confirmMsg).then(function (r) { if (r.isConfirmed) go(); }); }
    else go();
  }

  $(document).on('click', '.js-claim', function () { action('claim', this.getAttribute('data-id'), null, this); });
  $(document).on('click', '.js-release', function () { action('release', this.getAttribute('data-id'), 'Betreuung wirklich freigeben? Der Jungschütze und die anderen Betreuer werden informiert.', this); });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') load(); });

  load().finally(schedule);
})();
</script>
<?php endif; ?>

<?php include 'portal_footer.php'; ?>
