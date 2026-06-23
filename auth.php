<?php
// auth.php - Authentifizierungs- und Autorisierungsfunktionen fuer das Mitgliederportal
// Wird in geschuetzte Seiten eingebunden (zusaetzlich zum bestehenden Session-Handling)

// Zentrale Session-Konfiguration (inkl. Cross-Subdomain Cookie-Domain)
require_once __DIR__ . '/inc/session_config.inc.php';

// Remember-Me-Funktionen laden (für iOS PWA Session-Persistenz)
if (!function_exists('restoreSessionFromToken')) {
    $__remember_path = __DIR__ . '/inc/remember_me.inc.php';
    if (file_exists($__remember_path)) require_once $__remember_path;
    unset($__remember_path);
}

/**
 * Prueft ob User eingeloggt ist, leitet sonst zu login.php.
 * Versucht zuerst die Session aus dem Remember-Cookie wiederherzustellen (iOS PWA).
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        // iOS PWA: Session aus persistentem Cookie wiederherstellen
        if (function_exists('restoreSessionFromToken') && restoreSessionFromToken()) {
            session_regenerate_id(true);
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }
    }
    if (!isset($_SESSION['user_id'])) {
        $page = basename($_SERVER['PHP_SELF']);
        // Portal-Seiten liegen in /portal/ → relativer Redirect ohne portal/-Prefix
        if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/portal/') !== false
            && file_exists(__DIR__ . '/portal/check_session.php')) {
            header('Location: check_session.php?goto=' . urlencode($page));
        } else {
            $login = file_exists(__DIR__ . '/login.php') ? 'login.php' : '../login.php';
            header('Location: ' . $login);
        }
        exit;
    }
    // Pruefen ob User approved ist
    if (!empty($_SESSION['user_status']) && $_SESSION['user_status'] != 'approved') {
        session_destroy();
        $login = file_exists(__DIR__ . '/login.php') ? 'login.php' : '../login.php';
        header('Location: ' . $login . '?error=not_approved');
        exit;
    }
}

/**
 * Prueft ob User eine der geforderten Rollen hat
 * @param string|array $roles z.B. 'admin' oder ['admin','vorstand']
 */
function requireRole($roles) {
    requireLogin();
    $roles = (array) $roles;
    if (!in_array($_SESSION['user_role'] ?? '', $roles)) {
        http_response_code(403);
        die('Zugriff verweigert');
    }
}

/**
 * Prueft ob der aktuelle User eine bestimmte Berechtigung hat
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == $role;
}

function isAdmin() { return hasRole('admin'); }
function isVorstand() { return hasRole('vorstand') || isAdmin(); }
function isMitglied() { return isset($_SESSION['user_id']); }
function isLoggedIn() { return isset($_SESSION['user_id']); }

/**
 * Gibt die Mitglieder-ID des eingeloggten Users zurueck
 */
function getMitgliedId() {
    return $_SESSION['mitglied_id'] ?? null;
}

/**
 * CSRF-Token generieren falls noetig
 */
function ensureCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF-Token validieren
 */
function validateCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * CSRF-Token aus der Anfrage lesen (POST-Feld 'csrf_token' oder Header 'X-CSRF-TOKEN')
 * und validieren. Deckt klassische Formular-Posts und fetch()/AJAX mit JSON-Body ab.
 */
function validateCsrfRequest() {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return validateCsrf($token);
}

/**
 * Wie requireRole(), antwortet bei Fehlschlag aber mit JSON statt HTML/Redirect.
 * Für AJAX-/JSON-Endpoints gedacht.
 * @param string|array $roles
 */
function requireRoleJson($roles) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    // iOS PWA: Session ggf. aus Remember-Cookie wiederherstellen
    if (!isset($_SESSION['user_id']) && function_exists('restoreSessionFromToken') && restoreSessionFromToken()) {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
        exit;
    }
    if (!empty($_SESSION['user_status']) && $_SESSION['user_status'] != 'approved') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Konto nicht freigegeben']);
        exit;
    }
    if (!in_array($_SESSION['user_role'] ?? '', (array) $roles)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Zugriff verweigert']);
        exit;
    }
}

/**
 * Einheitliche JSON-Fehlerantwort für AJAX-Endpoints + sofortiger Abbruch.
 * Zentralisiert die zuvor pro Datei duplizierte json_error()-Definition.
 */
if (!function_exists('json_error')) {
    function json_error($msg, $code = 400) {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
}
