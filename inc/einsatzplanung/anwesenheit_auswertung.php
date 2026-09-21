<?php
/**
 * inc/einsatzplanung/anwesenheit_auswertung.php – Auswertung der Anwesenheit (Migration 054).
 *
 * GET jahr=YYYY [plan_id=N]  → JSON
 *   plaene:   [{id, titel, typ, eingeteilt, da, nein, offen}]                 (Pläne des Jahres, Status ≠ Entwurf; bzw. nur plan_id)
 *   vereine:  {msv: {eingeteilt, da, nein, offen, stunden_da}, …}
 *   personen: [{name, verein, mitglied_id, eingeteilt, da, nein, offen, stunden_da, plaene:[titel…]}]  sortiert nach Fehlquote, Name
 * Nur feste Positionen (kein Vorschlag), Vereins-Platzhalter ohne Namen zählen nicht.
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/plan_helpers.inc.php';

header('Content-Type: application/json; charset=utf-8');
$db     = getDB();
$jahr   = (int)($_GET['jahr'] ?? date('Y'));
$planId = (int)($_GET['plan_id'] ?? 0);

try {
    if ($planId > 0) { $st = $db->prepare("SELECT id, titel, typ FROM einsatz_plaene WHERE id = ?"); $st->execute([$planId]); }
    else { $st = $db->prepare("SELECT id, titel, typ FROM einsatz_plaene WHERE jahr = ? AND status <> 'entwurf' ORDER BY FIELD(typ,'obligatorisch','feldschiessen','schlossturm','chilbi','sonstiges'), titel"); $st->execute([$jahr]); }
    $plaeneRows = $st->fetchAll();
    $mitglieder = ep_mitglieder_map($db);

    $leer = fn() => ['eingeteilt' => 0, 'da' => 0, 'nein' => 0, 'offen' => 0, 'stunden_da' => 0.0];
    $vereine = []; foreach (EP_VEREINE as $k => $v) $vereine[$k] = $leer();
    $personen = []; $plaene = [];
    foreach ($plaeneRows as $pr) {
        $plan = ep_plan_laden($db, (int)$pr['id']);
        if (!$plan) continue;
        $termine = []; foreach ($plan['termine'] as $t) $termine[(int)$t['id']] = $t;
        $pStat = $leer();
        foreach ($plan['slots'] as $s) {
            if (!ep_slot_fix($s)) continue;
            $t = $termine[(int)$s['termin_id']] ?? null; if (!$t) continue;
            $std = ep_termin_stunden($t);   // Pauschale (Migration 058) bzw. Schichtdauer
            $key = ep_person_key((int)$s['mitglied_id'], (string)$s['name_text']);
            $personen[$key] ??= ['name' => ep_slot_text($s, $mitglieder), 'verein' => $s['verein'], 'mitglied_id' => (int)$s['mitglied_id'] ?: null, 'plaene' => []] + $leer();
            $status = $s['anwesend'] === null ? 'offen' : ((int)$s['anwesend'] === 1 ? 'da' : 'nein');
            foreach ([&$pStat, &$vereine[$s['verein']], &$personen[$key]] as &$acc) { $acc['eingeteilt']++; $acc[$status]++; if ($status === 'da') $acc['stunden_da'] += $std; } unset($acc);
            $personen[$key]['plaene'][$plan['titel']] = true;
        }
        $plaene[] = ['id' => (int)$plan['id'], 'titel' => $plan['titel'], 'typ' => $plan['typ']] + $pStat;
    }
    foreach ($personen as &$p) { $p['plaene'] = array_keys($p['plaene']); $p['stunden_da'] = round($p['stunden_da'], 1); } unset($p);
    foreach ($vereine as &$v) $v['stunden_da'] = round($v['stunden_da'], 1); unset($v);
    $personen = array_values($personen);
    usort($personen, fn($a, $b) => [$b['nein'], $a['verein'], $a['name']] <=> [$a['nein'], $b['verein'], $b['name']]);
    ep_json(['success' => true, 'jahr' => $jahr, 'plaene' => $plaene, 'vereine' => $vereine, 'personen' => $personen]);
} catch (Throwable $e) {
    error_log('[einsatzplanung/anwesenheit_auswertung] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Auswertung fehlgeschlagen (Migration 054 ausgeführt?)'], 500);
}
