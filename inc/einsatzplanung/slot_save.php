<?php
/**
 * inc/einsatzplanung/slot_save.php – eine Position (Slot) im Layout Funktionen × Termine setzen.
 *
 * POST plan_id, slot_id, verein=msv|freienbach|wollerau, mitglied_id (0 = keins), name_text, bemerkung, [ok=0|1]
 * Regeln: Fremdverein → mitglied_id wird verworfen (Name als Klartext); MSV mit Mitglied → name_text leer.
 *         ok (OK-Mitglied, Helferabrechnung/Migration 058) wird nur geändert, wenn der Parameter mitkommt
 *         (Drag & Drop schickt ihn nicht) – vor Migration 058 wird er ignoriert.
 * Antwort: {success, slot:{id, verein, mitglied_id, name_text, bemerkung, ok, anzeige, besetzt, warnung}}
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
$planId = (int)($_POST['plan_id'] ?? 0);
$slotId = (int)($_POST['slot_id'] ?? 0);

$st = $db->prepare("SELECT * FROM einsatz_plan_slots WHERE id = ? AND plan_id = ?");
$st->execute([$slotId, $planId]);
$slot = $st->fetch();
if (!$slot) ep_json(['success' => false, 'message' => 'Position nicht gefunden'], 404);

$verein = $_POST['verein'] ?? 'msv';
if (!isset(EP_VEREINE[$verein])) ep_json(['success' => false, 'message' => 'Ungültiger Verein'], 422);
$mid       = (int)($_POST['mitglied_id'] ?? 0);
$nameText  = mb_substr(trim((string)($_POST['name_text'] ?? '')), 0, 100);
$bemerkung = mb_substr(trim((string)($_POST['bemerkung'] ?? '')), 0, 100);
$okNeu     = array_key_exists('ok', $_POST) ? ((int)$_POST['ok'] === 1 ? 1 : 0) : null;

$mitglieder = ep_mitglieder_map($db);
if ($verein !== 'msv') {
    $mid = 0;                       // Fremdvereine: nur Klartext
} elseif ($mid > 0) {
    if (!isset($mitglieder[$mid])) ep_json(['success' => false, 'message' => 'Mitglied nicht gefunden'], 422);
    $nameText = '';                 // Mitglied gewinnt gegen Klartext
}

try {
    // Manuelle Entscheidung: ein allfälliger Einteilungs-Vorschlag gilt damit als bestätigt (vorschlag = 0)
    try {
        $db->prepare("UPDATE einsatz_plan_slots SET verein = ?, mitglied_id = ?, name_text = ?, bemerkung = ?, vorschlag = 0 WHERE id = ?")
           ->execute([$verein, $mid > 0 ? $mid : null, $nameText !== '' ? $nameText : null, $bemerkung !== '' ? $bemerkung : null, $slotId]);
    } catch (Throwable $e) {   // vor Migration 053 ohne Spalte vorschlag
        $db->prepare("UPDATE einsatz_plan_slots SET verein = ?, mitglied_id = ?, name_text = ?, bemerkung = ? WHERE id = ?")
           ->execute([$verein, $mid > 0 ? $mid : null, $nameText !== '' ? $nameText : null, $bemerkung !== '' ? $bemerkung : null, $slotId]);
    }
    $ok = (int)($slot['ok'] ?? 0);
    if ($okNeu !== null) {
        try { $db->prepare("UPDATE einsatz_plan_slots SET ok = ? WHERE id = ?")->execute([$okNeu, $slotId]); $ok = $okNeu; }
        catch (Throwable $e) { /* vor Migration 058 */ }
    }
    $slot['vorschlag'] = 0;
    ep_projizieren_wenn_freigegeben($db, $planId);

    $slot = array_merge($slot, ['verein' => $verein, 'mitglied_id' => $mid ?: null, 'name_text' => $nameText ?: null, 'bemerkung' => $bemerkung ?: null, 'ok' => $ok]);
    $warnung = '';
    if ($mid > 0) {
        $m = $mitglieder[$mid];
        if ((int)$m['Verstorben'] === 1) $warnung = 'verstorben';
        elseif ((int)$m['Status'] !== 1) $warnung = 'inaktiv';
    }
    ep_json(['success' => true, 'message' => 'Gespeichert', 'slot' => [
        'id' => $slotId, 'verein' => $verein, 'mitglied_id' => $mid ?: 0, 'name_text' => $nameText, 'bemerkung' => $bemerkung, 'ok' => $ok,
        'anzeige' => ep_slot_text($slot, $mitglieder), 'besetzt' => ep_slot_besetzt($slot), 'warnung' => $warnung,
    ]]);
} catch (Throwable $e) {
    error_log('[einsatzplanung/slot_save] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Position konnte nicht gespeichert werden'], 500);
}
