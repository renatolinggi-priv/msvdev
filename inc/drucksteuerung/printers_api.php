<?php
/**
 * pages/drucksteuerung/printers_api.php — Drucker-Verwaltung
 *
 * GET:  Liste aller Drucker
 * POST: Drucker hinzufügen/bearbeiten/löschen
 *       action = "add" | "update" | "delete"
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../session_config.inc.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Direktdruck nur im Admin-Bereich (admin/vorstand)

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

// GET ?andere=1: Einrichtungen desselben Benutzers an anderen Arbeitsplätzen (Browser-Kennungen),
// damit ein neuer Laptop oder gelöschte Browserdaten nicht alles neu verlangen (seit 1.68.52)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['andere'])) {
    $stmt = $db->prepare("
        SELECT pp.machine_id, COUNT(*) AS profile, MAX(pp.erstellt_am) AS zuletzt,
               GROUP_CONCAT(DISTINCT COALESCE(NULLIF(p.anzeigename, ''), p.name) ORDER BY p.name SEPARATOR ', ') AS drucker
        FROM print_profiles pp
        JOIN printers p ON p.id = pp.printer_id
        WHERE pp.benutzer_id = ? AND NOT (pp.machine_id <=> ?)
        GROUP BY pp.machine_id
        ORDER BY zuletzt DESC
    ");
    $stmt->execute([$userId, $machineId]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

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
        echo json_encode(['success' => false, 'message' => 'Sitzung abgelaufen – bitte die Seite neu laden und nochmals versuchen.']);
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
            echo json_encode(['success' => true, 'message' => 'Drucker übernommen', 'id' => (int)$db->lastInsertId()]);
            exit;

        case 'update':
            $id = intval($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
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
                echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
                exit;
            }
            $stmt = $db->prepare("DELETE FROM printers WHERE id = ? AND benutzer_id = ?");
            $stmt->execute([$id, $userId]);
            echo json_encode(['success' => true, 'message' => 'Drucker gelöscht']);
            exit;

        // Einrichtung eines anderen Arbeitsplatzes auf diesen Browser kopieren: Drucker (gleicher Systemname
        // wird wiederverwendet) und Profile, die hier noch keinen Drucker haben. Bestehendes bleibt unverändert.
        case 'uebernehmen':
            $von = array_key_exists('von', $input) && $input['von'] !== '' ? (string)$input['von'] : null;
            if ($von === $machineId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Das ist bereits dieser Arbeitsplatz.']);
                exit;
            }
            $db->beginTransaction();
            try {
                $neuDrucker = 0;
                $neuProfile = 0;
                $karte = []; // alte printer_id => neue printer_id
                $quelle = $db->prepare("SELECT * FROM printers WHERE benutzer_id = ? AND machine_id <=> ?");
                $quelle->execute([$userId, $von]);
                $finde = $db->prepare("SELECT id FROM printers WHERE benutzer_id = ? AND machine_id <=> ? AND name = ? LIMIT 1");
                $neu = $db->prepare("INSERT INTO printers (benutzer_id, machine_id, name, anzeigename, typ, beschreibung, ist_standard, aktiv) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($quelle->fetchAll() as $d) {
                    $finde->execute([$userId, $machineId, $d['name']]);
                    $vorhanden = $finde->fetchColumn();
                    if ($vorhanden) {
                        $karte[(int)$d['id']] = (int)$vorhanden;
                        continue;
                    }
                    $neu->execute([$userId, $machineId, $d['name'], $d['anzeigename'], $d['typ'], $d['beschreibung'], (int)$d['ist_standard'], (int)$d['aktiv']]);
                    $karte[(int)$d['id']] = (int)$db->lastInsertId();
                    $neuDrucker++;
                }

                $hier = $db->prepare("SELECT doc_type, id, printer_id FROM print_profiles WHERE benutzer_id = ? AND machine_id <=> ?");
                $hier->execute([$userId, $machineId]);
                $hierNach = [];
                foreach ($hier->fetchAll() as $h) {
                    $hierNach[$h['doc_type']] = $h;
                }
                $profile = $db->prepare("SELECT * FROM print_profiles WHERE benutzer_id = ? AND machine_id <=> ? AND printer_id IS NOT NULL");
                $profile->execute([$userId, $von]);
                $einfuegen = $db->prepare("INSERT INTO print_profiles (benutzer_id, machine_id, doc_type, anzeigename, printer_id, print_mode, copies, paper_size, orientation, color_mode, duplex, optionen, aktiv) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $setzen = $db->prepare("UPDATE print_profiles SET printer_id = ?, print_mode = ?, copies = ?, paper_size = ?, orientation = ?, color_mode = ?, duplex = ?, optionen = ? WHERE id = ? AND benutzer_id = ?");
                foreach ($profile->fetchAll() as $p) {
                    $druckerNeu = $karte[(int)$p['printer_id']] ?? null;
                    if (!$druckerNeu) continue;
                    $h = $hierNach[$p['doc_type']] ?? null;
                    if ($h && $h['printer_id']) continue; // hier schon eingerichtet: nicht überschreiben
                    if ($h) {
                        $setzen->execute([$druckerNeu, $p['print_mode'], (int)$p['copies'], $p['paper_size'], $p['orientation'], $p['color_mode'], $p['duplex'], $p['optionen'], (int)$h['id'], $userId]);
                    } else {
                        $einfuegen->execute([$userId, $machineId, $p['doc_type'], $p['anzeigename'], $druckerNeu, $p['print_mode'], (int)$p['copies'], $p['paper_size'], $p['orientation'], $p['color_mode'], $p['duplex'], $p['optionen'], (int)$p['aktiv']]);
                    }
                    $neuProfile++;
                }
                $db->commit();
                echo json_encode(['success' => true, 'drucker' => $neuDrucker, 'profile' => $neuProfile,
                    'message' => $neuProfile . ' Profile und ' . $neuDrucker . ' Drucker übernommen.']);
            } catch (Throwable $e) {
                $db->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => msvFehler('Die Einrichtung konnte nicht übernommen werden. Bitte nochmals versuchen.', $e)]);
            }
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
            exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
