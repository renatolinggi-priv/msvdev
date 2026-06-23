<?php
// load_events.php – JSON Response für Hybrid-Layout
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['year']) || !is_numeric($_GET['year'])) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage']);
    exit;
}

$year = intval($_GET['year']);

$sql = "SELECT ID, name, date, time FROM wichtige_termine WHERE year = ? ORDER BY date, time";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = [
        'ID'   => $row['ID'],
        'name' => $row['name'],
        'date' => $row['date'],
        'time' => $row['time']
    ];
}

echo json_encode([
    'success' => true,
    'events'  => $events,
    'count'   => count($events)
]);

$stmt->close();
$conn->close();
