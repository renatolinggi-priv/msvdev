<?php
// create_pdf.php

include '../config.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Jahr aus der URL lesen oder aktuelles Jahr verwenden
$year = isset($_GET['year']) ? (int)$_GET['year'] : date("Y");

// Wichtige Termine aus der Tabelle abfragen
$sql = "SELECT name, date, time FROM wichtige_termine WHERE year = ? ORDER BY date";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$termine = [];
while ($row = $result->fetch_assoc()) {
    $termine[] = $row;
}
$stmt->close();

// Standbelegung-Termine (InKalender = 1) laden und zusammenführen
$sql = "SELECT Bezeichnung, Datum, StartZeit, EndZeit FROM Standbelegung WHERE Jahr = ? AND InKalender = 1 ORDER BY Datum, StartZeit";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

// Existierende Termine als Lookup für Duplikaterkennung (Datum + Name)
$existingLookup = [];
foreach ($termine as $t) {
    $existingLookup[strtolower($t['date'] . '|' . $t['name'])] = true;
}

while ($row = $result->fetch_assoc()) {
    $sbDate = $row['Datum'];
    $sbName = $row['Bezeichnung'];

    // Nur hinzufügen wenn nicht bereits als wichtiger Termin vorhanden
    $lookupKey = strtolower($sbDate . '|' . $sbName);
    if (isset($existingLookup[$lookupKey])) {
        continue;
    }

    // Zeit formatieren: "HH:MM - HH:MM"
    $sbTime = '';
    if (!empty($row['StartZeit']) && !empty($row['EndZeit'])) {
        $sbTime = substr($row['StartZeit'], 0, 5) . ' - ' . substr($row['EndZeit'], 0, 5);
    }

    $termine[] = [
        'name' => $sbName,
        'date' => $sbDate,
        'time' => $sbTime
    ];
}
$stmt->close();
$conn->close();

// Nach Datum sortieren
usort($termine, function($a, $b) {
    return strcmp($a['date'], $b['date']);
});

$logoBase64 = imgToBase64('dat/MSVWilen_Logo.jpg');

// HTML-Inhalt für das PDF erstellen
$html = '<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Wichtige Termine ' . $year . '</title>
  <style>
    @page {
      margin: 20px 20px 20px 20px;
    }
    body {
      font-family: Arial, sans-serif;
      font-size: 12px;
      margin: 20px 20px 60px 20px;
      position: relative;
    }

    /* Tabelle für Logo und Titel */
    .header-table {
      width: 100%;
      border: none;
      margin-bottom: 10px;
      border-collapse: collapse;
    }
    .header-table td {
      border: none; /* Keine Rahmen in der Header-Tabelle */
      vertical-align: middle;
    }
    /* Linke Spalte: Breite für Logo */
    .header-left {
      width: 120px;
    }
    /* Rechte Spalte: ebenfalls 120px, damit die Mitte wirklich zentral liegt */
    .header-right {
      width: 120px;
    }
    /* Mittlere Spalte: hier wird zentriert */
    .header-center {
      text-align: center;
    }
    .header-center h1 {
      margin: 0; /* Standardabstand bei H1 entfernen */
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    th, td {
      border: 1px solid #e2e8f0;
      padding: 8px;
      text-align: left;
    }
    th {
      background-color: #eef2f7;
      color: #2d3748;
      border-bottom: 2px solid #cbd5e0;
    }

    /* Footer-Styles */
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

<!-- Kopfzeile: 3-spaltige Tabelle -->
<table class="header-table">
  <tr>
    <td class="header-left">
      <img src="' . $logoBase64 . '" alt="Logo" style="width:100px; height:auto;">
    </td>
    <td class="header-center">
      <h1>Wichtige Termine ' . $year . '</h1>
    </td>
    <td class="header-right"></td>
  </tr>
</table>

<table>
  <tr>
    <th>Datum</th>
    <th>Termin</th>
    <th>Uhrzeit</th>
  </tr>';

foreach ($termine as $termin) {
    $datum = date("d.m.Y", strtotime($termin['date']));
    $html .= '<tr>
                <td>' . $datum . '</td>
                <td>' . htmlspecialchars($termin['name']) . '</td>
                <td>' . htmlspecialchars($termin['time']) . '</td>
              </tr>';
}

// Abschließender Teil des HTML mit Footer
$html .= '
  </table>

  <div class="footer">
    Erstellt am ' . date('d.m.Y') . '
  </div>

</body>
</html>';

// DomPDF konfigurieren und PDF erstellen
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$pdfOutput = $dompdf->output();

// Erstelle einen Dateinamen mit Zeitstempel
$filename = 'Wichtige_Termine_' . $year . '_' . date('Y-m-d_H-i-s') . '.pdf';
// Definiere den Zielordner (bitte sicherstellen, dass der Ordner existiert und beschreibbar ist)
$outputPath = "../wichtigetermine/dat/" . $filename;

file_put_contents($outputPath, $pdfOutput);

// JSON-Antwort zurückgeben (Content-Type auf application/json setzen)
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'pdf_link' => "wichtigetermine/dat/" . $filename
]);
exit();

// Hilfsfunktion, um ein Bild in Base64 zu konvertieren
function imgToBase64($imgPath)
{
    if (file_exists($imgPath)) {
        $imageData = base64_encode(file_get_contents($imgPath));
        $mimeType  = mime_content_type($imgPath);
        return 'data:' . $mimeType . ';base64,' . $imageData;
    }
    return "";
}
?>
