// native_bridge.js - Verbindet das Web-Portal mit nativen Capacitor-Plugins,
// WENN es in der nativen App (iOS/Android) laeuft. Im normalen Browser/PWA tut
// diese Datei NICHTS (window.Capacitor ist dort undefined).
//
// Aufgaben in der App:
//   - FCM-Token holen (Firebase Messaging) -> an api/push.php?action=register_native
//   - Token-Rotation nachregistrieren
//   - Tap auf eine Push-Notification -> Deep-Link in die richtige Portal-Seite
//
// Setzt das gleiche CSRF-Muster wie push.js voraus (window.MSV_CSRF aus portal_footer.php).
// Native Plugin-Aufrufe laufen ueber den Capacitor-Bridge-Proxy (Capacitor.Plugins.*),
// daher ist KEIN gebundeltes Plugin-JS auf der Seite noetig.

(function (global) {
    'use strict';

    var Cap = global.Capacitor;
    if (!Cap || typeof Cap.isNativePlatform !== 'function' || !Cap.isNativePlatform()) {
        return; // kein nativer Kontext -> nichts tun (Browser/PWA)
    }

    var API = '../api/push.php';

    function getCsrf() { return global.MSV_CSRF || ''; }
    function platform() { try { return Cap.getPlatform(); } catch (e) { return ''; } }

    function post(action, body) {
        var payload = body || {};
        payload.csrf_token = getCsrf();
        return fetch(API + '?action=' + action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json(); }).catch(function () { return null; });
    }

    function registerToken(token) {
        if (!token) return;
        post('register_native', {
            fcm_token:   token,
            platform:    platform(),
            app_version: (global.MSV_APP_VERSION || '')
        });
    }

    function gotoUrl(url) {
        if (!url) return;
        try { global.location.href = new URL(url, global.location.origin).href; }
        catch (e) { global.location.href = url; }
    }

    function setupFirebaseMessaging() {
        var FM = Cap.Plugins && Cap.Plugins.FirebaseMessaging;
        if (!FM) {
            if (global.console) console.warn('[native] FirebaseMessaging-Plugin fehlt.');
            return;
        }

        // Permission anfragen -> Token holen -> registrieren.
        Promise.resolve(FM.requestPermissions())
            .then(function () { return FM.getToken(); })
            .then(function (res) { registerToken(res && res.token); })
            .catch(function (e) { if (global.console) console.warn('[native] FCM-Setup:', e); });

        // Token-Rotation
        try {
            FM.addListener('tokenReceived', function (ev) { registerToken(ev && ev.token); });
        } catch (e) {}

        // Tap auf Notification -> Deep-Link (data.url aus dem FCM-Payload)
        try {
            FM.addListener('notificationActionPerformed', function (ev) {
                var n = (ev && ev.notification) || {};
                var data = n.data || {};
                gotoUrl(data.url);
            });
        } catch (e) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupFirebaseMessaging);
    } else {
        setupFirebaseMessaging();
    }

    // Fuer Logout / Debug von aussen erreichbar.
    global.MSVNative = {
        platform:     platform,
        registerToken: registerToken,
        unregister: function () {
            var FM = Cap.Plugins && Cap.Plugins.FirebaseMessaging;
            if (!FM) return Promise.resolve();
            return Promise.resolve(FM.getToken())
                .then(function (res) { return res && res.token ? post('unregister_native', { fcm_token: res.token }) : null; })
                .catch(function () {});
        }
    };
})(window);
