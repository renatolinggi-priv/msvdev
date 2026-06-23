<?php
// copy_events.php – Übernimmt ausgewählte Termine (mit verschobenem Datum und
// angepasstem Namen) ins Zieljahr. Transaktional.
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF prüfen (Muster wie add_event.php)
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF-Token']);
    exit;
}

$targetYear = isset($_POST['target_year']) ? intval($_POST['target_year']) : 0;
$events = isset($_POST['events']) && is_array($_POST['events']) ? $_POST['events'] : [];

if ($targetYear < 2000 || $targetYear > 2100 || empty($events)) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO wichtige_termine (name, date, time, year) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);
    }

    $count = 0;
    foreach ($events as $ev) {
        $name = trim((string)($ev['name'] ?? ''));
        $date = trim((string)($ev['date'] ?? ''));
        $time = trim((string)($ev['time'] ?? ''));
        if ($name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { continue; }

        $stmt->bind_param("sssi", $name, $date, $time, $targetYear);
        if (!$stmt->execute()) {
            throw new Exception('Insert fehlgeschlagen: ' . $stmt->error);
        }
        $count++;
    }
    $stmt->close();
    $conn->commit();

    echo json_encode(['success' => true, 'count' => $count]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}

$conn->close();
