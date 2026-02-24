<?php
// load_available_members.php
header('Content-Type: application/json');
include '../config.php';

// Überprüfen der Datenbankverbindung
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

try {
    // Alle aktiven Mitglieder laden
    $sql = "SELECT ID, Name, Vorname
            FROM mitglieder
            WHERE status = 1
              AND Verstorben = 0
            ORDER BY Name ASC, Vorname ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Fehler beim Vorbereiten der SQL-Abfrage: ' . $conn->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Fehler beim Ausführen der SQL-Abfrage: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $members = [];
    
    while ($row = $result->fetch_assoc()) {
        $members[] = [
            'ID' => $row['ID'],
            'Name' => $row['Name'],
            'Vorname' => $row['Vorname']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'members' => $members
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in load_available_members.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Laden der Mitglieder: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>