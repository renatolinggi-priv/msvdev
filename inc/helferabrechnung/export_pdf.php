<?php
/**
 * inc/helferabrechnung/export_pdf.php – PDF «Helferabrechnung» (nur Abrechnungsblatt, A4 quer, Dompdf).
 * Beilage für die OK-Sitzung und zum Versand an die Partnervereine. HTML: pdf_builder.inc.php.
 *
 * GET plan_id, ok=0|1 [, orientation] → {success, link: 'dat/Helferabrechnung_<Jahr>_YYYY-MM-DD_HH-MM-SS.pdf'}
 * Muster: cuprang/generate_cup_pdf.php (ob_start, pdf_theme, pdfOrientationParam).
 */
use Dompdf\Dompdf;
use Dompdf\Options;

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../config.php';          // dat-Cleanup-Hook
    require_once __DIR__ . '/../dbconnect.inc.php';
    require_once __DIR__ . '/../admin_api_guard.inc.php';
    adminApiGuard('json');
    require_once __DIR__ . '/../pdf/pdf_orientation.inc.php';
    require_once __DIR__ . '/pdf_builder.inc.php';

    $db     = getDB();
    $planId = (int)($_GET['plan_id'] ?? 0);
    $ok     = (int)($_GET['ok'] ?? 0) === 1;
    $plan   = ha_plan_waehlen($db, 0, $planId);
    if (!$plan) throw new RuntimeException('Schlossturm-Plan nicht gefunden');
    $a = ha_abrechnung($db, $plan, $ok);
    $orientation = pdfOrientationParam('landscape');

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml(ha_pdf_html($a, $orientation));
    $dompdf->setPaper('A4', $orientation);   // zusätzlich zu @page im CSS (Dompdf: CSS gewinnt)
    $dompdf->render();

    $datDir = __DIR__ . '/dat';
    if (!is_dir($datDir) && !mkdir($datDir, 0775, true)) throw new RuntimeException('Verzeichnis dat/ konnte nicht angelegt werden');
    $datei = 'Helferabrechnung_' . (int)$plan['jahr'] . ($ok ? '_mitOK' : '') . '_' . date('Y-m-d_H-i-s') . '.pdf';
    if (file_put_contents($datDir . '/' . $datei, $dompdf->output()) === false) throw new RuntimeException('PDF konnte nicht gespeichert werden');

    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'link' => 'dat/' . $datei, 'message' => 'PDF erstellt']);
} catch (Throwable $e) {
    ob_end_clean();
    error_log('[helferabrechnung/export_pdf] ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'PDF konnte nicht erstellt werden: ' . $e->getMessage()]);
}
