<?php
/**
 * inc/einsatzplanung/rueckmeldung_save.php – ausgewählte Vorschläge aus rueckmeldung_parse.php übernehmen.
 *
 * POST plan_id, aenderungen = JSON [{slot_id, name_text}]
 *   Fremdvereins-Slot: name_text setzen (leer = wieder Platzhalter), mitglied_id bleibt NULL.
 *   MSV-Slot:          Name gegen die Mitglieder matchen (name_matcher.php); Treffer → mitglied_id,
 *                      sonst Klartext (externer Helfer). Leer = Position leeren.
 * Antwort: {success, message, uebernommen}
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/plan_helpers.inc.php';
require_once __DIR__ . '/../einsatzplan_parser/name_matcher.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db     = getDB();
$planId = (int)($_POST['plan_id'] ?? 0);
$plan   = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);

$aenderungen = json_decode((string)($_POST['aenderungen'] ?? '[]'), true);
if (!is_array($aenderungen) || !$aenderungen) ep_json(['success' => false, 'message' => 'Keine Änderungen übergeben'], 422);

$slots = []; foreach ($plan['slots'] as $s) $slots[(int)$s['id']] = $s;

try {
    $db->beginTransaction();
    $upd = $db->prepare("UPDATE einsatz_plan_slots SET mitglied_id = ?, name_text = ? WHERE id = ? AND plan_id = ?");
    $n = 0;
    foreach ($aenderungen as $a) {
        $sid  = (int)($a['slot_id'] ?? 0);
        $text = mb_substr(trim((string)($a['name_text'] ?? '')), 0, 100);
        if (!isset($slots[$sid])) continue;
        $s = $slots[$sid];
        $mid = null; $nameText = $text !== '' ? $text : null;
        if ($s['verein'] === 'msv' && $text !== '') {
            $m = matchMitglieder($db, [['mitglied_name' => $text]]);
            if (!empty($m[0]['mitglied_id']) && ($m[0]['match_status'] ?? '') === 'exact') { $mid = (int)$m[0]['mitglied_id']; $nameText = null; }
        }
        $upd->execute([$mid, $nameText, $sid, $planId]);
        $n += $upd->rowCount();
    }
    $db->commit();
    ep_projizieren_wenn_freigegeben($db, $planId);
    ep_json(['success' => true, 'uebernommen' => $n, 'message' => $n . ' Position(en) übernommen']);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[einsatzplanung/rueckmeldung_save] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Rückmeldung konnte nicht gespeichert werden'], 500);
}
