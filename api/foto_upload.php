<?php
// api/foto_upload.php - Mitglieder laden EIN Foto pro Request in eine Galerie hoch.
// Der Client laedt mehrere Fotos sequenziell (umgeht post_max_size, zeigt Fortschritt).
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/fotogalerie.inc.php';

header('Content-Type: application/json; charset=utf-8');

// post_max_size-Ueberschreitung: PHP verwirft $_POST/$_FILES -> frueh abfangen
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && empty($_POST) && empty($_FILES)
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    json_error('Datei zu gross (Server-Limit).', 413);
}

requireRoleJson(['admin', 'vorstand', 'mitglied']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_error('Methode nicht erlaubt', 405);
if (!validateCsrfRequest()) json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);
if (!fotoFeatureAktiv()) json_error('Die Foto-Galerie ist deaktiviert.', 403);

$db       = getDB();
$userId   = (int) ($_SESSION['user_id'] ?? 0);
$istVorst = isVorstand();

$galerieId = (int) ($_POST['galerie_id'] ?? 0);
$g = $galerieId > 0 ? fotoGalerieLaden($db, $galerieId) : null;
if (!$g) json_error('Galerie nicht gefunden.', 404);
if (empty($g['freigeschaltet'])) json_error('Diese Galerie ist nicht freigeschaltet.', 403);
if (empty($g['upload_offen']) && !$istVorst) json_error('Der Upload für diesen Anlass ist geschlossen.', 403);

// Upload-Fehler pruefen
if (!isset($_FILES['datei']) || $_FILES['datei']['error'] !== UPLOAD_ERR_OK) {
    $map = [
        UPLOAD_ERR_INI_SIZE  => 'Datei zu gross (Server-Limit)',
        UPLOAD_ERR_FORM_SIZE => 'Datei zu gross',
        UPLOAD_ERR_PARTIAL   => 'Upload unvollständig',
        UPLOAD_ERR_NO_FILE   => 'Keine Datei ausgewählt',
    ];
    $code = $_FILES['datei']['error'] ?? UPLOAD_ERR_NO_FILE;
    json_error($map[$code] ?? 'Upload-Fehler');
}

$file = $_FILES['datei'];

if ($file['size'] > FOTO_MAX_BYTES) {
    json_error('Datei zu gross (max. ' . (int) (FOTO_MAX_BYTES / 1024 / 1024) . ' MB).');
}

// MIME echt pruefen (Whitelist). HEIC wird damit abgelehnt.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!isset($GLOBALS['FOTO_ALLOWED_MIMES'][$mime])) {
    $hint = ($mime === 'image/heic' || $mime === 'image/heif')
        ? 'HEIC wird nicht unterstützt – bitte als JPG hochladen (iPhone: Einstellungen › Kamera › Formate › „Maximale Kompatibilität").'
        : 'Nur JPG, PNG oder WebP erlaubt.';
    json_error($hint);
}

// EXIF-Aufnahmedatum aus der Originaldatei (vor der Verarbeitung) lesen
[$aufnahme, $quelle] = fotoExifAufnahme($file['tmp_name']);
$segmente = fotoSchiesstageSegmente($g['Schiesstage'] ?? null);
$tag = fotoTagInfo($aufnahme, $segmente);

// Bild verarbeiten (Full + Thumbnail)
try {
    $bild = fotoSpeichereBild($file['tmp_name'], $galerieId, $file['name']);
} catch (Throwable $e) {
    json_error('Bild konnte nicht verarbeitet werden. Ist es ein gültiges Foto?');
}

$status = !empty($g['moderation_aktiv']) ? 'pending' : 'approved';

$stmt = $db->prepare(
    "INSERT INTO anlass_fotos
        (galerie_id, dateiname, dateipfad, thumb_pfad, original_name, dateigroesse,
         breite, hoehe, aufnahme_zeit, zeit_quelle, tag_datum, tag_index,
         status, hochgeladen_von, moderiert_von, moderiert_am)
     VALUES
        (:gid, :dn, :dp, :tp, :on, :sz,
         :br, :ho, :az, :zq, :td, :ti,
         :st, :uid, :mv, :ma)"
);
$nowApproved = ($status === 'approved');
$stmt->execute([
    ':gid' => $galerieId,
    ':dn'  => $bild['dateiname'],
    ':dp'  => $bild['dateipfad'],
    ':tp'  => $bild['thumb_pfad'],
    ':on'  => $file['name'],
    ':sz'  => $file['size'],
    ':br'  => $bild['breite'],
    ':ho'  => $bild['hoehe'],
    ':az'  => $aufnahme,
    ':zq'  => $quelle,
    ':td'  => $tag['tag_datum'],
    ':ti'  => $tag['tag_index'],
    ':st'  => $status,
    ':uid' => $userId,
    ':mv'  => $nowApproved ? $userId : null,
    ':ma'  => $nowApproved ? date('Y-m-d H:i:s') : null,
]);

echo json_encode([
    'success'   => true,
    'id'        => (int) $db->lastInsertId(),
    'status'    => $status,
    'thumb_url' => '../api/foto_serve.php?id=' . (int) $db->lastInsertId() . '&size=thumb',
    'message'   => $status === 'pending'
        ? 'Foto hochgeladen – wartet auf Freigabe durch den Vorstand.'
        : 'Foto hochgeladen.',
]);
