<?php
/**
 * inc/einsatzplanung/chilbi_zelle_save.php – Positionen im Layout person_x_schicht (Wiler Chilbi).
 * Seit dem Raster-Umbau (16.09.2026) sieht die Chilbi aus wie Obli/Feld: Funktionen × Schichten mit
 * Personen-Chips. Positionen entstehen und verschwinden dynamisch (kein fixes «anzahl»).
 *
 * POST action=add            plan_id, termin_id, funktion_id, mitglied_id | name_text, [bemerkung]
 *                            → neue Position (pos = max+1); Antwort wie slot_save.php: slot{…}
 * POST action=delete         plan_id, slot_id                              → Position löschen
 * POST action=set            plan_id, termin_id, mitglied_id | name_text, funktion_ids[]  (Altansicht, ersetzt
 *                            die Positionen der Person in dieser Schicht; leer = entfernen)
 * POST action=remove_person  plan_id, mitglied_id | name_text                          → alle Positionen der Person
 * Antwort: {success, message, …, besetzung, fehlt}
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/plan_helpers.inc.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db     = getDB();
$action = $_POST['action'] ?? 'set';
$planId = (int)($_POST['plan_id'] ?? 0);
$plan   = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan || $plan['layout'] !== 'person_x_schicht') ep_json(['success' => false, 'message' => 'Plan nicht gefunden oder falsches Layout'], 404);

$mitglieder = ep_mitglieder_map($db);
$funktionen = []; foreach ($plan['funktionen'] as $f) $funktionen[(int)$f['id']] = $f;
$termine    = []; foreach ($plan['termine'] as $t) $termine[(int)$t['id']] = $t;

/** Besetzung einer Schicht nach der Änderung (für die Anzeige). */
function ep_cz_besetzung(PDO $db, int $planId, int $terminId): array
{
    $neu = ep_plan_laden($db, $planId);
    $fehlt = false;
    foreach (ep_besetzung($neu, $terminId) as $b) if ($b['soll'] !== null && $b['ist'] < $b['soll']) $fehlt = true;
    return ['besetzung' => ep_besetzung_text($neu, $terminId), 'fehlt' => $fehlt];
}

/** Antwort-Payload eines Slots (wie slot_save.php). */
function ep_cz_slot_payload(array $slot, array $mitglieder): array
{
    $warnung = '';
    if (!empty($slot['mitglied_id']) && isset($mitglieder[(int)$slot['mitglied_id']])) {
        $m = $mitglieder[(int)$slot['mitglied_id']];
        if ((int)$m['Verstorben'] === 1) $warnung = 'verstorben'; elseif ((int)$m['Status'] !== 1) $warnung = 'inaktiv';
    }
    return ['id' => (int)$slot['id'], 'verein' => $slot['verein'], 'mitglied_id' => (int)($slot['mitglied_id'] ?? 0), 'name_text' => (string)($slot['name_text'] ?? ''),
            'bemerkung' => (string)($slot['bemerkung'] ?? ''), 'anzeige' => ep_slot_text($slot, $mitglieder), 'besetzt' => ep_slot_besetzt($slot), 'warnung' => $warnung];
}

try {
    // ---- delete: eine Position entfernen ------------------------------------------------
    if ($action === 'delete') {
        $slotId = (int)($_POST['slot_id'] ?? 0);
        $st = $db->prepare("SELECT * FROM einsatz_plan_slots WHERE id = ? AND plan_id = ?");
        $st->execute([$slotId, $planId]);
        $slot = $st->fetch();
        if (!$slot) ep_json(['success' => false, 'message' => 'Position nicht gefunden'], 404);
        $db->beginTransaction();
        $db->prepare("DELETE FROM einsatz_zuweisungen WHERE slot_id = ?")->execute([$slotId]);
        $db->prepare("DELETE FROM einsatz_plan_slots WHERE id = ?")->execute([$slotId]);
        $db->commit();
        ep_projizieren_wenn_freigegeben($db, $planId);
        ep_json(['success' => true, 'message' => 'Position entfernt', 'slot_id' => $slotId] + ep_cz_besetzung($db, $planId, (int)$slot['termin_id']));
    }

    // ---- Person bestimmen (add / set / remove_person) ---------------------------------------
    $mid      = (int)($_POST['mitglied_id'] ?? 0);
    $nameText = mb_substr(trim((string)($_POST['name_text'] ?? '')), 0, 100);
    if ($mid <= 0 && $nameText === '') ep_json(['success' => false, 'message' => 'Person fehlt'], 422);
    if ($mid > 0 && !isset($mitglieder[$mid])) ep_json(['success' => false, 'message' => 'Mitglied nicht gefunden'], 422);
    if ($mid > 0) $nameText = '';
    $persCond  = $mid > 0 ? "mitglied_id = ?" : "mitglied_id IS NULL AND name_text = ?";
    $persParam = $mid > 0 ? $mid : $nameText;

    // ---- add: neue Position in Zelle (Schicht × Funktion) ------------------------------------
    if ($action === 'add') {
        $terminId   = (int)($_POST['termin_id'] ?? 0);
        $funktionId = (int)($_POST['funktion_id'] ?? 0);
        if (!isset($termine[$terminId]) || !isset($funktionen[$funktionId])) ep_json(['success' => false, 'message' => 'Schicht oder Funktion nicht gefunden'], 404);
        $bemerkung = mb_substr(trim((string)($_POST['bemerkung'] ?? '')), 0, 100);
        // dieselbe Person nicht doppelt in derselben Zelle
        $st = $db->prepare("SELECT id FROM einsatz_plan_slots WHERE plan_id = ? AND termin_id = ? AND funktion_id = ? AND $persCond");
        $st->execute([$planId, $terminId, $funktionId, $persParam]);
        if ($st->fetch()) ep_json(['success' => false, 'message' => 'Diese Person ist in dieser Zelle bereits eingeteilt'], 409);
        $db->beginTransaction();
        $posSt = $db->prepare("SELECT COALESCE(MAX(pos), 0) FROM einsatz_plan_slots WHERE termin_id = ? AND funktion_id = ?");
        $posSt->execute([$terminId, $funktionId]);
        $pos = (int)$posSt->fetchColumn() + 1;
        $db->prepare("INSERT INTO einsatz_plan_slots (plan_id, termin_id, funktion_id, pos, verein, mitglied_id, name_text, bemerkung) VALUES (?, ?, ?, ?, 'msv', ?, ?, ?)")
           ->execute([$planId, $terminId, $funktionId, $pos, $mid > 0 ? $mid : null, $mid > 0 ? null : $nameText, $bemerkung !== '' ? $bemerkung : null]);
        $slotId = (int)$db->lastInsertId();
        $db->commit();
        ep_projizieren_wenn_freigegeben($db, $planId);
        $st = $db->prepare("SELECT * FROM einsatz_plan_slots WHERE id = ?"); $st->execute([$slotId]);
        $slot = $st->fetch();
        ep_json(['success' => true, 'message' => 'Position hinzugefügt', 'slot' => ep_cz_slot_payload($slot, $mitglieder)] + ep_cz_besetzung($db, $planId, $terminId));
    }

    // ---- remove_person: alle Positionen einer Person ---------------------------------------
    if ($action === 'remove_person') {
        $db->beginTransaction();
        $db->prepare("DELETE z FROM einsatz_zuweisungen z JOIN einsatz_plan_slots s ON s.id = z.slot_id WHERE s.plan_id = ? AND s.$persCond")->execute([$planId, $persParam]);
        $st = $db->prepare("DELETE FROM einsatz_plan_slots WHERE plan_id = ? AND $persCond");
        $st->execute([$planId, $persParam]);
        $db->commit();
        ep_projizieren_wenn_freigegeben($db, $planId);
        ep_json(['success' => true, 'message' => $st->rowCount() . ' Einsatz/Einsätze entfernt']);
    }

    // ---- set (Altansicht Personen × Schichten): Positionen der Person in der Schicht ersetzen ---
    $terminId = (int)($_POST['termin_id'] ?? 0);
    if (!isset($termine[$terminId])) ep_json(['success' => false, 'message' => 'Schicht nicht gefunden'], 404);
    $fids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['funktion_ids'] ?? [])), fn($id) => isset($funktionen[$id]))));

    $db->beginTransaction();
    $db->prepare("DELETE z FROM einsatz_zuweisungen z JOIN einsatz_plan_slots s ON s.id = z.slot_id WHERE s.plan_id = ? AND s.termin_id = ? AND s.$persCond")
       ->execute([$planId, $terminId, $persParam]);
    $db->prepare("DELETE FROM einsatz_plan_slots WHERE plan_id = ? AND termin_id = ? AND $persCond")->execute([$planId, $terminId, $persParam]);
    $posSt = $db->prepare("SELECT COALESCE(MAX(pos), 0) FROM einsatz_plan_slots WHERE termin_id = ? AND funktion_id = ?");
    $ins   = $db->prepare("INSERT INTO einsatz_plan_slots (plan_id, termin_id, funktion_id, pos, verein, mitglied_id, name_text) VALUES (?, ?, ?, ?, 'msv', ?, ?)");
    $labels = [];
    foreach ($fids as $fid) {
        $posSt->execute([$terminId, $fid]);
        $ins->execute([$planId, $terminId, $fid, (int)$posSt->fetchColumn() + 1, $mid > 0 ? $mid : null, $mid > 0 ? null : $nameText]);
        $labels[] = $funktionen[$fid]['bezeichnung'];
    }
    $db->commit();
    ep_projizieren_wenn_freigegeben($db, $planId);
    ep_json(['success' => true, 'message' => 'Gespeichert', 'anzeige' => implode(' / ', $labels), 'anzahl' => count($fids)] + ep_cz_besetzung($db, $planId, $terminId));
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[einsatzplanung/chilbi_zelle_save] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Einsatz konnte nicht gespeichert werden'], 500);
}
