<?php
/**
 * inc/helferabrechnung/zeile_delete.php – manuelle Abrechnungszeile löschen.
 * POST id, plan_id → {success, message}
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/abrechnung.inc.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db     = getDB();
$id     = (int)($_POST['id'] ?? 0);
$planId = (int)($_POST['plan_id'] ?? 0);

try {
    $st = $db->prepare("DELETE FROM einsatz_abr_zeilen WHERE id = ? AND plan_id = ?");
    $st->execute([$id, $planId]);
    if ($st->rowCount() === 0) ep_json(['success' => false, 'message' => 'Zeile nicht gefunden'], 404);
} catch (Throwable $e) {
    error_log('[helferabrechnung/zeile_delete] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Löschen fehlgeschlagen'], 500);
}
ep_json(['success' => true, 'message' => 'Zeile gelöscht']);
