<?php
/**
 * inc/jmstandblatt/standblatt_pdf.inc.php
 *
 * JM-Standblatt (Heimmeisterschaft und Kantonalstich) direkt als PDF mit Dompdf, ohne Word-Vorlage
 * und ohne PDF-Dienst (seit 1.68.49). A4 quer, zwei Seiten, zum Falten:
 *   Seite 1 aussen: links Barcodes der übrigen Stiche, rechts Deckblatt im Stil des Endschiessen-Standblatts
 *   Seite 2 innen:  Klebefelder Heimmeisterschaft (8 Passen) und Kantonalstich (5 Passen)
 *
 * Alle Masse in mm, gemessen am PDF der früheren Word-Vorlage (Vorlage/MSVStandblatHeim-KantVorlage.docx).
 * Die Klebefelder sind auf die Standblätter der Anlage abgestimmt und dürfen nicht kleiner werden.
 * Alles ist absolut gesetzt, Linien sind Striche: Dompdf hält feste Höhen so zuverlässig ein
 * (inline-block-Kästchen mit height ignoriert es).
 *
 * Barcodes: Code 128 Zeichensatz C mit «20» + Stichnr. + Stichnr. + 2 Prüfziffern
 * (97 - (Zahl·100 mod 97)), Strich für Strich gleich wie die Bilder der Word-Vorlage.
 */

require_once __DIR__ . '/standblatt_common.inc.php';

/**
 * Stiche auf der Aussenseite (links). Heim-Mannschaft kommt aus «Imetron-Stichnummern»,
 * der Rest stand fest in der Word-Vorlage. Zeilen mit «!» am Anfang erscheinen rot.
 */
function sb_pdf_stiche_aussen(array $heim): array
{
    return [
        ['zeilen' => ['1. Passe Mannschaft'], 'nr' => $heim['nummer1'] ?? '133', 'zeigeNr' => true],
        ['zeilen' => ['2. Passe Mannschaft'], 'nr' => $heim['nummer2'] ?? '134', 'zeigeNr' => true],
        ['zeilen' => ['SSM'], 'nr' => '604', 'zeigeNr' => true],
        ['zeilen' => ['SKSG Jubiläumswettkampf', '!ab 16. Mai bis Endschiessen'], 'nr' => '354', 'zeigeNr' => true],
        ['zeilen' => ['Probe A10'], 'nr' => '031', 'zeigeNr' => false],
        ['zeilen' => ['Probe A100'], 'nr' => '051', 'zeigeNr' => false],
        ['zeilen' => ['Rütlitraining'], 'nr' => '381', 'zeigeNr' => false],
    ];
}

/** Stichnummern Heim/Kanti aus interne_stichdefinition (Seite «Imetron-Stichnummern»). */
function sb_pdf_stichnummern(mysqli $conn): array
{
    $out = ['heim' => [], 'kanti' => []];
    $res = $conn->query("SELECT restable, nummer1, nummer2, nummer3 FROM interne_stichdefinition WHERE restable IN ('heimresultate', 'kantiresultate')");
    while ($res && ($r = $res->fetch_assoc())) {
        $key = $r['restable'] === 'heimresultate' ? 'heim' : 'kanti';
        foreach (['nummer1', 'nummer2', 'nummer3'] as $f) {
            $v = trim((string)($r[$f] ?? ''));
            if ($v !== '') $out[$key][$f] = $v;
        }
    }
    return $out;
}

/** Waffe pro Mitglied aus den Stammdaten: ID => Bezeichnung. */
function sb_pdf_waffen(mysqli $conn): array
{
    $out = [];
    $res = $conn->query('SELECT m.ID, w.Bezeichnung FROM mitglieder m JOIN Waffen w ON w.ID = m.WaffenID');
    while ($res && ($r = $res->fetch_assoc())) {
        $out[(int)$r['ID']] = (string)$r['Bezeichnung'];
    }
    return $out;
}

/** Angaben für ein Standblatt aus einer Mitglieder-Zeile (ID, Vorname, Name). */
function sb_pdf_person(array $row, int $jahr, array $nummern, array $waffen): array
{
    return [
        'name'    => trim($row['Vorname'] . ' ' . $row['Name']),
        'waffe'   => $waffen[(int)$row['ID']] ?? '',
        'jahr'    => $jahr,
        'lizenz'  => (int)$row['ID'],
        'nummern' => $nummern,
    ];
}

/** Inhalt des Stich-Barcodes: «20» + Nr + Nr + Prüfziffern (wie die Vorlage). */
function sb_pdf_stich_barcode(string $nr): string
{
    $nr = preg_replace('/\D/', '', $nr);
    if ($nr === '' || strlen($nr) > 3) return '';
    $nr = str_pad($nr, 3, '0', STR_PAD_LEFT);
    $basis = '20' . $nr . $nr;
    return $basis . str_pad((string)(97 - (int)bcmod($basis . '00', '97')), 2, '0', STR_PAD_LEFT);
}

/**
 * Code 128 C als PNG-Data-URI, mit Klarschrift darunter wie in der Vorlage.
 * $drehen: 90° gegen den Uhrzeigersinn (Klebefelder: Striche waagrecht, Ziffern rechts).
 */
function sb_pdf_code128c(string $ziffern, bool $drehen = false): string
{
    if ($ziffern === '' || strlen($ziffern) % 2 !== 0 || !ctype_digit($ziffern)) return '';
    static $cache = [];
    $schluessel = $ziffern . ($drehen ? 'd' : '');
    if (isset($cache[$schluessel])) return $cache[$schluessel];

    static $t = null;
    $t ??= explode(' ', '212222 222122 222221 121223 121322 131222 122213 122312 132212 221213 221312 231212 112232 122132 122231 113222 123122 123221 223211 221132 221231 213212 223112 312131 311222 321122 321221 312212 322112 322211 212123 212321 232121 111323 131123 131321 112313 132113 132311 211313 231113 231311 112133 112331 132131 113123 113321 133121 313121 211331 231131 213113 213311 213131 311123 311321 331121 312113 312311 332111 314111 221411 431111 111224 111422 121124 121421 141122 141221 112214 112412 122114 122411 142112 142211 241211 221114 413111 241112 134111 111242 121142 121241 114212 124112 124211 411212 421112 421211 212141 214121 412121 111143 111341 131141 114113 114311 411113 411311 113141 114131 311141 411131 211412 211214 211232');
    $werte = [105];                       // Start C
    for ($i = 0; $i < strlen($ziffern); $i += 2) $werte[] = (int)substr($ziffern, $i, 2);
    $summe = $werte[0];
    for ($i = 1; $i < count($werte); $i++) $summe += $i * $werte[$i];
    $werte[] = $summe % 103;              // Prüfzeichen
    $muster = '';
    foreach ($werte as $w) $muster .= $t[$w];
    $muster .= '2331112';                 // Stopp

    $modul = 4; $ruhe = 7; $strichHoehe = 168; $textHoehe = 21;   // Seitenverhältnis wie Vorlage (2.2 : 1)
    $breite = (array_sum(str_split($muster)) + 2 * $ruhe) * $modul;
    $img = imagecreatetruecolor($breite, $strichHoehe + $textHoehe);
    $weiss = imagecolorallocate($img, 255, 255, 255);
    $schwarz = imagecolorallocate($img, 0, 0, 0);
    imagefill($img, 0, 0, $weiss);
    $x = $ruhe * $modul;
    foreach (str_split($muster) as $i => $m) {
        if ($i % 2 === 0) imagefilledrectangle($img, $x, 0, $x + (int)$m * $modul - 1, $strichHoehe - 1, $schwarz);
        $x += (int)$m * $modul;
    }
    $font = 3;
    imagestring($img, $font, (int)(($breite - strlen($ziffern) * imagefontwidth($font)) / 2), $strichHoehe + 3, $ziffern, $schwarz);
    if ($drehen) {
        $img = imagerotate($img, 90, $weiss);
    }
    ob_start();
    imagepng($img);
    return $cache[$schluessel] = 'data:image/png;base64,' . base64_encode((string)ob_get_clean());
}

/** Lizenz-Barcode (ITF wie bisher, sb_barcode_nummer) als Data-URI, leer ohne gültige Lizenz. */
function sb_pdf_lizenz_barcode(int $lizenz): string
{
    $nummer = sb_barcode_nummer((string)$lizenz);
    $datei = $nummer !== '' ? sb_barcode_image($nummer, 'png', 560, 120) : null;
    if (!$datei) return '';
    $src = 'data:image/png;base64,' . base64_encode((string)file_get_contents($datei));
    unlink($datei);
    return $src;
}

/** CSS des Standblatts (einmal pro Dokument). */
function sb_pdf_css(): string
{
    return '
        @page { margin: 0; size: A4 landscape; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #000; }
        .sb-seite { position: relative; width: 297mm; height: 209mm; overflow: hidden; page-break-after: always; }
        .sb-seite-letzte { page-break-after: auto; }
        .sb-a { position: absolute; white-space: nowrap; }
        .sb-l { position: absolute; background: #000; }
        .sb-label { font-size: 10.5pt; font-weight: bold; line-height: 5.2mm; }
        .sb-rot { color: #e00000; }
        .sb-deck-titel { font-size: 24pt; font-weight: bold; line-height: 10.5mm; text-align: center; white-space: normal; }
        .sb-deck-label { font-size: 10pt; font-weight: bold; line-height: 5mm; }
        .sb-deck-wert { font-size: 11pt; line-height: 5.4mm; border-bottom: 0.6pt dotted #000; }
        .sb-kopf { font-size: 11pt; font-weight: bold; line-height: 4.4mm; }
        .sb-zusatz { font-weight: normal; font-size: 9pt; color: #555; margin-left: 2mm; }
        .sb-kopffeld { background: #e9e9e9; }
        .sb-passe { font-size: 11pt; line-height: 4.4mm; }
        .sb-runde { font-size: 7.5pt; color: #555; text-align: right; line-height: 4.4mm; }
        .sb-nr { font-size: 11pt; line-height: 5.5mm; }
        .sb-nr-klein { font-size: 8pt; color: #555; line-height: 3.6mm; }
        .sb-nr-gross { font-size: 15pt; font-weight: bold; line-height: 6.4mm; }
    ';
}

/**
 * Die zwei Seiten eines Standblatts als HTML.
 * $p: name, waffe, jahr, lizenz (int), nummern (aus sb_pdf_stichnummern), siehe sb_pdf_person()
 */
function sb_pdf_seiten_html(array $p, bool $letztes = true): string
{
    $h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $a = static fn(float $x, float $y, string $inhalt, string $klasse = '') =>
        '<div class="sb-a ' . $klasse . '" style="left: ' . $x . 'mm; top: ' . $y . 'mm;">' . $inhalt . '</div>';
    $bild = static fn(float $x, float $y, float $b, float $hh, string $src) => $src === '' ? '' :
        '<img class="sb-a" src="' . $src . '" style="left: ' . $x . 'mm; top: ' . $y . 'mm; width: ' . $b . 'mm; height: ' . $hh . 'mm;" alt="">';
    $linie = 0.18;
    $waag = static fn(float $x, float $y, float $b) => '<div class="sb-l" style="left: ' . $x . 'mm; top: ' . ($y - $linie / 2) . 'mm; width: ' . $b . 'mm; height: ' . $linie . 'mm;"></div>';
    $senk = static fn(float $x, float $y, float $hh) => '<div class="sb-l" style="left: ' . ($x - $linie / 2) . 'mm; top: ' . $y . 'mm; width: ' . $linie . 'mm; height: ' . $hh . 'mm;"></div>';

    $nummern = $p['nummern'] ?? [];
    $heim = $nummern['heim'] ?? [];
    $kanti = $nummern['kanti'] ?? [];

    // ---------- Seite 1 links: Barcodes der übrigen Stiche (Lage wie Vorlage)
    $s1 = '';
    $zeilenOben = [29.1, 49.8, 70.6, 91.2, 117.3, 138.4, 159.1];   // Oberkante Grossbuchstaben
    $barcodeOben = [28.1, 48.6, 69.3, 90.0, 116.2, 137.3, 158.1];
    foreach (sb_pdf_stiche_aussen($heim) as $i => $st) {
        $html = '';
        foreach ($st['zeilen'] as $z) {
            $rot = str_starts_with($z, '!');
            $html .= '<div' . ($rot ? ' class="sb-rot"' : '') . '>' . $h(ltrim($z, '!')) . '</div>';
        }
        if ($st['zeigeNr']) $html .= '<div>Stich-Nr.: ' . $h($st['nr']) . '</div>';
        $s1 .= $a(18.7, $zeilenOben[$i] - 1.1, $html, 'sb-label');
        $s1 .= $bild(88.4, $barcodeOben[$i] - 0.2, 34.4, 15.6, sb_pdf_code128c(sb_pdf_stich_barcode($st['nr'])));
    }

    // ---------- Seite 1 rechts: Deckblatt im Stil des Endschiessen-Standblatts
    // (grosser Titel, Linien um das Logo, Vorname/Name und Waffe auf gepunkteter Linie, Lizenz-Barcode)
    static $logo = null;
    $logo ??= function_exists('pdf_logo_src') ? pdf_logo_src() : '';
    $s1 .= '<div class="sb-a sb-deck-titel" style="left: 158mm; top: 12mm; width: 129.3mm;">Heimmeisterschaft<br>Kantonalstich<br>MSV Wilen ' . $h($p['jahr']) . '</div>';
    $s1 .= $waag(161.0, 45.5, 115.0);
    $s1 .= $bild(194.0, 50.5, 57.3, 56.4, $logo);
    $s1 .= $waag(161.0, 112.0, 115.0);
    $s1 .= $a(162.0, 124.0, 'Vorname, Name', 'sb-deck-label');
    $s1 .= '<div class="sb-a sb-deck-wert" style="left: 200mm; top: 123.6mm; width: 76mm;">' . $h($p['name']) . '</div>';
    $s1 .= $a(162.0, 134.5, 'Waffe', 'sb-deck-label');
    $s1 .= '<div class="sb-a sb-deck-wert" style="left: 200mm; top: 134.1mm; width: 76mm;">' . $h($p['waffe'] ?? '') . '&nbsp;</div>';
    $s1 .= $a(162.0, 146.0, 'Lizenz', 'sb-deck-label');
    $s1 .= $bild(200.0, 145.0, 37.1, 8.0, sb_pdf_lizenz_barcode((int)$p['lizenz']));

    // ---------- Seite 2: Klebefelder Heim (8 Passen) und Kanti (5 Passen), Masse wie Vorlage.
    // Heimmeisterschaft = Mannschaft: 4 Runden zu je zwei Stichen (1. Passe Mannschaft, 2. Passe Mannschaft),
    // die Kästchen wechseln darum zwischen nummer1 und nummer2.
    $s2 = '';
    $spalte = 33.87;
    $links = ['heim' => 9.9, 'kanti' => 151.0];
    $yLinien = [19.7, 25.0, 109.7, 114.9, 192.4];                    // Tabellenlinien
    $reihen = [['kopf' => 19.7, 'feld' => 25.0, 'text' => 6.4, 'code' => 26.6],
               ['kopf' => 109.7, 'feld' => 114.9, 'text' => 5.8, 'code' => 23.0]];
    $s2 .= $a(12.2, 9.9, 'Heimmeisterschaft <span class="sb-zusatz">Mannschaft · 4 Runden zu 2 Passen</span>', 'sb-kopf');
    $s2 .= $a(153.3, 9.9, 'Kantonalstich', 'sb-kopf');
    $heimNr = [$heim['nummer1'] ?? '133', $heim['nummer2'] ?? '134'];
    $kantiNr = $kanti['nummer1'] ?? '520';
    $bloecke = [
        'heim'  => ['passen' => [[1, 2, 3, 4], [5, 6, 7, 8]], 'nr' => static fn(int $n) => $heimNr[($n - 1) % 2], 'runde' => true],
        'kanti' => ['passen' => [[1, 2, 3, 4], [5, null, null, null]], 'nr' => static fn(int $n) => $kantiNr, 'runde' => false],
    ];
    foreach ($bloecke as $key => $b) {
        $x0 = $links[$key];
        foreach ([0, 2] as $k) {   // Kopfzeilen hellgrau
            $s2 .= '<div class="sb-l sb-kopffeld" style="left: ' . $x0 . 'mm; top: ' . $yLinien[$k] . 'mm; width: ' . (4 * $spalte) . 'mm; height: ' . ($yLinien[$k + 1] - $yLinien[$k]) . 'mm;"></div>';
        }
        foreach ($yLinien as $y) $s2 .= $waag($x0, $y, 4 * $spalte + $linie);
        for ($c = 0; $c <= 4; $c++) $s2 .= $senk($x0 + $c * $spalte, $yLinien[0], end($yLinien) - $yLinien[0]);
        foreach ($reihen as $r => $reihe) {
            foreach ($b['passen'][$r] as $c => $passe) {
                if ($passe === null) continue;
                $nr = ($b['nr'])($passe);
                $xc = $x0 + $c * $spalte;
                $s2 .= $a($xc + 0.8, $reihe['kopf'] + 1.0, $passe . '. Passe', 'sb-passe');
                if ($b['runde']) {
                    $s2 .= '<div class="sb-a sb-runde" style="left: ' . ($xc + 0.8) . 'mm; top: ' . ($reihe['kopf'] + 1.3) . 'mm; width: ' . ($spalte - 2) . 'mm;">Runde ' . (int)ceil($passe / 2) . '</div>';
                }
                $s2 .= $a($xc + 5.1, $reihe['feld'] + $reihe['text'] - 1.6, '<span class="sb-nr-klein">Stich-Nr.</span><br><span class="sb-nr-gross">' . $h($nr) . '</span>', 'sb-nr');
                $s2 .= $bild($xc + 6.9, $reihe['feld'] + $reihe['code'], 14.2, 31.2, sb_pdf_code128c(sb_pdf_stich_barcode($nr), true));
            }
        }
    }

    return '<div class="sb-seite">' . $s1 . '</div>'
         . '<div class="sb-seite' . ($letztes ? ' sb-seite-letzte' : '') . '">' . $s2 . '</div>';
}

/** Ganzes HTML-Dokument für ein oder mehrere Standblätter. */
function sb_pdf_dokument(array $personen, int $jahr): string
{
    $html = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>JM Standblatt ' . $jahr . '</title>'
          . '<style>' . sb_pdf_css() . '</style></head><body>';
    $n = count($personen);
    foreach (array_values($personen) as $i => $p) {
        $html .= sb_pdf_seiten_html($p, $i === $n - 1);
    }
    return $html . '</body></html>';
}

/** Dompdf-Rendering, liefert die PDF-Bytes. */
function sb_pdf_rendern(string $html): string
{
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    return $dompdf->output();
}

/** PDF ausgeben (Download-Name über Content-Disposition, msvAusgabe liest ihn). */
function sb_pdf_senden(string $pdf, string $dateiname): void
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: max-age=0');
    echo $pdf;
}
