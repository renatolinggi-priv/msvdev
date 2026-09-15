<?php
// delete_fragebogen.php – alle Antworten eines Jahres loeschen (erweitert + Hauptzeilen, transaktional)
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

$year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
if ($year < 2000 || $year > 2100) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültiges Jahr']));
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("DELETE fe FROM mitglieder_fragebogen_erweitert fe
                            JOIN mitglieder_fragebogen fb ON fe.fragebogenID = fb.ID
                            WHERE fb.jahr = ?");
    $stmt->bind_param('i', $year);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM mitglieder_fragebogen WHERE jahr = ?");
    $stmt->bind_param('i', $year);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $count = $stmt->affected_rows;
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Antworten für $year gelöscht", 'count' => $count]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[delete_fragebogen] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen']);
}
$conn->close();
