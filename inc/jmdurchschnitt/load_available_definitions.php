<?php
// load_available_definitions.php
include '../config.php';

// Überprüfen der Datenbankverbindung
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// Jahr aus GET-Parameter
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

try {
    // JMDefinitionen laden mit Info = 0 (normale Anlässe, keine Info-Einträge)
    // Sortiert nach Reihenfolge
    $sql = "SELECT ID, Bezeichnung, Maxpunkte, Zuschlag, Reihenfolge 
            FROM JMDefinition 
            WHERE year = ? 
            AND hidden = 0 
            AND Info = 0 
            AND Erweitert = 0
            ORDER BY Reihenfolge ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Fehler beim Vorbereiten der SQL-Abfrage: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $year);
    
    if (!$stmt->execute()) {
        throw new Exception('Fehler beim Ausführen der SQL-Abfrage: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $definitions = [];
    
    while ($row = $result->fetch_assoc()) {
        // Prüfen ob für diese Definition Resultate existieren
        $resultCheckSql = "SELECT COUNT(*) as count FROM jmresultate WHERE jmdefinitionID = ? AND Punkte > 0";
        $resultCheckStmt = $conn->prepare($resultCheckSql);
        $resultCheckStmt->bind_param("i", $row['ID']);
        $resultCheckStmt->execute();
        $resultCheck = $resultCheckStmt->get_result()->fetch_assoc();
        
        // Nur Definitionen mit vorhandenen Resultaten hinzufügen
        if ($resultCheck['count'] > 0) {
            $definitions[] = [
                'ID' => $row['ID'],
                'Bezeichnung' => $row['Bezeichnung'],
                'Maxpunkte' => $row['Maxpunkte'],
                'Zuschlag' => ($row['Zuschlag'] ?? 0) . '%',
                'Reihenfolge' => $row['Reihenfolge'],
                'result_count' => $resultCheck['count']
            ];
        }
        
        $resultCheckStmt->close();
    }
    
    $stmt->close();
    
    // Erfolgreiche Antwort
    echo json_encode([
        'success' => true,
        'definitions' => $definitions,
        'year' => $year,
        'count' => count($definitions)
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in load_available_definitions.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Laden der verfügbaren Anlässe: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>