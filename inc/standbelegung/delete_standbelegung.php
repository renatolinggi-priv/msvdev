<?php
// delete_standbelegung.php - Löscht bestehende Standbelegung für ein Jahr
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

if (!$input || !isset($input['year'])) {
    echo json_encode(['success' => false, 'message' => 'Kein Jahr angegeben']);
    exit;
}

$year = intval($input['year']);

try {
    $stmt = $conn->prepare("DELETE FROM Standbelegung WHERE Jahr = ?");
    $stmt->bind_param("i", $year);
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
