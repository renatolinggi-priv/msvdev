<?php
// Ausgabepufferung starten, um unerwünschte Ausgaben zu unterdrücken
ob_start();
require '../dompdf/autoload.php'; // Pfad zu Composer's autoload Datei
include '../config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Jahr parameter
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Funktion zum Konvertieren eines Bildes in Base64
function imgToBase64($imgPath) {
    if (file_exists($imgPath)) {
        $imageData = base64_encode(file_get_contents($imgPath));
        $src = 'data:' . mime_content_type($imgPath) . ';base64,' . $imageData;
        return $src;
    }
    return '';
}

// Pfad zum Logo anpassen
$logoPath = 'dat/MSVWilen_Logo.jpg';
if (!file_exists($logoPath)) {
    $logoPath = '../dat/MSVWilen_Logo.jpg';
}
$logoBase64 = imgToBase64($logoPath);

// Verbindung prüfen
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

// HTML-Inhalt beginnen
$html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>JS-Endschiessen Gesamtrangliste ' . $year . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        .container {
            margin: 5px;
            padding: 1px;
        }
        h2 {
            text-align: left;
            margin-bottom: 20px;
        }
        .header {
            display: flex;
            align-items: center;
        }
        .header img {
            margin-right: 20px;
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
        .table th {
            background-color: #343a40;
            color: #fff;
        }
        .bold {
            font-weight: bold;
        }
        .fixed-width {
            width: 10%;
        }
        .name-width {
            width: 35%;
        }
        .total-width {
            width: 15%;
        }
        .age-width {
            width: 10%;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">';
    
if ($logoBase64) {
    $html .= '<img src="' . $logoBase64 . '" alt="Logo" style="width:150px; height:auto;">';
}

$html .= '
        <h2>JS-Endschiessen Gesamtrangliste ' . $year . '</h2>
    </div>
';

// Tabelle erstellen
$html .= createTable($conn, $year);

$html .= '
</div>
<div class="footer">
    &copy; ' . date("Y") . ' MSV Wilen. Alle Rechte vorbehalten.
</div>
</body>
</html>';

// Funktion zum Erstellen der Tabelle
function createTable($conn, $year) {
    $html = '';

    // SQL-Abfrage für die neue Struktur mit endstich_gaeste
    $sql = "SELECT
    g.id,
    g.name,
    g.geburtsdatum,
    COALESCE(g.vorname, SUBSTRING_INDEX(g.name, ' ', 1)) as Vorname,
    COALESCE(g.nachname, SUBSTRING_INDEX(g.name, ' ', -1)) as Nachname,
    TIMESTAMPDIFF(YEAR, g.geburtsdatum, CURDATE()) as Alter,
    (
        CASE
            WHEN z.ZSchuss1 >= 91 THEN 10
            WHEN z.ZSchuss1 >= 81 THEN 9
            WHEN z.ZSchuss1 >= 71 THEN 8
            WHEN z.ZSchuss1 >= 61 THEN 7
            WHEN z.ZSchuss1 >= 51 THEN 6
            WHEN z.ZSchuss1 >= 41 THEN 5
            WHEN z.ZSchuss1 >= 31 THEN 4
            WHEN z.ZSchuss1 >= 21 THEN 3
            WHEN z.ZSchuss1 >= 11 THEN 2
            WHEN z.ZSchuss1 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss2 >= 91 THEN 10
            WHEN z.ZSchuss2 >= 81 THEN 9
            WHEN z.ZSchuss2 >= 71 THEN 8
            WHEN z.ZSchuss2 >= 61 THEN 7
            WHEN z.ZSchuss2 >= 51 THEN 6
            WHEN z.ZSchuss2 >= 41 THEN 5
            WHEN z.ZSchuss2 >= 31 THEN 4
            WHEN z.ZSchuss2 >= 21 THEN 3
            WHEN z.ZSchuss2 >= 11 THEN 2
            WHEN z.ZSchuss2 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss3 >= 91 THEN 10
            WHEN z.ZSchuss3 >= 81 THEN 9
            WHEN z.ZSchuss3 >= 71 THEN 8
            WHEN z.ZSchuss3 >= 61 THEN 7
            WHEN z.ZSchuss3 >= 51 THEN 6
            WHEN z.ZSchuss3 >= 41 THEN 5
            WHEN z.ZSchuss3 >= 31 THEN 4
            WHEN z.ZSchuss3 >= 21 THEN 3
            WHEN z.ZSchuss3 >= 11 THEN 2
            WHEN z.ZSchuss3 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss4 >= 91 THEN 10
            WHEN z.ZSchuss4 >= 81 THEN 9
            WHEN z.ZSchuss4 >= 71 THEN 8
            WHEN z.ZSchuss4 >= 61 THEN 7
            WHEN z.ZSchuss4 >= 51 THEN 6
            WHEN z.ZSchuss4 >= 41 THEN 5
            WHEN z.ZSchuss4 >= 31 THEN 4
            WHEN z.ZSchuss4 >= 21 THEN 3
            WHEN z.ZSchuss4 >= 11 THEN 2
            WHEN z.ZSchuss4 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss5 >= 91 THEN 10
            WHEN z.ZSchuss5 >= 81 THEN 9
            WHEN z.ZSchuss5 >= 71 THEN 8
            WHEN z.ZSchuss5 >= 61 THEN 7
            WHEN z.ZSchuss5 >= 51 THEN 6
            WHEN z.ZSchuss5 >= 41 THEN 5
            WHEN z.ZSchuss5 >= 31 THEN 4
            WHEN z.ZSchuss5 >= 21 THEN 3
            WHEN z.ZSchuss5 >= 11 THEN 2
            WHEN z.ZSchuss5 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss6 >= 91 THEN 10
            WHEN z.ZSchuss6 >= 81 THEN 9
            WHEN z.ZSchuss6 >= 71 THEN 8
            WHEN z.ZSchuss6 >= 61 THEN 7
            WHEN z.ZSchuss6 >= 51 THEN 6
            WHEN z.ZSchuss6 >= 41 THEN 5
            WHEN z.ZSchuss6 >= 31 THEN 4
            WHEN z.ZSchuss6 >= 21 THEN 3
            WHEN z.ZSchuss6 >= 11 THEN 2
            WHEN z.ZSchuss6 >= 1 THEN 1
            ELSE 0
        END
    ) AS ZabigTotal,
    COALESCE(
        e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 +
        e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10,
        0
    ) AS EndstichTotal,
    COALESCE(
        s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
        0
    ) AS SchwiniTotal,
    (
        COALESCE(
            e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 +
            e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10,
            0
        ) +
        COALESCE(
            s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
            0
        ) +
        (
            CASE WHEN z.ZSchuss1 >= 91 THEN 10
                WHEN z.ZSchuss1 >= 81 THEN 9
                WHEN z.ZSchuss1 >= 71 THEN 8
                WHEN z.ZSchuss1 >= 61 THEN 7
                WHEN z.ZSchuss1 >= 51 THEN 6
                WHEN z.ZSchuss1 >= 41 THEN 5
                WHEN z.ZSchuss1 >= 31 THEN 4
                WHEN z.ZSchuss1 >= 21 THEN 3
                WHEN z.ZSchuss1 >= 11 THEN 2
                WHEN z.ZSchuss1 >= 1 THEN 1
                ELSE 0
            END +
            CASE WHEN z.ZSchuss2 >= 91 THEN 10
                WHEN z.ZSchuss2 >= 81 THEN 9
                WHEN z.ZSchuss2 >= 71 THEN 8
                WHEN z.ZSchuss2 >= 61 THEN 7
                WHEN z.ZSchuss2 >= 51 THEN 6
                WHEN z.ZSchuss2 >= 41 THEN 5
                WHEN z.ZSchuss2 >= 31 THEN 4
                WHEN z.ZSchuss2 >= 21 THEN 3
                WHEN z.ZSchuss2 >= 11 THEN 2
                WHEN z.ZSchuss2 >= 1 THEN 1
                ELSE 0
            END +
            CASE WHEN z.ZSchuss3 >= 91 THEN 10
                WHEN z.ZSchuss3 >= 81 THEN 9
                WHEN z.ZSchuss3 >= 71 THEN 8
                WHEN z.ZSchuss3 >= 61 THEN 7
                WHEN z.ZSchuss3 >= 51 THEN 6
                WHEN z.ZSchuss3 >= 41 THEN 5
                WHEN z.ZSchuss3 >= 31 THEN 4
                WHEN z.ZSchuss3 >= 21 THEN 3
                WHEN z.ZSchuss3 >= 11 THEN 2
                WHEN z.ZSchuss3 >= 1 THEN 1
                ELSE 0
            END +
            CASE WHEN z.ZSchuss4 >= 91 THEN 10
                WHEN z.ZSchuss4 >= 81 THEN 9
                WHEN z.ZSchuss4 >= 71 THEN 8
                WHEN z.ZSchuss4 >= 61 THEN 7
                WHEN z.ZSchuss4 >= 51 THEN 6
                WHEN z.ZSchuss4 >= 41 THEN 5
                WHEN z.ZSchuss4 >= 31 THEN 4
                WHEN z.ZSchuss4 >= 21 THEN 3
                WHEN z.ZSchuss4 >= 11 THEN 2
                WHEN z.ZSchuss4 >= 1 THEN 1
                ELSE 0
            END +
            CASE WHEN z.ZSchuss5 >= 91 THEN 10
                WHEN z.ZSchuss5 >= 81 THEN 9
                WHEN z.ZSchuss5 >= 71 THEN 8
                WHEN z.ZSchuss5 >= 61 THEN 7
                WHEN z.ZSchuss5 >= 51 THEN 6
                WHEN z.ZSchuss5 >= 41 THEN 5
                WHEN z.ZSchuss5 >= 31 THEN 4
                WHEN z.ZSchuss5 >= 21 THEN 3
                WHEN z.ZSchuss5 >= 11 THEN 2
                WHEN z.ZSchuss5 >= 1 THEN 1
                ELSE 0
            END +
            CASE WHEN z.ZSchuss6 >= 91 THEN 10
                WHEN z.ZSchuss6 >= 81 THEN 9
                WHEN z.ZSchuss6 >= 71 THEN 8
                WHEN z.ZSchuss6 >= 61 THEN 7
                WHEN z.ZSchuss6 >= 51 THEN 6
                WHEN z.ZSchuss6 >= 41 THEN 5
                WHEN z.ZSchuss6 >= 31 THEN 4
                WHEN z.ZSchuss6 >= 21 THEN 3
                WHEN z.ZSchuss6 >= 11 THEN 2
                WHEN z.ZSchuss6 >= 1 THEN 1
                ELSE 0
            END
        )
    ) AS GesamtTotal,
    e.Tiefschuss
FROM
    endstich_gaeste g
LEFT JOIN endstich_jung e ON g.id = e.JungschuetzeID AND e.Jahr = ?
LEFT JOIN schwini_jung s ON g.id = s.JungschuetzeID AND s.Jahr = ?
LEFT JOIN zabig_jung z ON g.id = z.JungschuetzeID AND z.Jahr = ?
WHERE
    g.jahr = ?
    AND g.geburtsdatum IS NOT NULL
    AND TIMESTAMPDIFF(YEAR, g.geburtsdatum, CURDATE()) BETWEEN 10 AND 20
    AND (e.Schuss1 IS NOT NULL OR s.P1Schuss1 IS NOT NULL OR z.ZSchuss1 IS NOT NULL)
ORDER BY
    GesamtTotal DESC,
    EndstichTotal DESC,
    e.Tiefschuss DESC,
    g.geburtsdatum ASC";

    // Prepared Statement verwenden
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $year, $year, $year, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $html .= '<table class="table">';
        $html .= '<thead>
                <tr>
                    <th scope="col" class="fixed-width">Rang</th>
                    <th scope="col" class="name-width">Name</th>
                    <th scope="col" class="age-width">Alter</th>
                    <th scope="col" class="fixed-width">Endstich</th>
                    <th scope="col" class="fixed-width">Schwini</th>
                    <th scope="col" class="fixed-width">Zabig</th>
                    <th scope="col" class="total-width">Total</th>
                </tr>
              </thead>';
        $html .= '<tbody>';

        $rang = 1;
        $lastTotal = null;
        $lastEndstich = null;
        $lastTiefschuss = null;
        $sameRankCount = 0;
        
        while ($row = $result->fetch_assoc()) {
            // Rangierung mit Berücksichtigung von Gleichstand
            if ($lastTotal !== null && 
                $row['GesamtTotal'] == $lastTotal && 
                $row['EndstichTotal'] == $lastEndstich &&
                $row['Tiefschuss'] == $lastTiefschuss) {
                // Gleicher Rang
                $sameRankCount++;
            } else {
                // Neuer Rang
                $rang += $sameRankCount;
                $sameRankCount = 1;
            }
            
            $bold = ($rang <= 3) ? 'class="bold"' : '';

            $html .= '<tr>';
            $html .= '<td align="left" ' . $bold . '>' . $rang . '.</td>';
            $html .= '<td align="left" ' . $bold . '>' . htmlspecialchars($row['Nachname']) . ' ' . htmlspecialchars($row['Vorname']) . '</td>';
            $html .= '<td align="center" ' . $bold . '>' . $row['Alter'] . ' J.</td>';
            $html .= '<td align="center" ' . $bold . '>' . $row['EndstichTotal'];
            if ($row['Tiefschuss'] > 0) {
                $html .= ' <small>(' . $row['Tiefschuss'] . ')</small>';
            }
            $html .= '</td>';
            $html .= '<td align="center" ' . $bold . '>' . $row['SchwiniTotal'] . '</td>';
            $html .= '<td align="center" ' . $bold . '>' . $row['ZabigTotal'] . '</td>';
            $html .= '<td align="center" ' . $bold . '><strong>' . $row['GesamtTotal'] . '</strong></td>';
            $html .= '</tr>';

            $lastTotal = $row['GesamtTotal'];
            $lastEndstich = $row['EndstichTotal'];
            $lastTiefschuss = $row['Tiefschuss'];
        }

        $html .= '</tbody>';
        $html .= '</table>';
    } else {
        $html .= '<p>Keine Ergebnisse für das Jahr ' . $year . ' gefunden.</p>';
    }

    $stmt->close();
    return $html;
}

// Dompdf initialisieren
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// HTML in PDF umwandeln
$dompdf->loadHtml($html);

// Papierformat und Orientierung einstellen
$dompdf->setPaper('A4', 'portrait');

// Rendern des PDFs
$dompdf->render();

// PDF-Datei speichern
$pdfOutput = $dompdf->output();
$date = new DateTime();
$pdfFileName = 'JS_Endschiessen_Gesamtrangliste_' . $year . '_' . $date->format('Y-m-d_H-i-s') . '.pdf';
$pdfFilePath = 'dat/' . $pdfFileName;

// Verzeichnis erstellen falls es nicht existiert
if (!is_dir('dat')) {
    mkdir('dat', 0777, true);
}

file_put_contents($pdfFilePath, $pdfOutput);

// Leeren des Ausgabepuffers und Beenden der Ausgabepufferung
ob_end_clean();

// JSON-Antwort zurückgeben
echo json_encode(['pdf_link' => $pdfFilePath]);

$conn->close();
?>
