<?php
// inc/jsk_verwaltung/save_single_js.php
// Einzelnen Jungschuetzen anlegen (id=0) oder aktualisieren. PDO, CSRF, Vorstand/Admin.

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

$id        = (int) ($_POST['id'] ?? 0);
$vorname   = trim($_POST['vorname'] ?? '');
$name      = trim($_POST['name'] ?? '');
$geb       = trim($_POST['geburtsdatum'] ?? '');
$ahv       = trim($_POST['ahvnummer'] ?? '');
$strasse   = trim($_POST['strasse'] ?? '');
$plz       = trim($_POST['plz'] ?? '');
$ort       = trim($_POST['ort'] ?? '');
$email     = trim($_POST['email'] ?? '');
$mobile    = trim($_POST['mobile'] ?? '');
$kursNr    = ($_POST['kursnummer'] ?? '') === '' ? 0 : (int) $_POST['kursnummer'];
$kursJahr  = ($_POST['kursjahr'] ?? '') === '' ? null : (int) $_POST['kursjahr'];
$aktiv     = !empty($_POST['aktiv']) ? 1 : 0;

if ($vorname === '' || $name === '') {
    json_error('Vorname und Name sind erforderlich.');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Ungültige E-Mail-Adresse.');
}
// Datum normalisieren (leer -> NULL)
$gebVal = ($geb === '') ? null : $geb;
$ahvVal = ($ahv === '') ? null : $ahv;
$emailVal = ($email === '') ? null : $email;

$db = getDB();

try {
    // E-Mail muss eindeutig sein (Registrierungs-Match laeuft ueber die Mailadresse)
    if ($emailVal !== null) {
        $chk = $db->prepare('SELECT id FROM jungschuetzen WHERE Email = ? AND id <> ? LIMIT 1');
        $chk->execute([$emailVal, $id]);
        if ($chk->fetchColumn()) {
            json_error('Diese E-Mail-Adresse ist bereits einem anderen Jungschützen zugeordnet.');
        }
    }

    if ($id > 0) {
        $stmt = $db->prepare(
            'UPDATE jungschuetzen
                SET Vorname = ?, Name = ?, Geburtsdatum = ?, AHVNummer = ?,
                    Strasse = ?, PLZ = ?, Ort = ?, Email = ?, Mobile = ?,
                    KursNummer = ?, KursJahr = ?, Aktiv = ?
              WHERE id = ?'
        );
        $stmt->execute([$vorname, $name, $gebVal, $ahvVal, $strasse, $plz, $ort,
                        $emailVal, $mobile, $kursNr, $kursJahr, $aktiv, $id]);
        echo json_encode(['success' => true, 'message' => 'Jungschütze gespeichert', 'id' => $id]);
    } else {
        $stmt = $db->prepare(
            'INSERT INTO jungschuetzen
                (Vorname, Name, Geburtsdatum, AHVNummer, Strasse, PLZ, Ort, Email, Mobile, KursNummer, KursJahr, Aktiv)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$vorname, $name, $gebVal, $ahvVal, $strasse, $plz, $ort,
                        $emailVal, $mobile, $kursNr, $kursJahr, $aktiv]);
        echo json_encode(['success' => true, 'message' => 'Jungschütze hinzugefügt', 'id' => (int) $db->lastInsertId()]);
    }
} catch (Throwable $e) {
    error_log('save_single_js: ' . $e->getMessage());
    json_error('Datenbankfehler beim Speichern.', 500);
}
