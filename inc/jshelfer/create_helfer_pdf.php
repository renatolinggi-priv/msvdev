<?php
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once '../vendor/autoload.php';
require_once __DIR__ . '/../pdf/pdf_theme.php';

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

// HTML aufbauen (Ranglisten-Layout: Logo links, Titel, Fusszeile mit Seitenzahl nach dem Rendern)
$titel = 'Helferstunden ' . $year;
$html = pdf_rangliste_anfang($titel, 'Jungschützenkurs · Wilen und Wollerau')
    . '<div class="container"><table class="table">
  <thead><tr>
    <th style="width: 24mm;">Datum</th>
    <th>Bezeichnung</th>
    <th class="r" style="width: 26mm;">Wilen</th>
    <th class="r" style="width: 26mm;">Wollerau</th>
  </tr></thead><tbody>';

foreach ($eintraege as $e) {
    $html .= '<tr>
        <td>' . date('d.m.Y', strtotime($e['datum'])) . '</td>
        <td>' . htmlspecialchars((string)$e['bezeichnung']) . '</td>
        <td class="r">' . number_format($e['helferWilen'], 2, ',', '.') . '</td>
        <td class="r">' . number_format($e['helferWollerau'], 2, ',', '.') . '</td>
    </tr>';
}

// Summe
$html .= '<tr class="rl-total">
    <td colspan="2">Gesamtsumme</td>
    <td class="r">' . number_format($totalWilen, 2, ',', '.') . '</td>
    <td class="r">' . number_format($totalWollerau, 2, ',', '.') . '</td>
</tr>';

$html .= '</tbody></table></div></body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
pdf_seitenfuss($dompdf, $titel);

$filename = 'Helferstunden_' . $year . '_' . date('Y-m-d_H-i-s') . '.pdf';
$outputPath = '../jshelfer/pdf/' . $filename;

file_put_contents($outputPath, $dompdf->output());

header('Content-Type: application/json');
echo json_encode([
  'success' => true,
  'pdf_link' => 'jshelfer/pdf/' . $filename
]);
exit();
?>
