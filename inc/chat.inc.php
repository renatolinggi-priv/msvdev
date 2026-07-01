<?php
// inc/chat.inc.php – Helfer fuer den 1:1-Chat (Jungschütze ↔ Leiter / Match).
// Erwartet eine PDO-Verbindung via getDB() (inc/dbconnect.inc.php).

if (!function_exists('isJskLeiter')) {
    /** True, wenn der User-Account zu einem als Jungschützenleiter markierten Mitglied gehört. */
    function isJskLeiter(PDO $db, int $userId): bool {
        if ($userId <= 0) return false;
        $stmt = $db->prepare(
            "SELECT 1 FROM users u JOIN mitglieder m ON m.ID = u.mitglied_id
              WHERE u.id = ? AND m.ist_jsk_leiter = 1 LIMIT 1"
        );
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('jskLeiterUserIds')) {
    /** User-IDs aller freigeschalteten Jungschützenleiter (Mitglieder mit Flag + Login). */
    function jskLeiterUserIds(PDO $db): array {
        $rows = $db->query(
            "SELECT u.id FROM users u JOIN mitglieder m ON m.ID = u.mitglied_id
              WHERE m.ist_jsk_leiter = 1 AND u.status = 'approved'"
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    }
}

if (!function_exists('chatGetConversation')) {
    function chatGetConversation(PDO $db, int $convId): ?array {
        $stmt = $db->prepare("SELECT * FROM chat_conversations WHERE id = ?");
        $stmt->execute([$convId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

if (!function_exists('chatCanAccess')) {
    /** Darf $userId diese Konversation sehen/schreiben? */
    function chatCanAccess(PDO $db, array $conv, int $userId): bool {
        $js      = (int) $conv['js_user_id'];
        $partner = $conv['partner_user_id'] !== null ? (int) $conv['partner_user_id'] : null;
        if ($userId === $js) return true;
        if ($conv['typ'] === 'match') {
            return $partner !== null && $userId === $partner;
        }
        // typ === 'leiter': der Jungschütze ODER ein designierter Leiter
        return isJskLeiter($db, $userId);
    }
}

if (!function_exists('chatJsUserIdFromJungschuetze')) {
    /** User-ID (approved) zum Jungschützen-Stammsatz, oder 0. */
    function chatJsUserIdFromJungschuetze(PDO $db, int $jungschuetzeId): int {
        if ($jungschuetzeId <= 0) return 0;
        $stmt = $db->prepare("SELECT id FROM users WHERE jungschuetze_id = ? AND status = 'approved' LIMIT 1");
        $stmt->execute([$jungschuetzeId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('chatEnsureMatchConversation')) {
    /** Findet/erstellt den Match-Chat (js ↔ Mitglied) und gibt die conversation_id zurück. */
    function chatEnsureMatchConversation(PDO $db, int $jsUserId, int $partnerUserId): int {
        if ($jsUserId <= 0 || $partnerUserId <= 0) return 0;
        $stmt = $db->prepare(
            "INSERT INTO chat_conversations (typ, js_user_id, partner_user_id, erstellt_am)
             VALUES ('match', ?, ?, NOW())
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
        );
        $stmt->execute([$jsUserId, $partnerUserId]);
        return (int) $db->lastInsertId();
    }
}

if (!function_exists('chatEnsureLeiterConversation')) {
    /** Findet/erstellt den Leiter-Chat eines Jungschützen (partner NULL = „Leitung"). */
    function chatEnsureLeiterConversation(PDO $db, int $jsUserId): int {
        if ($jsUserId <= 0) return 0;
        $stmt = $db->prepare("SELECT id FROM chat_conversations WHERE typ = 'leiter' AND js_user_id = ? LIMIT 1");
        $stmt->execute([$jsUserId]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id > 0) return $id;
        $ins = $db->prepare("INSERT INTO chat_conversations (typ, js_user_id, partner_user_id, erstellt_am) VALUES ('leiter', ?, NULL, NOW())");
        $ins->execute([$jsUserId]);
        return (int) $db->lastInsertId();
    }
}

if (!function_exists('chatUnreadCount')) {
    /** Gesamtzahl ungelesener Nachrichten für $userId über alle zugänglichen Konversationen. */
    function chatUnreadCount(PDO $db, int $userId): int {
        $isLeiter = isJskLeiter($db, $userId) ? 1 : 0;
        $stmt = $db->prepare(
            "SELECT COUNT(*)
               FROM chat_nachrichten n
               JOIN chat_conversations c ON c.id = n.conversation_id
               LEFT JOIN chat_gelesen g ON g.conversation_id = c.id AND g.user_id = :me
              WHERE n.sender_user_id <> :me2
                AND n.id > COALESCE(g.last_read_nachricht_id, 0)
                AND ( c.js_user_id = :me3 OR c.partner_user_id = :me4 OR (:isleiter = 1 AND c.typ = 'leiter') )"
        );
        $stmt->execute([
            ':me' => $userId, ':me2' => $userId, ':me3' => $userId, ':me4' => $userId, ':isleiter' => $isLeiter,
        ]);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('chatSendPushToUser')) {
    /** Best-effort Push (bricht nie die Aktion ab). Respektiert benachrichtigung_prefs.chat. */
    function chatSendPushToUser(PDO $db, int $userId, string $titel, string $text, string $url): void {
        if ($userId <= 0) return;
        try {
            // Opt-In prüfen: chat-Toggle (Default 1) + push_aktiv (Default 1)
            $st = $db->prepare("SELECT COALESCE(chat,1) AS chat, COALESCE(push_aktiv,1) AS push_aktiv FROM benachrichtigung_prefs WHERE user_id = ?");
            $st->execute([$userId]);
            $p = $st->fetch();
            if ($p && ((int) $p['chat'] !== 1 || (int) $p['push_aktiv'] !== 1)) return;

            $helper = __DIR__ . '/push_helper.php';
            if (!file_exists($helper)) return;
            require_once $helper;
            // In-App-Eintrag (Glocke) IMMER + Push nur bei push_aktiv. Die chat-Pref
            // wurde oben bereits geprueft (Kategorie aus -> gar keine Benachrichtigung).
            if (function_exists('benachrichtigungZustellen')) {
                benachrichtigungZustellen($userId, $titel, $text, $url, 'chat');
            }
        } catch (Throwable $e) {
            error_log('chatSendPushToUser: ' . $e->getMessage());
        }
    }
}
