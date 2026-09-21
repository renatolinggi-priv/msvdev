<?php
// inc/chat.inc.php – Helfer fuer den 1:1-Chat (Jungschütze ↔ Leiter / Match).
// Erwartet eine PDO-Verbindung via getDB() (inc/dbconnect.inc.php).
// Betreuer-/Leiter-/Benachrichtigungs-Helfer liegen zentral in inc/jsk.inc.php.

require_once __DIR__ . '/jsk.inc.php';

if (!function_exists('isJskLeiter')) {
    /** True, wenn der User-Account zu einem als Jungschützenleiter markierten Mitglied gehört. */
    function isJskLeiter(PDO $db, int $userId): bool {
        static $cache = [];
        if ($userId <= 0) return false;
        if (isset($cache[$userId])) return $cache[$userId];
        $stmt = $db->prepare(
            "SELECT 1 FROM users u JOIN mitglieder m ON m.ID = u.mitglied_id
              WHERE u.id = ? AND m.ist_jsk_leiter = 1 LIMIT 1"
        );
        $stmt->execute([$userId]);
        return $cache[$userId] = (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('chatLeiterAnzahl')) {
    /** Anzahl Jungschützenleiter mit freigeschaltetem Login (0 = Leitungs-Chat läuft ins Leere). */
    function chatLeiterAnzahl(PDO $db): int {
        return count(jskLeiterUserIds($db));
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

if (!function_exists('chatAccessLevel')) {
    /**
     * Zugriffsstufe von $userId auf die Konversation:
     *   'write' = Teilnehmer (Jungschütze, Match-Partner, Leiter im Leiter-Chat)
     *   'read'  = Leitung mit aktivierter Einsicht in einen Match-Chat (nur mitlesen)
     *   ''      = kein Zugriff
     */
    function chatAccessLevel(PDO $db, array $conv, int $userId): string {
        $js      = (int) $conv['js_user_id'];
        $partner = $conv['partner_user_id'] !== null ? (int) $conv['partner_user_id'] : null;
        if ($userId === $js) return 'write';
        if ($conv['typ'] === 'match') {
            if ($partner !== null && $userId === $partner) return 'write';
            if (jskChatLeiterEinsichtAktiv($db) && isJskLeiter($db, $userId)) return 'read';
            return '';
        }
        // typ === 'leiter': der Jungschütze ODER ein designierter Leiter
        return isJskLeiter($db, $userId) ? 'write' : '';
    }
}

if (!function_exists('chatCanAccess')) {
    /** Darf $userId diese Konversation sehen (lesen oder schreiben)? */
    function chatCanAccess(PDO $db, array $conv, int $userId): bool {
        return chatAccessLevel($db, $conv, $userId) !== '';
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

if (!function_exists('chatFindMatchConversation')) {
    /** Bestehenden Match-Chat (js ↔ Mitglied) suchen, ohne zu schreiben. 0 = keiner. */
    function chatFindMatchConversation(PDO $db, int $jsUserId, int $partnerUserId): int {
        if ($jsUserId <= 0 || $partnerUserId <= 0) return 0;
        $stmt = $db->prepare("SELECT id FROM chat_conversations WHERE typ = 'match' AND js_user_id = ? AND partner_user_id = ? LIMIT 1");
        $stmt->execute([$jsUserId, $partnerUserId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('chatEnsureMatchConversation')) {
    /** Findet/erstellt den Match-Chat (js ↔ Mitglied) und gibt die conversation_id zurück. */
    function chatEnsureMatchConversation(PDO $db, int $jsUserId, int $partnerUserId): int {
        if ($jsUserId <= 0 || $partnerUserId <= 0) return 0;
        // Erst suchen (verbrennt keine Auto-Increment-Werte), dann race-sicher anlegen.
        $id = chatFindMatchConversation($db, $jsUserId, $partnerUserId);
        if ($id > 0) return $id;
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
    /**
     * Findet/erstellt den Leiter-Chat eines Jungschützen (partner NULL = „Leitung").
     * Ab Migration 064 faengt der UNIQUE-Key (typ, js_user_id, partner_key) gleichzeitige
     * Anlagen ab; davor deckt der SELECT den Normalfall ab.
     */
    function chatEnsureLeiterConversation(PDO $db, int $jsUserId): int {
        if ($jsUserId <= 0) return 0;
        $stmt = $db->prepare("SELECT id FROM chat_conversations WHERE typ = 'leiter' AND js_user_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$jsUserId]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id > 0) return $id;
        $ins = $db->prepare(
            "INSERT INTO chat_conversations (typ, js_user_id, partner_user_id, erstellt_am)
             VALUES ('leiter', ?, NULL, NOW())
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
        );
        $ins->execute([$jsUserId]);
        return (int) $db->lastInsertId();
    }
}

if (!function_exists('chatNamenSql')) {
    /**
     * SELECT-Fragment für die Anzeigenamen einer Konversation (Alias c):
     *   js_name      = Name aus den Kursdaten (jungschuetzen), ersatzweise users.full_name
     *   partner_name = users.full_name des Partners
     * Der Jungschütze kann seinen Login-Namen frei ändern; im Chat und auf dem Board soll
     * aber der Stammdaten-Name erscheinen (gleicher Name überall).
     */
    function chatNamenSql(): string {
        return "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(jj.Vorname,''), ' ', COALESCE(jj.Name,''))), ''), ju.full_name) AS js_name,
                pu.full_name AS partner_name";
    }
    function chatNamenJoinSql(): string {
        return "LEFT JOIN users ju ON ju.id = c.js_user_id
                LEFT JOIN jungschuetzen jj ON jj.id = ju.jungschuetze_id
                LEFT JOIN users pu ON pu.id = c.partner_user_id";
    }
}

if (!function_exists('chatMarkRead')) {
    /** Alles in der Konversation bis zur aktuell letzten Nachricht als gelesen markieren. */
    function chatMarkRead(PDO $db, int $convId, int $userId): void {
        $st = $db->prepare("SELECT COALESCE(MAX(id),0) FROM chat_nachrichten WHERE conversation_id = ?");
        $st->execute([$convId]);
        $top = (int) $st->fetchColumn();
        if ($top <= 0) return;
        $db->prepare(
            "INSERT INTO chat_gelesen (conversation_id, user_id, last_read_nachricht_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE last_read_nachricht_id = GREATEST(last_read_nachricht_id, VALUES(last_read_nachricht_id))"
        )->execute([$convId, $userId, $top]);
    }
}

if (!function_exists('chatUnreadCount')) {
    /** Gesamtzahl ungelesener Nachrichten für $userId über alle Konversationen, an denen er teilnimmt. */
    function chatUnreadCount(PDO $db, int $userId): int {
        $isLeiter = isJskLeiter($db, $userId) ? 1 : 0;
        $geloescht = jskDbHatSpalte($db, 'chat_nachrichten', 'geloescht_am') ? ' AND n.geloescht_am IS NULL' : '';
        $stmt = $db->prepare(
            "SELECT COUNT(*)
               FROM chat_nachrichten n
               JOIN chat_conversations c ON c.id = n.conversation_id
               LEFT JOIN chat_gelesen g ON g.conversation_id = c.id AND g.user_id = :me
              WHERE n.sender_user_id <> :me2
                AND n.id > COALESCE(g.last_read_nachricht_id, 0)$geloescht
                AND ( c.js_user_id = :me3 OR c.partner_user_id = :me4 OR (:isleiter = 1 AND c.typ = 'leiter') )"
        );
        $stmt->execute([
            ':me' => $userId, ':me2' => $userId, ':me3' => $userId, ':me4' => $userId, ':isleiter' => $isLeiter,
        ]);
        return (int) $stmt->fetchColumn();
    }
}

// ---------------------------------------------------------------------------
// Bilder im Chat (Migration 065). Ablage: portal/uploads/chat/<conversation_id>/<name>.jpg
// + <name>_t.jpg (Thumbnail). Verarbeitung ueber Intervention Image wie in der Foto-Galerie
// (Auto-Orientierung, Neukodierung als JPG -> EXIF/GPS werden entfernt).
// ---------------------------------------------------------------------------
if (!defined('CHAT_BILD_MAX'))       define('CHAT_BILD_MAX', 1600);            // laengste Kante Vollbild
if (!defined('CHAT_BILD_THUMB'))     define('CHAT_BILD_THUMB', 640);           // laengste Kante Vorschau in der Blase
if (!defined('CHAT_BILD_MAX_BYTES')) define('CHAT_BILD_MAX_BYTES', 15 * 1024 * 1024);

if (!function_exists('chatBilderAktiv')) {
    /** Bild-Spalten vorhanden (Migration 065 eingespielt)? */
    function chatBilderAktiv(PDO $db): bool {
        return jskDbHatSpalte($db, 'chat_nachrichten', 'bild');
    }

    function chatBildBasisDir(): string {
        return __DIR__ . '/../portal/uploads/chat/';
    }

    function chatBildDir(int $convId, bool $anlegen = true): string {
        $dir = chatBildBasisDir() . $convId . '/';
        if ($anlegen && !is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }

    /** Pfad zu Vollbild oder Thumbnail einer Bildnachricht. */
    function chatBildPfad(int $convId, string $datei, bool $thumb): string {
        $datei = basename($datei);
        if ($thumb) $datei = preg_replace('/\.jpg$/i', '_t.jpg', $datei);
        return chatBildDir($convId, false) . $datei;
    }

    /** Schutz gegen Pfad-Ausbruch: Datei muss unter portal/uploads/chat liegen. */
    function chatBildPfadErlaubt(string $pfad): bool {
        $real = realpath($pfad);
        $base = realpath(chatBildBasisDir());
        return $real !== false && $base !== false && strpos($real, $base) === 0;
    }

    /**
     * Verarbeitet ein hochgeladenes Bild und legt Vollbild + Thumbnail ab.
     * @return array{datei:string,breite:int,hoehe:int}
     * @throws RuntimeException mit benutzerlesbarer Meldung
     */
    function chatBildSpeichern(string $tmpPath, int $convId): array {
        require_once __DIR__ . '/fotogalerie.inc.php';   // fotoImageManager(), fotoErlaubteMimes()
        if (!is_file($tmpPath)) throw new RuntimeException('Keine Datei empfangen.');
        if (filesize($tmpPath) > CHAT_BILD_MAX_BYTES) throw new RuntimeException('Das Bild ist zu gross (max. 15 MB).');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = (string) finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        $erlaubt = fotoErlaubteMimes();
        if (!isset($erlaubt[$mime])) {
            throw new RuntimeException('Nur Bilder (JPG, PNG, WebP' . (fotoHeicUnterstuetzt() ? ', HEIC' : '') . ') sind erlaubt.');
        }
        $name  = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.jpg';
        $full  = chatBildDir($convId) . $name;
        $thumb = chatBildPfad($convId, $name, true);
        try {
            $img = fotoImageManager()->read($tmpPath);
            $img->orient();
            $img->scaleDown(CHAT_BILD_MAX, CHAT_BILD_MAX);
            $img->save($full, quality: 82);
            $w = $img->width(); $h = $img->height();
            $img->scaleDown(CHAT_BILD_THUMB, CHAT_BILD_THUMB);
            $img->save($thumb, quality: 78);
        } catch (Throwable $e) {
            foreach ([$full, $thumb] as $p) { if (is_file($p)) @unlink($p); }
            error_log('[chat] Bildverarbeitung fehlgeschlagen: ' . $e->getMessage());
            throw new RuntimeException('Bild konnte nicht verarbeitet werden.');
        }
        return ['datei' => $name, 'breite' => (int) $w, 'hoehe' => (int) $h];
    }

    function chatBildLoeschen(int $convId, ?string $datei): void {
        if (!$datei) return;
        foreach ([chatBildPfad($convId, $datei, false), chatBildPfad($convId, $datei, true)] as $p) {
            if (chatBildPfadErlaubt($p) && is_file($p)) @unlink($p);
        }
    }
}

if (!function_exists('chatSendPushToUser')) {
    /** Best-effort Push (bricht nie die Aktion ab). Respektiert benachrichtigung_prefs.chat. */
    function chatSendPushToUser(PDO $db, int $userId, string $titel, string $text, string $url): void {
        if ($userId <= 0) return;
        try {
            // Opt-In prüfen: chat-Toggle (Default 1). Kategorie aus -> gar keine Benachrichtigung.
            // push_aktiv entscheidet benachrichtigungZustellen() selbst (Glocke bleibt).
            $st = $db->prepare("SELECT COALESCE(chat,1) AS chat FROM benachrichtigung_prefs WHERE user_id = ?");
            $st->execute([$userId]);
            $p = $st->fetch();
            if ($p && (int) $p['chat'] !== 1) return;
            jskBenachrichtigen($userId, $titel, $text, $url, 'chat', 'chat-' . md5($url));
        } catch (Throwable $e) {
            error_log('chatSendPushToUser: ' . $e->getMessage());
        }
    }
}
