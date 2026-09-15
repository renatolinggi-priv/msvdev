<?php
/**
 * Generiert ein JM-Standblatt (Word) aus der Vorlage.
 * GET-Parameter: jahr, mitglied_id
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('plain');
require_once __DIR__ . '/standblatt_common.inc.php';

$jahr        = (int)($_GET['jahr'] ?? date('Y'));
$mitglied_id = (int)($_GET['mitglied_id'] ?? 0);

if ($mitglied_id <= 0 || !isset($conn)) {
    http_response_code(400);
    echo 'Ungültige Parameter';
    exit;
}

$row = sb_load_mitglied($conn, $mitglied_id);
if (!$row) {
    http_response_code(404);
    echo 'Mitglied nicht gefunden';
    exit;
}

try {
    $tmpDocx = sb_standblatt_docx((int)$row['ID'], $row['Vorname'], $row['Name'], $jahr, 'png');
} catch (Throwable $e) {
    error_log('[jmstandblatt] ' . $e->getMessage());
    http_response_code(500);
    echo 'Standblatt konnte nicht erstellt werden';
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . sb_dateiname($row['Vorname'], $row['Name'], $jahr, 'docx') . '"');
header('Content-Length: ' . filesize($tmpDocx));
header('Cache-Control: max-age=0');
readfile($tmpDocx);
unlink($tmpDocx);
exit;
