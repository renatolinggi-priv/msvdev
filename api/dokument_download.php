<?php
// api/dokument_download.php - Datei-Download mit Berechtigungspruefung
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();

$doc_id = intval($_GET['id'] ?? 0);
if ($doc_id < 1) {
    http_response_code(400);
    die('Ungültige Dokument-ID');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM vorstand_dokumente WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die('Dokument nicht gefunden');
}

// Berechtigungspruefung (fail-closed: nur bekannte Sichtbarkeits-Werte freigeben)
$user_role = $_SESSION['user_role'] ?? 'mitglied';
$visible = false;
switch ($doc['sichtbar_fuer']) {
    case 'alle_mitglieder':
        $visible = true;
        break;
    case 'vorstand':
        $visible = in_array($user_role, ['admin', 'vorstand']);
        break;
    case 'admin':
        $visible = ($user_role === 'admin');
        break;
}
if (!$visible) {
    http_response_code(403);
    die('Zugriff verweigert');
}

// Datei ausliefern
$filepath = $doc['dateipfad'];
if (!file_exists($filepath)) {
    http_response_code(404);
    die('Datei nicht gefunden');
}

// MIME-Type ermitteln
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filepath);
finfo_close($finfo);

$disposition = isset($_GET['force_download']) ? 'attachment' : 'inline';
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($doc['dateiname']) . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');

readfile($filepath);
exit;
