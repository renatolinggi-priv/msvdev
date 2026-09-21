<?php
/**
 * inc/einsatzplanung/ok_person_save.php – OK-Kennzeichen (Organisationskomitee) für eine Person im ganzen Plan setzen.
 *
 * OK ist eine Eigenschaft der Person am Anlass (im Original steht «OK» statt «x» bei allen ihren Positionen),
 * darum werden alle Slots der Person im Plan gemeinsam gesetzt (Migration 058, slots.ok).
 * POST plan_id, ok=0|1, mitglied_id (>0) ODER name_text (Externe/Fremdvereine)
 * Antwort: {success, message, ok, slot_ids:[…]}
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/plan_helpers.inc.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db       = getDB();
$planId   = (int)($_POST['plan_id'] ?? 0);
$ok       = (int)($_POST['ok'] ?? 0) === 1 ? 1 : 0;
$mid      = (int)($_POST['mitglied_id'] ?? 0);
$nameText = trim((string)($_POST['name_text'] ?? ''));
if ($mid <= 0 && $nameText === '') ep_json(['success' => false, 'message' => 'Person fehlt'], 422);

try {
    if ($mid > 0) {
        $st = $db->prepare("SELECT id FROM einsatz_plan_slots WHERE plan_id = ? AND mitglied_id = ?");
        $st->execute([$planId, $mid]);
    } else {
        $st = $db->prepare("SELECT id FROM einsatz_plan_slots WHERE plan_id = ? AND mitglied_id IS NULL AND name_text = ?");
        $st->execute([$planId, $nameText]);
    }
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) ep_json(['success' => false, 'message' => 'Keine Positionen dieser Person im Plan'], 404);
    $db->prepare("UPDATE einsatz_plan_slots SET ok = ? WHERE id IN (" . implode(',', $ids) . ")")->execute([$ok]);
    ep_json(['success' => true, 'message' => ($ok ? 'OK gesetzt' : 'OK entfernt') . ' (' . count($ids) . ' Position' . (count($ids) === 1 ? '' : 'en') . ')', 'ok' => $ok, 'slot_ids' => $ids]);
} catch (Throwable $e) {
    error_log('[einsatzplanung/ok_person_save] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Speichern fehlgeschlagen (Migration 058 ausgeführt?)'], 500);
}
