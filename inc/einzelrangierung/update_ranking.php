<?php
// update_ranking.php
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
$rang = isset($_POST['rang']) ? intval($_POST['rang']) : 0;
$resultat = isset($_POST['resultat']) ? trim($_POST['resultat']) : '';
$preis = isset($_POST['preis']) ? floatval($_POST['preis']) : 0;

// Validierung
if (empty($rankingId) || empty($rang) || $preis < 0) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Parameter']);
    exit;
}

if ($rang < 1 || $rang > 999) {
    echo json_encode(['success' => false, 'message' => 'Rang muss zwischen 1 und 999 liegen']);
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
    
    // Rangierung aktualisieren
    $sql = "UPDATE einzelrangierungen
            SET rang = ?, resultat = ?, preis = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Fehler beim Vorbereiten der SQL-Abfrage: ' . $conn->error);
    }
    
    $stmt->bind_param("isdi", $rang, $resultat, $preis, $rankingId);
    
    if (!$stmt->execute()) {
        throw new Exception('Fehler beim Ausführen der SQL-Abfrage: ' . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Keine Änderungen vorgenommen']);
        exit;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Einzelrangierung erfolgreich aktualisiert'
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in update_ranking.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Aktualisieren: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>