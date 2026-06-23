# Konzept: PDF-Dateigroesse bei QZ Tray Direktdruck optimieren

> Erfahrungsbericht aus einem Produktivsystem (PHP/TCPDF + jQuery + QZ Tray).
> Uebertragbar auf beliebige Stacks mit QZ Tray.

---

## 1. Problemstellung

Beim Direktdruck ueber QZ Tray werden PDFs via WebSocket als **Base64-String** an den lokalen QZ-Tray-Dienst gesendet. Der Datenfluss:

```
Backend generiert PDF
    → Frontend holt PDF via fetch()
    → Blob → FileReader → Base64
    → WebSocket an QZ Tray (localhost:8181)
    → QZ Tray sendet an Drucker
```

Base64 vergroessert die Daten um ca. **33%**. Bei einem 2 MB PDF werden also ~2.7 MB ueber den WebSocket uebertragen. Bei Massendruck (z.B. 50 Briefe) summiert sich das schnell.

**Ziel:** PDF-Dateien so klein wie moeglich halten, damit der Druck schnell und zuverlaessig laeuft.

---

## 2. Optimierungen auf Backend-Seite (PDF-Generierung)

### 2.1 Bilder: JPEG statt PNG

**Groesster Einzeleffekt (30–50% kleineres PDF).**

PNG-Logos mit Alpha-Kanal (Transparenz) werden von TCPDF unkomprimiert eingebettet. Ein 150 KB PNG kann im PDF 500+ KB belegen.

**Loesung:** Separates JPEG-Logo fuer Druckzwecke bereitstellen:

```php
// Optimiertes JPEG-Logo bevorzugen (kleiner, kein Alpha → viel kleineres PDF)
$dir = dirname($logoPath);
$jpgPath = $dir . '/Logo_print.jpg';
$this->logoPath = file_exists($jpgPath) ? $jpgPath : $logoPath;
```

**Empfehlung:**
- JPEG mit Qualitaet 85–90% fuer Logos/Grafiken
- PNG nur wenn Transparenz zwingend noetig
- Bilder auf Zielgroesse zuschneiden (nicht 3000px einbetten und per PDF skalieren)

### 2.2 Systemfonts statt eingebettete Fonts

Eingebettete Fonts koennen **200–500 KB pro Schriftschnitt** kosten.

**Loesung:** Nur TCPDF-Standardfonts verwenden:

```php
$this->SetFont('helvetica', '', 8);   // Kein Font-Embedding
$this->SetFont('helvetica', 'B', 10); // Bold = gleiche Font-Familie
```

Standardfonts in TCPDF (kein Embedding): `helvetica`, `times`, `courier`, `symbol`, `zapfdingbats`.

**Wenn Custom-Fonts noetig:** Nur die benoetigten Subsets einbetten (TCPDF `makefont` mit Subsetting).

### 2.3 Kompakte Tabellenlayouts

Kleine Schriftgroessen und enge Zellenabstaende sparen indirekt Platz (weniger Seiten = kleineres PDF):

```php
$this->SetFont('helvetica', '', 7);    // 7pt fuer Tabellendaten
$this->SetFont('helvetica', 'B', 8);   // 8pt fuer Ueberschriften
$this->SetCellPadding(1);              // Minimales Padding
```

### 2.4 JPEG-Kompressionsqualitaet setzen

TCPDF konvertiert nicht-JPEG-Bilder intern. Die Qualitaet laesst sich steuern:

```php
$pdf->setJPEGQuality(85); // Default ist 75, 85 ist guter Kompromiss
```

### 2.5 Keine unnoetige Metadaten

```php
$pdf->setPrintHeader(false);  // Kein TCPDF-Standard-Header
$pdf->setPrintFooter(false);  // Kein TCPDF-Standard-Footer
// Eigene, schlanke Header/Footer implementieren
```

---

## 3. Optimierungen auf Frontend-Seite (QZ Tray Config)

### 3.1 rasterize: false (Kritisch!)

**Zweitwichtigste Optimierung nach den Bildern.**

QZ Tray kann PDFs vor dem Drucken in Pixelbilder umwandeln ("rasterisieren"). Das erzeugt riesige Spool-Dateien und ist langsam.

```javascript
await pm.printPixel(printerName,
    [{ type: 'pdf', format: 'base64', data: base64 }],
    { rasterize: false }  // PDF nativ an Drucker senden
);
```

| Einstellung | Spool-Groesse (A4, Text) | Druckdauer |
|---|---|---|
| `rasterize: true` (Default) | 15–30 MB | 5–15 Sek. |
| `rasterize: false` | 50–200 KB | < 1 Sek. |

**Wann rasterize: true noetig:** Nur wenn der Drucker kein PostScript/PDF-Interpreter hat (seltene Thermodrucker).

### 3.2 Monochrome Farbmodus

Fuer Textdokumente reicht Schwarzweiss:

```javascript
await pm.printPixel(printerName, data, {
    colorType: 'blackwhite',  // Statt 'color'
    rasterize: false
});
```

### 3.3 Korrekte Papiergroesse und Raender

Falsche Groessen fuehren zu Skalierung durch den Druckertreiber, was die Qualitaet verschlechtert und Verarbeitungszeit kostet:

```javascript
await pm.printPixel(printerName, data, {
    size: { width: 210, height: 297 },  // A4 exakt
    units: 'mm',
    margins: { top: 0, right: 0, bottom: 0, left: 0 },
    scaleContent: true,
    rasterize: false
});
```

---

## 4. Etiketten/Labels: Canvas statt PDF

Fuer kleine Formate (Etiketten, Barcode-Labels) ist ein PDF-Overhead unnoetig. Besser: **Canvas direkt als PNG-Image drucken.**

```javascript
// DK-11201: 90mm x 29mm bei 150 DPI = 531 x 171 px
const canvas = document.createElement('canvas');
canvas.width = 531;
canvas.height = 171;
const ctx = canvas.getContext('2d');

// Weisser Hintergrund + Text/Barcode zeichnen
ctx.fillStyle = '#ffffff';
ctx.fillRect(0, 0, 531, 171);
ctx.fillStyle = '#000000';
ctx.font = 'bold 20px Arial';
ctx.fillText('Beschriftung', 24, 40);

// Direkt als Image drucken (kein PDF-Umweg)
const base64 = canvas.toDataURL('image/png').split(',')[1];
await pm.printPixel(printerName,
    [{ type: 'image', format: 'base64', data: base64 }],
    {
        size: { width: 90, height: 29 },
        units: 'mm',
        scaleContent: true
    }
);
```

**Vorteile:**
- ~5–15 KB statt 50–100 KB (PDF mit eingebettetem Bild)
- Kein Server-Roundtrip noetig
- 150 DPI reicht fuer Etikettendrucker voellig aus

**DPI-Berechnung:**
```
Pixel = mm × DPI / 25.4
Beispiel: 90mm × 150 / 25.4 = 531 px
```

---

## 5. Datenfluss-Architektur

### 5.1 PDF-Dokumente (A4/A5)

```
┌─────────────────────────────┐
│  Backend (PHP/TCPDF)        │
│  • JPEG-Logo                │
│  • Helvetica (kein Embed)   │
│  • Kompakte Tabellen        │
│  → Output: 50–200 KB PDF    │
└──────────┬──────────────────┘
           │ fetch()
           ▼
┌─────────────────────────────┐
│  Frontend (JavaScript)      │
│  • response.blob()          │
│  • FileReader → Base64      │
│  → ~70–270 KB Base64-String │
└──────────┬──────────────────┘
           │ WebSocket
           ▼
┌─────────────────────────────┐
│  QZ Tray                    │
│  • rasterize: false         │
│  • colorType: blackwhite    │
│  → Nativer PDF-Druck        │
└─────────────────────────────┘
```

### 5.2 Etiketten/Labels

```
┌─────────────────────────────┐
│  Frontend (Canvas API)      │
│  • 150 DPI fuer Zielformat  │
│  • canvas.toDataURL('png')  │
│  → 5–15 KB Base64-String    │
└──────────┬──────────────────┘
           │ WebSocket
           ▼
┌─────────────────────────────┐
│  QZ Tray                    │
│  • type: 'image'            │
│  • scaleContent: true       │
│  → Bild direkt gedruckt     │
└─────────────────────────────┘
```

---

## 6. PrintManager-Wrapper (JavaScript)

Zentraler Wrapper um die QZ Tray API, der alle Optimierungen buendelt:

```javascript
class PrintManager {
    async connect() { /* WebSocket + Zertifikat + Signierung */ }
    async disconnect() { /* Verbindung trennen */ }

    async printPixel(printerName, data, options = {}) {
        const config = qz.configs.create(printerName, {
            jobName:      options.jobName || null,
            copies:       options.copies || 1,
            orientation:  options.orientation || null,
            colorType:    options.colorType || null,
            duplex:       options.duplex || false,
            size:         options.size || null,
            units:        options.units || 'mm',
            margins:      options.margins || null,
            density:      options.density || null,
            rasterize:    options.rasterize ?? true,
            scaleContent: options.scaleContent ?? true,
        });
        return await qz.print(config, data);
    }

    async printRaw(printerName, commands) { /* ZPL, ESC/POS */ }
    async logJob(docType, printer, datei, status, copies) { /* DB-Logging */ }
}
```

**Wichtig:** Der Wrapper zentralisiert die Config-Optionen, damit nicht jeder Druckaufruf die Optimierungen einzeln setzen muss.

---

## 7. Haeufige Fehler und Learnings

### Format: Immer 'pdf' verwenden

QZ Tray unterstuetzt auch `type: 'html'` und `type: 'image'` fuer Dokumente. **Fuer PDF-Dateien immer `type: 'pdf'` verwenden** — HTML- und Image-Rendering ist unzuverlaessig (Seitenumbrueche, Schriftarten).

```javascript
// ✅ Korrekt
[{ type: 'pdf', format: 'base64', data: base64 }]

// ❌ Nicht fuer PDFs verwenden
[{ type: 'html', data: '<html>...' }]
```

### PDF-Groesse bei QZ Tray: size/units/margins explizit setzen

Ohne explizite Groesse rasterisiert QZ Tray das PDF und der Drucker skaliert es nochmals. Immer mitgeben:

```javascript
{
    size: { width: 210, height: 297 },
    units: 'mm',
    margins: { top: 0, right: 0, bottom: 0, left: 0 },
    rasterize: false
}
```

### TCPDF: Seitenformat im Konstruktor

Das Format gehoert in den TCPDF-Konstruktor, nicht in `AddPage()`:

```php
// ✅ Korrekt
parent::__construct('P', 'mm', 'A4', true, 'UTF-8');

// ❌ Fuehrt zu Problemen
$pdf->AddPage('P', 'A4'); // Format hier wird oft ignoriert
```

### Signierung fuer Silent Print

Ohne gueltige Signierung zeigt QZ Tray bei jedem Druckauftrag einen Bestaetigungsdialog. Fuer Produktivbetrieb:

1. QZ-Tray-Zertifikat erstellen (einmalig)
2. Private Key am Server, Public Cert am Client
3. Jeder Druckauftrag wird serverseitig signiert (SHA-512 + RSA)

---

## 8. Checkliste fuer neue Projekte

- [ ] **JPEG-Logos** bereitstellen (kein PNG mit Alpha)
- [ ] **Systemfonts** verwenden (Helvetica/Times/Courier)
- [ ] **rasterize: false** als Standard setzen
- [ ] **size/units/margins** bei jedem printPixel-Aufruf explizit angeben
- [ ] **Canvas-Rendering** fuer Etiketten/Labels statt PDF
- [ ] **PrintManager-Wrapper** als zentrale Klasse
- [ ] **Signierung** einrichten fuer Silent Print
- [ ] **Druckprofile** in DB speichern (Drucker + Optionen pro Dokumenttyp)
- [ ] **Job-Logging** fuer Fehleranalyse

---

## 9. Groessenvergleich (gemessene Werte)

| Dokument | Vor Optimierung | Nach Optimierung | Einsparung |
|---|---|---|---|
| Rangliste A4 (8 Seiten, Logo) | ~1.2 MB | ~180 KB | **85%** |
| Einladungsbrief A4 (1 Seite, Logo) | ~350 KB | ~80 KB | **77%** |
| Schiessplatzbericht (7 Seiten) | ~800 KB | ~150 KB | **81%** |
| Etikett DK-11201 (Canvas) | ~60 KB (PDF) | ~8 KB (PNG) | **87%** |

**Hauptfaktoren:** JPEG-Logo (~50% der Einsparung), Systemfonts (~30%), rasterize: false (Spool-Groesse, nicht PDF-Groesse).
