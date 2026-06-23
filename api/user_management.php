<?php
// api/user_management.php - Admin-API fuer Benutzerverwaltung (AJAX)
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

// Nur Admins (JSON-Antwort bei Auth-Fehler)
requireRoleJson('admin');

// CSRF pruefen
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiges CSRF-Token']);
    exit;
}

$action = $_POST['action'] ?? '';
$user_id = intval($_POST['user_id'] ?? 0);

if ($user_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Ungültige User-ID']);
    exit;
}

$db = getDB();

switch ($action) {
    case 'approve':
        $stmt = $db->prepare("UPDATE users SET status='approved', approved_at=NOW(), approved_by=? WHERE id=? AND status='pending'");
        $stmt->execute([$_SESSION['user_id'], $user_id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Benutzer freigeschaltet']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden oder bereits bearbeitet']);
        }
        break;

    case 'reject':
        $stmt = $db->prepare("UPDATE users SET status='rejected' WHERE id=? AND status='pending'");
        $stmt->execute([$user_id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Registrierung abgelehnt']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden oder bereits bearbeitet']);
        }
        break;

    case 'disable':
        // Kein Admin deaktivieren
        $stmt = $db->prepare("SELECT role FROM users WHERE id=?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user && $user['role'] == 'admin') {
            echo json_encode(['success' => false, 'message' => 'Admin-Accounts können nicht deaktiviert werden']);
            break;
        }
        $stmt = $db->prepare("UPDATE users SET status='disabled' WHERE id=?");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true, 'message' => 'Benutzer deaktiviert']);
        break;

    case 'enable':
        $stmt = $db->prepare("UPDATE users SET status='approved', approved_at=NOW(), approved_by=? WHERE id=?");
        $stmt->execute([$_SESSION['user_id'], $user_id]);
        echo json_encode(['success' => true, 'message' => 'Benutzer aktiviert']);
        break;

    case 'change_role':
        $new_role = $_POST['role'] ?? '';
        if (!in_array($new_role, ['admin', 'vorstand', 'mitglied'])) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Rolle']);
            break;
        }
        // Eigene Rolle nicht aendern
        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Du kannst deine eigene Rolle nicht ändern']);
            break;
        }
        $stmt = $db->prepare("UPDATE users SET role=? WHERE id=?");
        $stmt->execute([$new_role, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Rolle geändert zu ' . ucfirst($new_role)]);
        break;

    case 'assign_mitglied':
        $mitglied_id = intval($_POST['mitglied_id'] ?? 0);
        if ($mitglied_id < 1) {
            // Verknuepfung entfernen
            $stmt = $db->prepare("UPDATE users SET mitglied_id=NULL WHERE id=?");
            $stmt->execute([$user_id]);
            echo json_encode(['success' => true, 'message' => 'Mitglied-Verknüpfung entfernt']);
            break;
        }
        // Pruefen ob Mitglied existiert
        $stmt = $db->prepare("SELECT ID, Vorname, Name FROM mitglieder WHERE ID=?");
        $stmt->execute([$mitglied_id]);
        $mitglied = $stmt->fetch();
        if (!$mitglied) {
            echo json_encode(['success' => false, 'message' => 'Mitglied nicht gefunden']);
            break;
        }
        // Pruefen ob Mitglied bereits einem anderen User zugeordnet ist
        $stmt = $db->prepare("SELECT id, username FROM users WHERE mitglied_id=? AND id!=?");
        $stmt->execute([$mitglied_id, $user_id]);
        $existing = $stmt->fetch();
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Mitglied bereits zugeordnet zu: ' . $existing['username']]);
            break;
        }
        $stmt = $db->prepare("UPDATE users SET mitglied_id=? WHERE id=?");
        $stmt->execute([$mitglied_id, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Verknüpft mit ' . $mitglied['Vorname'] . ' ' . $mitglied['Name']]);
        break;

    case 'delete':
        // Sich selbst nicht loeschen
        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Du kannst dich nicht selbst löschen']);
            break;
        }
        $stmt = $db->prepare("DELETE FROM users WHERE id=? AND id!=1");
        $stmt->execute([$user_id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Benutzer gelöscht']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Benutzer kann nicht gelöscht werden']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
}
