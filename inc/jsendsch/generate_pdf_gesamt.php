<?php
// Ausgabepufferung starten, um unerwünschte Ausgaben zu unterdrücken
ob_start();
require '../dompdf/autoload.php';
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

// Funktion zur Berechnung der Zabig-Punkte
function calculateZabigPoints($value) {
    if ($value >= 91) return 10;
    if ($value >= 81) return 9;
    if ($value >= 71) return 8;
    if ($value >= 61) return 7;
    if ($value >= 51) return 6;
    if ($value >= 41) return 5;
    if ($value >= 31) return 4;
    if ($value >= 21) return 3;
    if ($value >= 11) return 2;
    if ($value >= 1) return 1;
    return 0;
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
            margin: 10px;
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
            margin-bottom: 20px;
        }
        .header img {
            margin-right: 20px;
            vertical-align: middle;
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
        .text-center {
            text-align: center;
        }
        .text-left {
            text-align: left;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #666;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">';
    
if ($logoBase64) {
    $html .= '<img src="' . $logoBase64 . '" alt="Logo" style="width:150px; height:auto;">';
}

$html .= '<h2 style="display: inline-block;">JS-Endschiessen Gesamtrangliste ' . $year . '</h2>
    </div>';

// SQL-Abfrage für die neue Struktur
$sql = "SELECT 
    g.id,
    g.name,
    g.geburtsdatum,
    COALESCE(g.vorname, SUBSTRING_INDEX(g.name, ' ', 1)) AS Vorname,
    COALESCE(g.nachname, SUBSTRING_INDEX(g.name, ' ', -1)) AS Nachname,
    TIMESTAMPDIFF(YEAR, g.geburtsdatum, CURDATE()) AS AlterJahre,

    COALESCE(e.Schuss1, 0)  AS E1,
    COALESCE(e.Schuss2, 0)  AS E2,
    COALESCE(e.Schuss3, 0)  AS E3,
    COALESCE(e.Schuss4, 0)  AS E4,
    COALESCE(e.Schuss5, 0)  AS E5,
    COALESCE(e.Schuss6, 0)  AS E6,
    COALESCE(e.Schuss7, 0)  AS E7,
    COALESCE(e.Schuss8, 0)  AS E8,
    COALESCE(e.Schuss9, 0)  AS E9,
    COALESCE(e.Schuss10, 0) AS E10,
    COALESCE(e.Tiefschuss, 0) AS Tiefschuss,

    COALESCE(s.P1Schuss1, 0) AS S1,
    COALESCE(s.P1Schuss2, 0) AS S2,
    COALESCE(s.P1Schuss3, 0) AS S3,
    COALESCE(s.P1Schuss4, 0) AS S4,
    COALESCE(s.P1Schuss5, 0) AS S5,
    COALESCE(s.P1Schuss6, 0) AS S6,

    COALESCE(z.ZSchuss1, 0) AS Z1,
    COALESCE(z.ZSchuss2, 0) AS Z2,
    COALESCE(z.ZSchuss3, 0) AS Z3,
    COALESCE(z.ZSchuss4, 0) AS Z4,
    COALESCE(z.ZSchuss5, 0) AS Z5,
    COALESCE(z.ZSchuss6, 0) AS Z6,

    -- Summen für Sortierung
    (COALESCE(e.Schuss1,0)+COALESCE(e.Schuss2,0)+COALESCE(e.Schuss3,0)+COALESCE(e.Schuss4,0)+COALESCE(e.Schuss5,0)
     +COALESCE(e.Schuss6,0)+COALESCE(e.Schuss7,0)+COALESCE(e.Schuss8,0)+COALESCE(e.Schuss9,0)+COALESCE(e.Schuss10,0)) AS EndstichSum,

    (COALESCE(s.P1Schuss1,0)+COALESCE(s.P1Schuss2,0)+COALESCE(s.P1Schuss3,0)
     +COALESCE(s.P1Schuss4,0)+COALESCE(s.P1Schuss5,0)+COALESCE(s.P1Schuss6,0)) AS SchwiniSum

FROM endstich_gaeste g
LEFT JOIN endstich_jung e ON g.id = e.JungschuetzeID AND e.Jahr = ?
LEFT JOIN schwini_jung   s ON g.id = s.JungschuetzeID AND s.Jahr = ?
LEFT JOIN zabig_jung     z ON g.id = z.JungschuetzeID AND z.Jahr = ?
WHERE 
    g.jahr = ?
    AND g.geburtsdatum IS NOT NULL
    AND TIMESTAMPDIFF(YEAR, g.geburtsdatum, CURDATE()) BETWEEN 10 AND 20
    AND (e.Schuss1 IS NOT NULL OR s.P1Schuss1 IS NOT NULL OR z.ZSchuss1 IS NOT NULL)
ORDER BY 
    EndstichSum DESC,
    SchwiniSum DESC,
    AlterJahre ASC,
    Nachname ASC,
    Vorname ASC";



// Prepared Statement verwenden
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("iiii", $year, $year, $year, $year);
$stmt->execute();
$result = $stmt->get_result();

// Daten verarbeiten und berechnen
$jungschuetzen = [];
while ($row = $result->fetch_assoc()) {
    // Berechnungen durchführen
    $endstichTotal = $row['E1'] + $row['E2'] + $row['E3'] + $row['E4'] + $row['E5'] + 
                     $row['E6'] + $row['E7'] + $row['E8'] + $row['E9'] + $row['E10'];
    
    $schwiniTotal = $row['S1'] + $row['S2'] + $row['S3'] + $row['S4'] + $row['S5'] + $row['S6'];
    
    $zabigTotal = calculateZabigPoints($row['Z1']) + calculateZabigPoints($row['Z2']) + 
                  calculateZabigPoints($row['Z3']) + calculateZabigPoints($row['Z4']) + 
                  calculateZabigPoints($row['Z5']) + calculateZabigPoints($row['Z6']);
    
    $gesamtTotal = $endstichTotal + $schwiniTotal + $zabigTotal;
    
    $geburtsdatumFmt = '';
    if (!empty($row['geburtsdatum']) && $row['geburtsdatum'] !== '0000-00-00') {
        $dt = DateTime::createFromFormat('Y-m-d', $row['geburtsdatum']);
        if ($dt) $geburtsdatumFmt = $dt->format('d.m.Y');
    }

    $jungschuetzen[] = [
        'name'        => $row['Nachname'] . ' ' . $row['Vorname'],
        'geburt'      => $geburtsdatumFmt,          // für Anzeige
        'geburt_raw'  => $row['geburtsdatum'],      // <-- NEU: YYYY-MM-DD für Sortierung
        'alter'       => (int)$row['AlterJahre'],   // falls Du's noch brauchst
        'endstich'    => $endstichTotal,
        'tiefschuss'  => $row['Tiefschuss'],
        'schwini'     => $schwiniTotal,
        'zabig'       => $zabigTotal,
        'gesamt'      => $gesamtTotal
    ];

}

// Sortieren nach Gesamt, dann Endstich, dann Tiefschuss
usort($jungschuetzen, function($a, $b) {
    if ($a['gesamt']   !== $b['gesamt'])   return $b['gesamt']   <=> $a['gesamt'];
    if ($a['endstich'] !== $b['endstich']) return $b['endstich'] <=> $a['endstich'];
    if ($a['schwini']  !== $b['schwini'])  return $b['schwini']  <=> $a['schwini'];

    // Geburtsdatum: jünger (späteres Datum) vor älterem -> DESC
    if ($a['geburt_raw'] !== $b['geburt_raw']) return strcmp($b['geburt_raw'], $a['geburt_raw']);

    return strcasecmp($a['name'], $b['name']);
});



// Tabelle erstellen
if (count($jungschuetzen) > 0) {
    $html .= '<table class="table">
        <thead>
            <tr>
                <th width="10%">Rang</th>
                <th width="35%">Name</th>
                <th width="10%" class="text-center">Geburtsdatum</th>
                <th width="12%" class="text-center">Endstich</th>
                <th width="11%" class="text-center">Schwini</th>
                <th width="11%" class="text-center">Zabig</th>
                <th width="11%" class="text-center">Total</th>
            </tr>
        </thead>
        <tbody>';
    
    $rang = 1;
    $lastGesamt = null;
    $lastEndstich = null;
    $lastTiefschuss = null;
    $sameRankCount = 0;
    
    foreach ($jungschuetzen as $index => $js) {
        // Rangierung
        if (
            $index > 0 &&
            $js['gesamt']   == $lastGesamt &&
            $js['endstich'] == $lastEndstich &&
            $js['schwini']  == $lastSchwini &&
            $js['alter']    == $lastAlter
        ) {
            $sameRankCount++;
        } else {
            if ($index > 0) {
                $rang += $sameRankCount;
            }
            $sameRankCount = 1;
        }

        $lastGesamt   = $js['gesamt'];
        $lastEndstich = $js['endstich'];
        $lastSchwini  = $js['schwini'];
        $lastAlter    = $js['alter'];

        
        $bold = ($rang <= 3) ? ' class="bold"' : '';
        
        $html .= '<tr>';
        $html .= '<td' . $bold . '>' . $rang . '.</td>';
        $html .= '<td' . $bold . '>' . htmlspecialchars($js['name']) . '</td>';
        $html .= '<td class="text-center"' . $bold . '>' . $js['geburt'] . ' </td>';
        $html .= '<td class="text-center"' . $bold . '>' . $js['endstich'];
        if ($js['tiefschuss'] > 0) {
            $html .= ' (' . $js['tiefschuss'] . ')';
        }
        $html .= '</td>';
        $html .= '<td class="text-center"' . $bold . '>' . $js['schwini'] . '</td>';
        $html .= '<td class="text-center"' . $bold . '>' . $js['zabig'] . '</td>';
        $html .= '<td class="text-center"' . $bold . '><strong>' . $js['gesamt'] . '</strong></td>';
        $html .= '</tr>';
        
        $lastGesamt = $js['gesamt'];
        $lastEndstich = $js['endstich'];
        $lastTiefschuss = $js['tiefschuss'];
    }
    
    $html .= '</tbody>
    </table>';
} else {
    $html .= '<p>Keine Ergebnisse für das Jahr ' . $year . ' gefunden.</p>';
}

$html .= '</div>
<div class="footer">
    &copy; ' . date("Y") . ' MSV Wilen. Alle Rechte vorbehalten. | Erstellt am ' . date('d.m.Y H:i') . '
</div>
</body>
</html>';

$stmt->close();
$conn->close();

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
header('Content-Type: application/json');
echo json_encode(['pdf_link' => $pdfFilePath]);
?>
