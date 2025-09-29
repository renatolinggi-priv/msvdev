<?php
// load_available_definitions.php
header('Content-Type: application/json');
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
    // Alle JMDefinitionen für das Jahr laden (nicht nur Info=0, da bei Einzelrangierungen alle Anlässe relevant sind)
    $sql = "SELECT ID, Bezeichnung, Reihenfolge 
            FROM JMDefinition 
            WHERE year = ? AND Info = 0 AND Streicher = 1
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
        $definitions[] = [
            'ID' => $row['ID'],
            'Bezeichnung' => $row['Bezeichnung'],
            'Reihenfolge' => $row['Reihenfolge']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'definitions' => $definitions
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in load_available_definitions.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Laden der Anlässe: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>