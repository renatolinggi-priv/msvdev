<?php
// api/jsk_betreuung.php - Betreuer-Board: offene Anfragen listen / uebernehmen / freigeben.
// JSON, CSRF, nur Mitglieder/Vorstand/Admin mit aktivierter Jungschuetzen-Betreuung.

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../inc/chat.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['mitglied', 'vorstand', 'admin']);

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);

/** Nur aktivierte Betreuer (benachrichtigung_prefs.jsk_betreuung = 1) duerfen das Board nutzen. */
function jskUserIstBetreuer(PDO $db, int $userId): bool {
    $s = $db->prepare('SELECT jsk_betreuung FROM benachrichtigung_prefs WHERE user_id = ?');
    $s->execute([$userId]);
    return (int) $s->fetchColumn() === 1;
}

// ---- GET: Board laden -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list') {
    if (!jskFeatureAktiv() || !jskUserIstBetreuer($db, $userId)) {
        echo json_encode(['success' => true, 'anfragen' => []]);
        exit;
    }
    $stmt = $db->prepare(
        "SELECT a.id, a.datum, a.zeit, a.bemerkung, a.status, a.betreut_von_user_id,
                j.Vorname, j.Name, bu.full_name AS betreuer_name
           FROM jsk_betreuung_anfragen a
           JOIN jungschuetzen j ON j.id = a.jungschuetze_id
           LEFT JOIN users bu ON bu.id = a.betreut_von_user_id
          WHERE a.datum >= CURDATE() AND a.status IN ('offen','vergeben')
          ORDER BY a.datum ASC, a.id ASC"
    );
    $stmt->execute();
    $list = [];
    foreach ($stmt->fetchAll() as $r) {
        $list[] = [
            'id'        => (int) $r['id'],
            'datum'     => $r['datum'],
            'zeit'      => $r['zeit'],
            'bemerkung' => $r['bemerkung'],
            'status'    => $r['status'],
            'name'      => trim($r['Vorname'] . ' ' . $r['Name']),
            'betreuer'  => $r['betreuer_name'],
            'mine'      => ((int) $r['betreut_von_user_id'] === $userId),
        ];
    }
    echo json_encode(['success' => true, 'anfragen' => $list]);
    exit;
}

// ---- schreibende Aktionen ---------------------------------------------------
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];
$csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validateCsrf($csrf)) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);
}
if (!jskFeatureAktiv()) {
    json_error('Die Jungschützen-Betreuung ist derzeit deaktiviert.', 403);
}
if (!jskUserIstBetreuer($db, $userId)) {
    json_error('Bitte aktiviere zuerst die Jungschützen-Betreuung in deinen Einstellungen.', 403);
}

$action = $input['action'] ?? '';
$id     = (int) ($input['id'] ?? 0);
if ($id <= 0) json_error('Keine gültige Anfrage.');

if ($action === 'claim') {
    // Atomar: nur uebernehmen, wenn noch offen -> verhindert Doppelvergabe
    $upd = $db->prepare(
        "UPDATE jsk_betreuung_anfragen
            SET status = 'vergeben', betreut_von_user_id = ?, betreut_am = NOW()
          WHERE id = ? AND status = 'offen'"
    );
    $upd->execute([$userId, $id]);
    if ($upd->rowCount() === 0) {
        json_error('Dieser Termin wurde bereits vergeben.', 409);
    }

    // Jungschuetzen benachrichtigen
    try {
        $info = $db->prepare(
            "SELECT a.datum, j.id AS js_id, u.id AS js_user_id, bu.full_name AS betreuer_name
               FROM jsk_betreuung_anfragen a
               JOIN jungschuetzen j ON j.id = a.jungschuetze_id
               LEFT JOIN users u ON u.jungschuetze_id = j.id AND u.status = 'approved'
               LEFT JOIN users bu ON bu.id = a.betreut_von_user_id
              WHERE a.id = ?"
        );
        $info->execute([$id]);
        if ($row = $info->fetch()) {
            if (!empty($row['js_user_id'])) {
                // Match-Chat zwischen Jungschütze und betreuendem Mitglied sicherstellen
                chatEnsureMatchConversation($db, (int) $row['js_user_id'], $userId);
                $datumDe = date('d.m.Y', strtotime($row['datum']));
                jskSendPush((int) $row['js_user_id'], 'Betreuer gefunden',
                    ($row['betreuer_name'] ?: 'Ein Mitglied') . ' betreut dich am ' . $datumDe . '.',
                    'portal/jsk_dashboard.php');
            }
        }
    } catch (Throwable $e) { error_log('jsk claim push: ' . $e->getMessage()); }

    echo json_encode(['success' => true, 'message' => 'Du betreust diesen Jungschützen. Danke!']);
    exit;
}

if ($action === 'release') {
    // Nur die eigene Uebernahme zuruecknehmen
    $upd = $db->prepare(
        "UPDATE jsk_betreuung_anfragen
            SET status = 'offen', betreut_von_user_id = NULL, betreut_am = NULL
          WHERE id = ? AND betreut_von_user_id = ? AND status = 'vergeben'"
    );
    $upd->execute([$id, $userId]);
    if ($upd->rowCount() === 0) {
        json_error('Konnte nicht freigegeben werden.', 409);
    }
    echo json_encode(['success' => true, 'message' => 'Betreuung freigegeben – andere können wieder übernehmen.']);
    exit;
}

json_error('Unbekannte Aktion.');

// ---------------------------------------------------------------------------
function jskSendPush(int $userId, string $titel, string $text, string $url): void {
    $helper = __DIR__ . '/../inc/push_helper.php';
    if (!file_exists($helper)) return;
    require_once $helper;
    if (function_exists('benachrichtigungZustellen')) {
        benachrichtigungZustellen($userId, $titel, $text, $url, 'jsk_betreuung');
    }
}
