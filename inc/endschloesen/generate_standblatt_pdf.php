<?php
/**
 * Generiert ein Endschiessen-Standblatt als PDF — direkt via DomPDF (kein Excel-Umweg).
 * GET-Parameter: jahr, mitglied_id (oder gast_name), stiche (kommasepariert)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../dbconnect.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

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
    $parts = explode(' ', $gast_name, 2);
    $vorname = $parts[0];
    $nachname = $parts[1] ?? '';
}

$fullName = trim($vorname . ' ' . $nachname);

// --- Stich-Definitionen aus DB laden ---
$stichDefs = [];
$sql = "SELECT code, name, shots FROM endstich_definition WHERE active = 1 ORDER BY sort_order, name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $stichDefs[] = $row;
    }
}

// Fallback falls DB leer
if (empty($stichDefs)) {
    $stichDefs = [
        ['code' => 'END',        'name' => 'Endstich',      'shots' => 20],
        ['code' => 'SCHWINI_P1', 'name' => 'Schwini P1',    'shots' => 6],
        ['code' => 'SCHWINI_P2', 'name' => 'Schwini P2',    'shots' => 6],
        ['code' => 'ZABIG',      'name' => 'Zabig',         'shots' => 6],
        ['code' => 'KUNST',      'name' => 'Kunststich',    'shots' => 10],
        ['code' => 'DIFF',       'name' => 'Differenz',     'shots' => 10],
        ['code' => 'SIEUNDER',   'name' => 'Sie und Er',    'shots' => 10],
        ['code' => 'GLUECK',     'name' => 'Glücksstich',   'shots' => 5],
    ];
}

// --- Barcode als Base64 Data-URI ---
function ssvBarcodeNummer(string $lnr): string {
    if ($lnr === '' || !ctype_digit($lnr)) return '';
    if (strlen($lnr) === 6) $lnr = '10' . $lnr;
    if (strlen($lnr) !== 8) return '';
    $rest = bcmod($lnr . '00', '97');
    $crc = 97 - intval($rest);
    return $lnr . str_pad($crc, 2, '0', STR_PAD_LEFT);
}

function generateBarcodeBase64(string $nummer, int $imgWidth = 280, int $imgHeight = 50): string {
    if ($nummer === '' || strlen($nummer) % 2 !== 0) return '';

    $patterns = [
        'NNWWN', 'WNNNW', 'NWNNW', 'WWNNN', 'NNWNW',
        'WNWNN', 'NWWNN', 'NNNWW', 'WNNWN', 'NWNWN'
    ];
    $narrow = 1;
    $wide = 3;

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

    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);

    return 'data:image/png;base64,' . base64_encode($data);
}

$barcode = ($mitglied_id > 0 && $lizenznr !== '') ? ssvBarcodeNummer($lizenznr) : '';
$barcodeDataUri = ($barcode !== '') ? generateBarcodeBase64($barcode) : '';

// --- Stich-Tabelle HTML ---
$stichRows = '';
foreach ($stichDefs as $stich) {
    $isGeloest = in_array($stich['code'], $geloeste);
    $statusHtml = $isGeloest
        ? '<span style="color:#198754;font-weight:bold;font-size:14px;">&#10004; gelöst</span>'
        : '<span style="color:#aaa;">–</span>';

    // Schussfelder generieren
    $shots = intval($stich['shots']);
    $schussHtml = '';
    for ($i = 1; $i <= $shots; $i++) {
        $schussHtml .= '<td style="width:22px;height:22px;border:1px solid #999;text-align:center;font-size:9px;color:#ccc;">' . $i . '</td>';
    }

    $stichRows .= '
    <tr>
        <td colspan="' . ($shots + 2) . '" style="background:#f0f0f0;font-weight:bold;font-size:11px;padding:6px 8px;border:1px solid #999;">
            ' . htmlspecialchars($stich['name']) . '
            <span style="float:right;">' . $statusHtml . '</span>
        </td>
    </tr>
    <tr>' . $schussHtml . '<td style="width:40px;border:1px solid #999;text-align:center;font-size:8px;font-weight:bold;">Total</td>
        <td style="width:50px;border:1px solid #999;text-align:center;font-size:8px;">Punkte</td>
    </tr>';
}

// --- Barcode HTML ---
$barcodeHtml = '';
if ($barcodeDataUri) {
    $barcodeHtml = '<div style="text-align:right;">
        <img src="' . $barcodeDataUri . '" style="height:35px;" />
        <div style="font-size:8px;color:#666;margin-top:2px;">Lizenz-Nr. ' . htmlspecialchars($lizenznr) . '</div>
    </div>';
}

// --- HTML zusammenbauen ---
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 15mm 12mm 12mm 12mm; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #222; margin: 0; padding: 0; }
    .header { display: table; width: 100%; margin-bottom: 12px; }
    .header-left { display: table-cell; vertical-align: top; width: 60%; }
    .header-right { display: table-cell; vertical-align: top; width: 40%; text-align: right; }
    h1 { font-size: 18px; margin: 0 0 2px 0; }
    h2 { font-size: 13px; font-weight: normal; color: #555; margin: 0 0 8px 0; }
    .info-table { border-collapse: collapse; margin-bottom: 15px; }
    .info-table td { padding: 3px 12px 3px 0; font-size: 10px; }
    .info-table .label { font-weight: bold; color: #555; min-width: 60px; }
    .stich-block { margin-bottom: 10px; }
    .stich-table { border-collapse: collapse; }
    .footer { margin-top: 20px; border-top: 1px solid #ccc; padding-top: 8px; font-size: 8px; color: #888; }
</style>
</head>
<body>

<div class="header">
    <div class="header-left">
        <h1>Endschiessen {$jahr}</h1>
        <h2>MSV Wilen — Standblatt</h2>
    </div>
    <div class="header-right">
        {$barcodeHtml}
    </div>
</div>

<table class="info-table">
    <tr>
        <td class="label">Name:</td>
        <td style="font-size:13px;font-weight:bold;">{$fullName}</td>
    </tr>
HTML;

if ($lizenznr) {
    $html .= '<tr><td class="label">Lizenz:</td><td>' . htmlspecialchars($lizenznr) . '</td></tr>';
}

$html .= '</table>';

// Stich-Blöcke
foreach ($stichDefs as $stich) {
    $isGeloest = in_array($stich['code'], $geloeste);
    $statusHtml = $isGeloest
        ? '<span style="color:#198754;font-weight:bold;">&#10004; gelöst</span>'
        : '';
    $shots = intval($stich['shots']);

    $html .= '<div class="stich-block">';
    $html .= '<table class="stich-table">';

    // Header-Zeile
    $html .= '<tr><td colspan="' . ($shots + 2) . '" style="background:#e9ecef;font-weight:bold;font-size:10px;padding:4px 6px;border:1px solid #aaa;">';
    $html .= htmlspecialchars($stich['name']) . ' (' . $shots . ' Schuss)';
    $html .= '<span style="float:right;">' . $statusHtml . '</span>';
    $html .= '</td></tr>';

    // Nummern-Zeile
    $html .= '<tr>';
    for ($i = 1; $i <= $shots; $i++) {
        $html .= '<td style="width:20px;height:10px;border:1px solid #aaa;text-align:center;font-size:7px;color:#999;background:#fafafa;">' . $i . '</td>';
    }
    $html .= '<td style="width:35px;border:1px solid #aaa;text-align:center;font-size:7px;background:#fafafa;">Total</td>';
    $html .= '<td style="width:45px;border:1px solid #aaa;text-align:center;font-size:7px;background:#fafafa;">Punkte</td>';
    $html .= '</tr>';

    // Leere Eingabezeile
    $html .= '<tr>';
    for ($i = 1; $i <= $shots; $i++) {
        $html .= '<td style="width:20px;height:22px;border:1px solid #aaa;"></td>';
    }
    $html .= '<td style="width:35px;height:22px;border:1px solid #aaa;"></td>';
    $html .= '<td style="width:45px;height:22px;border:1px solid #aaa;"></td>';
    $html .= '</tr>';

    $html .= '</table></div>';
}

// Footer
$html .= '<div class="footer">Generiert am ' . date('d.m.Y H:i') . ' — MSV Wilen Endschiessen ' . $jahr . '</div>';
$html .= '</body></html>';

// --- PDF rendern ---
try {
    $options = new Options();
    $options->setIsFontSubsettingEnabled(true);
    $options->setDefaultFont('Helvetica');
    $options->setIsRemoteEnabled(true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = "Endschiessen_{$jahr}_{$vorname}{$nachname}.pdf";
    header('Content-Type: application/pdf');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');
    echo $dompdf->output();

} catch (Exception $e) {
    error_log('[endschloesen_standblatt_pdf] Fehler: ' . $e->getMessage());
    http_response_code(500);
    echo 'PDF-Generierung fehlgeschlagen: ' . $e->getMessage();
}
exit;
