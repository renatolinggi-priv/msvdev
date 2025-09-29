<?php
// inc/wanderpreise/get_wanderpreis_historie.php
// Liefert JSON ohne HTML-Vorabgaben (für Ajax).

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');

// Eingabe prüfen
$wanderpreis_id = isset($_GET['wanderpreis_id']) ? (int)$_GET['wanderpreis_id'] : 0;
if ($wanderpreis_id <= 0) {
    wanderpreise_json_response(false, 'Ungültige oder fehlende wanderpreis_id', [], 400);
}

// DB holen
$mysqli = get_db_connection();
if (!$mysqli) {
    wanderpreise_json_response(false, 'Keine DB-Verbindung verfügbar', [], 500);
}
$mysqli->set_charset('utf8mb4');

// Wanderpreis-Stammdaten laden (Spalten laut Schema)
$stmt = $mysqli->prepare("
  SELECT id, bezeichnung, beschreibung, beschaffung_datum, min_anzahl_gewinne, hersteller
  FROM wanderpreise
  WHERE id = ?
");
if (!$stmt) {
    wanderpreise_json_response(false, 'DB-Fehler (prepare wanderpreise): '.$mysqli->error, [], 500);
}
$stmt->bind_param('i', $wanderpreis_id);
if (!$stmt->execute()) {
    wanderpreise_json_response(false, 'DB-Fehler (execute wanderpreise): '.$stmt->error, [], 500);
}
$wanderpreis = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wanderpreis) {
    wanderpreise_json_response(false, 'Wanderpreis nicht gefunden', [], 404);
}

// Gewinnerhistorie laden (richtige Tabelle + Join auf mitglieder)
$sqlGew = "
  SELECT 
   g.id AS eintrag_id,
    g.jahr,
    CONCAT(m.Vorname, ' ', m.Name) AS name,
    g.rang,
    g.resultat,
    g.bemerkung,
    g.ist_definitiv,
    g.anzahl_gewinne
  FROM wanderpreise_gewinner g
  JOIN mitglieder m ON m.ID = g.gewinner_id
  WHERE g.wanderpreis_id = ?
  ORDER BY g.jahr DESC
";
$stmt = $mysqli->prepare($sqlGew);
if (!$stmt) {
    wanderpreise_json_response(false, 'DB-Fehler (prepare gewinner): '.$mysqli->error, [], 500);
}
$stmt->bind_param('i', $wanderpreis_id);
if (!$stmt->execute()) {
    wanderpreise_json_response(false, 'DB-Fehler (execute gewinner): '.$stmt->error, [], 500);
}
$res = $stmt->get_result();

$gewinner = [];
while ($row = $res->fetch_assoc()) {
  $gewinner[] = [
    'eintrag_id'     => (int)$row['eintrag_id'],   
    'jahr'          => (int)$row['jahr'],
    'name'          => $row['name'],
    'rang'          => $row['rang'],
    'resultat'      => $row['resultat'],
    'bemerkung'     => $row['bemerkung'],
    'ist_definitiv' => isset($row['ist_definitiv']) ? (int)$row['ist_definitiv'] : 0,
    'anzahl_gewinne'=> isset($row['anzahl_gewinne']) ? (int)$row['anzahl_gewinne'] : 1,
  ];
}
$stmt->close();

// Antwort
wanderpreise_json_response(true, 'Historie geladen', [
  'wanderpreis' => $wanderpreis,
  'gewinner'    => $gewinner
]);
