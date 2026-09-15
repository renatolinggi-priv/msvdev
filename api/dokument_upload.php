<?php
// api/dokument_upload.php - Datei-Upload fuer Einsatzplaene/Protokolle/JSK-Dokumente
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/lib/dokument_datei.inc.php';

header('Content-Type: application/json; charset=utf-8');

// Nur Vorstand/Admin (JSON-Antwort bei Auth-Fehler)
requireRoleJson(['admin', 'vorstand']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiges CSRF-Token']);
    exit;
}

function upload_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$typ = $_POST['typ'] ?? '';
if (!in_array($typ, ['einsatzplan', 'protokoll', 'jsk'], true)) upload_fail('Ungültiger Dokumenttyp');

$titel        = trim($_POST['titel'] ?? '');
$beschreibung = trim($_POST['beschreibung'] ?? '');
if ($titel === '') upload_fail('Titel ist erforderlich');

try {
    $datum = dokument_datum_pruefen($_POST['datum'] ?? '');
} catch (InvalidArgumentException $e) {
    upload_fail($e->getMessage());
}
$jahr = $datum ? (int)substr($datum, 0, 4) : (int)date('Y');

// Sichtbarkeit: JSK-Dokumente sind immer fuer alle Mitglieder (Jungschuetzen lesen sie im Portal)
$sichtbar_fuer = $_POST['sichtbar_fuer'] ?? 'vorstand';
if ($typ === 'jsk') {
    $sichtbar_fuer = 'alle_mitglieder';
} elseif (!in_array($sichtbar_fuer, ['admin', 'vorstand', 'alle_mitglieder'], true)) {
    upload_fail('Ungültiger Sichtbarkeits-Wert');
}

$file  = $_FILES['datei'] ?? ['error' => UPLOAD_ERR_NO_FILE];
$check = dokument_datei_pruefen($file);
if (!$check['ok']) upload_fail($check['message']);
$extension = $check['ext'];

// DOCX/XLSX-Einsatzplaene automatisch auf "Nur Admin" setzen
if ($typ === 'einsatzplan' && in_array($extension, ['docx', 'xlsx', 'xls'], true)) {
    $sichtbar_fuer = 'admin';
}

$upload_base = __DIR__ . '/../portal/uploads/dokumente/' . $typ . '/';
if (!is_dir($upload_base) && !mkdir($upload_base, 0755, true)) {
    upload_fail('Upload-Verzeichnis konnte nicht angelegt werden', 500);
}
$filepath = $upload_base . dokument_zielname($file['name'], $extension);

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    upload_fail('Fehler beim Speichern der Datei', 500);
}

// DB-Eintrag; schlaegt er fehl, darf keine Datei-Leiche liegen bleiben
try {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO vorstand_dokumente (typ, titel, beschreibung, dateiname, dateipfad, dateigroesse, hochgeladen_von, sichtbar_fuer, datum, jahr)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $typ, $titel, $beschreibung !== '' ? $beschreibung : null, $file['name'], $filepath, $file['size'],
        $_SESSION['user_id'], $sichtbar_fuer, $datum, $jahr,
    ]);
    echo json_encode(['success' => true, 'message' => 'Dokument hochgeladen', 'id' => (int)$db->lastInsertId(), 'jahr' => $jahr]);
} catch (Throwable $e) {
    @unlink($filepath);
    error_log('[dokument_upload] ' . $e->getMessage());
    upload_fail('Dokument konnte nicht gespeichert werden', 500);
}
