<?php
/**
 * inc/einsatzplanung/einteilung_vorschlag.php – automatische Einteilung als Vorschlag.
 *
 * POST action=berechnen   plan_id, [kontinuitaet=1], [verein_beachten=0], [verwerfen=1]
 *                         → bestehende Vorschläge (optional) verwerfen, ep_einteilung_berechnen(), Vorschläge als
 *                           Slots mit vorschlag = 1 schreiben; Antwort mit Zusammenfassung
 * POST action=uebernehmen plan_id, [slot_ids = JSON]      → vorschlag = 0 (alle oder ausgewählte), Projektion
 * POST action=verwerfen   plan_id, [slot_ids = JSON]      → Vorschlags-Positionen wieder leeren
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/plan_helpers.inc.php';
require_once __DIR__ . '/einteilung.inc.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db     = getDB();
$action = $_POST['action'] ?? '';
$planId = (int)($_POST['plan_id'] ?? 0);
$plan   = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);

$slotIds = json_decode((string)($_POST['slot_ids'] ?? '[]'), true);
$slotIds = is_array($slotIds) ? array_values(array_filter(array_map('intval', $slotIds))) : [];

try {
    if ($action === 'berechnen') {
        if ($plan['layout'] !== 'funktion_x_termin') ep_json(['success' => false, 'message' => 'Automatische Einteilung gibt es nur im Raster Funktionen × Termine'], 422);
        $db->beginTransaction();
        if (!empty($_POST['verwerfen'])) {
            $db->prepare("UPDATE einsatz_plan_slots SET mitglied_id = NULL, name_text = NULL, vorschlag = 0 WHERE plan_id = ? AND vorschlag = 1")->execute([$planId]);
            $plan = ep_plan_laden($db, $planId);
        }
        $erg = ep_einteilung_berechnen($plan, $plan['verfuegbarkeit'], [
            'kontinuitaet'    => !isset($_POST['kontinuitaet']) || !empty($_POST['kontinuitaet']),
            'verein_beachten' => !empty($_POST['verein_beachten']),
        ]);
        $upd = $db->prepare("UPDATE einsatz_plan_slots SET verein = ?, mitglied_id = ?, name_text = ?, vorschlag = 1 WHERE id = ? AND plan_id = ?");
        foreach ($erg['vorschlaege'] as $v) $upd->execute([$v['verein'], $v['mitglied_id'], $v['name_text'], $v['slot_id'], $planId]);
        $db->commit();
        ep_json(['success' => true, 'message' => count($erg['vorschlaege']) . ' Vorschlag/Vorschläge, ' . count($erg['offen']) . ' offen, ' . count($erg['ungenutzt']) . ' ungenutzt'] + $erg);
    }

    if ($action === 'uebernehmen') {
        $sql = "UPDATE einsatz_plan_slots SET vorschlag = 0 WHERE plan_id = ? AND vorschlag = 1";
        $params = [$planId];
        if ($slotIds) { $sql .= " AND id IN (" . implode(',', array_fill(0, count($slotIds), '?')) . ")"; $params = array_merge($params, $slotIds); }
        $st = $db->prepare($sql); $st->execute($params);
        ep_projizieren_wenn_freigegeben($db, $planId);
        ep_json(['success' => true, 'message' => $st->rowCount() . ' Vorschlag/Vorschläge übernommen', 'anzahl' => $st->rowCount()]);
    }

    if ($action === 'verwerfen') {
        $sql = "UPDATE einsatz_plan_slots SET mitglied_id = NULL, name_text = NULL, vorschlag = 0 WHERE plan_id = ? AND vorschlag = 1";
        $params = [$planId];
        if ($slotIds) { $sql .= " AND id IN (" . implode(',', array_fill(0, count($slotIds), '?')) . ")"; $params = array_merge($params, $slotIds); }
        $st = $db->prepare($sql); $st->execute($params);
        ep_json(['success' => true, 'message' => $st->rowCount() . ' Vorschlag/Vorschläge verworfen', 'anzahl' => $st->rowCount()]);
    }

    ep_json(['success' => false, 'message' => 'Unbekannte Aktion'], 400);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[einsatzplanung/einteilung_vorschlag] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Einteilung fehlgeschlagen: ' . $e->getMessage()], 500);
}
