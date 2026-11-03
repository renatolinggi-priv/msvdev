<?php
/**
 * inc/drucksteuerung/qz_setup.php — Setup «MSV Wilen Direktdruck» (QZ Tray + Zertifikat) ausliefern (seit 1.68.54)
 *
 * Die Setups liegen ausserhalb des Docroots in ~/www/msv_downloads/ (neben qz_certs/), gebaut mit
 * C:\TEMP\msvjm\installer\baue.ps1 und per scp hochgeladen (der SFTP-Watcher lädt sie nicht). Ausgeliefert wird
 * die höchste Version von MSV-QZ-Setup-<Version>.exe, nur für Admin und Vorstand.
 *
 * GET            -> die Datei (Download)
 * GET ?info=1    -> JSON { success, name, version, mb, datum } für die Drucksteuerung
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
$nurInfo = isset($_GET['info']);
adminApiGuard($nurInfo ? 'json' : 'plain');

$ordner = dirname(__DIR__, 3) . '/msv_downloads';
$beste = null;
$besteVersion = '0';
foreach (glob($ordner . '/MSV-QZ-Setup-*.exe') ?: [] as $datei) {
    if (preg_match('/MSV-QZ-Setup-(\d+(?:\.\d+){1,3})\.exe$/', $datei, $m) && version_compare($m[1], $besteVersion, '>')) {
        $beste = $datei;
        $besteVersion = $m[1];
    }
}

if ($nurInfo) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$beste) {
        echo json_encode(['success' => false, 'message' => 'Das Setup ist noch nicht hochgeladen.']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'name'    => basename($beste),
        'version' => $besteVersion,
        'mb'      => round(filesize($beste) / 1048576),
        'datum'   => date('d.m.Y', filemtime($beste)),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$beste || !is_readable($beste)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Das Setup ist noch nicht auf dem Server. Bitte beim Administrator melden.';
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/vnd.microsoft.portable-executable');
header('Content-Disposition: attachment; filename="' . basename($beste) . '"');
header('Content-Length: ' . filesize($beste));
header('Cache-Control: private, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($beste);
exit;
