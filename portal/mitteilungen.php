<?php
// portal/mitteilungen.php - Benachrichtigungs-Verlauf (Glocke) + Vorstand-Broadcast
$portal_page_title = 'Benachrichtigungen';

$portal_page_css = '
.mt-compose { margin-bottom: var(--p-3); padding-top: 0; padding-bottom: 0; }
.mt-compose textarea { resize: vertical; min-height: 80px; }
.mt-compose-head {
    display: flex; align-items: center; gap: var(--p-2); width: 100%;
    background: none; border: none; padding: var(--p-3) 0; cursor: pointer; text-align: left;
}
.mt-compose-head .p-section-title { flex: 1; }
.mt-compose-caret { color: var(--p-text-muted); transition: transform .2s ease; }
.mt-compose.collapsed .mt-compose-caret { transform: rotate(-90deg); }
.mt-compose-body {
    overflow: hidden;
    max-height: 800px;
    transition: max-height .25s ease, opacity .2s ease, padding .2s ease;
    padding-bottom: var(--p-3);
}
.mt-compose.collapsed .mt-compose-body {
    max-height: 0; opacity: 0; padding-bottom: 0; pointer-events: none;
}
.mt-roles { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .35rem; }
.mt-role-chip {
    border: 1px solid var(--p-border); background: #fff; color: var(--p-text);
    border-radius: 20px; padding: .4rem .9rem; font-size: .85rem; cursor: pointer;
    display: inline-flex; align-items: center; gap: .4rem; transition: all .15s ease;
    -webkit-tap-highlight-color: transparent;
}
.mt-role-chip .bi { font-size: .85rem; opacity: 0; transition: opacity .15s ease; }
.mt-role-chip.active { background: var(--primary-color); border-color: var(--primary-color); color: #fff; }
.mt-role-chip.active .bi { opacity: 1; }
.mt-hint { font-size: .78rem; color: var(--p-text-muted); }

.mt-list { list-style: none; padding: 0; margin: 0; }
.mt-item {
    display: flex; gap: .7rem; padding: .7rem .2rem; border-top: 1px solid var(--p-border);
    text-decoration: none; color: inherit;
}
.mt-item:first-child { border-top: none; }
.mt-item.unread { background: rgba(59,89,152,.05); border-radius: var(--p-radius-sm); }
.mt-item.unread .mt-item-title { font-weight: 700; }
.mt-item-icon { color: var(--primary-color); font-size: 1.1rem; flex-shrink: 0; margin-top: .1rem; }
.mt-item-body { min-width: 0; flex: 1; }
.mt-item-title { font-size: .9rem; color: var(--p-text); }
.mt-item-text { font-size: .82rem; color: var(--p-text-muted); word-break: break-word; }
.mt-item-time { font-size: .72rem; color: #adb5bd; margin-top: .15rem; }
.mt-empty { text-align: center; color: var(--p-text-muted); padding: 2rem 0; font-size: .9rem; }
.mt-toolbar { display: flex; justify-content: flex-end; margin-bottom: var(--p-2); }
.mt-toolbar button { background: none; border: none; color: var(--primary-color); font-size: .82rem; cursor: pointer; }
.mt-toolbar button:hover { text-decoration: underline; }
#mtLoadMore { width: 100%; margin-top: var(--p-2); }
';

include 'portal_header.php';
$csrf_token = ensureCsrfToken();
$istVorstand = isVorstand();
?>

<div class="p-narrow">

    <div class="portal-page-header">
        <h1><i class="bi bi-megaphone me-2"></i>Mitteilungen</h1>
        <p class="subtitle">Mitteilungen senden und empfangen</p>
    </div>

    <?php if ($istVorstand): ?>
    <!-- Vorstand: Mitteilung an alle senden (eingeklappt) -->
    <div class="p-section mt-compose collapsed" id="mtCompose">
        <button type="button" class="mt-compose-head" id="mtComposeToggle" aria-expanded="false" aria-controls="mtComposeBody">
            <span class="p-chip blue"><i class="bi bi-megaphone"></i></span>
            <span class="p-section-title">Mitteilung senden</span>
            <i class="bi bi-chevron-down mt-compose-caret"></i>
        </button>
        <div class="mt-compose-body" id="mtComposeBody">
            <div class="p-field">
                <label for="mtTitel">Titel</label>
                <input type="text" id="mtTitel" maxlength="150" placeholder="z. B. Wichtige Info zum Vereinsabend">
            </div>
            <div class="p-field">
                <label for="mtText">Nachricht</label>
                <textarea id="mtText" maxlength="500" placeholder="Deine Mitteilung an die Mitglieder…"></textarea>
            </div>
            <div class="p-field">
                <label>Empfänger</label>
                <div class="mt-roles" id="mtRoles">
                    <button type="button" class="mt-role-chip active" data-role="mitglied"><i class="bi bi-check2"></i>Mitglieder</button>
                    <button type="button" class="mt-role-chip active" data-role="vorstand"><i class="bi bi-check2"></i>Vorstand</button>
                    <button type="button" class="mt-role-chip" data-role="jungschuetze"><i class="bi bi-check2"></i>Jungschützen</button>
                </div>
                <div class="mt-hint">Keine Auswahl = an alle freigegebenen Benutzer.</div>
            </div>
            <button type="button" class="p-btn primary" id="mtSend">
                <i class="bi bi-send me-1"></i>Senden
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Verlauf -->
    <div class="p-section">
        <div class="p-section-header">
            <div class="p-chip orange"><i class="bi bi-bell"></i></div>
            <div class="p-section-title">Deine Benachrichtigungen</div>
        </div>
        <div class="mt-toolbar">
            <button type="button" id="mtMarkAll"><i class="bi bi-check2-all me-1"></i>Alle als gelesen</button>
        </div>
        <ul class="mt-list" id="mtList">
            <li class="mt-empty">Wird geladen…</li>
        </ul>
        <button type="button" class="p-btn ghost" id="mtLoadMore" hidden>Mehr anzeigen</button>
    </div>
</div>

<script>
(function () {
    var csrfToken = <?php echo json_encode($csrf_token); ?>;
    var API = '../api/benachrichtigungen.php';
    var PAGE = 20;
    var offset = 0;
    var elList = document.getElementById('mtList');
    var elMore = document.getElementById('mtLoadMore');

    function toast(msg, type) { if (typeof msvToast === 'function') msvToast(msg, type || 'info'); }

    var ICONS = {
        chat: 'bi-chat-dots', einsaetze: 'bi-person-badge', einsatz_tausch: 'bi-arrow-left-right',
        jm: 'bi-bullseye', umfragen: 'bi-clipboard-check', termine: 'bi-calendar-event',
        fotos: 'bi-images', jsk_betreuung: 'bi-people', mitteilung: 'bi-megaphone', allgemein: 'bi-bell'
    };

    function fmtTime(s) {
        // s ist 'YYYY-MM-DD HH:MM:SS' (Serverzeit). Einfache, lokale Anzeige.
        if (!s) return '';
        var t = s.replace(' ', 'T');
        var d = new Date(t);
        if (isNaN(d.getTime())) return s;
        var now = new Date();
        var diffMin = Math.round((now - d) / 60000);
        if (diffMin < 1) return 'gerade eben';
        if (diffMin < 60) return 'vor ' + diffMin + ' Min.';
        if (diffMin < 1440) return 'vor ' + Math.round(diffMin / 60) + ' Std.';
        return d.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric' }) +
               ' ' + d.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit' });
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function renderItem(it) {
        var unread = !it.gelesen_am;
        var icon = ICONS[it.kategorie] || ICONS.allgemein;
        var li = document.createElement(it.url ? 'a' : 'div');
        li.className = 'mt-item' + (unread ? ' unread' : '');
        if (it.url) li.href = '../' + it.url.replace(/^\/+/, '');
        li.innerHTML =
            '<i class="bi ' + icon + ' mt-item-icon"></i>' +
            '<div class="mt-item-body">' +
                '<div class="mt-item-title">' + esc(it.titel) + '</div>' +
                '<div class="mt-item-text">' + esc(it.text) + '</div>' +
                '<div class="mt-item-time">' + fmtTime(it.erstellt_am) + '</div>' +
            '</div>';
        if (unread) {
            li.addEventListener('click', function () {
                markRead(it.id);
                li.classList.remove('unread');
            });
        }
        return li;
    }

    function load(reset) {
        if (reset) { offset = 0; elList.innerHTML = ''; }
        fetch(API + '?action=list&limit=' + PAGE + '&offset=' + offset)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) return;
                if (offset === 0 && (!d.items || !d.items.length)) {
                    elList.innerHTML = '<li class="mt-empty">Keine Benachrichtigungen.</li>';
                    elMore.hidden = true;
                    return;
                }
                d.items.forEach(function (it) { elList.appendChild(renderItem(it)); });
                offset += d.items.length;
                elMore.hidden = (d.items.length < PAGE);
            }).catch(function () {});
    }

    function markRead(id) {
        fetch(API + '?action=mark_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ id: id })
        }).catch(function () {});
    }

    document.getElementById('mtMarkAll').addEventListener('click', function () {
        fetch(API + '?action=mark_all_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (d && d.success) {
                Array.prototype.forEach.call(elList.querySelectorAll('.mt-item.unread'), function (el) {
                    el.classList.remove('unread');
                });
                toast('Alle als gelesen markiert.', 'success');
            }
        }).catch(function () {});
    });

    elMore.addEventListener('click', function () { load(false); });

    // ---------- Vorstand: Senden ----------
    var elSend = document.getElementById('mtSend');
    if (elSend) {
        // Einklappbare Compose-Card
        var elCompose = document.getElementById('mtCompose');
        var elToggle = document.getElementById('mtComposeToggle');
        if (elCompose && elToggle) {
            elToggle.addEventListener('click', function () {
                var collapsed = elCompose.classList.toggle('collapsed');
                elToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            });
        }

        // Empfänger-Chips umschalten
        Array.prototype.forEach.call(document.querySelectorAll('.mt-role-chip'), function (chip) {
            chip.addEventListener('click', function () { chip.classList.toggle('active'); });
        });

        elSend.addEventListener('click', function () {
            var titel = document.getElementById('mtTitel').value.trim();
            var text = document.getElementById('mtText').value.trim();
            var rollen = Array.prototype.map.call(
                document.querySelectorAll('.mt-role-chip.active'), function (c) { return c.getAttribute('data-role'); });
            if (!titel || !text) { toast('Bitte Titel und Nachricht ausfüllen.', 'warning'); return; }

            var go = function () {
                elSend.disabled = true;
                fetch(API + '?action=broadcast', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ titel: titel, text: text, rollen: rollen })
                }).then(function (r) { return r.json(); }).then(function (d) {
                    elSend.disabled = false;
                    if (d && d.success) {
                        if (typeof msvSuccess === 'function') msvSuccess(d.message || 'Mitteilung gesendet.');
                        else toast(d.message || 'Mitteilung gesendet.', 'success');
                        document.getElementById('mtTitel').value = '';
                        document.getElementById('mtText').value = '';
                        load(true);
                    } else {
                        toast((d && d.message) || 'Senden fehlgeschlagen.', 'error');
                    }
                }).catch(function () { elSend.disabled = false; toast('Senden fehlgeschlagen.', 'error'); });
            };

            if (typeof msvConfirm === 'function') {
                msvConfirm('Mitteilung jetzt an die ausgewählten Empfänger senden?', 'Senden bestätigen')
                    .then(function (res) { if (res && res.isConfirmed) go(); });
            } else { go(); }
        });
    }

    load(true);
})();
</script>

<?php include 'portal_footer.php'; ?>
