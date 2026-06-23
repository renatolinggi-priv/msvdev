<?php
/**
 * api/changelog.php — Öffentliches API für Changelog-Einträge
 *
 * Auth: API-Key via Header X-API-Key oder Query-Parameter ?key=
 * Key aus msvjm_config.php: $config['changelog']['api_key']
 *
 * GET-Parameter:
 *   key         - API-Key (alternativ zu X-API-Key Header)
 *   kategorie   - Filter: resultate, termine, definition, standbelegung (optional)
 *   jahr        - Filter: nur Einträge für dieses Jahr (optional)
 *   limit       - Max. Anzahl Einträge (default 20, max 100)
 *   seit        - ISO-Datum, nur Einträge seit diesem Zeitpunkt (optional)
 *
 * Response: { success: bool, data: [...], total: int, timestamp: string }
 */

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://www.msvwilen.ch');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Fehlerbehandlung
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Config + DB
$config = require __DIR__ . '/../inc/../../msvjm_config.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';

// API-Key prüfen
$expectedKey = $config['changelog']['api_key'] ?? '';
$providedKey = $_GET['key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');

if ($expectedKey === '' || $providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Parameter
$kategorie = $_GET['kategorie'] ?? null;
$jahr      = isset($_GET['jahr']) ? intval($_GET['jahr']) : null;
$limit     = min(max(intval($_GET['limit'] ?? 20), 1), 100);
$seit      = $_GET['seit'] ?? null;

try {
    $db = getDB();

    // WHERE-Clause bauen — NUR sichtbare Einträge
    $where = ['sichtbar = 1'];
    $params = [];

    if ($kategorie) {
        $where[] = 'kategorie = ?';
        $params[] = $kategorie;
    }
    if ($jahr) {
        $where[] = 'jahr = ?';
        $params[] = $jahr;
    }
    if ($seit) {
        $where[] = 'erstellt_am >= ?';
        $params[] = $seit;
    }

    $whereClause = implode(' AND ', $where);

    // Total count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM changelog WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Daten (kein OFFSET nötig für WordPress-Widget, limit reicht)
    $dataStmt = $db->prepare("
        SELECT id, kategorie, aktion, beschreibung, jahr, erstellt_am, wp_slug
        FROM changelog
        WHERE $whereClause
        ORDER BY erstellt_am DESC
        LIMIT $limit
    ");
    $dataStmt->execute($params);
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'   => true,
        'data'      => $rows,
        'total'     => $total,
        'limit'     => $limit,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    error_log('[changelog API] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Serverfehler']);
}
