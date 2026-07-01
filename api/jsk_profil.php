<?php
// api/jsk_profil.php – Jungschütze pflegt eigenen Anzeigenamen + Passwort.
// JSON, CSRF, Rolle jungschuetze (admin zum Testen).

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['jungschuetze', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];
$csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validateCsrf($csrf)) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);
}

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$action = $input['action'] ?? '';

if ($action === 'save_name') {
    $name = trim((string) ($input['full_name'] ?? ''));
    if ($name === '' || mb_strlen($name) < 2) {
        json_error('Bitte einen gültigen Namen angeben (min. 2 Zeichen).');
    }
    if (mb_strlen($name) > 100) $name = mb_substr($name, 0, 100);
    $db->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$name, $userId]);
    $_SESSION['user_name'] = $name;
    echo json_encode(['success' => true, 'message' => 'Name gespeichert.', 'full_name' => $name]);
    exit;
}

if ($action === 'change_password') {
    $current = (string) ($input['current'] ?? '');
    $neu     = (string) ($input['neu'] ?? '');
    if (strlen($neu) < 8) {
        json_error('Das neue Passwort muss mindestens 8 Zeichen lang sein.');
    }
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $hash = (string) ($stmt->fetchColumn() ?: '');
    // bcrypt oder Legacy-MD5 unterstützen
    $ok = (strlen($hash) === 32) ? (md5($current) === $hash) : password_verify($current, $hash);
    if (!$ok) {
        json_error('Das aktuelle Passwort ist nicht korrekt.');
    }
    $newHash = password_hash($neu, PASSWORD_DEFAULT);
    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $userId]);
    echo json_encode(['success' => true, 'message' => 'Passwort geändert.']);
    exit;
}

json_error('Unbekannte Aktion.');
