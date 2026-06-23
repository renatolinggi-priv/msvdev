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
    // Alte host-spezifische PHPSESSID-Cookies löschen (doppelte Cookies verhindern)
    // session_start() ohne session_config erzeugt host-only Cookies (ohne Domain-Attribut).
    // Diese müssen OHNE Domain gelöscht werden (RFC 6265: host-only ≠ domain cookie).
    if (isset($_COOKIE[session_name()])) {
        // 1) Host-only Cookie löschen (kein Domain-Attribut → matcht host-only Cookies)
        setcookie(session_name(), '', [
            'expires'  => 1,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // 2) Domain-Cookie für exakten Host löschen (falls mit domain= gesetzt)
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
