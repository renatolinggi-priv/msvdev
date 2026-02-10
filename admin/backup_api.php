<?php
/**
 * admin/backup_api.php â€“ robuste Backup/Restore API (ohne Shell-Pipe)
 * Actions: backup, list, download, delete, restore, diag, whoami, echo
 * Auth:   UI via Session+CSRF  ODER  extern via API-Key (Header X-API-Key / ?key=)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// ----------- Debug / Output -----------
header('Content-Type: application/json; charset=utf-8');
while (ob_get_level()) { ob_end_clean(); }
ini_set('display_errors','0'); error_reporting(E_ALL);

// Debug-Schalter: per ENV BACKUP_DEBUG=1 aktivierbar
$DEBUG = getenv('BACKUP_DEBUG') ? true : false;
// $DEBUG = true; // <- zum harten Aktivieren
function dbg($msg){ global $DEBUG; if ($DEBUG) error_log('[BACKUP_API] '.$msg); }

// Helfer
function out($a,$c=200){ http_response_code($c); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function sh($s){ return escapeshellarg($s); }

// ----------- DB-Konfig laden (Pfad ggf. anpassen) -----------
function load_db_conf(): array {
  $file = dirname(__DIR__, 2) . '/msvjm_config.php';
  if (is_file($file)) {
    $cfg = require $file;
    if (isset($cfg['db'])) return $cfg;
  }
  out(['success'=>false,'message'=>'DB-Konfiguration nicht gefunden'],500);
}

// ----------- Pfade / Config -----------
$CONF = load_db_conf(); // enthält mind. ['db'=>...], optional ['backup'=>['api_key'=>...]]
$BACKUP_DIR = dirname(__DIR__) . '/backups';

// API-Key optional (für externe Aufrufe). Leer lassen => nur CSRF/Session
$BACKUP_API_KEY = $CONF['backup']['api_key'] ?? (getenv('BACKUP_API_KEY') ?: '');

// ----------- Auth -----------
/**
 * Erlaubt EINES von beidem:
 * 1) Session + gültiger CSRF-Token (UI-intern)
 * 2) Gültiger API-Key (extern, Header X-API-Key oder ?key=)
 */
function require_key(string $expected){
  // 1) CSRF/Session (UI)
  $csrf = $_REQUEST['csrf_token'] ?? '';
  $hasSess = !empty($_SESSION['csrf_token']);
  $csrfOk = $csrf && $hasSess && hash_equals($_SESSION['csrf_token'], $csrf);

  dbg('Auth check: csrf_sent='.(bool)$csrf.' sess_has='.($hasSess?'1':'0').' csrf_ok='.($csrfOk?'1':'0'));
  if ($csrfOk) return;

  // 2) API-Key (extern)
  $got = $_GET['key'] ?? $_POST['key'] ?? '';
  if (!$got && isset($_SERVER['HTTP_X_API_KEY'])) $got = $_SERVER['HTTP_X_API_KEY'];

  $expected = (string)$expected;
  // Maskierte Ausgabe für Logs (keine Geheimnisse)
  $mask = function($s){ if($s==='') return 'âˆ…'; $len=strlen($s); return $len<6 ? str_repeat('*',$len) : substr($s,0,3).'…'.substr($s,-2)." (len:$len)"; };
  dbg('Auth check: key_sent='.$mask($got).' key_expected='.$mask($expected).' eq='.(($expected!=='' && $got!=='' && hash_equals($expected,$got))?'1':'0'));

  if ($expected !== '' && $got !== '' && hash_equals($expected, $got)) return;

  // 3) unauthorized â€“ mit Hinweisen (ohne Geheimnisse)
  out([
    'success'=>false,
    'message'=>'Unauthorized',
    'hints'=>[
      'csrf_token_sent'=> (bool)$csrf,
      'csrf_token_in_session'=> $hasSess,
      'api_key_sent'=> $got!=='' ? true : false,
      'header_present'=> isset($_SERVER['HTTP_X_API_KEY']),
    ]
  ], 401);
}

function ensure_backup_dir($dir){
  if (!is_dir($dir)) mkdir($dir, 0700, true);
  $ht = $dir.'/.htaccess';
  if (!is_file($ht)) @file_put_contents($ht, "Deny from all\n");
}

function make_filename($dbname){ return sprintf('%s_%s', preg_replace('/[^a-zA-Z0-9_\-]/','_', $dbname), date('Y-m-d_H-i-s')); }

function find_bin($names){
  foreach ((array)$names as $n){
    $which = trim(shell_exec('command -v '.escapeshellarg($n).' 2>/dev/null') ?: '');
    if ($which && is_executable($which)) return $which;
    foreach (["/usr/bin/$n","/usr/local/bin/$n","/bin/$n"] as $p) if (is_executable($p)) return $p;
  }
  return null;
}

function is_mariadb_dump($dumpbin): bool {
  $ver = @shell_exec(sh($dumpbin).' --version 2>&1') ?: '';
  return stripos($ver, 'mariadb') !== false;
}

// Dump ohne Pipe (direkt nach .sql schreiben)
function build_dump_cmd_without_pipe($dumpbin, $db, $sqlPath){
  $host = $db['host'] ?? 'localhost';
  $user = $db['user'] ?? '';
  $pass = $db['pass'] ?? '';
  $name = $db['name'] ?? '';
  $port = $db['port'] ?? null;
  $sock = $db['socket'] ?? null;

  $parts = [ sh($dumpbin) ];

  // Stabilitäts-/Kompatibilitäts-Flags (MariaDB vs. MySQL unterscheiden)
  $isMaria = is_mariadb_dump($dumpbin);

  $parts[] = '--single-transaction';
  $parts[] = '--quick';
  //$parts[] = '--routines';
  //$parts[] = '--events';
  $parts[] = '--triggers';
  // $parts[] = '--skip-definer'; // Nicht in allen Versionen verfügbar
  if (!$isMaria) {
    // Nur bei Oracle MySQL unterstützte Optionen
    $parts[] = '--set-gtid-purged=OFF';
    $parts[] = '--column-statistics=0';
  }
  $parts[] = '--no-tablespaces'; // bei beiden okay

  // Verbindung
  if ($sock) {
    $parts[] = '--protocol=SOCKET';
    $parts[] = '--socket=' . sh($sock);
  } else {
    $parts[] = '--protocol=TCP';
    $parts[] = '-h'; $parts[] = sh($host);
    if ($port) { $parts[] = '-P'; $parts[] = sh((string)$port); }
  }

  // Auth
  $parts[] = '-u'; $parts[] = sh($user);
  $parts[] = '-p' . str_replace("'","'\\''",$pass);

  // Output
  $parts[] = '--result-file=' . sh($sqlPath);

  // DB Name
  $parts[] = sh($name);

  return implode(' ', $parts);
}

function build_restore_cmd($mysqlbin, $db){
  $host = $db['host'] ?? 'localhost';
  $user = $db['user'] ?? '';
  $pass = $db['pass'] ?? '';
  $name = $db['name'] ?? '';
  $port = $db['port'] ?? null;
  $sock = $db['socket'] ?? null;

  $parts = [ sh($mysqlbin) ];

  if ($sock) {
    $parts[] = '--protocol=SOCKET';
    $parts[] = '--socket=' . sh($sock);
  } else {
    $parts[] = '--protocol=TCP';
    $parts[] = '-h'; $parts[] = sh($host);
    if ($port) { $parts[] = '-P'; $parts[] = sh((string)$port); }
  }

  $parts[] = '-u'; $parts[] = sh($user);
  $parts[] = '-p'.str_replace("'","'\\''",$pass);
  $parts[] = sh($name);
  return implode(' ', $parts);
}

// ----------- Boot -----------
require_key($BACKUP_API_KEY);
ensure_backup_dir($BACKUP_DIR);

$dbconf = $CONF['db']; // volle DB-Config inkl. evtl. port/socket
$dumpbin = find_bin(['mysqldump']);
$mysqlbin = find_bin(['mysql']);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
dbg('Action='.$action);

// ----------- Router -----------
switch ($action) {
  case 'restore_existing': {
    if (!$mysqlbin) out(['success'=>false,'message'=>'mysql CLI nicht gefunden'],500);
    $name = basename($_POST['name'] ?? '');
    $path = $BACKUP_DIR.'/'.$name;
    if (!is_file($path)) out(['success'=>false,'message'=>'Datei nicht gefunden'],404);

    @set_time_limit(0); @ignore_user_abort(true);

    // Falls .gz, erst entpacken
    $tmp = $path;
    $needsCleanup = false;
    if (preg_match('/\.gz$/i', $path)) {
        $data = @file_get_contents($path);
        if ($data === false) out(['success'=>false,'message'=>'GZ-Datei nicht lesbar'],400);
        $sql = @gzdecode($data);
        if ($sql === false) out(['success'=>false,'message'=>'GZ-Decode fehlgeschlagen'],400);
        
        // DEFINER-Statements entfernen für Kompatibilität
        $sql = preg_replace('/\/\*!50013\s+DEFINER\s*=\s*`[^`]+`\s*@\s*`[^`]+`\s*\*\//i', '/*!50013 */', $sql);
        $sql = preg_replace('/DEFINER\s*=\s*`[^`]+`\s*@\s*`[^`]+`/i', '', $sql);
        
        $tmpSql = tempnam(sys_get_temp_dir(), 'restore_').'.sql';
        file_put_contents($tmpSql, $sql);
        $tmp = $tmpSql;
        $needsCleanup = true;
    } else {
        // Auch bei .sql Dateien DEFINER entfernen
        $sql = @file_get_contents($path);
        if ($sql === false) out(['success'=>false,'message'=>'SQL-Datei nicht lesbar'],400);
        
        // DEFINER-Statements entfernen
        $sql = preg_replace('/\/\*!50013\s+DEFINER\s*=\s*`[^`]+`\s*@\s*`[^`]+`\s*\*\//i', '/*!50013 */', $sql);
        $sql = preg_replace('/DEFINER\s*=\s*`[^`]+`\s*@\s*`[^`]+`/i', '', $sql);
        
        $tmpSql = tempnam(sys_get_temp_dir(), 'restore_').'.sql';
        file_put_contents($tmpSql, $sql);
        $tmp = $tmpSql;
        $needsCleanup = true;
    }

    $cmd = build_restore_cmd($mysqlbin, $dbconf) . ' < ' . sh($tmp);
    dbg('Restore CMD: '.$cmd);
    
    $lines=[]; $exit=0; @exec($cmd.' 2>&1', $lines, $exit);
    if ($needsCleanup && is_file($tmp)) @unlink($tmp);

    $output = implode("\n",$lines);
    dbg("Restore exit=$exit output_len=".strlen($output));
    
    // Verbesserte Fehlerbehandlung mit mehr Details
    if ($exit!==0) {
      $errorInfo = [
        'success'=>false,
        'message'=>'Restore meldet Fehler',
        'exit'=>$exit,
        'details'=>substr($output,0,4000)
      ];
      
      // Versuche spezifische MySQL-Fehler zu extrahieren
      if (preg_match('/ERROR\s+(\d+)\s*\(([^)]+)\):\s*(.+)/i', $output, $matches)) {
        $errorInfo['mysql_error_code'] = $matches[1];
        $errorInfo['mysql_error_state'] = $matches[2];
        $errorInfo['mysql_error_message'] = $matches[3];
        $errorInfo['message'] = "MySQL Error {$matches[1]}: {$matches[3]}";
      } elseif (preg_match('/ERROR:\s*(.+)/i', $output, $matches)) {
        $errorInfo['message'] = "MySQL: {$matches[1]}";
      }
      
      out($errorInfo, 500);
    }
    
    // Auch bei Exit 0 prüfen ob es Warnungen gibt
    if (stripos($output,'ERROR')!==false) {
      out([
        'success'=>false,
        'message'=>'Restore enthält Fehler trotz Exit Code 0',
        'exit'=>$exit,
        'details'=>substr($output,0,4000)
      ],500);
    }
    
    out(['success'=>true,'message'=>'Restore aus Backup-Datei ausgeführt','file'=>$name],200);
    break; // WICHTIG: break hinzugefügt!
  }

  case 'backup': {
    if (!$dumpbin) out(['success'=>false,'message'=>'mysqldump nicht gefunden'],500);
    @set_time_limit(0); @ignore_user_abort(true);

    $base = make_filename($dbconf['name']);
    $sql  = $BACKUP_DIR . '/' . $base . '.sql';
    $gz   = $BACKUP_DIR . '/' . $base . '.sql.gz';

    $cmd = build_dump_cmd_without_pipe($dumpbin, $dbconf, $sql);
    dbg('Dump CMD: '.$cmd);

    $lines=[]; $exit=0; @exec($cmd.' 2>&1', $lines, $exit);
    $output = implode("\n", $lines);
    $size = is_file($sql) ? filesize($sql) : 0;

    dbg("Dump exit=$exit size=$size");

    if ($size < 100) {
      out([
        'success'=>false,
        'message'=>'Backup fehlgeschlagen (Dump leer)',
        'exit'=>$exit,
        'cmd'=>$cmd,
        'host'=>$dbconf['host'] ?? null,
        'port'=>$dbconf['port'] ?? null,
        'socket'=>$dbconf['socket'] ?? null,
        'details'=>substr($output, 0, 4000)
      ], 500);
    }

    $contents = @file_get_contents($sql);
    if ($contents === false) { @unlink($sql); out(['success'=>false,'message'=>'Backup fehlgeschlagen (SQL nicht lesbar)'],500); }

    // DEFINER-Statements entfernen für bessere Kompatibilität
    // Entfernt DEFINER=`user`@`host` aus CREATE VIEW/PROCEDURE/FUNCTION/TRIGGER Statements
    $contents = preg_replace(
      '/\/\*!50013\s+DEFINER\s*=\s*`[^`]+`\s*@\s*`[^`]+`\s*\*\//i',
      '/*!50013 */',
      $contents
    );
    // Alternative Syntax ohne Kommentar
    $contents = preg_replace(
      '/DEFINER\s*=\s*`[^`]+`\s*@\s*`[^`]+`/i',
      '',
      $contents
    );

    $gzdata = gzencode($contents, 9);
    if ($gzdata === false) { @unlink($sql); out(['success'=>false,'message'=>'Backup fehlgeschlagen (gzencode)'],500); }

    if (@file_put_contents($gz, $gzdata) === false) { @unlink($sql); out(['success'=>false,'message'=>'Backup fehlgeschlagen (schreiben .gz)'],500); }

    @unlink($sql);

    out([
      'success'=>true,
      'message'=>'Backup erstellt',
      'file'=>basename($gz),
      'size'=>filesize($gz),
      'exit'=>$exit,
      'details'=>substr($output,0,1500)
    ],200);
    break;
  }

  case 'list': {
    $files=[];
    foreach (glob($BACKUP_DIR.'/*.sql.gz') as $f) {
      $files[]=['name'=>basename($f),'size'=>filesize($f),'mtime'=>filemtime($f)];
    }
    usort($files, fn($a,$b)=>$b['mtime']<=>$a['mtime']);
    out(['success'=>true,'files'=>$files],200);
    break;
  }

  case 'download': {
    $name = basename($_GET['name'] ?? '');
    $path = $BACKUP_DIR.'/'.$name;
    if (!is_file($path)) out(['success'=>false,'message'=>'Datei nicht gefunden'],404);
    // Für Download kein JSON-Header
    header_remove('Content-Type');
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="'.$name.'"');
    header('Content-Length: '.filesize($path));
    readfile($path);
    exit;
    // break; nicht nötig nach exit
  }

  case 'delete': {
    $name = basename($_POST['name'] ?? '');
    $path = $BACKUP_DIR.'/'.$name;
    if (!is_file($path)) out(['success'=>false,'message'=>'Datei nicht gefunden'],404);
    if (!unlink($path)) out(['success'=>false,'message'=>'Löschen fehlgeschlagen'],500);
    out(['success'=>true,'message'=>'Datei gelöscht'],200);
    break;
  }

  case 'restore': {
    if (!$mysqlbin) out(['success'=>false,'message'=>'mysql CLI nicht gefunden'],500);
    if (!isset($_FILES['file']) || $_FILES['file']['error']!==UPLOAD_ERR_OK)
      out(['success'=>false,'message'=>'Upload fehlgeschlagen'],400);

    @set_time_limit(0); @ignore_user_abort(true);

    $tmp  = $_FILES['file']['tmp_name'];
    $orig = $_FILES['file']['name'];
    $isGz = preg_match('/\\.gz$/i', $orig);

    // SQL-Inhalt vorbereiten und DEFINER entfernen
    if ($isGz) {
      $data = @file_get_contents($tmp);
      if ($data === false) out(['success'=>false,'message'=>'GZ-Datei nicht lesbar'],400);
      $sql  = @gzdecode($data);
      if ($sql === false) out(['success'=>false,'message'=>'GZ-Decode fehlgeschlagen'],400);
    } else {
      $sql = @file_get_contents($tmp);
      if ($sql === false) out(['success'=>false,'message'=>'SQL-Datei nicht lesbar'],400);
    }
    
    // DEFINER-Statements entfernen für Kompatibilität
    $sql = preg_replace('/\/\*!50013\s+DEFINER\s*=\s*`[^`]+`\s*@\s*`[^`]+`\s*\*\//i', '/*!50013 */', $sql);
    $sql = preg_replace('/DEFINER\s*=\s*`[^`]+`\s*@\s*`[^`]+`/i', '', $sql);
    
    // Bereinigte SQL in temporäre Datei schreiben
    $tmpSql = tempnam(sys_get_temp_dir(), 'restore_').'.sql';
    file_put_contents($tmpSql, $sql);
    $tmp = $tmpSql;

    $cmd = build_restore_cmd($mysqlbin, $dbconf) . ' < ' . sh($tmp);
    dbg('Restore CMD: '.$cmd);

    $lines=[]; $exit=0; @exec($cmd.' 2>&1', $lines, $exit);
    if (isset($tmpSql) && is_file($tmpSql)) @unlink($tmpSql);

    $output = implode("\n",$lines);
    dbg("Restore exit=$exit len=".strlen($output));

    // Verbesserte Fehlerbehandlung mit mehr Details
    if ($exit!==0) {
      $errorInfo = [
        'success'=>false,
        'message'=>'Restore meldet Fehler',
        'exit'=>$exit,
        'details'=>substr($output,0,4000)
      ];
      
      // Versuche spezifische MySQL-Fehler zu extrahieren
      if (preg_match('/ERROR\s+(\d+)\s*\(([^)]+)\):\s*(.+)/i', $output, $matches)) {
        $errorInfo['mysql_error_code'] = $matches[1];
        $errorInfo['mysql_error_state'] = $matches[2];
        $errorInfo['mysql_error_message'] = $matches[3];
        $errorInfo['message'] = "MySQL Error {$matches[1]}: {$matches[3]}";
      } elseif (preg_match('/ERROR:\s*(.+)/i', $output, $matches)) {
        $errorInfo['message'] = "MySQL: {$matches[1]}";
      }
      
      out($errorInfo, 500);
    }
    
    // Auch bei Exit 0 prüfen ob es Warnungen gibt
    if (stripos($output,'ERROR')!==false) {
      out([
        'success'=>false,
        'message'=>'Restore enthält Fehler trotz Exit Code 0',
        'exit'=>$exit,
        'details'=>substr($output,0,4000)
      ],500);
    }
    
    out(['success'=>true,'message'=>'Restore ausgeführt','exit'=>$exit,'details'=>substr($output,0,1500)],200);
    break;
  }

  // ----------- Diagnose / Debug -----------
  case 'diag': {
    $db = $dbconf; $diag = [];

    $diag['mysql_version']     = trim(@shell_exec(($mysqlbin?:'mysql').' --version 2>&1'));
    $diag['mysqldump_version'] = trim(@shell_exec(($dumpbin?:'mysqldump').' --version 2>&1'));

    // PHP-Connectivity
    $diag['php_connect'] = null;
    mysqli_report(MYSQLI_REPORT_OFF);
    $mysqli = @mysqli_init();
    if (!empty($db['socket'])) {
      @mysqli_options($mysqli, MYSQLI_OPT_LOCAL_INFILE, 0);
      $ok = @$mysqli->real_connect(null, $db['user'], $db['pass'], $db['name'], null, $db['socket']);
    } else {
      $port = $db['port'] ?? ini_get("mysqli.default_port") ?: 3306;
      $ok = @$mysqli->real_connect($db['host'], $db['user'], $db['pass'], $db['name'], (int)$port, null, 0);
    }
    $diag['php_connect'] = $ok ? 'OK' : ('ERR: '.(@mysqli_connect_error() ?: 'unknown'));

    // mysqldump Trockenlauf (nur Struktur)
    if ($dumpbin) {
      $tmp = tempnam(sys_get_temp_dir(), 'diag_').'.sql';
      $cmd = build_dump_cmd_without_pipe($dumpbin, $db, $tmp) . ' --no-data';
      $lines=[]; $exit=0; @exec($cmd.' 2>&1', $lines, $exit);
      $diag['dry_run'] = [
        'exit'=>$exit,
        'size'=> (is_file($tmp)?filesize($tmp):0),
        'msg'=> substr(implode("\n",$lines),0,2000),
      ];
      if (is_file($tmp)) @unlink($tmp);
    } else {
      $diag['dry_run'] = ['exit'=>127,'size'=>0,'msg'=>'mysqldump nicht gefunden'];
    }

    out(['success'=>true,'diag'=>$diag],200);
    break;
  }

  case 'whoami': {
    $csrf = $_REQUEST['csrf_token'] ?? '';
    $sess = $_SESSION['csrf_token'] ?? '';
    $hdr  = $_SERVER['HTTP_X_API_KEY'] ?? '';
    $qry  = $_GET['key'] ?? $_POST['key'] ?? '';
    $mask = function($s){ if($s==='') return 'âˆ…'; $len=strlen($s); return $len<6 ? str_repeat('*',$len) : substr($s,0,3).'…'.substr($s,-2)." (len:$len)"; };
    out([
      'success'=>true,
      'mode'=> ($csrf && $sess && hash_equals($sess,$csrf)) ? 'csrf' : (($hdr||$qry)?'key':'none'),
      'csrf_token_sent'=> $mask($csrf),
      'csrf_token_session'=> $mask($sess),
      'api_key_header'=> $mask($hdr),
      'api_key_param'=> $mask($qry),
      'method'=> $_SERVER['REQUEST_METHOD'] ?? '',
      'origin'=> $_SERVER['HTTP_ORIGIN'] ?? '',
      'referer'=> $_SERVER['HTTP_REFERER'] ?? '',
    ], 200);
    break;
  }

  case 'echo': {
    $resp = [
      'method'=> $_SERVER['REQUEST_METHOD'] ?? '',
      'get'=> $_GET,
      'post'=> $_POST,
      'files'=> array_map(fn($f)=>['name'=>$f['name']??'', 'size'=>$f['size']??0, 'type'=>$f['type']??''], $_FILES),
      'headers'=> [
        'X-API-Key'=> isset($_SERVER['HTTP_X_API_KEY']) ? true : false,
        'Content-Type'=> $_SERVER['CONTENT_TYPE'] ?? '',
      ]
    ];
    out(['success'=>true,'echo'=>$resp],200);
    break;
  }

  default:
    out(['success'=>false,'message'=>'Unbekannte Aktion'],400);
    break;
}
