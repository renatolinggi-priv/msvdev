<?php
// api/chat.php – 1:1-Chat (Jungschütze ↔ Leiter / Match). JSON, PDO, CSRF.
// GET : list | messages | sync | unread | jsk_list
// POST: open | send | upload (multipart, ?action=upload) | read | delete
//
// „gelesen" wird NUR über POST read gesetzt (der Client tut das, wenn der Tab sichtbar ist),
// nie implizit beim Laden – sonst verschwand der Badge, obwohl niemand hinschaute.

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../inc/chat.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand', 'mitglied', 'jungschuetze']);

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

const CHAT_DELETE_MINUTEN = 10;

// Anzeigename der "Gegenseite" einer Konversation aus Sicht von $viewer
function chatDisplayName(array $c, int $viewer): string {
    $jsName      = trim((string) ($c['js_name'] ?? '')) ?: 'Jungschütze';
    $partnerName = trim((string) ($c['partner_name'] ?? '')) ?: 'Mitglied';
    if ($c['typ'] === 'leiter') {
        return ($viewer === (int) $c['js_user_id']) ? 'Jungschützenleitung' : $jsName;
    }
    // match
    if ($viewer === (int) $c['js_user_id']) return $partnerName;
    if ($viewer === (int) ($c['partner_user_id'] ?? 0)) return $jsName;
    return $jsName . ' ↔ ' . $partnerName;   // Leitung liest mit
}
// Untertitel im Thread-Kopf (WhatsApp: Status-Zeile)
function chatDisplaySub(array $c, int $viewer, string $level): string {
    if ($level === 'read') return 'Betreuer-Chat · nur Lesezugriff';
    if ($c['typ'] === 'leiter') {
        return ($viewer === (int) $c['js_user_id']) ? 'Deine Ansprechpersonen im Kurs' : 'Jungschütze';
    }
    return ($viewer === (int) $c['js_user_id']) ? 'Dein Betreuer' : 'Jungschütze';
}
function chatInitials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $a = mb_substr($parts[0] ?? '', 0, 1);
    $b = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);
    return mb_strtoupper($a . ($b ?: ''));
}
/** Bild-URLs (relativ zu portal/) einer Nachricht. */
function chatBildUrls(int $msgId, ?int $w, ?int $h): array {
    return [
        't' => '../api/chat_bild.php?id=' . $msgId . '&s=t',
        'f' => '../api/chat_bild.php?id=' . $msgId . '&s=f',
        'w' => (int) ($w ?: 4), 'h' => (int) ($h ?: 3),
    ];
}

/** Konversationsliste aus Sicht von $userId. */
function chatListe(PDO $db, int $userId): array {
    $isLeiter = isJskLeiter($db, $userId) ? 1 : 0;
    $einsicht = ($isLeiter && jskChatLeiterEinsichtAktiv($db)) ? 1 : 0;
    $geloescht = jskDbHatSpalte($db, 'chat_nachrichten', 'geloescht_am');
    $bilder    = chatBilderAktiv($db);
    $textExpr  = $bilder ? "IF(n.text = '' AND n.bild IS NOT NULL, '📷 Bild', n.text)" : 'n.text';
    $lastText  = $geloescht
        ? "(SELECT IF(n.geloescht_am IS NULL, $textExpr, '') FROM chat_nachrichten n WHERE n.conversation_id = c.id ORDER BY n.id DESC LIMIT 1)"
        : "(SELECT $textExpr FROM chat_nachrichten n WHERE n.conversation_id = c.id ORDER BY n.id DESC LIMIT 1)";
    $delFilter = $geloescht ? ' AND n2.geloescht_am IS NULL' : '';
    $stmt = $db->prepare(
        "SELECT c.id, c.typ, c.js_user_id, c.partner_user_id, c.last_message_at,
                " . chatNamenSql() . ",
                $lastText AS last_text,
                (SELECT COUNT(*) FROM chat_nachrichten n2
                   LEFT JOIN chat_gelesen g ON g.conversation_id = c.id AND g.user_id = :me
                  WHERE n2.conversation_id = c.id AND n2.sender_user_id <> :me2$delFilter
                    AND n2.id > COALESCE(g.last_read_nachricht_id, 0)) AS unread
           FROM chat_conversations c
           " . chatNamenJoinSql() . "
          WHERE c.js_user_id = :me3 OR c.partner_user_id = :me4
                OR (:isleiter = 1 AND c.typ = 'leiter' AND c.last_message_at IS NOT NULL)
                OR (:einsicht = 1 AND c.typ = 'match' AND c.last_message_at IS NOT NULL)
          ORDER BY (c.last_message_at IS NULL), c.last_message_at DESC, c.id DESC"
    );
    $stmt->execute([':me' => $userId, ':me2' => $userId, ':me3' => $userId, ':me4' => $userId,
                    ':isleiter' => $isLeiter, ':einsicht' => $einsicht]);
    $out = [];
    foreach ($stmt->fetchAll() as $c) {
        $teilnehmer = ($userId === (int) $c['js_user_id'] || $userId === (int) ($c['partner_user_id'] ?? 0)
                       || ($c['typ'] === 'leiter' && $isLeiter));
        $name = chatDisplayName($c, $userId);
        $out[] = [
            'id'        => (int) $c['id'],
            'typ'       => $c['typ'],
            'name'      => $name,
            'initials'  => chatInitials($name),
            'last_text' => $c['last_text'] !== null ? mb_substr((string) $c['last_text'], 0, 80) : '',
            'last_at'   => $c['last_message_at'],
            'unread'    => $teilnehmer ? (int) $c['unread'] : 0,
            'readonly'  => !$teilnehmer,
        ];
    }
    return $out;
}

/**
 * Bis zu welcher Nachrichten-ID hat die Gegenseite gelesen? (Doppelhäkchen)
 * match: der andere Teilnehmer. leiter: aus Sicht des JSK irgendein Leiter, aus Leitersicht der JSK.
 */
function chatGegenseiteGelesen(PDO $db, array $conv, int $userId): int {
    $js = (int) $conv['js_user_id'];
    if ($conv['typ'] === 'match') {
        $other = ($userId === $js) ? (int) ($conv['partner_user_id'] ?? 0) : $js;
        $st = $db->prepare("SELECT COALESCE(MAX(last_read_nachricht_id),0) FROM chat_gelesen WHERE conversation_id = ? AND user_id = ?");
        $st->execute([(int) $conv['id'], $other]);
    } elseif ($userId === $js) {
        $st = $db->prepare("SELECT COALESCE(MAX(last_read_nachricht_id),0) FROM chat_gelesen WHERE conversation_id = ? AND user_id <> ?");
        $st->execute([(int) $conv['id'], $js]);
    } else {
        $st = $db->prepare("SELECT COALESCE(MAX(last_read_nachricht_id),0) FROM chat_gelesen WHERE conversation_id = ? AND user_id = ?");
        $st->execute([(int) $conv['id'], $js]);
    }
    return (int) $st->fetchColumn();
}

/** Nachrichten einer Konversation ab ID $after (inkl. Kopfdaten). Prüft den Zugriff. */
function chatNachrichten(PDO $db, int $userId, int $convId, int $after): array {
    $conv = chatGetConversation($db, $convId);
    $level = $conv ? chatAccessLevel($db, $conv, $userId) : '';
    if ($level === '') json_error('Kein Zugriff auf diese Konversation.', 403);

    $geloescht = jskDbHatSpalte($db, 'chat_nachrichten', 'geloescht_am');
    $bilder    = chatBilderAktiv($db);
    $delCol  = $geloescht ? 'n.geloescht_am' : 'NULL AS geloescht_am';
    $bildCol = $bilder ? 'n.bild, n.bild_breite, n.bild_hoehe' : 'NULL AS bild, NULL AS bild_breite, NULL AS bild_hoehe';
    $stmt = $db->prepare(
        "SELECT n.id, n.sender_user_id, n.text, n.erstellt_am, $delCol, $bildCol,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(sj.Vorname,''), ' ', COALESCE(sj.Name,''))), ''), u.full_name) AS sender_name
           FROM chat_nachrichten n
           LEFT JOIN users u ON u.id = n.sender_user_id
           LEFT JOIN jungschuetzen sj ON sj.id = u.jungschuetze_id
          WHERE n.conversation_id = ? AND n.id > ? ORDER BY n.id ASC"
    );
    $stmt->execute([$convId, $after]);
    $msgs = [];
    $grenze = time() - CHAT_DELETE_MINUTEN * 60;
    foreach ($stmt->fetchAll() as $m) {
        $mine = ((int) $m['sender_user_id'] === $userId);
        $del  = !empty($m['geloescht_am']);
        $msgs[] = [
            'id'      => (int) $m['id'],
            'mine'    => $mine,
            'sender'  => (string) $m['sender_name'],
            'text'    => $del ? '' : (string) $m['text'],
            'bild'    => (!$del && !empty($m['bild'])) ? chatBildUrls((int) $m['id'], $m['bild_breite'], $m['bild_hoehe']) : null,
            'at'      => $m['erstellt_am'],
            'deleted' => $del,
            'can_del' => ($mine && !$del && $geloescht && strtotime((string) $m['erstellt_am']) >= $grenze),
        ];
    }
    // Namen für den Thread-Header
    $nm = $db->prepare("SELECT " . chatNamenSql() . " FROM chat_conversations c " . chatNamenJoinSql() . " WHERE c.id = ?");
    $nm->execute([$convId]);
    $convFull = array_merge($conv, $nm->fetch() ?: []);

    $einsicht = ($conv['typ'] === 'match') && jskChatLeiterEinsichtAktiv($db);
    $leiterAnzahl = ($conv['typ'] === 'leiter') ? chatLeiterAnzahl($db) : null;
    $hinweis = '';
    if ($level === 'read') {
        $hinweis = 'Nur Lesezugriff (Einsicht der Jungschützenleitung).';
    } elseif ($einsicht) {
        $hinweis = 'Die Jungschützenleitung kann in diesem Chat mitlesen.';
    } elseif ($conv['typ'] === 'leiter' && $leiterAnzahl === 0) {
        $hinweis = 'Derzeit ist kein Jungschützenleiter im Portal erreichbar. Bitte wende dich direkt an die Leitung.';
    }

    return [
        'messages'    => $msgs,
        'partner'     => chatDisplayName($convFull, $userId),
        'partner_sub' => chatDisplaySub($convFull, $userId, $level),
        'typ'         => $conv['typ'],
        'readonly'    => ($level === 'read') || ($conv['typ'] === 'leiter' && $leiterAnzahl === 0 && $userId === (int) $conv['js_user_id']),
        'hinweis'     => $hinweis,
        'other_read'  => chatGegenseiteGelesen($db, $conv, $userId),
        'bilder'      => $bilder,
    ];
}

/** Nach dem Speichern einer Nachricht: Zeitstempel, eigene Lesequittung, Push an die Gegenseite(n). */
function chatNachSenden(PDO $db, array $conv, int $userId, int $msgId, string $vorschau, array $empfaenger): void {
    $convId = (int) $conv['id'];
    $db->prepare("UPDATE chat_conversations SET last_message_at = NOW() WHERE id = ?")->execute([$convId]);
    $db->prepare(
        "INSERT INTO chat_gelesen (conversation_id, user_id, last_read_nachricht_id) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE last_read_nachricht_id = GREATEST(last_read_nachricht_id, VALUES(last_read_nachricht_id))"
    )->execute([$convId, $userId, $msgId]);

    $sn = $db->prepare(
        "SELECT COALESCE(NULLIF(TRIM(CONCAT(COALESCE(j.Vorname,''), ' ', COALESCE(j.Name,''))), ''), u.full_name)
           FROM users u LEFT JOIN jungschuetzen j ON j.id = u.jungschuetze_id WHERE u.id = ?"
    );
    $sn->execute([$userId]);
    $senderName = (string) ($sn->fetchColumn() ?: 'Jemand');
    $url = 'portal/chat.php?c=' . $convId;
    foreach (array_unique($empfaenger) as $eid) {
        if ((int) $eid !== $userId) chatSendPushToUser($db, (int) $eid, 'Neue Nachricht von ' . $senderName, $vorschau, $url);
    }
}

/** Empfänger einer Nachricht in $conv aus Sicht von $userId (leere Leitung -> Fehler). */
function chatEmpfaenger(PDO $db, array $conv, int $userId): array {
    $empfaenger = [];
    if ($conv['typ'] === 'match') {
        $other = ($userId === (int) $conv['js_user_id']) ? (int) $conv['partner_user_id'] : (int) $conv['js_user_id'];
        if ($other > 0) $empfaenger[] = $other;
    } elseif ($userId === (int) $conv['js_user_id']) {
        $empfaenger = jskLeiterUserIds($db);                 // JSK schreibt -> alle Leiter
        if (!$empfaenger) {
            json_error('Derzeit ist kein Jungschützenleiter im Portal erreichbar. Bitte wende dich direkt an die Leitung.');
        }
    } else {
        $empfaenger[] = (int) $conv['js_user_id'];           // Leiter schreibt -> der JSK
    }
    return $empfaenger;
}

// ---------------------------------------------------------------- GET: list
if ($method === 'GET' && $action === 'list') {
    echo json_encode(['success' => true, 'conversations' => chatListe($db, $userId)]);
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
    $out = chatNachrichten($db, $userId, (int) ($_GET['c'] ?? 0), (int) ($_GET['after'] ?? 0));
    echo json_encode(['success' => true] + $out);
    exit;
}

// ---------------------------------------------------------------- GET: sync
// Ein Aufruf statt drei Poller: neue Nachrichten der offenen Konversation (optional),
// Gesamt-Ungelesen und – auf Wunsch – die Liste.
if ($method === 'GET' && $action === 'sync') {
    $convId = (int) ($_GET['c'] ?? 0);
    $res = ['success' => true, 'unread' => chatUnreadCount($db, $userId)];
    if ($convId > 0) {
        $res['thread'] = chatNachrichten($db, $userId, $convId, (int) ($_GET['after'] ?? 0));
    }
    if (!empty($_GET['list'])) {
        $res['conversations'] = chatListe($db, $userId);
    }
    echo json_encode($res);
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
        $convId = chatEnsureLeiterConversation($db, $userId);
    } else {
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
    chatMarkRead($db, $convId, $userId);
    echo json_encode(['success' => true, 'unread' => chatUnreadCount($db, $userId)]);
    exit;
}

// ---------------------------------------------------------------- POST: delete (eigene Nachricht, 10 Min.)
if ($action === 'delete') {
    $msgId = (int) ($input['id'] ?? 0);
    if (!jskDbHatSpalte($db, 'chat_nachrichten', 'geloescht_am')) {
        json_error('Zurücknehmen ist erst nach der Datenbank-Aktualisierung 062 möglich.');
    }
    $bildCol = chatBilderAktiv($db) ? 'bild' : 'NULL AS bild';
    $st = $db->prepare("SELECT conversation_id, sender_user_id, erstellt_am, geloescht_am, $bildCol FROM chat_nachrichten WHERE id = ?");
    $st->execute([$msgId]);
    $m = $st->fetch();
    if (!$m || (int) $m['sender_user_id'] !== $userId) json_error('Nachricht nicht gefunden.', 404);
    if (!empty($m['geloescht_am'])) { echo json_encode(['success' => true]); exit; }
    if (strtotime((string) $m['erstellt_am']) < time() - CHAT_DELETE_MINUTEN * 60) {
        json_error('Nachrichten können nur innerhalb von ' . CHAT_DELETE_MINUTEN . ' Minuten zurückgenommen werden.');
    }
    $db->prepare("UPDATE chat_nachrichten SET geloescht_am = NOW() WHERE id = ?")->execute([$msgId]);
    chatBildLoeschen((int) $m['conversation_id'], $m['bild'] ?? null);   // Bilddateien sofort weg
    echo json_encode(['success' => true]);
    exit;
}

// ---------------------------------------------------------------- POST: upload (Bild, multipart)
if ($action === 'upload') {
    if (!chatBilderAktiv($db)) json_error('Bilder sind erst nach der Datenbank-Aktualisierung 063 möglich.');
    $convId = (int) ($_POST['c'] ?? 0);
    $text   = trim((string) ($_POST['text'] ?? ''));
    $conv = chatGetConversation($db, $convId);
    $level = $conv ? chatAccessLevel($db, $conv, $userId) : '';
    if ($level === '') json_error('Kein Zugriff auf diese Konversation.', 403);
    if ($level === 'read') json_error('In diesem Chat hast du nur Lesezugriff.', 403);
    if (mb_strlen($text) > 2000) $text = mb_substr($text, 0, 2000);

    $f = $_FILES['file'] ?? null;
    if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $code = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
        json_error(in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'Das Bild ist zu gross (max. 15 MB).' : 'Kein Bild empfangen.');
    }
    $empfaenger = chatEmpfaenger($db, $conv, $userId);   // vor dem Speichern (leere Leitung -> Abbruch)

    try {
        $bild = chatBildSpeichern((string) $f['tmp_name'], $convId);
    } catch (RuntimeException $e) {
        json_error($e->getMessage());
    }
    $ins = $db->prepare("INSERT INTO chat_nachrichten (conversation_id, sender_user_id, text, bild, bild_breite, bild_hoehe) VALUES (?, ?, ?, ?, ?, ?)");
    $ins->execute([$convId, $userId, $text, $bild['datei'], $bild['breite'], $bild['hoehe']]);
    $msgId = (int) $db->lastInsertId();
    chatNachSenden($db, $conv, $userId, $msgId, '📷 Bild' . ($text !== '' ? ' – ' . mb_substr($text, 0, 100) : ''), $empfaenger);
    echo json_encode(['success' => true, 'id' => $msgId]);
    exit;
}

// ---------------------------------------------------------------- POST: send
if ($action === 'send') {
    $convId = (int) ($input['c'] ?? 0);
    $text   = trim((string) ($input['text'] ?? ''));
    $conv = chatGetConversation($db, $convId);
    $level = $conv ? chatAccessLevel($db, $conv, $userId) : '';
    if ($level === '') json_error('Kein Zugriff auf diese Konversation.', 403);
    if ($level === 'read') json_error('In diesem Chat hast du nur Lesezugriff.', 403);
    if ($text === '') json_error('Leere Nachricht.');
    if (mb_strlen($text) > 2000) $text = mb_substr($text, 0, 2000);

    $empfaenger = chatEmpfaenger($db, $conv, $userId);   // vor dem Speichern (leere Leitung -> Abbruch)

    $ins = $db->prepare("INSERT INTO chat_nachrichten (conversation_id, sender_user_id, text) VALUES (?, ?, ?)");
    $ins->execute([$convId, $userId, $text]);
    $msgId = (int) $db->lastInsertId();
    chatNachSenden($db, $conv, $userId, $msgId, mb_substr($text, 0, 120), $empfaenger);
    echo json_encode(['success' => true, 'id' => $msgId]);
    exit;
}

json_error('Unbekannte Aktion.');
