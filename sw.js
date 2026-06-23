// sw.js - Service Worker fuer das MSV Wilen Mitgliederportal
// Vorlage: benachrichtigungs-konzept.md (Abschnitt 2.6)
//
// Liegt im App-Root (neben manifest.json). Wird von den Portal-Seiten relativ
// registriert (../sw.js, scope ../) -> deployment-unabhaengig (Domain-Root ODER
// Unterordner). Icon-/Ziel-Pfade werden gegen registration.scope aufgeloest.
//
// Nur Push + Klick-Handling, KEIN Offline-Caching (bewusst minimal gehalten).

self.addEventListener('install', function() {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(self.clients.claim());
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
