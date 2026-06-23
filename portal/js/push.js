// push.js - Web-Push Client-Lifecycle fuers Mitgliederportal
// Vorlage: benachrichtigungs-konzept.md (Abschnitt 2.3 / 5)
//
// Stellt window.MSVPush bereit. Wird in portal_footer.php global geladen; die
// SW-Registrierung laeuft auf jeder Seite, der Subscribe-Flow (Permission-Prompt)
// nur per Button-Klick auf portal/benachrichtigungen.php.
//
// Pfade sind RELATIV zur Portal-Seite (/portal/...): ../sw.js, ../api/push.php
// -> deployment-unabhaengig (Domain-Root ODER Unterordner).

(function (global) {
    'use strict';

    var API      = '../api/push.php';
    var SW_URL   = '../sw.js';
    var SW_SCOPE = '../';

    function isiOS() {
        return /iP(hone|ad|od)/.test(navigator.userAgent);
    }
    function isStandalone() {
        return (global.navigator.standalone === true) ||
               (global.matchMedia && global.matchMedia('(display-mode: standalone)').matches);
    }
    function isSupported() {
        return ('serviceWorker' in navigator) && ('PushManager' in global) && ('Notification' in global);
    }

    // URL-safe Base64 -> Uint8Array (Standard-Snippet fuer applicationServerKey)
    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = global.atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    function api(action, method, csrfToken, body) {
        var opts = { method: method, headers: {} };
        if (method === 'POST') {
            opts.headers['Content-Type'] = 'application/json';
            var payload = body || {};
            payload.csrf_token = csrfToken;
            opts.body = JSON.stringify(payload);
        }
        return fetch(API + '?action=' + action, opts).then(function (r) { return r.json(); });
    }

    function registerSW() {
        if (!('serviceWorker' in navigator)) return Promise.reject(new Error('no-serviceworker'));
        return navigator.serviceWorker.register(SW_URL, { scope: SW_SCOPE });
    }

    // Aktueller Zustand fuer das UI
    function getStatus() {
        var status = {
            supported:    isSupported(),
            isiOS:        isiOS(),
            isStandalone: isStandalone(),
            permission:   (global.Notification ? Notification.permission : 'unsupported'),
            subscribed:   false
        };
        if (!status.supported) return Promise.resolve(status);
        return navigator.serviceWorker.ready
            .then(function (reg) { return reg.pushManager.getSubscription(); })
            .then(function (sub) { status.subscribed = !!sub; return status; })
            .catch(function () { return status; });
    }

    // Permission holen (nur aus User-Geste!) -> subscriben -> Abo ans Backend
    function subscribe(csrfToken) {
        if (!isSupported()) return Promise.reject(new Error('unsupported'));
        return Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') throw new Error('permission-' + perm);
            return registerSW().then(function () { return navigator.serviceWorker.ready; });
        }).then(function (reg) {
            return reg.pushManager.getSubscription().then(function (existing) {
                if (existing) return existing;
                return api('public_key', 'GET').then(function (res) {
                    if (!res.success || !res.public_key) throw new Error('no-public-key');
                    return reg.pushManager.subscribe({
                        userVisibleOnly: true, // Pflicht in Chrome
                        applicationServerKey: urlBase64ToUint8Array(res.public_key)
                    });
                });
            });
        }).then(function (sub) {
            var json = sub.toJSON();
            return api('subscribe', 'POST', csrfToken, {
                endpoint: json.endpoint,
                p256dh:   json.keys.p256dh,
                auth:     json.keys.auth,
                geraet:   navigator.userAgent.substring(0, 100)
            });
        });
    }

    // Abo auf diesem Geraet entfernen (Browser + Backend)
    function unsubscribe(csrfToken) {
        if (!isSupported()) return Promise.resolve({ success: true });
        return navigator.serviceWorker.ready
            .then(function (reg) { return reg.pushManager.getSubscription(); })
            .then(function (sub) {
                if (!sub) return { success: true, message: 'Kein Abo vorhanden.' };
                var endpoint = sub.endpoint;
                return sub.unsubscribe().then(function () {
                    return api('unsubscribe', 'POST', csrfToken, { endpoint: endpoint });
                });
            });
    }

    function test(csrfToken) {
        return api('test', 'POST', csrfToken, {});
    }

    global.MSVPush = {
        isSupported:  isSupported,
        isiOS:        isiOS,
        isStandalone: isStandalone,
        registerSW:   registerSW,
        getStatus:    getStatus,
        subscribe:    subscribe,
        unsubscribe:  unsubscribe,
        test:         test
    };
})(window);
