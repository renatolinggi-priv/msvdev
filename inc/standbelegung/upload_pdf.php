<?php
// upload_pdf.php - Lädt PDF für Standbelegung hoch
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage']);
    exit;
}

// Prüfe ob Datei hochgeladen wurde
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'Datei zu gross (php.ini limit)',
        UPLOAD_ERR_FORM_SIZE => 'Datei zu gross (form limit)',
        UPLOAD_ERR_PARTIAL => 'Datei nur teilweise hochgeladen',
        UPLOAD_ERR_NO_FILE => 'Keine Datei hochgeladen',
        UPLOAD_ERR_NO_TMP_DIR => 'Kein temporäres Verzeichnis',
        UPLOAD_ERR_CANT_WRITE => 'Schreibfehler',
        UPLOAD_ERR_EXTENSION => 'Upload durch Erweiterung gestoppt'
    ];
    $errorCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['success' => false, 'message' => $errors[$errorCode] ?? 'Upload-Fehler']);
    exit;
}

$file = $_FILES['file'];
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

// Validierung
$allowedTypes = ['application/pdf'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Nur PDF-Dateien erlaubt']);
    exit;
}

// Verzeichnis erstellen
$pdfDir = __DIR__ . '/pdf';
if (!is_dir($pdfDir)) {
    if (!mkdir($pdfDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Konnte PDF-Verzeichnis nicht erstellen']);
        exit;
    }
}

// Dateiname: standbelegung_YYYY.pdf
$filename = 'standbelegung_' . $year . '.pdf';
$filepath = $pdfDir . '/' . $filename;

// Datei verschieben
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Konnte Datei nicht speichern']);
    exit;
}

echo json_encode([
    'success' => true,
    'year' => $year,
    'filename' => $filename,
    'filepath' => 'standbelegung/pdf/' . $filename,
    'size' => round(filesize($filepath) / 1024, 1) . ' KB',
    'message' => 'PDF erfolgreich hochgeladen'
]);
