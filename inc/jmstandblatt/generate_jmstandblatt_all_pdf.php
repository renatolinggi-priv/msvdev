<?php
/**
 * Generiert ein kombiniertes PDF mit allen JM-Standblaettern (aktive Mitglieder).
 * Einzelne DOCXs werden via ConvertAPI zu PDF konvertiert und mit FPDI zusammengefuegt.
 * GET-Parameter: jahr
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(600);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../lib/convertapi_helper.php';

use PhpOffice\PhpWord\TemplateProcessor;
use setasign\Fpdi\Fpdi;

// --- Parameter ---
$jahr = intval($_GET['jahr'] ?? date('Y'));

if (!isset($conn)) {
    http_response_code(500);
    echo 'Datenbankverbindung fehlt';
    exit;
}

// --- Alle aktiven Mitglieder laden ---
$sql = "SELECT ID, Vorname, Name FROM mitglieder WHERE Status = 1 AND Verstorben = 0 ORDER BY Name, Vorname";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    echo 'Keine aktiven Mitglieder gefunden';
    exit;
}

$mitglieder = [];
while ($row = $result->fetch_assoc()) {
    $mitglieder[] = $row;
}

// --- Barcode-Funktionen ---
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

/**
 * Generiert ein einzelnes Standblatt-PDF via ConvertAPI.
 */
function generateSinglePdf(mysqli $conn, int $mitglied_id, string $vorname, string $nachname, int $jahr, string $templatePath): ?string {
    $lizenznr = (string) $mitglied_id;
    $barcode = ssvBarcodeNummer($lizenznr);
    $barcodeImg = ($barcode !== '') ? generateItfBarcodePng($barcode) : null;

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

    $tmpDocx = tempnam(sys_get_temp_dir(), 'sb_') . '.docx';
    $template->saveAs($tmpDocx);

    $tmpPdf = null;
    try {
        $tmpPdf = convertDocxToPdf($tmpDocx);
    } catch (\Exception $e) {
        error_log("[jmstandblatt_all_pdf] Fehler bei {$vorname} {$nachname}: " . $e->getMessage());
        $tmpPdf = null;
    } finally {
        if (file_exists($tmpDocx)) unlink($tmpDocx);
        if ($barcodeImg && file_exists($barcodeImg)) unlink($barcodeImg);
    }

    return $tmpPdf;
}

// --- Alle Einzel-PDFs generieren ---
$templatePath = __DIR__ . '/Vorlage/MSVStandblatHeim-KantVorlage.docx';
if (!file_exists($templatePath)) {
    http_response_code(404);
    echo 'Vorlage nicht gefunden';
    exit;
}

$pdfFiles = [];
$errors = [];

foreach ($mitglieder as $m) {
    $pdf = generateSinglePdf($conn, (int)$m['ID'], $m['Vorname'], $m['Name'], $jahr, $templatePath);
    if ($pdf) {
        $pdfFiles[] = $pdf;
    } else {
        $errors[] = $m['Vorname'] . ' ' . $m['Name'];
    }
}

if (empty($pdfFiles)) {
    http_response_code(500);
    echo 'Keine PDFs konnten generiert werden';
    exit;
}

// --- PDFs zusammenfuegen mit FPDI ---
try {
    $merger = new Fpdi();
    $merger->SetAutoPageBreak(false);

    foreach ($pdfFiles as $pdfFile) {
        $pageCount = $merger->setSourceFile($pdfFile);
        for ($p = 1; $p <= $pageCount; $p++) {
            $tplId = $merger->importPage($p);
            $size = $merger->getTemplateSize($tplId);
            $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $merger->useTemplate($tplId);
        }
    }

    $mergedPdf = tempnam(sys_get_temp_dir(), 'merged_') . '.pdf';
    $merger->Output('F', $mergedPdf);

    $filename = "JM_Standblaetter_{$jahr}_alle.pdf";
    header('Content-Type: application/pdf');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Content-Length: ' . filesize($mergedPdf));
    header('Cache-Control: max-age=0');

    if (!empty($errors)) {
        header('X-Skipped: ' . count($errors));
    }

    readfile($mergedPdf);
    unlink($mergedPdf);

} catch (\Exception $e) {
    error_log('[jmstandblatt_all_pdf] Merge-Fehler: ' . $e->getMessage());
    http_response_code(500);
    echo 'PDF-Zusammenfuehrung fehlgeschlagen: ' . $e->getMessage();
} finally {
    foreach ($pdfFiles as $f) {
        if (file_exists($f)) unlink($f);
    }
}
exit;
