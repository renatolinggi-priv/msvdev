<?php
// copy_list.php – Liefert die Anlässe eines Jahres als JSON (für "Vom Vorjahr übernehmen").
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['year']) || !is_numeric($_GET['year'])) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage']);
    exit;
}

$year = intval($_GET['year']);

$sql = "SELECT ID, Bezeichnung, Schiesstage, Adresse, Maxpunkte, Zuschlag, Streicher, Erweitert, Info, Gruppe
        FROM JMDefinition WHERE year = ? AND hidden = 0 ORDER BY Reihenfolge";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = [
        'id'          => (int)$row['ID'],
        'bezeichnung' => $row['Bezeichnung'],
        'schiesstage' => $row['Schiesstage'] ?? '',
        'adresse'     => $row['Adresse'] ?? '',
        'maxpunkte'   => (int)$row['Maxpunkte'],
        'zuschlag'    => (int)$row['Zuschlag'],
        'streicher'   => (int)$row['Streicher'],
        'erweitert'   => (int)$row['Erweitert'],
        'info'        => (int)$row['Info'],
        'gruppe'      => (int)$row['Gruppe'],
    ];
}

echo json_encode(['success' => true, 'events' => $events, 'count' => count($events)]);

$stmt->close();
$conn->close();
