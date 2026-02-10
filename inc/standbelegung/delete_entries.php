<?php
// delete_entries.php - Löscht einzelne Standbelegung-Einträge
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

if (!$input || !isset($input['ids']) || !is_array($input['ids'])) {
    echo json_encode(['success' => false, 'message' => 'Keine IDs angegeben']);
    exit;
}

$ids = array_filter($input['ids'], 'is_numeric');

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Keine gültigen IDs']);
    exit;
}

try {
    // Prepared Statement mit IN-Klausel
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    
    $sql = "DELETE FROM Standbelegung WHERE ID IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    
    $deleted = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'deleted' => $deleted,
        'message' => "$deleted Einträge gelöscht"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
