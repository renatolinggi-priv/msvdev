<?php
/**
 * inc/einsatzplanung/plan_delete.php – Plan samt Termine/Funktionen/Slots löschen.
 * Projizierte Zeilen in einsatz_zuweisungen werden mit entfernt (Tausch-Historie bleibt als Audit).
 * POST plan_id
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/plan_helpers.inc.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db = getDB();
$planId = (int)($_POST['plan_id'] ?? 0);
$plan = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);

try {
    $db->beginTransaction();
    $db->prepare("DELETE z FROM einsatz_zuweisungen z JOIN einsatz_plan_slots s ON s.id = z.slot_id WHERE s.plan_id = ?")->execute([$planId]);
    $db->prepare("DELETE FROM einsatz_plan_slots WHERE plan_id = ?")->execute([$planId]);
    $db->prepare("DELETE FROM einsatz_plan_funktionen WHERE plan_id = ?")->execute([$planId]);
    $db->prepare("DELETE FROM einsatz_plan_termine WHERE plan_id = ?")->execute([$planId]);
    $db->prepare("DELETE FROM einsatz_plaene WHERE id = ?")->execute([$planId]);
    $db->commit();
    ep_json(['success' => true, 'message' => 'Plan «' . $plan['titel'] . '» gelöscht']);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[einsatzplanung/plan_delete] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Plan konnte nicht gelöscht werden'], 500);
}
