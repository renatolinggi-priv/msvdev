<?php
/**
 * cron/cleanup_dat.php - raeumt die generierten Exportdateien in inc/<modul>/dat/ auf.
 *
 * Hintergrund und Sicherheitsregeln: siehe inc/dat_cleanup.inc.php. Kurzform: es wird nur
 * geloescht, was die Signatur einer generierten Datei traegt (_YYYY-MM-DD_HH-MM-SS vor der
 * Endung). Logos und Vorlagen in denselben Verzeichnissen bleiben unangetastet.
 *
 * Aufruf:
 *   CLI : php cron/cleanup_dat.php [--dry] [--keep=5]
 *   HTTP: .../cron/cleanup_dat.php?key=<cron_trigger_key>[&dry=1][&keep=5]
 *
 * Intervall: woechentlich genuegt. Die Dateien wachsen langsam; nur jmdefinition/dat ist
 * oeffentlich ausloesbar und raeumt darum zusaetzlich direkt beim Generieren auf.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/dbconnect.inc.php';   // getDB()
require_once __DIR__ . '/../inc/dat_cleanup.inc.php'; // datAufraeumen(), datVerzeichnisse()

$cli = (PHP_SAPI === 'cli');

// --- Absicherung: HTTP nur mit geheimem Token (timing-sicher) ---------------
// Bewusst nicht ueber push_helper.php, damit hier kein Composer/WebPush geladen wird.
if (!$cli) {
    header('Content-Type: application/json; charset=utf-8');
    $erwartet = '';
    try {
        $stmt = getDB()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute(['cron_trigger_key']);
        $erwartet = (string) ($stmt->fetchColumn() ?: '');
    } catch (\Throwable $e) {
        error_log('cron cleanup_dat: Settings nicht lesbar: ' . $e->getMessage());
    }
    $gesendet = (string) ($_GET['key'] ?? '');
    if ($erwartet === '' || !hash_equals($erwartet, $gesendet)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}

// --- Parameter --------------------------------------------------------------
$argvSafe  = $cli ? ($GLOBALS['argv'] ?? []) : [];
$nurTesten = $cli ? in_array('--dry', $argvSafe, true) : isset($_GET['dry']);
$behalten  = 5;
if ($cli) {
    foreach ($argvSafe as $arg) {
        if (strpos($arg, '--keep=') === 0) {
            $behalten = (int) substr($arg, 7);
        }
    }
} elseif (isset($_GET['keep'])) {
    $behalten = (int) $_GET['keep'];
}
$behalten = max(1, min(50, $behalten));

// --- Durchlauf --------------------------------------------------------------
$gesamt = ['kandidaten' => 0, 'geloescht' => 0, 'bytes' => 0, 'geschuetzt' => 0];
$module = [];
$fehler = [];

foreach (datVerzeichnisse() as $dir) {
    $r = datAufraeumen($dir, $behalten, $nurTesten);

    $gesamt['kandidaten'] += $r['kandidaten'];
    $gesamt['geloescht']  += $r['geloescht'];
    $gesamt['bytes']      += $r['bytes'];
    $gesamt['geschuetzt'] += $r['geschuetzt'];
    if ($r['fehler']) {
        $fehler = array_merge($fehler, $r['fehler']);
    }
    if ($r['geloescht'] > 0) {
        $module[basename(dirname($dir))] = [
            'geloescht'  => $r['geloescht'],
            'kb'         => (int) round($r['bytes'] / 1024),
            'geschuetzt' => $r['geschuetzt'],
        ];
    }
}

if ($fehler) {
    error_log('cron cleanup_dat: ' . count($fehler) . ' Fehler, erster: ' . $fehler[0]);
}

$modus   = $nurTesten ? 'TESTLAUF (nichts geloescht)' : 'ausgefuehrt';
$summary = sprintf(
    'dat-Aufraeumen %s: %d von %d generierten Dateien entfernt (%d KB), %d geschuetzte Dateien unberuehrt, behalte %d je Dokumentart.',
    $modus,
    $gesamt['geloescht'],
    $gesamt['kandidaten'],
    (int) round($gesamt['bytes'] / 1024),
    $gesamt['geschuetzt'],
    $behalten
);

if ($cli) {
    echo $summary . PHP_EOL;
    foreach ($module as $name => $m) {
        printf("  %-24s %4d Dateien  %6d KB\n", $name, $m['geloescht'], $m['kb']);
    }
    foreach ($fehler as $f) {
        echo '  FEHLER: ' . $f . PHP_EOL;
    }
    exit(0);
}

echo json_encode([
    'success'   => true,
    'testlauf'  => $nurTesten,
    'message'   => $summary,
    'gesamt'    => $gesamt,
    'module'    => $module,
    'fehler'    => $fehler,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
