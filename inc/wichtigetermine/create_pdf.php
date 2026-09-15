<?php
// create_pdf.php – Wichtige Termine eines Jahres als PDF (zentrales PDF-Theme).
// Enthaelt zusaetzlich die Standbelegungs-Termine mit Kalender-Markierung (InKalender = 1);
// diese sind im PDF als "Standbelegung" gekennzeichnet, weil die Admin-Tabelle sie nicht zeigt.

include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once '../vendor/autoload.php';
require_once __DIR__ . '/../pdf/pdf_theme.php';

use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Type: application/json; charset=utf-8');

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');

// Wichtige Termine
$stmt = $conn->prepare("SELECT name, date, time FROM wichtige_termine WHERE year = ? ORDER BY date");
$stmt->bind_param('i', $year);
$stmt->execute();
$result = $stmt->get_result();

$termine = [];
$existingLookup = [];
while ($row = $result->fetch_assoc()) {
    $row['quelle'] = '';
    $termine[] = $row;
    $existingLookup[strtolower($row['date'] . '|' . $row['name'])] = true;
}
$stmt->close();

// Standbelegung-Termine (InKalender = 1) ergaenzen, sofern nicht schon als wichtiger Termin erfasst
$stmt = $conn->prepare("SELECT Bezeichnung, Datum, StartZeit, EndZeit FROM Standbelegung WHERE Jahr = ? AND InKalender = 1 ORDER BY Datum, StartZeit");
$stmt->bind_param('i', $year);
$stmt->execute();
$result = $stmt->get_result();
$anzStandbelegung = 0;
while ($row = $result->fetch_assoc()) {
    $lookupKey = strtolower($row['Datum'] . '|' . $row['Bezeichnung']);
    if (isset($existingLookup[$lookupKey])) continue;
    $sbTime = '';
    if (!empty($row['StartZeit']) && !empty($row['EndZeit'])) {
        $sbTime = substr($row['StartZeit'], 0, 5) . ' - ' . substr($row['EndZeit'], 0, 5);
    }
    $termine[] = ['name' => $row['Bezeichnung'], 'date' => $row['Datum'], 'time' => $sbTime, 'quelle' => 'Standbelegung'];
    $anzStandbelegung++;
}
$stmt->close();
$conn->close();

usort($termine, static fn($a, $b) => strcmp($a['date'], $b['date']));

$wochentage = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
$rows = '';
foreach ($termine as $t) {
    $ts = strtotime($t['date']);
    $datum = $ts ? $wochentage[(int)date('w', $ts)] . ' ' . date('d.m.Y', $ts) : htmlspecialchars($t['date']);
    $quelle = $t['quelle'] !== '' ? ' <span class="quelle">(' . htmlspecialchars($t['quelle']) . ')</span>' : '';
    $rows .= '<tr>'
           . '<td class="datum">' . $datum . '</td>'
           . '<td>' . htmlspecialchars($t['name']) . $quelle . '</td>'
           . '<td class="zeit">' . htmlspecialchars($t['time']) . '</td>'
           . '</tr>';
}
if ($rows === '') {
    $rows = '<tr><td colspan="3" class="text-center text-muted">Keine Termine für ' . $year . ' erfasst.</td></tr>';
}

$hinweis = $anzStandbelegung > 0
    ? '<p class="text-muted hinweis">Enthält zusätzlich ' . $anzStandbelegung . ' Termin(e) aus der Standbelegung mit Kalender-Markierung, gekennzeichnet mit „(Standbelegung)".</p>'
    : '';

$html = '<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Wichtige Termine ' . $year . '</title>
  <style>
    @page { margin: 20px 20px 50px 20px; }
    ' . pdf_theme_css() . '
    /* Terminliste: einfache Tabelle ohne Rang-/Total-Hervorhebung des Themes */
    .table td:first-child { text-align: left; font-weight: normal; white-space: nowrap; }
    .table td:last-child  { text-align: left; font-weight: normal; background-color: transparent; color: inherit; white-space: nowrap; }
    .table td.datum { width: 22%; }
    .table td.zeit  { width: 20%; }
    .quelle { color: #64748b; font-size: 9px; }
    .hinweis { font-size: 9px; margin: 0 10px 8px 10px; }
  </style>
</head>
<body>
  <div class="header">
    <img class="logo" src="' . pdf_logo_src() . '" alt="Logo">
    <div class="header-text"><h1>Wichtige Termine ' . $year . '</h1></div>
  </div>
  <div class="container">
    ' . $hinweis . '
    <table class="table">
      <thead><tr><th>Datum</th><th>Termin</th><th>Uhrzeit</th></tr></thead>
      <tbody>' . $rows . '</tbody>
    </table>
  </div>
  ' . pdf_footer_html('MSV Wilen · erstellt am ' . date('d.m.Y')) . '
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', false); // Logo ist als Data-URI eingebettet
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename   = 'Wichtige_Termine_' . $year . '_' . date('Y-m-d_H-i-s') . '.pdf';
$outputPath = __DIR__ . '/dat/' . $filename;
if (file_put_contents($outputPath, $dompdf->output()) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'PDF konnte nicht gespeichert werden']);
    exit;
}

echo json_encode([
    'success'  => true,
    'pdf_link' => 'wichtigetermine/dat/' . $filename,
]);
