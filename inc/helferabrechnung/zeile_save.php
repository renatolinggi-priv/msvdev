<?php
/**
 * inc/helferabrechnung/zeile_save.php – manuelle Abrechnungszeile anlegen oder ändern (einsatz_abr_zeilen).
 *
 * POST id (0 = neu), plan_id, kategorie=einsatz|vorarbeit|ok_funktion, taetigkeit, verein,
 *      mitglied_id (0 = keins), name_text, stunden, bemerkung
 * Regeln: Verein ≠ msv → mitglied_id verworfen (Klartext); MSV mit Mitglied → name_text leer.
 *         Mindestens Tätigkeit ODER Person muss gesetzt sein.
 * Antwort: {success, message, id}
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/abrechnung.inc.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db     = getDB();
$id     = (int)($_POST['id'] ?? 0);
$planId = (int)($_POST['plan_id'] ?? 0);

$st = $db->prepare("SELECT id, typ FROM einsatz_plaene WHERE id = ?");
$st->execute([$planId]);
$plan = $st->fetch();
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);
if ($plan['typ'] !== 'schlossturm') ep_json(['success' => false, 'message' => 'Manuelle Zeilen gibt es nur für Schlossturm-Pläne'], 422);

$kategorie = $_POST['kategorie'] ?? 'vorarbeit';
if (!isset(HA_KATEGORIEN[$kategorie])) ep_json(['success' => false, 'message' => 'Ungültige Kategorie'], 422);
$verein = $_POST['verein'] ?? 'msv';
if (!isset(EP_VEREINE[$verein])) ep_json(['success' => false, 'message' => 'Ungültiger Verein'], 422);

$taetigkeit = mb_substr(trim((string)($_POST['taetigkeit'] ?? '')), 0, 150);
$mid        = (int)($_POST['mitglied_id'] ?? 0);
$nameText   = mb_substr(trim((string)($_POST['name_text'] ?? '')), 0, 100);
$bemerkung  = mb_substr(trim((string)($_POST['bemerkung'] ?? '')), 0, 255);
$rawS       = str_replace(',', '.', trim((string)($_POST['stunden'] ?? '0')));
if ($rawS === '') $rawS = '0';
if (!is_numeric($rawS) || (float)$rawS < 0 || (float)$rawS > 9999.99) ep_json(['success' => false, 'message' => 'Stunden müssen zwischen 0 und 9999.99 liegen'], 422);
$stunden = round((float)$rawS, 2);

if ($verein !== 'msv') {
    $mid = 0;
} elseif ($mid > 0) {
    $chk = $db->prepare("SELECT ID FROM mitglieder WHERE ID = ?");
    $chk->execute([$mid]);
    if (!$chk->fetchColumn()) ep_json(['success' => false, 'message' => 'Mitglied nicht gefunden'], 422);
    $nameText = '';
}
if ($taetigkeit === '' && $mid === 0 && $nameText === '') ep_json(['success' => false, 'message' => 'Tätigkeit oder Person angeben'], 422);
if ($taetigkeit === '') $taetigkeit = HA_KATEGORIEN[$kategorie];

try {
    if ($id > 0) {
        $st = $db->prepare("UPDATE einsatz_abr_zeilen SET kategorie = ?, taetigkeit = ?, verein = ?, mitglied_id = ?, name_text = ?, stunden = ?, bemerkung = ?
                            WHERE id = ? AND plan_id = ?");
        $st->execute([$kategorie, $taetigkeit, $verein, $mid ?: null, $nameText !== '' ? $nameText : null, $stunden, $bemerkung !== '' ? $bemerkung : null, $id, $planId]);
        if ($st->rowCount() === 0) {
            $chk = $db->prepare("SELECT id FROM einsatz_abr_zeilen WHERE id = ? AND plan_id = ?"); $chk->execute([$id, $planId]);
            if (!$chk->fetchColumn()) ep_json(['success' => false, 'message' => 'Zeile nicht gefunden'], 404);
        }
        $msg = 'Zeile gespeichert';
    } else {
        $sort = (int)$db->query("SELECT COALESCE(MAX(sort), 0) + 10 FROM einsatz_abr_zeilen WHERE plan_id = " . $planId)->fetchColumn();
        $st = $db->prepare("INSERT INTO einsatz_abr_zeilen (plan_id, kategorie, taetigkeit, verein, mitglied_id, name_text, stunden, bemerkung, sort, erstellt_von)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([$planId, $kategorie, $taetigkeit, $verein, $mid ?: null, $nameText !== '' ? $nameText : null, $stunden, $bemerkung !== '' ? $bemerkung : null, $sort, (int)($_SESSION['user_id'] ?? 0) ?: null]);
        $id  = (int)$db->lastInsertId();
        $msg = 'Zeile angelegt';
    }
} catch (Throwable $e) {
    error_log('[helferabrechnung/zeile_save] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Speichern fehlgeschlagen (Migration 058 ausgeführt?)'], 500);
}
ep_json(['success' => true, 'message' => $msg, 'id' => $id]);
