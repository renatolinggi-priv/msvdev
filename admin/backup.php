<?php
// /home/USER/backup/backup.php
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('CLI only'); }

date_default_timezone_set('Europe/Zurich');

$cfg = [
  'app_root'     => '/home/bdebbd4/www/jahresmeisterschaft.msvwilen.ch',           // Root der Webapp
  'backup_root'  => '/home/bdebbd4/backup',        // Nicht-öffentlich!
  'tmp_dir'      => '/home/bdebbd4/backup/tmp',
  'db' => [
    'host' => 'bdebbd4.mysql.db.internal',
    'name' => 'bdebbd4_msvjm',
    'user' => 'bdebbd4_msvjm',
    'pass' => 'xx*97ubWcy+HnLWyf6PW',
  ],
  'exclude_paths' => ['backups','backup','cache','node_modules','vendor'],
  'retention' => ['daily'=>7, 'weekly'=>4, 'monthly'=>12],
];


$mode = in_array('--mode=auto', $argv, true) ? 'auto' : 'manual';
$ts   = date('Ymd-His');

@mkdir($cfg['backup_root'], 0700, true);
@mkdir($cfg['tmp_dir'], 0700, true);

function info($m){
  echo '['.date('H:i:s')."] $m\n";
  // Write progress information to a file
  $progressFile = $GLOBALS['cfg']['backup_root'] . '/backup_progress.txt';
  file_put_contents($progressFile, '['.date('H:i:s')."] $m\n", FILE_APPEND | LOCK_EX);
}
function sha256($file){ return hash_file('sha256', $file); }

try {
  info("Starte Backup ($mode) …");
  $start = microtime(true);

  // 1) DB-Dump
  $dbDump = "{$cfg['backup_root']}/{$ts}_db.sql.gz";
  $mysqldump = trim(shell_exec('command -v mysqldump'));
  if ($mysqldump) {
    info("mysqldump gefunden: $mysqldump");
    $cmd = sprintf(
      '%s --single-transaction --quick --routines --triggers --events --default-character-set=utf8mb4 -h%s -u%s -p%s %s 2>&1',
      escapeshellcmd($mysqldump),
      escapeshellarg($cfg['db']['host']),
      escapeshellarg($cfg['db']['user']),
      escapeshellarg($cfg['db']['pass']),
      escapeshellarg($cfg['db']['name'])
    );
    $tmpSql = "{$cfg['tmp_dir']}/{$ts}.sql";
    $out = [];
    $ret = 0;
    exec($cmd." > ".escapeshellarg($tmpSql), $out, $ret);
    if ($ret !== 0) throw new RuntimeException("mysqldump-Fehler: ".implode("\n",$out));
    // gzip
    $gz = gzopen($dbDump, 'w9');
    gzwrite($gz, file_get_contents($tmpSql));
    gzclose($gz);
    unlink($tmpSql);
  } else {
    info("mysqldump NICHT verfügbar – PHP-Fallback");
    // Sehr einfacher Fallback (für kleinere DBs). Für große Tabellen -> chunked Export ergänzen.
    $pdo = new PDO("mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4", $cfg['db']['user'], $cfg['db']['pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'"
    ]);
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $tmpSql = "{$cfg['tmp_dir']}/{$ts}.sql";
    $f = fopen($tmpSql, 'w');
    fwrite($f, "SET FOREIGN_KEY_CHECKS=0;\n");
    foreach ($tables as $t) {
      $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_ASSOC)['Create Table'] ?? '';
      fwrite($f, "DROP TABLE IF EXISTS `$t`;\n$create;\n");
      $stmt = $pdo->query("SELECT * FROM `$t`", PDO::FETCH_ASSOC);
      while ($row = $stmt->fetch()) {
        $vals = array_map(fn($v)=> is_null($v) ? 'NULL' : "'".addslashes($v)."'", array_values($row));
        fwrite($f, "INSERT INTO `$t` VALUES (".implode(',', $vals).");\n");
      }
      fwrite($f, "\n");
    }
    fwrite($f, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($f);
    $gz = gzopen($dbDump, 'w9'); gzwrite($gz, file_get_contents($tmpSql)); gzclose($gz); unlink($tmpSql);
  }
  info("DB-Dump OK: $dbDump");

  // 2) Files-Backup (Zip)
  $filesZip = "{$cfg['backup_root']}/{$ts}_files.zip";
  $zip = new ZipArchive();
  if ($zip->open($filesZip, ZipArchive::CREATE)!==true) throw new RuntimeException("Zip konnte nicht erstellt werden");
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cfg['app_root'], FilesystemIterator::SKIP_DOTS));
  foreach ($it as $path => $fileInfo) {
    $rel = ltrim(str_replace($cfg['app_root'],'',$path),'/');
    // Excludes
    foreach ($cfg['exclude_paths'] as $ex) {
      if (str_starts_with($rel, $ex.'/') || $rel === $ex) { continue 2; }
    }
    // Backups-Ordner ausschließen, falls unterhalb app_root
    if (str_starts_with($rel,'backups/') || $rel==='backups') continue;

    if ($fileInfo->isFile()) $zip->addFile($path, $rel);
  }
  $zip->close();
  info("Files-Zip OK: $filesZip");

  // 3) Manifest + Checksums
  $manifest = [
    'timestamp' => $ts,
    'mode'      => $mode,
    'db_dump'   => basename($dbDump),
    'db_sha256' => sha256($dbDump),
    'files_zip' => basename($filesZip),
    'files_sha256' => sha256($filesZip),
    'duration_sec' => round(microtime(true)-$start, 2),
    'retention' => $cfg['retention']
  ];
  file_put_contents("{$cfg['backup_root']}/{$ts}_manifest.json", json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

  // 4) Retention
  $byPrefix = [];
  foreach (glob($cfg['backup_root'].'/*_manifest.json') as $mf) {
    $bts = basename($mf, '_manifest.json');
    $byPrefix[$bts] = [
      'mf'=>$mf,
      'ts'=>$bts,
      'db'=>str_replace('_manifest.json','_db.sql.gz',$mf),
      'zip'=>str_replace('_manifest.json','_files.zip',$mf),
      'date'=>DateTime::createFromFormat('Ymd-His',$bts)
    ];
  }
  // Sort neueste → älteste
  uasort($byPrefix, fn($a,$b)=> $b['date'] <=> $a['date']);

  // Listen bilden
  $daily=[]; $weekly=[]; $monthly=[];
  foreach ($byPrefix as $p) {
    $d = $p['date'];
    $keyD = $d->format('Y-m-d');
    $keyW = $d->format('o-W');
    $keyM = $d->format('Y-m');
    // Erster Treffer pro Gruppe behalten
    $daily[$keyD]  = $daily[$keyD]  ?? $p;
    $weekly[$keyW] = $weekly[$keyW] ?? $p;
    $monthly[$keyM]= $monthly[$keyM]?? $p;
  }
  $keep = [];
  $keep += array_slice($daily, 0, $cfg['retention']['daily'], true);
  $keep += array_slice($weekly,0, $cfg['retention']['weekly'], true);
  $keep += array_slice($monthly,0,$cfg['retention']['monthly'], true);

  $keepIds = array_map(fn($x)=>$x['ts'], $keep);
  foreach ($byPrefix as $p) {
    if (!in_array($p['ts'], $keepIds, true)) {
      foreach (['mf','db','zip'] as $k) if (is_file($p[$k])) @unlink($p[$k]);
    }
  }

  info("Backup fertig. Dauer: ".round(microtime(true)-$start,2)."s");
  exit(0);

} catch (Throwable $e) {
  fwrite(STDERR, "FEHLER: ".$e->getMessage()."\n");
  exit(1);
}
