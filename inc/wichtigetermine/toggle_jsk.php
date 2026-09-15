<?php
// toggle_jsk.php – Schaltet das "Für Jungschützen"-Flag eines Termins inline um.
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

$eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
$fuerJsk = !empty($_POST['fuer_jsk']) ? 1 : 0;
if ($eventId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
    exit;
}

$stmt = $conn->prepare("UPDATE wichtige_termine SET fuer_jsk = ? WHERE ID = ?");
$stmt->bind_param("ii", $fuerJsk, $eventId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'fuer_jsk' => $fuerJsk]);
} else {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
