<?php
// update_event.php – Termin bearbeiten
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

$eventId   = (int)($_POST['event_id'] ?? 0);
$eventName = trim((string)($_POST['event_name'] ?? ''));
$eventDate = trim((string)($_POST['event_date'] ?? ''));
$eventTime = trim((string)($_POST['event_time'] ?? ''));
$fuerJsk   = !empty($_POST['fuer_jsk']) ? 1 : 0;

if ($eventId < 1 || $eventName === '' || $eventDate === '' || $eventTime === '') {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Bitte alle Felder ausfüllen']));
}
$d = DateTime::createFromFormat('Y-m-d', $eventDate);
if (!$d || $d->format('Y-m-d') !== $eventDate) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültiges Datum (erwartet JJJJ-MM-TT)']));
}
$eventYear = (int)$d->format('Y');

// year-Spalte mitziehen, damit ein auf ein anderes Jahr verschobener Termin auch dort erscheint
$stmt = $conn->prepare("UPDATE wichtige_termine SET name = ?, date = ?, time = ?, fuer_jsk = ?, year = ? WHERE ID = ?");
$stmt->bind_param('sssiii', $eventName, $eventDate, $eventTime, $fuerJsk, $eventYear, $eventId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Termin aktualisiert']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern']);
}

$stmt->close();
$conn->close();
