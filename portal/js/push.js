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

    // localStorage-Keys fuer den Launch-Re-Sync (Selbstheilung):
    //   LS_LAST_ENDPOINT - zuletzt am Server registrierter Endpoint (Guard gegen Doppel-POST)
    //   LS_DEVICE_ON     - '1' wenn dieses Geraet bewusst Push aktiviert hat
    //   LS_DEVICE_OFF    - '1' wenn der User Push auf diesem Geraet bewusst deaktiviert hat
    //                      (verhindert ungefragtes Reaktivieren durch den Re-Sync)
    var LS_LAST_ENDPOINT = 'msv_push_letzter_endpoint';
    var LS_DEVICE_ON     = 'msv_push_aktiv';
    var LS_DEVICE_OFF    = 'msv_push_aus';

    function lsGet(k) { try { return global.localStorage.getItem(k); } catch (e) { return null; } }
    function lsSet(k, v) { try { global.localStorage.setItem(k, v); } catch (e) {} }
    function lsDel(k) { try { global.localStorage.removeItem(k); } catch (e) {} }

    // CSRF-Token: die Seite legt ihn in window.MSV_CSRF ab (portal_footer.php).
    function getCsrf() { return global.MSV_CSRF || ''; }

    // Native App (Capacitor)? Dann uebernimmt native_bridge.js die Push-Registrierung
    // via FCM -> Web-Push hier komplett ueberspringen (kein Doppel-Kanal).
    function isNativeApp() {
        return !!(global.Capacitor && typeof global.Capacitor.isNativePlatform === 'function'
                  && global.Capacitor.isNativePlatform());
    }

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
        if (isNativeApp()) return Promise.reject(new Error('native-app')); // native: FCM via native_bridge.js
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
            }).then(function (res) {
                if (res && res.success) {
                    lsSet(LS_LAST_ENDPOINT, json.endpoint); // Re-Sync-Guard setzen
                    lsSet(LS_DEVICE_ON, '1');               // Geraet ist jetzt "Push-aktiv"
                    lsDel(LS_DEVICE_OFF);                   // evtl. "bewusst aus" aufheben
                }
                return res;
            });
        });
    }

    // Abo auf diesem Geraet entfernen (Browser + Backend)
    function unsubscribe(csrfToken) {
        if (!isSupported()) return Promise.resolve({ success: true });
        // Re-Sync-Guard & "aktiv"-Flag loeschen + "bewusst aus" merken, sonst heilt
        // der Launch-Re-Sync das gerade deaktivierte Abo sofort wieder herbei.
        lsDel(LS_LAST_ENDPOINT);
        lsDel(LS_DEVICE_ON);
        lsSet(LS_DEVICE_OFF, '1');
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

    // -------------------------------------------------------------------------
    // Launch-Re-Sync (Selbstheilung) — bei JEDEM Portal-Seitenaufruf aufrufen.
    // Gleicht das echte Browser-Abo mit dem Server ab und legt ein still vom OS
    // (v.a. iOS, das tote Endpoints mit HTTP 201 quittiert) verworfenes Abo
    // automatisch neu an. Best-effort: schlaegt etwas fehl, blockiert nichts.
    // -------------------------------------------------------------------------
    // Soll dieses Geraet Push haben? (fuer den Re-Sync, wenn das Browser-Abo fehlt)
    //   1. lokal als "aktiv" gemerkt        -> ja
    //   2. lokal als "bewusst aus" gemerkt  -> nein (nicht ungefragt reaktivieren)
    //   3. unbekannt (1. Start nach Update / localStorage geleert) -> Server fragen:
    //      hat der User noch ein Push-Geraet registriert? Tote iOS-Abos bleiben in
    //      der DB (Apple quittiert sie mit 201), daher ist das ein verlaesslicher
    //      "der wollte Push"-Hinweis. Nur erreichbar, wenn Permission bereits granted.
    function geraetWillPush() {
        if (lsGet(LS_DEVICE_ON) === '1')  return Promise.resolve(true);
        if (lsGet(LS_DEVICE_OFF) === '1') return Promise.resolve(false);
        return api('list', 'GET')
            .then(function (res) { return !!(res && res.success && res.geraete && res.geraete.length > 0); })
            .catch(function () { return false; });
    }

    function pushReSync() {
        if (isNativeApp()) return Promise.resolve();   // native App -> FCM via native_bridge.js
        if (!isSupported()) return Promise.resolve();
        // iOS liefert Push nur im installierten PWA-Kontext (Home-Bildschirm).
        if (isiOS() && !isStandalone()) return Promise.resolve();

        return registerSW()
            .then(function () { return navigator.serviceWorker.ready; })
            .then(function (reg) {
                // CSRF an den SW durchreichen (fuer Teil B: pushsubscriptionchange).
                try { if (reg.active) reg.active.postMessage({ type: 'SET_CSRF', token: getCsrf() }); } catch (e) {}

                if (!global.Notification || Notification.permission !== 'granted') return null;

                return reg.pushManager.getSubscription().then(function (sub) {
                    if (sub) return sub;
                    // Kein Abo im Browser (iOS hat es still verworfen). Nur neu anlegen,
                    // wenn dieses Geraet Push haben soll.
                    return geraetWillPush().then(function (will) {
                        if (!will) return null;
                        return api('public_key', 'GET').then(function (res) {
                            if (!res || !res.success || !res.public_key) return null;
                            return reg.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: urlBase64ToUint8Array(res.public_key)
                            });
                        });
                    });
                });
            })
            .then(function (sub) {
                if (!sub) return;
                var json = sub.toJSON();
                if (lsGet(LS_LAST_ENDPOINT) === json.endpoint) return; // unveraendert -> kein Request
                return api('subscribe', 'POST', getCsrf(), {
                    endpoint: json.endpoint,
                    p256dh:   json.keys.p256dh,
                    auth:     json.keys.auth,
                    geraet:   navigator.userAgent.substring(0, 100)
                }).then(function (res) {
                    if (res && res.success) {
                        lsSet(LS_LAST_ENDPOINT, json.endpoint);
                        lsSet(LS_DEVICE_ON, '1'); // Abo existiert -> Geraet als aktiv markieren
                        lsDel(LS_DEVICE_OFF);
                    }
                });
            })
            .catch(function (e) {
                if (global.console && console.warn) console.warn('[push] Re-Sync fehlgeschlagen:', e);
            });
    }

    global.MSVPush = {
        isSupported:  isSupported,
        isiOS:        isiOS,
        isStandalone: isStandalone,
        isNativeApp:  isNativeApp,
        registerSW:   registerSW,
        getStatus:    getStatus,
        subscribe:    subscribe,
        unsubscribe:  unsubscribe,
        pushReSync:   pushReSync,
        test:         test
    };
})(window);
