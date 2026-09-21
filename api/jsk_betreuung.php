<?php
// api/jsk_betreuung.php - Betreuer-Board: offene Anfragen listen / uebernehmen / freigeben.
// JSON, CSRF. Zugriff: aktivierte Betreuer (benachrichtigung_prefs.jsk_betreuung = 1) sowie
// Jungschuetzenleiter (mitglieder.ist_jsk_leiter) – Rollen mitglied/vorstand/admin.

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../inc/chat.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['mitglied', 'vorstand', 'admin']);

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);

$darfBoard = jskIstBetreuer($db, $userId) || isJskLeiter($db, $userId);

// ---- GET: Board laden -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list') {
    if (!jskFeatureAktiv() || !$darfBoard) {
        echo json_encode(['success' => true, 'anfragen' => []]);
        exit;
    }
    $hatTermin = jskDbHatSpalte($db, 'jsk_betreuung_anfragen', 'termin_id');
    $terminSel  = $hatTermin ? 'wt.name AS termin_name' : 'NULL AS termin_name';
    $terminJoin = $hatTermin ? 'LEFT JOIN wichtige_termine wt ON wt.ID = a.termin_id' : '';
    $stmt = $db->prepare(
        "SELECT a.id, a.datum, a.zeit, a.bemerkung, a.status, a.betreut_von_user_id, a.jungschuetze_id,
                j.Vorname, j.Name, bu.full_name AS betreuer_name, $terminSel,
                (SELECT u.id FROM users u WHERE u.jungschuetze_id = j.id AND u.status = 'approved' LIMIT 1) AS js_user_id
           FROM jsk_betreuung_anfragen a
           JOIN jungschuetzen j ON j.id = a.jungschuetze_id
           LEFT JOIN users bu ON bu.id = a.betreut_von_user_id
           $terminJoin
          WHERE a.datum >= CURDATE() AND a.status IN ('offen','vergeben')
          ORDER BY a.datum ASC, a.id ASC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Tage, an denen ich bereits jemanden betreue (Hinweis bei Mehrfachbetreuung)
    $meineTage = [];
    foreach ($rows as $r) {
        if ((int) $r['betreut_von_user_id'] === $userId) $meineTage[$r['datum']] = trim($r['Vorname'] . ' ' . $r['Name']);
    }

    $list = [];
    foreach ($rows as $r) {
        $mine = ((int) $r['betreut_von_user_id'] === $userId);
        $chat = 0;
        if ($mine && !empty($r['js_user_id'])) {
            $chat = chatFindMatchConversation($db, (int) $r['js_user_id'], $userId);   // nur lesen, kein INSERT im GET
        }
        $list[] = [
            'id'          => (int) $r['id'],
            'datum'       => $r['datum'],
            'datum_de'    => date('d.m.Y', strtotime($r['datum'])),
            'zeit'        => $r['zeit'],
            'bemerkung'   => $r['bemerkung'],
            'termin'      => $r['termin_name'],
            'status'      => $r['status'],
            'name'        => trim($r['Vorname'] . ' ' . $r['Name']),
            'betreuer'    => $r['betreuer_name'],
            'mine'        => $mine,
            'hat_login'   => !empty($r['js_user_id']),
            'chat_conv'   => $chat,
            'same_day'    => (!$mine && isset($meineTage[$r['datum']])) ? $meineTage[$r['datum']] : null,
        ];
    }
    echo json_encode(['success' => true, 'anfragen' => $list, 'ts' => date('H:i')]);
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
if (!$darfBoard) {
    json_error('Bitte aktiviere zuerst die Jungschützen-Betreuung in deinen Einstellungen.', 403);
}

$action = $input['action'] ?? '';
$id     = (int) ($input['id'] ?? 0);
if ($id <= 0) json_error('Keine gültige Anfrage.');

if ($action === 'claim') {
    // Atomar: nur uebernehmen, wenn noch offen und nicht vergangen -> verhindert Doppelvergabe
    $upd = $db->prepare(
        "UPDATE jsk_betreuung_anfragen
            SET status = 'vergeben', betreut_von_user_id = ?, betreut_am = NOW()
          WHERE id = ? AND status = 'offen' AND datum >= CURDATE()"
    );
    $upd->execute([$userId, $id]);
    if ($upd->rowCount() === 0) {
        json_error('Dieser Termin wurde bereits vergeben oder liegt in der Vergangenheit.', 409);
    }

    // Jungschuetzen benachrichtigen + Match-Chat anlegen
    try {
        $info = jskAnfrageInfo($db, $id);
        if ($info && $info['js_user_id'] > 0) {
            chatEnsureMatchConversation($db, $info['js_user_id'], $userId);
            jskBenachrichtigen($info['js_user_id'], 'Betreuer gefunden',
                ($info['betreuer_name'] ?: 'Ein Mitglied') . ' betreut dich am ' . $info['datum_de'] . '.',
                'portal/jsk_dashboard.php', 'jsk_betreuung', 'jsk-anfrage-' . $id);
        }
    } catch (Throwable $e) { error_log('jsk claim: ' . $e->getMessage()); }

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

    // Jungschuetzen informieren (er glaubte, versorgt zu sein) + Betreuer-Kreis erneut aufrufen
    try {
        $info = jskAnfrageInfo($db, $id);
        if ($info) {
            $meinName = '';
            try {
                $mn = $db->prepare('SELECT full_name FROM users WHERE id = ?');
                $mn->execute([$userId]);
                $meinName = (string) ($mn->fetchColumn() ?: '');
            } catch (Throwable $e) {}
            if ($info['js_user_id'] > 0) {
                jskBenachrichtigen($info['js_user_id'], 'Betreuung abgesagt',
                    ($meinName !== '' ? $meinName : 'Dein Betreuer') . ' kann am ' . $info['datum_de']
                    . ' doch nicht. Dein Termin ist wieder offen – andere Mitglieder wurden informiert.',
                    'portal/jsk_dashboard.php', 'jsk_betreuung', 'jsk-anfrage-' . $id);
            }
            jskBenachrichtigeBetreuer($db, 'Jungschütze sucht wieder Begleitung',
                $info['js_name'] . ' braucht am ' . $info['datum_de'] . ' erneut eine Begleitung (Betreuung wurde freigegeben).',
                'portal/jsk_betreuung.php', $userId, 'jsk-anfrage-' . $id);
        }
    } catch (Throwable $e) { error_log('jsk release: ' . $e->getMessage()); }

    echo json_encode(['success' => true, 'message' => 'Betreuung freigegeben – der Jungschütze und die anderen Betreuer wurden informiert.']);
    exit;
}

json_error('Unbekannte Aktion.');
