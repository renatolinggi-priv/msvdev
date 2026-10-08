/**
 * MSV Toast/Benachrichtigungssystem - Zentrale Funktionen
 * Nutzt SweetAlert2 für einheitliche Benachrichtigungen.
 *
 * Dialoge (msvConfirm, msvConfirmDelete, msvError, msvSwal.fire) laufen alle über das
 * kompakte Design «.msv-swal»: schmales Popup, kleines Icon, Bootstrap-Buttons (btn-sm)
 * statt der grossen SweetAlert-Buttons, Abbrechen links / Aktion rechts. Das CSS dazu
 * wird einmalig aus dieser Datei eingehängt, damit es überall gilt, wo msv-toast.js
 * geladen ist (Admin-Header, Portal-Header, Login/Registrierung) – ohne dass jede
 * Einstiegsseite ein weiteres Stylesheet einbinden muss.
 */

// ---------------------------------------------------------------------------
// Kompaktes Dialog-CSS (einmalig)
// ---------------------------------------------------------------------------
(function () {
    if (typeof document === 'undefined' || document.getElementById('msv-swal-css')) return;
    var css = [
        /* Popup: schmal, wenig Padding, Grundschrift 0.9rem */
        '.msv-swal.swal2-popup{width:22rem;max-width:calc(100vw - 2rem);padding:1rem 1rem 1rem;border-radius:.75rem;font-size:.9rem;color:#212529}',
        /* Icon: SweetAlert zeichnet alle Icons in em → über font-size skalieren, dann stimmen auch Häkchen/Kreuz */
        '.msv-swal .swal2-icon{font-size:.55em;margin:.25rem auto .75rem;border-width:.28em}',
        '.msv-swal .swal2-title{font-size:1.05rem;font-weight:600;line-height:1.3;padding:0 .25rem;color:#212529}',
        '.msv-swal .swal2-html-container{font-size:.9rem;line-height:1.45;margin:.5rem .25rem 0;color:#495057}',
        '.msv-swal .swal2-html-container strong{color:#212529}',
        /* Eingaben (input/select/textarea/checkbox/radio) auf Formular-Grösse der App */
        '.msv-swal .swal2-input,.msv-swal .swal2-select,.msv-swal .swal2-textarea{width:100%;height:auto;margin:.75rem 0 0;padding:.4rem .6rem;font-size:.9rem;border:1px solid #ced4da;border-radius:.375rem;box-shadow:none}',
        '.msv-swal .swal2-input:focus,.msv-swal .swal2-textarea:focus,.msv-swal .swal2-select:focus{border-color:#86b7fe;box-shadow:0 0 0 .2rem rgba(13,110,253,.25)}',
        '.msv-swal .swal2-checkbox,.msv-swal .swal2-radio{margin:.75rem 0 0;font-size:.85rem;color:#495057}',
        '.msv-swal .swal2-checkbox input,.msv-swal .swal2-radio input{margin-right:.4rem}',
        '.msv-swal .swal2-validation-message{margin:.6rem 0 0;padding:.4rem .6rem;font-size:.85rem}',
        /* Buttons: Bootstrap-Klassen, zentriert, Abstand klein */
        '.msv-swal .swal2-actions{margin:1rem 0 0;gap:.5rem;flex-wrap:nowrap}',
        '.msv-swal .swal2-actions .btn{min-width:6.5rem}',
        '.msv-swal .swal2-actions .btn:focus{box-shadow:0 0 0 .2rem rgba(13,110,253,.25)}',
        '.msv-swal .swal2-actions .btn-danger:focus{box-shadow:0 0 0 .2rem rgba(220,53,69,.25)}',
        '.msv-swal .swal2-actions .btn-outline-secondary:focus{box-shadow:0 0 0 .2rem rgba(108,117,125,.25)}',
        '.msv-swal .swal2-loader{width:1.5rem;height:1.5rem;border-width:.2rem}',
        /* Handy: volle Breite minus Rand, Buttons nebeneinander gleich breit */
        '@media (max-width:575.98px){.msv-swal.swal2-popup{width:calc(100vw - 1.5rem);padding:.9rem .85rem}.msv-swal .swal2-actions .btn{flex:1 1 0;min-width:0}}'
    ].join('\n');
    var s = document.createElement('style');
    s.id = 'msv-swal-css';
    s.textContent = css;
    (document.head || document.documentElement).appendChild(s);
})();

// Bootstrap-Klassen für die Dialog-Buttons. SweetAlert2 ersetzt customClass beim
// fire() komplett (kein Deep-Merge) → für einen anderen Bestätigungs-Button immer
// dieses Objekt vollständig übergeben: customClass: msvSwalButtons('btn-danger')
function msvSwalButtons(confirmClass) {
    return {
        popup: 'msv-swal',
        confirmButton: 'btn btn-sm ' + (confirmClass || 'btn-primary'),
        cancelButton: 'btn btn-sm btn-outline-secondary',
        denyButton: 'btn btn-sm btn-outline-danger'
    };
}

// Basis für alle Dialoge. Für Sonderdialoge (Texteingabe, Checkbox, Radio-Liste)
// direkt nutzen: msvSwal.fire({ title, html, input: 'text', showCancelButton: true, ... })
// Lazy, damit msvEsc/msvXhrMessage auch funktionieren, wenn SweetAlert2 nicht geladen ist.
var msvSwal = {
    fire: function (opts) {
        return Swal.mixin({
            buttonsStyling: false,
            reverseButtons: true,
            customClass: msvSwalButtons(),
            confirmButtonText: 'OK',
            cancelButtonText: 'Abbrechen'
        }).fire(opts || {});
    }
};

// ---------------------------------------------------------------------------
// Toasts
// ---------------------------------------------------------------------------

// Zentrale Toast-Funktion (nutzt SweetAlert2 Toast-Mode)
function msvToast(message, type = 'success') {
    // Bootstrap 'danger' auf SweetAlert2 'error' mappen
    if (type === 'danger') type = 'error';

    // Responsive: Mobile kompakter und unter Navbar, Desktop wie gewohnt
    const isMobile = window.innerWidth < 992;

    // Fehler und Warnungen bleiben länger stehen und pausieren unter der Maus,
    // damit man sie fertig lesen kann.
    const dauer = type === 'error' ? 7000 : (type === 'warning' ? 5000 : 3000);

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: dauer,
        timerProgressBar: true,
        customClass: {
            popup: isMobile ? 'swal2-toast-mobile' : ''
        },
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
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

// Zentrale Erfolgs-Anzeige
function msvSuccess(message) {
    msvToast(message, 'success');
}

// ---------------------------------------------------------------------------
// Dialoge (kompakt)
// ---------------------------------------------------------------------------

// Zentrale Lösch-Bestätigung mit spezifischem Namen (roter Button, Fokus auf Abbrechen).
// opts optional: { html: eigener Text statt Standardsatz, title, confirmText }
function msvConfirmDelete(itemName, opts) {
    opts = opts || {};
    return msvSwal.fire({
        title: opts.title || 'Löschen bestätigen',
        html: opts.html || ('Möchtest du <strong>' + itemName + '</strong> wirklich löschen?'),
        icon: 'warning',
        showCancelButton: true,
        focusCancel: true,
        customClass: msvSwalButtons('btn-danger'),
        confirmButtonText: opts.confirmText || 'Ja, löschen',
        cancelButtonText: 'Abbrechen'
    });
}

// «Alle Resultate eines Jahres löschen» – einheitlich für JM, Endschiessen, Heim, Kanti.
// Fragt erst die Anzahl ab, nennt Jahr und Anzahl im Dialog; der Server sichert die
// Datenbank vor dem Löschen (inc/jahr_loeschen.inc.php).
// opts: { url, year, was: 'Heimresultate', done: function(resp) }
async function msvJahrLoeschen(opts) {
    const tokenEl = document.querySelector('input[name="csrf_token"]');
    const basis = { year: opts.year, jahr: opts.year, csrf_token: tokenEl ? tokenEl.value : '' };
    const senden = async function (extra) {
        const res = await fetch(opts.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(Object.assign({}, basis, extra || {}))
        });
        let data = null;
        try { data = await res.json(); } catch (e) { data = null; }
        if (!res.ok || !data || data.success !== true) {
            const st = res.status;
            throw new Error((data && data.message) ||
                (st === 401 ? 'Sitzung abgelaufen – bitte neu anmelden' :
                 st === 403 ? 'Keine Berechtigung' : 'Serverfehler (' + st + ')'));
        }
        return data;
    };

    let anzahl;
    try {
        anzahl = (await senden({ nur_zaehlen: 1 })).anzahl;
    } catch (e) {
        msvToast(e.message, 'error');
        return;
    }
    if (!anzahl) {
        msvToast('Für ' + opts.year + ' sind keine ' + opts.was + ' erfasst.', 'info');
        return;
    }

    const r = await msvConfirmDelete('', {
        title: opts.was + ' ' + opts.year + ' löschen?',
        html: '<strong>' + anzahl + ' ' + msvEsc(opts.was) + '</strong> des Jahres <strong>'
            + msvEsc(String(opts.year)) + '</strong> werden gelöscht.'
            + '<div class="small text-muted mt-2">Vorher wird automatisch eine Sicherung der ganzen '
            + 'Datenbank erstellt. Sie steht danach auf der Seite «Backup &amp; Restore».</div>',
        confirmText: 'Ja, ' + anzahl + ' löschen'
    });
    if (!r.isConfirmed) return;

    msvSwal.fire({
        title: 'Sichern und löschen …',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function () { Swal.showLoading(); }
    });
    try {
        const resp = await senden();
        Swal.close();
        msvToast(resp.message || (opts.was + ' gelöscht'), 'success');
        if (typeof opts.done === 'function') opts.done(resp);
    } catch (e) {
        Swal.close();
        msvError(e.message);
    }
}

// Zentrale Fehler-Anzeige (Dialog, muss bestätigt werden – für Toasts msvToast(msg,'error'))
function msvError(message) {
    return msvSwal.fire({ icon: 'error', title: 'Fehler', text: message, confirmButtonText: 'OK' });
}

// Generische Bestätigung (für beliebige Aktionen). Rückgabe = SweetAlert-Promise,
// IMMER result.isConfirmed prüfen (das Objekt selbst ist immer truthy).
function msvConfirm(message, title, confirmText, cancelText) {
    return msvSwal.fire({
        title: title || 'Bestätigen',
        html: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: confirmText || 'Ja, fortfahren',
        cancelButtonText: cancelText || 'Abbrechen'
    });
}

// ---------------------------------------------------------------------------
// Helfer für AJAX
// ---------------------------------------------------------------------------

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

// Gemeinsamer JSON-GET fürs Laden von Daten (jQuery). ok(r) läuft bei r.success – oder
// wenn die Antwort gar kein success-Feld hat (reine Daten-Endpunkte). Bei success:false,
// Netz-/Serverfehler oder ungültigem JSON erscheint ein Fehler-Toast (r.message bzw.
// opts.failMsg, Standard «Daten konnten nicht geladen werden»); opts.fail(xhr) optional,
// z.B. um einen Spinner/Skeleton wieder wegzunehmen. Gibt das jqXHR zurück.
function msvGet(url, data, ok, opts) {
    if (typeof data === 'function') { opts = ok; ok = data; data = null; }
    opts = opts || {};
    var failMsg = opts.failMsg || 'Daten konnten nicht geladen werden';
    return jQuery.getJSON(url, data || undefined)
        .done(function (r) {
            if (r && (r.success || typeof r.success === 'undefined')) { if (typeof ok === 'function') ok(r); }
            else {
                if (typeof opts.fail === 'function') opts.fail(null, r);
                msvToast((r && (r.message || r.error)) || failMsg, 'error');
            }
        })
        .fail(function (xhr) {
            if (typeof opts.fail === 'function') opts.fail(xhr, null);
            msvToast(msvXhrMessage(xhr, failMsg), 'error');
        });
}

// Erzeugte Datei (PDF/Excel/Word) direkt herunterladen – statt einen Download-Link stehen zu lassen.
// filename optional, sonst der Dateiname aus der URL. Rückmeldung an den Nutzer per msvToast.
function msvDownload(url, filename) {
    var a = document.createElement('a');
    a.href = url;
    a.download = filename || String(url).split('?')[0].split('/').pop() || 'download';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { document.body.removeChild(a); }, 0);
}
