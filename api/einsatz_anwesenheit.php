<?php
/**
 * api/einsatz_anwesenheit.php – Anwesenheit an einer Position setzen (Vorstand/Admin, Portal-Seite
 * portal/einsatz_anwesenheit.php). Migration 054.
 *
 * POST action=set   slot_id, anwesend = 1 | 0 | '' (leer = wieder «nicht erfasst»)
 * POST action=alle  plan_id, termin_id, anwesend = 1 | 0   → alle noch nicht erfassten festen Positionen der Schicht
 * Antwort: {success, message, anwesend, [anzahl]}
 */
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/einsatzplanung/plan_helpers.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Methode nicht erlaubt', 405);
validateCsrfRequest();

$db     = getDB();
$uid    = (int)($_SESSION['user_id'] ?? 0);
$action = $_POST['action'] ?? 'set';
$raw    = $_POST['anwesend'] ?? '';
$anw    = $raw === '' || $raw === null ? null : ((int)$raw === 1 ? 1 : 0);

try {
    if ($action === 'alle') {
        if ($anw === null) json_error('Wert fehlt', 422);
        $planId = (int)($_POST['plan_id'] ?? 0); $terminId = (int)($_POST['termin_id'] ?? 0);
        $st = $db->prepare("UPDATE einsatz_plan_slots SET anwesend = ?, anwesend_am = NOW(), anwesend_von = ?
                             WHERE plan_id = ? AND termin_id = ? AND anwesend IS NULL AND vorschlag = 0
                               AND (mitglied_id IS NOT NULL OR (name_text IS NOT NULL AND name_text <> ''))");
        $st->execute([$anw, $uid, $planId, $terminId]);
        echo json_encode(['success' => true, 'message' => $st->rowCount() . ' Position(en) als «' . ($anw ? 'da' : 'nicht da') . '» erfasst', 'anwesend' => $anw, 'anzahl' => $st->rowCount()]);
        exit;
    }
    $slotId = (int)($_POST['slot_id'] ?? 0);
    $st = $db->prepare("SELECT id FROM einsatz_plan_slots WHERE id = ?");
    $st->execute([$slotId]);
    if (!$st->fetch()) json_error('Position nicht gefunden', 404);
    $db->prepare("UPDATE einsatz_plan_slots SET anwesend = ?, anwesend_am = ?, anwesend_von = ? WHERE id = ?")
       ->execute([$anw, $anw === null ? null : date('Y-m-d H:i:s'), $anw === null ? null : $uid, $slotId]);
    echo json_encode(['success' => true, 'message' => $anw === null ? 'zurückgesetzt' : ($anw ? 'da' : 'nicht da'), 'anwesend' => $anw]);
} catch (Throwable $e) {
    error_log('[einsatz_anwesenheit] ' . $e->getMessage());
    json_error('Anwesenheit konnte nicht gespeichert werden (Migration 054 ausgeführt?)', 500);
}
