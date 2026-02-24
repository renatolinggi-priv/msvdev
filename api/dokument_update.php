<?php
// api/dokument_update.php - Dokument-Metadaten aktualisieren, optional neue Datei
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireRole(['admin', 'vorstand']);

header('Content-Type: application/json; charset=utf-8');

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

$doc_id = intval($_POST['id'] ?? 0);
if ($doc_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM vorstand_dokumente WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) {
    echo json_encode(['success' => false, 'message' => 'Dokument nicht gefunden']);
    exit;
}

// Nur eigene Uploads oder Admin darf bearbeiten
if ($doc['hochgeladen_von'] != $_SESSION['user_id'] && ($_SESSION['user_role'] ?? '') != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

$titel = trim($_POST['titel'] ?? '');
$beschreibung = trim($_POST['beschreibung'] ?? '');
$datum = $_POST['datum'] ?? null;
$sichtbar_fuer = $_POST['sichtbar_fuer'] ?? $doc['sichtbar_fuer'];
$jahr = !empty($datum) ? date('Y', strtotime($datum)) : ($doc['jahr'] ?? date('Y'));

if (empty($titel)) {
    echo json_encode(['success' => false, 'message' => 'Titel ist erforderlich']);
    exit;
}
if (!in_array($sichtbar_fuer, ['admin', 'vorstand', 'alle_mitglieder'])) {
    echo json_encode(['success' => false, 'message' => 'Ungültiger Sichtbarkeits-Wert']);
    exit;
}

// Neue Datei optional
$new_filename = null;
$new_filepath = null;
$new_filesize = null;

if (isset($_FILES['datei']) && $_FILES['datei']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['datei'];

    $allowed_mimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
    ];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_mimes)) {
        echo json_encode(['success' => false, 'message' => 'Dateityp nicht erlaubt (PDF, DOCX, XLSX, JPG, PNG)']);
        exit;
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Datei zu gross (max. 10 MB)']);
        exit;
    }

    $upload_base = __DIR__ . '/../portal/uploads/dokumente/' . $doc['typ'] . '/';
    if (!is_dir($upload_base)) {
        mkdir($upload_base, 0755, true);
    }

    $safe_name  = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $extension  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename   = time() . '_' . $safe_name . '.' . $extension;
    $filepath   = $upload_base . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern der Datei']);
        exit;
    }

    // Alte Datei löschen
    if (file_exists($doc['dateipfad'])) {
        unlink($doc['dateipfad']);
    }

    $new_filename = $file['name'];
    $new_filepath = $filepath;
    $new_filesize = $file['size'];
}

// Update
if ($new_filename) {
    $upd = $db->prepare("
        UPDATE vorstand_dokumente
        SET titel=?, beschreibung=?, datum=?, sichtbar_fuer=?, jahr=?, dateiname=?, dateipfad=?, dateigroesse=?
        WHERE id=?
    ");
    $upd->execute([$titel, $beschreibung ?: null, $datum ?: null, $sichtbar_fuer, $jahr,
                   $new_filename, $new_filepath, $new_filesize, $doc_id]);
} else {
    $upd = $db->prepare("
        UPDATE vorstand_dokumente
        SET titel=?, beschreibung=?, datum=?, sichtbar_fuer=?, jahr=?
        WHERE id=?
    ");
    $upd->execute([$titel, $beschreibung ?: null, $datum ?: null, $sichtbar_fuer, $jahr, $doc_id]);
}

echo json_encode(['success' => true, 'message' => 'Dokument aktualisiert']);
