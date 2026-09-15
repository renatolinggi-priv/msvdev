<?php
// load_gruppen_members.php – aktive Mitglieder, die fuer diesen Anlass/Jahr noch keiner Gruppe zugeteilt sind
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');

header('Content-Type: application/json; charset=utf-8');

$year    = isset($_GET['jahr']) ? (int)$_GET['jahr'] : (int)date('Y');
$eventID = isset($_GET['eventID']) ? (int)$_GET['eventID'] : 0;

if ($eventID < 1) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT m.ID, m.Vorname, m.Name
    FROM mitglieder m
    WHERE m.Status = 1
      AND m.Verstorben = 0
      AND m.ID NOT IN (
            SELECT mitgliederID FROM JMDefinition_Gruppen WHERE JMDefinitionID = ? AND Jahr = ?
          )
    ORDER BY m.Name, m.Vorname
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
    exit;
}
$stmt->bind_param('ii', $eventID, $year);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

echo json_encode($members);
