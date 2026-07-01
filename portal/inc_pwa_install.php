<?php
// portal/inc_pwa_install.php
// Wiederverwendbarer "MSV Wilen als App installieren"-Hinweis fuers Portal.
//   - iOS/iPadOS (Safari): es gibt KEIN Install-API -> Schritt-fuer-Schritt-Anleitung
//     (Teilen -> Zum Home-Bildschirm).
//   - Android/Chromium: nutzt das 'beforeinstallprompt'-Event -> echter "Installieren"-Button.
//   - Bereits installiert (standalone) oder nicht unterstuetzt -> wird gar nicht angezeigt.
// Das fruehe Abfangen von 'beforeinstallprompt' passiert in portal_header.php (<head>),
// weil das Event oft vor diesem Skript feuert; es wird in window.__msvPWAprompt geparkt.
//
// Option (vor dem include setzbar): $pwa_dismissible (bool, default true).
$pwa_dismissible = isset($pwa_dismissible) ? (bool) $pwa_dismissible : true;
?>
<div id="pwaInstallCard" class="pwa-install" style="display:none;" data-dismissible="<?php echo $pwa_dismissible ? '1' : '0'; ?>">
    <div class="pwa-install-icon"><i class="bi bi-phone"></i></div>
    <div class="pwa-install-body">
        <div class="pwa-install-title">MSV Wilen als App installieren</div>
        <div class="pwa-install-text" id="pwaInstallText"></div>
        <div class="pwa-install-actions" id="pwaInstallActions"></div>
    </div>
    <?php if ($pwa_dismissible): ?>
    <button type="button" class="pwa-install-close" id="pwaInstallClose" aria-label="Hinweis ausblenden"><i class="bi bi-x-lg"></i></button>
    <?php endif; ?>
</div>

<style>
.pwa-install{display:flex;align-items:flex-start;gap:.85rem;position:relative;
    background:linear-gradient(135deg,#eef3ff,#f7faff);border:1px solid #d7e2ff;
    border-radius:12px;padding:.9rem 1rem;margin-bottom:1rem;}
.pwa-install-icon{width:40px;height:40px;border-radius:10px;flex-shrink:0;display:flex;
    align-items:center;justify-content:center;background:var(--primary-color,#3b5998);
    color:#fff;font-size:1.2rem;}
.pwa-install-body{flex:1;min-width:0;}
.pwa-install-title{font-weight:700;font-size:.95rem;color:#1e293b;margin-bottom:.15rem;}
.pwa-install-text{font-size:.83rem;color:#475569;line-height:1.5;}
.pwa-install-text strong{color:#1e293b;}
.pwa-install-actions{margin-top:.6rem;}
.pwa-install-actions:empty{display:none;}
.pwa-ios-share{display:inline-flex;align-items:center;justify-content:center;width:1.5em;
    height:1.5em;border-radius:5px;background:#0a84ff;color:#fff;vertical-align:-.35em;}
.pwa-ios-share i{font-size:.85em;}
.pwa-install-close{position:absolute;top:.4rem;right:.4rem;background:transparent;border:none;
    color:#94a3b8;width:30px;height:30px;border-radius:8px;cursor:pointer;display:flex;
    align-items:center;justify-content:center;font-size:.8rem;}
.pwa-install-close:hover,.pwa-install-close:active{background:rgba(0,0,0,.06);color:#475569;}
@media (max-width:420px){.pwa-install-icon{display:none;}}
</style>

<script>
(function(){
    var card = document.getElementById('pwaInstallCard');
    if (!card) return;
    // Native App (Capacitor) -> bereits "installiert", Hinweis nie zeigen.
    if (window.Capacitor && typeof Capacitor.isNativePlatform === 'function' && Capacitor.isNativePlatform()) return;
    var textEl    = document.getElementById('pwaInstallText');
    var actionsEl = document.getElementById('pwaInstallActions');
    var dismissible = card.getAttribute('data-dismissible') === '1';
    var DISMISS_KEY = 'msv_pwa_install_dismissed';
    var rendered = false;

    function isStandalone(){
        return (window.navigator.standalone === true) ||
               (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
    }
    function isiOS(){
        var ua = navigator.userAgent || '';
        return /iP(hone|ad|od)/.test(ua) ||
               (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // iPadOS 13+ meldet sich als Mac
    }
    function isDismissed(){ try { return localStorage.getItem(DISMISS_KEY) === '1'; } catch(e){ return false; } }

    // Schon als App installiert, oder (falls erlaubt) bewusst ausgeblendet -> nichts anzeigen.
    if (isStandalone()) return;
    if (dismissible && isDismissed()) return;

    function show(){ card.style.display = 'flex'; }
    function hide(){ card.style.display = 'none'; }

    function showIOS(){
        if (rendered) return; rendered = true;
        textEl.innerHTML = 'Tippe in Safari unten auf <strong>Teilen</strong> ' +
            '<span class="pwa-ios-share"><i class="bi bi-box-arrow-up"></i></span> und dann auf ' +
            '<strong>„Zum Home-Bildschirm“</strong>. Öffne das Portal danach über das neue App-Symbol – ' +
            'nur so funktionieren Push-Benachrichtigungen auf dem iPhone/iPad.';
        actionsEl.innerHTML = '';
        show();
    }

    function showAndroid(promptEvent){
        if (rendered) return; rendered = true;
        textEl.textContent = 'Installiere das Portal mit einem Tippen als App – danach startest du es wie eine ' +
            'normale App vom Startbildschirm, mit eigenem Symbol und Push-Benachrichtigungen.';
        actionsEl.innerHTML = '<button type="button" class="p-btn primary" id="pwaInstallBtn">' +
            '<i class="bi bi-download me-1"></i>App installieren</button>';
        show();
        document.getElementById('pwaInstallBtn').addEventListener('click', function(){
            var btn = this; btn.disabled = true;
            promptEvent.prompt();
            (promptEvent.userChoice || Promise.resolve()).then(function(choice){
                if (choice && choice.outcome === 'accepted') { hide(); }
                else { btn.disabled = false; }
                window.__msvPWAprompt = null; // Event ist verbraucht
            }).catch(function(){ btn.disabled = false; });
        });
    }

    // Schliessen + "installiert"-Event verdrahten (unabhaengig von der Variante).
    var closeBtn = document.getElementById('pwaInstallClose');
    if (closeBtn) closeBtn.addEventListener('click', function(){
        hide();
        if (dismissible) { try { localStorage.setItem(DISMISS_KEY, '1'); } catch(e){} }
    });
    window.addEventListener('appinstalled', function(){ hide(); });

    // Android/Chromium: beforeinstallprompt (evtl. schon frueh in portal_header abgefangen).
    window.addEventListener('beforeinstallprompt', function(e){ e.preventDefault(); window.__msvPWAprompt = e; showAndroid(e); });
    window.addEventListener('msv-pwa-available', function(){ if (window.__msvPWAprompt) showAndroid(window.__msvPWAprompt); });

    if (window.__msvPWAprompt) { showAndroid(window.__msvPWAprompt); }
    else if (isiOS())          { showIOS(); }
    // sonst: Browser ohne Install-Prompt (z.B. Desktop-Firefox) -> bleibt versteckt,
    //        bis evtl. doch noch ein beforeinstallprompt feuert.
})();
</script>
