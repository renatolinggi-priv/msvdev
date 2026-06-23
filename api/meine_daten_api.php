<?php
// api/meine_daten_api.php - API für "Meine Daten" im Mitgliederportal
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$userId = $_SESSION['user_id'];

/**
 * Passwort gegen bcrypt-Hash ODER alten 32-stelligen MD5-Hash prüfen (Legacy).
 */
function verifyUserPassword($plain, $hash) {
    if (is_string($hash) && strlen($hash) === 32 && ctype_xdigit($hash)) {
        return hash_equals(strtolower($hash), md5($plain));
    }
    return password_verify($plain, (string)$hash);
}

// User-Daten laden (NICHT vom Client!)
$stmt = $db->prepare("SELECT mitglied_id, username FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || empty($user['mitglied_id'])) {
    echo json_encode(['success' => false, 'message' => 'Kein Mitglied zugeordnet.']);
    exit;
}

$mitgliedId = $user['mitglied_id'];

// ================================================================
// GET — Daten laden
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT m.Vorname, m.Name, m.Geburtsdatum, m.Strasse, m.PLZ, m.Ort,
               m.Email, m.Telefon, m.Mobile, m.ID as MitgliedNr,
               w.bezeichnung AS Waffe
        FROM mitglieder m
        LEFT JOIN Waffen w ON m.WaffenID = w.id
        WHERE m.ID = ?
    ");
    $stmt->execute([$mitgliedId]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Mitglied nicht gefunden.']);
        exit;
    }

    // Geburtsdatum formatieren (DB: YYYY-MM-DD → Anzeige: DD.MM.YYYY)
    $geb = '';
    if (!empty($row['Geburtsdatum'])) {
        $d = DateTime::createFromFormat('Y-m-d', $row['Geburtsdatum']);
        $geb = $d ? $d->format('d.m.Y') : $row['Geburtsdatum'];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'vorname'      => $row['Vorname'] ?? '',
            'name'         => $row['Name'] ?? '',
            'geburtsdatum' => $geb,
            'waffe'        => $row['Waffe'] ?? '',
            'mitglied_nr'  => $row['MitgliedNr'],
            'strasse'      => $row['Strasse'] ?? '',
            'plz'          => $row['PLZ'] ?? '',
            'ort'          => $row['Ort'] ?? '',
            'email'        => $row['Email'] ?? '',
            'telefon'      => $row['Telefon'] ?? '',
            'mobile'       => $row['Mobile'] ?? '',
            'username'     => $user['username'] ?? '',
        ]
    ]);
    exit;
}

// ================================================================
// POST — Daten speichern
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage.']);
        exit;
    }

    // CSRF prüfen
    if (!validateCsrf($input['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Ungültiges CSRF-Token. Bitte Seite neu laden.']);
        exit;
    }

    $action = $input['action'] ?? 'save_contact';

    // ================================================================
    // Action: Benutzername ändern
    // ================================================================
    if ($action === 'change_username') {
        $newUsername = trim($input['new_username'] ?? '');
        $currentPassword = $input['current_password'] ?? '';

        if ($newUsername === '') {
            echo json_encode(['success' => false, 'message' => 'Bitte einen Benutzernamen eingeben.']);
            exit;
        }
        if (mb_strlen($newUsername) < 3) {
            echo json_encode(['success' => false, 'message' => 'Benutzername muss mindestens 3 Zeichen lang sein.']);
            exit;
        }
        if (mb_strlen($newUsername) > 50) {
            echo json_encode(['success' => false, 'message' => 'Benutzername darf maximal 50 Zeichen lang sein.']);
            exit;
        }
        if (!preg_match('/^[a-zA-Z0-9._@-]+$/', $newUsername)) {
            echo json_encode(['success' => false, 'message' => 'Benutzername darf nur Buchstaben, Zahlen, Punkte, Bindestriche und @ enthalten.']);
            exit;
        }

        // Aktuelles Passwort prüfen
        $stmt = $db->prepare("SELECT password_hash, username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userRow = $stmt->fetch();

        if (!verifyUserPassword($currentPassword, $userRow['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Aktuelles Passwort ist falsch.']);
            exit;
        }

        // Prüfen ob neuer Name == alter Name
        if ($newUsername === $userRow['username']) {
            echo json_encode(['success' => true, 'message' => 'Keine Änderung.', 'changes' => 0]);
            exit;
        }

        // Eindeutigkeit prüfen
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$newUsername, $userId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Dieser Benutzername ist bereits vergeben.']);
            exit;
        }

        // Update
        $stmt = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$newUsername, $userId]);

        // Session aktualisieren
        $_SESSION['username'] = $newUsername;

        echo json_encode(['success' => true, 'message' => 'Benutzername wurde geändert.', 'new_username' => $newUsername]);
        exit;
    }

    // ================================================================
    // Action: Passwort ändern
    // ================================================================
    if ($action === 'change_password') {
        $currentPassword = $input['current_password'] ?? '';
        $newPassword     = $input['new_password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            echo json_encode(['success' => false, 'message' => 'Bitte alle Passwort-Felder ausfüllen.']);
            exit;
        }
        if ($newPassword !== $confirmPassword) {
            echo json_encode(['success' => false, 'message' => 'Die neuen Passwörter stimmen nicht überein.']);
            exit;
        }
        if (mb_strlen($newPassword) < 8) {
            echo json_encode(['success' => false, 'message' => 'Das neue Passwort muss mindestens 8 Zeichen lang sein.']);
            exit;
        }

        // Aktuelles Passwort prüfen (bcrypt oder Legacy-MD5)
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userRow = $stmt->fetch();

        if (!verifyUserPassword($currentPassword, $userRow['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Aktuelles Passwort ist falsch.']);
            exit;
        }

        // Neues Passwort hashen (bcrypt)
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);

        // Sicherheit: bestehende Remember-Tokens invalidieren (andere Geräte ausloggen).
        // Aktuelles Gerät bleibt eingeloggt, falls ein Remember-Cookie aktiv ist.
        try {
            $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$userId]);
            if (function_exists('setRememberToken') && defined('REMEMBER_COOKIE_NAME')
                && !empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
                setRememberToken($userId);
            }
        } catch (Exception $e) {
            error_log('change_password token invalidation: ' . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Passwort wurde erfolgreich geändert.']);
        exit;
    }

    // ================================================================
    // Action: Kontaktdaten speichern (Standard)
    // ================================================================

    // Erlaubte Felder
    $allowedFields = [
        'strasse' => 'Strasse',
        'plz'     => 'PLZ',
        'ort'     => 'Ort',
        'email'   => 'Email',
        'telefon' => 'Telefon',
        'mobile'  => 'Mobile',
    ];

    // Validierung
    $errors = [];

    $strasse = trim($input['strasse'] ?? '');
    $plz     = trim($input['plz'] ?? '');
    $ort     = trim($input['ort'] ?? '');
    $email   = trim($input['email'] ?? '');
    $telefon = trim($input['telefon'] ?? '');
    $mobile  = trim($input['mobile'] ?? '');

    if (mb_strlen($strasse) > 255) $errors['strasse'] = 'Strasse darf maximal 255 Zeichen lang sein.';
    if (mb_strlen($plz) > 10) $errors['plz'] = 'PLZ darf maximal 10 Zeichen lang sein.';
    if ($plz !== '' && !preg_match('/^\d{4}$/', $plz)) $errors['plz'] = 'Bitte eine gültige 4-stellige PLZ eingeben.';
    if (mb_strlen($ort) > 100) $errors['ort'] = 'Ort darf maximal 100 Zeichen lang sein.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Bitte eine gültige E-Mail-Adresse eingeben.';
    if (mb_strlen($email) > 255) $errors['email'] = 'E-Mail darf maximal 255 Zeichen lang sein.';
    $phoneRegex = '/^\+41 \d{2} \d{3} \d{2} \d{2}$/';
    if ($telefon !== '' && !preg_match($phoneRegex, $telefon)) $errors['telefon'] = 'Bitte Format +41 79 123 45 67 verwenden.';
    if ($mobile !== '' && !preg_match($phoneRegex, $mobile)) $errors['mobile'] = 'Bitte Format +41 79 123 45 67 verwenden.';

    if (!empty($errors)) {
        $firstError = reset($errors);
        echo json_encode(['success' => false, 'message' => $firstError, 'errors' => $errors]);
        exit;
    }

    // Aktuelle Werte laden (für Audit-Trail)
    $stmt = $db->prepare("SELECT Strasse, PLZ, Ort, Email, Telefon, Mobile FROM mitglieder WHERE ID = ?");
    $stmt->execute([$mitgliedId]);
    $current = $stmt->fetch();

    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Mitglied nicht gefunden.']);
        exit;
    }

    // Neue Werte (XSS-geschützt für DB)
    $newValues = [
        'Strasse' => $strasse,
        'PLZ'     => $plz,
        'Ort'     => $ort,
        'Email'   => $email,
        'Telefon' => $telefon,
        'Mobile'  => $mobile,
    ];

    // Nur geänderte Felder ermitteln
    $changes = [];
    foreach ($newValues as $dbField => $newVal) {
        $oldVal = $current[$dbField] ?? '';
        if ((string)$oldVal !== (string)$newVal) {
            $changes[$dbField] = ['old' => $oldVal, 'new' => $newVal];
        }
    }

    if (empty($changes)) {
        echo json_encode(['success' => true, 'message' => 'Keine Änderungen vorhanden.', 'changes' => 0]);
        exit;
    }

    // Update + Audit-Trail in Transaktion
    try {
        $db->beginTransaction();

        // UPDATE nur geänderte Felder
        $setClauses = [];
        $params = [];
        foreach ($changes as $dbField => $vals) {
            $setClauses[] = "`$dbField` = ?";
            $params[] = $vals['new'];
        }
        $params[] = $mitgliedId;
        $sql = "UPDATE mitglieder SET " . implode(', ', $setClauses) . " WHERE ID = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // Audit-Trail
        $auditStmt = $db->prepare("
            INSERT INTO mitglieder_aenderungen (mitglied_id, user_id, feld, alter_wert, neuer_wert)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($changes as $dbField => $vals) {
            $auditStmt->execute([$mitgliedId, $userId, $dbField, $vals['old'], $vals['new']]);
        }

        $db->commit();

        $count = count($changes);
        echo json_encode([
            'success' => true,
            'message' => 'Deine Daten wurden gespeichert.',
            'changes' => $count
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("meine_daten_api.php save error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern. Bitte versuche es erneut.']);
    }
    exit;
}

// Andere Methoden
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt.']);
