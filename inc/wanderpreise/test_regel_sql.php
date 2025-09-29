<?php
// inc/wanderpreise/test_regel_sql.php – überarbeitete Variante
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }
ini_set('display_errors', '0');
error_reporting(E_ALL);

function out($arr, $code=200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // DB holen (wie in Deiner Umgebung vorgesehen)
  if (function_exists('get_db_connection')) {
    $conn = get_db_connection();
  } elseif (function_exists('connect_db_mysqli')) {
    $conn = connect_db_mysqli();
  } else {
    throw new RuntimeException('Keine DB-Verbindungsfunktion gefunden.');
  }
  if (!$conn) out(['success'=>false, 'message'=>'Datenbankverbindung fehlgeschlagen'], 500);

  // --- Eingaben ---
  $in   = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

  // CSRF
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
  }
  $csrf = (string)($in['csrf_token'] ?? '');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    out(['success'=>false, 'message'=>'Ungültiger CSRF-Token'], 403);
  }

  // Parameter
  $sql_raw = trim((string)($in['sql'] ?? ''));
  $regel   = trim((string)($in['regel'] ?? ''));      // Code ("jahresmeisterschaftA") ODER ID ("12")
  $jahr    = isset($in['jahr']) ? (int)$in['jahr'] : (int)date('Y');
  $wpid    = isset($in['wanderpreis_id']) ? (int)$in['wanderpreis_id'] : 1;

  // Kategorie (optional per Param); sonst aus Regel-Code A/B ableiten
  $katParam = trim((string)($in['kategorie'] ?? ''));
  if ($katParam !== '') {
    $kategorie = $katParam;
  } else {
    $kategorie = '';
    if ($regel !== '') {
      if (preg_match('/A$/i', $regel))      $kategorie = 'Kat. A';
      elseif (preg_match('/B$/i', $regel))  $kategorie = 'Kat. B';
    }
  }

  if ($sql_raw === '' && $regel === '') {
    out(['success'=>false, 'message'=>'Keine SQL-Abfrage angegeben und keine Regel gewählt (Parameter "sql" oder "regel").'], 400);
  }

  // Falls keine SQL direkt mitgegeben wurde: aus Tabelle laden (aktiv = 1)
  if ($sql_raw === '' && $regel !== '') {
    if (ctype_digit($regel)) {
      $st = $conn->prepare("SELECT sql_query FROM wanderpreise_regeln WHERE id = ? AND aktiv = 1");
      if (!$st) out(['success'=>false, 'message'=>'DB-Fehler (prepare): '.$conn->error], 500);
      $rid = (int)$regel;
      $st->bind_param("i", $rid);
    } else {
      $st = $conn->prepare("SELECT sql_query FROM wanderpreise_regeln WHERE regel_code = ? AND aktiv = 1");
      if (!$st) out(['success'=>false, 'message'=>'DB-Fehler (prepare): '.$conn->error], 500);
      $st->bind_param("s", $regel);
    }
    if (!$st->execute()) out(['success'=>false, 'message'=>'DB-Fehler (execute): '.$st->error], 500);
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row || empty($row['sql_query'])) out(['success'=>false, 'message'=>'Regel nicht gefunden oder inaktiv.'], 404);
    $sql_raw = (string)$row['sql_query'];
  }

  // --- Platzhalter ersetzen ---
  $sql = str_replace(
    ['{jahr}', '{wanderpreis_id}', '{kategorie}'],
    [$jahr, $wpid, $kategorie],
    $sql_raw
  );

  // --- SET-Variablen-Zeilen entfernen & @year/@kategorie inline ersetzen ---
  $hadSet = (stripos($sql, 'SET @') !== false);
  // ganze SET-Zeilen zu @year/@kategorie entfernen (robust gegen Spaces/Quotes)
  $sql = preg_replace('/\bSET\s+@year\s*=\s*[^;]+;?\s*/i', '', $sql);
  $sql = preg_replace('/\bSET\s+@kategorie\s*=\s*[^;]+;?\s*/i', '', $sql);

  // Vorkommen der Session-Variablen ersetzen
  $sql = str_replace(
    ['@year', '@kategorie'],
    [
      (string)$jahr,
      ($kategorie !== '' ? ("'".$conn->real_escape_string($kategorie)."'") : "''")
    ],
    $sql
  );

  // Normieren: äußeres trailing Semikolon weg
  $sql = preg_replace('/;\s*$/', '', trim($sql));

  // Art erkennen (WITH oder SELECT am Start)
  $isSelectLike = (bool)preg_match('/^\s*(WITH|SELECT)\b/i', $sql);

  // Nur bei Single-Statement-SELECT ohne LIMIT -> LIMIT 1 anhängen (Test spart Last)
  $isSingleStatement = (strpos($sql, ';') === false);
  if ($isSelectLike && $isSingleStatement && !preg_match('/\bLIMIT\b/i', $sql)) {
    $sql .= ' LIMIT 1';
  }

  // --- Ausführen ---
  $rows = [];
  $used_multi = false;

  if (!$isSingleStatement) {
    // Multi-Statements (z.B. verbliebene Statements) -> letztes Resultset nehmen
    $used_multi = true;
    if (!$conn->multi_query($sql)) {
      out(['success'=>false, 'message'=>'SQL-Fehler: '.$conn->error, 'sql'=>$sql], 400);
    }
    do {
      if ($result = $conn->store_result()) {
        $tmp = [];
        while ($r = $result->fetch_assoc()) $tmp[] = $r;
        $result->free();
        if (!empty($tmp)) $rows = $tmp; // jeweils letztes nicht-leeres Resultset behalten
      }
    } while ($conn->more_results() && $conn->next_result());
  } else {
    // Single
    $result = $conn->query($sql);
    if ($result === false) {
      out(['success'=>false, 'message'=>'SQL-Fehler: '.$conn->error, 'sql'=>$sql], 400);
    }
    if ($result instanceof mysqli_result) {
      while ($r = $result->fetch_assoc()) $rows[] = $r;
      $result->free();
    }
  }

  // --- Auswertung ---
  if ($isSelectLike || !empty($rows)) {
    if (empty($rows)) {
      out([
        'success'=>false,
        'message'=>'Die SQL-Abfrage lieferte keine Ergebnisse. Prüfe Daten für Jahr '.$jahr.'.',
        'sql'=>$sql,
        'jahr'=>$jahr,
        'wanderpreis_id'=>$wpid,
        'used_multi'=>$used_multi
      ], 404);
    }

    $row = $rows[0];

    if (!array_key_exists('gewinner_id', $row)) {
      out([
        'success'=>false,
        'message'=>'SQL muss ein Feld "gewinner_id" zurückgeben.',
        'fields'=>array_keys($row),
        'sql'=>$sql,
        'jahr'=>$jahr,
        'wanderpreis_id'=>$wpid,
        'used_multi'=>$used_multi
      ], 400);
    }

    // Mitgliedsname (optional)
    $mitglied_name = 'Unbekannt';
    if (!empty($row['gewinner_id'])) {
      $stmt = $conn->prepare("SELECT CONCAT(Name, ' ', Vorname) AS name FROM mitglieder WHERE ID = ?");
      if ($stmt) {
        $stmt->bind_param("i", $row['gewinner_id']);
        $stmt->execute();
        $mr = $stmt->get_result();
        if ($mr && $mr->num_rows > 0) {
          $mitglied_name = (string)$mr->fetch_assoc()['name'];
        } else {
          $mitglied_name = 'Mitglied ID '.$row['gewinner_id'].' (nicht gefunden)';
        }
      }
    }

    $msg = "✓ SQL OK – Gewinner: ".$mitglied_name;
    if (isset($row['resultat'])) $msg .= " | Resultat: ".$row['resultat'];
    if (isset($row['rang']))     $msg .= " | Rang: ".$row['rang'];

    out([
      'success'=>true,
      'message'=>$msg,
      'data'=>$row,
      'sql'=>$sql,
      'jahr'=>$jahr,
      'wanderpreis_id'=>$wpid,
      'kategorie'=>$kategorie,
      'used_multi'=>$used_multi
    ]);
  } else {
    // Kein Resultset (z.B. UPDATE/INSERT) – hier unüblich, aber der Vollständigkeit halber
    out([
      'success'=>true,
      'message'=>'SQL ausgeführt.',
      'affected_rows'=>$conn->affected_rows,
      'sql'=>$sql,
      'jahr'=>$jahr,
      'wanderpreis_id'=>$wpid,
      'kategorie'=>$kategorie,
      'used_multi'=>$used_multi
    ]);
  }

} catch (Throwable $e) {
  out(['success'=>false, 'message'=>$e->getMessage()], 500);
} finally {
  if (isset($conn) && $conn instanceof mysqli) $conn->close();
}
