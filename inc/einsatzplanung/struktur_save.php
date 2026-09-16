<?php
/**
 * inc/einsatzplanung/struktur_save.php – Termine und Funktionen eines Plans pflegen.
 *
 * POST action=termin_save      plan_id, [id], bezeichnung, datum, zeit_von, zeit_bis, zeit_text
 * POST action=termin_delete    plan_id, id            (löscht auch die Slots des Termins)
 * POST action=termine_aus_jm   plan_id                (fehlende Schiesstage aus JM-Definition ergänzen)
 * POST action=funktion_save    plan_id, [id], gruppe, bezeichnung, anzahl
 * POST action=funktion_delete  plan_id, id            (nur wenn keine besetzten Slots)
 * POST action=funktion_move    plan_id, id, dir=up|down
 * POST action=soll_save        plan_id, soll = JSON {"terminId|funktionId": n, ...}  (Migration 051; 0 löscht)
 * Antwort: {success, message, [id]}
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
$action = $_POST['action'] ?? '';
$planId = (int)($_POST['plan_id'] ?? 0);
$plan   = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);
$id = (int)($_POST['id'] ?? 0);

/** TIME-Wert aus «HH:MM» oder leer → null */
function ep_time_or_null($v): ?string
{
    $v = trim((string)$v);
    if ($v === '') return null;
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $v, $m) || (int)$m[1] > 23 || (int)$m[2] > 59) {
        throw new InvalidArgumentException('Ungültige Zeit «' . $v . '» (erwartet HH:MM)');
    }
    return sprintf('%02d:%02d:00', $m[1], $m[2]);
}

try {
    switch ($action) {
        case 'termin_save':
            $datum = trim((string)($_POST['datum'] ?? ''));
            $d = DateTime::createFromFormat('Y-m-d', $datum);
            if (!$d || $d->format('Y-m-d') !== $datum) ep_json(['success' => false, 'message' => 'Ungültiges Datum'], 422);
            $bez  = mb_substr(trim((string)($_POST['bezeichnung'] ?? '')), 0, 100);
            $von  = ep_time_or_null($_POST['zeit_von'] ?? '');
            $bis  = ep_time_or_null($_POST['zeit_bis'] ?? '');
            $zt   = mb_substr(trim((string)($_POST['zeit_text'] ?? '')), 0, 30);
            $info = mb_substr(trim((string)($_POST['info'] ?? '')), 0, 100);
            if ($id > 0) {
                $db->prepare("UPDATE einsatz_plan_termine SET bezeichnung = ?, datum = ?, zeit_von = ?, zeit_bis = ?, zeit_text = ?, info = ? WHERE id = ? AND plan_id = ?")
                   ->execute([$bez !== '' ? $bez : null, $datum, $von, $bis, $zt !== '' ? $zt : null, $info !== '' ? $info : null, $id, $planId]);
            } else {
                $sort = count($plan['termine']);
                $db->prepare("INSERT INTO einsatz_plan_termine (plan_id, bezeichnung, datum, zeit_von, zeit_bis, zeit_text, info, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$planId, $bez !== '' ? $bez : null, $datum, $von, $bis, $zt !== '' ? $zt : null, $info !== '' ? $info : null, $sort]);
                $id = (int)$db->lastInsertId();
                ep_slots_sicherstellen($db, $planId);
            }
            ep_projizieren_wenn_freigegeben($db, $planId);
            ep_json(['success' => true, 'id' => $id, 'message' => 'Termin gespeichert']);

        case 'termin_delete':
            $db->beginTransaction();
            $db->prepare("DELETE z FROM einsatz_zuweisungen z JOIN einsatz_plan_slots s ON s.id = z.slot_id WHERE s.plan_id = ? AND s.termin_id = ?")->execute([$planId, $id]);
            $db->prepare("DELETE FROM einsatz_plan_slots WHERE plan_id = ? AND termin_id = ?")->execute([$planId, $id]);
            $st = $db->prepare("DELETE FROM einsatz_plan_termine WHERE id = ? AND plan_id = ?");
            $st->execute([$id, $planId]);
            $db->commit();
            if ($st->rowCount() === 0) ep_json(['success' => false, 'message' => 'Termin nicht gefunden'], 404);
            ep_json(['success' => true, 'message' => 'Termin gelöscht']);

        case 'termine_aus_jm':
            $vorhanden = array_map(fn($t) => $t['datum'] . '|' . substr((string)$t['zeit_von'], 0, 5), $plan['termine']);
            $ins = $db->prepare("INSERT INTO einsatz_plan_termine (plan_id, bezeichnung, datum, zeit_von, zeit_bis, sort) VALUES (?, ?, ?, ?, ?, ?)");
            $n = 0;
            $idx = count($plan['termine']);
            foreach (ep_termine_aus_jm($db, $plan['typ'], (int)$plan['jahr']) as $t) {
                if (in_array($t['datum'] . '|' . substr((string)$t['zeit_von'], 0, 5), $vorhanden, true)) continue;
                $ins->execute([$planId, ep_termin_bezeichnung_auto($plan['typ'], $idx), $t['datum'], $t['zeit_von'], $t['zeit_bis'], $idx]);
                $idx++; $n++;
            }
            if ($n > 0) ep_slots_sicherstellen($db, $planId);
            ep_json(['success' => true, 'message' => $n > 0 ? $n . ' Termin(e) aus der JM-Definition übernommen' : 'Keine neuen Schiesstage in der JM-Definition gefunden']);

        case 'funktion_save':
            $bez = mb_substr(trim((string)($_POST['bezeichnung'] ?? '')), 0, 100);
            if ($bez === '') ep_json(['success' => false, 'message' => 'Bezeichnung fehlt'], 422);
            $gruppe = mb_substr(trim((string)($_POST['gruppe'] ?? '')), 0, 50);
            $anzahl = max(0, min(20, (int)($_POST['anzahl'] ?? 1)));
            $rolle  = trim((string)($_POST['rolle'] ?? ''));
            if ($rolle !== '' && !isset(EP_ROLLEN[$rolle])) ep_json(['success' => false, 'message' => 'Ungültige Rolle'], 422);
            if ($plan['layout'] === 'funktion_x_termin' && $anzahl < 1) $anzahl = 1;
            if ($id > 0) {
                if ($plan['layout'] === 'funktion_x_termin') {
                    $blockiert = ep_slots_kuerzen($db, $planId, $id, $anzahl);
                    if ($blockiert > 0) ep_json(['success' => false, 'message' => 'Kürzen nicht möglich: ' . $blockiert . ' besetzte Position(en) oberhalb der neuen Anzahl. Zuerst leeren.'], 409);
                }
                $db->prepare("UPDATE einsatz_plan_funktionen SET gruppe = ?, bezeichnung = ?, rolle = ?, anzahl = ? WHERE id = ? AND plan_id = ?")
                   ->execute([$gruppe !== '' ? $gruppe : null, $bez, $rolle !== '' ? $rolle : null, $anzahl, $id, $planId]);
            } else {
                $maxSort = 0; foreach ($plan['funktionen'] as $f) $maxSort = max($maxSort, (int)$f['sort']);
                $db->prepare("INSERT INTO einsatz_plan_funktionen (plan_id, gruppe, bezeichnung, rolle, anzahl, sort) VALUES (?, ?, ?, ?, ?, ?)")
                   ->execute([$planId, $gruppe !== '' ? $gruppe : null, $bez, $rolle !== '' ? $rolle : null, $anzahl, $maxSort + 10]);
                $id = (int)$db->lastInsertId();
            }
            ep_slots_sicherstellen($db, $planId);
            ep_projizieren_wenn_freigegeben($db, $planId);
            ep_json(['success' => true, 'id' => $id, 'message' => 'Funktion gespeichert']);

        case 'funktion_delete':
            $st = $db->prepare("SELECT COUNT(*) FROM einsatz_plan_slots WHERE plan_id = ? AND funktion_id = ? AND (mitglied_id IS NOT NULL OR (name_text IS NOT NULL AND name_text <> ''))");
            $st->execute([$planId, $id]);
            if ((int)$st->fetchColumn() > 0) ep_json(['success' => false, 'message' => 'Funktion hat besetzte Positionen – zuerst leeren'], 409);
            $db->beginTransaction();
            $db->prepare("DELETE FROM einsatz_plan_slots WHERE plan_id = ? AND funktion_id = ?")->execute([$planId, $id]);
            $st = $db->prepare("DELETE FROM einsatz_plan_funktionen WHERE id = ? AND plan_id = ?");
            $st->execute([$id, $planId]);
            $db->commit();
            if ($st->rowCount() === 0) ep_json(['success' => false, 'message' => 'Funktion nicht gefunden'], 404);
            ep_json(['success' => true, 'message' => 'Funktion gelöscht']);

        case 'funktion_move':
            $dir = ($_POST['dir'] ?? 'up') === 'down' ? 'down' : 'up';
            $liste = $plan['funktionen'];
            $pos = null;
            foreach ($liste as $i => $f) if ((int)$f['id'] === $id) { $pos = $i; break; }
            if ($pos === null) ep_json(['success' => false, 'message' => 'Funktion nicht gefunden'], 404);
            $ziel = $dir === 'up' ? $pos - 1 : $pos + 1;
            if ($ziel < 0 || $ziel >= count($liste)) ep_json(['success' => true, 'message' => 'Bereits am Rand']);
            // Reihenfolge komplett neu durchnummerieren (10er-Schritte), getauscht
            [$liste[$pos], $liste[$ziel]] = [$liste[$ziel], $liste[$pos]];
            $upd = $db->prepare("UPDATE einsatz_plan_funktionen SET sort = ? WHERE id = ? AND plan_id = ?");
            foreach ($liste as $i => $f) $upd->execute([($i + 1) * 10, (int)$f['id'], $planId]);
            ep_json(['success' => true, 'message' => 'Reihenfolge geändert']);

        case 'soll_save':
            $map = json_decode((string)($_POST['soll'] ?? '{}'), true);
            if (!is_array($map)) ep_json(['success' => false, 'message' => 'Ungültige Soll-Daten'], 422);
            $terminIds = array_map(fn($t) => (int)$t['id'], $plan['termine']);
            $funktionIds = array_map(fn($f) => (int)$f['id'], $plan['funktionen']);
            $db->beginTransaction();
            $up  = $db->prepare("INSERT INTO einsatz_plan_soll (plan_id, termin_id, funktion_id, soll) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE soll = VALUES(soll)");
            $del = $db->prepare("DELETE FROM einsatz_plan_soll WHERE plan_id = ? AND termin_id = ? AND funktion_id = ?");
            $n = 0;
            foreach ($map as $key => $wert) {
                [$tid, $fid] = array_map('intval', explode('|', (string)$key) + [0, 0]);
                if (!in_array($tid, $terminIds, true) || !in_array($fid, $funktionIds, true)) continue;
                $soll = max(0, min(99, (int)$wert));
                if ($soll > 0) $up->execute([$planId, $tid, $fid, $soll]); else $del->execute([$planId, $tid, $fid]);
                $n++;
            }
            $db->commit();
            ep_json(['success' => true, 'message' => 'Soll gespeichert (' . $n . ' Zellen)']);

        default:
            ep_json(['success' => false, 'message' => 'Unbekannte Aktion'], 400);
    }
} catch (InvalidArgumentException $e) {
    if ($db->inTransaction()) $db->rollBack();
    ep_json(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[einsatzplanung/struktur_save] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Änderung konnte nicht gespeichert werden'], 500);
}
