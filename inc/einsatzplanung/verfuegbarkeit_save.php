<?php
/**
 * inc/einsatzplanung/verfuegbarkeit_save.php – Verfügbarkeiten (Personalanfrage) manuell pflegen.
 *
 * POST action=save    plan_id, [id], verein, mitglied_id | name_text, rollen[] (EP_ROLLEN-Schlüssel), termin_ids[], bemerkung
 *                     → neue Zeile (Person darf pro Plan nur einmal vorkommen) oder Änderung; Quelle wird 'manuell'
 * POST action=delete  plan_id, id
 * Antwort: {success, message, [zeile]}
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
$action = $_POST['action'] ?? 'save';
$planId = (int)($_POST['plan_id'] ?? 0);
$plan   = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);
$id = (int)($_POST['id'] ?? 0);

try {
    if ($action === 'delete') {
        $st = $db->prepare("DELETE FROM einsatz_plan_verfuegbarkeit WHERE id = ? AND plan_id = ?");
        $st->execute([$id, $planId]);
        ep_json(['success' => $st->rowCount() > 0, 'message' => $st->rowCount() ? 'Verfügbarkeit gelöscht' : 'Zeile nicht gefunden']);
    }

    $verein = $_POST['verein'] ?? 'msv';
    if (!isset(EP_VEREINE[$verein])) ep_json(['success' => false, 'message' => 'Ungültiger Verein'], 422);
    $mid      = (int)($_POST['mitglied_id'] ?? 0);
    $nameText = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($_POST['name_text'] ?? ''))), 0, 100);
    if ($verein !== 'msv') $mid = 0;                                 // Fremdvereine nur als Klartext
    if ($mid <= 0 && $nameText === '') ep_json(['success' => false, 'message' => 'Person fehlt'], 422);
    if ($mid > 0) {
        $st = $db->prepare("SELECT ID FROM mitglieder WHERE ID = ?"); $st->execute([$mid]);
        if (!$st->fetch()) ep_json(['success' => false, 'message' => 'Mitglied nicht gefunden'], 422);
        $nameText = '';
    }
    $rollen = array_values(array_unique(array_filter((array)($_POST['rollen'] ?? []), fn($r) => isset(EP_ROLLEN[$r]))));
    $terminIds = array_map(fn($t) => (int)$t['id'], $plan['termine']);
    $tids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['termin_ids'] ?? [])), fn($t) => in_array($t, $terminIds, true))));
    $bemerkung = mb_substr(trim((string)($_POST['bemerkung'] ?? '')), 0, 255);

    // Person pro Plan nur einmal
    $dup = $db->prepare("SELECT id FROM einsatz_plan_verfuegbarkeit WHERE plan_id = ? AND id <> ? AND " . ($mid > 0 ? "mitglied_id = ?" : "mitglied_id IS NULL AND name_text = ?"));
    $dup->execute([$planId, $id, $mid > 0 ? $mid : $nameText]);
    if ($dup->fetch()) ep_json(['success' => false, 'message' => 'Diese Person hat bereits eine Verfügbarkeit in diesem Plan – bitte dort ändern'], 409);

    $rollenJson = json_encode($rollen, JSON_UNESCAPED_UNICODE); $tidsJson = json_encode($tids);
    if ($id > 0) {
        $db->prepare("UPDATE einsatz_plan_verfuegbarkeit SET verein = ?, mitglied_id = ?, name_text = ?, rollen = ?, termin_ids = ?, bemerkung = ?, quelle = 'manuell' WHERE id = ? AND plan_id = ?")
           ->execute([$verein, $mid ?: null, $nameText ?: null, $rollenJson, $tidsJson, $bemerkung ?: null, $id, $planId]);
    } else {
        $db->prepare("INSERT INTO einsatz_plan_verfuegbarkeit (plan_id, verein, mitglied_id, name_text, rollen, termin_ids, bemerkung, quelle) VALUES (?, ?, ?, ?, ?, ?, ?, 'manuell')")
           ->execute([$planId, $verein, $mid ?: null, $nameText ?: null, $rollenJson, $tidsJson, $bemerkung ?: null]);
        $id = (int)$db->lastInsertId();
    }
    $zeile = null;
    foreach (ep_verfuegbarkeit_laden($db, $planId) as $v) if ((int)$v['id'] === $id) $zeile = $v;
    ep_json(['success' => true, 'message' => 'Verfügbarkeit gespeichert', 'zeile' => $zeile]);
} catch (Throwable $e) {
    error_log('[einsatzplanung/verfuegbarkeit_save] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Verfügbarkeit konnte nicht gespeichert werden'], 500);
}
