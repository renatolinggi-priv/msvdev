// sw.js - Service Worker fuer das MSV Wilen Mitgliederportal
// Vorlage: benachrichtigungs-konzept.md (Abschnitt 2.6)
//
// Liegt im App-Root (neben manifest.json). Wird von den Portal-Seiten relativ
// registriert (../sw.js, scope ../) -> deployment-unabhaengig (Domain-Root ODER
// Unterordner). Icon-/Ziel-Pfade werden gegen registration.scope aufgeloest.
//
// Push + Klick-Handling + Offline-Caching (network-first fuer Seiten, cache-first
// fuer Assets). Der CSRF-Cache (META_CACHE) ist versions-UNABHAENGIG und wird beim
// activate-Cleanup ausgenommen.

// Versionierten Cache bei jeder relevanten SW-/Asset-Aenderung hochzaehlen (v1 -> v2 ...).
var RUNTIME_CACHE = 'msv-rt-v1';

// Best-effort vorgecachte App-Shell (relativ zum Scope = App-Root). Fehlende
// Eintraege brechen die Installation NICHT ab (jeweils .catch).
var PRECACHE_URLS = [
    'css/portal.css',
    'inc/js/ssv-barcode.js',
    'inc/js/msv-toast.js',
    'icons/icon-192x192.png'
];

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(RUNTIME_CACHE).then(function(cache) {
            return Promise.all(PRECACHE_URLS.map(function(u) {
                return cache.add(scoped(u)).catch(function() {}); // tolerant
            }));
        }).then(function() { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(keys.map(function(k) {
                // Aktuellen Laufzeit-Cache + CSRF-Meta-Cache behalten, alte Versionen loeschen.
                if (k === RUNTIME_CACHE || k === META_CACHE) return null;
                return caches.delete(k);
            }));
        }).then(function() { return self.clients.claim(); })
    );
});

// Hilfsfunktion: relativen Pfad gegen den SW-Scope aufloesen (absolute URL)
function scoped(path) {
    try { return new URL(path, self.registration.scope).href; }
    catch (e) { return path; }
}

self.addEventListener('push', function(event) {
    var data = { titel: 'MSV Wilen', text: 'Neue Benachrichtigung', url: 'portal/dashboard.php' };
    try { if (event.data) data = Object.assign(data, event.data.json()); } catch (e) {}

    event.waitUntil(self.registration.showNotification(data.titel, {
        body:  data.text || '',
        icon:  scoped('icons/icon-192x192.png'),
        badge: scoped('icons/icon-192x192.png'),
        data:  data.url || 'portal/dashboard.php',  // Klick-Ziel mitgeben
        tag:   data.tag || undefined,                // gleiche tag -> ersetzt statt stapelt
    }));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    var target = scoped(event.notification.data || 'portal/dashboard.php');

    // Vorhandenes App-Fenster fokussieren statt ein zweites zu oeffnen.
    // includeUncontrolled: noetig, falls direkt nach SW-Update noch nicht "controlled".
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var c = clientList[i];
                if (c.url.indexOf(self.location.origin) === 0 && 'focus' in c) {
                    c.focus();
                    if ('navigate' in c) { return c.navigate(target); }
                    return;
                }
            }
            if (self.clients.openWindow) { return self.clients.openWindow(target); }
        })
    );
});

// ============================================================
// Teil B: Selbstheilung bei Abo-Rotation (pushsubscriptionchange)
// Vorlage: web-push-selbstheilung.md (Abschnitt 6)
//
// iOS feuert dieses Event unzuverlaessig -> der Launch-Re-Sync in push.js ist
// der verlaessliche Pfad. Teil B deckt nur "App ist zu, Abo rotiert, Push soll
// vor dem naechsten Oeffnen ankommen" ab. Schlaegt etwas fehl, heilt der
// naechste App-Start. Der SW macht KEIN Cache-Cleanup im activate-Handler,
// daher ueberlebt META_CACHE jedes Update automatisch.
// ============================================================
var META_CACHE = 'msv-push-meta'; // versions-UNABHAENGIG, NICHT im activate loeschen!

// CSRF-Token von der Seite entgegennehmen und persistieren (push.js -> postMessage).
self.addEventListener('message', function(event) {
    var d = event.data;
    if (d === 'SKIP_WAITING') { self.skipWaiting(); return; }
    if (d && d.type === 'SET_CSRF' && d.token) {
        event.waitUntil(speichereCsrf(d.token));
    }
});

self.addEventListener('pushsubscriptionchange', function(event) {
    event.waitUntil((function() {
        var sub = event.newSubscription;
        // Browser liefert newSubscription oft nicht -> selbst neu abonnieren.
        var ensureSub = sub ? Promise.resolve(sub) : fetch(scoped('api/push.php?action=public_key'))
            .then(function(r) { return r.ok ? r.json() : null; })
            .then(function(res) {
                if (!res || !res.public_key) return null;
                return self.registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(res.public_key)
                });
            });

        return ensureSub.then(function(newSub) {
            if (!newSub) return;
            var json = newSub.toJSON();
            return leseCsrf().then(function(token) {
                return fetch(scoped('api/push.php?action=subscribe'), {
                    method: 'POST',
                    credentials: 'include', // Session-Cookie mitsenden (same-origin)
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': token
                    },
                    body: JSON.stringify({
                        endpoint: json.endpoint,
                        p256dh:   json.keys.p256dh,
                        auth:     json.keys.auth,
                        geraet:   ((self.navigator && self.navigator.userAgent) || '').substring(0, 100)
                    })
                });
            });
        }).catch(function() { /* Launch-Re-Sync faengt den Rest beim naechsten Start */ });
    })());
});

function speichereCsrf(token) {
    return caches.open(META_CACHE).then(function(c) {
        return c.put('/__csrf', new Response(token, { headers: { 'Content-Type': 'text/plain' } }));
    }).catch(function() {});
}
function leseCsrf() {
    return caches.open(META_CACHE)
        .then(function(c) { return c.match('/__csrf'); })
        .then(function(r) { return r ? r.text() : ''; })
        .catch(function() { return ''; });
}

// Gleiche Helferfunktion wie in push.js — der SW importiert keine App-Module.
function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw = self.atob(base64);
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
}

// ============================================================
// Offline-Caching
// - Seiten (Navigationen): network-first -> online immer frisch, offline letzte Fassung.
// - Statische Assets (css/js/img/fonts, auch CDN): cache-first + Hintergrund-Update.
// - Dynamische Endpoints (api/, cron/, Login/Logout/Session): NIE cachen.
// - Logout (login.php?logout=...): Laufzeit-Cache leeren (keine geschuetzten Seiten behalten).
// - Schreibaktionen (POST/CSRF) brauchen Netz -> nur GET wird behandelt.
// ============================================================
self.addEventListener('fetch', function(event) {
    var req = event.request;
    if (req.method !== 'GET') return; // nur GET cachen

    var url;
    try { url = new URL(req.url); } catch (e) { return; }

    // Nur HTTP(S) behandeln. chrome-extension://, data:, blob: etc. NICHT abfangen
    // (cache.put wirft sonst "scheme unsupported" und respondWith bricht ab).
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return;

    var path = url.pathname;
    var sameOrigin = (url.origin === self.location.origin);

    // Logout -> geschuetzte Seiten aus dem Cache werfen.
    if (sameOrigin && /login\.php$/.test(path) && url.search.indexOf('logout') !== -1) {
        event.respondWith(caches.delete(RUNTIME_CACHE).then(function() { return fetch(req); }).catch(function() { return fetch(req); }));
        return;
    }

    // Dynamische/sensible Endpoints: kein Caching (Standard-Netzwerkverhalten).
    if (/\/(api|cron)\//.test(path) || /(user_logout|login|check_session|restore_session)/.test(path)) {
        return;
    }

    // Statische Assets (auch von CDN): cache-first.
    if (/\.(css|js|png|jpe?g|svg|gif|webp|ico|woff2?|ttf)$/i.test(path)) {
        event.respondWith(cacheFirst(req));
        return;
    }

    // Same-origin Seiten/Navigationen: network-first mit Cache-Fallback.
    if (sameOrigin && (req.mode === 'navigate' || req.destination === 'document')) {
        event.respondWith(networkFirst(req));
        return;
    }
    // sonst: Standardverhalten (kein respondWith)
});

function cacheFirst(req) {
    return caches.open(RUNTIME_CACHE).then(function(cache) {
        return cache.match(req).then(function(hit) {
            if (hit) {
                // Treffer sofort liefern, im Hintergrund aktualisieren (Fehler ignorieren).
                fetch(req).then(function(resp) {
                    if (resp && (resp.ok || resp.type === 'opaque')) {
                        try { cache.put(req, resp.clone()); } catch (e) {}
                    }
                }).catch(function() {});
                return hit;
            }
            // Kein Cache-Treffer: Netz versuchen, bei Erfolg cachen, bei Fehler
            // IMMER eine gueltige Response zurueckgeben (sonst "Failed to convert to Response").
            return fetch(req).then(function(resp) {
                if (resp && (resp.ok || resp.type === 'opaque')) {
                    try { cache.put(req, resp.clone()); } catch (e) {}
                }
                return resp;
            }).catch(function() {
                return new Response('', { status: 504, statusText: 'Offline' });
            });
        });
    });
}

function networkFirst(req) {
    return caches.open(RUNTIME_CACHE).then(function(cache) {
        return fetch(req).then(function(resp) {
            if (resp && resp.ok) cache.put(req, resp.clone()); // nur echte 200 cachen (keine Redirects/Login)
            return resp;
        }).catch(function() {
            return cache.match(req).then(function(hit) { return hit || offlineFallback(); });
        });
    });
}

function offlineFallback() {
    return new Response(
        '<!doctype html><meta charset="utf-8">' +
        '<meta name="viewport" content="width=device-width,initial-scale=1">' +
        '<title>Offline</title>' +
        '<div style="font-family:system-ui,-apple-system,sans-serif;max-width:420px;margin:18vh auto;padding:0 1.5rem;text-align:center;color:#334">' +
        '<div style="font-size:3rem">📡</div>' +
        '<h1 style="font-size:1.2rem;margin:.5rem 0">Keine Verbindung</h1>' +
        '<p style="color:#667">Diese Seite ist offline nicht verfügbar. Sobald du wieder online bist, lädt sie normal.</p>' +
        '<p><a href="javascript:location.reload()" style="color:#3b5998;font-weight:600">Erneut versuchen</a></p></div>',
        { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    );
}
