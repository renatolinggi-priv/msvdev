<?php
/**
 * inc/einsatzplanung/ok_funktion_save.php – Funktion als «immer vom OK besetzt» definieren (oder Definition aufheben).
 *
 * Die Definition gilt über alle Jahre (settings.einsatzplan_ok_funktionen, normalisierter Funktionsname) und wird
 * sofort auf den aktuellen Plan angewendet: Rolle 'OK' bzw. zurück auf die übrige Vorbelegung.
 * POST plan_id, funktion_id, ok=0|1
 * Antwort: {success, message, funktionen: {funktion_id: ist_ok}, slots: [{id, funktion_id, ok}]}
 *          slots = alle besetzten Positionen des Plans mit ihrem eigenen Kennzeichen (für die Chips)
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
$fid    = (int)($_POST['funktion_id'] ?? 0);
$ok     = (int)($_POST['ok'] ?? 0) === 1;

$st = $db->prepare("SELECT f.bezeichnung, p.typ FROM einsatz_plan_funktionen f JOIN einsatz_plaene p ON p.id = f.plan_id WHERE f.id = ? AND f.plan_id = ?");
$st->execute([$fid, $planId]);
$f = $st->fetch();
if (!$f) ep_json(['success' => false, 'message' => 'Funktion nicht gefunden'], 404);
if ($f['typ'] !== 'schlossturm') ep_json(['success' => false, 'message' => 'Nur für Schlossturm-Pläne'], 422);

try {
    $liste = ep_ok_funktionen_laden($db);
    $name  = ep_funktion_norm((string)$f['bezeichnung']);
    $liste = $ok ? array_merge($liste, [$name]) : array_values(array_filter($liste, fn($n) => $n !== $name));
    ep_ok_funktionen_speichern($db, $liste);
    $funktionen = ep_ok_funktionen_anwenden($db, $planId);
    $sl = $db->prepare("SELECT id, funktion_id, ok FROM einsatz_plan_slots WHERE plan_id = ? AND (mitglied_id IS NOT NULL OR (name_text IS NOT NULL AND name_text <> ''))");
    $sl->execute([$planId]);
    $slots = array_map(fn($s) => ['id' => (int)$s['id'], 'funktion_id' => (int)$s['funktion_id'], 'ok' => (int)$s['ok']], $sl->fetchAll());
    ep_json(['success' => true, 'message' => '«' . $f['bezeichnung'] . '» ' . ($ok ? 'ist ab jetzt immer vom OK besetzt' : 'ist keine OK-Funktion mehr') . ' (gilt für alle Jahre)',
             'funktionen' => $funktionen, 'slots' => $slots]);
} catch (Throwable $e) {
    error_log('[einsatzplanung/ok_funktion_save] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Speichern fehlgeschlagen (Migrationen 053/058 ausgeführt?)'], 500);
}
