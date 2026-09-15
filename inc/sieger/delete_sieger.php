<?php
// delete_sieger.php — Sieger-Eintrag loeschen
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

$sieger_id = (int)($_POST['sieger_id'] ?? 0);
if ($sieger_id < 1) {
    http_response_code(422);
    die(json_encode(['success' => false, 'message' => 'Sieger-ID ist erforderlich']));
}

try {
    $stmt = $conn->prepare("DELETE FROM sieger WHERE ID = ?");
    $stmt->bind_param('i', $sieger_id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Sieger gelöscht']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sieger nicht gefunden']);
    }
    $stmt->close();
} catch (Throwable $e) {
    error_log('[delete_sieger] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sieger konnte nicht gelöscht werden']);
}
$conn->close();
