<?php
// delete_event.php – Termin loeschen
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}
csrf_require(true);

$eventId = (int)($_POST['event_id'] ?? 0);
if ($eventId < 1) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültige Event-ID']));
}

$stmt = $conn->prepare("DELETE FROM wichtige_termine WHERE ID = ?");
$stmt->bind_param('i', $eventId);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen']);
} elseif ($stmt->affected_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Termin nicht gefunden']);
} else {
    echo json_encode(['success' => true, 'message' => 'Termin gelöscht']);
}
$stmt->close();
$conn->close();
