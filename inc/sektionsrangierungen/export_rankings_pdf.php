<?php
// export_rankings_pdf.php
session_start();
include '../config.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Überprüfen der Datenbankverbindung
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// CSRF Token prüfen
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF Token']);
    exit;
}

// Parameter aus POST
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

try {
    // Rangierungen für das Jahr laden (sortiert nach Datum/Reihenfolge der Anlässe)
    $sql = "SELECT sr.rang, sr.preis, jd.Bezeichnung as bezeichnung, jd.Reihenfolge, jd.Schiesstage
            FROM sektionsrangierungen sr
            JOIN JMDefinition jd ON sr.jmdefinition_id = jd.ID
            WHERE sr.year = ?
            ORDER BY jd.Reihenfolge ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Fehler beim Vorbereiten der SQL-Abfrage: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $year);
    
    if (!$stmt->execute()) {
        throw new Exception('Fehler beim Ausführen der SQL-Abfrage: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $rankings = [];
    
    while ($row = $result->fetch_assoc()) {
        $rankings[] = [
            'rang' => $row['rang'],
            'preis' => $row['preis'],
            'bezeichnung' => $row['bezeichnung'],
            'reihenfolge' => $row['Reihenfolge'],
            'schiesstage' => $row['Schiesstage']
        ];
    }
    
    $stmt->close();
    
    if (empty($rankings)) {
        echo json_encode(['success' => false, 'message' => 'Keine Rangierungen für dieses Jahr gefunden']);
        exit;
    }
    
    // HTML für PDF generieren
    $html = generatePdfHtml($rankings, $year);
    
    // PDF-Optionen konfigurieren
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    
    // DOMPDF initialisieren
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Dateiname generieren
    $filename = "Sektionsrangierungen_" . $year . "_" . date('Y-m-d_H-i-s') . ".pdf";
    $filepath = '../temp/' . $filename;
    
    // Temp-Verzeichnis erstellen falls nicht vorhanden
    if (!file_exists('../temp/')) {
        if (!mkdir('../temp/', 0777, true)) {
            echo json_encode(['success' => false, 'message' => 'Temp-Verzeichnis konnte nicht erstellt werden']);
            exit;
        }
    }
    
    // PDF speichern
    $pdfOutput = $dompdf->output();
    if (file_put_contents($filepath, $pdfOutput) === false) {
        echo json_encode(['success' => false, 'message' => 'PDF konnte nicht gespeichert werden']);
        exit;
    }
    
    // Erfolgreiche Antwort
    echo json_encode([
        'success' => true,
        'pdf_url' => 'temp/' . $filename,
        'filename' => $filename,
        'message' => 'PDF erfolgreich generiert'
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in export_rankings_pdf.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim PDF-Export: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}

/**
 * Generiert das HTML für das PDF
 */
function generatePdfHtml($rankings, $year) {
    $currentDate = date('d.m.Y H:i');
    
    // Statistiken berechnen
    $totalRankings = count($rankings);
    $totalPrizes = array_sum(array_column($rankings, 'preis'));
    
    // Rangverteilung
    $rankDistribution = [];
    foreach ($rankings as $ranking) {
        $rang = $ranking['rang'];
        $rankDistribution[$rang] = ($rankDistribution[$rang] ?? 0) + 1;
    }
    ksort($rankDistribution);
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sektionsrangierungen ' . $year . '</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 11px;
                margin: 15px;
                line-height: 1.4;
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #333;
                padding-bottom: 15px;
            }
            
            .header h1 {
                font-size: 20px;
                margin: 0 0 10px 0;
                font-weight: bold;
                color: #333;
            }
            
            .header h2 {
                font-size: 16px;
                margin: 0 0 15px 0;
                font-weight: normal;
                color: #666;
            }
            
            .stats-box {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                padding: 15px;
                margin-bottom: 25px;
                overflow: hidden;
            }
            
            .stats-grid {
                display: table;
                width: 100%;
            }
            
            .stats-item {
                display: table-cell;
                text-align: center;
                vertical-align: top;
                padding: 5px;
                border-right: 1px solid #dee2e6;
            }
            
            .stats-item:last-child {
                border-right: none;
            }
            
            .stats-label {
                font-weight: bold;
                font-size: 10px;
                color: #666;
                text-transform: uppercase;
                margin-bottom: 3px;
            }
            
            .stats-value {
                font-size: 16px;
                font-weight: bold;
                color: #007bff;
            }
            
            .rankings-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            .rankings-table th,
            .rankings-table td {
                border: 1px solid #000;
                padding: 8px 6px;
                text-align: left;
                vertical-align: top;
            }
            
            .rankings-table th {
                background-color: #f0f0f0;
                font-weight: bold;
                text-align: center;
                font-size: 10px;
            }
            
            .rankings-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            
            .anlass-col { width: 45%; }
            .rang-col { width: 15%; text-align: center; }
            .preis-col { width: 20%; text-align: right; }
            .datum-col { width: 20%; }
            
            .rang-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 12px;
                font-weight: bold;
                font-size: 10px;
                color: white;
                text-align: center;
                min-width: 20px;
            }
            
            .rang-1 { background-color: #ffd700; color: #333; }
            .rang-2 { background-color: #c0c0c0; color: #333; }
            .rang-3 { background-color: #cd7f32; color: white; }
            .rang-other { background-color: #6c757d; }
            
            .preis-amount {
                font-weight: bold;
                color: #28a745;
            }
            
            .datum-info {
                font-size: 9px;
                color: #666;
                line-height: 1.2;
            }
            
            .summary-section {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ccc;
            }
            
            .summary-title {
                font-weight: bold;
                margin-bottom: 15px;
                font-size: 12px;
            }
            
            .rank-summary {
                display: table;
                width: 100%;
                margin-bottom: 15px;
            }
            
            .rank-item {
                display: table-cell;
                text-align: center;
                padding: 8px;
                border: 1px solid #dee2e6;
                background-color: #f8f9fa;
                width: 20%;
            }
            
            .footer {
                position: fixed;
                bottom: 15px;
                left: 15px;
                right: 15px;
                text-align: center;
                font-size: 9px;
                color: #666;
                border-top: 1px solid #ccc;
                padding-top: 8px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Sektionsrangierungen ' . $year . '</h1>
            <h2>Militärschützenverein Wilen</h2>
            <p style="font-size: 10px; color: #666;">Erstellt am: ' . $currentDate . '</p>
        </div>
        
        <div class="stats-box">
            <div class="stats-grid">
                <div class="stats-item">
                    <div class="stats-label">Total Anlässe</div>
                    <div class="stats-value">' . $totalRankings . '</div>
                </div>
                <div class="stats-item">
                    <div class="stats-label">Preissumme</div>
                    <div class="stats-value">CHF ' . number_format($totalPrizes, 2) . '</div>
                </div>
                <div class="stats-item">
                    <div class="stats-label">Ø Preis</div>
                    <div class="stats-value">CHF ' . number_format($totalPrizes / $totalRankings, 2) . '</div>
                </div>
            </div>
        </div>
        
        <table class="rankings-table">
            <thead>
                <tr>
                    <th class="anlass-col">Anlass</th>
                    <th class="rang-col">Rang</th>
                    <th class="preis-col">Preis</th>
                    <th class="datum-col">Datum</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($rankings as $ranking) {
        $rang = $ranking['rang'];
        $rangClass = '';
        if ($rang == 1) $rangClass = 'rang-1';
        elseif ($rang == 2) $rangClass = 'rang-2';
        elseif ($rang == 3) $rangClass = 'rang-3';
        else $rangClass = 'rang-other';
        
        // Datum aus Schiesstage extrahieren (erste Zeile)
        $datum = '';
        if (!empty($ranking['schiesstage'])) {
            $lines = explode("\n", trim($ranking['schiesstage']));
            if (!empty($lines[0])) {
                // Versuche Datum zu extrahieren
                preg_match('/(\d{1,2}\.\s*\w+\s*\d{4})/', $lines[0], $matches);
                $datum = $matches[1] ?? substr($lines[0], 0, 30) . '...';
            }
        }
        
        $html .= '
                <tr>
                    <td class="anlass-col">' . htmlspecialchars($ranking['bezeichnung']) . '</td>
                    <td class="rang-col">
                        <span class="rang-badge ' . $rangClass . '">' . $rang . '</span>
                    </td>
                    <td class="preis-col">
                        <span class="preis-amount">CHF ' . number_format($ranking['preis'], 2) . '</span>
                    </td>
                    <td class="datum-col">
                        <div class="datum-info">' . htmlspecialchars($datum) . '</div>
                    </td>
                </tr>';
    }
    
    // Total-Zeile hinzufügen
    $html .= '
                <tr style="background-color: #f0f0f0; border-top: 2px solid #000; font-weight: bold;">
                    <td class="anlass-col" style="font-weight: bold;">TOTAL</td>
                    <td class="rang-col" style="text-align: center;">-</td>
                    <td class="preis-col" style="font-weight: bold;">
                        <span class="preis-amount">CHF ' . number_format($totalPrizes, 2) . '</span>
                    </td>
                    <td class="datum-col" style="text-align: center;">-</td>
                </tr>';
    
    $html .= '
            </tbody>
        </table>
        
        <div class="summary-section">
            <div class="summary-title">Rangverteilung:</div>
            <div class="rank-summary">';
    
    foreach ($rankDistribution as $rang => $count) {
        $html .= '
                <div class="rank-item">
                    <strong>' . $rang . '. Rang</strong><br>
                    ' . $count . ' mal
                </div>';
    }
    
    $html .= '
            </div>
        </div>
        
        <div class="footer">
            MSV Wilen - Sektionsrangierungen ' . $year . ' - Generiert am ' . $currentDate . '
        </div>
    </body>
    </html>';
    
    return $html;
}
?>