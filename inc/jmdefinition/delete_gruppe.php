<?php
// delete_gruppe.php – Gruppe (alle Zeilen einer GruppenUID) loeschen
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

$groupID = (int)($_POST['groupID'] ?? 0);
if ($groupID < 1) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültige Gruppen-ID']));
}

$stmt = $conn->prepare("DELETE FROM JMDefinition_Gruppen WHERE GruppenUID = ?");
if (!$stmt) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Datenbankfehler']));
}
$stmt->bind_param('i', $groupID);
if (!$stmt->execute()) {
    error_log('[delete_gruppe] ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gruppe konnte nicht gelöscht werden']);
} elseif ($stmt->affected_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Gruppe nicht gefunden']);
} else {
    echo json_encode(['success' => true, 'message' => 'Gruppe gelöscht', 'geloescht' => $stmt->affected_rows]);
}
$stmt->close();
$conn->close();
