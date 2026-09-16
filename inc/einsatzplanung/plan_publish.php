<?php
/**
 * inc/einsatzplanung/plan_publish.php – Status setzen und in einsatz_zuweisungen projizieren.
 *
 * POST plan_id, status=entwurf|freigegeben|final, [push=1]
 *   freigegeben/final → einsatzplanProjizieren(); optional Push an alle eingeteilten Mitglieder
 *   entwurf           → projizierte Zeilen des Plans entfernen (Plan verschwindet aus dem Portal)
 * Antwort: {success, message, status, projektion:{upserts, geloescht, legacy}, push}
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
$plan   = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);

$status = $_POST['status'] ?? '';
if (!isset(EP_STATUS[$status])) ep_json(['success' => false, 'message' => 'Ungültiger Status'], 422);
$push = !empty($_POST['push']);

try {
    $db->beginTransaction();
    $db->prepare("UPDATE einsatz_plaene SET status = ?, freigegeben_am = CASE WHEN ? = 'entwurf' THEN NULL WHEN freigegeben_am IS NULL THEN NOW() ELSE freigegeben_am END WHERE id = ?")
       ->execute([$status, $status, $planId]);

    $projektion = ['upserts' => 0, 'geloescht' => 0, 'legacy' => 0];
    if ($status === 'entwurf') {
        $del = $db->prepare("DELETE z FROM einsatz_zuweisungen z JOIN einsatz_plan_slots s ON s.id = z.slot_id WHERE s.plan_id = ?");
        $del->execute([$planId]);
        $projektion['geloescht'] = $del->rowCount();
    } else {
        $projektion = einsatzplanProjizieren($db, $planId);
    }
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[einsatzplanung/plan_publish] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Status konnte nicht gesetzt werden'], 500);
}

// Push an eingeteilte Mitglieder mit Portal-Konto (best effort, ausserhalb der Transaktion)
$pushCount = 0;
if ($push && $status !== 'entwurf') {
    try {
        require_once __DIR__ . '/../push_helper.php';
        $st = $db->prepare("SELECT DISTINCT u.id
                              FROM einsatz_plan_slots s
                              JOIN users u ON u.mitglied_id = s.mitglied_id AND u.status = 'approved'
                             WHERE s.plan_id = ? AND s.verein = 'msv' AND s.mitglied_id IS NOT NULL");
        $st->execute([$planId]);
        $titel = 'Einsatzplan ' . $plan['titel'];
        $text  = $status === 'final'
            ? 'Der Einsatzplan ist definitiv. Bitte prüfe deine Einsätze im Portal.'
            : 'Der Einsatzplan ist freigegeben. Bitte prüfe deine Einsätze im Portal.';
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            if (function_exists('benachrichtigungZustellen')) {
                benachrichtigungZustellen((int)$uid, $titel, $text, '/portal/einsatzplan.php?id=' . $planId, 'einsaetze', 'einsatzplan-' . $planId);
                $pushCount++;
            }
        }
    } catch (Throwable $e) {
        error_log('[einsatzplanung/plan_publish] Push: ' . $e->getMessage());
    }
}

ep_json([
    'success'    => true,
    'status'     => $status,
    'message'    => 'Status «' . EP_STATUS[$status] . '» gesetzt'
        . ($status !== 'entwurf' ? ' – ' . $projektion['upserts'] . ' Einsätze im Portal' : '')
        . ($pushCount > 0 ? ', ' . $pushCount . ' Benachrichtigung(en)' : ''),
    'projektion' => $projektion,
    'push'       => $pushCount,
]);
