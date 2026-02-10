<?php
// update_event.php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

// 1. Daten prüfen
if (
  !isset($_POST['event_id']) || 
  !isset($_POST['event_name']) || 
  !isset($_POST['event_date']) ||
  !isset($_POST['event_time'])
) {
  echo json_encode([
    'success' => false,
    'message' => 'Ungültige Eingabedaten'
  ]);
  exit;
}

$eventId   = (int)$_POST['event_id'];
$eventName = trim($_POST['event_name']);
$eventDate = trim($_POST['event_date']);
$eventTime = trim($_POST['event_time']);

// Einfacher Check, ob Datum in gültigem Format ist, optional
// if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) { ... }

// 2. Update in der Datenbank
$sql = "UPDATE wichtige_termine SET name=?, date=?, time=? WHERE ID=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $eventName, $eventDate, $eventTime, $eventId);

if ($stmt->execute()) {
  echo json_encode([
    'success' => true
  ]);
} else {
  echo json_encode([
    'success' => false,
    'message' => 'Fehler beim Update'
  ]);
}

$stmt->close();
$conn->close();
