<?php
// load_available_definitions.php
session_start();
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
    // JMDefinitionen laden mit Info = 0 (normale Anlässe) die noch keine Rangierung haben
    $sql = "SELECT jd.ID, jd.Bezeichnung, jd.Reihenfolge 
            FROM JMDefinition jd
            LEFT JOIN sektionsrangierungen sr ON jd.ID = sr.jmdefinition_id AND sr.year = ?
            WHERE jd.year = ? 
            AND jd.hidden = 0 
            AND jd.Info = 0 
            AND jd.Erweitert = 0
            AND sr.id IS NULL
            ORDER BY jd.Reihenfolge ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Fehler beim Vorbereiten der SQL-Abfrage: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $year, $year);
    
    if (!$stmt->execute()) {
        throw new Exception('Fehler beim Ausführen der SQL-Abfrage: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $definitions = [];
    
    while ($row = $result->fetch_assoc()) {
        $definitions[] = [
            'ID' => $row['ID'],
            'Bezeichnung' => $row['Bezeichnung'],
            'Reihenfolge' => $row['Reihenfolge']
        ];
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