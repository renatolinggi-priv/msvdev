<?php
include '../config.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$year = isset($_GET['year']) ? (int)$_GET['year'] : date("Y");

$sql = "
SELECT 
  h.ID,
  h.eventID, 
  h.helferWilen,
  h.helferWollerau,
  h.freierTitel,
  h.angeletAM,
  e.name AS eventName,
  e.date AS eventDate
FROM jungschuetzen_helfer h
LEFT JOIN wichtige_termine e ON h.eventID = e.ID
WHERE (e.date IS NULL OR YEAR(e.date) = ?) 
   OR (YEAR(h.angeletAM) = ?)
ORDER BY COALESCE(e.date, h.angeletAM)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $year, $year);
$stmt->execute();
$result = $stmt->get_result();

$eintraege = [];
$totalWilen = 0;
$totalWollerau = 0;

while ($row = $result->fetch_assoc()) {
    $bezeichnung = $row['eventName'] ?? $row['freierTitel'];
    $datum = $row['eventDate'] ?? $row['angeletAM'];
    $eventID = $row['eventID'] ?? null;
    $wilen = floatval($row['helferWilen']);
    $wollerau = floatval($row['helferWollerau']);

    // Logik: Event-Eintrag mit "Jungschützenkurs" → multiplizieren
    if ($eventID && stripos($bezeichnung, 'Jungschützenkurs') !== false) {
        $wilen *= 2.5;
        $wollerau *= 2.5;
    }

    $totalWilen += $wilen;
    $totalWollerau += $wollerau;

    $eintraege[] = [
        'datum' => $datum,
        'bezeichnung' => $bezeichnung,
        'helferWilen' => $wilen,
        'helferWollerau' => $wollerau
    ];
}

$stmt->close();
$conn->close();

$logoBase64 = imgToBase64('dat/MSVWilen_Logo.jpg');

// HTML aufbauen
$html = '
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Jungschützenkurs Helfereinsätze ' . $year . '</title>
  <style>
    @page { margin: 20px 20px 10px 20px; }
    body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; position: relative; }

    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .header-table td { border: none; vertical-align: middle; }
    .header-left { width: 120px; }
    .header-right { width: 120px; }
    .header-center { text-align: center; }
    .header-center h1 { margin: 0; }

    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #e2e8f0; padding: 6px; text-align: left; }
    th { background-color: #eef2f7; color: #2d3748; border-bottom: 2px solid #cbd5e0; }
    td.right { text-align: right; }

    .footer {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      height: 40px;
      border-top: 1px solid #cbd5e0;
      text-align: center;
      font-size: 10px;
      padding-top: 5px;
    }
  </style>
</head>
<body>

<table class="header-table">
  <tr>
    <td class="header-left"><img src="' . $logoBase64 . '" alt="Logo" style="width:100px;"></td>
    <td class="header-center"><h1>Helferstunden ' . $year . '</h1></td>
    <td class="header-right"></td>
  </tr>
</table>

<table>
  <tr>
    <th>Datum</th>
    <th>Bezeichnung</th>
    <th>Wilen</th>
    <th>Wollerau</th>
  </tr>';

foreach ($eintraege as $e) {
    $html .= '<tr>
        <td>' . date('d.m.Y', strtotime($e['datum'])) . '</td>
        <td>' . htmlspecialchars($e['bezeichnung']) . '</td>
        <td class="right">' . number_format($e['helferWilen'], 2, ',', '.') . '</td>
        <td class="right">' . number_format($e['helferWollerau'], 2, ',', '.') . '</td>
    </tr>';
}

// Summe
$html .= '<tr>
    <th colspan="2">Gesamtsumme</th>
    <th class="right">' . number_format($totalWilen, 2, ',', '.') . '</th>
    <th class="right">' . number_format($totalWollerau, 2, ',', '.') . '</th>
</tr>';

$html .= '
</table>

<div class="footer">
  Erstellt am ' . date('d.m.Y') . '
</div>

</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Helferstunden_' . $year . '_' . date('Y-m-d_H-i-s') . '.pdf';
$outputPath = '../jshelfer/pdf/' . $filename;

file_put_contents($outputPath, $dompdf->output());

header('Content-Type: application/json');
echo json_encode([
  'success' => true,
  'pdf_link' => 'jshelfer/pdf/' . $filename
]);
exit();

// Hilfsfunktion
function imgToBase64($imgPath) {
    if (file_exists($imgPath)) {
        $imageData = base64_encode(file_get_contents($imgPath));
        $mimeType  = mime_content_type($imgPath);
        return 'data:' . $mimeType . ';base64,' . $imageData;
    }
    return '';
}
?>
