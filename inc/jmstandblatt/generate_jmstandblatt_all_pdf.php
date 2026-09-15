<?php
/**
 * Generiert ein kombiniertes PDF mit allen JM-Standblaettern (aktive Mitglieder).
 * Einzelne DOCXs werden ueber den PDF-Dienst konvertiert und mit FPDI zusammengefuegt.
 * GET-Parameter: jahr
 * Antwort-Header: X-Skipped (Anzahl) und X-Skipped-Names (URL-codiert, "; "-getrennt)
 * fuer Mitglieder, deren Standblatt nicht erzeugt werden konnte.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(600);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('plain'); // ein API-Aufruf pro Mitglied -> nie anonym erreichbar lassen
require_once __DIR__ . '/../lib/convertapi_helper.php';
require_once __DIR__ . '/standblatt_common.inc.php';

use setasign\Fpdi\Fpdi;

$jahr = (int)($_GET['jahr'] ?? date('Y'));

if (!isset($conn)) {
    http_response_code(500);
    echo 'Datenbankverbindung fehlt';
    exit;
}
if (!file_exists(sb_template_path())) {
    http_response_code(404);
    echo 'Vorlage nicht gefunden';
    exit;
}

$result = $conn->query("SELECT ID, Vorname, Name FROM mitglieder WHERE Status = 1 AND Verstorben = 0 ORDER BY Name, Vorname");
if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    echo 'Keine aktiven Mitglieder gefunden';
    exit;
}

$pdfFiles = [];
$errors   = [];
while ($m = $result->fetch_assoc()) {
    $tmpDocx = null;
    try {
        $tmpDocx = sb_standblatt_docx((int)$m['ID'], $m['Vorname'], $m['Name'], $jahr, 'jpg');
        $pdfFiles[] = convertDocxToPdf($tmpDocx);
    } catch (Throwable $e) {
        error_log("[jmstandblatt_all_pdf] Fehler bei {$m['Vorname']} {$m['Name']}: " . $e->getMessage());
        $errors[] = $m['Vorname'] . ' ' . $m['Name'];
    } finally {
        if ($tmpDocx && file_exists($tmpDocx)) unlink($tmpDocx);
    }
}

if (empty($pdfFiles)) {
    http_response_code(500);
    echo 'Keine PDFs konnten generiert werden';
    exit;
}

$mergedPdf = null;
try {
    $merger = new Fpdi();
    $merger->SetAutoPageBreak(false);
    foreach ($pdfFiles as $pdfFile) {
        $pageCount = $merger->setSourceFile($pdfFile);
        for ($p = 1; $p <= $pageCount; $p++) {
            $tplId = $merger->importPage($p);
            $size  = $merger->getTemplateSize($tplId);
            $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $merger->useTemplate($tplId);
        }
    }

    $mergedPdf = tempnam(sys_get_temp_dir(), 'merged_');
    rename($mergedPdf, $mergedPdf . '.pdf'); $mergedPdf .= '.pdf';
    $merger->Output('F', $mergedPdf);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="JM_Standblaetter_' . $jahr . '_alle.pdf"');
    header('Content-Length: ' . filesize($mergedPdf));
    header('Cache-Control: max-age=0');
    if ($errors) {
        header('X-Skipped: ' . count($errors));
        header('X-Skipped-Names: ' . rawurlencode(implode('; ', $errors)));
    }
    readfile($mergedPdf);
} catch (Throwable $e) {
    error_log('[jmstandblatt_all_pdf] Merge-Fehler: ' . $e->getMessage());
    http_response_code(500);
    echo 'PDF-Zusammenführung fehlgeschlagen: ' . $e->getMessage();
} finally {
    if ($mergedPdf && file_exists($mergedPdf)) unlink($mergedPdf);
    foreach ($pdfFiles as $f) {
        if (file_exists($f)) unlink($f);
    }
}
exit;
