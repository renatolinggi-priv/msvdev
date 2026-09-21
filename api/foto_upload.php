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

// MIME echt pruefen (Whitelist). HEIC nur, wenn der Server es lesen kann (Imagick).
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$erlaubt = fotoErlaubteMimes();
if (!isset($erlaubt[$mime])) {
    if ($mime === 'image/heic' || $mime === 'image/heif') {
        $hint = 'HEIC wird auf diesem Server nicht unterstützt – bitte als JPG hochladen (iPhone: Einstellungen › Kamera › Formate › „Maximale Kompatibilität").';
    } elseif (strpos((string) $mime, 'video/') === 0) {
        $hint = 'Videos werden nicht unterstützt – nur Fotos (JPG, PNG, WebP' . (fotoHeicUnterstuetzt() ? ', HEIC' : '') . ').';
    } else {
        $hint = 'Nur JPG, PNG' . (fotoHeicUnterstuetzt() ? ', WebP oder HEIC' : ' oder WebP') . ' erlaubt.';
    }
    json_error($hint);
}

// Aufnahmedatum: EXIF aus der Originaldatei (vor der Verarbeitung). Ohne EXIF (WhatsApp,
// Screenshots) faellt es auf das vom Browser mitgeschickte Dateidatum zurueck.
[$aufnahme, $quelle] = fotoExifAufnahme($file['tmp_name']);
if ($aufnahme === null) {
    $aufnahme = fotoAufnahmeAusDateidatum($_POST['datei_mtime'] ?? null);
    $quelle   = $aufnahme !== null ? 'mtime' : null;
}
$segmente = fotoGalerieSegmente($g);
$tag = fotoTagInfo($aufnahme, $segmente);

// Bild verarbeiten (Full + Medium + Thumbnail). Grosse Originale (40+ Megapixel) brauchen
// mit GD viel Speicher -> Limit fuer diesen Request anheben; Imagick kommt mit weniger aus.
@ini_set('memory_limit', '512M');
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
         status, hochgeladen_von, moderiert_von, moderiert_am, sortierung)
     VALUES
        (:gid, :dn, :dp, :tp, :on, :sz,
         :br, :ho, :az, :zq, :td, :ti,
         :st, :uid, :mv, :ma, :so)"
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
    // Neue Fotos reihen sich hinten ein (0 wuerde vor die vom Vorstand sortierten Fotos springen)
    ':so'  => fotoSortierungNaechste($db, $galerieId),
]);

$neuId = (int) $db->lastInsertId();
echo json_encode([
    'success'     => true,
    'id'          => $neuId,
    'status'      => $status,
    'zeit_quelle' => $quelle,
    'thumb_url'   => '../api/foto_serve.php?id=' . $neuId . '&size=thumb',
    'medium_url'  => '../api/foto_serve.php?id=' . $neuId . '&size=medium',
    'message'   => $status === 'pending'
        ? 'Foto hochgeladen – wartet auf Freigabe durch den Vorstand.'
        : 'Foto hochgeladen.',
]);
