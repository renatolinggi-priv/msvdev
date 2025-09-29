<?php
// save_ranking.php
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
$year = isset($_POST['year']) ? intval($_POST['year']) : 0;
$anlassId = isset($_POST['anlass_id']) ? intval($_POST['anlass_id']) : 0;
$rang = isset($_POST['rang']) ? intval($_POST['rang']) : 0;
$preis = isset($_POST['preis']) ? floatval($_POST['preis']) : 0;

// Validierung
if ($year < 2020 || $year > 2030) {
    echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr']);
    exit;
}

if ($anlassId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Anlass nicht ausgewählt']);
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
    // Prüfen ob bereits eine Rangierung für diesen Anlass im Jahr existiert
    $checkSql = "SELECT id FROM sektionsrangierungen WHERE jmdefinition_id = ? AND year = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $anlassId, $year);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Für diesen Anlass existiert bereits eine Rangierung']);
        exit;
    }
    $checkStmt->close();
    
    // Prüfen ob JMDefinition existiert und Info = 0 ist
    $jmSql = "SELECT ID FROM JMDefinition WHERE ID = ? AND year = ? AND Info = 0 AND hidden = 0";
    $jmStmt = $conn->prepare($jmSql);
    $jmStmt->bind_param("ii", $anlassId, $year);
    $jmStmt->execute();
    $jmResult = $jmStmt->get_result();
    
    if ($jmResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Anlass nicht gefunden oder nicht berechtigt']);
        exit;
    }
    $jmStmt->close();
    
    // Neue Rangierung speichern
    $insertSql = "INSERT INTO sektionsrangierungen (year, jmdefinition_id, rang, preis, created_at) VALUES (?, ?, ?, ?, NOW())";
    $insertStmt = $conn->prepare($insertSql);
    
    if (!$insertStmt) {
        throw new Exception('Fehler beim Vorbereiten der INSERT-Abfrage: ' . $conn->error);
    }
    
    $insertStmt->bind_param("iiid", $year, $anlassId, $rang, $preis);
    
    if (!$insertStmt->execute()) {
        throw new Exception('Fehler beim Speichern der Rangierung: ' . $insertStmt->error);
    }
    
    $newId = $conn->insert_id;
    $insertStmt->close();
    
    // Erfolgreiche Antwort
    echo json_encode([
        'success' => true,
        'message' => 'Rangierung erfolgreich gespeichert',
        'ranking_id' => $newId
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in save_ranking.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Speichern der Rangierung: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>