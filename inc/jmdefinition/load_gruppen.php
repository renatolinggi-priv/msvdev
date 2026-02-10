<?php
// load_gruppen.php
include '../config.php';

$year = isset($_GET['jahr']) ? (int)$_GET['jahr'] : date('Y');
$eventID = isset($_GET['eventID']) ? (int)$_GET['eventID'] : 0;

if ($eventID === 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// Wir gruppieren nun nach GruppenUID, sodass wir genau eine Zeile pro Gruppe bekommen.
$sql = "
    SELECT
        g.GruppenUID AS ID,           -- Diese Spalte nutzt das Frontend als 'group.ID'
        g.Gruppenname,
        GROUP_CONCAT(CONCAT(m.Name, ' ', m.Vorname) ORDER BY m.Name SEPARATOR ', ') AS Mitglieder
    FROM JMDefinition_Gruppen g
    JOIN mitglieder m ON g.mitgliederID = m.ID
    WHERE g.JMDefinitionID = ?
      AND g.Jahr = ?
    GROUP BY g.GruppenUID, g.Gruppenname
    ORDER BY g.GruppenUID
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Fehler beim Vorbereiten des Statements: ' . $conn->error]);
    exit;
}
$stmt->bind_param("ii", $eventID, $year);
$stmt->execute();
$result = $stmt->get_result();

$groups = [];
while ($row = $result->fetch_assoc()) {
    // Pro Datensatz haben wir jetzt:
    // row['ID'] => GruppenUID
    // row['Gruppenname']
    // row['Mitglieder'] => zusammengesetzte Stringliste
    $groups[] = $row;
}

header('Content-Type: application/json');
echo json_encode($groups);

$stmt->close();
$conn->close();
