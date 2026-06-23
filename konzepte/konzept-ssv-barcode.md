# Konzept: SSV Schützen-Barcode (Interleaved 2 of 5 mit Mod-97)

## Zweck

Auf den SSV-Lizenzkarten ist ein **Interleaved 2 of 5 (ITF)** Barcode aufgedruckt. Dieser kodiert die Mitgliedernummer des Schützen mit einer Mod-97-Prüfziffer. Im EWS-Projekt wird dieser Barcode auf Standblatt-Etiketten gedruckt und kann per Scanner eingelesen werden, um Schützen schnell zu identifizieren.

---

## Algorithmus: Lizenznummer → Barcode-Nummer

### Eingabe
- **6-stellige** SSV-Lizenznummer (z.B. `112097`) oder
- **8-stellige** Nummer (z.B. `10112097`, bereits mit Präfix)

### Schritte

1. **Normalisieren:** Bei 6-stelliger Eingabe → Präfix `10` voranstellen → 8-stellig
   - `112097` → `10112097`
2. **Mod-97-Prüfziffer berechnen:**
   - `Basis = 8-stellige Nummer × 100` (d.h. `"00"` anhängen)
   - `Rest = Basis mod 97`
   - `CRC = 97 - Rest` (2-stellig, ggf. führende Null)
3. **Ergebnis:** 8-stellig + 2-stellige CRC = **10-stellige Barcode-Nummer**

### Rechenbeispiele

| Eingabe  | 8-stellig  | ×100         | mod 97 | CRC | Barcode-Nr   |
|----------|------------|--------------|--------|-----|--------------|
| 112097   | 10112097   | 1011209700   | 26     | 71  | 1011209771   |
| 112100   | 10112100   | 1011210000   | 35     | 62  | 1011210062   |

### Wichtig
- Für die mod-Berechnung braucht man **Big-Integer-Arithmetik** (10-stellige Zahl), da normale 32-Bit-Integer überlaufen.
- PHP: `bcmod()`, JavaScript: `BigInt`

---

## Barcode-Typ: Interleaved 2 of 5 (ITF)

**NICHT** Code 128C oder Code 39 — der SSV verwendet explizit ITF.

### ITF-Encoding-Tabelle (Ziffern 0-9)

Jede Ziffer wird durch 5 Elemente kodiert (N = schmal, W = breit):

| Ziffer | Pattern |
|--------|---------|
| 0      | NNWWN   |
| 1      | WNNNW   |
| 2      | NWNNW   |
| 3      | WWNNN   |
| 4      | NNWNW   |
| 5      | WNWNN   |
| 6      | NWWNN   |
| 7      | NNNWW   |
| 8      | WNNWN   |
| 9      | NWNWN   |

### ITF-Struktur

- **Start-Pattern:** 4× schmal (Bar, Space, Bar, Space)
- **Daten:** Je 2 Ziffern werden **interleaved** kodiert:
  - Ziffer 1 = Bars (schwarz), Ziffer 2 = Spaces (weiss)
  - 5 Bars und 5 Spaces abwechselnd → 10 Elemente pro Paar
- **Stop-Pattern:** Breit-Bar, Schmal-Space, Schmal-Bar
- Die Nummer muss eine **gerade Anzahl Ziffern** haben (bei SSV: 10 → passt)

### Breitenverhältnis
- Schmal (N) = 1 Einheit, Breit (W) = 3 Einheiten

---

## PHP-Implementierung

**Datei:** `shared/ssv-barcode.php`
**Abhängigkeit:** `picqer/php-barcode-generator` (Composer)

```php
<?php
/**
 * Berechnet die 10-stellige Barcode-Nummer aus einer SSV-Lizenznummer
 */
function ssvBarcodeNummer(string $lizenznummer): string
{
    if (!preg_match('/^\d+$/', $lizenznummer)) {
        throw new InvalidArgumentException('SSV-Lizenznummer muss numerisch sein');
    }

    // 6-stellig → "10" voranstellen
    if (strlen($lizenznummer) === 6) {
        $lizenznummer = '10' . $lizenznummer;
    }

    // Mod-97-Prüfziffer: Nummer * 100 mod 97, CRC = 97 - Rest
    $basis = $lizenznummer . '00';
    $mod97 = (int)bcmod($basis, '97');
    $crc = 97 - $mod97;

    return $lizenznummer . str_pad((string)$crc, 2, '0', STR_PAD_LEFT);
}

/**
 * Generiert einen ITF-Barcode als SVG
 */
function ssvBarcodeSvg(string $barcodeNummer, int $widthFactor = 2, int $height = 60): string
{
    $generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
    return $generator->getBarcode(
        $barcodeNummer,
        \Picqer\Barcode\BarcodeGeneratorSVG::TYPE_INTERLEAVED_2_5,
        $widthFactor,
        $height
    );
}

/**
 * Generiert einen ITF-Barcode als Base64-PNG
 */
function ssvBarcodePngBase64(string $barcodeNummer, int $widthFactor = 2, int $height = 60): string
{
    $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
    $png = $generator->getBarcode(
        $barcodeNummer,
        \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_INTERLEAVED_2_5,
        $widthFactor,
        $height
    );
    return base64_encode($png);
}
```

### Composer-Paket installieren
```bash
composer require picqer/php-barcode-generator
```

---

## JavaScript-Implementierung

**Datei:** `js/itf-barcode.js` — Zeichnet ITF-Barcodes direkt auf ein HTML5 Canvas (z.B. für Etiketten-Vorschau).

```javascript
// ITF Encoding-Tabelle
const ITF_PATTERNS = [
    'NNWWN', 'WNNNW', 'NWNNW', 'WWNNN', 'NNWNW',
    'WNWNN', 'NWWNN', 'NNNWW', 'WNNWN', 'NWNWN'
];

/**
 * Berechnet die 10-stellige SSV-Barcode-Nummer (JS-Pendant)
 */
function ssvBarcodeNummer(lizenznummer) {
    if (!lizenznummer || !/^\d+$/.test(lizenznummer)) return '';
    if (lizenznummer.length === 6) lizenznummer = '10' + lizenznummer;
    if (lizenznummer.length !== 8) return '';

    const basis = BigInt(lizenznummer + '00');
    const rest = Number(basis % 97n);
    const crc = 97 - rest;
    return lizenznummer + String(crc).padStart(2, '0');
}

/**
 * Zeichnet einen ITF-Barcode auf ein Canvas-2D-Context
 *
 * @param {CanvasRenderingContext2D} ctx
 * @param {string} nummer    10-stellige Barcode-Nummer
 * @param {number} x, y      Position
 * @param {number} width      Gesamtbreite
 * @param {number} height     Höhe
 */
function drawItfBarcode(ctx, nummer, x, y, width, height) {
    if (!nummer || nummer.length % 2 !== 0) return;

    const narrow = 1, wide = 3;

    // Gesamtbreite berechnen
    let totalUnits = 4; // Start: NNNN
    for (let i = 0; i < nummer.length; i += 2) {
        const p1 = ITF_PATTERNS[parseInt(nummer[i])];
        const p2 = ITF_PATTERNS[parseInt(nummer[i + 1])];
        for (let j = 0; j < 5; j++) {
            totalUnits += (p1[j] === 'W' ? wide : narrow);
            totalUnits += (p2[j] === 'W' ? wide : narrow);
        }
    }
    totalUnits += wide + narrow + narrow; // Stop: WNN

    const unitWidth = width / totalUnits;
    let pos = x;

    const drawBar = (units) => { ctx.fillRect(pos, y, units * unitWidth, height); pos += units * unitWidth; };
    const drawSpace = (units) => { pos += units * unitWidth; };

    ctx.fillStyle = '#000000';

    // Start: Bar-Space-Bar-Space (alle schmal)
    drawBar(narrow); drawSpace(narrow); drawBar(narrow); drawSpace(narrow);

    // Daten: je 2 Ziffern interleaved
    for (let i = 0; i < nummer.length; i += 2) {
        const bars = ITF_PATTERNS[parseInt(nummer[i])];
        const spaces = ITF_PATTERNS[parseInt(nummer[i + 1])];
        for (let j = 0; j < 5; j++) {
            drawBar(bars[j] === 'W' ? wide : narrow);
            drawSpace(spaces[j] === 'W' ? wide : narrow);
        }
    }

    // Stop: Bar breit, Space schmal, Bar schmal
    drawBar(wide); drawSpace(narrow); drawBar(narrow);
}
```

---

## PrecisionID ITF Font-Encoding (für Excel/Direktdruck)

Falls der Barcode über einen Font gerendert werden soll (z.B. in Excel oder Word):

**Font:** `PrecisionID_ITF_12.ttf`

Paare von je 2 Ziffern werden zu einem Zeichen kodiert:
- Wert < 94 → `chr(Wert + 33)`
- Wert >= 94 → `chr(Wert + 103)`
- Start-Zeichen: `chr(203)`
- Stop-Zeichen: `chr(204)`

---

## Verwendung im Projekt

1. **Standblatt-Etiketten** (Canvas-Rendering): `ssvBarcodeNummer()` + `drawItfBarcode()` aus `itf-barcode.js`
2. **PDF-Generierung** (Server): `ssvBarcodeNummer()` + `ssvBarcodeSvg()`/`ssvBarcodePngBase64()` aus `shared/ssv-barcode.php`
3. **Scanner-Eingabe**: Der Scanner liefert die 10-stellige Nummer → Die ersten 8 Stellen = Mitgliedernummer (nach Abzug des `10`-Präfix = 6-stellige Lizenznummer)

### Validierung einer gescannten Nummer
```php
function validateSsvBarcode(string $scanned): bool {
    if (strlen($scanned) !== 10 || !ctype_digit($scanned)) return false;
    $expected = ssvBarcodeNummer(substr($scanned, 0, 8));
    return $expected === $scanned;
}
```

```javascript
function validateSsvBarcode(scanned) {
    if (!scanned || scanned.length !== 10 || !/^\d{10}$/.test(scanned)) return false;
    return ssvBarcodeNummer(scanned.substring(2, 8)) === scanned;
}
```
