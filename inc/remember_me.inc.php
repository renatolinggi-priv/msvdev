<?php
// remember_me.inc.php - Persistente Login-Tokens für iOS PWA Session-Persistenz
// Setzt einen httpOnly Cookie mit 30-Tagen-Ablauf und speichert den Hash in der DB.
// Benötigt: getDB() aus inc/dbconnect.inc.php

define('REMEMBER_COOKIE_NAME', 'msv_remember');
define('REMEMBER_TOKEN_LIFETIME', 30 * 24 * 60 * 60); // 30 Tage in Sekunden

/**
 * Erkennt ob die aktuelle Verbindung HTTPS ist
 */
function _remember_is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

/**
 * Setzt einen neuen Remember-Token für den User.
 * Generiert 32 zufällige Bytes, speichert den SHA-256-Hash in der DB
 * und setzt den Rohwert als httpOnly Cookie.
 *
 * @param int $user_id
 */
function setRememberToken($user_id) {
    $token      = bin2hex(random_bytes(32)); // 64-stelliger Hex-String
    $token_hash = hash('sha256', $token);
    $expires_at = date('Y-m-d H:i:s', time() + REMEMBER_TOKEN_LIFETIME);

    try {
        $db = getDB();
        // Abgelaufene Tokens dieses Users bereinigen
        $db->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND expires_at < NOW()")
           ->execute([$user_id]);
        // Neuen Token speichern
        $db->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
           ->execute([$user_id, $token_hash, $expires_at]);
    } catch (PDOException $e) {
        error_log("setRememberToken error: " . $e->getMessage());
        return; // Kein Cookie setzen wenn DB-Fehler
    }

    $cookieDomain = function_exists('msv_cookie_domain') ? msv_cookie_domain() : '';
    setcookie(REMEMBER_COOKIE_NAME, $token, [
        'expires'  => time() + REMEMBER_TOKEN_LIFETIME,
        'path'     => '/',
        'domain'   => $cookieDomain,
        'secure'   => _remember_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Prüft den Remember-Cookie und gibt User-Daten zurück.
 * Gibt false zurück wenn kein gültiger Token vorhanden.
 *
 * @return array|bool User-Daten-Array oder false
 */
function validateRememberToken() {
    $token = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
    if (empty($token)) {
        error_log('[remember_me] validateRememberToken: cookie "' . REMEMBER_COOKIE_NAME . '" not found. All cookies: ' . implode(', ', array_keys($_COOKIE)));
        return false;
    }

    $token_hash = hash('sha256', $token);
    error_log('[remember_me] validateRememberToken: cookie found, hash=' . substr($token_hash, 0, 16) . '...');

    try {
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT rt.user_id, u.username, u.full_name, u.role, u.status, u.mitglied_id
            FROM remember_tokens rt
            JOIN users u ON rt.user_id = u.id
            WHERE rt.token_hash = ? AND rt.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token_hash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log('[remember_me] validateRememberToken: no matching token in DB (hash=' . $token_hash . ')');
            _clearRememberCookie();
            return false;
        }

        // Nur freigeschaltete User dürfen via Token einloggen
        $allowed_statuses = ['approved', null, ''];
        if (!in_array($user['status'], $allowed_statuses, true)) {
            error_log('[remember_me] validateRememberToken: user status "' . $user['status'] . '" not allowed');
            clearRememberToken();
            return false;
        }

        error_log('[remember_me] validateRememberToken: success for user_id=' . $user['user_id']);
        return $user;
    } catch (PDOException $e) {
        error_log('[remember_me] validateRememberToken error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Stellt eine PHP-Session aus dem Remember-Cookie wieder her.
 * Setzt alle nötigen Session-Variablen (user_id, username, etc.).
 * Gibt true zurück wenn erfolgreich, sonst false.
 *
 * @return bool
 */
function restoreSessionFromToken() {
    if (!function_exists('getDB')) return false;
    $user = validateRememberToken();
    if (!$user) return false;

    // Session mit User-Daten befüllen (analog zu setLoginSession() in login.php)
    $_SESSION['user_id']     = (int)$user['user_id'];
    $_SESSION['username']    = $user['username'];
    $_SESSION['user_name']   = $user['full_name'];
    $_SESSION['user_role']   = $user['role'] ?? 'mitglied';
    $_SESSION['user_status'] = $user['status'] ?: 'approved'; // '' und NULL → 'approved' (Legacy-Admins)
    $_SESSION['mitglied_id'] = $user['mitglied_id'];
    $_SESSION['last_activity'] = time();
    // 'regenerated' bewusst NICHT setzen → header.inc.php löst session_regenerate_id() aus

    // Cookie erneuern (Sliding Expiry: 30 Tage ab jetzt).
    // portal_header.php sichert den neuen Cookie-Wert dann in localStorage.
    setRememberToken((int)$user['user_id']);

    return true;
}

/**
 * Löscht den Remember-Token: Cookie + DB-Eintrag.
 */
function clearRememberToken() {
    $token = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';

    if (!empty($token)) {
        $token_hash = hash('sha256', $token);
        try {
            $db = getDB();
            $db->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")
               ->execute([$token_hash]);
        } catch (PDOException $e) {
            error_log("clearRememberToken error: " . $e->getMessage());
        }
    }

    _clearRememberCookie();
}

/**
 * Löscht nur den Cookie (ohne DB-Zugriff).
 */
function _clearRememberCookie() {
    $cookieDomain = function_exists('msv_cookie_domain') ? msv_cookie_domain() : '';
    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'domain'   => $cookieDomain,
        'secure'   => _remember_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
