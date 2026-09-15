<?php
// save_sieger.php — neuen Sieger-Eintrag anlegen (Name wird aus dem Mitglied abgeleitet)
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}
csrf_require(true);

$member_id = (int)($_POST['member_id'] ?? 0);
$wert      = (int)($_POST['wert'] ?? 0);
$siegerdef = (int)($_POST['siegerdef'] ?? 0);
$year      = (int)($_POST['year'] ?? 0);

if ($member_id < 1)  { http_response_code(422); die(json_encode(['success' => false, 'message' => 'Bitte ein Mitglied wählen'])); }
if ($siegerdef < 1)  { http_response_code(422); die(json_encode(['success' => false, 'message' => 'Bitte eine Auszeichnung wählen'])); }
if ($wert <= 0)      { http_response_code(422); die(json_encode(['success' => false, 'message' => 'Wert muss grösser als 0 sein'])); }
if ($year < 2000 || $year > 2100) { http_response_code(422); die(json_encode(['success' => false, 'message' => 'Ungültiges Jahr'])); }

try {
    $stmt = $conn->prepare("SELECT Vorname, Name FROM mitglieder WHERE ID = ?");
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Mitglied nicht gefunden']));
    }
    $name = $row['Name'] . ' ' . $row['Vorname'];

    $stmt = $conn->prepare("INSERT INTO sieger (Name, Wert, siegerdef, year) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('siii', $name, $wert, $siegerdef, $year);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $newId = (int)$conn->insert_id;
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Sieger gespeichert', 'id' => $newId, 'year' => $year]);
} catch (Throwable $e) {
    error_log('[save_sieger] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sieger konnte nicht gespeichert werden']);
}
$conn->close();
