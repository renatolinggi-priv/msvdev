<?php
// export_rankings_pdf.php
session_start();
include '../config.php';
require_once '../vendor/autoload.php';
require_once __DIR__ . '/../csrf.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Überprüfen der Datenbankverbindung
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// CSRF Token prüfen
csrf_require(true);

// Parameter aus POST
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

try {
    // Einzelrangierungen für das Jahr laden (sortiert nach Anlass-Reihenfolge, dann nach Rang)
    $sql = "SELECT er.rang, er.resultat, er.preis,
                   jd.Bezeichnung as anlass_bezeichnung, jd.Reihenfolge, jd.Schiesstage,
                   CONCAT(m.Name, ' ', m.Vorname) as mitglied_name
            FROM einzelrangierungen er
            JOIN JMDefinition jd ON er.jmdefinition_id = jd.ID
            JOIN mitglieder m ON er.mitglied_id = m.ID
            WHERE er.year = ?
            ORDER BY jd.Reihenfolge ASC, er.rang ASC";
    
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
            'resultat' => $row['resultat'],
            'preis' => $row['preis'],
            'anlass_bezeichnung' => $row['anlass_bezeichnung'],
            'mitglied_name' => $row['mitglied_name'],
            'reihenfolge' => $row['Reihenfolge'],
            'schiesstage' => $row['Schiesstage']
        ];
    }
    
    $stmt->close();
    
    if (empty($rankings)) {
        echo json_encode(['success' => false, 'message' => 'Keine Einzelrangierungen für dieses Jahr gefunden']);
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
    $filename = "Einzelrangierungen_" . $year . "_" . date('Y-m-d_H-i-s') . ".pdf";
    $filepath = 'dat/' . $filename;

    // dat-Verzeichnis erstellen falls nicht vorhanden
    if (!file_exists('dat/')) {
        if (!mkdir('dat/', 0777, true)) {
            echo json_encode(['success' => false, 'message' => 'dat-Verzeichnis konnte nicht erstellt werden']);
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
        'pdf_url' => 'einzelrangierung/dat/' . $filename,
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

    // Vereinslogo als Base64 einbetten (robuster als externe URL)
    $logoBase64 = '';
    $logoPath = __DIR__ . '/dat/MSVWilen_Logo.jpg';
    if (file_exists($logoPath)) {
        $logoBase64 = 'data:' . mime_content_type($logoPath) . ';base64,' . base64_encode(file_get_contents($logoPath));
    }
    
    // Statistiken berechnen
    $totalRankings = count($rankings);
    $totalPrizes = array_sum(array_column($rankings, 'preis'));
    
    // Nach Anlässen gruppieren für bessere Übersicht
    $groupedRankings = [];
    foreach ($rankings as $ranking) {
        $anlassKey = $ranking['anlass_bezeichnung'];
        if (!isset($groupedRankings[$anlassKey])) {
            $groupedRankings[$anlassKey] = [
                'anlass' => $ranking['anlass_bezeichnung'],
                'reihenfolge' => $ranking['reihenfolge'],
                'schiesstage' => $ranking['schiesstage'],
                'rankings' => []
            ];
        }
        $groupedRankings[$anlassKey]['rankings'][] = $ranking;
    }
    
    // Rangverteilung berechnen
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
        <title>Einzelrangierungen ' . $year . '</title>
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
                border-bottom: 2px solid #cbd5e0;
                padding-bottom: 15px;
            }

            .header .logo {
                width: 90px;
                height: auto;
                margin-bottom: 10px;
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
                color: #3b5998;
            }
            
            .anlass-section {
                margin-bottom: 25px;
                page-break-inside: avoid;
            }
            
            .anlass-header {
                background-color: #e9ecef;
                padding: 10px;
                margin-bottom: 10px;
                border-left: 4px solid #3b5998;
                font-weight: bold;
                font-size: 12px;
            }
            
            .rankings-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            .rankings-table th,
            .rankings-table td {
                border: 1px solid #e2e8f0;
                padding: 6px 8px;
                text-align: left;
                vertical-align: top;
            }

            .rankings-table th {
                background-color: #eef2f7;
                color: #2d3748;
                font-weight: bold;
                text-align: center;
                font-size: 10px;
            }
            
            .rankings-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            
            .mitglied-col { width: 40%; }
            .rang-col { width: 15%; text-align: center; }
            .resultat-col { width: 25%; text-align: center; }
            .preis-col { width: 20%; text-align: right; }
            
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
            
            .rang-1 { background-color: #fdf6e3; color: #8a6d1c; }
            .rang-2 { background-color: #f1f1f1; color: #6b7280; }
            .rang-3 { background-color: #f7ede2; color: #9c6b3f; }
            .rang-other { background-color: #eef2f7; color: #64748b; }

            .preis-amount {
                font-weight: bold;
                color: #2f855a;
            }
            
            .summary-section {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ccc;
                page-break-inside: avoid;
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
                width: 16.66%;
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
            ' . ($logoBase64 ? '<img src="' . $logoBase64 . '" class="logo" alt="MSV Wilen Logo">' : '') . '
            <h1>Einzelrangierungen ' . $year . '</h1>
            <h2>Militärschützenverein Wilen</h2>
            <p style="font-size: 10px; color: #666;">Erstellt am: ' . $currentDate . '</p>
        </div>
        
        <div class="stats-box">
            <div class="stats-grid">
                <div class="stats-item">
                    <div class="stats-label">Total Rangierungen</div>
                    <div class="stats-value">' . $totalRankings . '</div>
                </div>
                <div class="stats-item">
                    <div class="stats-label">Anzahl Anlässe</div>
                    <div class="stats-value">' . count($groupedRankings) . '</div>
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
        </div>';
    
    // Anlässe und Rangierungen anzeigen
    foreach ($groupedRankings as $group) {
        // Datum aus Schiesstage extrahieren (erste Zeile)
        $datum = '';
        if (!empty($group['schiesstage'])) {
            $lines = explode("\n", trim($group['schiesstage']));
            if (!empty($lines[0])) {
                preg_match('/(\d{1,2}\.\s*\w+\s*\d{4})/', $lines[0], $matches);
                $datum = $matches[1] ?? substr($lines[0], 0, 25);
            }
        }
        
        $html .= '
        <div class="anlass-section">
            <div class="anlass-header">
                ' . htmlspecialchars($group['anlass']) . 
                ($datum ? ' - ' . htmlspecialchars($datum) : '') . '
            </div>
            
            <table class="rankings-table">
                <thead>
                    <tr>
                        <th class="mitglied-col">Mitglied</th>
                        <th class="rang-col">Rang</th>
                        <th class="resultat-col">Resultat</th>
                        <th class="preis-col">Preis</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($group['rankings'] as $ranking) {
            $rang = $ranking['rang'];
            $rangClass = '';
            if ($rang == 1) $rangClass = 'rang-1';
            elseif ($rang == 2) $rangClass = 'rang-2';
            elseif ($rang == 3) $rangClass = 'rang-3';
            else $rangClass = 'rang-other';
            
            $html .= '
                    <tr>
                        <td class="mitglied-col">' . htmlspecialchars($ranking['mitglied_name']) . '</td>
                        <td class="rang-col">
                            <span class="rang-badge ' . $rangClass . '">' . $rang . '</span>
                        </td>
                        <td class="resultat-col">' . htmlspecialchars($ranking['resultat']) . '</td>
                        <td class="preis-col">
                            <span class="preis-amount">CHF ' . number_format($ranking['preis'], 2) . '</span>
                        </td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
        </div>';
    }
    
    $html .= '
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
            MSV Wilen - Einzelrangierungen ' . $year . ' - Generiert am ' . $currentDate . '
        </div>
    </body>
    </html>';
    
    return $html;
}
?>