<?php
// update_ranking.php
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
$rang = isset($_POST['rang']) ? intval($_POST['rang']) : 0;
$preis = isset($_POST['preis']) ? floatval($_POST['preis']) : 0;

// Validierung
if ($rankingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Rangierungs-ID']);
    exit;
}

if ($rang <= 0 || $rang > 999) {
    echo json_encode(['success' => false, 'message' => 'Rang muss zwischen 1 und 999 liegen']);
    exit;
}

if ($preis < 0) {
    echo json_encode(['success' => false, 'message' => 'Preis darf nicht negativ sein']);
    exit;
}

try {
    // Prüfen ob Rangierung existiert
    $checkSql = "SELECT id FROM sektionsrangierungen WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $rankingId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Rangierung nicht gefunden']);
        exit;
    }
    $checkStmt->close();
    
    // Rangierung aktualisieren
    $updateSql = "UPDATE sektionsrangierungen SET rang = ?, preis = ?, updated_at = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    
    if (!$updateStmt) {
        throw new Exception('Fehler beim Vorbereiten der UPDATE-Abfrage: ' . $conn->error);
    }
    
    $updateStmt->bind_param("idi", $rang, $preis, $rankingId);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Fehler beim Aktualisieren der Rangierung: ' . $updateStmt->error);
    }
    
    $affectedRows = $updateStmt->affected_rows;
    $updateStmt->close();
    
    if ($affectedRows === 0) {
        echo json_encode(['success' => false, 'message' => 'Keine Änderungen vorgenommen']);
        exit;
    }
    
    // Erfolgreiche Antwort
    echo json_encode([
        'success' => true,
        'message' => 'Rangierung erfolgreich aktualisiert'
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in update_ranking.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Aktualisieren der Rangierung: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>