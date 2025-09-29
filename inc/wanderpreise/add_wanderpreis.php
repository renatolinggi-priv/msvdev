<?php
// wanderpreise/add_wanderpreis.php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }

function out($a,$c=200){ http_response_code($c); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function log_add($m){
  wanderpreise_debug('add_wanderpreis', ['message' => $m]);
}

try{
  if ($_SERVER['REQUEST_METHOD']!=='POST') wanderpreise_json_response(false, 'Method not allowed', [], 405);
  wanderpreise_check_csrf();

  $conn = get_db_connection();
  $conn->set_charset('utf8mb4');

  // --- Eingaben normalisieren ---
  $bezeichnung        = trim((string)($_POST['bezeichnung'] ?? ''));
  $beschreibung       = trim((string)($_POST['beschreibung'] ?? ''));
  $hersteller         = trim((string)($_POST['hersteller'] ?? ''));
  $min_anz_gewinne    = isset($_POST['min_anzahl_gewinne']) ? (int)$_POST['min_anzahl_gewinne'] : 3;
  $auto_verknuepfung  = !empty($_POST['auto_verknuepfung']) ? 1 : 0;

  // Jahr kann leer sein -> NULL (funktioniert für INT- oder DATE-Spalte)
  $beschaffung_raw    = trim((string)($_POST['beschaffung_jahr'] ?? $_POST['beschaffung_datum'] ?? ''));
  $beschaffung_datum  = ($beschaffung_raw === '') ? null : $beschaffung_raw;

  // Regel-Code als STRING (nicht casten). Leer => NULL
  $verknuepfung_regel = isset($_POST['verknuepfung_regel']) ? trim((string)$_POST['verknuepfung_regel']) : '';
  if ($verknuepfung_regel === '') $verknuepfung_regel = null;

  // Option C: 0/leer = alle Jahre
  $verknuepfung_jahr  = (isset($_POST['verknuepfung_jahr']) && $_POST['verknuepfung_jahr']!=='')
                        ? (int)$_POST['verknuepfung_jahr'] : 0;

  if ($bezeichnung==='') out(['success'=>false,'message'=>'Bezeichnung fehlt'],400);

  // (Optional) Regel-Code validieren – wenn unbekannt -> NULL, damit kein „0“-Müll
  if ($verknuepfung_regel !== null) {
    $chk = $conn->prepare("SELECT 1 FROM wanderpreise_regeln WHERE regel_code=? AND aktiv=1 LIMIT 1");
    if ($chk){
      $chk->bind_param("s",$verknuepfung_regel);
      $chk->execute();
      if (!$chk->get_result()->fetch_column()) $verknuepfung_regel = null;
    }
  }

  // --- Zwei SQL-Zweige: mit/ohne verknuepfung_regel (damit NULL sauber gesetzt wird) ---
  if ($verknuepfung_regel === null) {
    $sql = "INSERT INTO wanderpreise
            (bezeichnung,beschreibung,beschaffung_datum,min_anzahl_gewinne,hersteller,
             auto_verknuepfung,verknuepfung_regel,verknuepfung_jahr,created_at,updated_at)
            VALUES (?,?,?,?,?,?, NULL, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { log_add('prepare-ERR: '.$conn->error); out(['success'=>false,'message'=>'DB-Fehler (prepare): '.$conn->error],500); }

    //           s   s   s/i  i    s   i    i
    $stmt->bind_param("sssisii",
      $bezeichnung,
      $beschreibung,
      $beschaffung_datum,   // NULL oder Jahr-String
      $min_anz_gewinne,
      $hersteller,
      $auto_verknuepfung,
      $verknuepfung_jahr
    );
  } else {
    $sql = "INSERT INTO wanderpreise
            (bezeichnung,beschreibung,beschaffung_datum,min_anzahl_gewinne,hersteller,
             auto_verknuepfung,verknuepfung_regel,verknuepfung_jahr,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { log_add('prepare-ERR: '.$conn->error); out(['success'=>false,'message'=>'DB-Fehler (prepare): '.$conn->error],500); }

    //           s   s   s/i  i    s   i   s   i
    $stmt->bind_param("ssiisisi",
      $bezeichnung,
      $beschreibung,
      $beschaffung_datum,   // NULL oder Jahr-String
      $min_anz_gewinne,
      $hersteller,
      $auto_verknuepfung,
      $verknuepfung_regel,  // STRING!
      $verknuepfung_jahr
    );
  }

  if (!$stmt->execute()) {
    log_add('execute-ERR: '.$stmt->error.' | SQL='.$sql.' | POST='.json_encode($_POST));
    out(['success'=>false,'message'=>'DB-Fehler (execute): '.$stmt->error],500);
  }
  if ($stmt->affected_rows < 1) {
    log_add('affected_rows=0 | SQL='.$sql.' | POST='.json_encode($_POST));
    out(['success'=>false,'message'=>'Insert ergab 0 betroffene Zeilen'],500);
  }

  out(['success'=>true,'message'=>'Wanderpreis erfolgreich hinzugefügt','wanderpreis_id'=>$conn->insert_id]);
} catch (Throwable $e){
  log_add('Throwable: '.$e->getMessage());
  out(['success'=>false,'message'=>$e->getMessage()],500);
}
