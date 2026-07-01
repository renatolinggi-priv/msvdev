<?php
// api/chat.php – 1:1-Chat (Jungschütze ↔ Leiter / Match). JSON, PDO, CSRF.
// Aktionen: list | messages | unread (GET) ; open | send | read (POST)

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../inc/chat.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand', 'mitglied', 'jungschuetze']);

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Anzeigename der "Gegenseite" einer Konversation aus Sicht von $userId
function chatDisplayName(array $c, int $viewer): string {
    $jsName      = trim((string) ($c['js_name'] ?? '')) ?: 'Jungschütze';
    $partnerName = trim((string) ($c['partner_name'] ?? '')) ?: 'Mitglied';
    if ($c['typ'] === 'leiter') {
        return ($viewer === (int) $c['js_user_id']) ? 'Jungschützenleitung' : $jsName;
    }
    // match
    return ($viewer === (int) $c['js_user_id']) ? $partnerName : $jsName;
}
function chatInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $a = mb_substr($parts[0] ?? '', 0, 1);
    $b = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);
    return mb_strtoupper($a . ($b ?: ''));
}

// ---------------------------------------------------------------- GET: list
if ($method === 'GET' && $action === 'list') {
    $isLeiter = isJskLeiter($db, $userId) ? 1 : 0;
    $stmt = $db->prepare(
        "SELECT c.id, c.typ, c.js_user_id, c.partner_user_id, c.last_message_at,
                ju.full_name AS js_name, pu.full_name AS partner_name,
                (SELECT n.text FROM chat_nachrichten n WHERE n.conversation_id = c.id ORDER BY n.id DESC LIMIT 1) AS last_text,
                (SELECT COUNT(*) FROM chat_nachrichten n2
                   LEFT JOIN chat_gelesen g ON g.conversation_id = c.id AND g.user_id = :me
                  WHERE n2.conversation_id = c.id AND n2.sender_user_id <> :me2
                    AND n2.id > COALESCE(g.last_read_nachricht_id, 0)) AS unread
           FROM chat_conversations c
           LEFT JOIN users ju ON ju.id = c.js_user_id
           LEFT JOIN users pu ON pu.id = c.partner_user_id
          WHERE c.js_user_id = :me3 OR c.partner_user_id = :me4
                OR (:isleiter = 1 AND c.typ = 'leiter' AND c.last_message_at IS NOT NULL)
          ORDER BY (c.last_message_at IS NULL), c.last_message_at DESC, c.id DESC"
    );
    $stmt->execute([':me' => $userId, ':me2' => $userId, ':me3' => $userId, ':me4' => $userId, ':isleiter' => $isLeiter]);
    $out = [];
    foreach ($stmt->fetchAll() as $c) {
        // Leere Leiter-Chats (noch keine Nachricht) nur für die Leiterseite ausblenden wäre möglich;
        // wir zeigen alle, an denen man teilnimmt.
        $name = chatDisplayName($c, $userId);
        $out[] = [
            'id'       => (int) $c['id'],
            'typ'      => $c['typ'],
            'name'     => $name,
            'initials' => chatInitials($name),
            'last_text' => $c['last_text'] !== null ? mb_substr((string) $c['last_text'], 0, 80) : '',
            'last_at'  => $c['last_message_at'],
            'unread'   => (int) $c['unread'],
        ];
    }
    echo json_encode(['success' => true, 'conversations' => $out]);
    exit;
}

// ---------------------------------------------------------------- GET: unread
if ($method === 'GET' && $action === 'unread') {
    echo json_encode(['success' => true, 'unread' => chatUnreadCount($db, $userId)]);
    exit;
}

// ---------------------------------------------------------------- GET: jsk_list (nur Leiter)
if ($method === 'GET' && $action === 'jsk_list') {
    if (!isJskLeiter($db, $userId)) {
        echo json_encode(['success' => true, 'jsk' => []]);
        exit;
    }
    $rows = $db->query(
        "SELECT j.id, j.Vorname, j.Name FROM jungschuetzen j
           JOIN users u ON u.jungschuetze_id = j.id AND u.status = 'approved'
          ORDER BY j.Name ASC, j.Vorname ASC"
    )->fetchAll();
    $jsk = [];
    foreach ($rows as $r) {
        $jsk[] = ['jungschuetze_id' => (int) $r['id'], 'name' => trim($r['Vorname'] . ' ' . $r['Name'])];
    }
    echo json_encode(['success' => true, 'jsk' => $jsk]);
    exit;
}

// ---------------------------------------------------------------- GET: messages
if ($method === 'GET' && $action === 'messages') {
    $convId = (int) ($_GET['c'] ?? 0);
    $after  = (int) ($_GET['after'] ?? 0);
    $conv = chatGetConversation($db, $convId);
    if (!$conv || !chatCanAccess($db, $conv, $userId)) {
        json_error('Kein Zugriff auf diese Konversation.', 403);
    }
    $stmt = $db->prepare(
        "SELECT n.id, n.sender_user_id, n.text, n.erstellt_am, u.full_name AS sender_name
           FROM chat_nachrichten n LEFT JOIN users u ON u.id = n.sender_user_id
          WHERE n.conversation_id = ? AND n.id > ? ORDER BY n.id ASC"
    );
    $stmt->execute([$convId, $after]);
    $msgs = [];
    $maxId = $after;
    foreach ($stmt->fetchAll() as $m) {
        $maxId = max($maxId, (int) $m['id']);
        $msgs[] = [
            'id'     => (int) $m['id'],
            'mine'   => ((int) $m['sender_user_id'] === $userId),
            'sender' => (string) $m['sender_name'],
            'text'   => (string) $m['text'],
            'at'     => $m['erstellt_am'],
        ];
    }
    // Auto-Quittung: alles in dieser Konversation als gelesen markieren
    $top = (int) $db->query("SELECT COALESCE(MAX(id),0) FROM chat_nachrichten WHERE conversation_id = " . (int) $convId)->fetchColumn();
    if ($top > 0) {
        $up = $db->prepare(
            "INSERT INTO chat_gelesen (conversation_id, user_id, last_read_nachricht_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE last_read_nachricht_id = GREATEST(last_read_nachricht_id, VALUES(last_read_nachricht_id))"
        );
        $up->execute([$convId, $userId, $top]);
    }
    // Namen für den Thread-Header nachladen
    $nm = $db->prepare(
        "SELECT ju.full_name AS js_name, pu.full_name AS partner_name
           FROM chat_conversations c
           LEFT JOIN users ju ON ju.id = c.js_user_id
           LEFT JOIN users pu ON pu.id = c.partner_user_id
          WHERE c.id = ?"
    );
    $nm->execute([$convId]);
    $convFull = array_merge($conv, $nm->fetch() ?: []);

    echo json_encode([
        'success' => true,
        'messages' => $msgs,
        'partner' => chatDisplayName($convFull, $userId),
    ]);
    exit;
}

// ------------------------------------------------ ab hier POST -> CSRF nötig
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];
$csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
if (!validateCsrf($csrf)) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);
}
$action = $input['action'] ?? $action;

// ---------------------------------------------------------------- POST: open (Leiter-Chat)
if ($action === 'open') {
    $typ = $input['typ'] ?? 'leiter';
    if ($typ !== 'leiter') json_error('Nur Leiter-Chats können hier geöffnet werden.');

    if (isJungschuetze()) {
        // Jungschütze öffnet seinen eigenen Leiter-Chat
        $convId = chatEnsureLeiterConversation($db, $userId);
    } else {
        // Leiter öffnet den Leiter-Chat eines bestimmten Jungschützen
        if (!isJskLeiter($db, $userId)) {
            json_error('Nur Jungschützenleiter können einen JSK-Chat starten.', 403);
        }
        $jsId = (int) ($input['jungschuetze_id'] ?? 0);
        $jsUserId = chatJsUserIdFromJungschuetze($db, $jsId);
        if ($jsUserId <= 0) json_error('Dieser Jungschütze hat (noch) kein Login.', 404);
        $convId = chatEnsureLeiterConversation($db, $jsUserId);
    }
    echo json_encode(['success' => true, 'conversation_id' => $convId]);
    exit;
}

// ---------------------------------------------------------------- POST: read
if ($action === 'read') {
    $convId = (int) ($input['c'] ?? 0);
    $conv = chatGetConversation($db, $convId);
    if (!$conv || !chatCanAccess($db, $conv, $userId)) json_error('Kein Zugriff.', 403);
    $top = (int) $db->query("SELECT COALESCE(MAX(id),0) FROM chat_nachrichten WHERE conversation_id = " . (int) $convId)->fetchColumn();
    $up = $db->prepare(
        "INSERT INTO chat_gelesen (conversation_id, user_id, last_read_nachricht_id) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE last_read_nachricht_id = GREATEST(last_read_nachricht_id, VALUES(last_read_nachricht_id))"
    );
    $up->execute([$convId, $userId, $top]);
    echo json_encode(['success' => true]);
    exit;
}

// ---------------------------------------------------------------- POST: send
if ($action === 'send') {
    $convId = (int) ($input['c'] ?? 0);
    $text   = trim((string) ($input['text'] ?? ''));
    $conv = chatGetConversation($db, $convId);
    if (!$conv || !chatCanAccess($db, $conv, $userId)) json_error('Kein Zugriff auf diese Konversation.', 403);
    if ($text === '') json_error('Leere Nachricht.');
    if (mb_strlen($text) > 2000) $text = mb_substr($text, 0, 2000);

    $ins = $db->prepare("INSERT INTO chat_nachrichten (conversation_id, sender_user_id, text) VALUES (?, ?, ?)");
    $ins->execute([$convId, $userId, $text]);
    $msgId = (int) $db->lastInsertId();
    $db->prepare("UPDATE chat_conversations SET last_message_at = NOW() WHERE id = ?")->execute([$convId]);
    // Eigene Nachricht gilt als gelesen
    $db->prepare(
        "INSERT INTO chat_gelesen (conversation_id, user_id, last_read_nachricht_id) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE last_read_nachricht_id = GREATEST(last_read_nachricht_id, VALUES(last_read_nachricht_id))"
    )->execute([$convId, $userId, $msgId]);

    // Push an die Gegenseite(n)
    $senderName = (string) ($db->query("SELECT full_name FROM users WHERE id = " . (int) $userId)->fetchColumn() ?: 'Jemand');
    $titel = 'Neue Nachricht von ' . $senderName;
    $vorschau = mb_substr($text, 0, 120);
    $url = 'portal/chat.php?c=' . $convId;

    $empfaenger = [];
    if ($conv['typ'] === 'match') {
        $other = ($userId === (int) $conv['js_user_id']) ? (int) $conv['partner_user_id'] : (int) $conv['js_user_id'];
        if ($other > 0) $empfaenger[] = $other;
    } else { // leiter
        if ($userId === (int) $conv['js_user_id']) {
            $empfaenger = jskLeiterUserIds($db);                 // JSK schreibt -> alle Leiter
        } else {
            $empfaenger[] = (int) $conv['js_user_id'];           // Leiter schreibt -> der JSK
        }
    }
    foreach (array_unique($empfaenger) as $eid) {
        if ((int) $eid !== $userId) chatSendPushToUser($db, (int) $eid, $titel, $vorschau, $url);
    }

    echo json_encode(['success' => true, 'id' => $msgId]);
    exit;
}

json_error('Unbekannte Aktion.');
