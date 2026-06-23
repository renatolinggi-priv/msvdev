/**
 * SSV Barcode Utilities — Interleaved 2 of 5 (ITF)
 * Zentrale Funktionen für Lizenznummer → Barcode-Nummer und Canvas-Rendering.
 */

const ITF_PATTERNS = [
    'NNWWN', 'WNNNW', 'NWNNW', 'WWNNN', 'NNWNW',
    'WNWNN', 'NWWNN', 'NNNWW', 'WNNWN', 'NWNWN'
];

/**
 * Berechnet die 10-stellige SSV-Barcode-Nummer aus einer 6- oder 8-stelligen Lizenznummer.
 * @param {string} lizenznummer
 * @returns {string} 10-stellige Barcode-Nummer oder ''
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
 * Zeichnet einen ITF-Barcode auf ein Canvas-2D-Context.
 * Die Nutzbreite wird um eine Quiet Zone (10 Einheiten beidseitig) reduziert,
 * Balken werden pixelgenau gerundet (keine Subpixel) für scharfe Scanner-Kanten.
 * @param {CanvasRenderingContext2D} ctx
 * @param {string} nummer  10-stellige Barcode-Nummer
 * @param {number} x       Start-X (CSS-Pixel)
 * @param {number} y       Start-Y
 * @param {number} width   Gesamtbreite inkl. Quiet Zones
 * @param {number} height  Höhe
 */
function drawItfBarcode(ctx, nummer, x, y, width, height) {
    if (!nummer || nummer.length % 2 !== 0) return;

    const narrow = 1, wide = 3;
    const quietUnits = 10; // ITF-Spec: mind. 10× narrow

    // Gesamtbreite in Einheiten berechnen (inkl. Quiet Zones)
    let dataUnits = 4; // Start: NNNN
    for (let i = 0; i < nummer.length; i += 2) {
        const p1 = ITF_PATTERNS[parseInt(nummer[i])];
        const p2 = ITF_PATTERNS[parseInt(nummer[i + 1])];
        for (let j = 0; j < 5; j++) {
            dataUnits += (p1[j] === 'W' ? wide : narrow);
            dataUnits += (p2[j] === 'W' ? wide : narrow);
        }
    }
    dataUnits += wide + narrow + narrow; // Stop: WNN

    const totalUnits = dataUnits + 2 * quietUnits;
    // Ganzzahlige Unit-Breite für pixelgenaue Balken (mind. 1 Pixel)
    const unitWidth = Math.max(1, Math.floor(width / totalUnits));
    const barcodeWidth = unitWidth * totalUnits;
    // Zentrieren falls durch Rundung Platz übrig bleibt
    let pos = Math.floor(x + (width - barcodeWidth) / 2);

    // Quiet Zone vorne
    pos += quietUnits * unitWidth;

    ctx.fillStyle = '#000000';

    const drawBar = (units) => { ctx.fillRect(pos, y, units * unitWidth, height); pos += units * unitWidth; };
    const drawSpace = (units) => { pos += units * unitWidth; };

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

/**
 * Bereitet ein Canvas für scharfes Rendering auf HighDPI-Displays vor.
 * Passt die Backing-Store-Auflösung an devicePixelRatio an und skaliert den Context,
 * damit nachfolgendes Zeichnen in CSS-Pixeln erfolgt.
 * @param {HTMLCanvasElement} canvas
 * @param {number} cssWidth
 * @param {number} cssHeight
 * @returns {CanvasRenderingContext2D}
 */
function prepareBarcodeCanvas(canvas, cssWidth, cssHeight) {
    const dpr = Math.max(1, window.devicePixelRatio || 1);
    canvas.style.width = cssWidth + 'px';
    canvas.style.height = cssHeight + 'px';
    canvas.width = Math.round(cssWidth * dpr);
    canvas.height = Math.round(cssHeight * dpr);
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.imageSmoothingEnabled = false;
    // Weisser Hintergrund (Quiet Zone muss weiss sein)
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, cssWidth, cssHeight);
    return ctx;
}
