<?php
/**
 * pages/drucksteuerung/sign_api.php — QZ Tray Request-Signierung
 *
 * POST: { "request": "..." }
 * Response: { "success": true, "signature": "base64..." }
 *
 * Signiert den QZ Tray Request mit dem privaten Schluessel,
 * damit QZ Tray ohne Bestaetigungs-Popup druckt (Silent Printing).
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

// Privaten Schluessel suchen
$projectRoot = realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2);
$candidates = [
    '/home/linggire/www/sksg_ews-ca-key/private-key.pem',
    $projectRoot . '/../sksg_ews-ca-key/private-key.pem',
    $projectRoot . '/../msvjm-ca-key/private-key.pem',
    $projectRoot . '/certs/private-key.pem',
];
error_log('[sign] projectRoot=' . $projectRoot . ' | Pruefe: ' . implode(', ', $candidates));

$keyPath = null;
foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        $keyPath = $candidate;
        break;
    }
}

if (!$keyPath) {
    error_log('[sign] Privater Schluessel nicht gefunden. Geprueft: ' . implode(', ', $candidates));
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
