<?php
// portal/benachrichtigungen.php - Push-Benachrichtigungen ein-/ausschalten
$portal_page_title = 'Benachrichtigungen';

$portal_page_css = '
/* Geraete-Status */
.bn-status {
    display: flex; align-items: center; gap: var(--p-2);
    font-size: .85rem; color: var(--p-text); margin-bottom: var(--p-3);
}
.bn-status .dot {
    width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; background: #cbd5e0;
}
.bn-status.on  .dot { background: var(--success-color); }
.bn-status.off .dot { background: var(--danger-color); }

.bn-hint {
    font-size: .8rem; color: var(--p-text-muted); background: #f8f9fa;
    border: 1px solid var(--p-border); border-radius: var(--p-radius-sm);
    padding: var(--p-2) var(--p-3); margin-bottom: var(--p-2);
}
.bn-hint i { color: #e65100; margin-right: var(--p-1); }

/* Button-Zeile (Test / Deaktivieren nebeneinander) */
.bn-btn-row { display: flex; gap: var(--p-2); margin-top: var(--p-2); }
.bn-btn-row .p-btn { flex: 1; }

/* Geraeteliste */
.bn-devices { list-style: none; padding: 0; margin: var(--p-3) 0 0; }
.bn-devices li {
    display: flex; align-items: center; gap: var(--p-2); font-size: .78rem;
    color: var(--p-text-muted); padding: var(--p-1) 0; border-top: 1px solid var(--p-border);
}
.bn-devices li i { color: #a0aec0; }

/* Themen-Schalter */
.bn-toggle {
    display: flex; align-items: center; justify-content: space-between;
    padding: var(--p-2) 0; border-top: 1px solid var(--p-border);
}
.bn-toggle:first-of-type { border-top: none; }
.bn-toggle-label { display: flex; align-items: center; gap: var(--p-2); }
.bn-toggle-label .ti {
    width: 30px; height: 30px; border-radius: var(--p-radius-sm); display: flex;
    align-items: center; justify-content: center; font-size: .95rem; flex-shrink: 0;
    background: #eef1f6; color: var(--primary-color);
}
.bn-toggle-text .t1 { font-size: .86rem; font-weight: 600; color: var(--p-text); }
.bn-toggle-text .t2 { font-size: .74rem; color: #a0aec0; }
.bn-master { border-bottom: 2px solid var(--p-border); padding-bottom: var(--p-3); margin-bottom: var(--p-1); }
.bn-master .bn-toggle-text .t1 { font-weight: 700; }

/* Bootstrap-Switch etwas groesser */
.form-switch .form-check-input { width: 2.4em; height: 1.3em; cursor: pointer; }
.form-switch .form-check-input:checked { background-color: var(--primary-color); border-color: var(--primary-color); }
.bn-topics.disabled { opacity: 0.45; pointer-events: none; }

/* Vorlaufzeit-Auswahl */
.bn-lead { border-top: 2px solid var(--p-border); margin-top: var(--p-1); padding-top: var(--p-3); }
.bn-lead .ti { background: #fff3e0; color: #e65100; }
.bn-lead-select {
    border: 1px solid #e2e8f0; border-radius: var(--p-radius-sm); padding: .45rem .6rem;
    font-size: .85rem; color: var(--p-text); background: #fff; min-width: 135px; cursor: pointer;
}
.bn-lead-select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(59,89,152,0.12); }
';

// Diese Seite zeigt im Geraete-Status einen eigenen, kontextbezogenen iOS-Installations-
// Hinweis -> die globale "Als App installieren"-Karte hier unterdruecken (keine Dopplung).
$portal_hide_pwa_install = true;

include 'portal_header.php';

$csrf_token = ensureCsrfToken();
?>

<div class="p-narrow">

    <!-- Dieses Geraet -->
    <div class="p-section">
        <div class="p-section-header">
            <div class="p-chip blue"><i class="bi bi-bell"></i></div>
            <div class="p-section-title">Push auf diesem Gerät</div>
        </div>

        <div class="bn-status off" id="bnStatus">
            <span class="dot"></span>
            <span id="bnStatusText">Status wird geprüft…</span>
        </div>

        <div id="bnDeviceArea"><!-- Button / Hinweis (per JS) --></div>

        <ul class="bn-devices" id="bnDevices"></ul>
    </div>

    <!-- Themen -->
    <div class="p-section">
        <div class="p-section-header">
            <div class="p-chip orange"><i class="bi bi-sliders"></i></div>
            <div class="p-section-title">Wofür möchtest du erinnert werden?</div>
        </div>

        <!-- Haupt-Schalter -->
        <div class="bn-toggle bn-master">
            <div class="bn-toggle-label">
                <div class="bn-toggle-text">
                    <div class="t1">Benachrichtigungen aktiv</div>
                    <div class="t2">Haupt-Schalter – schaltet alle Themen ein/aus</div>
                </div>
            </div>
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" id="prefMaster">
            </div>
        </div>

        <div class="bn-topics" id="bnTopics">
            <?php if (!isJungschuetze()): ?>
            <div class="bn-toggle">
                <div class="bn-toggle-label">
                    <div class="ti"><i class="bi bi-person-badge"></i></div>
                    <div class="bn-toggle-text">
                        <div class="t1">Kommende Einsätze</div>
                        <div class="t2">Erinnerung an deine zugewiesenen Einsätze</div>
                    </div>
                </div>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input pref-topic" type="checkbox" role="switch" id="prefEinsaetze" data-field="einsaetze">
                </div>
            </div>

            <div class="bn-toggle">
                <div class="bn-toggle-label">
                    <div class="ti"><i class="bi bi-bullseye"></i></div>
                    <div class="bn-toggle-text">
                        <div class="t1">Jahresmeisterschaft</div>
                        <div class="t2">Erinnerung an kommende JM-Schiesstage</div>
                    </div>
                </div>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input pref-topic" type="checkbox" role="switch" id="prefJm" data-field="jm">
                </div>
            </div>

            <div class="bn-toggle">
                <div class="bn-toggle-label">
                    <div class="ti"><i class="bi bi-clipboard-check"></i></div>
                    <div class="bn-toggle-text">
                        <div class="t1">Umfrage-Fristen</div>
                        <div class="t2">Erinnerung, bevor eine Umfrage abläuft</div>
                    </div>
                </div>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input pref-topic" type="checkbox" role="switch" id="prefUmfragen" data-field="umfragen">
                </div>
            </div>

            <div class="bn-toggle">
                <div class="bn-toggle-label">
                    <div class="ti"><i class="bi bi-calendar-event"></i></div>
                    <div class="bn-toggle-text">
                        <div class="t1">Vereinstermine & Training</div>
                        <div class="t2">Erinnerung an Anlässe und Trainings</div>
                    </div>
                </div>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input pref-topic" type="checkbox" role="switch" id="prefTermine" data-field="termine">
                </div>
            </div>

            <div class="bn-toggle bn-lead">
                <div class="bn-toggle-label">
                    <div class="ti"><i class="bi bi-clock-history"></i></div>
                    <div class="bn-toggle-text">
                        <div class="t1">Wie viele Tage vorher?</div>
                        <div class="t2">Vorlauf der einmaligen Erinnerung – gilt für alle Themen</div>
                    </div>
                </div>
                <select id="prefLead" class="bn-lead-select" aria-label="Vorlaufzeit in Tagen">
                    <option value="0">am selben Tag</option>
                    <option value="1">1 Tag vorher</option>
                    <option value="2">2 Tage vorher</option>
                    <option value="3">3 Tage vorher</option>
                    <option value="5">5 Tage vorher</option>
                    <option value="7">7 Tage vorher</option>
                    <option value="14">14 Tage vorher</option>
                </select>
            </div>
            <?php endif; ?>
            <?php if (isJungschuetze()): ?>
            <div class="bn-hint"><i class="bi bi-info-circle"></i>Du wirst automatisch benachrichtigt, sobald dich ein Mitglied für einen Termin übernimmt. Stelle sicher, dass Push oben aktiviert ist.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!isJungschuetze() && jskFeatureAktiv()): ?>
    <!-- Jungschützen-Betreuung -->
    <div class="p-section">
        <div class="p-section-header">
            <div class="p-chip" style="background:#ccfbf1;color:#0d9488;"><i class="bi bi-people"></i></div>
            <div class="p-section-title">Jungschützen-Betreuung</div>
        </div>
        <div class="bn-toggle">
            <div class="bn-toggle-label">
                <div class="ti" style="background:#ccfbf1;color:#0d9488;"><i class="bi bi-person-hearts"></i></div>
                <div class="bn-toggle-text">
                    <div class="t1">Jungschützen betreuen</div>
                    <div class="t2">Board anzeigen & benachrichtigt werden, wenn ein Jungschütze einen Schiess-Termin sucht</div>
                </div>
            </div>
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" id="prefJsk">
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="js/push.js"></script>
<script>
(function () {
    var csrfToken = <?php echo json_encode($csrf_token); ?>;
    var PREFS_API = '../api/benachrichtigung_prefs.php';
    var PUSH_API  = '../api/push.php';

    var elStatus     = document.getElementById('bnStatus');
    var elStatusText = document.getElementById('bnStatusText');
    var elDeviceArea = document.getElementById('bnDeviceArea');
    var elDevices    = document.getElementById('bnDevices');
    var elMaster     = document.getElementById('prefMaster');
    var elTopics     = document.getElementById('bnTopics');
    var topicInputs  = Array.prototype.slice.call(document.querySelectorAll('.pref-topic'));

    function toast(msg, type) { if (typeof msvToast === 'function') msvToast(msg, type || 'info'); }

    // ---------- Themen-Schalter ----------
    function applyMasterState() {
        var on = elMaster.checked;
        elTopics.classList.toggle('disabled', !on);
    }

    function loadPrefs() {
        fetch(PREFS_API).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.success) return;
            var p = data.prefs;
            function setChk(id, v) { var e = document.getElementById(id); if (e) e.checked = !!v; }
            elMaster.checked = !!p.push_aktiv;
            setChk('prefEinsaetze', p.einsaetze);
            setChk('prefJm',        p.jm);
            setChk('prefUmfragen',  p.umfragen);
            setChk('prefTermine',   p.termine);
            setChk('prefJsk',       p.jsk_betreuung);

            // Vorlaufzeit: null (noch nicht angepasst) -> Default 3 anzeigen (nur falls Element vorhanden)
            var leadSel = document.getElementById('prefLead');
            if (leadSel) {
                var lead = (p.lead_tage === null || typeof p.lead_tage === 'undefined') ? 3 : p.lead_tage;
                leadSel.value = String(lead);
                if (leadSel.selectedIndex < 0) leadSel.value = '3';
            }

            applyMasterState();
        }).catch(function () {});
    }

    function savePrefs() {
        function chk(id) { var e = document.getElementById(id); return (e && e.checked) ? 1 : 0; }
        var payload = {
            csrf_token: csrfToken,
            push_aktiv: elMaster.checked ? 1 : 0
        };
        var elEins = document.getElementById('prefEinsaetze');
        if (elEins) {
            payload.einsaetze = chk('prefEinsaetze');
            payload.jm        = chk('prefJm');
            payload.umfragen  = chk('prefUmfragen');
            payload.termine   = chk('prefTermine');
        }
        var elLead = document.getElementById('prefLead');
        if (elLead) payload.lead_tage = parseInt(elLead.value, 10);
        var elJsk = document.getElementById('prefJsk');
        if (elJsk) payload.jsk_betreuung = elJsk.checked ? 1 : 0;
        fetch(PREFS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.success) { toast('Gespeichert', 'success'); }
            else { toast(data.message || 'Fehler beim Speichern', 'error'); }
        }).catch(function () { toast('Verbindungsfehler', 'error'); });
    }

    elMaster.addEventListener('change', function () { applyMasterState(); savePrefs(); });
    topicInputs.forEach(function (inp) { inp.addEventListener('change', savePrefs); });
    var elLeadSel = document.getElementById('prefLead');
    if (elLeadSel) elLeadSel.addEventListener('change', savePrefs);
    var elJskToggle = document.getElementById('prefJsk');
    if (elJskToggle) elJskToggle.addEventListener('change', function () {
        savePrefs();
        toast(this.checked ? 'Board wird nach dem Neuladen angezeigt.' : 'Jungschützen-Board deaktiviert.', 'info');
    });

    // ---------- Geraete-Status / Push ----------
    function setStatus(on, text) {
        elStatus.classList.toggle('on', on);
        elStatus.classList.toggle('off', !on);
        elStatusText.textContent = text;
    }

    function loadDevices() {
        fetch(PUSH_API + '?action=list').then(function (r) { return r.json(); }).then(function (data) {
            elDevices.innerHTML = '';
            if (!data.success || !data.geraete || !data.geraete.length) return;
            data.geraete.forEach(function (g) {
                var li = document.createElement('li');
                var name = (g.geraet || 'Unbekanntes Gerät');
                li.innerHTML = '<i class="bi bi-phone"></i><span></span>';
                li.querySelector('span').textContent = name + (g.erstellt_am ? '  ·  seit ' + (g.erstellt_am.substring(0, 10)) : '');
                elDevices.appendChild(li);
            });
        }).catch(function () {});
    }

    function renderDevice(status) {
        elDeviceArea.innerHTML = '';

        // Nicht unterstuetzt
        if (!status.supported) {
            if (status.isiOS && !status.isStandalone) {
                setStatus(false, 'Auf dem iPhone/iPad zuerst installieren');
                var hint = document.createElement('div');
                hint.className = 'bn-hint';
                hint.innerHTML = '<i class="bi bi-info-circle"></i>Tippe in Safari auf <strong>Teilen → Zum Home-Bildschirm</strong>, öffne die App vom Home-Bildschirm und aktiviere Push hier.';
                elDeviceArea.appendChild(hint);
            } else {
                setStatus(false, 'Push wird von diesem Browser nicht unterstützt');
            }
            return;
        }

        // Permission hart verweigert
        if (status.permission === 'denied') {
            setStatus(false, 'In den Browser-Einstellungen blockiert');
            var h = document.createElement('div');
            h.className = 'bn-hint';
            h.innerHTML = '<i class="bi bi-info-circle"></i>Benachrichtigungen wurden für diese Seite blockiert. Bitte in den Browser-/Geräteeinstellungen wieder erlauben.';
            elDeviceArea.appendChild(h);
            return;
        }

        if (status.subscribed) {
            setStatus(true, 'Auf diesem Gerät aktiviert');
            var row = document.createElement('div');
            row.className = 'bn-btn-row';
            row.innerHTML =
                '<button class="p-btn ghost" id="bnTest"><i class="bi bi-send"></i> Test senden</button>' +
                '<button class="p-btn danger-outline" id="bnOff"><i class="bi bi-bell-slash"></i> Deaktivieren</button>';
            elDeviceArea.appendChild(row);
            document.getElementById('bnTest').addEventListener('click', onTest);
            document.getElementById('bnOff').addEventListener('click', onUnsubscribe);
        } else {
            setStatus(false, 'Auf diesem Gerät nicht aktiviert');
            var btn = document.createElement('button');
            btn.className = 'p-btn primary block';
            btn.id = 'bnOn';
            btn.innerHTML = '<i class="bi bi-bell"></i> Push auf diesem Gerät aktivieren';
            elDeviceArea.appendChild(btn);
            btn.addEventListener('click', onSubscribe);
        }
    }

    function refresh() {
        MSVPush.getStatus().then(renderDevice);
        loadDevices();
    }

    function onSubscribe() {
        var btn = document.getElementById('bnOn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Aktiviere…'; }
        MSVPush.subscribe(csrfToken).then(function (res) {
            if (res && res.success) { toast(res.message || 'Aktiviert', 'success'); }
            else { toast((res && res.message) || 'Aktivierung fehlgeschlagen', 'error'); }
        }).catch(function (err) {
            var m = String(err && err.message || '');
            if (m.indexOf('permission-denied') === 0) toast('Du hast Benachrichtigungen abgelehnt.', 'error');
            else if (m.indexOf('permission-') === 0)   toast('Benachrichtigungen wurden nicht erlaubt.', 'error');
            else toast('Aktivierung fehlgeschlagen.', 'error');
        }).finally(refresh);
    }

    function onUnsubscribe() {
        var btn = document.getElementById('bnOff');
        if (btn) { btn.disabled = true; }
        MSVPush.unsubscribe(csrfToken).then(function (res) {
            toast((res && res.message) || 'Deaktiviert', 'info');
        }).catch(function () { toast('Fehler beim Deaktivieren', 'error'); })
          .finally(refresh);
    }

    function onTest() {
        var btn = document.getElementById('bnTest');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Senden…'; }
        MSVPush.test(csrfToken).then(function (res) {
            if (res && res.success) toast(res.message || 'Test gesendet', 'success');
            else toast((res && res.message) || 'Test fehlgeschlagen', 'error');
        }).catch(function () { toast('Test fehlgeschlagen', 'error'); })
          .finally(function () { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send"></i> Test senden'; } });
    }

    // Init
    loadPrefs();
    refresh();
})();
</script>

<?php include 'portal_footer.php'; ?>
