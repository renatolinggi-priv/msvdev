<?php
// api/einsatz_feed_token.php — Kalender-Token für persönliches Einsatz-Abo generieren
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

// CSRF vor requireLogin prüfen (Remember-Me kann session_regenerate_id auslösen)
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sitzung abgelaufen. Bitte Seite neu laden.', 'csrf_expired' => true]);
    exit;
}

requireLogin();

$action = $_POST['action'] ?? '';

if ($action !== 'generate') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}

$token = bin2hex(random_bytes(32)); // 64 Hex-Zeichen

$db = getDB();
$stmt = $db->prepare("UPDATE users SET calendar_token = ? WHERE id = ?");
$stmt->execute([$token, $user_id]);

if ($stmt->rowCount() === 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Token konnte nicht gespeichert werden']);
    exit;
}

// URL zusammenbauen — funktioniert auf allen Subdomains
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
// Pfad: eine Ebene höher als /api/
$base   = $scheme . '://' . $host;
$url    = $base . '/einsatz_feed.php?token=' . $token;

echo json_encode(['success' => true, 'token' => $token, 'url' => $url]);
?>
