<?php
/**
 * cleanup_pdf_files.php â€“ Standalone PDF-Cleanup
 *
 * - Rekursiv ab BASE_DIR (Standard: Ordner über diesem Skript)
 * - Ignoriert Ordner: dompdf/, phpword/, spreadsheet/ (case-insensitive)
 * - In jedem gefundenen "dat/"-Unterordner alle .pdf löschen, die älter als N Tage sind
 * - Läuft per HTTP (POST) und per CLI (für Cronjobs)
 * - Optional: Auth-Key via ?key=... / POST[key] / --key=...
 *
 * Exitcodes: 0 = OK, 1 = Fehler
 */
declare(strict_types=1);

// ========================= Konfiguration =========================
const DEFAULT_BASE_DIR = __DIR__ . '/..';   // ggf. anpassen
const DEFAULT_DAYS     = 30;
const EXCLUDE_DIRS     = ['dompdf', 'phpword', 'spreadsheet']; // nur Ordnernamen

// Optionaler fixer Key (oder per ENV: CLEANUP_KEY)
const FIXED_KEY        = ''; // z.B. 'super-secret-123'; leer lassen = kein Key nötig

// ========================= Utility ===============================
function json_out(bool $success, string $message, array $data = [], int $httpCode = 200): void {
    $isCli = (PHP_SAPI === 'cli');
    if (!$isCli) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ($isCli ? PHP_EOL : '');
    exit($success ? 0 : 1);
}

function is_excluded_dirname(string $name): bool {
    $n = mb_strtolower($name);
    foreach (EXCLUDE_DIRS as $ex) {
        if ($n === mb_strtolower($ex)) return true;
    }
    return false;
}

function purge_old_pdfs_in_dat(string $datPath, int $days, bool $dryRun = false, bool $verbose = false): array {
    $deleted = 0; $scanned = 0; $deletedFiles = [];
    if (!is_dir($datPath) || !is_readable($datPath)) {
        return ['deleted_count' => 0, 'deleted_files' => [], 'scanned_count' => 0];
    }
    $threshold = time() - ($days * 86400);
    try {
        $dir = new DirectoryIterator($datPath);
    } catch (Throwable $e) {
        return ['deleted_count' => 0, 'deleted_files' => [], 'scanned_count' => 0];
    }
    foreach ($dir as $fi) {
        if ($fi->isDot() || !$fi->isFile()) continue;
        $scanned++;
        if (strtolower(pathinfo($fi->getFilename(), PATHINFO_EXTENSION)) !== 'pdf') continue;
        if ($fi->getMTime() <= $threshold) {
            $full = $fi->getPathname();
            if ($dryRun) {
                $deleted++;
                if ($verbose) $deletedFiles[] = $full;
            } else {
                if (@unlink($full)) {
                    $deleted++;
                    if ($verbose) $deletedFiles[] = $full;
                } // bei Fehlschlag einfach überspringen
            }
        }
    }
    return ['deleted_count' => $deleted, 'deleted_files' => $deletedFiles, 'scanned_count' => $scanned];
}

function cleanup_all(string $baseDir, int $days, bool $dryRun = false, bool $verbose = false): array {
    $totalDeleted = 0; $totalScanned = 0; $touchedDat = 0; $allDeletedFiles = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            function (SplFileInfo $current) {
                if ($current->isDir() && is_excluded_dirname($current->getFilename())) return false;
                return true;
            }
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $fi) {
        if (!$fi->isDir()) continue;
        $dat = $fi->getPathname() . DIRECTORY_SEPARATOR . 'dat';
        if (is_dir($dat)) {
            $touchedDat++;
            $res = purge_old_pdfs_in_dat($dat, $days, $dryRun, $verbose);
            $totalDeleted += $res['deleted_count'];
            $totalScanned += $res['scanned_count'];
            if ($verbose && !empty($res['deleted_files'])) {
                $allDeletedFiles = array_merge($allDeletedFiles, $res['deleted_files']);
            }
        }
    }
    return [
        'base_dir'      => $baseDir,
        'exclude_dirs'  => EXCLUDE_DIRS,
        'days'          => $days,
        'dry_run'       => $dryRun,
        'touched_dat'   => $touchedDat,
        'scanned_files' => $totalScanned,
        'deleted_count' => $totalDeleted,
        'deleted_files' => $verbose ? $allDeletedFiles : null,
    ];
}

// ========================= Parameter Parsing =====================
// Key-Ermittlung (Reihenfolge: FIXED_KEY > ENV > Request/CLI)
$configuredKey = FIXED_KEY !== '' ? FIXED_KEY : (getenv('CLEANUP_KEY') ?: '');

// CLI?
if (PHP_SAPI === 'cli') {

    // --help, --days=, --dry-run, --verbose, --base-dir=, --key=
    $opts = [
        'help::',
        'days::',
        'dry-run::',
        'verbose::',
        'base-dir::',
        'key::',
        'json::'
    ];
    $args = getopt('', $opts);
    if (isset($args['help'])) {
        echo "Usage:\n";
        echo "  php cleanup.php --days=30 --dry-run --verbose --base-dir=/path --key=SECRET --json\n\n";
        echo "Options:\n";
        echo "  --days       Anzahl Tage (Default " . DEFAULT_DAYS . ")\n";
        echo "  --dry-run    Nichts löschen, nur zählen\n";
        echo "  --verbose    Gelöschte Dateien auflisten\n";
        echo "  --base-dir   Basisverzeichnis (Default: " . DEFAULT_BASE_DIR . ")\n";
        echo "  --key        Falls konfiguriert, muss Key passen\n";
        echo "  --json       Ausgabe als JSON (sonst einfache Text-Zusammenfassung)\n";
        exit(0);
    }
    $days    = isset($args['days']) ? max(0, (int)$args['days']) : DEFAULT_DAYS;
    $dryRun  = array_key_exists('dry-run', $args);
    $verbose = array_key_exists('verbose', $args);
    $baseDir = isset($args['base-dir']) ? (string)$args['base-dir'] : DEFAULT_BASE_DIR;
    $keyIn   = isset($args['key']) ? (string)$args['key'] : '';
    $asJson  = array_key_exists('json', $args);
    if ($configuredKey !== '' && $keyIn !== $configuredKey) {
        json_out(false, 'Unauthorized (CLI key mismatch)', [], 401);
    }
    $stats = cleanup_all($baseDir, $days, $dryRun, $verbose);
    if ($asJson) {
        json_out(true, $dryRun
            ? "Dry-Run: {$stats['deleted_count']} Dateien wären gelöscht worden (> {$days} Tage)."
            : "Cleanup: {$stats['deleted_count']} Dateien gelöscht (> {$days} Tage).", $stats, 200);
    } else {

        // Kurze Textausgabe + sinnvoller Exitcode
        echo ($dryRun ? "Dry-Run: " : "Cleanup: ") .
             "{$stats['deleted_count']} gelöscht/zu löschen; " .
             "{$stats['scanned_files']} geprüft; " .
             "{$stats['touched_dat']} dat/-Ordner.\n";
        exit(0);
    }
}

// HTTP (nur POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(false, 'Method not allowed (POST required)', [], 405);
}

$days    = isset($_POST['days']) ? max(0, (int)$_POST['days']) : DEFAULT_DAYS;
$dryRun  = filter_var($_POST['dry_run'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$verbose = filter_var($_POST['verbose'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$baseDir = isset($_POST['base_dir']) ? (string)$_POST['base_dir'] : DEFAULT_BASE_DIR;
$keyIn   = (string)($_POST['key'] ?? '');
if ($configuredKey !== '' && $keyIn !== $configuredKey) {
    json_out(false, 'Unauthorized', [], 401);
}

$stats = cleanup_all($baseDir, $days, $dryRun, $verbose);
json_out(true, $dryRun
    ? "Dry-Run: {$stats['deleted_count']} Dateien wären gelöscht worden (> {$days} Tage)."
    : "Cleanup erfolgreich: {$stats['deleted_count']} Dateien gelöscht (> {$days} Tage).",
    $stats, 200);
