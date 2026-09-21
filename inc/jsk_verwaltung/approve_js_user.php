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

        // Freischaltung per Mail mitteilen (best effort) – bisher erfuhr der Jungschuetze nichts davon
        $mailOk = false;
        if ($action === 'approve') {
            try {
                $u = $db->prepare('SELECT full_name, email, username FROM users WHERE id = ?');
                $u->execute([$userId]);
                if (($row = $u->fetch()) && !empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $host  = preg_replace('/[^a-z0-9.\-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'mitglieder.msvwilen.ch'));
                    $login = 'https://' . $host . '/login.php';
                    $subject = '=?UTF-8?B?' . base64_encode('MSV Wilen – dein Jungschützen-Zugang ist freigeschaltet') . '?=';
                    $body = "Hallo " . $row['full_name'] . ",\n\n"
                          . "dein Zugang zum Jungschützen-Portal des MSV Wilen wurde freigeschaltet.\n"
                          . "Benutzername: " . $row['username'] . "\n"
                          . "Anmelden: " . $login . "\n\n"
                          . "Im Portal kannst du Schiesstermine melden, dich für Trainings an- oder abmelden und mit der Jungschützenleitung chatten.\n\n"
                          . "Sportliche Grüsse\nJungschützenleitung MSV Wilen";
                    $headers = "From: noreply@msvwilen.ch\r\nContent-Type: text/plain; charset=UTF-8\r\n";
                    $mailOk = @mail($row['email'], $subject, $body, $headers);
                }
            } catch (Throwable $e) {
                error_log('approve_js_user mail: ' . $e->getMessage());
            }
        }
        echo json_encode(['success' => true, 'message' => 'Konto freigeschaltet' . ($mailOk ? ' – Jungschütze per E-Mail informiert' : '')]);
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
