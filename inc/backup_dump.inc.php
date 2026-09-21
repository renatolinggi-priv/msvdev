<?php
/**
 * backup_dump.inc.php
 *
 * Erstellt einen gzip-komprimierten Datenbank-Dump und raeumt alte Staende auf.
 * Wird von cron/backup_db.php benutzt (automatischer Lauf).
 *
 * Bewusst getrennt von admin/backup_api.php: jene Datei kann auch
 * WIEDERHERSTELLEN und LOESCHEN. Ein Schluessel, der im crontab steht, darf
 * genau eines koennen - sichern. Darum hier eine eigene, schreibgeschuetzte
 * Variante ohne Restore-Pfad.
 *
 * Das Passwort geht ueber eine temporaere Defaults-Datei (chmod 0600) an
 * mysqldump und nicht ueber die Kommandozeile: Argumente sind auf einem
 * geteilten Server in der Prozessliste sichtbar.
 */

/** Verzeichnis, in dem die Sicherungen liegen (per .htaccess vom Web gesperrt). */
function msvBackupVerzeichnis(): string
{
    return dirname(__DIR__) . '/backups';
}

/** DB-Zugangsdaten aus msvjm_config.php. */
function msvBackupDbConf(): array
{
    $conf = require dirname(__DIR__) . '/config.php';
    return $conf['db'] ?? [];
}

/**
 * Sucht ein Programm. Aus dem Web-PHP heraus ist der PATH knapp, darum werden
 * die ueblichen Orte zusaetzlich direkt geprueft (siehe CLAUDE.md).
 */
function msvBackupFindBin(array $namen): ?string
{
    foreach ($namen as $n) {
        foreach (['/usr/local/bin/', '/usr/bin/', '/bin/'] as $p) {
            if (is_executable($p . $n)) {
                return $p . $n;
            }
        }
        $which = trim((string)@shell_exec('command -v ' . escapeshellarg($n) . ' 2>/dev/null'));
        if ($which !== '' && is_executable($which)) {
            return $which;
        }
    }
    return null;
}

/** MariaDB kennt --set-gtid-purged und --column-statistics nicht. */
function msvBackupIstMaria(string $bin): bool
{
    $ver = (string)@shell_exec(escapeshellarg($bin) . ' --version 2>&1');
    return stripos($ver, 'mariadb') !== false;
}

/**
 * Erstellt die Sicherung.
 *
 * @return array{ok:bool, datei:?string, groesse:int, meldung:string}
 */
function msvBackupErstellen(?string $zielDir = null): array
{
    $dir = $zielDir ?: msvBackupVerzeichnis();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return ['ok' => false, 'datei' => null, 'groesse' => 0, 'meldung' => 'Backup-Verzeichnis fehlt und kann nicht angelegt werden'];
    }
    if (!is_writable($dir)) {
        return ['ok' => false, 'datei' => null, 'groesse' => 0, 'meldung' => 'Backup-Verzeichnis ist nicht beschreibbar'];
    }

    $db = msvBackupDbConf();
    $name = (string)($db['name'] ?? '');
    if ($name === '') {
        return ['ok' => false, 'datei' => null, 'groesse' => 0, 'meldung' => 'Kein Datenbankname in der Konfiguration'];
    }

    $dumpbin = msvBackupFindBin(['mysqldump']);
    if (!$dumpbin) {
        return ['ok' => false, 'datei' => null, 'groesse' => 0, 'meldung' => 'mysqldump nicht gefunden'];
    }

    $basis   = preg_replace('/[^A-Za-z0-9_\-]/', '_', $name) . '_' . date('Y-m-d_H-i-s');
    $sqlPfad = $dir . '/' . $basis . '.sql';
    $gzPfad  = $dir . '/' . $basis . '.sql.gz';

    // --- Passwort ueber eine temporaere Defaults-Datei ---
    $iniPfad = $dir . '/.my.' . bin2hex(random_bytes(6)) . '.cnf';
    $ini  = "[client]\n";
    $ini .= 'host=' . ($db['host'] ?? 'localhost') . "\n";
    $ini .= 'user=' . ($db['user'] ?? '') . "\n";
    $ini .= 'password=' . ($db['pass'] ?? '') . "\n";
    if (!empty($db['port']))   { $ini .= 'port=' . (int)$db['port'] . "\n"; }
    if (!empty($db['socket'])) { $ini .= 'socket=' . $db['socket'] . "\n"; }

    if (@file_put_contents($iniPfad, $ini) === false) {
        return ['ok' => false, 'datei' => null, 'groesse' => 0, 'meldung' => 'Temporaere Zugangsdatei konnte nicht geschrieben werden'];
    }
    @chmod($iniPfad, 0600);

    $teile = [
        escapeshellarg($dumpbin),
        '--defaults-extra-file=' . escapeshellarg($iniPfad),
        '--single-transaction',
        '--quick',
        '--triggers',
        '--no-tablespaces',
    ];
    if (!msvBackupIstMaria($dumpbin)) {
        $teile[] = '--set-gtid-purged=OFF';
        $teile[] = '--column-statistics=0';
    }
    $teile[] = '--result-file=' . escapeshellarg($sqlPfad);
    $teile[] = escapeshellarg($name);

    // PATH/HOME setzen: aus Web-PHP heraus sind beide sonst unbrauchbar.
    $cmd = 'PATH=/usr/local/bin:/usr/bin:/bin HOME=' . escapeshellarg(sys_get_temp_dir())
         . ' ' . implode(' ', $teile) . ' 2>&1';

    $ausgabe = [];
    $code = 0;
    @exec($cmd, $ausgabe, $code);

    @unlink($iniPfad); // Zugangsdaten sofort wieder weg, auch bei Fehler

    if ($code !== 0) {
        @unlink($sqlPfad);
        $txt = trim(implode(' ', array_slice($ausgabe, 0, 3)));
        return ['ok' => false, 'datei' => null, 'groesse' => 0, 'meldung' => 'mysqldump Exit ' . $code . ($txt !== '' ? ': ' . $txt : '')];
    }

    // --- Plausibilitaet: ein abgeschnittener Dump ist schlimmer als keiner ---
    $pruefung = msvBackupDumpPruefen($sqlPfad);
    if ($pruefung !== null) {
        @unlink($sqlPfad);
        return ['ok' => false, 'datei' => null, 'groesse' => 0, 'meldung' => $pruefung];
    }

    // --- Komprimieren ---
    $inhalt = @file_get_contents($sqlPfad);
    if ($inhalt === false) {
        @unlink($sqlPfad);
        return ['ok' => false, 'datei' => null, 'groesse' => 0, 'meldung' => 'Dump konnte nicht gelesen werden'];
    }
    $gz = gzencode($inhalt, 9);
    unset($inhalt);
    if ($gz === false || @file_put_contents($gzPfad, $gz) === false) {
        @unlink($sqlPfad);
        @unlink($gzPfad);
        return ['ok' => false, 'datei' => null, 'groesse' => 0, 'meldung' => 'Komprimieren fehlgeschlagen'];
    }
    @unlink($sqlPfad);
    @chmod($gzPfad, 0640);

    return [
        'ok'      => true,
        'datei'   => basename($gzPfad),
        'groesse' => (int)filesize($gzPfad),
        'meldung' => 'Sicherung erstellt',
    ];
}

/**
 * Prueft einen frischen Dump oberflaechlich auf Vollstaendigkeit.
 * @return string|null Fehlertext, oder null wenn alles passt.
 */
function msvBackupDumpPruefen(string $sqlPfad): ?string
{
    if (!is_file($sqlPfad)) {
        return 'Dump-Datei wurde nicht angelegt';
    }
    $groesse = (int)filesize($sqlPfad);
    if ($groesse < 10240) {
        return 'Dump ist nur ' . $groesse . ' Bytes gross - vermutlich unvollstaendig';
    }
    // mysqldump schreibt als letzte Zeile "-- Dump completed on ...".
    $fh = @fopen($sqlPfad, 'rb');
    if (!$fh) {
        return 'Dump-Datei kann nicht gelesen werden';
    }
    fseek($fh, -200, SEEK_END);
    $ende = (string)fread($fh, 200);
    fclose($fh);
    if (stripos($ende, 'Dump completed') === false) {
        return 'Dump endet nicht mit "Dump completed" - vermutlich abgebrochen';
    }
    return null;
}

/**
 * Loescht alte Sicherungen und behaelt die neuesten $behalten Staende.
 * Greift ausschliesslich Dateien mit der Endung .sql.gz an.
 *
 * @return int Anzahl geloeschter Dateien
 */
function msvBackupAufraeumen(int $behalten = 14, ?string $zielDir = null): int
{
    $dir = $zielDir ?: msvBackupVerzeichnis();
    $dateien = glob($dir . '/*.sql.gz') ?: [];
    if (count($dateien) <= $behalten) {
        return 0;
    }
    // Neueste zuerst
    usort($dateien, static fn($a, $b) => filemtime($b) <=> filemtime($a));
    $weg = array_slice($dateien, $behalten);
    $n = 0;
    foreach ($weg as $f) {
        if (@unlink($f)) {
            $n++;
        }
    }
    return $n;
}

/**
 * Rotiert eine Logdatei: log -> log.1 -> log.2 ... und leert das Original.
 * Wird nur taetig, wenn die Datei groesser als $abBytes ist.
 *
 * Das Umbenennen statt Loeschen haelt den Dateizeiger von PHP gueltig; das
 * Original wird anschliessend geleert, nicht entfernt.
 *
 * @return array{rotiert:bool, meldung:string}
 */
function msvLogRotieren(string $logPfad, int $behalten = 3, int $abBytes = 1048576): array
{
    if (!is_file($logPfad)) {
        return ['rotiert' => false, 'meldung' => 'Logdatei nicht vorhanden'];
    }
    $groesse = (int)filesize($logPfad);
    if ($groesse < $abBytes) {
        return ['rotiert' => false, 'meldung' => 'Logdatei unter Schwelle (' . $groesse . ' Bytes)'];
    }
    if (!is_writable($logPfad)) {
        return ['rotiert' => false, 'meldung' => 'Logdatei ist nicht beschreibbar'];
    }

    // Aeltesten Stand entfernen, dann durchschieben
    $aeltest = $logPfad . '.' . $behalten . '.gz';
    if (is_file($aeltest)) {
        @unlink($aeltest);
    }
    for ($i = $behalten - 1; $i >= 1; $i--) {
        $von = $logPfad . '.' . $i . '.gz';
        if (is_file($von)) {
            @rename($von, $logPfad . '.' . ($i + 1) . '.gz');
        }
    }

    $inhalt = @file_get_contents($logPfad);
    if ($inhalt === false) {
        return ['rotiert' => false, 'meldung' => 'Logdatei konnte nicht gelesen werden'];
    }
    $gz = gzencode($inhalt, 9);
    unset($inhalt);
    if ($gz === false || @file_put_contents($logPfad . '.1.gz', $gz) === false) {
        return ['rotiert' => false, 'meldung' => 'Log konnte nicht archiviert werden'];
    }
    @chmod($logPfad . '.1.gz', 0640);

    // Original leeren statt loeschen, damit PHP weiterschreiben kann
    if (@file_put_contents($logPfad, '') === false) {
        return ['rotiert' => false, 'meldung' => 'Logdatei konnte nicht geleert werden'];
    }

    return ['rotiert' => true, 'meldung' => 'Log rotiert (' . $groesse . ' Bytes archiviert)'];
}
