<?php
/**
 * inc/migrations.inc.php — Migrations-Runner (PDO).
 *
 * Portiert von der SKSG-Jungschuetzen-Loesung. Wendet SQL-Dateien aus
 * migrations/ an, die noch nicht in der Tracking-Tabelle `schema_migrationen`
 * vermerkt sind. Migrationen werden sortiert nach Dateiname angewendet, beim
 * ersten Fehler wird abgebrochen.
 *
 * WICHTIG (Baseline): msvjm hatte vor Einfuehrung dieses Tools bereits
 * Migrationen manuell eingespielt. Damit der erste Lauf nicht alle Altlasten
 * erneut anwendet (und mit "Duplicate column" o.ae. crasht), markiert
 * markBaseline() alle aktuell vorhandenen Dateien als angewendet, ohne sie
 * auszufuehren. Danach laufen nur noch NEUE Migrationen.
 */

const MIGRATIONS_TABLE = 'schema_migrationen';

/**
 * Splittet einen SQL-String in einzelne Statements — string- und kommentar-aware.
 * Trennt nur an `;` ausserhalb von Strings/Backticks/Block-Kommentaren.
 * Zeilen-Kommentare (`-- ...`) muessen vorher entfernt sein.
 */
function splitSqlStatements(string $sql): array {
    $statements = [];
    $buf       = '';
    $len       = strlen($sql);
    $inSingle  = false;
    $inDouble  = false;
    $inBackt   = false;
    $inBlock   = false;

    for ($i = 0; $i < $len; $i++) {
        $c    = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($inBlock) {
            $buf .= $c;
            if ($c === '*' && $next === '/') { $buf .= $next; $i++; $inBlock = false; }
            continue;
        }

        if ($inSingle || $inDouble || $inBackt) {
            $buf .= $c;
            $quote = $inSingle ? "'" : ($inDouble ? '"' : '`');
            if ($c === '\\' && $next !== '') {
                $buf .= $next; $i++;
                continue;
            }
            if ($c === $quote) {
                if ($next === $quote) { $buf .= $next; $i++; continue; }
                $inSingle = $inDouble = $inBackt = false;
            }
            continue;
        }

        if ($c === '/' && $next === '*') { $buf .= $c . $next; $i++; $inBlock = true; continue; }
        if ($c === "'") { $buf .= $c; $inSingle = true; continue; }
        if ($c === '"') { $buf .= $c; $inDouble = true; continue; }
        if ($c === '`') { $buf .= $c; $inBackt  = true; continue; }
        if ($c === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') $statements[] = $stmt;
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    $tail = trim($buf);
    if ($tail !== '') $statements[] = $tail;
    return $statements;
}

/** Legt die Tracking-Tabelle an, falls sie fehlt. */
function ensureMigrationsTable(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS `" . MIGRATIONS_TABLE . "` (
        ID INT AUTO_INCREMENT PRIMARY KEY,
        Dateiname VARCHAR(255) NOT NULL UNIQUE,
        AngewendetAm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Liste aller Migrationsdateien (sortiert nach Dateiname). */
function migrationFiles(string $dir): array {
    $files = glob($dir . '/*.sql') ?: [];
    sort($files);
    return $files;
}

/** Bereits angewendete Dateinamen aus der Tracking-Tabelle. */
function appliedMigrations(PDO $db): array {
    ensureMigrationsTable($db);
    return array_column(
        $db->query("SELECT Dateiname FROM `" . MIGRATIONS_TABLE . "`")->fetchAll(),
        'Dateiname'
    );
}

/** True, wenn die Tracking-Tabelle (noch) keine Eintraege hat. */
function migrationsTrackingEmpty(PDO $db): bool {
    ensureMigrationsTable($db);
    return (int)$db->query("SELECT COUNT(*) FROM `" . MIGRATIONS_TABLE . "`")->fetchColumn() === 0;
}

/**
 * Baseline: alle aktuell vorhandenen Migrationsdateien als angewendet
 * markieren, OHNE sie auszufuehren. Nutzt INSERT IGNORE → idempotent.
 *
 * @return int Anzahl neu markierter Dateien
 */
function markBaseline(PDO $db, string $dir): int {
    ensureMigrationsTable($db);
    $stmt = $db->prepare("INSERT IGNORE INTO `" . MIGRATIONS_TABLE . "` (Dateiname) VALUES (?)");
    $count = 0;
    foreach (migrationFiles($dir) as $file) {
        $stmt->execute([basename($file)]);
        $count += $stmt->rowCount();
    }
    return $count;
}

/**
 * Wendet alle noch nicht getrackten Migrationen an. Bricht beim ersten Fehler ab.
 *
 * @return array{applied: list<string>, errors: list<string>}
 */
function runPendingMigrations(PDO $db, string $dir): array {
    $result = ['applied' => [], 'errors' => []];
    ensureMigrationsTable($db);

    $files = migrationFiles($dir);
    if (!$files) return $result;

    $applied = appliedMigrations($db);
    $insert  = $db->prepare("INSERT IGNORE INTO `" . MIGRATIONS_TABLE . "` (Dateiname) VALUES (?)");

    foreach ($files as $file) {
        $base = basename($file);
        if (in_array($base, $applied, true)) continue;

        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            // Leere Datei: als angewendet markieren, damit sie nicht ewig "pending" bleibt.
            $insert->execute([$base]);
            $result['applied'][] = $base . ' (leer)';
            continue;
        }

        // Zeilen-Kommentare raus, BEVOR auf `;` gesplittet wird.
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $statements = splitSqlStatements($sql);
        if (empty($statements)) {
            $insert->execute([$base]);
            $result['applied'][] = $base . ' (nur Kommentare)';
            continue;
        }

        try {
            foreach ($statements as $stmt) {
                $r = $db->query($stmt);
                if ($r instanceof PDOStatement) {
                    try { while ($r->nextRowset()) {} } catch (PDOException $ignore) {}
                    $r->closeCursor();
                }
            }
            $insert->execute([$base]);
            $result['applied'][] = $base;
        } catch (Throwable $e) {
            $result['errors'][] = $base . ': ' . $e->getMessage();
            break;
        }
    }

    return $result;
}
