<?php
// Ausgabepufferung starten, um unerwünschte Ausgaben zu unterdrücken
ob_start();
require '../vendor/autoload.php'; // Pfad zu Composer's autoload Datei
include '../config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Verbindung prüfen
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

// Funktion zum Erstellen der Tabellen
function createTable($kat) {
    $html = '<div class="container">
                <h2>' . $title . '</h2>';

    if ($result->num_rows > 0) {
        $html .= '<table class="table table-bordered table-striped" style="width: 100%; border-collapse: collapse;">';
        $html .= '<thead class="thead-dark">
                    <tr>
                        <th style="width: 40px;">Rang</th>
                        <th style="width: auto;">Name</th>
                        <th style="width: 60px;">Passe</th>
                        <th style="width: 60px;">Total</th>
                    </tr>
                  </thead>';
        $html .= '<tbody>';
        $i = 1;
        foreach ($result as $row) {
            $html .= '<tr>';
            $html .= '<td>' . $i . '.</td>';
            $html .= '<td>' . $row["Name"] . ' ' . $row["Vorname"] . '</td>';
            $html .= '<td>' . $row["Passe1"] . '</td>';
            $html .= '<td>' . $row["Passe2"] . '</td>';
            $html .= '<td>' . $row["Passe3"] . '</td>';
            $html .= '<td>' . $row["Passe4"] . '</td>';
            $html .= '<td>' . $row["Passe5"] . '</td>';
            $html .= '<td>' . $row["Passe6"] . '</td>';
            $html .= '<td>' . $row["Passe7"] . '</td>';
            $html .= '<td>' . $row["Passe8"] . '</td>';
            $html .= '<td>' . $row["HeimSumme"] . '</td>';
            $html .= '</tr>';
            $i++;
        }
        $html .= '</tbody>';
        $html .= '</table>';
    } else {
        $html .= '<p>Keine Ergebnisse gefunden.</p>';
    }

    $html .= '</div>';
    return $html;
}

// SQL-Abfrage für Kategorie A
$sql = "SELECT
  m.ID,
  m.Name,
  m.Vorname,
  g.MitgliedID,
  GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3) AS MaxGlueck,
  COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS Endstich_Summe,
  COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6 ), 0) AS Schwini_Summe1,
  COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6 ), 0) AS Schwini_Summe2,
  ROUND(COALESCE(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5 )/10, 0), 2) AS Kunst_Summe, 

  GREATEST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
        s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6) as MaxSchwini,
  LEAST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
        s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6) as MinSchwini
FROM
  mitglieder m
LEFT JOIN endstich e ON m.ID = e.MitgliedID
LEFT JOIN schwini s ON m.ID = s.MitgliedID
LEFT JOIN kunst k ON m.ID = k.MitgliedID
LEFT JOIN glueck g ON m.ID = g.MitgliedID
LEFT JOIN zabig z ON m.ID = z.MitgliedID
LEFT JOIN Waffen w ON w.ID = m.WaffenID
WHERE w.Kategorie = 'Kat. A'
GROUP BY
  m.ID, m.Vorname, m.Name 
ORDER BY m.Name, m.Vorname;
";

$result = $conn->query($sql);

// HTML-Struktur erstellen
$html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
        }
        .container {
            margin: 5px;
            padding: 1px;
        }
        h2 {
            text-align: left;
            margin-bottom: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            vertical-align: middle;
            padding: 4px;
            border: 1px solid #000;
        }
        .table th:first-child, .table td:first-child {
            width: 40px;
        }
        .table th:nth-child(2), .table td:nth-child(2) {
            width: auto;
        }
        .table th:nth-child(n+3), .table td:nth-child(n+3) {
            width: 60px;
        }
        .table th:nth-child(n+3), .table td:nth-child(n+3) {
            text-align: right;
        }
        .table th:last-child, .table td:last-child {
            font-weight: bold;
        }
        .table tbody tr td {
            background-color: #fff;
        }
        .thead-dark th {
            background-color: #343a40;
            color: #fff;
        }
    </style>
    <title>Ergebnisse</title>
</head>
<body>
<div class="container">
    <h2>Heimmeisterschaft A</h2>';

// Ergebnisse prüfen und als Tabelle ausgeben
if ($result->num_rows > 0) {
    $html .= '<table class="table">';
    $html .= '<thead class="thead-dark">
            <tr>
                <th>Rang</th>
                <th>Name</th>
                <th>Passe 1</th>
                <th>Passe 2</th>
                <th>Passe 3</th>
                <th>Passe 4</th>
                <th>Passe 5</th>
                <th>Passe 6</th>
                <th>Passe 7</th>
                <th>Passe 8</th>
                <th>Total</th>
            </tr>
          </thead>';
    $html .= '<tbody>';
    $i = 1;
    foreach ($result as $row) {
        $html .= '<tr>';
        $html .= '<td>' . $i. '.</td>';
        $html .= '<td>' . $row["Name"] .' '. $row["Vorname"] . '</td>';
        $html .= '<td>' . $row["Passe1"] . '</td>';
        $html .= '<td>' . $row["Passe2"] . '</td>';
        $html .= '<td>' . $row["Passe3"] . '</td>';
        $html .= '<td>' . $row["Passe4"] . '</td>';
        $html .= '<td>' . $row["Passe5"] . '</td>';
        $html .= '<td>' . $row["Passe6"] . '</td>';
        $html .= '<td>' . $row["Passe7"] . '</td>';
        $html .= '<td>' . $row["Passe8"] . '</td>';
        $html .= '<td>' . $row["HeimSumme"] . '</td>';
        $html .= '</tr>';
        $i++;
    }
    $html .= '</tbody>';
    $html .= '</table>';
} else {
    $html .= '<p>Keine Ergebnisse gefunden.</p>';
}

$html .= '</div>';

// SQL-Abfrage für Kategorie B
$sql = "SELECT m.Name, m.Vorname, h.Passe1, h.Passe2, h.Passe3, h.Passe4, h.Passe5, h.Passe6, h.Passe7, h.Passe8,
               (COALESCE(h.Passe1, 0) + COALESCE(h.Passe2, 0) + COALESCE(h.Passe3, 0) + COALESCE(h.Passe4, 0) + 
                COALESCE(h.Passe5, 0) + COALESCE(h.Passe6, 0) + COALESCE(h.Passe7, 0) + COALESCE(h.Passe8, 0)) AS HeimSumme
        FROM heimresultate h
        INNER JOIN mitglieder m ON m.ID = h.MitgliedID
        INNER JOIN Waffen w ON w.ID = m.WaffenID 
        WHERE w.Kategorie = 'Kat. B' and h.Passe1 > 0
        ORDER BY HeimSumme DESC";

$result = $conn->query($sql);

// HTML-Struktur erstellen
$html .= '
<div class="container">
    <h2>Heimmeisterschaft B</h2>';

// Ergebnisse prüfen und als Tabelle ausgeben
if ($result->num_rows > 0) {
    $html .= '<table class="table">';
    $html .= '<thead class="thead-dark">
            <tr>
                <th>Rang</th>
                <th>Name</th>
                <th>Passe 1</th>
                <th>Passe 2</th>
                <th>Passe 3</th>
                <th>Passe 4</th>
                <th>Passe 5</th>
                <th>Passe 6</th>
                <th>Passe 7</th>
                <th>Passe 8</th>
                <th>Total</th>
            </tr>
          </thead>';
    $html .= '<tbody>';
    $i = 1;
    foreach ($result as $row) {
        $html .= '<tr>';
        $html .= '<td>' . $i. '.</td>';
        $html .= '<td>' . $row["Name"] .' '. $row["Vorname"] . '</td>';
        $html .= '<td>' . $row["Passe1"] . '</td>';
        $html .= '<td>' . $row["Passe2"] . '</td>';
        $html .= '<td>' . $row["Passe3"] . '</td>';
        $html .= '<td>' . $row["Passe4"] . '</td>';
        $html .= '<td>' . $row["Passe5"] . '</td>';
        $html .= '<td>' . $row["Passe6"] . '</td>';
        $html .= '<td>' . $row["Passe7"] . '</td>';
        $html .= '<td>' . $row["Passe8"] . '</td>';
        $html .= '<td>' . $row["HeimSumme"] . '</td>';
        $html .= '</tr>';
        $i++;
    }
    $html .= '</tbody>';
    $html .= '</table>';
} else {
    $html .= '<p>Keine Ergebnisse gefunden.</p>';
}

$html .= '</div>
</body>
</html>';

// HTML im Browser ausgeben, um die Formatierung zu überprüfen
//echo $html;

// Verbindung schließen
$conn->close();

// Weiteres Skript beenden, damit das PDF nicht sofort erstellt wird
// exit();
// Dompdf initialisieren
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// HTML in PDF umwandeln
$dompdf->loadHtml($html);

// Querformat einstellen
$dompdf->setPaper('A4', 'landscape');
//$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

// PDF-Datei speichern
$pdfOutput = $dompdf->output();
$date = new DateTime();
$pdfFilePath = 'RanglisteHeimmeisterschaft_' . $date->format('Y-m-d_H-i-s') . '.pdf';

file_put_contents($pdfFilePath, $pdfOutput);

// Leeren des Ausgabepuffers und Beenden der Ausgabepufferung
ob_end_clean();
// Download-Link anzeigen
//echo "PDF gespeichert unter: <a href='$pdfFilePath' target='_blank'>$pdfFilePath herunterladen</a>";
echo json_encode(array('pdf_link' => "rangheim/" .$pdfFilePath));
?>
