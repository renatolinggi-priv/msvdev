<?php
// delete_all_events.php – Löscht alle Termine eines Jahres (JSON Response)
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

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
