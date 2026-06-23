<?php
/**
 * pages/drucksteuerung/print_log_api.php — Druckauftrags-Historie
 *
 * GET: Letzte Druckauftraege
 *      ?limit=20     (Standard: 20, Max: 100)
 *      ?status=fehler (optional: nur bestimmten Status)
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$limit = min(max(intval($_GET['limit'] ?? 20), 1), 100);
$status = $_GET['status'] ?? null;
$machineId = $_SERVER['HTTP_X_MACHINE_ID'] ?? null;

$where = [];
$params = [];

// Nicht-Admins sehen nur eigene Druckauftraege
if (!$isAdmin) {
    $where[] = 'benutzer_id = ?';
    $params[] = $userId;
}

// Bei Mehrplatz: nur Jobs dieser Maschine
if ($machineId) {
    $where[] = 'machine_id = ?';
    $params[] = $machineId;
}

if ($status) {
    $where[] = 'status = ?';
    $params[] = $status;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$params[] = $limit;

$stmt = $db->prepare("SELECT * FROM print_jobs $whereClause ORDER BY erstellt_am DESC LIMIT ?");
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Gesamtzahl
$countStmt = $db->prepare("SELECT COUNT(*) FROM print_jobs $whereClause");
$countStmt->execute(array_slice($params, 0, -1));
$total = (int)$countStmt->fetchColumn();

echo json_encode([
    'success' => true,
    'data'    => $jobs,
    'total'   => $total,
    'limit'   => $limit,
]);
