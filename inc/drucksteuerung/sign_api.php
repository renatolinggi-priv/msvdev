<?php
/**
 * inc/drucksteuerung/sign_api.php — QZ-Tray-Zertifikat und Request-Signierung
 *
 * GET
 *   200 text/plain   oeffentliches Zertifikat (digital-certificate.txt)
 *   204 (leer)       kein Zertifikat hinterlegt -> QZ Tray laeuft unsigniert («Allow»-Dialog)
 * POST { "request": "..." }
 *   200 { "success": true, "signature": "base64..." }
 *
 * Signiert den QZ Tray Request mit dem privaten Schluessel,
 * damit QZ Tray ohne Bestaetigungs-Popup druckt (Silent Printing).
 *
 * Beide Dateien liegen im Ordner qz_certs/ neben msvjm_config.php, eine Stufe ueber dem
 * Docroot: Prod /home/bdebbd4/www/qz_certs/, lokal C:\TEMP\msvjm\qz_certs\. Nichts davon
 * liegt im Web-Root. Gleiche Regel wie EWS/JSK/SFARL (dort /home/linggire/www/qz_certs/),
 * aber eigene Kopie, weil msvdev auf einem anderen Server und Konto laeuft.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../session_config.inc.php';
require_once __DIR__ . '/../../auth.php';

// Session aus Remember-Me wiederherstellen falls noetig
if (!isset($_SESSION['user_id']) && function_exists('restoreSessionFromToken')) {
    restoreSessionFromToken();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}

// Ordner qz_certs/ neben msvjm_config.php
$projectRoot = realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2);
$qzDir = dirname($projectRoot) . '/qz_certs';

// GET: oeffentliches Zertifikat fuer qz.security.setCertificatePromise()
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $certPath = $qzDir . '/digital-certificate.txt';
    if (!is_file($certPath) || !is_readable($certPath)) {
        http_response_code(204);
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    readfile($certPath);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$toSign = $input['request'] ?? '';

if (empty($toSign)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Kein Request zum Signieren']);
    exit;
}

$keyPath = $qzDir . '/private-key.pem';
if (!is_file($keyPath) || !is_readable($keyPath)) {
    error_log('[sign] Privater Schluessel fehlt oder ist nicht lesbar: ' . $keyPath);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Zertifikat nicht konfiguriert']);
    exit;
}

$privateKey = openssl_pkey_get_private('file://' . $keyPath);
if (!$privateKey) {
    error_log('[sign] Schluessel konnte nicht geladen werden: ' . openssl_error_string());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Schluessel-Fehler']);
    exit;
}

// Signieren (SHA-512 mit RSA, wie von QZ Tray erwartet)
$signature = '';
$result = openssl_sign($toSign, $signature, $privateKey, OPENSSL_ALGO_SHA512);

if (!$result) {
    error_log('[sign] Signierung fehlgeschlagen: ' . openssl_error_string());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Signierung fehlgeschlagen']);
    exit;
}

echo json_encode([
    'success'   => true,
    'signature' => base64_encode($signature),
]);
