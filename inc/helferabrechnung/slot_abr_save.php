<?php
/**
 * inc/helferabrechnung/slot_abr_save.php – Abrechnungsfelder einer Position (Slot) setzen.
 *
 * POST slot_id, ok (0|1), stunden_korrektur ('' = keine), bemerkung
 * Nur diese drei Felder – Verein/Person/Anwesenheit bleiben der Einsatzplanung vorbehalten
 * (slot_save.php, api/einsatz_anwesenheit.php).
 * Antwort: {success, message, ok, stunden_korrektur, bemerkung}
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
$slotId = (int)($_POST['slot_id'] ?? 0);

$st = $db->prepare("SELECT s.id, p.typ FROM einsatz_plan_slots s JOIN einsatz_plaene p ON p.id = s.plan_id WHERE s.id = ?");
$st->execute([$slotId]);
$s = $st->fetch();
if (!$s) ep_json(['success' => false, 'message' => 'Position nicht gefunden'], 404);
if ($s['typ'] !== 'schlossturm') ep_json(['success' => false, 'message' => 'Abrechnungsfelder gibt es nur für Schlossturm-Pläne'], 422);

$ok        = (int)($_POST['ok'] ?? 0) === 1 ? 1 : 0;
$rawK      = str_replace(',', '.', trim((string)($_POST['stunden_korrektur'] ?? '')));
$korrektur = null;
if ($rawK !== '') {
    if (!is_numeric($rawK) || (float)$rawK < 0 || (float)$rawK > 99.99) ep_json(['success' => false, 'message' => 'Korrektur muss zwischen 0 und 99.99 liegen'], 422);
    $korrektur = round((float)$rawK, 2);
}
$bemerkung = mb_substr(trim((string)($_POST['bemerkung'] ?? '')), 0, 100);

try {
    $db->prepare("UPDATE einsatz_plan_slots SET ok = ?, stunden_korrektur = ?, bemerkung = ? WHERE id = ?")
       ->execute([$ok, $korrektur, $bemerkung !== '' ? $bemerkung : null, $slotId]);
} catch (Throwable $e) {
    error_log('[helferabrechnung/slot_abr_save] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Speichern fehlgeschlagen (Migration 058 ausgeführt?)'], 500);
}
ep_json(['success' => true, 'message' => 'Gespeichert', 'ok' => $ok, 'stunden_korrektur' => $korrektur, 'bemerkung' => $bemerkung]);
