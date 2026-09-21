<?php
/**
 * inc/einsatzplanung/ok_person_save.php – OK-Kennzeichen (Organisationskomitee) für eine Person setzen.
 *
 * OK ist eine Eigenschaft der Person am Anlass (im Original steht «OK» statt «x» bei allen ihren Positionen),
 * darum werden alle Slots der Person im Plan gemeinsam gesetzt (Migration 058, slots.ok).
 * Zusätzlich «dauerhaft»: Person in die Stammliste einsatz_ok_personen (Migration 060) aufnehmen bzw. daraus
 * entfernen – gilt dann automatisch in jedem künftigen Plan (Import, Kopie, Setzen einer Position).
 *
 * POST plan_id, mitglied_id (>0) ODER name_text, und mindestens eins von:
 *      ok=0|1        OK im Plan (alle Positionen der Person)
 *      dauerhaft=0|1 Stammliste; dauerhaft=1 setzt zugleich ok=1 im Plan
 * Antwort: {success, message, ok, dauerhaft, slot_ids:[…]}
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/plan_helpers.inc.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db        = getDB();
$planId    = (int)($_POST['plan_id'] ?? 0);
$mid       = (int)($_POST['mitglied_id'] ?? 0);
$nameText  = mb_substr(trim((string)($_POST['name_text'] ?? '')), 0, 100);
$ok        = array_key_exists('ok', $_POST) ? ((int)$_POST['ok'] === 1 ? 1 : 0) : null;
$dauerhaft = array_key_exists('dauerhaft', $_POST) ? ((int)$_POST['dauerhaft'] === 1 ? 1 : 0) : null;
if ($mid <= 0 && $nameText === '') ep_json(['success' => false, 'message' => 'Person fehlt'], 422);
if ($ok === null && $dauerhaft === null) ep_json(['success' => false, 'message' => 'Nichts zu ändern'], 422);
if ($dauerhaft === 1) $ok = 1;   // dauerhaft OK → auch in diesem Plan

try {
    $meldung = [];
    if ($dauerhaft !== null) {
        if ($dauerhaft === 1) {
            $db->prepare("INSERT INTO einsatz_ok_personen (mitglied_id, name_text, erstellt_von) VALUES (?, ?, ?)
                          ON DUPLICATE KEY UPDATE erstellt_von = VALUES(erstellt_von)")
               ->execute([$mid > 0 ? $mid : null, $mid > 0 ? null : $nameText, (int)($_SESSION['user_id'] ?? 0) ?: null]);
            $meldung[] = 'dauerhaft als OK gemerkt';
        } else {
            if ($mid > 0) $db->prepare("DELETE FROM einsatz_ok_personen WHERE mitglied_id = ?")->execute([$mid]);
            else $db->prepare("DELETE FROM einsatz_ok_personen WHERE name_text = ?")->execute([$nameText]);
            $meldung[] = 'aus der Stammliste entfernt (Plan unverändert)';
        }
    }

    $ids = [];
    if ($ok !== null) {
        if ($mid > 0) { $st = $db->prepare("SELECT id FROM einsatz_plan_slots WHERE plan_id = ? AND mitglied_id = ?"); $st->execute([$planId, $mid]); }
        else { $st = $db->prepare("SELECT id FROM einsatz_plan_slots WHERE plan_id = ? AND mitglied_id IS NULL AND name_text = ?"); $st->execute([$planId, $nameText]); }
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        if ($ids) {
            $db->prepare("UPDATE einsatz_plan_slots SET ok = ? WHERE id IN (" . implode(',', $ids) . ")")->execute([$ok]);
            $meldung[] = ($ok ? 'OK gesetzt' : 'OK entfernt') . ' (' . count($ids) . ' Position' . (count($ids) === 1 ? '' : 'en') . ')';
        } elseif ($dauerhaft === null) {
            ep_json(['success' => false, 'message' => 'Keine Positionen dieser Person im Plan'], 404);
        }
    }
    ep_json(['success' => true, 'message' => ucfirst(implode(', ', $meldung)), 'ok' => $ok, 'dauerhaft' => $dauerhaft, 'slot_ids' => $ids]);
} catch (Throwable $e) {
    error_log('[einsatzplanung/ok_person_save] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Speichern fehlgeschlagen (Migrationen 058/060 ausgeführt?)'], 500);
}
