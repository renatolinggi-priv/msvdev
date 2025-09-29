<?php
// calculate_averages.php
session_start();
include '../config.php';

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
            // Berechnung: Wie viele Resultate werden verwendet?
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
            
            // Erfolgreiche Antwort
            echo json_encode([
                'success' => true,
                'result' => [
                    'anlass_id' => $definitionId,
                    'anlass_name' => $anlassName,
                    'teilnehmer_anzahl' => $teilnehmerAnzahl,
                    'verwendete_resultate' => $verwendeteResultate,
                    'durchschnitt' => number_format($durchschnitt, 2),
                    'zuschlag' => $zuschlag . '%',
                    'endergebnis' => number_format($endergebnis, 3),
                    'alle_resultate' => $teilnehmerResultate
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Keine Resultate für diesen Anlass gefunden'
            ]);
        }
        
        $resultStmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Anlass nicht gefunden'
        ]);
    }
    
    $defStmt->close();
    
} catch (Exception $e) {
    error_log("Fehler in calculate_averages.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler bei der Berechnung: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}

/**
 * Berechnet die Anzahl der zu verwendenden Resultate basierend auf der Teilnehmerzahl
 * 
 * @param int $teilnehmerAnzahl Anzahl der Teilnehmer
 * @return int Anzahl der zu verwendenden besten Resultate
 */
function calculateUsedResults($teilnehmerAnzahl) {
    if ($teilnehmerAnzahl <= 13) {
        // Bis 13 Teilnehmer: die besten 6 Resultate
        return min(6, $teilnehmerAnzahl);
    } else {
        // Ab 14 Teilnehmer: die Hälfte der Resultate (abgerundet)
        return intval(floor($teilnehmerAnzahl / 2));
    }
}
?>