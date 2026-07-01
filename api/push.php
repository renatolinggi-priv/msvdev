<?php
// api/push.php - Web-Push Abo-Endpoint fuers Mitgliederportal
// Vorlage: benachrichtigungs-konzept.md (Abschnitt 2.4)
//
// Aktionen (?action=...):
//   public_key       GET   -> liefert VAPID Public Key (kein CSRF, nicht geheim)
//   subscribe        POST  -> Web-Push-Abo speichern (CSRF)
//   unsubscribe      POST  -> Web-Push-Abo dieses Geraets loeschen (CSRF)
//   register_native  POST  -> FCM-Token der nativen App speichern (CSRF)
//   unregister_native POST -> FCM-Token der nativen App loeschen (CSRF)
//   test             POST  -> Test-Push an alle eigenen Geraete (CSRF)

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// --- public_key: GET, OEFFENTLICH (kein Login, kein CSRF) --------------------
// Der VAPID Public Key ist nicht geheim und muss auch fuer den Service Worker
// (pushsubscriptionchange / Selbstheilung bei evtl. abgelaufener Session)
// erreichbar sein. Daher VOR requireRoleJson.
if ($action === 'public_key') {
    $db = getDB();
    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute(['vapid_public_key']);
    $pub = $stmt->fetchColumn();
    if (!$pub) {
        json_error('Push ist noch nicht eingerichtet (kein VAPID-Key vorhanden).', 503);
    }
    echo json_encode(['success' => true, 'public_key' => $pub]);
    exit;
}

requireRoleJson(['admin', 'vorstand', 'mitglied']); // alle eingeloggten, freigegebenen User

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);

// --- list: GET, eigene abonnierte Geraete (nur Anzeige, kein CSRF) -----------
if ($action === 'list') {
    $stmt = $db->prepare('SELECT geraet, erstellt_am FROM push_abos WHERE benutzer_id = ? ORDER BY erstellt_am DESC');
    $stmt->execute([$userId]);
    echo json_encode(['success' => true, 'geraete' => $stmt->fetchAll()]);
    exit;
}

// --- Ab hier: POST + CSRF aus JSON-Body --------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Methode nicht erlaubt.', 405);
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];
$csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validateCsrf($csrf)) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.');
}

switch ($action) {
    case 'subscribe':
        $endpoint = trim((string) ($input['endpoint'] ?? ''));
        $p256dh   = trim((string) ($input['p256dh'] ?? ''));
        $auth     = trim((string) ($input['auth'] ?? ''));
        $geraet   = mb_substr(trim((string) ($input['geraet'] ?? '')), 0, 100);
        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            json_error('Unvollständige Abo-Daten.');
        }

        // Gehoert der endpoint einem ANDEREN Benutzer (Geraet hat Account gewechselt)?
        // -> alten Eintrag explizit loeschen, sonst bekaeme der alte User die Pushes des neuen.
        $del = $db->prepare('DELETE FROM push_abos WHERE endpoint = ? AND benutzer_id <> ?');
        $del->execute([$endpoint, $userId]);

        $ins = $db->prepare(
            'INSERT INTO push_abos (benutzer_id, endpoint, p256dh, auth_key, geraet)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth_key = VALUES(auth_key),
                                     geraet = VALUES(geraet), benutzer_id = VALUES(benutzer_id)'
        );
        $ins->execute([$userId, $endpoint, $p256dh, $auth, ($geraet !== '' ? $geraet : null)]);

        // Prefs-Zeile sicherstellen (Defaults = alles an)
        $db->prepare('INSERT IGNORE INTO benachrichtigung_prefs (user_id) VALUES (?)')->execute([$userId]);

        echo json_encode(['success' => true, 'message' => 'Benachrichtigungen auf diesem Gerät aktiviert.']);
        break;

    case 'unsubscribe':
        $endpoint = trim((string) ($input['endpoint'] ?? ''));
        if ($endpoint !== '') {
            $del = $db->prepare('DELETE FROM push_abos WHERE endpoint = ? AND benutzer_id = ?');
            $del->execute([$endpoint, $userId]);
        }
        echo json_encode(['success' => true, 'message' => 'Benachrichtigungen auf diesem Gerät deaktiviert.']);
        break;

    // --- Native App (Capacitor): FCM-Token registrieren -----------------------
    case 'register_native':
        $token    = trim((string) ($input['fcm_token'] ?? ''));
        $platform = (string) ($input['platform'] ?? '');
        $platform = in_array($platform, ['ios', 'android'], true) ? $platform : null;
        $appVer   = mb_substr(trim((string) ($input['app_version'] ?? '')), 0, 20);
        if ($token === '') {
            json_error('Kein Token übermittelt.');
        }

        // Token evtl. anderem Benutzer wegnehmen (Geraet hat Account gewechselt) - wie bei subscribe.
        $db->prepare('DELETE FROM push_geraete_native WHERE fcm_token = ? AND benutzer_id <> ?')
           ->execute([$token, $userId]);

        $ins = $db->prepare(
            'INSERT INTO push_geraete_native (benutzer_id, fcm_token, platform, app_version)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE benutzer_id = VALUES(benutzer_id), platform = VALUES(platform),
                                     app_version = VALUES(app_version)'
        );
        $ins->execute([$userId, $token, $platform, ($appVer !== '' ? $appVer : null)]);

        // Prefs-Zeile sicherstellen (Defaults = alles an) - identisch zu subscribe.
        $db->prepare('INSERT IGNORE INTO benachrichtigung_prefs (user_id) VALUES (?)')->execute([$userId]);

        echo json_encode(['success' => true, 'message' => 'Gerät für Benachrichtigungen registriert.']);
        break;

    case 'unregister_native':
        $token = trim((string) ($input['fcm_token'] ?? ''));
        if ($token !== '') {
            $db->prepare('DELETE FROM push_geraete_native WHERE fcm_token = ? AND benutzer_id = ?')
               ->execute([$token, $userId]);
        }
        echo json_encode(['success' => true, 'message' => 'Gerät abgemeldet.']);
        break;

    case 'test':
        require_once __DIR__ . '/../inc/push_helper.php';
        $n = sendePushAnBenutzer(
            $userId,
            'MSV Wilen',
            'Test-Benachrichtigung – Push funktioniert!',
            'portal/dashboard.php'
        );
        if ($n > 0) {
            echo json_encode(['success' => true, 'message' => 'Test gesendet (' . $n . ' Gerät' . ($n === 1 ? '' : 'e') . ').']);
        } else {
            json_error('Kein Gerät erreichbar. Aktiviere Push zuerst auf diesem Gerät.');
        }
        break;

    default:
        json_error('Unbekannte Aktion.', 400);
}
