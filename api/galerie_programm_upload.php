<?php
// api/galerie_programm_upload.php - optionale Programm-PDF einer Galerie (Vorstand/Admin).
// POST datei (PDF) + galerie_id  -> hochladen/ersetzen
// POST action=remove + galerie_id -> entfernen
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/fotogalerie.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_error('Methode nicht erlaubt', 405);
if (!validateCsrf($_POST['csrf_token'] ?? '')) json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);

$db  = getDB();
$gid = (int) ($_POST['galerie_id'] ?? 0);
if ($gid < 1) json_error('Ungültige Galerie.');

$g = fotoGalerieLaden($db, $gid);
if (!$g) json_error('Galerie nicht gefunden.', 404);

// --- Entfernen ---
if (($_POST['action'] ?? '') === 'remove') {
    if (!empty($g['programm_dateipfad']) && is_file($g['programm_dateipfad']) && fotoPfadErlaubt($g['programm_dateipfad'])) {
        @unlink($g['programm_dateipfad']);
    }
    $db->prepare("UPDATE anlass_galerie SET programm_dateiname = NULL, programm_dateipfad = NULL WHERE id = ?")->execute([$gid]);
    echo json_encode(['success' => true, 'message' => 'Programm entfernt.']);
    exit;
}

// --- Hochladen ---
if (!isset($_FILES['datei']) || $_FILES['datei']['error'] !== UPLOAD_ERR_OK) {
    json_error('Keine Datei oder Upload-Fehler.');
}
$file = $_FILES['datei'];
if ($file['size'] > 10 * 1024 * 1024) json_error('Datei zu gross (max. 10 MB).');

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if ($mime !== 'application/pdf') json_error('Nur PDF erlaubt.');

// Altes Programm entfernen
if (!empty($g['programm_dateipfad']) && is_file($g['programm_dateipfad']) && fotoPfadErlaubt($g['programm_dateipfad'])) {
    @unlink($g['programm_dateipfad']);
}

$fname = 'programm_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
$path  = fotoGalerieDir($gid) . $fname;
if (!move_uploaded_file($file['tmp_name'], $path)) {
    json_error('Datei konnte nicht gespeichert werden.');
}

$db->prepare("UPDATE anlass_galerie SET programm_dateiname = ?, programm_dateipfad = ? WHERE id = ?")
   ->execute([$file['name'], $path, $gid]);

echo json_encode(['success' => true, 'message' => 'Programm hochgeladen.', 'dateiname' => $file['name']]);
