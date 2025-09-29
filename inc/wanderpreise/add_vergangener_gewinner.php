<?php
// wanderpreise/add_vergangener_gewinner.php
// Legt einen historischen Gewinner an (belässt den „aktuellen“ unberührt)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }

// Method & CSRF Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  wanderpreise_json_response(false, 'Method not allowed', [], 405);
}
wanderpreise_check_csrf();

// Eingaben prüfen
$req = ['wanderpreis_id','gewinner_id','jahr'];
foreach ($req as $f) {
  if (!isset($_POST[$f]) || $_POST[$f] === '') {
    wanderpreise_json_response(false, "Feld '$f' ist erforderlich", [], 400);
  }
}
$wanderpreis_id = (int)$_POST['wanderpreis_id'];
$gewinner_id    = (int)$_POST['gewinner_id'];
$jahr           = (int)$_POST['jahr'];
$rang           = trim((string)($_POST['rang']      ?? ''));
$resultat       = trim((string)($_POST['resultat']  ?? ''));
$bemerkung      = trim((string)($_POST['bemerkung'] ?? ''));

if ($wanderpreis_id <= 0 || $gewinner_id <= 0 || $jahr < 1900 || $jahr > 2100) {
  wanderpreise_json_response(false, 'Ungültige Eingaben', [], 400);
}

// Datenbankverbindung
$conn = get_db_connection();
if (!$conn) {
  wanderpreise_json_response(false, 'Keine DB-Verbindung', [], 500);
}
$conn->set_charset('utf8mb4');

// ---- Existiert für das Jahr schon ein Eintrag? (ein Preis pro Jahr) ---------
$chk = $conn->prepare("SELECT id FROM wanderpreise_gewinner WHERE wanderpreis_id = ? AND jahr = ? LIMIT 1");
if (!$chk) {
  wanderpreise_json_response(false, 'DB-Fehler (prepare check): '.$conn->error, [], 500);
}
$chk->bind_param("ii", $wanderpreis_id, $jahr);
$chk->execute();
$already = $chk->get_result()->num_rows > 0;
$chk->close();
if ($already) {
  wanderpreise_json_response(false, 'Für dieses Jahr existiert bereits ein Eintrag', [], 409);
}

// ---- Bisherige Gewinne dieses Mitglieds bei diesem Preis zählen --------------
$c = $conn->prepare("SELECT COUNT(*) AS c FROM wanderpreise_gewinner WHERE wanderpreis_id = ? AND gewinner_id = ?");
if (!$c) {
  wanderpreise_json_response(false, 'DB-Fehler (prepare count): '.$conn->error, [], 500);
}
$c->bind_param("ii", $wanderpreis_id, $gewinner_id);
$c->execute();
$anz_bisher = (int)$c->get_result()->fetch_assoc()['c'];
$c->close();

$anzahl_gewinne = $anz_bisher + 1; // inkl. aktuellem Eintrag

// ---- min_anzahl_gewinne für den Preis holen ---------------------------------
$m = $conn->prepare("SELECT min_anzahl_gewinne FROM wanderpreise WHERE id = ?");
if (!$m) {
  wanderpreise_json_response(false, 'DB-Fehler (prepare min_anz): '.$conn->error, [], 500);
}
$m->bind_param("i", $wanderpreis_id);
$m->execute();
$row = $m->get_result()->fetch_assoc();
$m->close();
$min_anz = isset($row['min_anzahl_gewinne']) ? (int)$row['min_anzahl_gewinne'] : 3;

$ist_def = ($anzahl_gewinne >= $min_anz) ? 1 : 0;

// ---- Insert ------------------------------------------------------------------
$sql = "INSERT INTO wanderpreise_gewinner
        (wanderpreis_id, gewinner_id, jahr, rang, resultat, bemerkung, ist_definitiv, anzahl_gewinne, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?, ?, NOW(), NOW())";
$ins = $conn->prepare($sql);
if (!$ins) {
  wanderpreise_json_response(false, 'DB-Fehler (prepare insert): '.$conn->error, [], 500);
}
$ins->bind_param("iiisssii", $wanderpreis_id, $gewinner_id, $jahr, $rang, $resultat, $bemerkung, $ist_def, $anzahl_gewinne);
if (!$ins->execute()) {
  wanderpreise_json_response(false, 'DB-Fehler (execute insert): '.$ins->error, [], 500);
}
$ins->close();

// Optional: Wenn Besitz jetzt definitiv -> im Stamm markieren (non-blocking)
if ($ist_def) {
  if ($u = $conn->prepare("UPDATE wanderpreise SET gewinner_id = ?, verknuepfung_jahr = ?, updated_at = NOW() WHERE id = ?")) {
    $u->bind_param("iii", $gewinner_id, $jahr, $wanderpreis_id);
    $u->execute();
    $u->close();
  }
}

// Erfolg
wanderpreise_json_response(true, 'Vergangener Gewinner gespeichert', [
  'ist_definitiv'  => $ist_def,
  'anzahl_gewinne' => $anzahl_gewinne
]);
