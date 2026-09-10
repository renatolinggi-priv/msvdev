<?php
/**
 * Zugriffsschutz für Admin-AJAX-Endpunkte (inc/<modul>/*.php).
 *
 * Bisher prüften diese Endpunkte nur das CSRF-Token bei POST – lesende Aufrufe
 * (Mitgliederlisten, Resultate, Jahresübersichten) waren ohne Login erreichbar,
 * weil nur header.inc.php (Seiten) den Login erzwingt, nicht die Endpunkte.
 *
 * Nutzung ganz oben im Endpunkt, direkt nach dem DB-Include:
 *   require_once __DIR__ . '/../admin_api_guard.inc.php';
 *   adminApiGuard();          // JSON-Endpunkt  -> 401/403 als {"success":false,"message":...}
 *   adminApiGuard('html');    // Endpunkt liefert HTML-Fragment (Tabellenzeilen) -> Fehlerzeile
 *   adminApiGuard('plain');   // Datei-Download o.ä. -> 403 Klartext
 *
 * Erlaubte Rollen = Admin-Bereich (admin, vorstand). Das entspricht der Weiche in
 * header.inc.php, die die Rolle 'mitglied' ins Portal umleitet. Session-Handling und
 * Remember-Me-Wiederherstellung kommen aus auth.php (nutzt session_config.inc.php,
 * also NIE session_start() direkt).
 */
require_once __DIR__ . '/../auth.php';

const ADMIN_API_ROLES = ['admin', 'vorstand'];

if (!function_exists('adminApiGuard')) {
    function adminApiGuard(string $mode = 'json'): void
    {
        // iOS PWA: Session ggf. aus Remember-Cookie wiederherstellen
        if (!isset($_SESSION['user_id']) && function_exists('restoreSessionFromToken')) {
            restoreSessionFromToken();
        }

        $eingeloggt  = isset($_SESSION['user_id']);
        $freigegeben = empty($_SESSION['user_status']) || $_SESSION['user_status'] === 'approved';
        $rolleOk     = in_array($_SESSION['user_role'] ?? '', ADMIN_API_ROLES, true);

        if ($eingeloggt && $freigegeben && $rolleOk) {
            // AJAX-Aufrufe halten den serverseitigen Inaktivitäts-Timeout wach
            $_SESSION['last_activity'] = time();
            return;
        }

        $code = $eingeloggt ? 403 : 401;
        $msg  = $eingeloggt ? 'Zugriff verweigert' : 'Nicht angemeldet';
        http_response_code($code);

        if ($mode === 'html') {
            header('Content-Type: text/html; charset=utf-8');
            echo "<tr><td colspan='20' class='text-center text-danger py-3'>"
               . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
               . " – bitte neu anmelden.</td></tr>";
        } elseif ($mode === 'plain') {
            header('Content-Type: text/plain; charset=utf-8');
            echo $msg;
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $msg]);
        }
        exit;
    }
}
