<?php
/**
 * inc/einsatzplanung/export_pdf.php – PDF-Export: Word erzeugen, über inc/lib/convertapi_helper.php
 * (iLoveAPI / ConvertAPI) nach PDF wandeln, in dat/ ablegen.
 *
 * GET  plan_id                      → {success, link: 'dat/….pdf'}
 * POST plan_id, ablegen=1 (+CSRF)   → zusätzlich als Dokument im Portal ablegen
 *                                     (vorstand_dokumente, typ einsatzplan, sichtbar alle_mitglieder)
 */
require_once __DIR__ . '/../config.php';          // dat-Cleanup-Hook
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/plan_helpers.inc.php';
require_once __DIR__ . '/docx_builder.inc.php';
require_once __DIR__ . '/../lib/convertapi_helper.php';

header('Content-Type: application/json; charset=utf-8');
$db      = getDB();
$planId  = (int)($_GET['plan_id'] ?? $_POST['plan_id'] ?? 0);
$ablegen = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ablegen']);
if ($ablegen) csrf_require(true);

$plan = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);

$tmpDocx = null; $tmpPdf = null;
try {
    $datDir = __DIR__ . '/dat';
    if (!is_dir($datDir)) mkdir($datDir, 0775, true);
    $ansichtParam = $_GET['ansicht'] ?? $_POST['ansicht'] ?? '';
    $ansicht = in_array($ansichtParam, ['funktionen', 'personen'], true) ? $ansichtParam : ep_docx_standard_ansicht($plan);
    $stamm = ep_dateistamm($plan) . ($ansicht !== ep_docx_standard_ansicht($plan) ? '_' . ucfirst($ansicht) : '') . '_' . date('Y-m-d_H-i-s');

    $tmpDocx = tempnam(sys_get_temp_dir(), 'einsatzplan_');
    rename($tmpDocx, $tmpDocx . '.docx'); $tmpDocx .= '.docx';
    ep_docx_erzeugen($plan, ep_mitglieder_map($db), $tmpDocx, $ansicht);

    $tmpPdf = convertToPdf($tmpDocx, 'docx');
    $ziel = $datDir . '/' . $stamm . '.pdf';
    if (!copy($tmpPdf, $ziel)) throw new RuntimeException('PDF konnte nicht abgelegt werden');

    $antwort = ['success' => true, 'link' => 'dat/' . $stamm . '.pdf', 'message' => 'PDF erstellt'];

    if ($ablegen) {
        require_once __DIR__ . '/../lib/dokument_datei.inc.php';
        $uploadDir = __DIR__ . '/../../portal/uploads/dokumente/einsatzplan/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        $dokName = dokument_zielname($plan['titel'] . '.pdf', 'pdf');
        $dokPfad = $uploadDir . $dokName;
        if (!copy($ziel, $dokPfad)) throw new RuntimeException('Dokument konnte nicht ins Portal kopiert werden');
        $ersterTermin = $plan['termine'][0]['datum'] ?? null;
        $db->prepare("INSERT INTO vorstand_dokumente (typ, titel, beschreibung, dateiname, dateipfad, dateigroesse, hochgeladen_von, sichtbar_fuer, datum, jahr)
                      VALUES ('einsatzplan', ?, ?, ?, ?, ?, ?, 'alle_mitglieder', ?, ?)")
           ->execute([mb_substr($plan['titel'], 0, 255), 'Aus der Einsatzplanung erzeugt (' . date('d.m.Y H:i') . ')', ep_dateistamm($plan) . '.pdf',
                      realpath($dokPfad) ?: $dokPfad, filesize($dokPfad), (int)($_SESSION['user_id'] ?? 0), $ersterTermin, (int)$plan['jahr']]);
        $antwort['message'] = 'PDF erstellt und als Dokument «' . $plan['titel'] . '» im Portal abgelegt';
        $antwort['dokument_id'] = (int)$db->lastInsertId();
    }
    ep_json($antwort);
} catch (Throwable $e) {
    error_log('[einsatzplanung/export_pdf] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'PDF konnte nicht erstellt werden: ' . $e->getMessage()], 500);
} finally {
    if ($tmpDocx && file_exists($tmpDocx)) @unlink($tmpDocx);
    if ($tmpPdf && file_exists($tmpPdf)) @unlink($tmpPdf);
}
