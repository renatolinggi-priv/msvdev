<?php
// inc/jsk_verwaltung/approve_js_user.php
// Konto eines Jungschuetzen freischalten/ablehnen/(de)aktivieren - direkt aus der
// Jungschuetzen-Verwaltung, damit auch der Vorstand (nicht nur Admin) freigeben kann.
// Streng auf users mit role='jungschuetze' beschraenkt -> keine Rechte-Eskalation.
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

$action = $_POST['action'] ?? '';
$userId = (int) ($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    json_error('Keine gültige Konto-ID.');
}

$db = getDB();

// Sicherstellen, dass es sich wirklich um ein Jungschuetzen-Konto handelt
$chk = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'jungschuetze' LIMIT 1");
$chk->execute([$userId]);
if (!$chk->fetchColumn()) {
    json_error('Kein Jungschützen-Konto gefunden.', 404);
}

switch ($action) {
    case 'approve':
    case 'enable':
        $stmt = $db->prepare("UPDATE users SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ? AND role = 'jungschuetze'");
        $stmt->execute([(int) ($_SESSION['user_id'] ?? 0), $userId]);
        echo json_encode(['success' => true, 'message' => 'Konto freigeschaltet']);
        break;

    case 'reject':
        $stmt = $db->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'jungschuetze'");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'message' => 'Konto abgelehnt']);
        break;

    case 'disable':
        $stmt = $db->prepare("UPDATE users SET status = 'disabled' WHERE id = ? AND role = 'jungschuetze'");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'message' => 'Konto deaktiviert']);
        break;

    default:
        json_error('Unbekannte Aktion.');
}
