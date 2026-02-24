<?php
// inc/session_config.inc.php - Zentrale Session-Konfiguration
// Einmal einbinden vor session_start() - idempotent (sicher bei Mehrfach-Include)

if (session_status() !== PHP_SESSION_NONE) return;

// HTTPS-Erkennung
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Session-Sicherheit
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
if ($isHttps) ini_set('session.cookie_secure', 1);

// Cross-Subdomain Session-Sharing: .msvwilen.ch auf Produktion, nichts auf localhost
$host = $_SERVER['HTTP_HOST'] ?? '';
if (preg_match('/\.msvwilen\.ch$/i', $host)) {
    ini_set('session.cookie_domain', '.msvwilen.ch');
    // Alten subdomain-spezifischen PHPSESSID-Cookie löschen (z.B. mitglieder.msvwilen.ch ohne Punkt)
    // Verhindert doppelte PHPSESSID-Cookies nach Migration zu cross-Subdomain-Sessions
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', [
            'expires'  => 1,
            'path'     => '/',
            'domain'   => $host,
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

session_start();

// Helper-Funktion fuer setcookie()-Aufrufe (remember_me etc.)
if (!function_exists('msv_cookie_domain')) {
    function msv_cookie_domain(): string {
        $h = $_SERVER['HTTP_HOST'] ?? '';
        return preg_match('/\.msvwilen\.ch$/i', $h) ? '.msvwilen.ch' : '';
    }
}
