<?php
/**
 * inc/helferabrechnung/zeile_copy.php – manuelle Zeilen aus dem Vorjahr übernehmen.
 *
 * POST plan_id [, quelle_id]  → kopiert Vor-/Nacharbeiten und OK-Funktionen des neuesten älteren Schlossturm-Plans
 * (bzw. von quelle_id) in den Zielplan: Tätigkeit, Person, Verein, Kategorie und Stunden als Startwert;
 * Bemerkung «Vorjahr <Jahr>: <Person>, <Std> h». Nachträge (Kategorie einsatz) werden nicht kopiert – sie
 * gehören zum konkreten Anlass. Zeilen, die im Ziel bereits mit gleicher Tätigkeit + Person existieren, werden übersprungen.
 * GET plan_id → {success, quelle: {id, jahr, titel, anzahl} | null}   (Vorschau für den Bestätigungsdialog)
 * Antwort POST: {success, message, kopiert, uebersprungen}
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/abrechnung.inc.php';

header('Content-Type: application/json; charset=utf-8');
$db     = getDB();
$planId = (int)($_REQUEST['plan_id'] ?? 0);

$st = $db->prepare("SELECT id, jahr, typ FROM einsatz_plaene WHERE id = ?");
$st->execute([$planId]);
$plan = $st->fetch();
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);
if ($plan['typ'] !== 'schlossturm') ep_json(['success' => false, 'message' => 'Nur für Schlossturm-Pläne'], 422);

/** Quellplan: explizit oder neuester älterer Schlossturm-Plan mit manuellen Zeilen. */
function ha_quelle_finden(PDO $db, array $plan, int $quelleId): ?array
{
    if ($quelleId > 0) {
        $st = $db->prepare("SELECT p.id, p.jahr, p.titel, (SELECT COUNT(*) FROM einsatz_abr_zeilen z WHERE z.plan_id = p.id AND z.kategorie <> 'einsatz') AS anzahl
                            FROM einsatz_plaene p WHERE p.id = ? AND p.typ = 'schlossturm' AND p.id <> ?");
        $st->execute([$quelleId, (int)$plan['id']]);
    } else {
        $st = $db->prepare("SELECT p.id, p.jahr, p.titel, (SELECT COUNT(*) FROM einsatz_abr_zeilen z WHERE z.plan_id = p.id AND z.kategorie <> 'einsatz') AS anzahl
                            FROM einsatz_plaene p WHERE p.typ = 'schlossturm' AND p.id <> ? AND (p.jahr < ? OR (p.jahr = ? AND p.id < ?))
                            HAVING anzahl > 0 ORDER BY p.jahr DESC, p.id DESC LIMIT 1");
        $st->execute([(int)$plan['id'], (int)$plan['jahr'], (int)$plan['jahr'], (int)$plan['id']]);
    }
    $q = $st->fetch();
    return $q ?: null;
}

try {
    $quelle = ha_quelle_finden($db, $plan, (int)($_REQUEST['quelle_id'] ?? 0));
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ep_json(['success' => true, 'quelle' => $quelle ? ['id' => (int)$quelle['id'], 'jahr' => (int)$quelle['jahr'], 'titel' => $quelle['titel'], 'anzahl' => (int)$quelle['anzahl']] : null]);
    }
    csrf_require(true);
    if (!$quelle) ep_json(['success' => false, 'message' => 'Kein früherer Schlossturm-Plan mit manuellen Zeilen gefunden'], 404);

    // Bereits vorhandene Zeilen im Ziel (Tätigkeit + Person) nicht doppelt anlegen
    $vorhanden = [];
    foreach (ha_manuell($db, $planId) as $z) $vorhanden[mb_strtolower($z['taetigkeit'] . '|' . $z['person'])] = true;

    $ins = $db->prepare("INSERT INTO einsatz_abr_zeilen (plan_id, kategorie, taetigkeit, verein, mitglied_id, name_text, stunden, bemerkung, sort, erstellt_von)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $sort = (int)$db->query("SELECT COALESCE(MAX(sort), 0) FROM einsatz_abr_zeilen WHERE plan_id = " . $planId)->fetchColumn();
    $uid = (int)($_SESSION['user_id'] ?? 0) ?: null;
    $kopiert = 0; $uebersprungen = 0;
    $db->beginTransaction();
    foreach (ha_manuell($db, (int)$quelle['id']) as $z) {
        if ($z['kategorie'] === 'einsatz') continue;
        if (isset($vorhanden[mb_strtolower($z['taetigkeit'] . '|' . $z['person'])])) { $uebersprungen++; continue; }
        $sort += 10;
        $bem = 'Vorjahr ' . (int)$quelle['jahr'] . ': ' . $z['person'] . ', ' . ha_fmt((float)$z['stunden']) . ' h';
        $ins->execute([$planId, $z['kategorie'], $z['taetigkeit'], $z['verein'], $z['mitglied_id'] ?: null, $z['name_text'] ?: null, (float)$z['stunden'], mb_substr($bem, 0, 255), $sort, $uid]);
        $kopiert++;
    }
    $db->commit();
    ep_json(['success' => true, 'message' => $kopiert . ' Zeile(n) aus ' . (int)$quelle['jahr'] . ' übernommen' . ($uebersprungen ? ', ' . $uebersprungen . ' bereits vorhanden' : '') . ' – Stunden bitte prüfen', 'kopiert' => $kopiert, 'uebersprungen' => $uebersprungen]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[helferabrechnung/zeile_copy] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Übernahme fehlgeschlagen (Migration 058 ausgeführt?)'], 500);
}
