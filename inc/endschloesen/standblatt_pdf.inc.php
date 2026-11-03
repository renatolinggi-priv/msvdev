<?php
/**
 * inc/endschloesen/standblatt_pdf.inc.php
 *
 * Endschiessen-Standblatt direkt als PDF mit Dompdf, ohne Excel-Vorlage und ohne PDF-Dienst.
 * A4 quer, zwei Seiten, beidseitig über die kurze Seite gedruckt und gefaltet:
 *   Seite 1 innen:  Endstich, Schwinistich | Zabigstich, Differenzler, Sie und Er
 *   Seite 2 aussen: Kunst, Glückstich      | Deckblatt (Titel, Logo, Name, Waffe, Lizenz, Absenden)
 *
 * Alle Masse in mm, gemessen am PDF, das die Excel-Vorlage (Vorlage/Standblatt_Endschiessen_Vorlage.xlsx)
 * über den PDF-Dienst ergab (Nov 2026). Die Klebefelder sind auf die Standblätter der Anlage abgestimmt
 * und dürfen nicht kleiner werden. Alles ist absolut gesetzt, Linien und Rahmen sind Striche: Dompdf
 * hält so feste Höhen zuverlässig ein (inline-block-Kästchen mit height ignoriert es).
 *
 * Texte: wie in der Vorlage. Preise aus endstich_definition bzw. endstich_spezialpreise (Partner Zabig),
 * Junioren-Preise stehen nur hier. Stichnummern aus interne_stichdefinition («Imetron-Stichnummern»).
 * Barcodes über die Bausteine des JM-Standblatts (Code 128 C, Lizenz ITF), gleich wie die Vorlage.
 */

require_once __DIR__ . '/../jmstandblatt/standblatt_pdf.inc.php';

/** Code => Resultat-Tabelle in interne_stichdefinition (Stichnummer für den Barcode). */
const ESB_RESTABLE = [
    'END' => 'endstich', 'SCHWINI_P1' => 'schwini', 'SCHWINI_P2' => 'schwini', 'KUNST' => 'kunst',
    'GLUECK' => 'glueck', 'ZABIG' => 'zabig', 'SIEUNDER' => 'endresultate_partner',
];

/** Stichnummern code => Nummer (leer = kein Barcode). */
function esb_stichnummern(mysqli $conn): array
{
    $proTabelle = [];
    $res = $conn->query('SELECT restable, nummer1 FROM interne_stichdefinition');
    while ($res && ($r = $res->fetch_assoc())) {
        $proTabelle[$r['restable']] = trim((string)$r['nummer1']);
    }
    $out = [];
    foreach (ESB_RESTABLE as $code => $tabelle) {
        $out[$code] = $proTabelle[$tabelle] ?? '';
    }
    return $out;
}

/** Preise in Franken: code => Betrag (Rappen / 100), dazu 'PARTNER_ZABIG'. */
function esb_preise(mysqli $conn): array
{
    $out = [];
    $res = $conn->query('SELECT code, price_cents FROM endstich_definition');
    while ($res && ($r = $res->fetch_assoc())) {
        $out[$r['code']] = (int)$r['price_cents'];
    }
    $res = $conn->query("SELECT price_cents FROM endstich_spezialpreise WHERE typ = 'partner_zabig' LIMIT 1");
    $r = $res ? $res->fetch_row() : null;
    $out['PARTNER_ZABIG'] = $r ? (int)$r[0] : 1000;
    return $out;
}

/** Betrag wie in der Vorlage: 20.--, 13.50 */
function esb_chf(int $rappen): string
{
    return $rappen % 100 === 0 ? intdiv($rappen, 100) . '.--' : number_format($rappen / 100, 2, '.', '');
}

function esb_css(): string
{
    return '
        @page { margin: 0; size: A4 landscape; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #000; }
        .s-seite { position: relative; width: 297mm; height: 209mm; overflow: hidden; page-break-after: always; }
        .s-letzte { page-break-after: auto; }
        .a { position: absolute; white-space: nowrap; }
        .l { position: absolute; background: #000; }
        .t { font-size: 9.65pt; line-height: 4mm; }
        .b { font-size: 9.65pt; line-height: 4mm; font-weight: bold; }
        .titel { font-size: 15.5pt; line-height: 6.4mm; font-weight: bold; }
        .nr-klein { font-size: 8pt; line-height: 3.6mm; text-align: center; color: #555; }
        .nr-gross { font-size: 16pt; line-height: 6.6mm; text-align: center; font-weight: bold; }
        .nr-zeile { font-size: 9pt; line-height: 6.6mm; text-align: center; }
        .nr-zeile strong { font-size: 16pt; }
        .geloest { font-size: 9.65pt; line-height: 4mm; font-weight: bold; }
        .deck { font-size: 23pt; line-height: 9.2mm; font-weight: bold; text-align: center; white-space: normal; }
        .wert { font-size: 9.5pt; line-height: 4.4mm; border-bottom: 0.6pt dotted #000; }
    ';
}

/**
 * Die zwei Seiten als HTML.
 * $d: name, waffe, jahr, lizenz (int, 0 = Gast), geloeste (Codes), preise (esb_preise), nummern (esb_stichnummern)
 */
function esb_seiten_html(array $d): string
{
    $h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    // Textzeile: y = Oberkante der Wortbox wie pdftotext sie für die Vorlage misst; Versatz je Schrift
    $off = ['t' => 0.35, 'b' => 0.35, 'titel' => 0.5, 'nr-klein' => 0.2, 'nr-gross' => 0.0, 'nr-zeile' => -0.6, 'geloest' => 0.35, 'deck' => -0.4, 'wert' => 0.3];
    $t = static function (float $x, float $y, string $inhalt, string $kl = 't', string $stil = '') use ($off): string {
        return '<div class="a ' . $kl . '" style="left: ' . $x . 'mm; top: ' . ($y + ($off[$kl] ?? 0)) . 'mm;' . $stil . '">' . $inhalt . '</div>';
    };
    $bild = static fn(float $x1, float $y1, float $x2, float $y2, string $src) => $src === '' ? '' :
        '<img class="a" src="' . $src . '" style="left: ' . $x1 . 'mm; top: ' . $y1 . 'mm; width: ' . round($x2 - $x1, 2) . 'mm; height: ' . round($y2 - $y1, 2) . 'mm;" alt="">';
    $linie = static fn(float $x1, float $y, float $x2, float $d = 0.2) =>
        '<div class="l" style="left: ' . $x1 . 'mm; top: ' . $y . 'mm; width: ' . round($x2 - $x1, 2) . 'mm; height: ' . $d . 'mm;"></div>';
    $doppel = static fn(float $x1, float $y, float $x2) => $linie($x1, $y, $x2, 0.25) . $linie($x1, $y + 0.6, $x2, 0.25);
    // Rahmen: x1/y1 = gemessene Kante oben/links, x2/y2 = gemessene Kante der Linie unten/rechts;
    // die Linien beginnen dort (wie in der Vorlage gemessen), das Klebefeld wird so nie kleiner
    $rahmen = static function (float $x1, float $y1, float $x2, float $y2, float $d = 0.25): string {
        $s = static fn($l, $tp, $w, $hh) => '<div class="l" style="left: ' . $l . 'mm; top: ' . $tp . 'mm; width: ' . round($w, 2) . 'mm; height: ' . round($hh, 2) . 'mm;"></div>';
        return $s($x1, $y1, $x2 - $x1 + $d, $d) . $s($x1, $y2, $x2 - $x1 + $d, $d) . $s($x1, $y1, $d, $y2 - $y1 + $d) . $s($x2, $y1, $d, $y2 - $y1 + $d);
    };
    $p = $d['preise'] ?? [];
    $chf = static fn(string $code, int $vorgabe) => esb_chf($p[$code] ?? $vorgabe);
    $nr = $d['nummern'] ?? [];
    $geloest = static fn(string $code, float $x, float $y) => in_array($code, $d['geloeste'] ?? [], true) ? $t($x, $y, 'gelöst', 'geloest') : '';
    $code128 = static fn(string $code) => ($nr[$code] ?? '') !== '' ? sb_pdf_code128c(sb_pdf_stich_barcode($nr[$code])) : '';
    // Klebefeld: oben klein «Stichnr.», darunter die Nummer gross (Wunsch 03.11.2026), dann der Barcode;
    // Text über der Mitte des Barcodes
    $feld = static function (string $code, float $x1, float $y1, float $x2, float $y2, float $yText, array $bc) use ($rahmen, $t, $bild, $nr, $code128, $h): string {
        $mitte = ($bc[0] + $bc[2]) / 2;
        $html = $rahmen($x1, $y1, $x2, $y2);
        if (($nr[$code] ?? '') !== '') {
            $html .= $t($mitte - 10, $yText, 'Stichnr.', 'nr-klein', ' width: 20mm;');
            $html .= $t($mitte - 10, $yText + 3.4, $h($nr[$code]), 'nr-gross', ' width: 20mm;');
            $html .= $bild($bc[0], $bc[1], $bc[2], $bc[3], $code128($code));
        }
        return $html;
    };
    // Barcode-Bild: gemessene Strichfläche, plus Ruhezone links/rechts (7 Module von 90)
    $bcBox = static fn(float $x1, float $y1, float $x2, float $y2) => [$x1 - ($x2 - $x1) * 7 / 90, $y1, $x2 + ($x2 - $x1) * 7 / 90, $y2 + 0.4];

    // ================= Seite 1 (innen) =================
    $s1 = '';
    // --- Endstich
    $s1 .= $t(18.1, 6.3, 'Endstich', 'titel') . $geloest('END', 66.2, 8.4);
    $s1 .= $t(17.8, 19.6, '10 Schuss / Scheibe A 10') . $t(17.8, 24.9, 'Probeschüsse unbeschränkt');
    $s1 .= $t(17.8, 35.4, 'DOPPEL:', 'b') . $t(39.7, 35.4, 'CHF') . $t(47.7, 35.4, $chf('END', 2000)) . $t(55.6, 35.4, 'Aktive/Veteranen');
    $s1 .= $t(39.7, 40.7, 'CHF') . $t(47.7, 40.7, '10.--') . $t(55.6, 40.7, 'Junioren');
    $s1 .= $t(39.7, 46.0, 'inkl. Munition');
    $s1 .= $t(17.8, 56.6, 'Auszeichnung:', 'b');
    $s1 .= $t(17.8, 61.9, 'Kat. «A»') . $t(39.7, 61.9, 'KK ab 89 Pkt.') . $t(39.7, 66.2, 'Veteranen / Junioren ab 87 Pkt.');
    $s1 .= $t(17.8, 75.6, 'Kat. «D»') . $t(39.7, 75.6, 'KK / KA ab 84 Pkt.') . $t(39.7, 80.1, 'Veteranen / Junioren ab 82 Pkt.');
    $s1 .= $feld('END', 105.2, 13.3, 137.7, 80.1, 19.1, $bcBox(109.5, 34.2, 132.7, 45.2));
    $s1 .= $doppel(17.1, 94.0, 137.5);
    // --- Schwinistich (zwei Felder, Preise darunter)
    $s1 .= $t(18.1, 103.1, 'Schwinistich', 'titel');
    $s1 .= $geloest('SCHWINI_P1', 66.2, 99.0) . $geloest('SCHWINI_P2', 106.0, 99.0);
    $s1 .= $t(66.2, 105.3, '1. Doppel') . $t(106.0, 105.3, '2. Doppel');
    $s1 .= $t(17.8, 114.5, '6 Schuss / Sau-Scheibe A10') . $t(17.8, 118.8, '2 Probeschüsse pro Passe');
    $s1 .= $t(17.8, 127.2, 'Bei Punktgleichheit', 'b') . $t(17.8, 131.6, 'entscheiden:', 'b');
    $s1 .= $t(17.8, 138.0, 'der höhere 2. Doppel') . $t(17.8, 142.8, 'die besseren Tiefschüsse')
         . $t(17.8, 147.2, 'aus beiden Passen') . $t(17.8, 151.5, 'das höhere Alter');
    $s1 .= $feld('SCHWINI_P1', 65.4, 110.2, 97.8, 175.6, 114.2, $bcBox(67.7, 129.4, 94.6, 142.2));
    $s1 .= $feld('SCHWINI_P2', 105.2, 110.2, 137.7, 175.6, 114.2, $bcBox(108.3, 129.4, 135.3, 142.2));
    foreach ([[179.8, '1. Doppel', 'b', 17.8, $chf('SCHWINI_P1', 2000)], [184.2, '(Junioren)', 't', 20.5, '16.--'],
              [188.4, '2. Doppel', 'b', 17.8, $chf('SCHWINI_P2', 1400)], [192.8, '(Junioren)', 't', 20.5, '13.--']] as [$y, $lab, $kl, $x, $betrag]) {
        $s1 .= $t($x, $y, $lab, $kl) . $t(48.9, $y, 'CHF') . $t(73.9, $y, $betrag) . $t(82.2, $y, 'inkl. Munition');
    }
    $s1 .= $linie(17.1, 188.6, 97.6, 0.2);
    // --- Zabigstich (Barcode ohne Feld, mit Ablauf)
    $s1 .= $t(168.7, 6.3, 'Zabigstich', 'titel') . $geloest('ZABIG', 214.6, 8.4);
    $s1 .= $t(168.5, 19.6, '6 Schuss / Scheibe A10') . $t(168.5, 24.9, 'Probeschüsse unbeschränkt auf andere Scheibe möglich')
         . $t(168.5, 30.2, 'Das Resultat wird erst am Absenden bekannt gegeben.');
    $s1 .= $t(168.5, 40.7, 'DOPPEL:', 'b') . $t(194.5, 40.7, 'CHF') . $t(206.3, 40.7, $chf('ZABIG', 1900)) . $t(214.6, 40.7, 'inkl. Munition');
    $s1 .= $t(168.5, 46.0, 'Partner') . $t(194.5, 46.0, 'CHF') . $t(206.3, 46.0, $chf('PARTNER_ZABIG', 1000)) . $t(214.6, 46.0, 'inkl. Munition');
    $s1 .= $t(168.5, 56.6, 'Ablauf:', 'b') . $t(190.3, 56.6, '1. Mit der eigenen Lizenz anmelden')
         . $t(190.3, 61.9, '2. Stich mit folgendem Barcode laden') . $t(190.3, 66.2, '3. Kontrolle des eingelesenen Schützen');
    if (($nr['ZABIG'] ?? '') !== '') {
        [$bx1, $by1, $bx2, $by2] = $bcBox(201.3, 71.8, 229.7, 85.3);
        $s1 .= $bild($bx1, $by1, $bx2, $by2, $code128('ZABIG'));
        $s1 .= $t(($bx1 + $bx2) / 2 - 15, 85.4, 'Stichnr. <strong>' . $h($nr['ZABIG']) . '</strong>', 'nr-zeile', ' width: 30mm;');
    }
    $s1 .= $doppel(167.8, 94.0, 279.8);
    // --- Differenzler (Feld ohne Barcode)
    $s1 .= $t(168.7, 103.1, 'Differenzler', 'titel') . $geloest('DIFF', 214.6, 105.3);
    $s1 .= $t(168.5, 110.2, 'Total Zabigstich / Scheibe A100');
    $s1 .= $t(168.5, 118.7, 'DOPPEL:', 'b') . $t(194.5, 118.7, 'CHF') . $t(208.2, 118.7, $chf('DIFF', 500));
    $s1 .= $rahmen(245.5, 114.6, 279.9, 123.2);
    $s1 .= $doppel(167.8, 126.9, 279.8);
    // --- Sie und Er
    $s1 .= $t(168.7, 135.8, 'Sie und Er', 'titel') . $geloest('SIEUNDER', 214.6, 138.0);
    $s1 .= $t(168.5, 147.2, '5 Schuss / Scheibe A10') . $t(168.5, 151.5, 'Probeschüsse unbeschränkt');
    $s1 .= $t(168.5, 160.0, 'DOPPEL:', 'b') . $t(194.5, 160.0, 'CHF') . $t(206.3, 160.0, $chf('SIEUNDER', 1000)) . $t(214.6, 160.0, 'inkl. Munition');
    $s1 .= $feld('SIEUNDER', 245.5, 142.9, 279.9, 201.4, 146.8, $bcBox(249.2, 160.4, 276.1, 173.4));

    // ================= Seite 2 (aussen) =================
    $s2 = '';
    // --- Kunst
    $s2 .= $t(18.1, 9.7, 'Kunst', 'titel') . $geloest('KUNST', 66.2, 12.0);
    $s2 .= $t(17.8, 23.1, '5 Schuss / Scheibe A 100') . $t(17.8, 28.4, 'Probeschüsse unbeschränkt');
    $s2 .= $t(17.8, 39.0, 'DOPPEL:', 'b') . $t(48.9, 39.0, 'CHF') . $t(66.2, 39.0, $chf('KUNST', 1300)) . $t(74.1, 39.0, 'Aktive/Veteranen');
    $s2 .= $t(66.2, 44.3, '9.--') . $t(74.1, 44.3, 'Junioren') . $t(66.2, 49.6, 'inkl. Munition');
    $s2 .= $t(17.8, 60.1, 'Auszeichnung:', 'b');
    $s2 .= $t(17.8, 65.5, 'Kat. «A»') . $t(39.7, 65.5, 'KK ab 435 Pkt.') . $t(39.7, 69.8, 'Veteranen / Junioren ab 425 Pkt.');
    $s2 .= $t(17.8, 79.1, 'Kat. «D»') . $t(39.7, 79.1, 'KK / KA ab 415 Pkt.') . $t(39.7, 83.7, 'Veteranen / Junioren ab 405 Pkt.');
    $s2 .= $feld('KUNST', 105.2, 16.9, 137.7, 70.1, 21.4, $bcBox(108.1, 38.0, 134.1, 50.5));
    $s2 .= $doppel(17.1, 96.5, 137.5);
    // --- Glückstich (+ Feld «Bester Schuss»)
    $s2 .= $t(18.1, 104.2, 'Glückstich', 'titel') . $geloest('GLUECK', 66.2, 106.5);
    $s2 .= $t(17.8, 116.5, '3 Schuss / Scheibe A 100') . $t(17.8, 121.8, 'Probeschüsse unbeschränkt')
         . $t(17.8, 132.4, 'Für die Rangierung zählt der beste Schuss.');
    $s2 .= $t(17.8, 137.6, 'Bei Punktgleichheit entscheiden:', 'b');
    $s2 .= $t(37.2, 143.0, '- der höhere 2. Schuss') . $t(37.2, 148.3, '- der höhere 3. Schuss');
    $s2 .= $t(17.8, 157.1, 'DOPPEL:', 'b') . $t(48.9, 157.1, 'CHF') . $t(75.8, 157.1, $chf('GLUECK', 800)) . $t(82.2, 157.1, 'inkl. Munition');
    $s2 .= $t(82.2, 162.6, 'Bester Schuss', 'b');
    $s2 .= $feld('GLUECK', 105.2, 115.6, 137.7, 147.3, 119.5, $bcBox(108.1, 132.1, 134.5, 144.8));
    $s2 .= $rahmen(121.0, 157.0, 137.8, 171.7, 0.5);
    // --- Deckblatt
    static $logo = null;
    $logo ??= function_exists('pdf_logo_src') ? pdf_logo_src() : '';
    $s2 .= $t(167.8, 11.8, 'Endschiessen<br>MSV Wilen<br>' . $h($d['jahr']), 'deck', ' width: 122.5mm;');
    $s2 .= $linie(167.8, 43.3, 279.8, 0.3);
    $s2 .= $bild(201.9, 49.4, 262.1, 104.8, $logo);
    $s2 .= $linie(167.8, 111.3, 279.8, 0.3);
    $s2 .= $t(168.5, 121.8, 'Vorname, Name', 'b') . $t(214.6, 121.4, $h($d['name']), 'wert', ' width: 50mm;');
    $s2 .= $t(168.5, 132.3, 'Waffe', 'b') . $t(214.6, 131.9, $h($d['waffe'] ?? '') . '&nbsp;', 'wert', ' width: 50mm;');
    if ((int)($d['lizenz'] ?? 0) > 0) {
        $s2 .= $bild(214.2, 142.4, 262.0, 152.7, sb_pdf_lizenz_barcode((int)$d['lizenz']));
    }
    $s2 .= $t(168.5, 157.1, 'Anmeldung Absenden', 'b');
    foreach ([[213.6, 225.7, 218.4, 'ja'], [227.3, 240.1, 228.3, 'Anzahl'], [245.2, 258.2, 248.4, 'nein']] as [$x1, $x2, $xl, $lab]) {
        $s2 .= $rahmen($x1, 157.0, $x2, 167.1, 0.5) . $t($xl, 167.1, $lab, 'b');
    }

    return '<div class="s-seite">' . $s1 . '</div><div class="s-seite s-letzte">' . $s2 . '</div>';
}

function esb_dokument(array $d): string
{
    return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Standblatt Endschiessen ' . (int)$d['jahr'] . '</title>'
         . '<style>' . esb_css() . '</style></head><body>' . esb_seiten_html($d) . '</body></html>';
}
