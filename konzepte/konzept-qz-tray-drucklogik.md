# Konzept: QZ Tray Drucklogik

> Anleitung zum Einbinden von QZ Tray Direktdruck auf einer beliebigen Seite.

---

## 1. Architektur-Ueberblick

```
┌─────────────────────────────────────────────────────────────┐
│  Browser (Seite)                                            │
│                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌───────────────┐  │
│  │ Druck-Button │───>│ PrintManager │───>│ QZ Tray (WS)  │  │
│  └──────────────┘    │  (JS-Klasse) │    │ localhost:8182 │  │
│                      └──────┬───────┘    └───────┬────────┘  │
│                             │                    │           │
│                      ┌──────▼───────┐    ┌───────▼────────┐  │
│                      │ sign_api.php │    │ Systemdrucker  │  │
│                      │ (Signierung) │    └────────────────┘  │
│                      └──────────────┘                        │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Server-APIs                                          │   │
│  │  profiles_api.php  — Profil laden (Drucker + Config) │   │
│  │  print_job_api.php — Druckauftrag loggen             │   │
│  │  sign_api.php      — Request signieren (SHA-512)     │   │
│  │  printers_api.php  — Drucker CRUD                    │   │
│  │  print_log_api.php — Druckprotokoll lesen            │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

**Kernprinzip:** Der Browser holt ein Druckprofil (Drucker + Optionen) aus der DB, generiert oder fetcht ein PDF, und sendet es via `PrintManager` ueber QZ Tray an den lokalen Drucker — ohne Druck-Dialog.

---

## 2. Voraussetzungen

### 2.1 JS-Libraries einbinden

In der PHP-Seite (`$extraScripts`):

```php
$extraScripts = [
    'js/lib/rsvp.min.js',       // Promise-Polyfill fuer QZ Tray
    'js/lib/sha-256.min.js',    // SHA-256 fuer QZ Tray Hashing
    'js/lib/qz-tray.js',       // QZ Tray Client-Library
    'js/print-manager.js',      // Eigener Wrapper (PrintManager-Klasse)
];
```

### 2.2 Zertifikate

- **Oeffentlich:** `certs/digital-certificate.txt` (wird vom Browser geladen)
- **Privat:** `certs/private-key.pem` (wird nur serverseitig von `sign_api.php` gelesen)
- Pfad konfigurierbar via `settings`-Tabelle (`cert_path`) oder automatische Suche

### 2.3 DB-Tabellen

Bereits vorhanden: `printers`, `print_profiles`, `print_jobs` — keine neuen Tabellen noetig.

---

## 3. Drucksteuerung-Seite (drucksteuerung.php)

Die zentrale Verwaltungsseite fuer alle Druckprofile. Hier konfiguriert der Benutzer **pro Dokumenttyp**:

| Feld | Beschreibung |
|------|-------------|
| `doc_type` | Eindeutiger Schluessel (z.B. `kranzkarte`, `rangliste`) |
| `anzeigename` | Lesbarer Name fuer die UI |
| `printer_id` | Zugewiesener Drucker (aus `printers`-Tabelle) |
| `print_mode` | `pixel` (PDF/HTML) oder `raw` (ZPL/ESC) |
| `copies` | Anzahl Kopien |
| `paper_size` | Papierformat (A4, A5, A6, Custom) |
| `orientation` | `portrait` / `landscape` |
| `color_mode` | `color` / `grayscale` / `blackwhite` |
| `duplex` | Beidseitig ja/nein |
| `optionen` | JSON fuer seitenspezifische Extras (z.B. `{"modus": "etikett_11209"}`) |

### 3.1 Sektionen definieren

In `app-drucksteuerung.js` werden die Dokumenttypen in Sektionen gruppiert:

```javascript
const DOC_TYPE_SECTIONS = [
    {
        title: 'Meine neue Seite',
        types: [
            { key: 'mein_dokument',   label: 'Mein Dokument',   defaults: { paper_size: 'A4', orientation: 'portrait' } },
            { key: 'mein_etikett',    label: 'Mein Etikett',    defaults: { paper_size: 'Custom' } },
        ]
    },
];
```

Die Drucksteuerung-Seite rendert automatisch alle hier definierten Typen als Profil-Matrix.

---

## 4. Schritt-fuer-Schritt: Druck auf einer neuen Seite

### Schritt 1: PrintManager initialisieren

```javascript
Object.assign(App, {
    _pm: null,           // PrintManager-Instanz
    _printConfig: null,  // Geladenes Profil

    async initMeineSeite() {
        // ... restliche Seiten-Init ...
        this._initPrint();
    },

    async _initPrint() {
        if (typeof PrintManager === 'undefined' || typeof qz === 'undefined') return;

        this._pm = new PrintManager();
        this._pm.onStatusChange = (connected) => this._updateQzBadge(connected);

        try {
            await this._pm.connect();
        } catch (err) {
            console.warn('QZ Tray nicht verfuegbar:', err.message);
            this._pm = null;
            return;
        }

        // Profil laden
        await this._loadPrintConfig();
    },
});
```

### Schritt 2: Druckprofil aus DB laden

```javascript
async _loadPrintConfig() {
    try {
        const res = await $.getJSON('pages/drucksteuerung/profiles_api.php', {
            doc_type: 'mein_dokument'  // Dokumenttyp-Schluessel
        });
        if (res.success && res.data.length > 0) {
            this._printConfig = res.data[0];
        }
    } catch (err) {
        console.error('Druckprofil laden fehlgeschlagen:', err);
    }
},
```

**Mehrere Dokumenttypen laden:**

```javascript
async _loadPrintConfigs() {
    const types = ['mein_dokument', 'mein_etikett'];
    this._printConfigs = {};

    for (const t of types) {
        try {
            const res = await $.getJSON('pages/drucksteuerung/profiles_api.php', { doc_type: t });
            if (res.success && res.data.length > 0) {
                this._printConfigs[t] = res.data[0];
            }
        } catch (e) { /* ignore */ }
    }
},
```

### Schritt 3: PDF serverseitig generieren

In `pages/meineseite/api.php`:

```php
if ($action === 'pdf_mein_dokument') {
    require_once __DIR__ . '/../../shared/vendor/autoload.php';

    // WICHTIG: Format im Konstruktor, nicht in AddPage()
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Mein Dokument', 0, 1);
    // ... Inhalt ...

    // WICHTIG: 'D' fuer Download, nicht 'I' (kein Browser-Tab)
    $pdf->Output('mein_dokument.pdf', 'D');
    exit;
}
```

### Schritt 4: Drucken ausfuehren

```javascript
async _druckeMeinDokument(id) {
    const profile = this._printConfig;
    if (!profile || !this._pm?.getStatus()) {
        ewsError('Drucker nicht konfiguriert oder QZ Tray nicht verbunden.');
        return;
    }

    try {
        // 1) PDF vom Server holen
        const response = await fetch(`pages/meineseite/api.php?action=pdf_mein_dokument&id=${id}`);
        if (!response.ok) throw new Error('PDF konnte nicht generiert werden');
        const blob = await response.blob();

        // 2) In Base64 umwandeln
        const base64 = await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result.split(',')[1]);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });

        // 3) An QZ Tray senden
        await this._pm.printPixel(
            profile.printer_name,
            [{ type: 'pixel', format: 'pdf', flavor: 'base64', data: base64 }],
            {
                copies:      profile.copies || 1,
                orientation: profile.orientation || 'portrait',
                colorType:   profile.color_mode || 'blackwhite',
                duplex:      !!profile.duplex,
                jobName:     `MeinDokument_${id}`,
                // WICHTIG bei Etiketten/Spezialformaten:
                // size: { width: 62, height: 29 },
                // units: 'mm',
                // margins: { top: 0, right: 0, bottom: 0, left: 0 },
            }
        );

        // 4) Erfolg loggen
        await this._pm.logJob('mein_dokument', profile.printer_name, `Dokument ${id}`, 'erfolgreich');
        ewsToast('Druckauftrag gesendet', 'success');

    } catch (err) {
        console.error('Druckfehler:', err);
        await this._pm.logJob('mein_dokument', profile.printer_name, `Dokument ${id}`, 'fehler', 1, err.message);
        ewsError('Druckfehler: ' + err.message);
    }
},
```

### Schritt 5: Druck-Button in der UI

```html
<button class="btn btn-sm btn-outline-primary" onclick="App._druckeMeinDokument(123)">
    <i class="bi bi-printer"></i> Drucken
</button>
```

---

## 5. Variante: HTML-Direktdruck (ohne PDF)

Fuer einfache Etiketten oder Kranzkarten kann statt PDF auch HTML direkt gedruckt werden:

```javascript
async _druckeEtikett(text) {
    const profile = this._printConfigs['mein_etikett'];
    if (!profile || !this._pm?.getStatus()) return;

    const html = `<div style="width:62mm;height:29mm;padding:2mm 3mm;font-family:Arial,sans-serif;font-size:10pt;box-sizing:border-box">
        <div style="font-weight:bold">${App.esc(text)}</div>
    </div>`;

    await this._pm.printPixel(
        profile.printer_name,
        [{ type: 'html', format: 'plain', data: html }],
        {
            size: { width: 62, height: 29 },
            units: 'mm',
            margins: { top: 0, right: 0, bottom: 0, left: 0 },
            copies: profile.copies || 1,
            jobName: 'Etikett',
        }
    );
},
```

> **Achtung:** HTML-Druck ist unzuverlaessig bei komplexen Layouts. Fuer alles ausser einfachste Etiketten → PDF verwenden.

---

## 6. QZ-Status-Badge (optional)

Visuelle Rueckmeldung ob QZ Tray verbunden ist:

```javascript
_updateQzBadge(connected) {
    const badge = document.getElementById('qz-badge');
    if (!badge) return;
    badge.className = connected ? 'badge bg-success' : 'badge bg-danger';
    badge.textContent = connected ? 'QZ verbunden' : 'QZ getrennt';
},
```

```html
<span id="qz-badge" class="badge bg-secondary">QZ Tray</span>
```

---

## 7. Wichtige Regeln (Lessons Learned)

| Regel | Warum |
|-------|-------|
| PDF immer `format: 'pdf'` | HTML/Image-Druck ist unzuverlaessig |
| Bei PDF-Druck IMMER `size`, `units`, `margins` mitgeben | Sonst nimmt QZ Tray Systemstandards |
| TCPDF: Format im **Konstruktor** setzen | `AddPage('', 'A5')` wird oft ignoriert |
| PDF als **Download** (`'D'`), nie Browser-Tab (`'I'`) | Konsistentes Verhalten |
| `App.esc()` fuer alle User-Daten in HTML-Druck | XSS-Schutz |
| `profile.printer_name` pruefen vor Druck | Sonst geht der Job an den Standarddrucker |
| Job immer loggen (Erfolg + Fehler) | Nachvollziehbarkeit in Druckprotokoll |

---

## 8. Dateien-Referenz

| Datei | Zweck |
|-------|-------|
| `js/print-manager.js` | PrintManager-Klasse (connect, printPixel, printRaw, logJob) |
| `js/lib/qz-tray.js` | QZ Tray Client-Library |
| `js/lib/rsvp.min.js` | Promise-Polyfill |
| `js/lib/sha-256.min.js` | SHA-256 fuer QZ Tray |
| `certs/digital-certificate.txt` | Oeffentliches Zertifikat |
| `certs/private-key.pem` | Privater Schluessel (nur Server) |
| `pages/drucksteuerung.php` | UI: Profil-Matrix, Drucker-Verwaltung |
| `js/app-drucksteuerung.js` | JS: Drucksteuerung-Logik, DOC_TYPE_SECTIONS |
| `pages/drucksteuerung/sign_api.php` | Signierung (SHA-512 + RSA) |
| `pages/drucksteuerung/profiles_api.php` | CRUD fuer Druckprofile |
| `pages/drucksteuerung/printers_api.php` | CRUD fuer Drucker |
| `pages/drucksteuerung/print_job_api.php` | Druckauftraege loggen/updaten |
| `pages/drucksteuerung/print_log_api.php` | Druckprotokoll lesen |

---

## 9. Checkliste: Neuen Dokumenttyp hinzufuegen

1. **`app-drucksteuerung.js`** — Dokumenttyp in `DOC_TYPE_SECTIONS` eintragen (Key + Label + Defaults)
2. **Server-API** — PDF-Endpoint in `pages/meineseite/api.php` erstellen
3. **Seiten-JS** — PrintManager init, Profil laden, Druckfunktion, Button
4. **`$extraScripts`** — QZ Tray Libraries in der PHP-Seite einbinden
5. **Testen** — Profil in Drucksteuerung konfigurieren, Testdruck ausfuehren

---

## 10. Mehrplatz / Machine-ID

Bei lokaler Installation (`is_local_installation = 1`) werden Drucker und Profile **pro Rechner** getrennt via `machine_id` (UUID in `localStorage`). Der Header `X-Machine-Id` wird automatisch an alle Drucksteuerungs-API-Calls angehaengt (jQuery + fetch Interceptor in `print-manager.js`).

Das bedeutet: Jeder Arbeitsplatz kann eigene Drucker und Profile haben, ohne die anderen zu beeinflussen.
