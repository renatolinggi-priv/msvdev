<?php
/**
 * inc/helferabrechnung/export_xlsx.php – Excel-Export der Helferabrechnung (Arbeitsmappe des Benutzers als Vorlage,
 * dat/Helferabrechnung_Vorlage.xlsx – Formeln bleiben, nur Datenbereiche werden gefüllt; siehe xlsx_builder.inc.php).
 * GET plan_id, ok=0|1 → {success, link: 'dat/Helferabrechnung_<Jahr>_YYYY-MM-DD_HH-MM-SS.xlsx'}
 */
require_once __DIR__ . '/../config.php';          // dat-Cleanup-Hook (zentral)
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/abrechnung.inc.php';
require_once __DIR__ . '/xlsx_builder.inc.php';

header('Content-Type: application/json; charset=utf-8');
$db     = getDB();
$planId = (int)($_GET['plan_id'] ?? $_POST['plan_id'] ?? 0);
$ok     = (int)($_GET['ok'] ?? $_POST['ok'] ?? 0) === 1;

try {
    $plan = ha_plan_waehlen($db, 0, $planId);
    if (!$plan) ep_json(['success' => false, 'message' => 'Schlossturm-Plan nicht gefunden'], 404);
    $a = ha_abrechnung($db, $plan, $ok);
    $datDir = __DIR__ . '/dat';
    if (!is_dir($datDir)) mkdir($datDir, 0775, true);
    $datei = 'Helferabrechnung_' . (int)$plan['jahr'] . ($ok ? '_mitOK' : '') . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    ha_xlsx_erzeugen($a, $datDir . '/' . $datei);
    ep_json(['success' => true, 'link' => 'dat/' . $datei, 'message' => 'Excel erstellt']);
} catch (Throwable $e) {
    error_log('[helferabrechnung/export_xlsx] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Excel konnte nicht erstellt werden: ' . $e->getMessage()], 500);
}
