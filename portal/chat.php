<?php
// portal/chat.php – 1:1-Chat (Jungschütze ↔ Leiter / Match), WhatsApp-Stil.
// CSS: css/portal.css (Abschnitt „Jungschützenchat"). API: api/chat.php, Bilder: api/chat_bild.php.
$portal_page_title = 'Jungschützenchat';
$portal_body_class = 'page-chat';   // Hook für randlose Chat-Darstellung (mobil)
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/chat.inc.php';
require_once __DIR__ . '/../inc/fotogalerie.inc.php';   // fotoAcceptAttribut()
requireLogin();

$userId    = (int) ($_SESSION['user_id'] ?? 0);
$db        = getDB();
$istLeiter = isJskLeiter($db, $userId);
$initialConv = (int) ($_GET['c'] ?? 0);
$bilderAktiv = chatBilderAktiv($db);

// Zugriff: Jungschützen + Jungschützenleiter immer; Mitglieder nur mit aktivierter
// „Jungschützen betreuen"-Einstellung.
if (isJungschuetze()) {
    $chatAccess = true;
    if ($initialConv <= 0 && empty($_GET['anfrage'])) {
        $initialConv = chatEnsureLeiterConversation($db, $userId);
    }
} else {
    $chatAccess = jskIstBetreuer($db, $userId) || $istLeiter;
}

// Deep-Link aus Board/Dashboard: ?anfrage=<id> -> Match-Chat der Anfrage (wird bei Bedarf
// genau einmal angelegt; Board und Dashboard schreiben beim Rendern nichts mehr).
if ($chatAccess && !empty($_GET['anfrage'])) {
    try {
        $info = jskAnfrageInfo($db, (int) $_GET['anfrage']);
        if ($info && $info['js_user_id'] > 0 && !empty($info['betreut_von_user_id'])) {
            $betreuer = (int) $info['betreut_von_user_id'];
            if ($userId === $betreuer || $userId === $info['js_user_id']) {
                $initialConv = chatEnsureMatchConversation($db, $info['js_user_id'], $betreuer);
            }
        }
    } catch (Throwable $e) { /* Deep-Link best effort */ }
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

<div class="container-fluid py-3 chat-page" style="max-width:1100px;">
  <div class="chat-wrap" id="chatWrap">
    <!-- Linke Spalte: Unterhaltungen -->
    <div class="chat-list">
      <div class="chat-list-head">
        <strong><i class="bi bi-chat-dots me-1"></i>Jungschützenchat</strong>
        <?php if ($istLeiter): ?>
          <button class="btn btn-sm btn-outline-club" id="btnNewChat" data-tooltip="Jungschütze anschreiben"><i class="bi bi-pencil-square"></i></button>
        <?php endif; ?>
      </div>
      <div class="chat-search"><i class="bi bi-search"></i><input type="search" id="chatSearch" placeholder="Suchen oder neuen Chat beginnen" autocomplete="off"></div>
      <div class="chat-list-scroll" id="chatListScroll">
        <div class="chat-empty">Lädt…</div>
      </div>
    </div>

    <!-- Rechte Spalte: Thread -->
    <div class="chat-thread" id="chatThread">
      <div class="chat-empty chat-empty--start" id="threadPlaceholder">
        <i class="bi bi-chat-square-text d-block mb-2"></i>
        <div class="fw-semibold">Jungschützenchat</div>
        <div class="small">Wähle links eine Unterhaltung, um Nachrichten zu lesen und zu schreiben.</div>
      </div>
      <div class="chat-thread-head" id="threadHead" style="display:none;">
        <button class="chat-back" id="chatBack" aria-label="Zurück"><i class="bi bi-arrow-left"></i></button>
        <div class="chat-av" id="threadAv"></div>
        <div class="chat-thread-title">
          <div class="chat-thread-name" id="threadName"></div>
          <div class="chat-thread-sub" id="threadSub"></div>
        </div>
      </div>
      <div class="chat-hinweis" id="threadHinweis" style="display:none;"></div>
      <div class="chat-msgs" id="chatMsgs" style="display:none;"></div>
      <button type="button" class="chat-scroll-down" id="scrollDown" style="display:none;" aria-label="Nach unten"><i class="bi bi-chevron-double-down"></i><span class="n" id="scrollDownN"></span></button>
      <div class="chat-emoji-panel" id="emojiPanel"></div>
      <form class="chat-input" id="chatForm" style="display:none;">
        <button type="button" class="chat-icon-btn" id="emojiBtn" aria-label="Emoji"><i class="bi bi-emoji-smile"></i></button>
        <?php if ($bilderAktiv): ?>
          <button type="button" class="chat-icon-btn" id="attachBtn" aria-label="Bild anhängen" data-tooltip="Bild senden"><i class="bi bi-paperclip"></i></button>
          <input type="file" id="chatFile" accept="<?= htmlspecialchars(fotoAcceptAttribut(), ENT_QUOTES) ?>" style="display:none;">
        <?php endif; ?>
        <textarea id="chatText" rows="1" placeholder="Nachricht" maxlength="2000"></textarea>
        <button class="chat-send" type="submit" aria-label="Senden"><i class="bi bi-send-fill"></i></button>
      </form>
    </div>
  </div>
</div>

<!-- Bild-Vorschau vor dem Senden (WhatsApp: Bildunterschrift) -->
<div class="chat-overlay" id="imgPreview" style="display:none;">
  <div class="chat-overlay-head">
    <button type="button" class="chat-overlay-close" id="imgPreviewCancel" aria-label="Abbrechen"><i class="bi bi-x-lg"></i></button>
    <span>Bild senden</span>
  </div>
  <div class="chat-overlay-body"><img id="imgPreviewImg" alt="Vorschau"></div>
  <form class="chat-overlay-foot" id="imgPreviewForm">
    <input type="text" id="imgCaption" class="form-control" maxlength="500" placeholder="Bildunterschrift hinzufügen…" autocomplete="off">
    <button class="chat-send" type="submit" id="imgPreviewSend" aria-label="Senden"><i class="bi bi-send-fill"></i></button>
  </form>
</div>

<!-- Lightbox -->
<div class="chat-overlay chat-lightbox" id="chatLightbox" style="display:none;">
  <div class="chat-overlay-head">
    <button type="button" class="chat-overlay-close" id="lightboxClose" aria-label="Schliessen"><i class="bi bi-x-lg"></i></button>
    <span id="lightboxTitle"></span>
    <a class="chat-overlay-close ms-auto" id="lightboxOpen" href="#" target="_blank" rel="noopener" aria-label="In neuem Tab öffnen" data-tooltip="Original öffnen"><i class="bi bi-box-arrow-up-right"></i></a>
  </div>
  <div class="chat-overlay-body" id="lightboxBody"><img id="lightboxImg" alt=""></div>
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
  const TOUCH = window.matchMedia('(pointer: coarse)').matches;   // Handy: Enter = Zeilenumbruch
  const BILDER = <?php echo $bilderAktiv ? 'true' : 'false'; ?>;
  let activeConv = 0, activeTyp = '', readonly = false, otherRead = 0;
  let lastMsgId = 0, lastDay = '', lastSenderKey = null;
  let timer = null, tick = 0, idleSince = Date.now(), readPending = false;
  let convCache = [], pendingFile = null, unseenBelow = 0;

  const esc = s => $('<div>').text(s == null ? '' : s).html();
  const linkify = h => h.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
  function fmtTime(s){ if(!s) return ''; const d=new Date(s.replace(' ','T')); return String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0'); }
  function fmtDay(s){ if(!s) return ''; const d=new Date(s.replace(' ','T')); const t=new Date();
    if(d.toDateString()===t.toDateString()) return 'Heute';
    const y=new Date(t); y.setDate(t.getDate()-1); if(d.toDateString()===y.toDateString()) return 'Gestern';
    return String(d.getDate()).padStart(2,'0')+'.'+String(d.getMonth()+1).padStart(2,'0')+'.'+d.getFullYear(); }
  function fmtListTime(s){ if(!s) return ''; const d=new Date(s.replace(' ','T')); const t=new Date();
    if(d.toDateString()===t.toDateString()) return fmtTime(s);
    const y=new Date(t); y.setDate(t.getDate()-1); if(d.toDateString()===y.toDateString()) return 'Gestern';
    return String(d.getDate()).padStart(2,'0')+'.'+String(d.getMonth()+1).padStart(2,'0')+'.'; }
  function setBadge(n){ try { window.dispatchEvent(new CustomEvent('msv-chat-unread', { detail: n })); } catch (e) {} }
  function post(payload){
    payload.csrf_token = csrf;
    return fetch(API, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf}, body: JSON.stringify(payload) }).then(r => r.json());
  }
  function ticks(id){ return '<i class="bi ' + (id <= otherRead ? 'bi-check2-all tick read' : 'bi-check2 tick') + '"></i>'; }

  // ---------- Liste ----------
  function renderList(convs) {
    convCache = convs;
    const q = (document.getElementById('chatSearch').value || '').trim().toLowerCase();
    const sc = document.getElementById('chatListScroll');
    const rows = q ? convs.filter(c => (c.name + ' ' + (c.last_text||'')).toLowerCase().includes(q)) : convs;
    if (!rows.length) { sc.innerHTML = '<div class="chat-empty">' + (q ? 'Keine Treffer.' : 'Noch keine Chats.') + '</div>'; return; }
    let html = '';
    rows.forEach(c => {
      const av = c.readonly ? ' readonly' : (c.typ==='leiter' ? ' leiter' : '');
      html += '<div class="chat-row' + (c.id===activeConv?' active':'') + '" data-id="' + c.id + '" data-typ="' + esc(c.typ) + '">'
        + '<div class="chat-av' + av + '">' + esc(c.initials) + '</div>'
        + '<div class="chat-row-body"><div class="chat-row-name"><span>' + esc(c.name) + '</span>'
        + '<span class="chat-row-time' + (c.unread>0 ? ' unread' : '') + '">' + fmtListTime(c.last_at) + '</span></div>'
        + '<div class="d-flex align-items-center"><span class="chat-row-last flex-grow-1">' + esc(c.last_text || '') + '</span>'
        + (c.unread>0 ? '<span class="chat-badge">' + c.unread + '</span>' : '') + '</div></div></div>';
    });
    sc.innerHTML = html;
  }
  document.getElementById('chatSearch').addEventListener('input', () => renderList(convCache));

  // ---------- Thread ----------
  function bubbleHtml(m) {
    const side = m.mine ? 'me' : 'them';
    const key  = m.mine ? 'me' : (activeTyp === 'leiter' ? 'them:' + m.sender : 'them');
    const day  = fmtDay(m.at);
    let pre = '', dayBreak = false;
    if (day !== lastDay) { pre = '<div class="chat-day">' + esc(day) + '</div>'; lastDay = day; dayBreak = true; }
    const cont = (key === lastSenderKey && !dayBreak);
    lastSenderKey = key;
    const name = (!m.mine && activeTyp === 'leiter' && !cont && m.sender) ? '<span class="s">' + esc(m.sender) + '</span>' : '';
    const meta = '<span class="t">' + fmtTime(m.at) + (m.mine && !m.deleted ? ticks(m.id) : '') + '</span>';
    let body;
    if (m.deleted) {
      body = '<span class="del"><i class="bi bi-slash-circle me-1"></i>Nachricht zurückgenommen</span>' + meta;
    } else if (m.bild) {
      body = '<a href="' + m.bild.f + '" class="chat-img" data-full="' + m.bild.f + '" style="aspect-ratio:' + m.bild.w + '/' + m.bild.h + '">'
           + '<img src="' + m.bild.t + '" alt="Bild" loading="lazy"></a>'
           + (m.text ? '<div class="cap">' + linkify(esc(m.text)).replace(/\n/g,'<br>') + meta + '</div>' : '<div class="cap cap--only">' + meta + '</div>');
    } else {
      body = linkify(esc(m.text)).replace(/\n/g,'<br>') + meta;
    }
    return pre + '<div class="chat-bubble ' + side + (cont ? ' cont' : '') + (m.deleted ? ' deleted' : '') + (m.bild && !m.deleted ? ' has-img' : '')
      + (m.can_del ? ' can-del' : '') + '" data-id="' + m.id + '">' + name + body + '</div>';
  }

  function renderMessages(msgs) {
    const box = document.getElementById('chatMsgs');
    const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
    let fremde = 0, html = '';
    msgs.forEach(m => { html += bubbleHtml(m); lastMsgId = Math.max(lastMsgId, m.id); if (!m.mine) fremde++; });
    if (html) box.insertAdjacentHTML('beforeend', html);
    if (msgs.length) {
      if (nearBottom) { scrollBottom(); }
      else { unseenBelow += fremde; updateScrollBtn(); }
    }
    return fremde;
  }
  function updateTicks() {
    document.querySelectorAll('#chatMsgs .chat-bubble.me .tick').forEach(el => {
      const id = parseInt(el.closest('.chat-bubble').dataset.id, 10);
      const read = id <= otherRead;
      el.classList.toggle('read', read); el.classList.toggle('bi-check2-all', read); el.classList.toggle('bi-check2', !read);
    });
  }
  function scrollBottom() { const box = document.getElementById('chatMsgs'); box.scrollTop = box.scrollHeight; unseenBelow = 0; updateScrollBtn(); }
  function updateScrollBtn() {
    const box = document.getElementById('chatMsgs');
    const far = box.scrollHeight - box.scrollTop - box.clientHeight > 200;
    const b = document.getElementById('scrollDown');
    b.style.display = (activeConv && far) ? 'flex' : 'none';
    const n = document.getElementById('scrollDownN'); n.textContent = unseenBelow > 0 ? unseenBelow : ''; n.style.display = unseenBelow > 0 ? '' : 'none';
    if (!far) unseenBelow = 0;
  }
  document.getElementById('chatMsgs').addEventListener('scroll', updateScrollBtn);
  document.getElementById('scrollDown').addEventListener('click', scrollBottom);

  function applyThreadMeta(t, initial) {
    activeTyp = t.typ || activeTyp;
    readonly  = !!t.readonly;
    if (initial && t.partner) {
      document.getElementById('threadName').textContent = t.partner;
      document.getElementById('threadSub').textContent = t.partner_sub || '';
      const ini = (t.partner.trim().split(/\s+/).map(w=>w[0]).slice(0,2).join('')||'?').toUpperCase();
      document.getElementById('threadAv').textContent = ini;
    }
    const hw = document.getElementById('threadHinweis');
    hw.textContent = t.hinweis || ''; hw.style.display = t.hinweis ? '' : 'none';
    document.getElementById('chatForm').style.display = readonly ? 'none' : 'flex';
    if (typeof t.other_read === 'number' && t.other_read !== otherRead) { otherRead = t.other_read; updateTicks(); }
  }

  // Gelesen-Quittung: nur wenn der Tab sichtbar ist und der Thread offen (nie implizit)
  function markRead() {
    if (!activeConv || document.visibilityState !== 'visible' || readPending) return;
    readPending = true;
    post({ action:'read', c: activeConv }).then(d => { if (d && d.success) { setBadge(d.unread); } }).catch(()=>{}).finally(() => { readPending = false; });
  }

  // ---------- Ein Poller für alles ----------
  function sync(withList, initial) {
    let url = API + '?action=sync';
    if (activeConv) url += '&c=' + activeConv + '&after=' + lastMsgId;
    if (withList) url += '&list=1';
    const conv = activeConv;
    return fetch(url).then(r => r.json()).then(d => {
      if (!d || !d.success) return;
      if (typeof d.unread === 'number') setBadge(d.unread);
      if (d.thread && conv === activeConv) {
        applyThreadMeta(d.thread, initial);
        const fremde = renderMessages(d.thread.messages);
        if (initial) scrollBottom();
        if (d.thread.messages.length) { idleSince = Date.now(); if (!withList) loadList(); }
        if (fremde > 0 || initial) markRead();
      }
      if (d.conversations) renderList(d.conversations);
    }).catch(()=>{});
  }
  function loadList() { return sync(true, false); }

  function schedule() {
    clearTimeout(timer);
    const idle  = Date.now() - idleSince > 60000;         // 1 Min. ohne neue Nachricht -> langsamer
    const delay = activeConv ? (idle ? 10000 : 4000) : 20000;
    timer = setTimeout(pollTick, delay);
  }
  function pollTick() {
    if (document.visibilityState !== 'visible') { schedule(); return; }
    tick++;
    sync(!activeConv || tick % 5 === 0, false).finally(schedule);
  }

  function openConv(id) {
    activeConv = id; activeTyp = ''; lastMsgId = 0; lastDay = ''; lastSenderKey = null; readonly = false; otherRead = 0; unseenBelow = 0;
    idleSince = Date.now();
    const row = document.querySelector('.chat-row[data-id="'+id+'"]');
    if (row) activeTyp = row.dataset.typ || '';
    document.getElementById('threadPlaceholder').style.display = 'none';
    document.getElementById('threadHead').style.display = 'flex';
    document.getElementById('chatMsgs').style.display = 'flex';
    document.getElementById('chatForm').style.display = 'flex';
    document.getElementById('chatMsgs').innerHTML = '';
    document.getElementById('chatWrap').classList.add('show-thread');
    document.body.classList.add('chat-in-thread');
    $('.chat-row').removeClass('active'); $('.chat-row[data-id="'+id+'"]').addClass('active');
    sync(false, true).finally(schedule);
  }
  function closeThread() {
    document.getElementById('chatWrap').classList.remove('show-thread');
    document.body.classList.remove('chat-in-thread');
    activeConv = 0; loadList().finally(schedule);
  }

  function sendMsg() {
    const ta = document.getElementById('chatText');
    const text = ta.value.trim();
    if (!text || !activeConv || readonly) return;
    ta.value = ''; ta.style.height = '';
    post({ action:'send', c:activeConv, text:text })
      .then(d => { if (d.success) { idleSince = Date.now(); sync(true, false).then(scrollBottom); } else { msvToast(d.message||'Fehler','error'); ta.value=text; } })
      .catch(() => { msvToast('Senden fehlgeschlagen','error'); ta.value=text; });
  }

  // ---------- Bilder ----------
  function openPreview(file) {
    if (!BILDER || !file || !activeConv || readonly) return;
    if (!/^image\//.test(file.type) && !/\.(heic|heif)$/i.test(file.name || '')) { msvToast('Bitte ein Bild wählen', 'warning'); return; }
    if (file.size > 15 * 1024 * 1024) { msvToast('Das Bild ist zu gross (max. 15 MB)', 'warning'); return; }
    pendingFile = file;
    const img = document.getElementById('imgPreviewImg');
    try { img.src = URL.createObjectURL(file); } catch (e) { img.removeAttribute('src'); }
    document.getElementById('imgCaption').value = '';
    document.getElementById('imgPreview').style.display = 'flex';
    setTimeout(() => document.getElementById('imgCaption').focus(), 50);
  }
  function closePreview() {
    document.getElementById('imgPreview').style.display = 'none';
    const img = document.getElementById('imgPreviewImg'); if (img.src) { try { URL.revokeObjectURL(img.src); } catch (e) {} }
    pendingFile = null;
    const fi = document.getElementById('chatFile'); if (fi) fi.value = '';
  }
  function uploadImage() {
    if (!pendingFile || !activeConv) return;
    const btn = document.getElementById('imgPreviewSend');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    const fd = new FormData();
    fd.append('c', activeConv); fd.append('text', document.getElementById('imgCaption').value.trim());
    fd.append('file', pendingFile, pendingFile.name || 'bild.jpg'); fd.append('csrf_token', csrf);
    fetch(API + '?action=upload', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.success) { closePreview(); idleSince = Date.now(); sync(true, false).then(scrollBottom); }
        else msvToast(d.message || 'Fehler beim Senden', 'error');
      })
      .catch(() => msvToast('Bild konnte nicht gesendet werden', 'error'))
      .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-fill"></i>'; });
  }
  if (BILDER) {
    document.getElementById('attachBtn').addEventListener('click', () => document.getElementById('chatFile').click());
    document.getElementById('chatFile').addEventListener('change', e => openPreview(e.target.files[0]));
    document.addEventListener('paste', e => {
      if (!activeConv || readonly || document.getElementById('imgPreview').style.display !== 'none') return;
      const items = (e.clipboardData && e.clipboardData.files) ? e.clipboardData.files : [];
      for (const f of items) { if (/^image\//.test(f.type)) { e.preventDefault(); openPreview(f); break; } }
    });
    const wrap = document.getElementById('chatThread');
    ['dragenter','dragover'].forEach(ev => wrap.addEventListener(ev, e => { if (activeConv && !readonly) { e.preventDefault(); wrap.classList.add('dragging'); } }));
    ['dragleave','drop'].forEach(ev => wrap.addEventListener(ev, e => { wrap.classList.remove('dragging'); }));
    wrap.addEventListener('drop', e => { if (!activeConv || readonly) return; e.preventDefault(); const f = e.dataTransfer.files && e.dataTransfer.files[0]; if (f) openPreview(f); });
  }
  document.getElementById('imgPreviewCancel').addEventListener('click', closePreview);
  document.getElementById('imgPreviewForm').addEventListener('submit', e => { e.preventDefault(); uploadImage(); });

  // Lightbox
  $(document).on('click', '.chat-img', function (e) {
    e.preventDefault();
    const full = this.dataset.full;
    document.getElementById('lightboxImg').src = full;
    document.getElementById('lightboxOpen').href = full;
    document.getElementById('lightboxTitle').textContent = document.getElementById('threadName').textContent;
    document.getElementById('chatLightbox').style.display = 'flex';
  });
  function closeLightbox() { document.getElementById('chatLightbox').style.display = 'none'; document.getElementById('lightboxImg').removeAttribute('src'); }
  document.getElementById('lightboxClose').addEventListener('click', closeLightbox);
  document.getElementById('lightboxBody').addEventListener('click', e => { if (e.target.id === 'lightboxBody') closeLightbox(); });
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (document.getElementById('chatLightbox').style.display !== 'none') closeLightbox();
    else if (document.getElementById('imgPreview').style.display !== 'none') closePreview();
  });

  // Eigene Nachricht zurücknehmen (Rechtsklick / langes Drücken, innerhalb von 10 Minuten)
  function deleteMsg(el) {
    const id = parseInt(el.dataset.id, 10);
    msvConfirm('Nachricht zurücknehmen?').then(r => {
      if (!r.isConfirmed) return;
      post({ action:'delete', id:id }).then(d => {
        if (!d.success) { msvToast(d.message || 'Fehler', 'error'); return; }
        const t = el.querySelector('.t');
        const time = t ? t.textContent.trim() : '';
        el.classList.add('deleted'); el.classList.remove('can-del', 'has-img');
        el.innerHTML = '<span class="del"><i class="bi bi-slash-circle me-1"></i>Nachricht zurückgenommen</span><span class="t">' + esc(time) + '</span>';
      }).catch(() => msvToast('Fehler', 'error'));
    });
  }
  let pressTimer = null;
  $(document).on('contextmenu', '.chat-bubble.me.can-del', function (e) { e.preventDefault(); deleteMsg(this); });
  $(document).on('touchstart', '.chat-bubble.me.can-del', function () { const el = this; pressTimer = setTimeout(() => deleteMsg(el), 650); });
  $(document).on('touchend touchmove touchcancel', '.chat-bubble', function () { clearTimeout(pressTimer); });

  // ---------- Events ----------
  $(document).on('click', '.chat-row', function(){ openConv(parseInt(this.dataset.id,10)); });
  document.getElementById('chatForm').addEventListener('submit', e => { e.preventDefault(); sendMsg(); });
  const ta = document.getElementById('chatText');
  ta.addEventListener('keydown', e => { if (e.key==='Enter' && !e.shiftKey && !TOUCH) { e.preventDefault(); sendMsg(); } });
  ta.addEventListener('input', () => { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 120) + 'px'; });   // wächst mit dem Text
  document.getElementById('chatBack').addEventListener('click', closeThread);

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
    post({ action:'open', typ:'leiter', jungschuetze_id:jsid }).then(d=>{
      bootstrap.Modal.getInstance(document.getElementById('newChatModal')).hide();
      if (d.success && d.conversation_id) { loadList().then(() => openConv(d.conversation_id)); }
      else msvToast(d.message||'Fehler','error');
    });
  });
  <?php endif; ?>

  // ---------- Init ----------
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') { clearTimeout(timer); sync(true, false).then(() => { if (activeConv) markRead(); }).finally(schedule); }
  });
  <?php if ($initialConv > 0): ?>
  loadList().then(() => openConv(<?php echo $initialConv; ?>));
  <?php else: ?>
  loadList().finally(schedule);
  <?php endif; ?>
})();
</script>

<?php include 'portal_footer.php'; ?>
