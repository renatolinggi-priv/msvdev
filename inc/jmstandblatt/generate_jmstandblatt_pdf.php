<?php
/**
 * Generiert ein JM-Standblatt als PDF (fuer QZ Tray Direktdruck).
 * Ablauf: DOCX-Vorlage befuellen → ConvertAPI → PDF ausgeben.
 * GET-Parameter: jahr, mitglied_id
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../lib/convertapi_helper.php';

use PhpOffice\PhpWord\TemplateProcessor;

// --- Parameter ---
$jahr = intval($_GET['jahr'] ?? date('Y'));
$mitglied_id = intval($_GET['mitglied_id'] ?? 0);

if ($mitglied_id <= 0 || !isset($conn)) {
    http_response_code(400);
    echo 'Ungueltige Parameter';
    exit;
}

// --- Mitglied-Daten laden ---
$stmt = $conn->prepare("SELECT Vorname, Name, ID FROM mitglieder WHERE ID = ?");
$stmt->bind_param('i', $mitglied_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo 'Mitglied nicht gefunden';
    exit;
}

$vorname = $row['Vorname'];
$nachname = $row['Name'];
$lizenznr = (string) $row['ID'];

// --- Barcode ---
function ssvBarcodeNummer(string $lnr): string {
    if ($lnr === '' || !ctype_digit($lnr)) return '';
    if (strlen($lnr) === 6) $lnr = '10' . $lnr;
    if (strlen($lnr) !== 8) return '';
    $rest = bcmod($lnr . '00', '97');
    $crc = 97 - intval($rest);
    return $lnr . str_pad($crc, 2, '0', STR_PAD_LEFT);
}

function generateItfBarcodePng(string $nummer, int $imgWidth = 280, int $imgHeight = 60): ?string {
    if ($nummer === '' || strlen($nummer) % 2 !== 0) return null;

    $patterns = [
        'NNWWN', 'WNNNW', 'NWNNW', 'WWNNN', 'NNWNW',
        'WNWNN', 'NWWNN', 'NNNWW', 'WNNWN', 'NWNWN'
    ];
    $narrow = 1; $wide = 3;

    $totalUnits = 4;
    for ($i = 0; $i < strlen($nummer); $i += 2) {
        $p1 = $patterns[(int)$nummer[$i]];
        $p2 = $patterns[(int)$nummer[$i + 1]];
        for ($j = 0; $j < 5; $j++) {
            $totalUnits += ($p1[$j] === 'W' ? $wide : $narrow);
            $totalUnits += ($p2[$j] === 'W' ? $wide : $narrow);
        }
    }
    $totalUnits += $wide + $narrow + $narrow;

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

    $drawBar($narrow); $drawSpace($narrow); $drawBar($narrow); $drawSpace($narrow);
    for ($i = 0; $i < strlen($nummer); $i += 2) {
        $bars = $patterns[(int)$nummer[$i]];
        $spaces = $patterns[(int)$nummer[$i + 1]];
        for ($j = 0; $j < 5; $j++) {
            $drawBar($bars[$j] === 'W' ? $wide : $narrow);
            $drawSpace($spaces[$j] === 'W' ? $wide : $narrow);
        }
    }
    $drawBar($wide); $drawSpace($narrow); $drawBar($narrow);

    $tmpFile = tempnam(sys_get_temp_dir(), 'barcode_') . '.jpg';
    imagejpeg($img, $tmpFile, 90);
    imagedestroy($img);
    return $tmpFile;
}

$barcode = ssvBarcodeNummer($lizenznr);
$barcodeImg = ($barcode !== '') ? generateItfBarcodePng($barcode) : null;

// --- Vorlage laden und befuellen ---
$templatePath = __DIR__ . '/Vorlage/MSVStandblatHeim-KantVorlage.docx';

if (!file_exists($templatePath)) {
    http_response_code(404);
    echo 'Vorlage nicht gefunden';
    exit;
}

$template = new TemplateProcessor($templatePath);
$template->setValue('year', (string) $jahr);
$template->setValue('name', trim($vorname . ' ' . $nachname));

if ($barcodeImg) {
    $template->setImageValue('lizenz', [
        'path'   => $barcodeImg,
        'width'  => 150,
        'height' => 30,
    ]);
} else {
    $template->setValue('lizenz', '');
}

// --- DOCX speichern → ConvertAPI → PDF ---
$tmpDocx = tempnam(sys_get_temp_dir(), 'standblatt_') . '.docx';
$template->saveAs($tmpDocx);

$tmpPdf = null;
try {
    $tmpPdf = convertDocxToPdf($tmpDocx);

    $filename = "JM_Standblatt_{$jahr}_{$vorname}{$nachname}.pdf";
    header('Content-Type: application/pdf');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Content-Length: ' . filesize($tmpPdf));
    header('Cache-Control: max-age=0');
    readfile($tmpPdf);

} catch (Exception $e) {
    error_log('[jmstandblatt_pdf] Fehler: ' . $e->getMessage());
    http_response_code(500);
    echo 'PDF-Konvertierung fehlgeschlagen: ' . $e->getMessage();
} finally {
    if ($tmpPdf && file_exists($tmpPdf)) unlink($tmpPdf);
    if (file_exists($tmpDocx)) unlink($tmpDocx);
    if ($barcodeImg && file_exists($barcodeImg)) unlink($barcodeImg);
}
exit;
