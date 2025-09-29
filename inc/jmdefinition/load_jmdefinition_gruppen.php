<?php
// get_events.php
include '../config.php';

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Wir gehen davon aus, dass die Anlässe in der Tabelle JMDefinition gespeichert sind 
// und dass das Feld Gruppe (oder ein entsprechendes Kriterium) angibt, ob es sich um einen Anlass für Gruppen handelt.
$sql = "SELECT ID, Bezeichnung 
        FROM JMDefinition 
        WHERE year = ? AND Gruppe = 1
        ORDER BY Reihenfolge";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

header('Content-Type: application/json');
echo json_encode($events);
?>
