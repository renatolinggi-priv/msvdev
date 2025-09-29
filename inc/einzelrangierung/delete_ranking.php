<?php
// delete_ranking.php
session_start();
header('Content-Type: application/json');
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

// POST-Parameter validieren
$rankingId = isset($_POST['ranking_id']) ? intval($_POST['ranking_id']) : 0;

// Validierung
if (empty($rankingId)) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Rangierungs-ID']);
    exit;
}

try {
    // Prüfen ob die Rangierung existiert
    $checkSql = "SELECT id FROM einzelrangierungen WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        throw new Exception('Fehler beim Vorbereiten der Prüfabfrage: ' . $conn->error);
    }
    
    $checkStmt->bind_param("i", $rankingId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        echo json_encode(['success' => false, 'message' => 'Rangierung nicht gefunden']);
        exit;
    }
    $checkStmt->close();
    
    // Rangierung löschen
    $sql = "DELETE FROM einzelrangierungen WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Fehler beim Vorbereiten der SQL-Abfrage: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $rankingId);
    
    if (!$stmt->execute()) {
        throw new Exception('Fehler beim Ausführen der SQL-Abfrage: ' . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Rangierung konnte nicht gelöscht werden']);
        exit;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Einzelrangierung erfolgreich gelöscht'
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in delete_ranking.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Löschen: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>