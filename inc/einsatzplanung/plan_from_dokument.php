<?php
/**
 * inc/einsatzplanung/plan_from_dokument.php – Plan aus einem hochgeladenen Einsatzplan-Dokument
 * (vorstand_dokumente, typ einsatzplan) aufbauen. Logik in plan_import.inc.php (ep_plan_aus_dokument),
 * damit derselbe Import auch per CLI läuft.
 *
 * POST dokument_id, [titel], [typ]
 * Antwort: {success, plan_id, message, statistik:{termine, funktionen, slots, ohne_match:[...], verknuepft}}
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/plan_import.inc.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db     = getDB();
$userId = (int)($_SESSION['user_id'] ?? 0);
$dokId  = (int)($_POST['dokument_id'] ?? 0);

$st = $db->prepare("SELECT * FROM vorstand_dokumente WHERE id = ? AND typ = 'einsatzplan'");
$st->execute([$dokId]);
$dok = $st->fetch();
if (!$dok) ep_json(['success' => false, 'message' => 'Dokument nicht gefunden'], 404);

$pfad = $dok['dateipfad'];
if (!is_file($pfad)) {
    // Pfad ist absolut gespeichert; Rückfall relativ zum Upload-Ordner
    $alt = __DIR__ . '/../../portal/uploads/dokumente/einsatzplan/' . basename($pfad);
    if (is_file($alt)) $pfad = $alt; else ep_json(['success' => false, 'message' => 'Datei nicht gefunden: ' . basename($pfad)], 404);
}

try {
    $r = ep_plan_aus_dokument($db, $pfad, $userId, [
        'dokument_id' => $dokId,
        'titel'       => trim((string)($_POST['titel'] ?? '')) ?: (string)$dok['titel'],
        'typ'         => (string)($_POST['typ'] ?? ''),
        'jahr'        => (int)($dok['jahr'] ?? 0),
    ]);
    $s = $r['statistik'];
    ep_json([
        'success' => true, 'plan_id' => $r['plan_id'],
        'message' => 'Plan «' . $r['titel'] . '» aus Dokument übernommen: ' . $s['termine'] . ' Termine, ' . $s['funktionen'] . ' Funktionen, ' . $s['slots'] . ' Positionen'
            . ($s['ohne_match'] ? ', ' . count($s['ohne_match']) . ' Name(n) ohne Mitglied' : '')
            . ($s['verknuepft'] ? ', ' . $s['verknuepft'] . ' bestehende Einsätze verknüpft' : ''),
        'statistik' => $s,
    ]);
} catch (InvalidArgumentException $e) {
    ep_json(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('[einsatzplanung/plan_from_dokument] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Übernahme fehlgeschlagen: ' . $e->getMessage()], 500);
}
