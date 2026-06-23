# Konzept: CSV-Schnittstelle Schiessanlage (Standalone / Wiederverwendbar)

Dieses Dokument beschreibt eine generische, wiederverwendbare CSV-Schnittstelle zwischen einer Webapplikation und einer elektronischen Schiessanlage. Basierend auf der bewährten Implementierung im SKSG-EWS-Projekt, aber **entkoppelt von spezifischen Tabellennamen und Spalten**.

---

## 1. Übersicht

```
┌─────────────────┐     shooters.csv      ┌──────────────────────┐
│   Webapp (PHP)  │ ──────────────────────→│  Elektronische       │
│                 │                        │  Schiessanlage       │
│                 │←──────────────────────│                      │
└─────────────────┘     ResData.csv        └──────────────────────┘
        ↕
   ┌──────────┐
   │  MySQL   │
   └──────────┘
```

**Zwei Datenflüsse:**
- **shooters.csv (Export):** Webapp → Schiessanlage (Schützenliste)
- **ResData.csv (Import):** Schiessanlage → Webapp (Schiessergebnisse)

**Auto-Polling:** Frontend prüft periodisch ob ResData.csv geändert wurde und importiert automatisch.

---

## 2. Dateistruktur

```
projekt-root/
├── shared/
│   └── csv_schnittstelle.php      ← Kern-Logik (Export + Import + Helpers)
├── pages/
│   ├── csv-import/
│   │   └── api.php                ← Polling-API (check, import, status)
│   └── csv-einstellungen.php      ← Konfig-Seite (HTML)
├── js/
│   ├── csv-polling.js             ← Globales Auto-Polling + Badge
│   └── csv-einstellungen.js       ← Konfig-Seite JS
└── sql/
    └── migration_csv.sql          ← DB-Schema (Settings + Log)
```

---

## 3. Datenbank-Schema

### 3.1 Settings-Tabelle (Key-Value)

Nutzt eine bestehende `settings`-Tabelle (oder eigene). Benötigte Keys:

| Key | Typ | Default | Beschreibung |
|-----|-----|---------|-------------|
| `csv_import_aktiv` | `'0'`/`'1'` | `'0'` | Schnittstelle ein/aus |
| `csv_import_intervall` | int (Sek.) | `30` | Polling-Intervall (min. 10) |
| `csv_pfad_shooters` | string | `''` | Absoluter Pfad zu shooters.csv |
| `csv_pfad_resdata` | string | `''` | Absoluter Pfad zu ResData.csv |
| `csv_stichnummer_feld_a` | string | `''` | Stichnummer für Feld A |
| `csv_stichnummer_feld_d` | string | `''` | Stichnummer für Feld D |
| `csv_stichnummer_feld_e` | string | `''` | Stichnummer für Feld E |

### 3.2 Import-Log-Tabelle

```sql
CREATE TABLE IF NOT EXISTS csv_import_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    dateityp        ENUM('shooters','resdata') NOT NULL,
    datei_mtime     INT UNSIGNED NOT NULL         COMMENT 'Unix-Timestamp der Datei beim Import',
    anzahl_verarbeitet  INT UNSIGNED DEFAULT 0,
    anzahl_importiert   INT UNSIGNED DEFAULT 0,
    anzahl_uebersprungen INT UNSIGNED DEFAULT 0,
    anzahl_fehler       INT UNSIGNED DEFAULT 0,
    fehler_details  TEXT                          COMMENT 'Erste 50 Fehlermeldungen',
    dauer_ms        INT UNSIGNED DEFAULT 0,
    erstellt_am     DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_typ_datum (dateityp, erstellt_am DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.3 Migration-Script

```sql
-- migration_csv.sql

-- Settings (idempotent)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('csv_import_aktiv', '0'),
    ('csv_import_intervall', '30'),
    ('csv_pfad_shooters', ''),
    ('csv_pfad_resdata', ''),
    ('csv_stichnummer_feld_a', ''),
    ('csv_stichnummer_feld_d', ''),
    ('csv_stichnummer_feld_e', '');

-- Log-Tabelle
CREATE TABLE IF NOT EXISTS csv_import_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    dateityp        ENUM('shooters','resdata') NOT NULL,
    datei_mtime     INT UNSIGNED NOT NULL,
    anzahl_verarbeitet  INT UNSIGNED DEFAULT 0,
    anzahl_importiert   INT UNSIGNED DEFAULT 0,
    anzahl_uebersprungen INT UNSIGNED DEFAULT 0,
    anzahl_fehler       INT UNSIGNED DEFAULT 0,
    fehler_details  TEXT,
    dauer_ms        INT UNSIGNED DEFAULT 0,
    erstellt_am     DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_typ_datum (dateityp, erstellt_am DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 4. Konfiguration (Generisches Mapping)

Der Kern der Wiederverwendbarkeit: Ein Config-Array definiert das Mapping auf die projektspezifischen Tabellen/Spalten.

### 4.1 PHP-Konfiguration

```php
// shared/csv_config.php

/**
 * Projektspezifisches Mapping — einmal anpassen, Rest funktioniert generisch.
 */
return [
    // ---------- Tabellen + Spalten ----------
    'tables' => [
        'settings' => [
            'table'       => 'settings',          // Key-Value Settings-Tabelle
            'col_key'     => 'setting_key',
            'col_value'   => 'setting_value',
        ],
        'schuetzen' => [
            'table'       => 'schuetzen',          // ← Anpassen
            'col_id'      => 'id',
            'col_mnr'     => 'mitgliedernummer',   // Mitgliedernummer
            'col_name'    => 'nachname',
            'col_vorname' => 'vorname',
            'col_gebdat'  => 'geburtsdatum',       // Format YYYY-MM-DD
        ],
        'standblatt' => [
            'table'       => 'standblattausgaben', // ← Anpassen
            'col_schuetze'=> 'schuetze_id',
            'col_jahr'    => 'jahr',
            'col_feld'    => 'feld',
            'col_waffe'   => 'waffe',
        ],
        'resultate' => [
            'table'       => 'resultate',          // ← Anpassen
            'col_schuetze'=> 'schuetze_id',
            'col_phase'   => 'phase_id',
            'col_runde'   => 'runde',
            'col_jahr'    => 'jahr',
            'col_resultat'=> 'resultat',
            'col_tiefschuss' => 'tiefschuss',
            'col_waffe'   => 'waffe',
            'col_feld'    => 'feld',
            'col_erfasser'=> 'erfasst_von',
            'col_status'  => 'sync_status',        // oder null wenn nicht vorhanden
            'status_value' => 'freigegeben',        // Wert beim Insert
        ],
        'einzelschuesse' => [
            'table'       => 'einzelschuesse',     // ← Anpassen (oder null = keine Einzelschüsse)
            'col_resultat'=> 'resultat_id',
            'col_nr'      => 'schuss_nr',
            'col_wert'    => 'wert',
        ],
        'log' => [
            'table'       => 'csv_import_log',
        ],
    ],

    // ---------- Phase ----------
    // Wie die "Phase" (Wettkampftyp) ermittelt wird
    'phase' => [
        'table'  => 'phasen',                      // ← Anpassen oder null
        'col_id' => 'id',
        'col_code' => 'code',
        'code_value' => 'ews',                     // Welcher Phase-Code
        // ODER: fester Wert statt DB-Lookup:
        // 'fixed_id' => 1,
    ],
    'runde' => 1,                                   // Fester Runden-Wert beim Insert

    // ---------- Feld-Konfiguration ----------
    'felder' => [
        'A' => ['anzahl_schuesse' => 20, 'max_punkte' => 200, 'schwelle' => 190],
        'D' => ['anzahl_schuesse' => 15, 'max_punkte' => 150, 'schwelle' => 140],
        'E' => ['anzahl_schuesse' => 15, 'max_punkte' => 150, 'schwelle' => 140],
    ],

    // ---------- shooters.csv Format ----------
    'shooters_csv' => [
        'delimiter'  => ';',
        'encoding'   => 'ISO-8859-1',              // Ziel-Encoding (Quelle = UTF-8)
        'line_ending' => "\r\n",
        // Spalten-Builder: Callback oder Array von Feld-Keys
        // Default: mitgliedernr;nachname vorname;;;jahrgang(2-stellig)
    ],

    // ---------- ResData.csv Format ----------
    'resdata_csv' => [
        'delimiter'     => ';',
        'col_mnr'       => 0,                       // Spalte: Mitgliedernummer
        'col_ringwert'  => 1,                       // Spalte: Ringwert (0-10)
        'col_wertung'   => 4,                       // Spalte: Wertungsschuss-Flag (1=zählt)
        'col_stich'     => -1,                      // Spalte: Stichnummer (-1 = letzte)
        'system_mnr'    => '000000',                // Mitgliedernr für Systemzeilen (skip)
        'min_columns'   => 28,                      // Mindestanzahl Spalten pro Zeile
    ],

    // ---------- User-ID Ermittlung ----------
    // Callback für aktuellen User (für erfasst_von)
    'get_user_id' => function(PDO $db): ?int {
        // Projektspezifisch anpassen:
        // return $_SESSION['user_id'] ?? null;
        return null; // Fallback: null = kein Erfasser
    },

    // Fallback-User wenn get_user_id null liefert
    'fallback_user' => [
        'table'    => 'benutzer',                   // ← Anpassen
        'col_id'   => 'id',
        'condition' => 'ist_hauptadmin = 1',        // ← Anpassen
    ],
];
```

---

## 5. Kern-Logik (PHP)

### 5.1 shared/csv_schnittstelle.php

```php
<?php
/**
 * CSV-Schnittstelle Schiessanlage — Generische Implementierung
 *
 * generateShootersCsv()  — Export: Webapp → Anlage
 * importResData()        — Import: Anlage → Webapp
 */

// Config laden
$CSV_CONFIG = null;
function getCsvConfig(): array {
    global $CSV_CONFIG;
    if ($CSV_CONFIG === null) {
        $CSV_CONFIG = require __DIR__ . '/csv_config.php';
    }
    return $CSV_CONFIG;
}

// ============================================================
//  Settings-Zugriff
// ============================================================

function getCsvSetting(PDO $db, string $key): string {
    $cfg = getCsvConfig()['tables']['settings'];
    $sql = "SELECT {$cfg['col_value']} FROM {$cfg['table']} WHERE {$cfg['col_key']} = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$key]);
    return (string)$stmt->fetchColumn();
}

function getCsvSettings(PDO $db): array {
    $cfg = getCsvConfig()['tables']['settings'];
    $keys = [
        'csv_import_aktiv', 'csv_import_intervall',
        'csv_pfad_shooters', 'csv_pfad_resdata',
        'csv_stichnummer_feld_a', 'csv_stichnummer_feld_d', 'csv_stichnummer_feld_e',
    ];
    $ph = implode(',', array_fill(0, count($keys), '?'));
    $sql = "SELECT {$cfg['col_key']}, {$cfg['col_value']} FROM {$cfg['table']} WHERE {$cfg['col_key']} IN ($ph)";
    $stmt = $db->prepare($sql);
    $stmt->execute($keys);
    $result = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result[$row[$cfg['col_key']]] = $row[$cfg['col_value']];
    }
    return $result;
}

// ============================================================
//  shooters.csv — Export (Webapp → Schiessanlage)
// ============================================================

/**
 * Generiert shooters.csv mit allen Schützen die ein Standblatt im gegebenen Jahr haben.
 *
 * Format: Mitgliedernr;Nachname Vorname;;;Jahrgang(2-stellig)
 */
function generateShootersCsv(PDO $db, int $jahr): array {
    $config = getCsvConfig();
    $tblS  = $config['tables']['schuetzen'];
    $tblSb = $config['tables']['standblatt'];
    $csvCfg = $config['shooters_csv'];

    $pfad = getCsvSetting($db, 'csv_pfad_shooters');
    if (empty($pfad)) {
        return ['success' => false, 'count' => 0, 'error' => 'Pfad nicht konfiguriert'];
    }

    try {
        $sql = "
            SELECT DISTINCT
                s.{$tblS['col_mnr']}     AS mnr,
                s.{$tblS['col_name']}     AS nachname,
                s.{$tblS['col_vorname']}  AS vorname,
                s.{$tblS['col_gebdat']}   AS geburtsdatum
            FROM {$tblSb['table']} sa
            JOIN {$tblS['table']} s ON sa.{$tblSb['col_schuetze']} = s.{$tblS['col_id']}
            WHERE sa.{$tblSb['col_jahr']} = ?
            ORDER BY s.{$tblS['col_mnr']}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$jahr]);

        $dir = dirname($pfad);
        if (!is_dir($dir)) {
            return ['success' => false, 'count' => 0, 'error' => 'Verzeichnis existiert nicht: ' . $dir];
        }

        $delim = $csvCfg['delimiter'];
        $lines = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $jahrgang = '';
            if (!empty($row['geburtsdatum'])) {
                $year = (int)substr($row['geburtsdatum'], 0, 4);
                $jahrgang = str_pad($year % 100, 2, '0', STR_PAD_LEFT);
            }
            $lines[] = implode($delim, [
                $row['mnr'],
                $row['nachname'] . ' ' . $row['vorname'],
                '', '',
                $jahrgang,
            ]);
        }
        $lineEnding = $csvCfg['line_ending'];
        $newContent = implode($lineEnding, $lines) . ($lines ? $lineEnding : '');

        // Encoding konvertieren (DB = UTF-8 → Anlage erwartet z.B. ISO-8859-1)
        if (($csvCfg['encoding'] ?? 'UTF-8') !== 'UTF-8') {
            $newContent = mb_convert_encoding($newContent, $csvCfg['encoding'], 'UTF-8');
        }
        $count = count($lines);

        // Nur schreiben wenn sich Inhalt geändert hat
        $existing = file_exists($pfad) ? file_get_contents($pfad) : '';
        if ($newContent === $existing) {
            return ['success' => true, 'count' => $count, 'unchanged' => true];
        }

        $written = file_put_contents($pfad, $newContent);
        if ($written === false) {
            return ['success' => false, 'count' => 0, 'error' => 'Datei konnte nicht geschrieben werden: ' . $pfad];
        }

        logCsvImport($db, 'shooters', filemtime($pfad), $count, $count, 0, 0, null, 0);
        return ['success' => true, 'count' => $count];

    } catch (\Exception $e) {
        error_log('[CSV] shooters.csv Fehler: ' . $e->getMessage());
        return ['success' => false, 'count' => 0, 'error' => $e->getMessage()];
    }
}

// ============================================================
//  ResData.csv — Import (Schiessanlage → Webapp)
// ============================================================

/**
 * Importiert Resultate aus ResData.csv.
 */
function importResData(PDO $db, int $jahr): array {
    $startTime = microtime(true);
    $config = getCsvConfig();
    $settings = getCsvSettings($db);
    $pfad = $settings['csv_pfad_resdata'] ?? '';

    $emptyResult = ['success' => false, 'importiert' => 0, 'uebersprungen' => 0, 'fehler' => 0, 'wartend' => 0, 'details' => []];

    if (empty($pfad) || !file_exists($pfad)) {
        return array_merge($emptyResult, ['details' => ['Datei nicht gefunden: ' . $pfad]]);
    }

    // --- Stichnummer → Feld Mapping ---
    $stichMap = [];
    foreach (['a', 'd', 'e'] as $f) {
        $stich = trim($settings['csv_stichnummer_feld_' . $f] ?? '');
        if ($stich !== '') {
            $stichMap[$stich] = strtoupper($f);
        }
    }
    if (empty($stichMap)) {
        return array_merge($emptyResult, ['details' => ['Keine Stichnummern konfiguriert']]);
    }

    // --- Phase-ID ermitteln ---
    $phaseCfg = $config['phase'];
    if (isset($phaseCfg['fixed_id'])) {
        $phaseId = (int)$phaseCfg['fixed_id'];
    } else {
        $stmt = $db->prepare("SELECT {$phaseCfg['col_id']} FROM {$phaseCfg['table']} WHERE {$phaseCfg['col_code']} = ?");
        $stmt->execute([$phaseCfg['code_value']]);
        $phaseId = (int)$stmt->fetchColumn();
        if ($phaseId <= 0) {
            return array_merge($emptyResult, ['details' => ['Phase nicht in DB gefunden']]);
        }
    }

    // --- ResData.csv parsen ---
    $resCfg = $config['resdata_csv'];
    $shots = []; // $shots[mnr][stichnummer][] = ringwert
    $fp = fopen($pfad, 'r');
    if ($fp === false) {
        return array_merge($emptyResult, ['details' => ['Datei konnte nicht gelesen werden']]);
    }

    while (($line = fgets($fp)) !== false) {
        $line = trim($line);
        if ($line === '') continue;

        $cols = explode($resCfg['delimiter'], $line);
        if (count($cols) < $resCfg['min_columns']) continue;

        $mnr = $cols[$resCfg['col_mnr']];
        if ($mnr === $resCfg['system_mnr']) continue;

        $ringwert = (int)$cols[$resCfg['col_ringwert']];

        // Stichnummer: -1 = letzte Spalte
        $stichIdx = $resCfg['col_stich'] < 0
            ? count($cols) + $resCfg['col_stich']
            : $resCfg['col_stich'];
        $stichnummer = trim($cols[$stichIdx] ?? '');

        if (!isset($stichMap[$stichnummer])) continue;

        $shots[$mnr][$stichnummer][] = $ringwert;
    }
    fclose($fp);

    // --- Caches aufbauen ---
    $tblS  = $config['tables']['schuetzen'];
    $tblSb = $config['tables']['standblatt'];
    $tblR  = $config['tables']['resultate'];

    // Schützen-Cache: mnr → id
    $allMnr = array_keys($shots);
    $schuetzenCache = [];
    if (!empty($allMnr)) {
        $ph = implode(',', array_fill(0, count($allMnr), '?'));
        $stmt = $db->prepare("SELECT {$tblS['col_id']}, {$tblS['col_mnr']} FROM {$tblS['table']} WHERE {$tblS['col_mnr']} IN ($ph)");
        $stmt->execute($allMnr);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $schuetzenCache[$row[$tblS['col_mnr']]] = (int)$row[$tblS['col_id']];
        }
    }

    // Bestehende Resultate: schuetze_id → true (Duplikat-Schutz)
    $existingResults = [];
    if (!empty($schuetzenCache)) {
        $ids = array_values($schuetzenCache);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $runde = $config['runde'];
        $stmt = $db->prepare("
            SELECT {$tblR['col_schuetze']}
            FROM {$tblR['table']}
            WHERE {$tblR['col_phase']} = ? AND {$tblR['col_runde']} = ? AND {$tblR['col_jahr']} = ?
              AND {$tblR['col_schuetze']} IN ($ph)
        ");
        $stmt->execute(array_merge([$phaseId, $runde, $jahr], $ids));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingResults[(int)$row[$tblR['col_schuetze']]] = true;
        }
    }

    // Standblatt-Cache: schuetze_id_feld → waffe
    $standblattCache = [];
    if (!empty($schuetzenCache)) {
        $ids = array_values($schuetzenCache);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("
            SELECT {$tblSb['col_schuetze']}, {$tblSb['col_feld']}, {$tblSb['col_waffe']}
            FROM {$tblSb['table']}
            WHERE {$tblSb['col_jahr']} = ? AND {$tblSb['col_schuetze']} IN ($ph)
        ");
        $stmt->execute(array_merge([$jahr], $ids));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $standblattCache[(int)$row[$tblSb['col_schuetze']] . '_' . $row[$tblSb['col_feld']]] = $row[$tblSb['col_waffe']];
        }
    }

    // --- User-ID für erfasst_von ---
    $getUserId = $config['get_user_id'];
    $userId = $getUserId($db);
    if (!$userId && isset($config['fallback_user'])) {
        $fb = $config['fallback_user'];
        $stmt = $db->query("SELECT {$fb['col_id']} FROM {$fb['table']} WHERE {$fb['condition']} LIMIT 1");
        $userId = (int)$stmt->fetchColumn();
    }

    // --- Verarbeitung ---
    $felder = $config['felder'];
    $tblEs = $config['tables']['einzelschuesse'];
    $runde = $config['runde'];

    $importiert = 0;
    $uebersprungen = 0;
    $fehler = 0;
    $wartend = 0;
    $details = [];

    foreach ($shots as $mnr => $stichData) {
        foreach ($stichData as $stichnummer => $ringwerte) {
            $feld = $stichMap[$stichnummer];
            $feldCfg = $felder[$feld];

            // Vollständigkeitsprüfung
            if (count($ringwerte) < $feldCfg['anzahl_schuesse']) {
                $wartend++;
                continue;
            }

            // Nur erwartete Anzahl nehmen
            $ringwerte = array_slice($ringwerte, 0, $feldCfg['anzahl_schuesse']);

            // Schütze in DB?
            if (!isset($schuetzenCache[$mnr])) {
                $fehler++;
                $details[] = "Mitglied $mnr nicht in DB";
                continue;
            }
            $schuetzeId = $schuetzenCache[$mnr];

            // Bereits erfasst?
            if (isset($existingResults[$schuetzeId])) {
                $uebersprungen++;
                continue;
            }

            // Waffe aus Standblatt?
            $cacheKey = $schuetzeId . '_' . $feld;
            if (!isset($standblattCache[$cacheKey])) {
                $fehler++;
                $details[] = "Mitglied $mnr: Kein Standblatt fuer Feld $feld";
                continue;
            }
            $waffe = $standblattCache[$cacheKey];

            // Resultat berechnen
            $resultat = array_sum($ringwerte);
            $tiefschuss = min($ringwerte);

            if ($resultat < 0 || $resultat > $feldCfg['max_punkte']) {
                $fehler++;
                $details[] = "Mitglied $mnr: Resultat $resultat ausserhalb gueltigem Bereich";
                continue;
            }

            // --- INSERT ---
            $db->beginTransaction();
            try {
                // Resultat einfügen
                $cols = [
                    $tblR['col_schuetze'], $tblR['col_phase'], $tblR['col_runde'],
                    $tblR['col_jahr'], $tblR['col_resultat'], $tblR['col_tiefschuss'],
                    $tblR['col_waffe'], $tblR['col_feld'], $tblR['col_erfasser'],
                ];
                $vals = [$schuetzeId, $phaseId, $runde, $jahr, $resultat, $tiefschuss, $waffe, $feld, $userId];

                // Optionaler Status-Spalte
                if (!empty($tblR['col_status'])) {
                    $cols[] = $tblR['col_status'];
                    $vals[] = $tblR['status_value'];
                }

                $colStr = implode(', ', $cols);
                $phStr = implode(', ', array_fill(0, count($cols), '?'));
                $stmt = $db->prepare("INSERT INTO {$tblR['table']} ($colStr) VALUES ($phStr)");
                $stmt->execute($vals);
                $resultatId = (int)$db->lastInsertId();

                // Einzelschüsse speichern (wenn über Schwelle und Tabelle konfiguriert)
                if ($tblEs && $tblEs['table'] && $resultat > $feldCfg['schwelle']) {
                    $stmtEs = $db->prepare("
                        INSERT INTO {$tblEs['table']} ({$tblEs['col_resultat']}, {$tblEs['col_nr']}, {$tblEs['col_wert']})
                        VALUES (?, ?, ?)
                    ");
                    foreach ($ringwerte as $i => $wert) {
                        $stmtEs->execute([$resultatId, $i + 1, $wert]);
                    }
                }

                $db->commit();
                $importiert++;
                $existingResults[$schuetzeId] = true;

            } catch (\Exception $e) {
                $db->rollBack();
                $fehler++;
                $details[] = "Mitglied $mnr: DB-Fehler — " . $e->getMessage();
                error_log("[CSV] Import Fehler Mitglied $mnr: " . $e->getMessage());
            }
        }
    }

    // Log
    $dauer = (int)((microtime(true) - $startTime) * 1000);
    $verarbeitet = $importiert + $uebersprungen + $fehler + $wartend;
    $fehlerText = !empty($details) ? implode("\n", array_slice($details, 0, 50)) : null;
    logCsvImport($db, 'resdata', filemtime($pfad), $verarbeitet, $importiert, $uebersprungen + $wartend, $fehler, $fehlerText, $dauer);

    return [
        'success' => true,
        'importiert' => $importiert,
        'uebersprungen' => $uebersprungen,
        'fehler' => $fehler,
        'wartend' => $wartend,
        'details' => $details,
    ];
}

// ============================================================
//  Helpers
// ============================================================

function checkResDataChanged(PDO $db): array {
    $config = getCsvConfig();
    $logTbl = $config['tables']['log']['table'];

    $pfad = getCsvSetting($db, 'csv_pfad_resdata');
    if (empty($pfad) || !file_exists($pfad)) {
        return ['changed' => false, 'mtime' => 0];
    }

    $currentMtime = filemtime($pfad);
    $stmt = $db->prepare("SELECT datei_mtime FROM $logTbl WHERE dateityp = 'resdata' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $lastMtime = (int)$stmt->fetchColumn();

    return ['changed' => $currentMtime !== $lastMtime, 'mtime' => $currentMtime];
}

function logCsvImport(PDO $db, string $dateityp, int $mtime, int $verarbeitet, int $importiert, int $uebersprungen, int $fehler, ?string $fehlerDetails, int $dauerMs): void {
    $logTbl = getCsvConfig()['tables']['log']['table'];
    try {
        $stmt = $db->prepare("
            INSERT INTO $logTbl
                (dateityp, datei_mtime, anzahl_verarbeitet, anzahl_importiert, anzahl_uebersprungen, anzahl_fehler, fehler_details, dauer_ms)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$dateityp, $mtime, $verarbeitet, $importiert, $uebersprungen, $fehler, $fehlerDetails, $dauerMs]);
    } catch (\Exception $e) {
        error_log('[CSV] Log-Fehler: ' . $e->getMessage());
    }
}
```

---

## 6. API-Endpoints

### 6.1 pages/csv-import/api.php (Polling-API)

```php
<?php
/**
 * CSV-Schnittstelle Polling-API
 *
 * GET  ?action=check_config   → Aktiv + Intervall
 * GET  ?action=check          → ResData.csv geändert?
 * GET  ?action=status         → Letzter Import-Status
 * POST action=import_resdata  → Import auslösen
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../dbconnect.inc.php';
require_once __DIR__ . '/../../shared/auth.php';
require_once __DIR__ . '/../../shared/response.php';
require_once __DIR__ . '/../../shared/csv_schnittstelle.php';

requireAuth();

$db = getDB();
$config = getCsvConfig();
$logTbl = $config['tables']['log']['table'];

// === GET ===
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'check_config':
            $settings = getCsvSettings($db);
            jsonResponse([
                'success'  => true,
                'aktiv'    => ($settings['csv_import_aktiv'] ?? '0') === '1',
                'intervall' => max(10, (int)($settings['csv_import_intervall'] ?? 30)),
            ]);

        case 'check':
            $result = checkResDataChanged($db);
            jsonResponse([
                'success'        => true,
                'resdata_changed' => $result['changed'],
                'mtime'          => $result['mtime'],
            ]);

        case 'status':
            $stmtS = $db->prepare("SELECT anzahl_importiert, anzahl_fehler, erstellt_am FROM $logTbl WHERE dateityp = 'shooters' ORDER BY id DESC LIMIT 1");
            $stmtS->execute();
            $shooters = $stmtS->fetch(PDO::FETCH_ASSOC) ?: null;

            $stmtR = $db->prepare("SELECT anzahl_verarbeitet, anzahl_importiert, anzahl_uebersprungen, anzahl_fehler, erstellt_am FROM $logTbl WHERE dateityp = 'resdata' ORDER BY id DESC LIMIT 1");
            $stmtR->execute();
            $resdata = $stmtR->fetch(PDO::FETCH_ASSOC) ?: null;

            jsonResponse(['success' => true, 'shooters' => $shooters, 'resdata' => $resdata]);

        default:
            jsonResponse(['success' => false, 'message' => 'Unbekannte Action'], 400);
    }
}

// === POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? ($_POST['action'] ?? '');

    switch ($action) {
        case 'import_resdata':
            $jahr = (int)date('Y');
            $result = importResData($db, $jahr);
            jsonResponse([
                'success'       => $result['success'],
                'importiert'    => $result['importiert'],
                'uebersprungen' => $result['uebersprungen'],
                'fehler'        => $result['fehler'],
                'wartend'       => $result['wartend'],
                'message'       => $result['importiert'] > 0
                    ? $result['importiert'] . ' Resultat(e) importiert'
                    : 'Keine neuen Resultate',
            ]);

        default:
            jsonResponse(['success' => false, 'message' => 'Unbekannte Action'], 400);
    }
}

jsonResponse(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
```

### 6.2 Settings-API (load/save)

Im bestehenden Einstellungen-API oder eigener Datei:

```php
// GET ?action=load_csv_settings
case 'load_csv_settings':
    $data = getCsvSettings($db);
    jsonResponse(['success' => true, 'data' => $data]);

// POST action=save_csv_settings
case 'save_csv_settings':
    // requireCsrf() muss vorher aufgerufen sein
    $keys = [
        'csv_import_aktiv', 'csv_import_intervall',
        'csv_pfad_shooters', 'csv_pfad_resdata',
        'csv_stichnummer_feld_a', 'csv_stichnummer_feld_d', 'csv_stichnummer_feld_e',
    ];
    $cfg = getCsvConfig()['tables']['settings'];
    $stmt = $db->prepare("
        INSERT INTO {$cfg['table']} ({$cfg['col_key']}, {$cfg['col_value']})
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE {$cfg['col_value']} = VALUES({$cfg['col_value']})
    ");
    foreach ($keys as $k) {
        if (isset($input[$k])) {
            $stmt->execute([$k, $input[$k]]);
        }
    }

    // shooters.csv sofort generieren wenn aktiv
    if (($input['csv_import_aktiv'] ?? '0') === '1' && !empty($input['csv_pfad_shooters'])) {
        $csvResult = generateShootersCsv($db, (int)date('Y'));
    }

    jsonResponse(['success' => true, 'message' => 'CSV-Einstellungen gespeichert']);
```

---

## 7. Konfigurationsseite (HTML + JS)

### 7.1 HTML (pages/csv-einstellungen.php oder Sektion in Einstellungen)

```html
<!-- CSV-Schnittstelle (Schiessanlage) -->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-arrow-left-right me-1"></i>CSV-Schnittstelle (Schiessanlage)</span>
        <button class="btn btn-primary btn-sm" onclick="CsvSettings.save()">
            <i class="bi bi-save me-1"></i>Speichern
        </button>
    </div>
    <div class="card-body">

        <!-- Aktiv + Intervall -->
        <div class="d-flex flex-wrap gap-3 mb-3">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="csvImportAktiv">
                <label class="form-check-label" for="csvImportAktiv">Schnittstelle aktiv</label>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label for="csvImportIntervall" class="form-label mb-0 small">Intervall:</label>
                <input type="number" id="csvImportIntervall" class="form-control form-control-sm"
                       style="width:70px;" min="10" max="300" value="30">
                <span class="small">Sek.</span>
            </div>
        </div>

        <!-- Dateipfade -->
        <div class="row g-2 mb-3">
            <div class="col-12">
                <label class="form-label small fw-semibold">Pfad shooters.csv:</label>
                <div class="input-group input-group-sm">
                    <input type="text" id="csvPfadShooters" class="form-control"
                           placeholder="z.B. C:\Schiessanlage\shooters.csv">
                    <button class="btn btn-outline-secondary" type="button"
                            onclick="CsvSettings.browse('csvPfadShooters', '.csv')">
                        <i class="bi bi-folder2-open"></i>
                    </button>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Pfad ResData.csv:</label>
                <div class="input-group input-group-sm">
                    <input type="text" id="csvPfadResdata" class="form-control"
                           placeholder="z.B. C:\Schiessanlage\ResData.csv">
                    <button class="btn btn-outline-secondary" type="button"
                            onclick="CsvSettings.browse('csvPfadResdata', '.csv')">
                        <i class="bi bi-folder2-open"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Stichnummern -->
        <div class="mb-3">
            <div class="text-uppercase small text-muted fw-semibold mb-1">
                Stichnummern (letzte Spalte ResData)
            </div>
            <div class="d-flex gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-1">
                    <label for="csvStichA" class="small fw-semibold">Feld A:</label>
                    <input type="text" id="csvStichA" class="form-control form-control-sm"
                           style="width:80px; text-align:center;">
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label for="csvStichD" class="small fw-semibold">Feld D:</label>
                    <input type="text" id="csvStichD" class="form-control form-control-sm"
                           style="width:80px; text-align:center;">
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label for="csvStichE" class="small fw-semibold">Feld E:</label>
                    <input type="text" id="csvStichE" class="form-control form-control-sm"
                           style="width:80px; text-align:center;">
                </div>
            </div>
        </div>

        <!-- Status -->
        <div id="csvImportStatus" class="small text-muted border-top pt-2" style="display:none;">
            <div class="text-uppercase small text-muted fw-semibold mb-1">Letzter Import</div>
            <div id="csvStatusShooters"></div>
            <div id="csvStatusResdata"></div>
        </div>

    </div>
</div>
```

### 7.2 JavaScript — Konfig-Seite (js/csv-einstellungen.js)

```js
const CsvSettings = {

    /** API-Basispfad — projektspezifisch anpassen */
    apiSettings: 'pages/einstellungen/api.php',   // load/save
    apiPolling:  'pages/csv-import/api.php',       // status

    init() {
        this.load();
    },

    load() {
        $.getJSON(this.apiSettings + '?action=load_csv_settings', (res) => {
            if (!res.success) return;
            const d = res.data;
            $('#csvImportAktiv').prop('checked', d.csv_import_aktiv === '1');
            $('#csvImportIntervall').val(d.csv_import_intervall || '30');
            $('#csvPfadShooters').val(d.csv_pfad_shooters || '');
            $('#csvPfadResdata').val(d.csv_pfad_resdata || '');
            $('#csvStichA').val(d.csv_stichnummer_feld_a || '');
            $('#csvStichD').val(d.csv_stichnummer_feld_d || '');
            $('#csvStichE').val(d.csv_stichnummer_feld_e || '');
        });
        this._loadStatus();
    },

    _loadStatus() {
        $.getJSON(this.apiPolling + '?action=status', (res) => {
            if (!res.success) return;
            const $wrap = $('#csvImportStatus');
            let hasData = false;

            if (res.shooters) {
                const t = new Date(res.shooters.erstellt_am).toLocaleString('de-CH');
                $('#csvStatusShooters').html(
                    `<i class="bi bi-upload me-1"></i>shooters.csv: ${t} — ${res.shooters.anzahl_importiert} Schuetzen`
                );
                hasData = true;
            }
            if (res.resdata) {
                const t = new Date(res.resdata.erstellt_am).toLocaleString('de-CH');
                const parts = [`${res.resdata.anzahl_importiert} importiert`];
                if (parseInt(res.resdata.anzahl_uebersprungen) > 0)
                    parts.push(`${res.resdata.anzahl_uebersprungen} uebersprungen`);
                if (parseInt(res.resdata.anzahl_fehler) > 0)
                    parts.push(`${res.resdata.anzahl_fehler} Fehler`);
                $('#csvStatusResdata').html(
                    `<i class="bi bi-download me-1"></i>ResData.csv: ${t} — ${parts.join(', ')}`
                );
                hasData = true;
            }
            $wrap.toggle(hasData);
        });
    },

    save() {
        const data = {
            action: 'save_csv_settings',
            csv_import_aktiv: $('#csvImportAktiv').is(':checked') ? '1' : '0',
            csv_import_intervall: $('#csvImportIntervall').val(),
            csv_pfad_shooters: $('#csvPfadShooters').val().trim(),
            csv_pfad_resdata: $('#csvPfadResdata').val().trim(),
            csv_stichnummer_feld_a: $('#csvStichA').val().trim(),
            csv_stichnummer_feld_d: $('#csvStichD').val().trim(),
            csv_stichnummer_feld_e: $('#csvStichE').val().trim(),
        };
        $.ajax({
            url: this.apiSettings,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: (res) => {
                if (res.success) {
                    // Toast oder Alert — projektspezifisch
                    alert('CSV-Einstellungen gespeichert');
                    this._loadStatus();
                } else {
                    alert('Fehler: ' + (res.message || 'Unbekannt'));
                }
            }
        });
    },

    browse(inputId, filter) {
        // Projektspezifisch: Datei-Browser-Modal öffnen
        // oder einfach Pfad manuell eingeben lassen
        console.log('Datei-Browser für', inputId, filter);
    }
};
```

### 7.3 JavaScript — Globales Polling (js/csv-polling.js)

```js
const CsvPolling = {
    _timer: null,
    _active: false,
    _lastImport: null,
    _lastCheck: null,
    _lastMtime: 0,

    /** API-Basispfad — projektspezifisch anpassen */
    api: 'pages/csv-import/api.php',

    /**
     * Auf jeder Seite beim Laden aufrufen.
     */
    init() {
        $.getJSON(this.api + '?action=check_config', (res) => {
            if (!res.success || !res.aktiv) {
                this._updateBadge(false);
                return;
            }
            this._active = true;
            this._loadLastStatus();
            const interval = Math.max(10, res.intervall) * 1000;
            this._tick();
            this._timer = setInterval(() => this._tick(), interval);
        }).fail(() => {
            // Schnittstelle nicht verfügbar — still ignorieren
        });
    },

    _loadLastStatus() {
        $.getJSON(this.api + '?action=status', (res) => {
            if (!res.success) return;
            if (res.resdata && res.resdata.erstellt_am) {
                this._lastImport = res.resdata;
            }
            this._updateBadge(true);
        });
        $.getJSON(this.api + '?action=check', (res) => {
            if (res.success && res.mtime) {
                this._lastMtime = res.mtime;
            }
        });
    },

    _tick() {
        this._lastCheck = new Date();
        this._updateBadge(true);

        $.getJSON(this.api + '?action=check', (res) => {
            if (!res.success) return;

            // mtime unverändert → nichts tun
            if (res.mtime && res.mtime === this._lastMtime) return;
            if (!res.resdata_changed && res.mtime === this._lastMtime) return;

            this._lastMtime = res.mtime;

            $.ajax({
                url: this.api,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ action: 'import_resdata' }),
                success: (importRes) => {
                    if (!importRes.success) return;
                    this._lastImport = {
                        erstellt_am: new Date().toISOString(),
                        anzahl_importiert: importRes.importiert,
                        anzahl_uebersprungen: importRes.uebersprungen + importRes.wartend,
                        anzahl_fehler: importRes.fehler,
                    };
                    this._updateBadge(true);

                    // Toast bei erfolgreichem Import — projektspezifisch anpassen
                    if (importRes.importiert > 0) {
                        this._showToast(importRes.importiert + ' Resultat(e) importiert');
                    }
                }
            });
        });
    },

    _updateBadge(active) {
        const $badge = $('#csvStatusBadge');
        if (!$badge.length) return;

        if (!active) {
            $badge.addClass('d-none');
            return;
        }

        const checkStr = this._lastCheck
            ? this._lastCheck.toLocaleTimeString('de-CH', { hour:'2-digit', minute:'2-digit', second:'2-digit' })
            : '';

        let tooltipParts = ['CSV-Schnittstelle aktiv'];
        if (checkStr) tooltipParts.push('Letzter Check: ' + checkStr);

        if (this._lastImport && this._lastImport.erstellt_am) {
            const d = new Date(this._lastImport.erstellt_am);
            const t = d.toLocaleTimeString('de-CH', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
            const detail = [];
            const imp  = parseInt(this._lastImport.anzahl_importiert) || 0;
            const skip = parseInt(this._lastImport.anzahl_uebersprungen) || 0;
            const err  = parseInt(this._lastImport.anzahl_fehler) || 0;
            if (imp > 0)  detail.push(imp + ' importiert');
            if (skip > 0) detail.push(skip + ' uebersprungen');
            if (err > 0)  detail.push(err + ' Fehler');
            tooltipParts.push('Letzter Import: ' + t + (detail.length ? ' (' + detail.join(', ') + ')' : ''));
        } else {
            tooltipParts.push('Noch kein Import');
        }

        $badge.removeClass('d-none')
               .attr('title', tooltipParts.join('\n'))
               .html('<i class="bi bi-arrow-left-right me-1"></i>' + (checkStr || 'Aktiv'));
    },

    /** Projektspezifisch überschreiben */
    _showToast(message) {
        if (typeof ewsToast === 'function') {
            ewsToast(message, 'success');
        } else {
            console.log('[CSV]', message);
        }
    }
};

// Auto-Init beim Laden
$(document).ready(() => CsvPolling.init());
```

---

## 8. Integration ins neue Projekt — Checkliste

### Schritt 1: Dateien kopieren
- [ ] `shared/csv_config.php` — Tabellen-Mapping anpassen
- [ ] `shared/csv_schnittstelle.php` — Kern-Logik (unverändert)
- [ ] `pages/csv-import/api.php` — Polling-API
- [ ] `js/csv-polling.js` — Globales Polling
- [ ] `js/csv-einstellungen.js` — Konfig-UI
- [ ] SQL-Migration ausführen

### Schritt 2: csv_config.php anpassen
- [ ] Tabellennamen + Spaltennamen auf eigenes Schema mappen
- [ ] Phase-Konfiguration: `fixed_id` oder DB-Lookup
- [ ] `get_user_id`-Callback auf eigenes Auth-System
- [ ] `fallback_user`-Query anpassen
- [ ] Felder (A/D/E) + Schussanzahlen prüfen

### Schritt 3: Frontend einbinden
- [ ] jQuery + Bootstrap 5 vorhanden (oder ersetzen)
- [ ] `#csvStatusBadge` Element in Navbar/Header einfügen
- [ ] `csv-polling.js` global laden (auf jeder Seite)
- [ ] Konfig-Seite einbinden (eigene Seite oder Einstellungen-Sektion)
- [ ] CSRF-Token im AJAX-Header setzen

### Schritt 4: Hooks setzen
- [ ] Bei Standblattausgabe (speichern/löschen): `generateShootersCsv()` aufrufen
- [ ] `requireAuth()` / `requireCsrf()` an eigenes Auth-System anpassen

---

## 9. Datenfluss-Diagramm

```
EXPORT (bei Standblatt-Änderung):
  Standblatt speichern/löschen
    → generateShootersCsv($db, $jahr)
      → SELECT Schützen mit Standblatt
      → CSV bauen (Semikolon, ISO-8859-1, CRLF)
      → Nur schreiben wenn Inhalt geändert
      → Log in csv_import_log

IMPORT (Auto-Polling alle X Sekunden):
  Frontend: CsvPolling._tick()
    → GET check → mtime geändert?
      → Nein: Skip
      → Ja: POST import_resdata
        → importResData($db, $jahr)
          → ResData.csv parsen (Schüsse gruppieren nach Mitglied+Stich)
          → Caches laden (Schützen, Resultate, Standblätter)
          → Pro Mitglied/Feld:
            ✓ Vollständig? (A≥20, D/E≥15 Schuss)
            ✓ Schütze in DB?
            ✓ Noch kein Resultat?
            ✓ Standblatt vorhanden?
            → INSERT Resultat + ggf. Einzelschüsse
          → Log in csv_import_log
        → Badge + Toast aktualisieren
```

---

## 10. ResData.csv Spalten-Referenz

| Spalte | Index | Inhalt | Beispiel |
|--------|-------|--------|----------|
| Mitgliedernr | 0 | Eindeutige ID | `112101` |
| Ringwert | 1 | Punkte (0-10) | `9` |
| Wertungsflag | 4 | 1=zählt, 0=Probe | `1` |
| ... | 2-26 | Anlagespezifisch | — |
| Stichnummer | letzte | Feld-Zuordnung | `STICH001` |

**Systemzeile:** Mitgliedernr `000000` → wird ignoriert.
**Mindestens 28 Spalten** pro Zeile, sonst wird die Zeile übersprungen.

---

## 11. shooters.csv Format-Referenz

```
Mitgliedernr;Nachname Vorname;;;Jahrgang(2-stellig)\r\n
```

| Spalte | Inhalt | Beispiel |
|--------|--------|----------|
| 1 | Mitgliedernummer | `112101` |
| 2 | Nachname + Vorname | `Muster Hans` |
| 3 | (leer) | |
| 4 | (leer) | |
| 5 | 2-stelliger Jahrgang | `86` |

**Encoding:** ISO-8859-1 (westeuropäisch), **Zeilenende:** CRLF, **Trennzeichen:** Semikolon, **Kein Header.**
