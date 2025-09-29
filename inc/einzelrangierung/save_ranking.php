<?php
// save_ranking.php
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
$year = isset($_POST['year']) ? intval($_POST['year']) : 0;
$anlassId = isset($_POST['anlass_id']) ? intval($_POST['anlass_id']) : 0;
$mitgliedId = isset($_POST['mitglied_id']) ? intval($_POST['mitglied_id']) : 0;
$rang = isset($_POST['rang']) ? intval($_POST['rang']) : 0;
$resultat = isset($_POST['resultat']) ? trim($_POST['resultat']) : '';
$preis = isset($_POST['preis']) ? floatval($_POST['preis']) : 0;

// Validierung
if (empty($year) || empty($anlassId) || empty($mitgliedId) || empty($rang) || $preis < 0) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Parameter']);
    exit;
}

if ($rang < 1 || $rang > 999) {
    echo json_encode(['success' => false, 'message' => 'Rang muss zwischen 1 und 999 liegen']);
    exit;
}

try {
    // Prüfen ob bereits eine Rangierung für dieses Mitglied bei diesem Anlass im Jahr existiert
    $checkSql = "SELECT id FROM einzelrangierungen 
                 WHERE year = ? AND jmdefinition_id = ? AND mitglied_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        throw new Exception('Fehler beim Vorbereiten der Prüfabfrage: ' . $conn->error);
    }
    
    $checkStmt->bind_param("iii", $year, $anlassId, $mitgliedId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        echo json_encode(['success' => false, 'message' => 'Für dieses Mitglied existiert bereits eine Rangierung bei diesem Anlass']);
        exit;
    }
    $checkStmt->close();
    
    // Neue Rangierung einfügen
    $sql = "INSERT INTO einzelrangierungen (year, jmdefinition_id, mitglied_id, rang, resultat, preis)
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Fehler beim Vorbereiten der SQL-Abfrage: ' . $conn->error);
    }
    
    $stmt->bind_param("iiiisd", $year, $anlassId, $mitgliedId, $rang, $resultat, $preis);
    
    if (!$stmt->execute()) {
        throw new Exception('Fehler beim Ausführen der SQL-Abfrage: ' . $stmt->error);
    }
    
    $insertId = $conn->insert_id;
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Einzelrangierung erfolgreich gespeichert',
        'id' => $insertId
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in save_ranking.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Speichern: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>