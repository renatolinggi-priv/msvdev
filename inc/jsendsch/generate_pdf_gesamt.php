<?php
// Ausgabepufferung starten, um unerwünschte Ausgaben zu unterdrücken
ob_start();
require '../dompdf/autoload.php'; // Pfad zu Composer's autoload Datei
include '../config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Funktion zum Konvertieren eines Bildes in Base64
function imgToBase64($imgPath) {
    $imageData = base64_encode(file_get_contents($imgPath));
    $src = 'data:' . mime_content_type($imgPath) . ';base64,' . $imageData;
    return $src;
}

// Pfad zu Ihrem Logo anpassen
$logoBase64 = imgToBase64('dat/MSVWilen_Logo.jpg');

// Verbindung prüfen
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

// HTML-Inhalt beginnen
$html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Endschiessen Gesamtrangliste Jungschützen</title>
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
            width: 20%;
        }
        .total-width {
            width: 15%;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="' . $logoBase64 . '" alt="Logo" style="width:150px; height:auto;">
        <h2>Endschiessen Gesamtrangliste Jungschützen</h2>
    </div>
';

// Tabelle erstellen
$html .= createTable($conn);

$html .= '
</div>
<div class="footer">
    &copy; ' . date("Y") . ' MSV Wilen. Alle Rechte vorbehalten.
</div>
</body>
</html>';

// Funktion zum Erstellen der Tabelle
function createTable($conn) {
    $html = '';

    // SQL-Abfrage anpassen
    $sql = "SELECT
    js.Name,
    js.Vorname,
    -- Berechnung von ZabigTotal
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
    -- Berechnung von GlueckTotal
    COALESCE(ROUND(GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3) / 10, 1), 0) AS GlueckTotal,
    -- Berechnung von EndstichTotal
    COALESCE(
        e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 +
        e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10,
        0
    ) AS EndstichTotal,
    -- Berechnung von Schwini_Summe1 und Schwini_Summe2
    COALESCE(
        s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
        0
    ) AS Schwini_Summe1,
    COALESCE(
        s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6,
        0
    ) AS Schwini_Summe2,
    -- Berechnung von KunstTotal
    COALESCE(ROUND(
        (k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10,
        1
    ), 0) AS KunstTotal,
    -- Berechnung von MaxSchwini und MinSchwini
    GREATEST(
        s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
        s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6
    ) AS MaxSchwini,
    LEAST(
        s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
        s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6
    ) AS MinSchwini,
    -- Berechnung von GesamtTotal
    (
        COALESCE(
            e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 +
            e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10,
            0
        ) +
        COALESCE(ROUND(GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3) / 10, 1), 0) +
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
            -- Wiederholen für ZSchuss2 bis ZSchuss6
            CASE WHEN z.ZSchuss2 >= 91 THEN 10
                -- ...
                ELSE 0
            END +
            CASE WHEN z.ZSchuss3 >= 91 THEN 10
                -- ...
                ELSE 0
            END +
            CASE WHEN z.ZSchuss4 >= 91 THEN 10
                -- ...
                ELSE 0
            END +
            CASE WHEN z.ZSchuss5 >= 91 THEN 10
                -- ...
                ELSE 0
            END +
            CASE WHEN z.ZSchuss6 >= 91 THEN 10
                -- ...
                ELSE 0
            END
        ) +
        COALESCE(ROUND(
            (k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10,
            1
        ), 0) +
        GREATEST(
            s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
            s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6
        )
    ) AS GesamtTotal
FROM
    jungschuetzen js
LEFT JOIN endstich_jung e ON js.ID = e.JungschuetzeID
LEFT JOIN schwini_jung s ON js.ID = s.JungschuetzeID
LEFT JOIN kunst_jung k ON js.ID = k.JungschuetzeID
LEFT JOIN glueck_jung g ON js.ID = g.JungschuetzeID
LEFT JOIN zabig_jung z ON js.ID = z.JungschuetzeID
WHERE
    e.Schuss1 IS NOT NULL
GROUP BY
    js.ID, js.Vorname, js.Name
ORDER BY
    GesamtTotal DESC,
    EndstichTotal DESC,
    js.Geburtsdatum ASC;
";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $html .= '<table class="table">';
        $html .= '<thead>
                <tr>
                    <th scope="col" class="fixed-width">Rang</th>
                    <th scope="col" class="name-width">Name</th>
                    <th scope="col" class="fixed-width">Endstich</th>
                    <th scope="col" class="fixed-width">Schwini</th>
                    <th scope="col" class="fixed-width">Kunst</th>
                    <th scope="col" class="fixed-width">Glück</th>
                    <th scope="col" class="fixed-width">Zabig</th>
                    <th scope="col" class="total-width">Total</th>
                </tr>
              </thead>';
        $html .= '<tbody>';

        $rang = 1;
        while ($row = $result->fetch_assoc()) {
            $bold = ($rang <= 3) ? 'class="bold"' : '';

            $html .= '<tr>';
            $html .= '<td align="left" ' . $bold . '>' . $rang . '.</td>';
            $html .= '<td align="left" ' . $bold . '>' . htmlspecialchars($row['Name']) . ' ' . htmlspecialchars($row['Vorname']) . '</td>';
            $html .= '<td align="center" ' . $bold . '>' . $row['EndstichTotal'] . '</td>';
            $html .= '<td align="center" ' . $bold . '>' . $row['MaxSchwini'] . '</td>';
            $html .= '<td align="center" ' . $bold . '>' . $row['KunstTotal'] . '</td>';
            $html .= '<td align="center" ' . $bold . '>' . $row['GlueckTotal'] . '</td>';
            $html .= '<td align="center" ' . $bold . '>' . $row['ZabigTotal'] . '</td>';
            $html .= '<td align="center" ' . $bold . '>' . $row['GesamtTotal'] . '</td>';
            $html .= '</tr>';

            $rang++;
        }

        $html .= '</tbody>';
        $html .= '</table>';
    } else {
        $html .= '<p>Keine Ergebnisse gefunden.</p>';
    }

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
$pdfFileName = 'EndschiessenGesamtrangliste_Jungschuetzen_' . $date->format('Y-m-d_H-i-s') . '.pdf';
$pdfFilePath = 'dat/' . $pdfFileName;

file_put_contents($pdfFilePath, $pdfOutput);

// Leeren des Ausgabepuffers und Beenden der Ausgabepufferung
ob_end_clean();

// JSON-Antwort zurückgeben
echo json_encode(['pdf_link' => $pdfFilePath]);

$conn->close();
?>
