<?php
// wanderpreise/delete_vergangener_gewinner.php
// Löscht einen historischen Gewinner-Eintrag

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }

// Method Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wanderpreise_json_response(false, 'Method not allowed', [], 405);
}

// CSRF Check
wanderpreise_check_csrf();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    wanderpreise_json_response(false, 'Ungültige ID', [], 400);
}

$conn = get_db_connection();
if (!$conn) {
    wanderpreise_json_response(false, 'Keine DB-Verbindung', [], 500);
}
$conn->set_charset('utf8mb4');

// Eintrag holen (für Folge-Logik)
$sel = $conn->prepare("SELECT id, wanderpreis_id, gewinner_id, jahr FROM wanderpreise_gewinner WHERE id=? LIMIT 1");
if (!$sel) {
    wanderpreise_json_response(false, 'DB-Fehler (prepare select): '.$conn->error, [], 500);
}
$sel->bind_param("i", $id);
$sel->execute();
$res = $sel->get_result();
if (!$res || !$res->num_rows) {
    wanderpreise_json_response(false, 'Eintrag nicht gefunden', [], 404);
}
$row = $res->fetch_assoc();
$sel->close();

$wanderpreis_id = (int)$row['wanderpreis_id'];
$gewinner_id    = (int)$row['gewinner_id'];

// Mindestgewinne des Preises ermitteln
$mp = $conn->prepare("SELECT min_anzahl_gewinne, gewinner_id AS aktueller_besitzer FROM wanderpreise WHERE id=?");
if (!$mp) {
    wanderpreise_json_response(false, 'DB-Fehler (prepare preis): '.$conn->error, [], 500);
}
$mp->bind_param("i", $wanderpreis_id);
$mp->execute();
$pr = $mp->get_result()->fetch_assoc();
$mp->close();
$min_anz = isset($pr['min_anzahl_gewinne']) ? (int)$pr['min_anzahl_gewinne'] : 3;
$aktueller_besitzer = isset($pr['aktueller_besitzer']) ? (int)$pr['aktueller_besitzer'] : null;

// Löschen
$del = $conn->prepare("DELETE FROM wanderpreise_gewinner WHERE id=?");
if (!$del) {
    wanderpreise_json_response(false, 'DB-Fehler (prepare delete): '.$conn->error, [], 500);
}
$del->bind_param("i", $id);
if (!$del->execute()) {
    wanderpreise_json_response(false, 'DB-Fehler (execute delete): '.$del->error, [], 500);
}
$del->close();

// Neue Anzahl Gewinne dieses Mitglieds für diesen Preis
$cnt = $conn->prepare("SELECT COUNT(*) AS c FROM wanderpreise_gewinner WHERE wanderpreis_id=? AND gewinner_id=?");
if (!$cnt) {
    wanderpreise_json_response(false, 'DB-Fehler (prepare count): '.$conn->error, [], 500);
}
$cnt->bind_param("ii", $wanderpreis_id, $gewinner_id);
$cnt->execute();
$anz = (int)($cnt->get_result()->fetch_assoc()['c'] ?? 0);
$cnt->close();

// Restliche Zeilen für dieses Mitglied bei diesem Preis konsistent setzen
$ist_def = ($anz >= $min_anz) ? 1 : 0;
$upd = $conn->prepare("UPDATE wanderpreise_gewinner SET anzahl_gewinne=?, ist_definitiv=? WHERE wanderpreis_id=? AND gewinner_id=?");
if ($upd) {
  $upd->bind_param("iiii", $anz, $ist_def, $wanderpreis_id, $gewinner_id);
  $upd->execute();
  $upd->close();
}

// Optional: Besitzer korrigieren, falls der gerade gelöschte Gewinner der Besitzer war und jetzt die Schwelle unterschreitet
if ($aktueller_besitzer && $aktueller_besitzer === $gewinner_id && !$ist_def) {
  // Einfach auf NULL setzen (keine Heuristik, wer sonst Besitzer wäre)
  if ($fix = $conn->prepare("UPDATE wanderpreise SET gewinner_id=NULL, verknuepfung_jahr=NULL, updated_at=NOW() WHERE id=?")) {
    $fix->bind_param("i", $wanderpreis_id);
    $fix->execute();
    $fix->close();
  }
}

wanderpreise_json_response(true, 'Vergangener Gewinner gelöscht', [
    'wanderpreis_id' => $wanderpreis_id,
    'gewinner_id' => $gewinner_id,
    'anzahl_gewinne' => $anz,
    'ist_definitiv' => $ist_def
]);
