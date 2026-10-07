<?php
// api/foto_zip.php - alle Fotos einer Galerie als ZIP (Vorstand/Admin), fuer Archiv/Chronik.
//   ?galerie_id=<id>          -> freigegebene Fotos, Full-Version, nach Tagen in Ordnern
//   ?galerie_id=<id>&alle=1   -> zusaetzlich wartende und abgelehnte (Ordner _wartend/_abgelehnt)
// Das ZIP wird ohne Kompression gebaut (JPEG ist schon komprimiert) und aus einer
// Temp-Datei gestreamt; Speicherbedarf bleibt damit klein, auch bei hunderten Fotos.
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/fotogalerie.inc.php';
requireLogin();
if (!isVorstand()) { http_response_code(403); die('Zugriff verweigert'); }

$db  = getDB();
$gid = (int) ($_GET['galerie_id'] ?? 0);
$g   = $gid > 0 ? fotoGalerieLaden($db, $gid) : null;
if (!$g) { http_response_code(404); die('Galerie nicht gefunden'); }
if (!class_exists('ZipArchive')) { http_response_code(500); die('ZIP-Erweiterung fehlt auf dem Server'); }

$alle = !empty($_GET['alle']);

// Session frueh freigeben: das Packen kann dauern, andere Requests sollen nicht warten.
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
@set_time_limit(300);
@ignore_user_abort(false);

$sql = "SELECT id, dateipfad, dateiname, original_name, titel, status, tag_index, tag_datum, aufnahme_zeit
          FROM anlass_fotos WHERE galerie_id = :gid" . ($alle ? '' : " AND status = 'approved'")
     . " ORDER BY " . fotoOrderBySql();
$st = $db->prepare($sql);
$st->execute([':gid' => $gid]);
$fotos = $st->fetchAll();
if (!$fotos) { http_response_code(404); die('Keine Fotos in dieser Galerie'); }

$segMap = [];
foreach (fotoGalerieSegmente($g) as $s) $segMap[$s['index']] = $s['datum'];

/** Dateisystem-sicherer Name (ASCII, keine Sonderzeichen). */
$safe = static function (string $s, int $max = 60): string {
    $s = str_replace(['ä','ö','ü','Ä','Ö','Ü','ß'], ['ae','oe','ue','Ae','Oe','Ue','ss'], $s);
    $s = preg_replace('/[^A-Za-z0-9._-]+/', '_', $s);
    $s = trim((string) $s, '_.');
    return $s === '' ? 'x' : substr($s, 0, $max);
};

$root = $safe('Fotos_' . fotoGalerieLabel($g));

$tmp = tempnam(sys_get_temp_dir(), 'fotozip_');
if ($tmp === false) { http_response_code(500); die('Temp-Datei konnte nicht angelegt werden'); }
register_shutdown_function(static function () use ($tmp) { if (is_file($tmp)) @unlink($tmp); });

$zip = new ZipArchive();
// CREATE|OVERWRITE: OVERWRITE allein scheitert mit dem libzip auf Prod an der leeren tempnam-Datei
if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { http_response_code(500); die('ZIP konnte nicht erstellt werden'); }

$pos = 0;
$n   = 0;
foreach ($fotos as $f) {
    $pfad = (string) $f['dateipfad'];
    if ($pfad === '' || !is_file($pfad) || !fotoPfadErlaubt($pfad)) continue;
    $pos++;

    if ($f['status'] === 'pending')       $ordner = '_wartend';
    elseif ($f['status'] === 'rejected')  $ordner = '_abgelehnt';
    elseif ($f['tag_index'] !== null)     $ordner = sprintf('Tag%02d_%s', (int) $f['tag_index'], $segMap[(int) $f['tag_index']] ?? (string) $f['tag_datum']);
    elseif ($f['tag_datum'] !== null)     $ordner = (string) $f['tag_datum'];
    else                                  $ordner = 'Weitere_Fotos';

    $basis = $f['titel'] ? $f['titel'] : pathinfo((string) ($f['original_name'] ?: $f['dateiname']), PATHINFO_FILENAME);
    $name  = sprintf('%03d_%s.jpg', $pos, $safe((string) $basis, 50));

    $zip->addFile($pfad, $root . '/' . $safe($ordner) . '/' . $name);
    $zip->setCompressionIndex($zip->numFiles - 1, ZipArchive::CM_STORE);
    $n++;
}
$zip->close();

if ($n === 0 || !is_file($tmp) || filesize($tmp) < 100) { http_response_code(404); die('Keine Dateien gefunden'); }

header('Content-Type: application/zip');
header('Content-Length: ' . filesize($tmp));
header('Content-Disposition: attachment; filename="' . $root . '.zip"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($tmp);
exit;
