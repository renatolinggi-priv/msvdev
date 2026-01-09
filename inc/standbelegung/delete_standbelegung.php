<?php
// delete_standbelegung.php - Löscht bestehende Standbelegung für ein Jahr
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['year'])) {
    echo json_encode(['success' => false, 'error' => 'Kein Jahr angegeben']);
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
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
