<?php
// api/benachrichtigungen.php - In-App-Benachrichtigungen (Glocke) + Vorstand-Broadcast
//
//   GET  ?action=unread_count           -> {success, count}            (Badge-Polling)
//   GET  ?action=list&limit=&offset=    -> {success, items[], unread}  (Dropdown / Vollseite)
//   POST ?action=mark_read   {id}       -> einen Eintrag als gelesen markieren
//   POST ?action=mark_all_read          -> alle eigenen als gelesen markieren
//   POST ?action=broadcast {titel,text,url,rollen[]}  -> NUR Vorstand/Admin
//
// Schreiben/Senden laeuft ueber benachrichtigungZustellen() (inc/push_helper.php):
// In-App immer, Push nur bei push_aktiv.

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand', 'mitglied', 'jungschuetze']);

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$action = $_GET['action'] ?? '';

// ---------------------------------------------------------------------------
// GET: Lesen
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'unread_count') {
        $s = $db->prepare('SELECT COUNT(*) FROM benachrichtigungen_inbox WHERE user_id = ? AND gelesen_am IS NULL');
        $s->execute([$userId]);
        echo json_encode(['success' => true, 'count' => (int) $s->fetchColumn()]);
        exit;
    }

    if ($action === 'list') {
        $limit  = max(1, min(50, (int) ($_GET['limit'] ?? 20)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        // LIMIT/OFFSET als Integer interpoliert (keine Bind-Unterstuetzung bei manchen Treibern) -> vorher gecastet.
        $s = $db->prepare("SELECT id, titel, text, url, kategorie, gelesen_am, erstellt_am
                           FROM benachrichtigungen_inbox
                           WHERE user_id = ?
                           ORDER BY erstellt_am DESC, id DESC
                           LIMIT $limit OFFSET $offset");
        $s->execute([$userId]);
        $items = $s->fetchAll(PDO::FETCH_ASSOC);

        $u = $db->prepare('SELECT COUNT(*) FROM benachrichtigungen_inbox WHERE user_id = ? AND gelesen_am IS NULL');
        $u->execute([$userId]);

        echo json_encode([
            'success' => true,
            'items'   => $items,
            'unread'  => (int) $u->fetchColumn(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    json_error('Unbekannte Aktion.');
}

// ---------------------------------------------------------------------------
// POST: Schreiben (CSRF-pflichtig)
// ---------------------------------------------------------------------------
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];
$csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validateCsrf($csrf)) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.');
}

if ($action === 'mark_read') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) json_error('Ungültige ID.');
    $s = $db->prepare('UPDATE benachrichtigungen_inbox SET gelesen_am = NOW()
                       WHERE id = ? AND user_id = ? AND gelesen_am IS NULL');
    $s->execute([$id, $userId]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_all_read') {
    $s = $db->prepare('UPDATE benachrichtigungen_inbox SET gelesen_am = NOW()
                       WHERE user_id = ? AND gelesen_am IS NULL');
    $s->execute([$userId]);
    echo json_encode(['success' => true, 'updated' => $s->rowCount()]);
    exit;
}

if ($action === 'broadcast') {
    // Nur Vorstand/Admin duerfen an andere senden.
    if (!isVorstand()) json_error('Zugriff verweigert.', 403);

    $titel = trim((string) ($input['titel'] ?? ''));
    $text  = trim((string) ($input['text'] ?? ''));
    $url   = trim((string) ($input['url'] ?? ''));
    if ($titel === '' || $text === '') json_error('Titel und Text sind erforderlich.');
    if (mb_strlen($titel) > 150) $titel = mb_substr($titel, 0, 150);
    if (mb_strlen($text)  > 500) $text  = mb_substr($text, 0, 500);
    // Nur portal-interne Ziele zulassen (keine externen/JS-URLs). Leer -> Dashboard.
    if ($url === '' || !preg_match('#^(portal/|/portal/)[A-Za-z0-9_./?=&-]*$#', $url)) {
        $url = 'portal/dashboard.php';
    }

    // Rollen-Filter (Whitelist). Leer = alle approved Benutzer.
    $erlaubt = ['admin', 'vorstand', 'mitglied', 'jungschuetze'];
    $rollen  = array_values(array_intersect((array) ($input['rollen'] ?? []), $erlaubt));

    $sql = "SELECT id FROM users WHERE status = 'approved'";
    $params = [];
    if ($rollen) {
        $ph  = implode(',', array_fill(0, count($rollen), '?'));
        $sql .= " AND role IN ($ph)";
        $params = $rollen;
    }
    $empf = $db->prepare($sql);
    $empf->execute($params);
    $ids = array_map('intval', $empf->fetchAll(PDO::FETCH_COLUMN));

    // Antwort sofort senden, Versand laeuft danach weiter (kann bei vielen Usern dauern).
    echo json_encode(['success' => true, 'empfaenger' => count($ids),
        'message' => 'Mitteilung an ' . count($ids) . ' Empfänger gesendet.']);
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    @session_write_close();

    require_once __DIR__ . '/../inc/push_helper.php';
    foreach ($ids as $uid) {
        try {
            benachrichtigungZustellen($uid, $titel, $text, $url, 'mitteilung');
        } catch (\Throwable $e) {
            error_log('broadcast (user ' . $uid . '): ' . $e->getMessage());
        }
    }
    exit;
}

json_error('Unbekannte Aktion.');
