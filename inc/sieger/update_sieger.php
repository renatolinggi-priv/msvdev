<?php
// update_sieger.php — bestehenden Sieger-Eintrag aktualisieren
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

$id        = (int)($_POST['id'] ?? 0);
$wert      = (int)($_POST['wert'] ?? 0);
$siegerdef = (int)($_POST['siegerdef'] ?? 0);
$year      = (int)($_POST['year'] ?? 0);
$name      = trim((string)($_POST['name'] ?? ''));
$member_id = (int)($_POST['member_id'] ?? 0);

if ($id < 1)        { http_response_code(422); die(json_encode(['success' => false, 'message' => 'Ungültige ID'])); }
if ($siegerdef < 1) { http_response_code(422); die(json_encode(['success' => false, 'message' => 'Bitte eine Auszeichnung wählen'])); }
if ($wert <= 0)     { http_response_code(422); die(json_encode(['success' => false, 'message' => 'Wert muss grösser als 0 sein'])); }

try {
    // Name aus dem gewaehlten Mitglied ableiten, sonst den mitgeschickten (bestehenden) Namen behalten
    if ($member_id > 0) {
        $stmt = $conn->prepare("SELECT Vorname, Name FROM mitglieder WHERE ID = ?");
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $name = $row['Name'] . ' ' . $row['Vorname'];
        }
        $stmt->close();
    }
    if ($name === '') {
        http_response_code(422);
        die(json_encode(['success' => false, 'message' => 'Bitte ein Mitglied wählen']));
    }

    // Jahr nur uebernehmen, wenn ein gueltiges mitgeschickt wurde
    if ($year >= 2000 && $year <= 2100) {
        $stmt = $conn->prepare("UPDATE sieger SET Name = ?, Wert = ?, siegerdef = ?, year = ? WHERE ID = ?");
        $stmt->bind_param('siiii', $name, $wert, $siegerdef, $year, $id);
    } else {
        $stmt = $conn->prepare("UPDATE sieger SET Name = ?, Wert = ?, siegerdef = ? WHERE ID = ?");
        $stmt->bind_param('siii', $name, $wert, $siegerdef, $id);
    }
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();

    // Existenz pruefen (affected_rows ist auch 0, wenn nichts geaendert wurde)
    $check = $conn->prepare("SELECT ID FROM sieger WHERE ID = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();
    if (!$exists) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Eintrag nicht gefunden']));
    }

    echo json_encode(['success' => true, 'message' => 'Sieger aktualisiert']);
} catch (Throwable $e) {
    error_log('[update_sieger] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sieger konnte nicht aktualisiert werden']);
}
$conn->close();
