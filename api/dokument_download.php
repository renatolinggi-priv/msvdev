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

// Rollentrennung: Jungschuetzen haben nur Zugriff auf JSK-Inhalte. Die Allowlist in
// portal/portal_header.php greift ausschliesslich auf Seiten, die den Header einbinden --
// ohne diese Pruefung koennte ein Jungschuetze Protokolle und Einsatzplaene mit
// sichtbar_fuer='alle_mitglieder' per ?id=N abrufen. Analog zu api/foto_serve.php.
if (isJungschuetze() && ($doc['typ'] ?? '') !== 'jsk') {
    http_response_code(403);
    die('Zugriff verweigert');
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
// Dateiname header-sicher: ASCII-Fallback ohne Anfuehrungszeichen + UTF-8-Variante (RFC 5987)
$origName  = basename((string)$doc['dateiname']);
$asciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $origName) ?: 'dokument';
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($origName));
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');

readfile($filepath);
exit;
