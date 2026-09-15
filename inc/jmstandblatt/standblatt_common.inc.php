<?php
/**
 * inc/jmstandblatt/standblatt_common.inc.php
 *
 * Gemeinsame Bausteine der drei Standblatt-Generatoren (DOCX, PDF, Sammel-PDF).
 * Vorher lagen Barcode-Berechnung, Barcode-Bild und Vorlagen-Befuellung dreimal
 * wortgleich in den Generatoren (Review 09.2026).
 */

use PhpOffice\PhpWord\TemplateProcessor;

/** Pfad zur DOCX-Vorlage (A4 quer). */
function sb_template_path(): string
{
    return __DIR__ . '/Vorlage/MSVStandblatHeim-KantVorlage.docx';
}

/** Mitglied fuer das Standblatt laden (null wenn unbekannt). */
function sb_load_mitglied(mysqli $conn, int $mitgliedId): ?array
{
    $stmt = $conn->prepare("SELECT ID, Vorname, Name FROM mitglieder WHERE ID = ?");
    $stmt->bind_param('i', $mitgliedId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * SSV-Barcode-Nummer aus der Lizenznummer: 6-stellige Nummern erhalten den Praefix "10",
 * dann 2 Pruefziffern (97 - (Nummer*100 mod 97)).
 */
function sb_barcode_nummer(string $lnr): string
{
    if ($lnr === '' || !ctype_digit($lnr)) return '';
    if (strlen($lnr) === 6) $lnr = '10' . $lnr;
    if (strlen($lnr) !== 8) return '';
    $rest = bcmod($lnr . '00', '97');
    $crc  = 97 - (int)$rest;
    return $lnr . str_pad((string)$crc, 2, '0', STR_PAD_LEFT);
}

/**
 * ITF-Barcode (Interleaved 2 of 5) als Bilddatei. Gibt den Pfad einer Temp-Datei zurueck
 * (Aufrufer loescht sie), oder null bei ungerader Stellenzahl.
 *
 * @param string $format 'png' (DOCX-Download) oder 'jpg' (PDF-Konvertierung)
 */
function sb_barcode_image(string $nummer, string $format = 'png', int $imgWidth = 280, int $imgHeight = 60): ?string
{
    if ($nummer === '' || strlen($nummer) % 2 !== 0) return null;

    $patterns = ['NNWWN', 'WNNNW', 'NWNNW', 'WWNNN', 'NNWNW', 'WNWNN', 'NWWNN', 'NNNWW', 'WNNWN', 'NWNWN'];
    $narrow = 1;
    $wide   = 3;

    $totalUnits = 4; // Startzeichen
    for ($i = 0; $i < strlen($nummer); $i += 2) {
        $p1 = $patterns[(int)$nummer[$i]];
        $p2 = $patterns[(int)$nummer[$i + 1]];
        for ($j = 0; $j < 5; $j++) {
            $totalUnits += ($p1[$j] === 'W' ? $wide : $narrow);
            $totalUnits += ($p2[$j] === 'W' ? $wide : $narrow);
        }
    }
    $totalUnits += $wide + $narrow + $narrow; // Stoppzeichen

    $unitWidth = $imgWidth / $totalUnits;
    $img   = imagecreatetruecolor($imgWidth, $imgHeight);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefill($img, 0, 0, $white);

    $pos = 0.0;
    $drawBar = function ($units) use ($img, $black, $unitWidth, $imgHeight, &$pos) {
        $x1 = (int)round($pos);
        $x2 = (int)round($pos + $units * $unitWidth) - 1;
        imagefilledrectangle($img, $x1, 0, $x2, $imgHeight - 1, $black);
        $pos += $units * $unitWidth;
    };
    $drawSpace = function ($units) use ($unitWidth, &$pos) {
        $pos += $units * $unitWidth;
    };

    $drawBar($narrow); $drawSpace($narrow); $drawBar($narrow); $drawSpace($narrow);
    for ($i = 0; $i < strlen($nummer); $i += 2) {
        $bars   = $patterns[(int)$nummer[$i]];
        $spaces = $patterns[(int)$nummer[$i + 1]];
        for ($j = 0; $j < 5; $j++) {
            $drawBar($bars[$j] === 'W' ? $wide : $narrow);
            $drawSpace($spaces[$j] === 'W' ? $wide : $narrow);
        }
    }
    $drawBar($wide); $drawSpace($narrow); $drawBar($narrow);

    $ext = $format === 'jpg' ? '.jpg' : '.png';
    $tmpFile = tempnam(sys_get_temp_dir(), 'barcode_');
    rename($tmpFile, $tmpFile . $ext); $tmpFile .= $ext; // Stub umbenennen statt liegen lassen
    if ($ext === '.jpg') {
        imagejpeg($img, $tmpFile, 90);
    } else {
        imagepng($img, $tmpFile);
    }
    imagedestroy($img);
    return $tmpFile;
}

/**
 * Befuellt die Vorlage fuer ein Mitglied und liefert den Pfad der temporaeren DOCX-Datei.
 * Der Aufrufer loescht die Datei nach Gebrauch.
 *
 * @throws RuntimeException wenn die Vorlage fehlt
 */
function sb_standblatt_docx(int $lizenz, string $vorname, string $name, int $jahr, string $barcodeFormat = 'png'): string
{
    $templatePath = sb_template_path();
    if (!file_exists($templatePath)) {
        throw new RuntimeException('Vorlage nicht gefunden: ' . basename($templatePath));
    }

    $barcodeImg = null;
    $barcode = sb_barcode_nummer((string)$lizenz);
    if ($barcode !== '') {
        $barcodeImg = sb_barcode_image($barcode, $barcodeFormat);
    }

    try {
        $template = new TemplateProcessor($templatePath);
        $template->setValue('year', (string)$jahr);
        $template->setValue('name', trim($vorname . ' ' . $name));
        if ($barcodeImg) {
            $template->setImageValue('lizenz', ['path' => $barcodeImg, 'width' => 150, 'height' => 30]);
        } else {
            $template->setValue('lizenz', '');
        }

        $tmpDocx = tempnam(sys_get_temp_dir(), 'standblatt_');
        rename($tmpDocx, $tmpDocx . '.docx'); $tmpDocx .= '.docx';
        $template->saveAs($tmpDocx);
        return $tmpDocx;
    } finally {
        if ($barcodeImg && file_exists($barcodeImg)) unlink($barcodeImg);
    }
}

/**
 * Download-Dateiname ohne Umlaute/Sonderzeichen (Content-Disposition vertraegt weder
 * Anfuehrungszeichen noch rohe UTF-8-Zeichen zuverlaessig).
 */
function sb_dateiname(string $vorname, string $name, int $jahr, string $ext): string
{
    $map = ['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue','ß'=>'ss',
            'é'=>'e','è'=>'e','ê'=>'e','à'=>'a','â'=>'a','ç'=>'c','ô'=>'o','î'=>'i','ï'=>'i','É'=>'E','È'=>'E'];
    $raw  = strtr($vorname . $name, $map);
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $raw) ?: 'Mitglied';
    return 'JM_Standblatt_' . $jahr . '_' . $safe . '.' . $ext;
}
