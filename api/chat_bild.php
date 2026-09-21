<?php
// api/chat_bild.php – liefert Chat-Bilder mit Berechtigungspruefung.
//   ?id=<nachrichtId>&s=t|f   -> Thumbnail (t) oder Vollbild (f)
// Direktzugriff auf portal/uploads/ ist per .htaccess gesperrt; Auslieferung nur hier.
// Zugriff: Teilnehmer der Konversation sowie Leitung mit aktivierter Einsicht (chatAccessLevel).

require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/chat.inc.php';
requireLogin();

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$id     = (int) ($_GET['id'] ?? 0);
$thumb  = (($_GET['s'] ?? 't') !== 'f');

if ($id <= 0 || !chatBilderAktiv($db)) { http_response_code(404); die('Nicht gefunden'); }

$geloescht = jskDbHatSpalte($db, 'chat_nachrichten', 'geloescht_am') ? 'geloescht_am' : 'NULL AS geloescht_am';
$st = $db->prepare("SELECT conversation_id, bild, $geloescht FROM chat_nachrichten WHERE id = ?");
$st->execute([$id]);
$m = $st->fetch();
if (!$m || empty($m['bild']) || !empty($m['geloescht_am'])) { http_response_code(404); die('Nicht gefunden'); }

$conv = chatGetConversation($db, (int) $m['conversation_id']);
if (!$conv || chatAccessLevel($db, $conv, $userId) === '') { http_response_code(403); die('Zugriff verweigert'); }

// Session-Lock frueh freigeben: ein Thread laedt mehrere Bilder parallel (siehe foto_serve.php)
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

$pfad = chatBildPfad((int) $m['conversation_id'], (string) $m['bild'], $thumb);
if (!chatBildPfadErlaubt($pfad) || !is_file($pfad)) { http_response_code(404); die('Datei nicht gefunden'); }

$mtime = filemtime($pfad);
$etag  = '"' . md5($pfad . '|' . $mtime) . '"';
$cache = 'private, max-age=31536000, immutable';   // eine Nachrichten-ID zeigt immer dasselbe Bild
if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    header('ETag: ' . $etag);
    header('Cache-Control: ' . $cache);
    http_response_code(304);
    exit;
}
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($pfad));
header('Content-Disposition: inline; filename="' . basename($pfad) . '"');
header('Cache-Control: ' . $cache);
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
readfile($pfad);
exit;
