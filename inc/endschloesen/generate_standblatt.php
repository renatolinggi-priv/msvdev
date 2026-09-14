<?php
/**
 * Generiert ein Endschiessen-Standblatt aus der Excel-Vorlage.
 * GET-Parameter: jahr, mitglied_id (oder gast_name), stiche (kommasepariert),
 *                waffen_id (optional; sonst gespeicherte Lösung bzw. Stammdaten),
 *                format=pdf (optional; XLSX via ConvertAPI zu PDF für den QZ-Tray-Direktdruck)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('plain'); // Zugriff nur Admin-Bereich (admin/vorstand)

// --- Parameter ---
$jahr = intval($_GET['jahr'] ?? date('Y'));
$mitglied_id = intval($_GET['mitglied_id'] ?? 0);
$gast_name = trim($_GET['gast_name'] ?? '');
$sticheCsv = trim($_GET['stiche'] ?? '');
$geloeste = $sticheCsv !== '' ? array_map('trim', explode(',', $sticheCsv)) : [];

// --- Mitglied-Daten laden ---
$vorname = '';
$nachname = '';
$lizenznr = '';

if ($mitglied_id > 0 && isset($conn)) {
    $stmt = $conn->prepare("SELECT Vorname, Name, ID FROM mitglieder WHERE ID = ?");
    $stmt->bind_param('i', $mitglied_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $vorname = $row['Vorname'];
        $nachname = $row['Name'];
        $lizenznr = (string) $row['ID'];
    }
    $stmt->close();
} elseif ($gast_name !== '') {
    // Gast: Name splitten (Format "Vorname Nachname" oder einfach den ganzen String)
    $parts = explode(' ', $gast_name, 2);
    $vorname = $parts[0];
    $nachname = $parts[1] ?? '';
}

// --- Barcode-Nummer (nur für Mitglieder) ---
function ssvBarcodeNummer(string $lnr): string {
    if ($lnr === '' || !ctype_digit($lnr)) return '';
    if (strlen($lnr) === 6) $lnr = '10' . $lnr;
    if (strlen($lnr) !== 8) return '';
    $rest = bcmod($lnr . '00', '97');
    $crc = 97 - intval($rest);
    return $lnr . str_pad($crc, 2, '0', STR_PAD_LEFT);
}

$barcode = ($mitglied_id > 0 && $lizenznr !== '') ? ssvBarcodeNummer($lizenznr) : '';

// --- Waffe (Platzhalter ${waffe}) ---
// Vorrang: 1. waffen_id aus dem Formular, 2. bei der Lösung gespeicherte Waffe
// (endstich_selection.waffen_id, Migration 047), 3. Stammdaten (mitglieder.WaffenID
// bzw. endstich_gaeste.waffen_id).
$waffenId = intval($_GET['waffen_id'] ?? 0);
$waffeBez = '';
if (isset($conn)) {
    $einWert = static function (string $sql, string $types, array $params) use ($conn) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();
        return $row ? $row[0] : null;
    };
    if ($waffenId <= 0) {
        $res = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'waffen_id'");
        $hatWaffeSpalte = $res ? $res->num_rows > 0 : false;
        if ($mitglied_id > 0) {
            if ($hatWaffeSpalte) {
                $waffenId = (int) $einWert("SELECT waffen_id FROM endstich_selection WHERE mitglied_id = ? AND jahr = ? AND waffen_id IS NOT NULL LIMIT 1", 'ii', [$mitglied_id, $jahr]);
            }
            if ($waffenId <= 0) {
                $waffenId = (int) $einWert("SELECT WaffenID FROM mitglieder WHERE ID = ?", 'i', [$mitglied_id]);
            }
        } elseif ($gast_name !== '') {
            $gastId = (int) $einWert("SELECT id FROM endstich_gaeste WHERE name = ? AND jahr = ?", 'si', [$gast_name, $jahr]);
            if ($gastId > 0 && $hatWaffeSpalte) {
                $waffenId = (int) $einWert("SELECT waffen_id FROM endstich_selection WHERE gast_id = ? AND jahr = ? AND waffen_id IS NOT NULL LIMIT 1", 'ii', [$gastId, $jahr]);
            }
            if ($gastId > 0 && $waffenId <= 0) {
                $waffenId = (int) $einWert("SELECT waffen_id FROM endstich_gaeste WHERE id = ?", 'i', [$gastId]);
            }
        }
    }
    if ($waffenId > 0) {
        $waffeBez = (string) ($einWert("SELECT Bezeichnung FROM Waffen WHERE ID = ?", 'i', [$waffenId]) ?? '');
    }
}

// --- ITF Barcode als PNG generieren ---
function generateItfBarcodePng(string $nummer, int $imgWidth = 280, int $imgHeight = 60): ?string {
    if ($nummer === '' || strlen($nummer) % 2 !== 0) return null;

    $patterns = [
        'NNWWN', 'WNNNW', 'NWNNW', 'WWNNN', 'NNWNW',
        'WNWNN', 'NWWNN', 'NNNWW', 'WNNWN', 'NWNWN'
    ];
    $narrow = 1;
    $wide = 3;

    // Gesamtbreite in Einheiten berechnen
    $totalUnits = 4; // Start: NNNN
    for ($i = 0; $i < strlen($nummer); $i += 2) {
        $p1 = $patterns[(int)$nummer[$i]];
        $p2 = $patterns[(int)$nummer[$i + 1]];
        for ($j = 0; $j < 5; $j++) {
            $totalUnits += ($p1[$j] === 'W' ? $wide : $narrow);
            $totalUnits += ($p2[$j] === 'W' ? $wide : $narrow);
        }
    }
    $totalUnits += $wide + $narrow + $narrow; // Stop: WNN

    $unitWidth = $imgWidth / $totalUnits;
    $img = imagecreatetruecolor($imgWidth, $imgHeight);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefill($img, 0, 0, $white);

    $pos = 0.0;
    $drawBar = function($units) use ($img, $black, $unitWidth, $imgHeight, &$pos) {
        $x1 = (int)round($pos);
        $x2 = (int)round($pos + $units * $unitWidth) - 1;
        imagefilledrectangle($img, $x1, 0, $x2, $imgHeight - 1, $black);
        $pos += $units * $unitWidth;
    };
    $drawSpace = function($units) use ($unitWidth, &$pos) {
        $pos += $units * $unitWidth;
    };

    // Start: NNNN
    $drawBar($narrow); $drawSpace($narrow); $drawBar($narrow); $drawSpace($narrow);

    // Daten
    for ($i = 0; $i < strlen($nummer); $i += 2) {
        $bars = $patterns[(int)$nummer[$i]];
        $spaces = $patterns[(int)$nummer[$i + 1]];
        for ($j = 0; $j < 5; $j++) {
            $drawBar($bars[$j] === 'W' ? $wide : $narrow);
            $drawSpace($spaces[$j] === 'W' ? $wide : $narrow);
        }
    }

    // Stop: WNN
    $drawBar($wide); $drawSpace($narrow); $drawBar($narrow);

    $tmpFile = tempnam(sys_get_temp_dir(), 'barcode_');
    rename($tmpFile, $tmpFile . '.png'); $tmpFile .= '.png'; // Stub umbenennen statt liegen lassen
    imagepng($img, $tmpFile);
    imagedestroy($img);
    return $tmpFile;
}

$barcodePng = ($barcode !== '') ? generateItfBarcodePng($barcode) : null;

// --- Platzhalter-Map ---
$codeToPlaceholder = [
    'END'        => 'endgeloest',
    'ZABIG'      => 'zabiggeloest',
    'SCHWINI_P1' => 'schwini1geloest',
    'SCHWINI_P2' => 'schwini2geloest',
    'KUNST'      => 'kunstgeloest',
    'DIFF'       => 'difgeloest',
    'SIEUNDER'   => 'sieergeloest',
    'GLUECK'     => 'glueckgeloest',
];

$replacements = [
    'year'   => (string) $jahr,
    'name'   => trim($vorname . ' ' . $nachname),
    'waffe'  => $waffeBez,
];

foreach ($codeToPlaceholder as $code => $placeholder) {
    $replacements[$placeholder] = in_array($code, $geloeste) ? 'gelöst' : '';
}

// --- Excel-Vorlage laden und Platzhalter ersetzen ---
// Bewusst OHNE PhpSpreadsheet: die Bibliothek kann Textfelder/Autoformen nicht
// einlesen und wirft sie beim Speichern weg (Titel "Endschiessen MSV Wilen ${year}"
// und die "Stichnr."-Beschriftungen liegen in der Vorlage als Textfelder vor).
// Darum wird die XLSX als Zip geöffnet und die XML-Teile werden direkt bearbeitet:
//   - xl/sharedStrings.xml   → Platzhalter in Zellen
//   - xl/drawings/drawing1.xml → Platzhalter in Textfeldern
//   - Barcode als zusätzliches Bild im Drawing verankert (an der ${lizenz}-Zelle)
$templatePath = __DIR__ . '/Vorlage/Standblatt_Endschiessen_Vorlage.xlsx';

if (!file_exists($templatePath)) {
    http_response_code(404);
    echo 'Vorlage nicht gefunden';
    exit;
}

$xmlEsc = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

/** Ersetzt alle ${key}-Platzhalter in einem XML-String (Werte XML-escaped). */
$ersetzePlatzhalter = static function (string $xml, array $map) use ($xmlEsc): string {
    foreach ($map as $key => $wert) {
        $xml = str_replace('${' . $key . '}', $xmlEsc((string) $wert), $xml);
    }
    return $xml;
};

/** Spaltenbuchstaben (A, B, …, AA) → 0-basierter Index */
$spalteZuIndex = static function (string $col): int {
    $n = 0;
    foreach (str_split(strtoupper($col)) as $ch) {
        $n = $n * 26 + (ord($ch) - 64);
    }
    return $n - 1;
};

$tmpXlsx = tempnam(sys_get_temp_dir(), 'standblatt_');
rename($tmpXlsx, $tmpXlsx . '.xlsx'); $tmpXlsx .= '.xlsx'; // Stub umbenennen statt liegen lassen
if (!copy($templatePath, $tmpXlsx)) {
    http_response_code(500);
    echo 'Vorlage konnte nicht kopiert werden';
    exit;
}

$zip = new ZipArchive();
if ($zip->open($tmpXlsx) !== true) {
    @unlink($tmpXlsx);
    http_response_code(500);
    echo 'Vorlage konnte nicht geöffnet werden';
    exit;
}

// 1) Zell-Texte (sharedStrings): Position der ${lizenz}-Zelle merken, dann ersetzen
$sharedStrings = (string) $zip->getFromName('xl/sharedStrings.xml');
$lizenzIndex = -1;
if (preg_match_all('#<si>.*?</si>#s', $sharedStrings, $siMatches)) {
    foreach ($siMatches[0] as $idx => $si) {
        if (strpos($si, '${lizenz}') !== false) { $lizenzIndex = $idx; break; }
    }
}
$zellMap = $replacements + ['lizenz' => ''];
$zip->addFromString('xl/sharedStrings.xml', $ersetzePlatzhalter($sharedStrings, $zellMap));

// 2) Textfelder (Drawings): alle drawing*.xml durchgehen
$drawingNamen = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $n = $zip->getNameIndex($i);
    if (preg_match('#^xl/drawings/drawing\d+\.xml$#', $n)) $drawingNamen[] = $n;
}
// PITFALL ZipArchive: getFromName() liefert für einen per addFromString() ersetzten,
// noch nicht geschriebenen Eintrag einen leeren Inhalt. Darum alle XML-Teile im
// Speicher halten und jeden Eintrag erst am Ende genau einmal schreiben.
$drawings = [];
foreach ($drawingNamen as $dn) {
    $drawings[$dn] = $ersetzePlatzhalter((string) $zip->getFromName($dn), $replacements);
}

// 3) Barcode-Bild an der ${lizenz}-Zelle verankern (Blatt 1, drawing1.xml)
if ($barcodePng && $lizenzIndex >= 0 && isset($drawings['xl/drawings/drawing1.xml'])) {
    $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    if (preg_match('#<c r="([A-Z]+)(\d+)"[^>]*\bt="s"[^>]*>\s*<v>' . $lizenzIndex . '</v>#', $sheetXml, $cm)) {
        $col = $spalteZuIndex($cm[1]);
        $row = (int) $cm[2] - 1;

        // Bildgrösse: Höhe 40px, Breite proportional; 1px = 9525 EMU
        [$pxW, $pxH] = getimagesize($barcodePng) ?: [280, 60];
        $cy = 40 * 9525;
        $cx = (int) round($pxW * 40 / max(1, $pxH) * 9525);
        $off = 2 * 9525;

        $relId = 'rIdBarcode';
        $relsName = 'xl/drawings/_rels/drawing1.xml.rels';
        $rels = (string) $zip->getFromName($relsName);
        $rels = str_replace(
            '</Relationships>',
            '<Relationship Id="' . $relId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/barcode_lizenz.png"/></Relationships>',
            $rels
        );
        $zip->addFromString($relsName, $rels);
        $zip->addFromString('xl/media/barcode_lizenz.png', (string) file_get_contents($barcodePng));

        $anchor = '<xdr:oneCellAnchor>'
            . '<xdr:from><xdr:col>' . $col . '</xdr:col><xdr:colOff>' . $off . '</xdr:colOff>'
            . '<xdr:row>' . $row . '</xdr:row><xdr:rowOff>' . $off . '</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="9001" name="Barcode Lizenz"/>'
            . '<xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="' . $relId . '"/>'
            . '<a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic>'
            . '<xdr:clientData/></xdr:oneCellAnchor>';
        $drawings['xl/drawings/drawing1.xml'] = str_replace('</xdr:wsDr>', $anchor . '</xdr:wsDr>', $drawings['xl/drawings/drawing1.xml']);
    }
}

foreach ($drawings as $dn => $xml) {
    $zip->addFromString($dn, $xml);
}

$zip->close();

// --- Ausgabe: XLSX (Download) oder PDF (Direktdruck via QZ Tray) ---
$alsPdf = (($_GET['format'] ?? '') === 'pdf');
$basisname = "Endschiessen_{$jahr}_{$vorname}{$nachname}";
$tmpPdf = null;

try {
    if ($alsPdf) {
        // XLSX → PDF über ConvertAPI (kostenpflichtiger Dienst, Kontingent beachten);
        // Seiteneinrichtung der Vorlage (A4 quer, Druckbereich) wird übernommen.
        require_once __DIR__ . '/../lib/convertapi_helper.php';
        $tmpPdf = convertToPdf($tmpXlsx, 'xlsx');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $basisname . '.pdf"');
        header('Cache-Control: max-age=0');
        header('Content-Length: ' . filesize($tmpPdf));
        readfile($tmpPdf);
    } else {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $basisname . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Content-Length: ' . filesize($tmpXlsx));
        readfile($tmpXlsx);
    }
} catch (Throwable $e) {
    error_log('[generate_standblatt] ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PDF-Konvertierung fehlgeschlagen: ' . $e->getMessage();
} finally {
    // Temporäre Dateien aufräumen
    if ($tmpPdf && file_exists($tmpPdf)) @unlink($tmpPdf);
    @unlink($tmpXlsx);
    if ($barcodePng && file_exists($barcodePng)) {
        unlink($barcodePng);
    }
}
exit;
