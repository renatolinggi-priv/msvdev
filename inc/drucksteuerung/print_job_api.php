<?php
/**
 * pages/drucksteuerung/print_job_api.php — Druckauftrag loggen
 *
 * POST: { "doc_type": "...", "printer_name": "...", "dateiname": "...", "status": "gesendet", "copies": 1 }
 * Status-Update: { "id": 1, "status": "erfolgreich" }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../session_config.inc.php';
require_once __DIR__ . '/../../auth.php';

if (!isset($_SESSION['user_id']) && function_exists('restoreSessionFromToken')) {
    restoreSessionFromToken();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$machineId = $_SERVER['HTTP_X_MACHINE_ID'] ?? null;

$input = json_decode(file_get_contents('php://input'), true);

// Status-Update (wenn id vorhanden)
if (!empty($input['id']) && !empty($input['status'])) {
    $stmt = $db->prepare("UPDATE print_jobs SET status=?, fehler_text=? WHERE id=?");
    $stmt->execute([
        $input['status'],
        $input['fehler_text'] ?? null,
        intval($input['id']),
    ]);
    echo json_encode(['success' => true, 'message' => 'Status aktualisiert']);
    exit;
}

// Neuen Druckauftrag loggen
$stmt = $db->prepare("INSERT INTO print_jobs (benutzer_id, machine_id, doc_type, printer_name, dateiname, status, copies) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $userId,
    $machineId,
    $input['doc_type'] ?? null,
    $input['printer_name'] ?? null,
    $input['dateiname'] ?? null,
    $input['status'] ?? 'gesendet',
    intval($input['copies'] ?? 1),
]);
echo json_encode(['success' => true, 'message' => 'Druckauftrag geloggt', 'id' => (int)$db->lastInsertId()]);
