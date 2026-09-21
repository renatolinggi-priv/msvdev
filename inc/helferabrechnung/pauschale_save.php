<?php
/**
 * inc/helferabrechnung/pauschale_save.php – abgerechnete Stunden (Pauschale) einer Schicht setzen.
 *
 * POST termin_id, pauschale_std ('' = zurück auf Schichtdauer aus zeit_von/zeit_bis)
 * Antwort: {success, message, pauschale_std (float|null), ansatz (float)}  ansatz = ep_termin_stunden()
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/abrechnung.inc.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db       = getDB();
$terminId = (int)($_POST['termin_id'] ?? 0);
$raw      = str_replace(',', '.', trim((string)($_POST['pauschale_std'] ?? '')));

$st = $db->prepare("SELECT t.*, p.typ FROM einsatz_plan_termine t JOIN einsatz_plaene p ON p.id = t.plan_id WHERE t.id = ?");
$st->execute([$terminId]);
$t = $st->fetch();
if (!$t) ep_json(['success' => false, 'message' => 'Schicht nicht gefunden'], 404);
if ($t['typ'] !== 'schlossturm') ep_json(['success' => false, 'message' => 'Pauschalen gibt es nur für Schlossturm-Pläne'], 422);

$wert = null;
if ($raw !== '') {
    if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > 99.99) ep_json(['success' => false, 'message' => 'Pauschale muss zwischen 0 und 99.99 liegen'], 422);
    $wert = round((float)$raw, 2);
}

try {
    $db->prepare("UPDATE einsatz_plan_termine SET pauschale_std = ? WHERE id = ?")->execute([$wert, $terminId]);
} catch (Throwable $e) {
    error_log('[helferabrechnung/pauschale_save] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Speichern fehlgeschlagen (Migration 058 ausgeführt?)'], 500);
}
$t['pauschale_std'] = $wert;
ep_json(['success' => true, 'message' => $wert === null ? 'Pauschale entfernt – es zählt die Schichtdauer' : 'Pauschale gespeichert', 'pauschale_std' => $wert, 'ansatz' => ep_termin_stunden($t)]);
