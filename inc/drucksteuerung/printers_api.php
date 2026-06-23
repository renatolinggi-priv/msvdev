<?php
/**
 * pages/drucksteuerung/printers_api.php — Drucker-Verwaltung
 *
 * GET:  Liste aller Drucker
 * POST: Drucker hinzufuegen/bearbeiten/loeschen
 *       action = "add" | "update" | "delete"
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
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$machineId = $_SERVER['HTTP_X_MACHINE_ID'] ?? null;

// GET: Drucker des aktuellen Benutzers auflisten
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($machineId) {
        $stmt = $db->prepare("SELECT * FROM printers WHERE benutzer_id = ? AND machine_id = ? ORDER BY ist_standard DESC, name ASC");
        $stmt->execute([$userId, $machineId]);
    } else {
        $stmt = $db->prepare("SELECT * FROM printers WHERE benutzer_id = ? AND machine_id IS NULL ORDER BY ist_standard DESC, name ASC");
        $stmt->execute([$userId]);
    }
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

// POST: Drucker verwalten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Ungueltiges CSRF-Token']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'add':
            $name = trim($input['name'] ?? '');
            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Druckername fehlt']);
                exit;
            }
            $stmt = $db->prepare("INSERT INTO printers (benutzer_id, machine_id, name, anzeigename, typ, beschreibung, ist_standard) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $machineId,
                $name,
                trim($input['anzeigename'] ?? '') ?: $name,
                $input['typ'] ?? 'laser',
                $input['beschreibung'] ?? '',
                !empty($input['ist_standard']) ? 1 : 0,
            ]);
            echo json_encode(['success' => true, 'message' => 'Drucker hinzugefuegt', 'id' => (int)$db->lastInsertId()]);
            exit;

        case 'update':
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ungueltige ID']);
                exit;
            }
            $stmt = $db->prepare("UPDATE printers SET anzeigename=?, typ=?, beschreibung=?, ist_standard=?, aktiv=? WHERE id=? AND benutzer_id=?");
            $stmt->execute([
                trim($input['anzeigename'] ?? ''),
                $input['typ'] ?? 'laser',
                $input['beschreibung'] ?? '',
                !empty($input['ist_standard']) ? 1 : 0,
                isset($input['aktiv']) ? (int)$input['aktiv'] : 1,
                $id,
                $userId,
            ]);
            echo json_encode(['success' => true, 'message' => 'Drucker aktualisiert']);
            exit;

        case 'delete':
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ungueltige ID']);
                exit;
            }
            $stmt = $db->prepare("DELETE FROM printers WHERE id = ? AND benutzer_id = ?");
            $stmt->execute([$id, $userId]);
            echo json_encode(['success' => true, 'message' => 'Drucker geloescht']);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
            exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
