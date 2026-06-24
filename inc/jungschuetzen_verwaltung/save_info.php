<?php
// inc/jungschuetzen_verwaltung/save_info.php
// Speichert Titel + Text des Info-/Willkommensblocks fuers JSK-Dashboard (settings).
// PDO, CSRF, Vorstand/Admin.

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);
}

$titel = trim($_POST['info_titel'] ?? '');
$text  = trim($_POST['info_text'] ?? '');
if (mb_strlen($titel) > 120) $titel = mb_substr($titel, 0, 120);
if (mb_strlen($text) > 4000) $text = mb_substr($text, 0, 4000);

try {
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute(['jsk_info_titel', $titel]);
    $stmt->execute(['jsk_info_text', $text]);
    echo json_encode(['success' => true, 'message' => 'Info gespeichert']);
} catch (Throwable $e) {
    error_log('save_info: ' . $e->getMessage());
    json_error('Speichern fehlgeschlagen.', 500);
}
