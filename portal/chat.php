<?php
// portal/chat.php – 1:1-Chat (Jungschütze ↔ Leiter / Match), WhatsApp-Stil.
$portal_page_title = 'Jungschützenchat';
$portal_body_class = 'page-chat';   // Hook für randlose Chat-Darstellung (mobil)
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/chat.inc.php';
requireLogin();

$userId   = (int) ($_SESSION['user_id'] ?? 0);
$db       = getDB();
$istLeiter = isJskLeiter($db, $userId);
$initialConv = (int) ($_GET['c'] ?? 0);

// Zugriff: Jungschützen + Jungschützenleiter immer; Mitglieder nur mit aktivierter
// „Jungschützen betreuen"-Einstellung.
if (isJungschuetze()) {
    $chatAccess = true;
    $initialConv = $initialConv ?: chatEnsureLeiterConversation($db, $userId);
} else {
    $hatBetreuung = false;
    try {
        $st = $db->prepare('SELECT jsk_betreuung FROM benachrichtigung_prefs WHERE user_id = ?');
        $st->execute([$userId]);
        $hatBetreuung = ((int) $st->fetchColumn() === 1);
    } catch (Throwable $e) { $hatBetreuung = false; }
    $chatAccess = $hatBetreuung || $istLeiter;
}

include 'portal_header.php';
$csrf_token = ensureCsrfToken();
?>

<?php if (!$chatAccess): ?>
  <div class="container py-4" style="max-width:620px;">
    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span><i class="bi bi-chat-dots me-2"></i>Der Jungschützenchat ist nur aktiv, wenn du „Jungschützen betreuen" aktiviert hast.</span>
      <a href="benachrichtigungen.php" class="btn btn-outline-primary btn-sm">Einstellungen</a>
    </div>
  </div>
  <?php include 'portal_footer.php'; ?>
  <?php return; ?>
<?php endif; ?>

<style>
/* Vereinsfarbe #3b5998 (primary), dunkel #2d4373 */
.chat-wrap { display:flex; gap:0; border:1px solid #e2e8f0; border-radius:1rem; overflow:hidden;
  height: calc(100dvh - var(--nav-height) - 7rem); background:#fff; box-shadow:0 2px 12px rgba(0,0,0,0.06); }
.chat-list { width:340px; flex-shrink:0; border-right:1px solid #e2e8f0; display:flex; flex-direction:column; min-height:0; }
.chat-list-head { padding:0.75rem 1rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; color:#3b5998; }
.chat-list-scroll { overflow-y:auto; flex:1; }
.chat-row { display:flex; gap:0.7rem; align-items:center; padding:0.7rem 1rem; cursor:pointer; border-bottom:1px solid #f1f5f9; }
.chat-row:hover { background:#f6f8fc; }
.chat-row.active { background:#eef2fb; }
.chat-av { width:44px; height:44px; border-radius:50%; background:#3b5998; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0; }
.chat-av.leiter { background:#2d4373; }
.chat-row-body { min-width:0; flex:1; }
.chat-row-name { font-weight:600; font-size:0.9rem; display:flex; justify-content:space-between; gap:0.5rem; }
.chat-row-last { font-size:0.8rem; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.chat-row-time { font-size:0.7rem; color:#cbd5e1; font-weight:400; flex-shrink:0; }
.chat-badge { background:#3b5998; color:#fff; border-radius:999px; font-size:0.7rem; padding:1px 7px; margin-left:auto; }

/* Thread im WhatsApp-Stil: dezenter Hintergrund, Sprechblasen mit „Tail" */
.chat-thread { flex:1; display:flex; flex-direction:column; min-width:0; min-height:0; position:relative;
  background-color:#e7ebf3;
  background-image:radial-gradient(rgba(59,89,152,0.06) 1px, transparent 1px); background-size:18px 18px; }
.chat-thread-head { padding:0.55rem 1rem; border-bottom:1px solid #e2e8f0; background:#3b5998; color:#fff; display:flex; align-items:center; gap:0.6rem; }
.chat-thread-head .chat-av { width:38px; height:38px; background:#fff; color:#3b5998; }
.chat-back { display:none; background:none; border:none; font-size:1.3rem; color:#fff; }
.chat-msgs { flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:0.3rem; }
.chat-bubble { position:relative; max-width:80%; padding:0.4rem 0.65rem 0.5rem; border-radius:0.7rem; font-size:0.9rem; line-height:1.35; word-wrap:break-word; box-shadow:0 1px 0.5px rgba(0,0,0,0.13); }
.chat-bubble .t { font-size:0.62rem; color:#8a9bb5; display:block; text-align:right; margin-top:1px; }
.chat-bubble.them { background:#fff; color:#1f2937; align-self:flex-start; border-top-left-radius:0.15rem; }
.chat-bubble.me { background:#dbe4f7; color:#16233d; align-self:flex-end; border-top-right-radius:0.15rem; }
.chat-bubble.me .t { color:#5b7099; }
.chat-day { align-self:center; background:#ffffffcc; color:#3b5998; font-size:0.72rem; font-weight:600; padding:2px 12px; border-radius:999px; margin:0.5rem 0; box-shadow:0 1px 1px rgba(0,0,0,0.08); }
.chat-input { display:flex; gap:0.4rem; padding:0.5rem 0.6rem; border-top:1px solid #e2e8f0; background:#fff; align-items:flex-end; }
.chat-input textarea { flex:1; resize:none; border:1px solid #e2e8f0; border-radius:1.3rem; padding:0.5rem 0.95rem; font-size:0.9rem; max-height:120px; line-height:1.3; }
.chat-input textarea:focus { outline:none; border-color:#3b5998; box-shadow:0 0 0 3px rgba(59,89,152,0.12); }
.chat-send { flex-shrink:0; background:#3b5998; color:#fff; border:none; border-radius:50%; width:42px; height:42px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; }
.chat-send:hover { background:#2d4373; color:#fff; }
.chat-emoji-btn { background:none; border:none; font-size:1.4rem; line-height:1; padding:0 0.25rem; cursor:pointer; flex-shrink:0; }
.chat-emoji-panel { display:none; position:absolute; left:8px; right:8px; bottom:62px; z-index:20;
  grid-template-columns:repeat(8, 1fr); gap:2px; max-height:190px; overflow-y:auto;
  background:#fff; border:1px solid #e2e8f0; border-radius:0.8rem; box-shadow:0 6px 20px rgba(0,0,0,0.15); padding:0.5rem; }
.chat-emoji-panel.open { display:grid; }
.chat-emoji-panel span { font-size:1.35rem; text-align:center; cursor:pointer; border-radius:6px; padding:3px 0; }
.chat-emoji-panel span:hover { background:#f1f5f9; }
.chat-empty { margin:auto; color:#94a3b8; text-align:center; padding:2rem; }
#btnNewChat { color:#3b5998; border-color:#3b5998; }
#btnNewChat:hover { background:#3b5998; color:#fff; }

@media (max-width: 767.98px) {
  /* Chat randlos / edge-to-edge: Padding des Portal-Wrappers + Footer auf der Chat-Seite weg */
  body.page-chat .portal-content { padding:0 !important; }
  body.page-chat > footer, body.page-chat footer { display:none !important; }
  .chat-page { padding:0 !important; max-width:100% !important; }
  .chat-wrap { height: calc(100dvh - var(--nav-height)); border:0; border-radius:0; box-shadow:none; }
  /* Sendebutton nicht ganz am Rand / nicht unter dem iOS-Home-Indikator */
  .chat-input { padding-bottom: calc(0.5rem + env(safe-area-inset-bottom)); }
  .chat-list { width:100%; }
  .chat-thread { display:none; }
  .chat-wrap.show-thread .chat-list { display:none; }
  .chat-wrap.show-thread .chat-thread { display:flex; }
  .chat-back { display:inline-block; }
  /* Home-FAB nicht über dem Sendebutton: in der Liste nach links, im offenen Thread ausblenden */
  .portal-back-fab { left:1rem !important; right:auto !important; }
  body.chat-in-thread .portal-back-fab { display:none !important; }
}
</style>

<div class="container-fluid py-3 chat-page" style="max-width:1100px;">
  <div class="chat-wrap" id="chatWrap">
    <div class="chat-list">
      <div class="chat-list-head">
        <strong><i class="bi bi-chat-dots me-1"></i>Jungschützenchat</strong>
        <?php if ($istLeiter): ?>
          <button class="btn btn-sm btn-outline-club" id="btnNewChat"><i class="bi bi-plus-lg"></i></button>
        <?php endif; ?>
      </div>
      <div class="chat-list-scroll" id="chatListScroll">
        <div class="chat-empty">Lädt…</div>
      </div>
    </div>
    <div class="chat-thread" id="chatThread">
      <div class="chat-empty" id="threadPlaceholder"><i class="bi bi-chat-square-text d-block mb-2" style="font-size:2rem;"></i>Wähle links eine Unterhaltung.</div>
      <div class="chat-thread-head" id="threadHead" style="display:none;">
        <button class="chat-back" id="chatBack"><i class="bi bi-arrow-left"></i></button>
        <div class="chat-av" id="threadAv"></div>
        <div class="fw-semibold" id="threadName"></div>
      </div>
      <div class="chat-msgs" id="chatMsgs" style="display:none;"></div>
      <div class="chat-emoji-panel" id="emojiPanel"></div>
      <form class="chat-input" id="chatForm" style="display:none;">
        <button type="button" class="chat-emoji-btn" id="emojiBtn" title="Emoji" aria-label="Emoji">😊</button>
        <textarea id="chatText" rows="1" placeholder="Nachricht…" maxlength="2000"></textarea>
        <button class="chat-send" type="submit" aria-label="Senden"><i class="bi bi-send-fill"></i></button>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Neuer Chat (Leiter) -->
<?php if ($istLeiter): ?>
<div class="modal fade" id="newChatModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">Jungschütze anschreiben</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><div id="newChatList" class="list-group"><div class="text-muted">Lädt…</div></div></div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
(function () {
  const csrf = <?php echo json_encode($csrf_token); ?>;
  const API = '../api/chat.php';
  let activeConv = 0, lastMsgId = 0, threadTimer = null, lastDay = '';

  const esc = s => $('<div>').text(s == null ? '' : s).html();
  function fmtTime(s){ if(!s) return ''; const d=new Date(s.replace(' ','T')); return String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0'); }
  function fmtDay(s){ if(!s) return ''; const d=new Date(s.replace(' ','T')); const t=new Date();
    if(d.toDateString()===t.toDateString()) return 'Heute';
    const y=new Date(t); y.setDate(t.getDate()-1); if(d.toDateString()===y.toDateString()) return 'Gestern';
    return String(d.getDate()).padStart(2,'0')+'.'+String(d.getMonth()+1).padStart(2,'0')+'.'+d.getFullYear(); }

  // ---------- Liste ----------
  function loadList() {
    fetch(API + '?action=list').then(r => r.json()).then(d => {
      if (!d.success) return;
      const sc = document.getElementById('chatListScroll');
      if (!d.conversations.length) { sc.innerHTML = '<div class="chat-empty">Noch keine Chats.</div>'; return; }
      let html = '';
      d.conversations.forEach(c => {
        html += '<div class="chat-row' + (c.id===activeConv?' active':'') + '" data-id="' + c.id + '">'
          + '<div class="chat-av' + (c.typ==='leiter'?' leiter':'') + '">' + esc(c.initials) + '</div>'
          + '<div class="chat-row-body"><div class="chat-row-name"><span>' + esc(c.name) + '</span>'
          + '<span class="chat-row-time">' + fmtTime(c.last_at) + '</span></div>'
          + '<div class="d-flex align-items-center"><span class="chat-row-last flex-grow-1">' + esc(c.last_text || '–') + '</span>'
          + (c.unread>0 ? '<span class="chat-badge">' + c.unread + '</span>' : '') + '</div></div></div>';
      });
      sc.innerHTML = html;
    }).catch(()=>{});
  }

  // ---------- Thread ----------
  function openConv(id) {
    activeConv = id; lastMsgId = 0; lastDay = '';
    document.getElementById('threadPlaceholder').style.display = 'none';
    document.getElementById('threadHead').style.display = 'flex';
    document.getElementById('chatMsgs').style.display = 'flex';
    document.getElementById('chatForm').style.display = 'flex';
    document.getElementById('chatMsgs').innerHTML = '';
    document.getElementById('chatWrap').classList.add('show-thread');
    document.body.classList.add('chat-in-thread');
    $('.chat-row').removeClass('active'); $('.chat-row[data-id="'+id+'"]').addClass('active');
    fetchMsgs(true);
    if (threadTimer) clearInterval(threadTimer);
    threadTimer = setInterval(() => fetchMsgs(false), 4000);
  }

  function fetchMsgs(initial) {
    if (!activeConv) return;
    fetch(API + '?action=messages&c=' + activeConv + '&after=' + lastMsgId).then(r => r.json()).then(d => {
      if (!d.success) return;
      if (initial && d.partner) {
        document.getElementById('threadName').textContent = d.partner;
        const ini = (d.partner.trim().split(/\s+/).map(w=>w[0]).slice(0,2).join('')||'?').toUpperCase();
        document.getElementById('threadAv').textContent = ini;
      }
      const box = document.getElementById('chatMsgs');
      const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
      d.messages.forEach(m => {
        const day = fmtDay(m.at);
        if (day !== lastDay) { box.insertAdjacentHTML('beforeend', '<div class="chat-day">'+esc(day)+'</div>'); lastDay = day; }
        box.insertAdjacentHTML('beforeend',
          '<div class="chat-bubble ' + (m.mine?'me':'them') + '">' + esc(m.text).replace(/\n/g,'<br>')
          + '<span class="t">' + fmtTime(m.at) + '</span></div>');
        lastMsgId = Math.max(lastMsgId, m.id);
      });
      if (d.messages.length && (initial || nearBottom)) box.scrollTop = box.scrollHeight;
      if (d.messages.length && !initial) loadList(); // neue Nachricht -> Liste/Badge auffrischen
    }).catch(()=>{});
  }

  function sendMsg() {
    const ta = document.getElementById('chatText');
    const text = ta.value.trim();
    if (!text || !activeConv) return;
    ta.value = '';
    fetch(API, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},
      body: JSON.stringify({ action:'send', c:activeConv, text:text, csrf_token:csrf }) })
      .then(r => r.json()).then(d => { if (d.success) { fetchMsgs(false); loadList(); } else { msvToast(d.message||'Fehler','error'); ta.value=text; } })
      .catch(() => { msvToast('Senden fehlgeschlagen','error'); ta.value=text; });
  }

  // ---------- Events ----------
  $(document).on('click', '.chat-row', function(){ openConv(parseInt(this.dataset.id,10)); });
  document.getElementById('chatForm').addEventListener('submit', e => { e.preventDefault(); sendMsg(); });
  document.getElementById('chatText').addEventListener('keydown', e => { if (e.key==='Enter' && !e.shiftKey){ e.preventDefault(); sendMsg(); } });
  document.getElementById('chatBack').addEventListener('click', () => {
    document.getElementById('chatWrap').classList.remove('show-thread');
    document.body.classList.remove('chat-in-thread');
    if (threadTimer) clearInterval(threadTimer); activeConv = 0; loadList();
  });

  // ---------- Emoji-Picker (selbst-enthalten) ----------
  (function () {
    var EMOJIS = ['😀','😃','😄','😁','😆','😅','😂','🤣','😊','🙂','😉','😍','😘','😎','🤔','😴',
      '😢','😭','😅','😬','🙈','👍','👎','👏','🙌','🙏','💪','👌','🤙','👋','🤝','💬',
      '❤️','🔥','⭐','🎉','✅','❌','⚠️','🎯','🔫','🏆','🥇','📅','⏰','💯'];
    var panel = document.getElementById('emojiPanel');
    var btn = document.getElementById('emojiBtn');
    if (!panel || !btn) return;
    panel.innerHTML = EMOJIS.map(function (e) { return '<span>' + e + '</span>'; }).join('');
    btn.addEventListener('click', function (ev) { ev.stopPropagation(); panel.classList.toggle('open'); });
    panel.addEventListener('click', function (ev) {
      if (ev.target.tagName !== 'SPAN') return;
      var ta = document.getElementById('chatText');
      var s = ta.selectionStart || 0, en = ta.selectionEnd || 0, em = ev.target.textContent;
      ta.value = ta.value.slice(0, s) + em + ta.value.slice(en);
      ta.focus(); ta.selectionStart = ta.selectionEnd = s + em.length;
    });
    document.addEventListener('click', function (ev) {
      if (ev.target !== btn && !ev.target.closest('#emojiPanel')) panel.classList.remove('open');
    });
  })();

  <?php if ($istLeiter): ?>
  document.getElementById('btnNewChat').addEventListener('click', () => {
    const m = new bootstrap.Modal('#newChatModal'); m.show();
    fetch(API + '?action=jsk_list').then(r=>r.json()).then(d=>{
      const el = document.getElementById('newChatList');
      if (!d.success || !d.jsk.length) { el.innerHTML = '<div class="text-muted">Keine Jungschützen mit Login.</div>'; return; }
      el.innerHTML = d.jsk.map(j => '<button type="button" class="list-group-item list-group-item-action nc-item" data-jsid="'+j.jungschuetze_id+'">'+esc(j.name)+'</button>').join('');
    });
  });
  $(document).on('click', '.nc-item', function(){
    const jsid = this.dataset.jsid;
    fetch(API, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},
      body: JSON.stringify({ action:'open', typ:'leiter', jungschuetze_id:jsid, csrf_token:csrf }) })
      .then(r=>r.json()).then(d=>{
        bootstrap.Modal.getInstance(document.getElementById('newChatModal')).hide();
        if (d.success && d.conversation_id) { loadList(); openConv(d.conversation_id); }
        else msvToast(d.message||'Fehler','error');
      });
  });
  <?php endif; ?>

  // ---------- Init + Polling ----------
  loadList();
  setInterval(() => { if (document.visibilityState === 'visible') loadList(); }, 20000);
  document.addEventListener('visibilitychange', () => { if (document.visibilityState==='visible') { loadList(); if(activeConv) fetchMsgs(false); } });
  <?php if ($initialConv > 0): ?>openConv(<?php echo $initialConv; ?>);<?php endif; ?>
})();
</script>

<?php include 'portal_footer.php'; ?>
