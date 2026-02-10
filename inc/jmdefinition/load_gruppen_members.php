<?php
// load_members.php
include '../config.php';

// Erwartete GET-Parameter: jahr und eventID (JMDefinitionID)
$year = isset($_GET['jahr']) ? intval($_GET['jahr']) : date('Y');
$eventID = isset($_GET['eventID']) ? intval($_GET['eventID']) : 0;

if ($eventID === 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// Query: Alle aktiven Mitglieder, die nicht bereits einer Gruppe für diesen Anlass im angegebenen Jahr zugeordnet sind
$sql = "
    SELECT m.ID, m.Vorname, m.Name
    FROM mitglieder m
    WHERE m.Status = 1 
      AND m.ID NOT IN (
            SELECT mitgliederID 
            FROM JMDefinition_Gruppen 
            WHERE JMDefinitionID = ? 
              AND Jahr = ?
          )
    ORDER BY m.Name, m.Vorname
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

$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

header('Content-Type: application/json');
echo json_encode($members);

$stmt->close();
$conn->close();
?>
