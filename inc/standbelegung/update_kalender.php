<?php
// update_kalender.php - Aktualisiert InKalender-Status eines Eintrags
require_once '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id']) || !isset($input['in_kalender'])) {
    echo json_encode(['success' => false, 'message' => 'ID und in_kalender müssen angegeben werden']);
    exit;
}

$id = intval($input['id']);
$inKalender = intval($input['in_kalender']) ? 1 : 0;

try {
    $stmt = $conn->prepare("UPDATE Standbelegung SET InKalender = ? WHERE ID = ?");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $inKalender, $id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'id' => $id,
        'in_kalender' => $inKalender,
        'affected' => $affected
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
