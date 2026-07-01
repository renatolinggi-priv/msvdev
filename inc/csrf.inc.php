<?php
/**
 * Zentrale CSRF-Absicherung für Admin-Endpoints.
 *
 * Vereinheitlicht die bisher verstreuten Prüfungen (inline hash_equals,
 * `!==`-Vergleiche, auth.php::validateCsrf, wanderpreise_check_csrf).
 *
 * Nutzung im Endpoint (ganz oben, nach den include-Zeilen):
 *   require_once __DIR__ . '/../csrf.inc.php';   // Pfad je nach Ordnertiefe anpassen
 *   csrf_require();          // HTML/Formular-POST  -> 403 + Klartext bei Fehler
 *   csrf_require(true);      // JSON/AJAX-Endpoint  -> 403 + {"success":false,...}
 *
 * Token im Client mitschicken: POST-Feld `csrf_token` ODER Header `X-CSRF-TOKEN`.
 * Token im View holen: <?= csrf_token() ?>  bzw. $_SESSION['csrf_token'].
 *
 * Stellt sicher, dass eine Session läuft (via session_config.inc.php) und ein
 * Token existiert.
 */

require_once __DIR__ . '/session_config.inc.php';

if (!function_exists('csrf_token')) {
    /** Aktuelles CSRF-Token holen (erzeugt eins, falls noch keins existiert). */
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * Prüft das mitgeschickte Token gegen die Session.
     * Quelle: POST-Feld `csrf_token` oder Header `X-CSRF-TOKEN`. Timing-sicher.
     */
    function csrf_verify(): bool {
        $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return !empty($_SESSION['csrf_token'])
            && is_string($sent) && $sent !== ''
            && hash_equals($_SESSION['csrf_token'], $sent);
    }
}

if (!function_exists('csrf_require')) {
    /**
     * Bricht mit HTTP 403 ab, wenn das CSRF-Token ungültig ist.
     * @param bool $json true → JSON-Fehlerantwort (für AJAX/API), sonst Klartext.
     */
    function csrf_require(bool $json = false): void {
        if (csrf_verify()) {
            return;
        }
        http_response_code(403);
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']);
        } else {
            echo 'CSRF-Validierung fehlgeschlagen';
        }
        exit;
    }
}
