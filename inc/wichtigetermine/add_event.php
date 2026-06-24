<?php
// add_event.php – JSON Response
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF prüfen
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF-Token']);
    exit;
}

if (!isset($_POST['event_name'], $_POST['event_date'], $_POST['event_time'])) {
    echo json_encode(['success' => false, 'message' => 'Bitte alle Felder ausfüllen']);
    exit;
}

$eventName = trim($_POST['event_name']);
$eventDate = $_POST['event_date'];
$eventTime = trim($_POST['event_time']);
$eventYear = isset($_POST['year']) ? intval($_POST['year']) : (isset($_POST['event_year']) ? intval($_POST['event_year']) : date('Y'));
$fuerJsk   = !empty($_POST['fuer_jsk']) ? 1 : 0;

if (empty($eventName) || empty($eventDate) || empty($eventTime)) {
    echo json_encode(['success' => false, 'message' => 'Bitte alle Felder ausfüllen']);
    exit;
}

$sql = "INSERT INTO wichtige_termine (name, date, time, year, fuer_jsk) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssii", $eventName, $eventDate, $eventTime, $eventYear, $fuerJsk);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Termin hinzugefügt', 'id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
