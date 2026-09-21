<?php
// inc/jsk_verwaltung/set_feature_active.php
// Globale Admin-Schalter der Jungschuetzen-Betreuung (settings):
//   jsk_betreuung_aktiv       – Master-Schalter fuer die ganze Funktion
//   jsk_chat_leiter_einsicht  – Leitung darf Match-Chats (JSK ↔ Betreuer) mitlesen
// Nur Admin. PDO, CSRF. Parameter: aktiv=0|1, setting=<key> (Default Master).

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

$erlaubt = [
    'jsk_betreuung_aktiv'      => ['Funktion aktiviert', 'Funktion deaktiviert'],
    'jsk_chat_leiter_einsicht' => ['Leitungs-Einsicht in Match-Chats aktiviert', 'Leitungs-Einsicht deaktiviert'],
];
$setting = (string) ($_POST['setting'] ?? 'jsk_betreuung_aktiv');
if (!isset($erlaubt[$setting])) {
    json_error('Unbekannte Einstellung.');
}
$aktiv = !empty($_POST['aktiv']) ? '1' : '0';

try {
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$setting, $aktiv]);
    echo json_encode([
        'success' => true,
        'aktiv'   => $aktiv === '1',
        'message' => $aktiv === '1' ? $erlaubt[$setting][0] : $erlaubt[$setting][1],
    ]);
} catch (Throwable $e) {
    error_log('set_feature_active: ' . $e->getMessage());
    json_error('Speichern fehlgeschlagen.', 500);
}
