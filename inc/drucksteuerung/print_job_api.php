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

$input = json_decode(file_get_contents('php://input'), true) ?: [];

// Status-Lebenszyklus: «gesendet» = an den OS-Spooler übergeben (qz.print() resolved bereits dann),
// «erfolgreich» nur bei bestätigtem Druck, «fehler» mit fehler_text (Ursache aus QZ/Server).
$erlaubteStatus = ['gesendet', 'erfolgreich', 'fehler'];
$status = in_array($input['status'] ?? '', $erlaubteStatus, true) ? $input['status'] : 'gesendet';
$fehlerText = isset($input['fehler_text']) && trim((string)$input['fehler_text']) !== ''
    ? mb_substr(trim((string)$input['fehler_text']), 0, 2000)
    : null;
$kuerzen = static fn($v, int $max) => $v === null || $v === '' ? null : mb_substr((string)$v, 0, $max);

// Status-Update (wenn id vorhanden) – nur eigene Einträge
if (!empty($input['id']) && !empty($input['status'])) {
    $stmt = $db->prepare("UPDATE print_jobs SET status=?, fehler_text=? WHERE id=? AND benutzer_id=?");
    $stmt->execute([$status, $fehlerText, intval($input['id']), $userId]);
    echo json_encode(['success' => true, 'message' => 'Status aktualisiert']);
    exit;
}

// Neuen Druckauftrag loggen (inkl. Fehlertext, damit gescheiterte Drucke nachvollziehbar sind)
$stmt = $db->prepare("INSERT INTO print_jobs (benutzer_id, machine_id, doc_type, printer_name, dateiname, status, copies, fehler_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $userId,
    $machineId,
    $kuerzen($input['doc_type'] ?? null, 100),
    $kuerzen($input['printer_name'] ?? null, 255),
    $kuerzen($input['dateiname'] ?? null, 500),
    $status,
    max(1, min(99, intval($input['copies'] ?? 1))),
    $fehlerText,
]);
echo json_encode(['success' => true, 'message' => 'Druckauftrag geloggt', 'id' => (int)$db->lastInsertId()]);
