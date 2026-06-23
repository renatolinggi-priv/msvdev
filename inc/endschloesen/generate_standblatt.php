<?php
/**
 * Generiert ein Endschiessen-Standblatt aus der Excel-Vorlage.
 * GET-Parameter: jahr, mitglied_id (oder gast_name), stiche (kommasepariert)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../dbconnect.inc.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

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

    $tmpFile = tempnam(sys_get_temp_dir(), 'barcode_') . '.png';
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
];

foreach ($codeToPlaceholder as $code => $placeholder) {
    $replacements[$placeholder] = in_array($code, $geloeste) ? 'gelöst' : '';
}

// --- Excel-Vorlage laden und Platzhalter ersetzen ---
$templatePath = __DIR__ . '/Vorlage/Standblatt_Endschiessen_Vorlage.xlsx';

if (!file_exists($templatePath)) {
    http_response_code(404);
    echo 'Vorlage nicht gefunden';
    exit;
}

$spreadsheet = IOFactory::load($templatePath);

foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
    foreach ($sheet->getRowIterator() as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(true);
        foreach ($cellIterator as $cell) {
            $val = $cell->getValue();
            if (!is_string($val) || strpos($val, '${') === false) continue;

            // Barcode-Platzhalter → Bild einfügen
            if (strpos($val, '${lizenz}') !== false) {
                $cell->setValue(str_replace('${lizenz}', '', $val));
                if ($barcodePng) {
                    $drawing = new Drawing();
                    $drawing->setPath($barcodePng);
                    $drawing->setCoordinates($cell->getCoordinate());
                    $drawing->setHeight(40);
                    $drawing->setOffsetX(2);
                    $drawing->setOffsetY(2);
                    $drawing->setWorksheet($sheet);
                }
                continue;
            }

            // Normale Text-Platzhalter
            foreach ($replacements as $key => $replacement) {
                $val = str_replace('${' . $key . '}', $replacement, $val);
            }
            $cell->setValue($val);
        }
    }
}

// --- Download ausgeben ---
$filename = "Endschiessen_{$jahr}_{$vorname}{$nachname}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');

// Temporäre Barcode-Datei aufräumen
if ($barcodePng && file_exists($barcodePng)) {
    unlink($barcodePng);
}
exit;
