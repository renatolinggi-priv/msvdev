<?php
// export_averages_pdf.php
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
$definitionId = isset($_POST['definition_id']) ? intval($_POST['definition_id']) : 0;

if (empty($definitionId)) {
    echo json_encode(['success' => false, 'message' => 'Kein Anlass ausgewählt']);
    exit;
}

try {
    // Definition-Details laden
    $defSql = "SELECT ID, Bezeichnung, Maxpunkte, Zuschlag FROM JMDefinition WHERE ID = ? AND year = ?";
    $defStmt = $conn->prepare($defSql);
    $defStmt->bind_param("ii", $definitionId, $year);
    $defStmt->execute();
    $defResult = $defStmt->get_result();
    
    if ($defRow = $defResult->fetch_assoc()) {
        $anlassName = $defRow['Bezeichnung'];
        $zuschlag = $defRow['Zuschlag'] ?? 0;
        
        // Alle Resultate für diese Definition laden
        $resultSql = "SELECT jr.Punkte, m.Name, m.Vorname, m.ID as MitgliedID
                      FROM jmresultate jr
                      JOIN mitglieder m ON jr.mitgliederID = m.ID
                      WHERE jr.jmdefinitionID = ?
                      AND jr.Punkte > 0
                      AND m.status = 1
                      ORDER BY jr.Punkte DESC";
        
        $resultStmt = $conn->prepare($resultSql);
        $resultStmt->bind_param("i", $definitionId);
        $resultStmt->execute();
        $resultData = $resultStmt->get_result();
        
        $teilnehmerResultate = [];
        while ($row = $resultData->fetch_assoc()) {
            $teilnehmerResultate[] = [
                'mitglied_id' => $row['MitgliedID'],
                'name' => $row['Name'] . ' ' . $row['Vorname'],
                'punkte' => floatval($row['Punkte'])
            ];
        }
        
        $teilnehmerAnzahl = count($teilnehmerResultate);
        
        if ($teilnehmerAnzahl > 0) {
            $verwendeteResultate = calculateUsedResults($teilnehmerAnzahl);
            
            // Die besten X Resultate nehmen (zählende)
            $zaehlendeResultate = array_slice($teilnehmerResultate, 0, $verwendeteResultate);
            $nichtZaehlendeResultate = array_slice($teilnehmerResultate, $verwendeteResultate);
            
            // Summen berechnen
            $summeZaehlende = array_sum(array_column($zaehlendeResultate, 'punkte'));
            $summeNichtZaehlende = array_sum(array_column($nichtZaehlendeResultate, 'punkte'));
            
            // Neue Zuschlagsberechnung: (Summe_zählende + (Zuschlag * Summe_nicht_zählende) / 100) / Anzahl_zählende
            $zuschlagsBonus = ($zuschlag * $summeNichtZaehlende) / 100;
            $endergebnis = round(($summeZaehlende + $zuschlagsBonus) / $verwendeteResultate, 3);
            
            // Klassischer Durchschnitt für Anzeige
            $durchschnitt = round($summeZaehlende / $verwendeteResultate, 2);
            
            $result = [
                'anlass_name' => $anlassName,
                'teilnehmer_anzahl' => $teilnehmerAnzahl,
                'verwendete_resultate' => $verwendeteResultate,
                'durchschnitt' => $durchschnitt,
                'zuschlag' => $zuschlag,
                'endergebnis' => $endergebnis,
                'alle_resultate' => $teilnehmerResultate
            ];
            
            // HTML für PDF generieren
            $html = generatePdfHtml($result, $year);
        } else {
            echo json_encode(['success' => false, 'message' => 'Keine Resultate für diesen Anlass gefunden']);
            exit;
        }
        
        $resultStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Anlass nicht gefunden']);
        exit;
    }
    
    $defStmt->close();
    
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
    $filename = "JM_Vereinsabrechnung_" . preg_replace('/[^a-zA-Z0-9]/', '_', $result['anlass_name']) . "_" . $year . ".pdf";
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
        'pdf_url' => 'jmdurchschnitt/dat/' . $filename,
        'filename' => $filename,
        'message' => 'PDF erfolgreich generiert'
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in export_averages_pdf.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim PDF-Export: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}

/**
 * Berechnet die Anzahl der zu verwendenden Resultate
 */
function calculateUsedResults($teilnehmerAnzahl) {
    if ($teilnehmerAnzahl <= 13) {
        return min(6, $teilnehmerAnzahl);
    } else {
        // Ab 14 Teilnehmer: die Hälfte der Resultate (abgerundet)
        return intval(floor($teilnehmerAnzahl / 2));
    }
}

/**
 * Generiert das HTML für das PDF im Stil der Vereinsabrechnung
 */
function generatePdfHtml($result, $year) {
    $currentDate = date('d.m.Y H:i');
    
    // Pflichtteilnehmer und Nicht-Pflichtteilnehmer trennen
    $pflichtteilnehmer = array_slice($result['alle_resultate'], 0, $result['verwendete_resultate']);
    $nichtPflichtteilnehmer = array_slice($result['alle_resultate'], $result['verwendete_resultate']);
    
    // Summen berechnen
    $summePflicht = array_sum(array_column($pflichtteilnehmer, 'punkte'));
    $summeNichtPflicht = array_sum(array_column($nichtPflichtteilnehmer, 'punkte'));
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($result['anlass_name']) . ' - Vereinsabrechnung</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 11px;
                margin: 15px;
                line-height: 1.3;
            }
            
            .header {
                text-align: center;
                margin-bottom: 40px;
            }
            
            .header h1 {
                font-size: 18px;
                margin: 0 0 5px 0;
                font-weight: bold;
            }
            
            .header h2 {
                font-size: 14px;
                margin: 0 0 20px 0;
                font-weight: normal;
            }
            
            .vereinsabrechnung {
                margin: 30px 0 20px 0;
                font-weight: bold;
                font-size: 12px;
            }
            
            .wettkampf-info {
                margin-bottom: 15px;
            }
            
            .wettkampf-info table {
                border: none;
                margin-left: 20px;
            }
            
            .wettkampf-info td {
                padding: 2px 30px 2px 0;
                border: none;
                vertical-align: top;
            }
            
            .section-title {
                font-weight: bold;
                margin: 20px 0 10px 20px;
                text-decoration: underline;
            }
            
            .results-table {
                width: 100%;
                border-collapse: collapse;
                margin: 10px 0;
                font-size: 10px;
            }
            
            .results-table th,
            .results-table td {
                border: 1px solid #000;
                padding: 4px 6px;
                text-align: left;
            }
            
            .results-table th {
                background-color: #f0f0f0;
                font-weight: bold;
                text-align: center;
            }
            
            .results-table .name-col { width: 70%; }
            .results-table .points-col { width: 30%; text-align: right; }
            
            .total-row {
                font-weight: bold;
                border-top: 2px solid #000;
            }
            
            .calculation {
                margin: 30px 0;
                text-align: center;
                font-size: 12px;
            }
            
            .calculation-line {
                margin: 5px 0;
                padding: 5px;
                border: 1px solid #ccc;
                background-color: #f9f9f9;
            }
            
            .final-result {
                font-weight: bold;
                font-size: 14px;
                background-color: #e0e0e0;
                padding: 8px;
                border: 2px solid #000;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . htmlspecialchars($result['anlass_name']) . '</h1>
            <h2>Militärschützenverein Wilen</h2>
        </div>
        
        <div class="vereinsabrechnung">Vereinsabrechnung</div>
        
        <div class="wettkampf-info">
            <strong>Vereinswettkampf</strong>
            <table>
                <tr>
                    <td>Vereinskategorie</td>
                    <td>4</td>
                </tr>
                <tr>
                    <td>Vereinsschützen / Pflichtteilnehmer</td>
                    <td>' . $result['teilnehmer_anzahl'] . ' / ' . $result['verwendete_resultate'] . '</td>
                </tr>
            </table>
        </div>';
    
    // Pflichtteilnehmer Tabelle
    if (!empty($pflichtteilnehmer)) {
        $html .= '
        <div class="section-title">Pflichtteilnehmer</div>
        <table class="results-table">
            <thead>
                <tr>
                    <th class="name-col">Name</th>
                    <th class="points-col">Punkte</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($pflichtteilnehmer as $teilnehmer) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($teilnehmer['name']) . '</td>
                    <td class="points-col">' . number_format($teilnehmer['punkte'], 0) . '</td>
                </tr>';
        }
        
        $html .= '
                <tr class="total-row">
                    <td>Summe Pflichtteilnehmer</td>
                    <td class="points-col">' . number_format($summePflicht, 0) . '</td>
                </tr>
            </tbody>
        </table>';
    }
    
    // Nicht-Pflichtteilnehmer Tabelle
    if (!empty($nichtPflichtteilnehmer)) {
        $html .= '
        <div class="section-title">Nicht-Pflichtteilnehmer</div>
        <table class="results-table">
            <thead>
                <tr>
                    <th class="name-col">Name</th>
                    <th class="points-col">Punkte</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($nichtPflichtteilnehmer as $teilnehmer) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($teilnehmer['name']) . '</td>
                    <td class="points-col">' . number_format($teilnehmer['punkte'], 0) . '</td>
                </tr>';
        }
        
        $html .= '
                <tr class="total-row">
                    <td>Summe Nicht-Pflichtteilnehmer</td>
                    <td class="points-col">' . number_format($summeNichtPflicht, 0) . '</td>
                </tr>
            </tbody>
        </table>';
    }
    
    // Berechnung - Formel
    $zuschlagsProzent = str_replace('%', '', $result['zuschlag']); // % entfernen für Berechnung
    $zuschlagsBonus = ($zuschlagsProzent * $summeNichtPflicht) / 100;
    $gesamtsumme = $summePflicht + $zuschlagsBonus;
    
    $html .= '
        <div class="calculation">
            <div class="calculation-line" style="font-weight: bold; background-color: #f0f0f0; margin-bottom: 10px;">
                Berechnungsformel:
            </div>
            <div class="calculation-line">
                Endergebnis = (Summe Pflichtteilnehmer + Beteiligungszuschlag × Summe Nicht-Pflichtteilnehmer ÷ 100) ÷ Anzahl Pflichtteilnehmer
            </div>
            <div class="calculation-line">
                Endergebnis = (' . number_format($summePflicht, 0) . ' + ' . $zuschlagsProzent . '% × ' . number_format($summeNichtPflicht, 0) . ' ÷ 100) ÷ ' . $result['verwendete_resultate'] . '
            </div>
            <div class="final-result">
                Endergebnis: ' . number_format($result['endergebnis'], 3) . ' Punkte
            </div>
        </div>
        
        <div style="margin-top: 40px; font-size: 9px; color: #666;">
            Generiert am ' . $currentDate . ' - MSV Wilen
        </div>
    </body>
    </html>';
    
    return $html;
}
?>