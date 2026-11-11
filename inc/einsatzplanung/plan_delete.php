<?php
/**
 * inc/einsatzplanung/plan_delete.php – Einsatzplan löschen, nur ohne Geschichte («Nichts geht verloren», seit 1.68.56).
 *
 * POST plan_id, aktion:
 *   pruefen  → {success, titel, status, positionen, personen, verfuegbarkeiten, gesperrt, grund, bezuege{Bezeichnung: Anzahl}}
 *   loeschen → nur Entwürfe ohne erfasste Anwesenheit und ohne Abrechnungszeilen, nach Sicherung der Datenbank;
 *              Plan samt Terminen, Funktionen, Positionen, Soll und Verfügbarkeiten in einer Transaktion.
 *
 * Gesperrt:
 *   - freigegebene und finale Pläne (die Einsätze stehen im Portal: zuerst zurückziehen)
 *   - Pläne mit erfasster Anwesenheit oder manuellen Abrechnungszeilen (Nachweis der geleisteten Stunden)
 * Projizierte Zeilen in einsatz_zuweisungen gehen mit; die Tausch-Historie bleibt als Audit.
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/../backup_dump.inc.php';
require_once __DIR__ . '/plan_helpers.inc.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db     = getDB();
$planId = (int)($_POST['plan_id'] ?? 0);
$aktion = (string)($_POST['aktion'] ?? 'loeschen');
$plan   = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan) ep_json(['success' => false, 'message' => 'Der Plan wurde nicht gefunden. Bitte die Seite neu laden.'], 404);
$titel = (string)$plan['titel'];

// Was am Plan hängt
$besetzt = array_values(array_filter($plan['slots'], 'ep_slot_besetzt'));
$personen = [];
foreach ($besetzt as $s) $personen[ep_person_key((int)$s['mitglied_id'], (string)$s['name_text'])] = true;
$anwesenheit = count(array_filter($plan['slots'], fn($s) => $s['anwesend'] !== null));
$abrZeilen = 0;
try {
    $st = $db->prepare("SELECT COUNT(*) FROM einsatz_abr_zeilen WHERE plan_id = ?");
    $st->execute([$planId]);
    $abrZeilen = (int)$st->fetchColumn();
} catch (Throwable $e) { /* Tabelle erst ab Migration 058 */ }

$bezuege = [];
if ($anwesenheit > 0) $bezuege['Positionen mit erfasster Anwesenheit'] = $anwesenheit;
if ($abrZeilen > 0)   $bezuege['Zeilen in der Helferabrechnung'] = $abrZeilen;

$grund = '';
if ($plan['status'] !== 'entwurf') {
    $grund = 'freigegeben';
} elseif ($bezuege) {
    $grund = 'geschichte';
}

$info = [
    'titel'            => $titel,
    'status'           => $plan['status'],
    'status_label'     => EP_STATUS[$plan['status']] ?? $plan['status'],
    'positionen'       => count($besetzt),
    'personen'         => count($personen),
    'verfuegbarkeiten' => count($plan['verfuegbarkeit']),
    'gesperrt'         => $grund !== '',
    'grund'            => $grund,
    'bezuege'          => (object)$bezuege,
];

if ($aktion === 'pruefen') ep_json(['success' => true] + $info);
if ($aktion !== 'loeschen') ep_json(['success' => false, 'message' => 'Unbekannte Aktion'], 400);

if ($grund === 'freigegeben') {
    ep_json(['success' => false] + $info + ['message' => "Der Plan «$titel» ist im Portal sichtbar und wird darum nicht gelöscht. Zuerst unter «Status» zurückziehen."], 409);
}
if ($grund === 'geschichte') {
    ep_json(['success' => false] + $info + ['message' => "Am Plan «$titel» hängen erfasste Einsätze; er bleibt als Nachweis erhalten."], 409);
}

$sicherung = msvBackupVorher('einsatzplan-loeschen-' . $planId);
if (!$sicherung['ok']) {
    error_log('[einsatzplanung/plan_delete] Sicherung fehlgeschlagen: ' . $sicherung['meldung']);
    ep_json(['success' => false, 'message' => 'Die Sicherung vor dem Löschen ist fehlgeschlagen. Es wurde nichts gelöscht; bitte später nochmals versuchen.'], 500);
}

try {
    $db->beginTransaction();
    $db->prepare("DELETE z FROM einsatz_zuweisungen z JOIN einsatz_plan_slots s ON s.id = z.slot_id WHERE s.plan_id = ?")->execute([$planId]);
    $db->prepare("DELETE FROM einsatz_plan_slots WHERE plan_id = ?")->execute([$planId]);
    foreach (['einsatz_plan_soll', 'einsatz_plan_verfuegbarkeit'] as $tabelle) {
        try { $db->prepare("DELETE FROM $tabelle WHERE plan_id = ?")->execute([$planId]); }
        catch (Throwable $e) { if (!str_contains($e->getMessage(), '42S02')) throw $e; }   // Tabelle fehlt vor Migration 051/053
    }
    $db->prepare("DELETE FROM einsatz_plan_funktionen WHERE plan_id = ?")->execute([$planId]);
    $db->prepare("DELETE FROM einsatz_plan_termine WHERE plan_id = ?")->execute([$planId]);
    $db->prepare("UPDATE einsatz_plaene SET vorlage_plan_id = NULL WHERE vorlage_plan_id = ?")->execute([$planId]);
    $db->prepare("DELETE FROM einsatz_plaene WHERE id = ?")->execute([$planId]);
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[einsatzplanung/plan_delete] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Der Plan konnte nicht gelöscht werden; es wurde nichts geändert. Die Sicherung '
             . basename((string)$sicherung['datei']) . ' liegt vor.'], 500);
}
ep_json(['success' => true, 'message' => "Plan «$titel» gelöscht. Sicherung: " . basename((string)$sicherung['datei']),
         'sicherung' => basename((string)$sicherung['datei'])]);
