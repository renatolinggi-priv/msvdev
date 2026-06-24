<?php
// toggle_jsk.php – Schaltet das "Für Jungschützen"-Flag eines Termins inline um.
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF prüfen (Muster wie add_event.php)
$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF-Token']);
    exit;
}

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
