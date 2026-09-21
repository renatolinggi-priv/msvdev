<?php
/**
 * cron/backup_db.php
 *
 * Taegliche Datensicherung der Datenbank plus Hausputz.
 *
 *   HTTP: .../cron/backup_db.php?key=<cron_trigger_key>
 *   CLI : php cron/backup_db.php
 *
 * Intervall: taeglich (Crontab-Zeile siehe CLAUDE.md). Die Datenbank ist rund
 * 5 MB gross, gepackt deutlich weniger - taeglich kostet praktisch nichts und
 * ist einem Wochenstand klar vorzuziehen, weil Resultate tageweise erfasst
 * werden.
 *
 * Bewusst NICHT admin/backup_api.php: jene Datei kann auch wiederherstellen
 * und loeschen. Der Schluessel steht im Klartext im crontab, also darf er nur
 * sichern koennen.
 *
 * Schritte:
 *   1. Dump erstellen, pruefen, packen  -> backups/<db>_<zeitstempel>.sql.gz
 *   2. Alte Sicherungen aufraeumen      -> die neuesten BEHALTEN bleiben
 *   3. PHP-Error-Log rotieren           -> ab 1 MB, 3 gepackte Staende
 */

declare(strict_types=1);

const BACKUP_BEHALTEN   = 14;        // Anzahl aufzubewahrender Sicherungen
const LOG_BEHALTEN      = 3;         // Anzahl archivierter Logstaende
const LOG_ROTATE_AB     = 1048576;   // 1 MB
const LOG_PFAD          = '/home/bdebbd4/php_error.log';

require_once __DIR__ . '/../inc/backup_dump.inc.php';

$istCli = (PHP_SAPI === 'cli');

// --- Zugriffsschutz ------------------------------------------------------
// Ueber HTTP nur mit dem Cron-Schluessel. Auf der Kommandozeile hat der
// Aufrufer ohnehin Shell-Zugang, dort entfaellt die Pruefung.
if (!$istCli) {
    header('Content-Type: application/json; charset=utf-8');

    $conf = require __DIR__ . '/../config.php';
    $db   = $conf['db'] ?? [];
    $mysqli = @new mysqli(
        (string)($db['host'] ?? ''), (string)($db['user'] ?? ''),
        (string)($db['pass'] ?? ''), (string)($db['name'] ?? '')
    );
    if ($mysqli->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Datenbank nicht erreichbar']);
        exit;
    }
    $mysqli->set_charset('utf8mb4');

    $erwartet = '';
    if ($stmt = $mysqli->prepare("SELECT setting_value FROM settings WHERE setting_key = 'cron_trigger_key'")) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $erwartet = (string)($row['setting_value'] ?? '');
        $stmt->close();
    }
    $gesendet = (string)($_GET['key'] ?? '');

    if ($erwartet === '' || $gesendet === '' || !hash_equals($erwartet, $gesendet)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}

@set_time_limit(300);
@ignore_user_abort(true);

$ergebnis = [];

// --- 1. Sicherung --------------------------------------------------------
$backup = msvBackupErstellen();
$ergebnis['backup'] = $backup;

if ($backup['ok']) {
    error_log(sprintf('[backup_db] OK %s (%d Bytes)', (string)$backup['datei'], (int)$backup['groesse']));
} else {
    // Fehlgeschlagene Sicherungen muessen auffallen - sonst merkt es niemand.
    error_log('[backup_db] FEHLER: ' . $backup['meldung']);
}

// --- 2. Alte Staende aufraeumen -----------------------------------------
$geloescht = msvBackupAufraeumen(BACKUP_BEHALTEN);
$ergebnis['aufgeraeumt'] = $geloescht;
if ($geloescht > 0) {
    error_log(sprintf('[backup_db] %d alte Sicherung(en) entfernt, %d bleiben', $geloescht, BACKUP_BEHALTEN));
}

// --- 3. Error-Log rotieren ----------------------------------------------
$rotation = msvLogRotieren(LOG_PFAD, LOG_BEHALTEN, LOG_ROTATE_AB);
$ergebnis['log'] = $rotation;
if ($rotation['rotiert']) {
    error_log('[backup_db] ' . $rotation['meldung']);
}

// --- Ausgabe -------------------------------------------------------------
$ergebnis['success'] = (bool)$backup['ok'];

if ($istCli) {
    echo 'Sicherung : ' . ($backup['ok'] ? $backup['datei'] . ' (' . $backup['groesse'] . " Bytes)\n" : 'FEHLER - ' . $backup['meldung'] . "\n");
    echo 'Aufgeraeumt: ' . $geloescht . " alte Sicherung(en)\n";
    echo 'Log       : ' . $rotation['meldung'] . "\n";
    exit($backup['ok'] ? 0 : 1);
}

if (!$backup['ok']) {
    http_response_code(500);
}
echo json_encode($ergebnis, JSON_UNESCAPED_UNICODE);
