<?php
/**
 * pages/drucksteuerung/profiles_api.php — Druckprofile verwalten
 *
 * GET:  Liste aller Profile (mit Druckernamen), optional gefiltert nach doc_type
 * POST: Profil erstellen/bearbeiten/loeschen/bulk-save
 *       action = "add" | "update" | "delete" | "save_all"
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

// Machine-ID: fuer Mehrplatz-Druckertrennung (UUID aus localStorage)
$machineId = $_SERVER['HTTP_X_MACHINE_ID'] ?? null;
$machineWhere = $machineId ? 'pp.machine_id = ?' : 'pp.machine_id IS NULL';

// GET: Profile des aktuellen Benutzers mit Druckernamen
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $docTypeFilter = $_GET['doc_type'] ?? null;
    $params = $machineId ? [$userId, $machineId] : [$userId];
    if ($docTypeFilter) {
        $stmt = $db->prepare("
            SELECT pp.*, p.name AS printer_name, p.anzeigename AS printer_anzeigename
            FROM print_profiles pp
            LEFT JOIN printers p ON pp.printer_id = p.id
            WHERE pp.benutzer_id = ? AND $machineWhere AND pp.doc_type = ? AND pp.aktiv = 1
            ORDER BY pp.anzeigename ASC
        ");
        $stmt->execute(array_merge($params, [$docTypeFilter]));
    } else {
        $stmt = $db->prepare("
            SELECT pp.*, p.name AS printer_name, p.anzeigename AS printer_anzeigename
            FROM print_profiles pp
            LEFT JOIN printers p ON pp.printer_id = p.id
            WHERE pp.benutzer_id = ? AND $machineWhere
            ORDER BY pp.anzeigename ASC
        ");
        $stmt->execute($params);
    }
    $profiles = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $profiles]);
    exit;
}

// POST: Profil verwalten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Ungueltiges CSRF-Token']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        // -- Bulk-Save: alle Profile in einer Transaktion --
        case 'save_all':
            $profiles = $input['profiles'] ?? [];
            $deleteIds = $input['delete_ids'] ?? [];
            $saved = 0;
            $deleted = 0;

            $db->beginTransaction();
            try {
                // 1) Profile mit leerem Drucker loeschen
                if (!empty($deleteIds)) {
                    $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                    $delSql = "DELETE FROM print_profiles WHERE id IN ($placeholders) AND benutzer_id = ?";
                    $delParams = array_map('intval', $deleteIds);
                    $delParams[] = $userId;
                    if ($machineId) {
                        $delSql .= " AND machine_id = ?";
                        $delParams[] = $machineId;
                    }
                    $stmt = $db->prepare($delSql);
                    $stmt->execute($delParams);
                    $deleted = $stmt->rowCount();
                }

                // 2) Profile speichern (Insert oder Update)
                foreach ($profiles as $p) {
                    $optionen = isset($p['optionen']) && $p['optionen'] !== '' ? trim($p['optionen']) : null;

                    if (!empty($p['id'])) {
                        $stmt = $db->prepare("
                            UPDATE print_profiles SET
                                anzeigename = ?, printer_id = ?, print_mode = ?,
                                copies = ?, paper_size = ?, orientation = ?,
                                color_mode = ?, duplex = ?, optionen = ?
                            WHERE id = ? AND benutzer_id = ?
                        ");
                        $stmt->execute([
                            trim($p['anzeigename'] ?? ''),
                            !empty($p['printer_id']) ? intval($p['printer_id']) : null,
                            $p['print_mode'] ?? 'pixel',
                            intval($p['copies'] ?? 1),
                            $p['paper_size'] ?? 'A4',
                            $p['orientation'] ?? 'portrait',
                            $p['color_mode'] ?? 'blackwhite',
                            $p['duplex'] ?? '',
                            $optionen,
                            intval($p['id']),
                            $userId
                        ]);
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO print_profiles
                                (benutzer_id, machine_id, doc_type, anzeigename, printer_id, print_mode,
                                 copies, paper_size, orientation, color_mode, duplex, optionen)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $userId,
                            $machineId,
                            trim($p['doc_type'] ?? ''),
                            trim($p['anzeigename'] ?? ''),
                            !empty($p['printer_id']) ? intval($p['printer_id']) : null,
                            $p['print_mode'] ?? 'pixel',
                            intval($p['copies'] ?? 1),
                            $p['paper_size'] ?? 'A4',
                            $p['orientation'] ?? 'portrait',
                            $p['color_mode'] ?? 'blackwhite',
                            $p['duplex'] ?? '',
                            $optionen
                        ]);
                    }
                    $saved++;
                }

                $db->commit();
                echo json_encode(['success' => true, 'saved' => $saved, 'deleted' => $deleted]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern']);
            }
            exit;

        case 'add':
            $docType = trim($input['doc_type'] ?? '');
            $anzeigename = trim($input['anzeigename'] ?? '');
            if (empty($docType) || empty($anzeigename)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Dokumenttyp und Anzeigename erforderlich']);
                exit;
            }
            $optionen = isset($input['optionen']) && $input['optionen'] !== '' ? trim($input['optionen']) : null;
            $stmt = $db->prepare("INSERT INTO print_profiles (benutzer_id, machine_id, doc_type, anzeigename, printer_id, print_mode, copies, paper_size, orientation, color_mode, duplex, optionen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $machineId,
                $docType,
                $anzeigename,
                !empty($input['printer_id']) ? intval($input['printer_id']) : null,
                $input['print_mode'] ?? 'pixel',
                intval($input['copies'] ?? 1),
                $input['paper_size'] ?? 'A4',
                $input['orientation'] ?? 'portrait',
                $input['color_mode'] ?? 'blackwhite',
                !empty($input['duplex']) ? 1 : 0,
                $optionen,
            ]);
            echo json_encode(['success' => true, 'message' => 'Profil erstellt', 'id' => (int)$db->lastInsertId()]);
            exit;

        case 'update':
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ungueltige ID']);
                exit;
            }
            $optionenUpd = isset($input['optionen']) && $input['optionen'] !== '' ? trim($input['optionen']) : null;
            $stmt = $db->prepare("UPDATE print_profiles SET anzeigename=?, printer_id=?, print_mode=?, copies=?, paper_size=?, orientation=?, color_mode=?, duplex=?, optionen=? WHERE id=? AND benutzer_id=?");
            $stmt->execute([
                trim($input['anzeigename'] ?? ''),
                !empty($input['printer_id']) ? intval($input['printer_id']) : null,
                $input['print_mode'] ?? 'pixel',
                intval($input['copies'] ?? 1),
                $input['paper_size'] ?? 'A4',
                $input['orientation'] ?? 'portrait',
                $input['color_mode'] ?? 'blackwhite',
                !empty($input['duplex']) ? 1 : 0,
                $optionenUpd,
                $id,
                $userId,
            ]);
            echo json_encode(['success' => true, 'message' => 'Profil aktualisiert']);
            exit;

        case 'delete':
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ungueltige ID']);
                exit;
            }
            $stmt = $db->prepare("DELETE FROM print_profiles WHERE id = ? AND benutzer_id = ?");
            $stmt->execute([$id, $userId]);
            echo json_encode(['success' => true, 'message' => 'Profil geloescht']);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
            exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
