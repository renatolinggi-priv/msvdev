<?php
// generate_wanderpreise_jahresreport.php
require_once '../dbconnect.inc.php';
require_once 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$conn = get_db_connection();
if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// Parameter
$type = $_GET['type'] ?? 'jahresreport';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$hersteller = $_GET['hersteller'] ?? '';

// Spezialbehandlung für Akura Einsiedeln - Gravur-Auftrag
if ($hersteller === 'Akura Einsiedeln') {
    
    // Hole das Absenden-Datum aus JMDefinition für das aktuelle Jahr
    $sql_absenden = "SELECT Schiesstage FROM JMDefinition 
                     WHERE Bezeichnung = 'Absenden' 
                     AND year = ?
                     LIMIT 1";
    $stmt_absenden = $conn->prepare($sql_absenden);
    $stmt_absenden->bind_param("i", $year);
    $stmt_absenden->execute();
    $result_absenden = $stmt_absenden->get_result();
    $absenden_data = $result_absenden->fetch_assoc();
    
    // Standard-Datum falls nicht gefunden
    $gravur_datum = "15. November " . $year;
    
    if ($absenden_data && $absenden_data['Schiesstage']) {
        // Parse das Datum aus dem Schiesstage-Feld
        if (preg_match('/(\d{1,2})\.\s*(\w+)/', $absenden_data['Schiesstage'], $matches)) {
            $tag = intval($matches[1]);
            $monat = $matches[2];
            
            // Berechne den Freitag davor
            $monate = [
                'Januar' => 1, 'Februar' => 2, 'März' => 3, 'April' => 4,
                'Mai' => 5, 'Juni' => 6, 'Juli' => 7, 'August' => 8,
                'September' => 9, 'Oktober' => 10, 'November' => 11, 'Dezember' => 12
            ];
            
            if (isset($monate[$monat])) {
                $datum = mktime(0, 0, 0, $monate[$monat], $tag, $year);
                $wochentag = date('w', $datum);
                
                // Berechne den vorherigen Freitag
                if ($wochentag == 6) { // Samstag
                    $freitag = $datum - 86400; // Ein Tag zurück
                } elseif ($wochentag == 0) { // Sonntag
                    $freitag = $datum - 2 * 86400; // Zwei Tage zurück
                } else {
                    // Andere Wochentage - zum vorherigen Freitag zurück
                    $tage_zurueck = ($wochentag + 2) % 7;
                    if ($tage_zurueck == 0) $tage_zurueck = 7;
                    $freitag = $datum - ($tage_zurueck * 86400);
                }
                
                $gravur_datum = date('j', $freitag) . '. ' . $monat . ' ' . $year;
            }
        }
    }
    
    // Hole alle Wanderpreise von Akura Einsiedeln mit Gewinnern für das aktuelle Jahr
    $sql = "SELECT 
                w.id,
                w.bezeichnung,
                wh.jahr,
                CONCAT(m.Name, ' ', m.Vorname) as gewinner_name
            FROM wanderpreise w
            LEFT JOIN wanderpreis_historie wh ON w.id = wh.wanderpreis_id AND wh.jahr = ?
            LEFT JOIN mitglieder m ON wh.gewinner_id = m.ID
            WHERE w.hersteller = 'Akura Einsiedeln'
            ORDER BY w.bezeichnung ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // HTML für Gravur-PDF erstellen
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page {
                margin: 2cm;
            }
            body {
                font-family: "DejaVu Sans", Arial, sans-serif;
                font-size: 11pt;
                line-height: 1.6;
                color: #333;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #003366;
                padding-bottom: 15px;
            }
            h1 {
                color: #003366;
                font-size: 18pt;
                margin: 10px 0;
                font-weight: bold;
            }
            h2 {
                color: #003366;
                font-size: 16pt;
                margin: 5px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            th {
                background-color: #003366;
                color: white;
                padding: 10px;
                text-align: left;
                font-weight: bold;
            }
            td {
                padding: 8px 10px;
                border-bottom: 1px solid #ddd;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .wanderpreis-name {
                font-weight: bold;
                color: #003366;
            }
            .gravur-info {
                color: #666;
            }
            .no-winner {
                color: #999;
                font-style: italic;
            }
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }
            .rechnung-box {
                background-color: #f0f0f0;
                padding: 15px;
                border-left: 4px solid #003366;
                margin-top: 30px;
            }
            .rechnung-titel {
                font-weight: bold;
                font-size: 12pt;
                color: #003366;
                margin-bottom: 10px;
            }
            .rechnung-adresse {
                line-height: 1.4;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Wanderpreise gravieren bis: Freitag, ' . $gravur_datum . '</h1>
            <h2>MSV Wilen</h2>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 40%;">Wanderpreis</th>
                    <th style="width: 60%;">Gravur-Information</th>
                </tr>
            </thead>
            <tbody>';
    
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $count++;
        $gravur_text = '';
        
        if ($row['gewinner_name'] && $row['jahr']) {
            $gravur_text = '<span class="gravur-info">gravieren: </span>' . 
                          $row['jahr'] . ' ' . $row['gewinner_name'];
        } else {
            $gravur_text = '<span class="no-winner">Noch kein Gewinner für ' . $year . '</span>';
        }
        
        $html .= '
                <tr>
                    <td class="wanderpreis-name">' . htmlspecialchars($row['bezeichnung']) . '</td>
                    <td>' . $gravur_text . '</td>
                </tr>';
    }
    
    if ($count == 0) {
        $html .= '
                <tr>
                    <td colspan="2" style="text-align: center; padding: 20px; color: #999;">
                        Keine Wanderpreise von Akura Einsiedeln gefunden
                    </td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
        
        <div class="rechnung-box">
            <div class="rechnung-titel">Rechnung senden an:</div>
            <div class="rechnung-adresse">
                <strong>Schober Marco</strong><br>
                Sihlboden 2<br>
                8847 Egg<br>
                Tel. 079 519 11 88
            </div>
        </div>
        
        <div class="footer">
            <p style="text-align: center; color: #999; font-size: 9pt;">
                Erstellt am ' . date('d.m.Y') . ' • MSV Wilen • Seite 1
            </p>
        </div>
    </body>
    </html>';
    
    $stmt->close();
    
} else {
    // STANDARD JAHRESREPORT CODE (wie vorher)
    // Hier kommt der normale Code für Jahresreport, Schnitzerei, etc.
    
    // SQL-Query je nach Hersteller
    $sql = "SELECT 
                w.id,
                w.bezeichnung,
                w.beschreibung,
                w.beschaffung_datum,
                w.min_anzahl_gewinne,
                w.hersteller,
                COUNT(wh.id) as anzahl_gewinner,
                CASE 
                    WHEN COUNT(wh.id) >= w.min_anzahl_gewinne THEN 'Definitiv'
                    ELSE 'Wandernd'
                END as status
            FROM wanderpreise w
            LEFT JOIN wanderpreis_historie wh ON w.id = wh.wanderpreis_id
            WHERE 1=1";
    
    if ($hersteller) {
        $sql .= " AND w.hersteller = ?";
    }
    
    $sql .= " GROUP BY w.id ORDER BY w.bezeichnung ASC";
    
    $stmt = $conn->prepare($sql);
    if ($hersteller) {
        $stmt->bind_param("s", $hersteller);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Standard HTML für Jahresreport
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 2cm; }
            body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10pt; }
            .header { text-align: center; margin-bottom: 20px; }
            h1 { color: #003366; font-size: 18pt; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #003366; color: white; padding: 8px; text-align: left; }
            td { padding: 6px; border-bottom: 1px solid #ddd; }
            .status-definitiv { color: green; font-weight: bold; }
            .status-wandernd { color: orange; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Wanderpreise Jahresreport ' . $year . '</h1>';
    
    if ($hersteller) {
        $html .= '<h2>' . htmlspecialchars($hersteller) . '</h2>';
    }
    
    $html .= '</div>
        <table>
            <thead>
                <tr>
                    <th>Bezeichnung</th>
                    <th>Beschreibung</th>
                    <th>Jahr</th>
                    <th>Min. Gewinne</th>
                    <th>Akt. Gewinner</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
    
    while ($row = $result->fetch_assoc()) {
        $statusClass = $row['status'] == 'Definitiv' ? 'status-definitiv' : 'status-wandernd';
        $html .= '<tr>
            <td>' . htmlspecialchars($row['bezeichnung']) . '</td>
            <td>' . htmlspecialchars($row['beschreibung'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['beschaffung_datum']) . '</td>
            <td>' . htmlspecialchars($row['min_anzahl_gewinne']) . '</td>
            <td>' . htmlspecialchars($row['anzahl_gewinner']) . '</td>
            <td class="' . $statusClass . '">' . htmlspecialchars($row['status']) . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table></body></html>';
    $stmt->close();
}

// PDF generieren
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Dateiname anpassen
if ($hersteller === 'Akura Einsiedeln') {
    $filename = 'Akura_Gravur_' . $year . '_' . date('Ymd') . '.pdf';
} else {
    $filename = 'Wanderpreise_Jahresreport_' . $year . '_' . date('Ymd') . '.pdf';
}

// PDF speichern und Link zurückgeben
$pdfDir = 'temp/';
if (!file_exists($pdfDir)) {
    mkdir($pdfDir, 0777, true);
}

$pdfPath = $pdfDir . $filename;
file_put_contents($pdfPath, $dompdf->output());

// JSON Response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'pdf_link' => $pdfPath,
    'filename' => $filename
]);

$conn->close();
?>