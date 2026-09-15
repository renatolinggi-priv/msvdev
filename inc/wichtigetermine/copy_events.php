<?php
// copy_events.php – Übernimmt ausgewählte Termine (mit verschobenem Datum und
// angepasstem Namen) ins Zieljahr. Transaktional.
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');

require_once __DIR__ . '/../csrf.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}
csrf_require(true); // 403 + JSON bei ungültigem Token

$targetYear = isset($_POST['target_year']) ? intval($_POST['target_year']) : 0;
$events = isset($_POST['events']) && is_array($_POST['events']) ? $_POST['events'] : [];

if ($targetYear < 2000 || $targetYear > 2100 || empty($events)) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO wichtige_termine (name, date, time, year, fuer_jsk) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);
    }

    $count = 0;
    foreach ($events as $ev) {
        $name = trim((string)($ev['name'] ?? ''));
        $date = trim((string)($ev['date'] ?? ''));
        $time = trim((string)($ev['time'] ?? ''));
        $fuerJsk = !empty($ev['fuer_jsk']) ? 1 : 0;
        if ($name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { continue; }

        $stmt->bind_param("sssii", $name, $date, $time, $targetYear, $fuerJsk);
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
