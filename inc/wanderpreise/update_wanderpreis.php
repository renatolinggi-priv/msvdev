<?php
// inc/wanderpreise/update_wanderpreis.php - Wanderpreis aktualisieren
if (session_status()===PHP_SESSION_NONE) session_start();
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }

if ($_SERVER['REQUEST_METHOD']!=='POST') {
    wanderpreise_json_response(false, 'Method not allowed', [], 405);
}
wanderpreise_check_csrf();

try {
  $conn = get_db_connection();

  $id                 = (int)($_POST['wanderpreis_id'] ?? $_POST['id'] ?? 0);
  $bezeichnung        = trim((string)($_POST['bezeichnung'] ?? ''));
  $beschreibung       = trim((string)($_POST['beschreibung'] ?? ''));
  $beschaffung_datum  = (int)($_POST['beschaffung_jahr'] ?? $_POST['beschaffung_datum'] ?? 0);
  $min_anz_gewinne    = (int)($_POST['min_anzahl_gewinne'] ?? 3);
  $hersteller         = trim((string)($_POST['hersteller'] ?? ''));
  $auto_verknuepfung  = !empty($_POST['auto_verknuepfung']) ? 1 : 0;
  $verknuepfung_regel = isset($_POST['verknuepfung_regel']) ? trim((string)$_POST['verknuepfung_regel']) : null;
  $verknuepfung_jahr  = (isset($_POST['verknuepfung_jahr']) && $_POST['verknuepfung_jahr']!=='')
                        ? (int)$_POST['verknuepfung_jahr'] : 0;

  if ($id<=0) wanderpreise_json_response(false, 'ID fehlt/ungültig', [], 400);
  if ($bezeichnung==='') wanderpreise_json_response(false, 'Bezeichnung fehlt', [], 400);

  if ($verknuepfung_regel===null || $verknuepfung_regel==='') {
    $sql = "UPDATE wanderpreise SET
              bezeichnung=?, beschreibung=?, beschaffung_datum=?, min_anzahl_gewinne=?, hersteller=?,
              auto_verknuepfung=?, verknuepfung_regel=NULL, verknuepfung_jahr=?, updated_at=NOW()
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    //          s   s   i   i   s   i   i   i
    $stmt->bind_param("ssiisiii", $bezeichnung,$beschreibung,$beschaffung_datum,$min_anz_gewinne,$hersteller,$auto_verknuepfung,$verknuepfung_jahr,$id);
  } else {
    $sql = "UPDATE wanderpreise SET
              bezeichnung=?, beschreibung=?, beschaffung_datum=?, min_anzahl_gewinne=?, hersteller=?,
              auto_verknuepfung=?, verknuepfung_regel=?, verknuepfung_jahr=?, updated_at=NOW()
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    //          s   s   i   i   s   i   s   i   i
    $stmt->bind_param("ssiisisii", $bezeichnung,$beschreibung,$beschaffung_datum,$min_anz_gewinne,$hersteller,$auto_verknuepfung,$verknuepfung_regel,$verknuepfung_jahr,$id);
  }

  if (!$stmt) wanderpreise_json_response(false, 'DB-Fehler (prepare): '.$conn->error, [], 500);
  if (!$stmt->execute()) wanderpreise_json_response(false, 'DB-Fehler (execute): '.$stmt->error, [], 500);

  wanderpreise_json_response(true, 'Wanderpreis aktualisiert', ['wanderpreis_id'=>$id]);
} catch (Throwable $e){
  wanderpreise_debug('Update Error', ['error' => $e->getMessage()]);
  wanderpreise_json_response(false, $e->getMessage(), [], 500);
}
