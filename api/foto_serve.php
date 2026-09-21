<?php
// api/foto_serve.php - liefert Galerie-Bilder/Programm-PDF mit Berechtigungspruefung.
// Direktzugriff auf portal/uploads/ ist per .htaccess gesperrt; Auslieferung nur hier.
//   ?id=<fotoId>&size=thumb|medium|full -> Bild (medium wird bei Bedarf aus full erzeugt)
//   ?programm=<galerieId>               -> Programm-PDF der Galerie
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/fotogalerie.inc.php';
requireLogin();

// Jungschuetzen haben keinen Zugriff auf die Mitglieder-Galerie
if (isJungschuetze()) { http_response_code(403); die('Zugriff verweigert'); }

$db        = getDB();
$userId    = (int) ($_SESSION['user_id'] ?? 0);
$istVorst  = isVorstand();

// WICHTIG: Session-Lock früh freigeben. Eine Galerie lädt viele Bilder gleichzeitig;
// solange dieser Request die Session hält, werden alle weiteren Bild-Requests (und
// sogar andere Seitenaufrufe) serialisiert -> Galerie lädt extrem langsam / "offline".
// Ab hier wird die Session nicht mehr gebraucht (nur noch DB-Lesezugriff + Datei).
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

/**
 * Datei mit Cache-Headern + 304-Unterstuetzung ausliefern.
 * $unveraenderlich = true fuer Fotos: eine Foto-ID zeigt immer dieselbe Datei, darum darf der
 * Browser ein Jahr cachen, ohne taeglich per ETag nachzufragen (Programm-PDF kann ersetzt werden).
 */
function foto_send_file(string $path, string $disposition = 'inline', bool $unveraenderlich = false): void {
    if (!fotoPfadErlaubt($path) || !is_file($path)) { http_response_code(404); die('Datei nicht gefunden'); }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $path);
    finfo_close($finfo);

    $mtime = filemtime($path);
    $etag  = '"' . md5($path . '|' . $mtime) . '"';
    $cache = $unveraenderlich ? 'private, max-age=31536000, immutable' : 'private, max-age=86400';

    $ifNone = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
    if ($ifNone !== '' && $ifNone === $etag) {
        header('ETag: ' . $etag);
        header('Cache-Control: ' . $cache);
        http_response_code(304);
        exit;
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . $disposition . '; filename="' . basename($path) . '"');
    header('Cache-Control: ' . $cache);
    header('ETag: ' . $etag);
    readfile($path);
    exit;
}

// --- Programm-PDF einer Galerie ---
$programmId = (int) ($_GET['programm'] ?? 0);
if ($programmId > 0) {
    $g = fotoGalerieLaden($db, $programmId);
    if (!$g || empty($g['programm_dateipfad'])) { http_response_code(404); die('Kein Programm vorhanden'); }
    if (!$istVorst && empty($g['freigeschaltet'])) { http_response_code(403); die('Zugriff verweigert'); }
    foto_send_file($g['programm_dateipfad'], 'inline');
}

// --- Foto (Thumbnail oder Full) ---
$fotoId = (int) ($_GET['id'] ?? 0);
if ($fotoId < 1) { http_response_code(400); die('Ungültige ID'); }

$stmt = $db->prepare(
    "SELECT f.*, g.freigeschaltet
       FROM anlass_fotos f
       JOIN anlass_galerie g ON g.id = f.galerie_id
      WHERE f.id = ?"
);
$stmt->execute([$fotoId]);
$foto = $stmt->fetch();
if (!$foto) { http_response_code(404); die('Foto nicht gefunden'); }

// Galerie muss freigeschaltet sein (ausser Vorstand)
if (!$istVorst && empty($foto['freigeschaltet'])) { http_response_code(403); die('Zugriff verweigert'); }

// Moderation: nicht freigegebene Fotos nur fuer Uploader + Vorstand
if ($foto['status'] !== 'approved' && !$istVorst && (int) $foto['hochgeladen_von'] !== $userId) {
    http_response_code(403);
    die('Zugriff verweigert');
}

$size = $_GET['size'] ?? 'thumb';
if ($size === 'full') {
    $path = $foto['dateipfad'];
} elseif ($size === 'medium') {
    // Uebersichts-Cover und Handy-Slideshow: 1280 px statt 2560 px. Aeltere Fotos ohne
    // Medium-Datei werden beim ersten Aufruf nachgerechnet.
    $path = fotoMediumSicherstellen($foto);
} else {
    $path = $foto['thumb_pfad'] ?: $foto['dateipfad'];
}
foto_send_file($path, 'inline', true);
