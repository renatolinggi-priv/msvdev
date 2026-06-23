<?php
// delete_all_events.php – Löscht alle Termine eines Jahres (JSON Response)
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF prüfen
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage']);
    exit;
}

if (!isset($_POST['year']) || !is_numeric($_POST['year'])) {
    echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr']);
    exit;
}

$year = intval($_POST['year']);

$sql = "DELETE FROM wichtige_termine WHERE year = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'count' => $stmt->affected_rows]);
} else {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen']);
}

$stmt->close();
$conn->close();
