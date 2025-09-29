<?php
// test_csrf.php - CSRF Debug Tool

// Session-Konfiguration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ausgabe als JSON
header('Content-Type: application/json');

$debug_info = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'csrf_token_exists' => isset($_SESSION['csrf_token']),
    'csrf_token' => isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : null,
    'session_data' => $_SESSION,
    'cookie_params' => session_get_cookie_params(),
    'php_session_name' => session_name(),
    'cookies' => $_COOKIE,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? ''
];

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>
