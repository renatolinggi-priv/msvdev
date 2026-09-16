<?php
/**
 * inc/einsatzplanung/export_docx.php – Word-Export eines Plans nach dat/ (Zeitstempel, zentrale Aufräumung).
 * GET plan_id → {success, link: 'dat/<Titel>_<Jahr>_YYYY-MM-DD_HH-MM-SS.docx'}
 */
require_once __DIR__ . '/../config.php';          // dat-Cleanup-Hook
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/plan_helpers.inc.php';
require_once __DIR__ . '/docx_builder.inc.php';

header('Content-Type: application/json; charset=utf-8');
$db     = getDB();
$planId = (int)($_GET['plan_id'] ?? $_POST['plan_id'] ?? 0);
$plan   = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);

try {
    $datDir = __DIR__ . '/dat';
    if (!is_dir($datDir)) mkdir($datDir, 0775, true);
    // ansicht=funktionen|personen (leer = Standard des Layouts: Chilbi → Personen, sonst Funktionen)
    $ansicht = in_array($_GET['ansicht'] ?? '', ['funktionen', 'personen'], true) ? $_GET['ansicht'] : ep_docx_standard_ansicht($plan);
    $datei = ep_dateistamm($plan) . ($ansicht !== ep_docx_standard_ansicht($plan) ? '_' . ucfirst($ansicht) : '') . '_' . date('Y-m-d_H-i-s') . '.docx';
    ep_docx_erzeugen($plan, ep_mitglieder_map($db), $datDir . '/' . $datei, $ansicht);
    ep_json(['success' => true, 'link' => 'dat/' . $datei, 'message' => 'Word erstellt']);
} catch (Throwable $e) {
    error_log('[einsatzplanung/export_docx] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Word konnte nicht erstellt werden: ' . $e->getMessage()], 500);
}
