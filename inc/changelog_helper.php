<?php
/**
 * changelog_helper.php — Zentraler Helper für Changelog-Einträge
 *
 * Usage:
 *   require_once __DIR__ . '/changelog_helper.php';
 *   logChangelog('resultate', 'aktualisiert', 'Kantiresultate 2026 gespeichert', [
 *       'tabelle' => 'kantiresultate',
 *       'jahr' => 2026,
 *   ]);
 */

// Mapping: Kategorie → WordPress-Seitenslug
const CHANGELOG_WP_SLUGS = [
    'definition'    => 'home',
    'standbelegung' => 'standbelegung',
    'termine'       => 'wichtige-termine-3',
];

// Mapping: Resultate-Tabelle → WordPress-Seitenslug
const CHANGELOG_RESULTATE_SLUGS = [
    'jmresultate'    => 'jahresmeisterschaft-2',
    'heimresultate'  => 'heimmeisterschaft-2',
    'kantiresultate' => 'kantonalstich-2',
];

/**
 * Schreibt einen Changelog-Eintrag in die Datenbank.
 *
 * @param string $kategorie  'resultate' | 'termine' | 'definition' | 'standbelegung'
 * @param string $aktion     'erstellt' | 'aktualisiert' | 'geloescht'
 * @param string $beschreibung  Menschenlesbare Beschreibung
 * @param array  $opts       Optionale Felder:
 *                           'tabelle'      => string   DB-Tabellenname
 *                           'jahr'         => int      Betroffenes Jahr
 *                           'details'      => array    Wird als JSON gespeichert
 *                           'sichtbar'     => int      0 oder 1 (default 1)
 *                           'user_id'      => int      Override (default $_SESSION)
 *                           'wp_slug'      => string   WP-Seitenslug (auto-ermittelt falls nicht gesetzt)
 * @return int|false  Insert-ID bei Erfolg, false bei Fehler
 */
function logChangelog(string $kategorie, string $aktion, string $beschreibung, array $opts = []) {
    try {
        if (!function_exists('getDB')) {
            require_once __DIR__ . '/dbconnect.inc.php';
        }
        $db = getDB();

        $tabelle  = $opts['tabelle'] ?? null;
        $jahr     = $opts['jahr'] ?? null;
        $details  = isset($opts['details']) ? json_encode($opts['details'], JSON_UNESCAPED_UNICODE) : null;
        $sichtbar = $opts['sichtbar'] ?? 1;
        $userId   = $opts['user_id'] ?? ($_SESSION['user_id'] ?? null);

        // wp_slug: explizit gesetzt > auto-ermittelt aus Kategorie/Tabelle
        if (array_key_exists('wp_slug', $opts)) {
            $wpSlug = $opts['wp_slug'];
        } elseif ($kategorie === 'resultate' && $tabelle) {
            $wpSlug = CHANGELOG_RESULTATE_SLUGS[$tabelle] ?? null;
        } else {
            $wpSlug = CHANGELOG_WP_SLUGS[$kategorie] ?? null;
        }

        $stmt = $db->prepare("
            INSERT INTO changelog (kategorie, aktion, beschreibung, tabelle, jahr, details, sichtbar, user_id, wp_slug)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $kategorie,
            $aktion,
            $beschreibung,
            $tabelle,
            $jahr,
            $details,
            $sichtbar,
            $userId,
            $wpSlug
        ]);

        return (int)$db->lastInsertId();
    } catch (\Throwable $e) {
        // Changelog-Fehler duerfen NIE die eigentliche Operation blockieren
        error_log('[changelog_helper] Fehler: ' . $e->getMessage());
        return false;
    }
}

/**
 * Veröffentlicht alle unveröffentlichten JM-Resultate-Einträge eines Jahres.
 *
 * @param int $jahr
 * @return int Anzahl veröffentlichter Einträge
 */
function publishJmChangelog(int $jahr): int {
    try {
        if (!function_exists('getDB')) {
            require_once __DIR__ . '/dbconnect.inc.php';
        }
        $db = getDB();

        $stmt = $db->prepare("
            UPDATE changelog
            SET sichtbar = 1
            WHERE kategorie = 'resultate'
              AND sichtbar = 0
              AND jahr = ?
              AND tabelle = 'jmresultate'
        ");
        $stmt->execute([$jahr]);
        return $stmt->rowCount();
    } catch (\Throwable $e) {
        error_log('[changelog_helper] publishJmChangelog Fehler: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Zählt unveröffentlichte JM-Resultate-Einträge für ein Jahr.
 *
 * @param int $jahr
 * @return int
 */
function countUnpublishedJmChangelog(int $jahr): int {
    try {
        if (!function_exists('getDB')) {
            require_once __DIR__ . '/dbconnect.inc.php';
        }
        $db = getDB();

        $stmt = $db->prepare("
            SELECT COUNT(*) FROM changelog
            WHERE kategorie = 'resultate'
              AND sichtbar = 0
              AND jahr = ?
              AND tabelle = 'jmresultate'
        ");
        $stmt->execute([$jahr]);
        return (int)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        error_log('[changelog_helper] countUnpublishedJmChangelog Fehler: ' . $e->getMessage());
        return 0;
    }
}
