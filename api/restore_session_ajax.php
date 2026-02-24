<?php
// api/restore_session_ajax.php - Session-Wiederherstellung via localStorage-Token (iOS PWA)
header('Content-Type: application/json');

// Zentrale Session-Konfiguration (inkl. Cross-Subdomain Cookie-Domain)
require_once __DIR__ . '/../inc/session_config.inc.php';

// Bereits eingeloggt?
if (isset($_SESSION['user_id'])) {
    echo json_encode(['success' => true]);
    exit;
}

// Nur POST erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'reason' => 'method_not_allowed']);
    exit;
}

require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../inc/remember_me.inc.php';

// Token aus JSON-Body lesen
$body  = json_decode(file_get_contents('php://input'), true);
$token = trim($body['token'] ?? '');

if (empty($token)) {
    echo json_encode(['success' => false, 'reason' => 'no_token']);
    exit;
}

$token_hash = hash('sha256', $token);

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
        echo json_encode(['success' => false, 'reason' => 'invalid_token']);
        exit;
    }

    $allowed = ['approved', null, ''];
    if (!in_array($user['status'], $allowed, true)) {
        echo json_encode(['success' => false, 'reason' => 'not_approved']);
        exit;
    }

    // Session aufbauen
    session_regenerate_id(true);
    $_SESSION['user_id']       = (int)$user['user_id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['user_name']     = $user['full_name'];
    $_SESSION['user_role']     = $user['role'] ?? 'mitglied';
    $_SESSION['user_status']   = $user['status'] ?: 'approved'; // '' und NULL → 'approved' (Legacy-Admins)
    $_SESSION['mitglied_id']   = $user['mitglied_id'];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));

    // Neuen msv_remember Cookie setzen (Sliding Expiry: 30 Tage ab jetzt).
    // portal_header.php wird beim nächsten Load den Cookie in localStorage sichern.
    setRememberToken((int)$user['user_id']);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('[restore_session_ajax] error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'reason' => 'error']);
}
