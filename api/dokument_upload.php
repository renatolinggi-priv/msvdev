<?php
// api/dokument_upload.php - Datei-Upload fuer Einsatzplaene/Protokolle
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

// Nur Vorstand/Admin (JSON-Antwort bei Auth-Fehler)
requireRoleJson(['admin', 'vorstand']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

// CSRF pruefen
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiges CSRF-Token']);
    exit;
}

$typ = $_POST['typ'] ?? '';
if (!in_array($typ, ['einsatzplan', 'protokoll', 'jsk'])) {
    echo json_encode(['success' => false, 'message' => 'Ungültiger Dokumenttyp']);
    exit;
}

$titel = trim($_POST['titel'] ?? '');
$beschreibung = trim($_POST['beschreibung'] ?? '');
$datum = $_POST['datum'] ?? null;
$sichtbar_fuer = $_POST['sichtbar_fuer'] ?? 'vorstand';
if (!in_array($sichtbar_fuer, ['admin', 'vorstand', 'alle_mitglieder'])) {
    echo json_encode(['success' => false, 'message' => 'Ungültiger Sichtbarkeits-Wert']);
    exit;
}
$jahr = !empty($datum) ? date('Y', strtotime($datum)) : date('Y');

if (empty($titel)) {
    echo json_encode(['success' => false, 'message' => 'Titel ist erforderlich']);
    exit;
}

if (!isset($_FILES['datei']) || $_FILES['datei']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'Datei zu gross (Server-Limit)',
        UPLOAD_ERR_FORM_SIZE => 'Datei zu gross',
        UPLOAD_ERR_PARTIAL => 'Upload unvollständig',
        UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewählt',
    ];
    $err_code = $_FILES['datei']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['success' => false, 'message' => $upload_errors[$err_code] ?? 'Upload-Fehler']);
    exit;
}

$file = $_FILES['datei'];

// Dateityp pruefen — Whitelist mit zugehoeriger, vertrauenswuerdiger Endung
$allowed_mimes = [
    'application/pdf'                                                            => 'pdf',
    'image/jpeg'                                                                 => 'jpg',
    'image/png'                                                                  => 'png',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'    => 'docx',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'          => 'xlsx',
    'application/vnd.ms-excel'                                                   => 'xls',
];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowed_mimes[$mime])) {
    echo json_encode(['success' => false, 'message' => 'Dateityp nicht erlaubt (PDF, DOCX, XLSX, JPG, PNG)']);
    exit;
}

// Dateigroesse pruefen (max 10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Datei zu gross (max. 10 MB)']);
    exit;
}

// Upload-Verzeichnis
$upload_base = __DIR__ . '/../portal/uploads/dokumente/' . $typ . '/';
if (!is_dir($upload_base)) {
    mkdir($upload_base, 0755, true);
}

// Dateiname sanitizen — Endung aus verifiziertem MIME (nicht aus Client-Namen)
$original_name = pathinfo($file['name'], PATHINFO_FILENAME);
$extension = $allowed_mimes[$mime];
$safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $original_name);
$filename = time() . '_' . $safe_name . '.' . $extension;
$filepath = $upload_base . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern der Datei']);
    exit;
}

// DOCX/XLSX-Einsatzpläne automatisch auf "Nur Admin" setzen
if (in_array($extension, ['docx', 'xlsx', 'xls']) && $typ === 'einsatzplan') {
    $sichtbar_fuer = 'admin';
}

// DB-Eintrag
$db = getDB();
$stmt = $db->prepare("
    INSERT INTO vorstand_dokumente (typ, titel, beschreibung, dateiname, dateipfad, dateigroesse, hochgeladen_von, sichtbar_fuer, datum, jahr)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $typ,
    $titel,
    $beschreibung ?: null,
    $file['name'],
    $filepath,
    $file['size'],
    $_SESSION['user_id'],
    $sichtbar_fuer,
    $datum ?: null,
    $jahr
]);

echo json_encode(['success' => true, 'message' => 'Dokument hochgeladen', 'id' => $db->lastInsertId()]);
