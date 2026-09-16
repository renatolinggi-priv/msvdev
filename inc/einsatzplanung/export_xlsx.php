<?php
/**
 * inc/einsatzplanung/export_xlsx.php – Excel-Export (nur Layout Personen × Schichten / Chilbi).
 * GET plan_id → {success, link: 'dat/<Titel>_YYYY-MM-DD_HH-MM-SS.xlsx'}
 */
require_once __DIR__ . '/../config.php';          // dat-Cleanup-Hook
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/plan_helpers.inc.php';
require_once __DIR__ . '/xlsx_builder.inc.php';

header('Content-Type: application/json; charset=utf-8');
$db     = getDB();
$planId = (int)($_GET['plan_id'] ?? $_POST['plan_id'] ?? 0);
$plan   = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);
if ($plan['layout'] !== 'person_x_schicht') ep_json(['success' => false, 'message' => 'Excel gibt es nur für das Chilbi-Raster (Personen × Schichten)'], 422);

try {
    $datDir = __DIR__ . '/dat';
    if (!is_dir($datDir)) mkdir($datDir, 0775, true);
    $datei = ep_dateistamm($plan) . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    ep_xlsx_erzeugen($plan, ep_mitglieder_map($db), $datDir . '/' . $datei);
    ep_json(['success' => true, 'link' => 'dat/' . $datei, 'message' => 'Excel erstellt']);
} catch (Throwable $e) {
    error_log('[einsatzplanung/export_xlsx] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Excel konnte nicht erstellt werden: ' . $e->getMessage()], 500);
}
