<?php
// inc/jungschuetzen_verwaltung/set_feature_active.php
// Globaler Admin-Schalter fuer die Jungschuetzen-Betreuung (settings.jsk_betreuung_aktiv).
// Nur Admin. PDO, CSRF.

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);
}

$aktiv = !empty($_POST['aktiv']) ? '1' : '0';

try {
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute(['jsk_betreuung_aktiv', $aktiv]);
    echo json_encode([
        'success' => true,
        'aktiv'   => $aktiv === '1',
        'message' => $aktiv === '1' ? 'Funktion aktiviert' : 'Funktion deaktiviert',
    ]);
} catch (Throwable $e) {
    error_log('set_feature_active: ' . $e->getMessage());
    json_error('Speichern fehlgeschlagen.', 500);
}
