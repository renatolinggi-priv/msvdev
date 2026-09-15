/**
 * MSV Toast/Benachrichtigungssystem - Zentrale Funktionen
 * Nutzt SweetAlert2 für einheitliche Benachrichtigungen
 */

// Zentrale Toast-Funktion (nutzt SweetAlert2 Toast-Mode)
function msvToast(message, type = 'success') {
    // Bootstrap 'danger' auf SweetAlert2 'error' mappen
    if (type === 'danger') type = 'error';

    // Responsive: Mobile kompakter und unter Navbar, Desktop wie gewohnt
    const isMobile = window.innerWidth < 992;

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        customClass: {
            popup: isMobile ? 'swal2-toast-mobile' : ''
        },
        didOpen: (toast) => {
            // Mobile: Kompakteres Styling unter dem Hamburger-Button
            if (isMobile) {
                toast.style.top = '60px'; // Unter der Navbar/Hamburger
                toast.style.fontSize = '13px';
                toast.style.padding = '6px 10px';
                toast.style.minWidth = 'auto';
                toast.style.maxWidth = '85%';
                toast.style.right = '10px';
            }
        }
    });
    Toast.fire({ icon: type, title: message });
}

// Zentrale Lösch-Bestätigung mit spezifischem Namen
function msvConfirmDelete(itemName) {
    return Swal.fire({
        title: 'Löschen bestätigen',
        html: `Möchtest du <strong>${itemName}</strong> wirklich löschen?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ja, löschen',
        cancelButtonText: 'Abbrechen'
    });
}

// Zentrale Fehler-Anzeige
function msvError(message) {
    Swal.fire({ icon: 'error', title: 'Fehler', text: message });
}

// Zentrale Erfolgs-Anzeige
function msvSuccess(message) {
    msvToast(message, 'success');
}

// Generische Bestätigung (für beliebige Aktionen)
function msvConfirm(message, title = 'Bestätigen', confirmText = 'Ja, fortfahren') {
    return Swal.fire({
        title: title,
        html: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: confirmText,
        cancelButtonText: 'Abbrechen'
    });
}

// HTML-Escaping für Text, der per innerHTML/jQuery.html() eingesetzt wird.
// null/undefined -> leerer String. (vorher in mehreren Seiten lokal als esc()/escapeHtml())
function msvEsc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

// Lesbare Meldung aus einem fehlgeschlagenen jQuery-XHR: JSON-message des Servers,
// sonst Standardtext je Status (0/401/403/413), sonst fallback.
function msvXhrMessage(xhr, fallback) {
    var r = xhr && xhr.responseJSON;
    if (!r && xhr && xhr.responseText) {
        try { r = JSON.parse(xhr.responseText); } catch (e) { r = null; }
    }
    if (r && (r.message || r.error)) return r.message || r.error;
    var st = xhr ? xhr.status : -1;
    if (st === 0)   return 'Keine Verbindung zum Server';
    if (st === 401) return 'Sitzung abgelaufen – bitte neu anmelden';
    if (st === 403) return 'Keine Berechtigung';
    if (st === 413) return 'Anfrage zu gross für den Server';
    return fallback || 'Serverfehler' + (st > 0 ? ' (' + st + ')' : '');
}

// Gemeinsamer JSON-POST für Admin-Endpunkte (jQuery). Hängt das CSRF-Token an
// (opts.csrf, sonst <meta name="csrf-token"> oder das erste [name=csrf_token]),
// ruft ok(r) bei r.success, zeigt sonst einen Fehler-Toast (r.message bzw. opts.failMsg).
// Gibt das jqXHR zurück, damit .always()/.then() weiter angehängt werden kann.
function msvPost(url, data, ok, opts) {
    opts = opts || {};
    var csrf = opts.csrf
        || (document.querySelector('meta[name="csrf-token"]') || {}).content
        || (document.querySelector('[name="csrf_token"]') || {}).value
        || '';
    var payload = Object.assign({ csrf_token: csrf }, data || {});
    return jQuery.post(url, payload, null, 'json')
        .done(function (r) {
            if (r && r.success) { if (typeof ok === 'function') ok(r); }
            else msvToast((r && (r.message || r.error)) || opts.failMsg || 'Aktion fehlgeschlagen', 'error');
        })
        .fail(function (xhr) {
            if (typeof opts.fail === 'function') opts.fail(xhr);
            msvToast(msvXhrMessage(xhr, opts.failMsg), 'error');
        });
}
