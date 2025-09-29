<?php
// delete_ranking.php
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

// Parameter aus POST validieren
$rankingId = isset($_POST['ranking_id']) ? intval($_POST['ranking_id']) : 0;

// Validierung
if ($rankingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Rangierungs-ID']);
    exit;
}

try {
    // Prüfen ob Rangierung existiert und Details laden für Log
    $checkSql = "SELECT sr.id, sr.rang, sr.preis, jd.Bezeichnung 
                 FROM sektionsrangierungen sr
                 JOIN JMDefinition jd ON sr.jmdefinition_id = jd.ID
                 WHERE sr.id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $rankingId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Rangierung nicht gefunden']);
        exit;
    }
    
    $rankingData = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    // Rangierung löschen
    $deleteSql = "DELETE FROM sektionsrangierungen WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    
    if (!$deleteStmt) {
        throw new Exception('Fehler beim Vorbereiten der DELETE-Abfrage: ' . $conn->error);
    }
    
    $deleteStmt->bind_param("i", $rankingId);
    
    if (!$deleteStmt->execute()) {
        throw new Exception('Fehler beim Löschen der Rangierung: ' . $deleteStmt->error);
    }
    
    $affectedRows = $deleteStmt->affected_rows;
    $deleteStmt->close();
    
    if ($affectedRows === 0) {
        echo json_encode(['success' => false, 'message' => 'Rangierung konnte nicht gelöscht werden']);
        exit;
    }
    
    // Log-Eintrag für gelöschte Rangierung
    error_log("Rangierung gelöscht - ID: $rankingId, Anlass: {$rankingData['Bezeichnung']}, Rang: {$rankingData['rang']}, Preis: {$rankingData['preis']}");
    
    // Erfolgreiche Antwort
    echo json_encode([
        'success' => true,
        'message' => 'Rangierung erfolgreich gelöscht'
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in delete_ranking.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Löschen der Rangierung: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>