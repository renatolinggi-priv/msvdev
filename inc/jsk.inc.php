<?php
// inc/jsk.inc.php – zentrale Helfer für die Jungschützen-Betreuung (Matching + Chat).
//
// Bewusst OHNE Abhängigkeit zu auth.php (Session), damit die Funktionen auch aus dem
// Cron (CLI) nutzbar sind. Erwartet eine PDO-Verbindung (getDB() aus dbconnect.inc.php).
//
// Ersetzt die früher vierfach duplizierte Betreuer-Abfrage (portal_header, chat.php,
// jsk_betreuung.php, api/jsk_betreuung.php) und die zweifach vorhandene jskSendPush().

if (!function_exists('jskDbHatSpalte')) {
    /**
     * Existiert die Spalte in der Tabelle? Pro Request gecacht.
     * Dient als Rückfallebene, solange eine Migration noch nicht eingespielt ist
     * (Prod ist sofort live, die Migration läuft manuell über admin/aktualisierung.php).
     */
    function jskDbHatSpalte(PDO $db, string $tabelle, string $spalte): bool {
        static $cache = [];
        $k = $tabelle . '.' . $spalte;
        if (array_key_exists($k, $cache)) return $cache[$k];
        try {
            $st = $db->prepare('SELECT 1 FROM information_schema.COLUMNS
                                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
            $st->execute([$tabelle, $spalte]);
            $cache[$k] = (bool) $st->fetchColumn();
        } catch (Throwable $e) {
            $cache[$k] = false;
        }
        return $cache[$k];
    }
}

if (!function_exists('jskSettingWert')) {
    /** Liest einen Wert aus der settings-Tabelle (fehlend/Fehler -> Default). Pro Request gecacht. */
    function jskSettingWert(PDO $db, string $key, string $default = ''): string {
        static $cache = [];
        if (array_key_exists($key, $cache)) return $cache[$key];
        try {
            $st = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
            $st->execute([$key]);
            $v = $st->fetchColumn();
            $cache[$key] = ($v === false || $v === null) ? $default : (string) $v;
        } catch (Throwable $e) {
            $cache[$key] = $default;
        }
        return $cache[$key];
    }
}

if (!function_exists('jskChatLeiterEinsichtAktiv')) {
    /**
     * Darf die Jungschützenleitung Match-Chats (Jungschütze ↔ Betreuer) mitlesen?
     * settings.jsk_chat_leiter_einsicht ('1' = an). Default AUS – der Vorstand entscheidet
     * bewusst (Jugendschutz vs. Privatsphäre). Schalter in der JSK-Verwaltung (nur Admin).
     */
    function jskChatLeiterEinsichtAktiv(PDO $db): bool {
        return jskSettingWert($db, 'jsk_chat_leiter_einsicht', '0') === '1';
    }
}

if (!function_exists('jskIstBetreuer')) {
    /** Hat der Benutzer „Jungschützen betreuen" aktiviert (benachrichtigung_prefs.jsk_betreuung = 1)? */
    function jskIstBetreuer(PDO $db, int $userId): bool {
        if ($userId <= 0) return false;
        try {
            $s = $db->prepare('SELECT jsk_betreuung FROM benachrichtigung_prefs WHERE user_id = ?');
            $s->execute([$userId]);
            return (int) $s->fetchColumn() === 1;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('jskBetreuerUserIds')) {
    /**
     * User-IDs aller aktivierten Betreuer (freigegebene Mitglieder/Vorstand/Admin mit Opt-In).
     * Bewusst OHNE push_aktiv-Filter: die In-App-Glocke bekommt jeder, Push entscheidet
     * benachrichtigungZustellen() selbst.
     */
    function jskBetreuerUserIds(PDO $db, int $ausser = 0): array {
        try {
            $rows = $db->query(
                "SELECT u.id FROM users u
                   JOIN benachrichtigung_prefs p ON p.user_id = u.id
                  WHERE u.status = 'approved'
                    AND u.role IN ('mitglied','vorstand','admin')
                    AND p.jsk_betreuung = 1"
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return [];
        }
        $ids = array_map('intval', $rows);
        return array_values(array_filter($ids, fn($id) => $id !== $ausser));
    }
}

if (!function_exists('jskBenachrichtigen')) {
    /**
     * Best-effort Benachrichtigung (Glocke immer, Push je nach push_aktiv) an EINEN Benutzer.
     * Bricht nie die aufrufende Aktion ab.
     */
    function jskBenachrichtigen(int $userId, string $titel, string $text, string $url,
                                string $kategorie = 'jsk_betreuung', string $tag = ''): void {
        if ($userId <= 0) return;
        try {
            $helper = __DIR__ . '/push_helper.php';
            if (!file_exists($helper)) return;
            require_once $helper;
            if (function_exists('benachrichtigungZustellen')) {
                benachrichtigungZustellen($userId, $titel, $text, $url, $kategorie, $tag);
            }
        } catch (Throwable $e) {
            error_log('jskBenachrichtigen (user ' . $userId . '): ' . $e->getMessage());
        }
    }
}

if (!function_exists('jskBenachrichtigeBetreuer')) {
    /** Benachrichtigt alle aktivierten Betreuer (optional ohne einen bestimmten Benutzer). */
    function jskBenachrichtigeBetreuer(PDO $db, string $titel, string $text,
                                       string $url = 'portal/jsk_betreuung.php', int $ausser = 0, string $tag = ''): int {
        $n = 0;
        foreach (jskBetreuerUserIds($db, $ausser) as $uid) {
            jskBenachrichtigen($uid, $titel, $text, $url, 'jsk_betreuung', $tag);
            $n++;
        }
        return $n;
    }
}

if (!function_exists('jskLeiterUserIds')) {
    /** User-IDs aller freigeschalteten Jungschützenleiter (mitglieder.ist_jsk_leiter + Login). */
    function jskLeiterUserIds(PDO $db): array {
        try {
            $rows = $db->query(
                "SELECT u.id FROM users u JOIN mitglieder m ON m.ID = u.mitglied_id
                  WHERE m.ist_jsk_leiter = 1 AND u.status = 'approved'"
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return [];
        }
        return array_map('intval', $rows);
    }
}

if (!function_exists('jskLeitungOderVorstandUserIds')) {
    /**
     * Empfänger für „Leitung informieren": die Jungschützenleiter; gibt es keinen mit Login,
     * ersatzweise Vorstand + Admin (damit eine Meldung nie ins Leere läuft).
     */
    function jskLeitungOderVorstandUserIds(PDO $db): array {
        $ids = jskLeiterUserIds($db);
        if ($ids) return $ids;
        try {
            $rows = $db->query("SELECT id FROM users WHERE status = 'approved' AND role IN ('vorstand','admin')")
                       ->fetchAll(PDO::FETCH_COLUMN);
            return array_map('intval', $rows);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('jskAnfrageInfo')) {
    /**
     * Stammdaten einer Anfrage inkl. Jungschützen-Name, dessen Login (approved) und Betreuer-Name.
     * Liefert null, wenn die Anfrage nicht existiert.
     */
    function jskAnfrageInfo(PDO $db, int $anfrageId): ?array {
        $st = $db->prepare(
            "SELECT a.id, a.datum, a.zeit, a.status, a.jungschuetze_id, a.betreut_von_user_id,
                    j.Vorname, j.Name,
                    (SELECT u.id FROM users u WHERE u.jungschuetze_id = j.id AND u.status = 'approved' LIMIT 1) AS js_user_id,
                    bu.full_name AS betreuer_name
               FROM jsk_betreuung_anfragen a
               JOIN jungschuetzen j ON j.id = a.jungschuetze_id
               LEFT JOIN users bu ON bu.id = a.betreut_von_user_id
              WHERE a.id = ?"
        );
        $st->execute([$anfrageId]);
        $row = $st->fetch();
        if (!$row) return null;
        $row['js_name']    = trim($row['Vorname'] . ' ' . $row['Name']);
        $row['datum_de']   = date('d.m.Y', strtotime($row['datum']));
        $row['js_user_id'] = (int) ($row['js_user_id'] ?? 0);
        return $row;
    }
}
