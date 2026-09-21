<?php
/**
 * inc/helferabrechnung/zeile_ok_vorschlag.php – OK-Funktionen (je 50 h) für die OK-Mitglieder des Plans vorschlagen.
 *
 * OK-Mitglieder = Personen mit OK-Kennzeichen im Plan (Position, Funktion mit Rolle «OK» oder Stammliste).
 * Für jede Person ohne bestehende Zeile der Kategorie ok_funktion wird eine Zeile «OK-Funktion» mit
 * HA_OK_FUNKTION_STD Stunden angelegt (Tätigkeit anschliessend präzisieren: Präsident, Kasse …).
 * GET  plan_id → {success, vorschlag:[{name, verein}], vorhanden:n}   (Vorschau)
 * POST plan_id → {success, message, angelegt}
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/abrechnung.inc.php';

header('Content-Type: application/json; charset=utf-8');
$db     = getDB();
$planId = (int)($_REQUEST['plan_id'] ?? 0);

try {
    $plan = ha_plan_waehlen($db, 0, $planId);
    if (!$plan) ep_json(['success' => false, 'message' => 'Schlossturm-Plan nicht gefunden'], 404);

    // OK-Personen aus den Zuteilungen (Position, Funktion) plus Stammliste
    $zuteilungen = ha_zuteilungen($plan, ep_mitglieder_map($db), true);
    $stamm = ep_ok_stamm_laden($db);
    $ok = [];
    foreach ($zuteilungen as $z) {
        if (!$z['ok'] && !ep_ok_stamm_hat($stamm, (int)$z['mitglied_id'], $z['mitglied_id'] ? '' : $z['person'])) continue;
        $ok[$z['person_key']] ??= ['name' => $z['person'], 'verein' => $z['verein'], 'mitglied_id' => $z['mitglied_id'], 'name_text' => $z['mitglied_id'] ? null : $z['person']];
    }
    $vorhanden = [];
    foreach (ha_manuell($db, (int)$plan['id']) as $m) if ($m['kategorie'] === 'ok_funktion') $vorhanden[$m['person_key']] = true;
    $neu = array_filter($ok, fn($p, $k) => !isset($vorhanden[$k]), ARRAY_FILTER_USE_BOTH);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ep_json(['success' => true, 'vorschlag' => array_values(array_map(fn($p) => ['name' => $p['name'], 'verein' => EP_VEREINE[$p['verein']] ?? $p['verein']], $neu)), 'vorhanden' => count($vorhanden), 'stunden' => HA_OK_FUNKTION_STD]);
    }
    csrf_require(true);
    if (!$neu) ep_json(['success' => false, 'message' => $ok ? 'Alle OK-Mitglieder haben bereits eine OK-Funktion' : 'Keine OK-Mitglieder im Plan (Dialog «OK-Mitglieder» im Editor)'], 422);

    $sort = (int)$db->query("SELECT COALESCE(MAX(sort), 0) FROM einsatz_abr_zeilen WHERE plan_id = " . (int)$plan['id'])->fetchColumn();
    $ins = $db->prepare("INSERT INTO einsatz_abr_zeilen (plan_id, kategorie, taetigkeit, verein, mitglied_id, name_text, stunden, bemerkung, sort, erstellt_von) VALUES (?, 'ok_funktion', ?, ?, ?, ?, ?, ?, ?, ?)");
    $uid = (int)($_SESSION['user_id'] ?? 0) ?: null;
    $db->beginTransaction();
    foreach ($neu as $p) {
        $sort += 10;
        $ins->execute([(int)$plan['id'], 'OK – Funktion', $p['verein'], $p['mitglied_id'], $p['name_text'], HA_OK_FUNKTION_STD, 'Vorschlag aus OK-Mitgliedern – Funktion bitte präzisieren', $sort, $uid]);
    }
    $db->commit();
    ep_json(['success' => true, 'message' => count($neu) . ' OK-Funktion(en) à ' . ha_fmt(HA_OK_FUNKTION_STD) . ' h angelegt – Bezeichnung bitte anpassen', 'angelegt' => count($neu)]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[helferabrechnung/zeile_ok_vorschlag] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Vorschlag fehlgeschlagen (Migration 058 ausgeführt?)'], 500);
}
