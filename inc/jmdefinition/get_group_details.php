<?php
// get_group_details.php
include '../config.php';

$groupUID = isset($_GET['groupID']) ? intval($_GET['groupID']) : 0; 
if ($groupUID <= 0) {
    echo json_encode(['message' => 'Ungültige groupID']);
    exit;
}

// Wir holen für diese GruppenUID den Gruppennamen + alle MemberIDs & MemberNames
$sql = "
    SELECT
      g.GruppenUID,
      g.Gruppenname,
      GROUP_CONCAT(g.mitgliederID ORDER BY g.mitgliederID SEPARATOR ',') AS MemberIDs,
      GROUP_CONCAT(CONCAT(m.Name,' ',m.Vorname) ORDER BY m.Name SEPARATOR '|') AS MemberNames
    FROM JMDefinition_Gruppen g
    JOIN mitglieder m ON g.mitgliederID = m.ID
    WHERE g.GruppenUID = ?
    GROUP BY g.GruppenUID, g.Gruppenname
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['message' => 'Statement-Fehler: '.$conn->error]);
    exit;
}
$stmt->bind_param("i", $groupUID);
$stmt->execute();
$result = $stmt->get_result();

$row = $result->fetch_assoc();
if (!$row) {
    echo json_encode(['message' => 'Keine Daten für GruppenUID='.$groupUID]);
    exit;
}

// Wir geben ein JSON zurück mit ID = GruppenUID
// MemberIDs, MemberNames sind Komma-/Pipe-getrennte Listen.
echo json_encode([
    'ID'          => $row['GruppenUID'],
    'Gruppenname' => $row['Gruppenname'],
    'MemberIDs'   => $row['MemberIDs'],    // z. B. "112108,112114,112300"
    'MemberNames' => $row['MemberNames']   // z. B. "Mustermann Max|Müller Lena|Schneider Tim"
]);

$stmt->close();
$conn->close();
