<?php
/**
 * Generiert ein JM-Standblatt als PDF (Download und QZ-Tray-Direktdruck).
 * Seit 1.68.49 direkt mit Dompdf (standblatt_pdf.inc.php), ohne Word-Vorlage und PDF-Dienst.
 * GET-Parameter: jahr, mitglied_id
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('plain');
require_once __DIR__ . '/../pdf/pdf_theme.php';
require_once __DIR__ . '/standblatt_pdf.inc.php';

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
    $person = sb_pdf_person($row, $jahr, sb_pdf_stichnummern($conn), sb_pdf_waffen($conn));
    $pdf = sb_pdf_rendern(sb_pdf_dokument([$person], $jahr));
    sb_pdf_senden($pdf, sb_dateiname($row['Vorname'], $row['Name'], $jahr, 'pdf'));
} catch (Throwable $e) {
    error_log('[jmstandblatt_pdf] Fehler: ' . $e->getMessage());
    http_response_code(500);
    echo msvFehler('Das Standblatt konnte nicht erstellt werden. Bitte nochmals versuchen.', $e);
}
exit;
