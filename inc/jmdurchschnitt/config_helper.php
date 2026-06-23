<?php
// config_helper.php
// Gemeinsame Logik fuer die "Anzahl zaehlende Resultate" pro Jahr.
// Wird von calculate_averages.php, export_averages_pdf.php sowie den
// get_config/save_config-Endpoints verwendet.

if (!defined('JMDURCHSCHNITT_DEFAULT_ZAEHLENDE')) {
    define('JMDURCHSCHNITT_DEFAULT_ZAEHLENDE', 6);
}

/**
 * Stellt sicher, dass die Konfigurationstabelle existiert (selbstheilend,
 * falls die Migration noch nicht eingespielt wurde).
 */
function ensureDurchschnittConfigTable($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS jmdurchschnitt_config (
        year INT NOT NULL,
        anzahl_zaehlende INT NOT NULL DEFAULT 6,
        PRIMARY KEY (year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Liefert die Konfiguration fuer ein Jahr.
 *
 * Reihenfolge: exakter Eintrag fuer das Jahr -> sonst Wert des letzten
 * vorhandenen Vorjahres -> sonst Default (6).
 *
 * @return array{anzahl_zaehlende:int, inherited:bool, source_year:?int}
 */
function getDurchschnittConfig($conn, $year) {
    ensureDurchschnittConfigTable($conn);
    $year = intval($year);

    // 1. Exakter Eintrag fuer das Jahr
    $stmt = $conn->prepare("SELECT anzahl_zaehlende FROM jmdurchschnitt_config WHERE year = ?");
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return [
            'anzahl_zaehlende' => intval($row['anzahl_zaehlende']),
            'inherited'        => false,
            'source_year'      => $year,
        ];
    }

    // 2. Letztes vorhandenes Vorjahr
    $stmt = $conn->prepare("SELECT year, anzahl_zaehlende FROM jmdurchschnitt_config WHERE year < ? ORDER BY year DESC LIMIT 1");
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $prev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($prev) {
        return [
            'anzahl_zaehlende' => intval($prev['anzahl_zaehlende']),
            'inherited'        => true,
            'source_year'      => intval($prev['year']),
        ];
    }

    // 3. Default
    return [
        'anzahl_zaehlende' => JMDURCHSCHNITT_DEFAULT_ZAEHLENDE,
        'inherited'        => true,
        'source_year'      => null,
    ];
}

/**
 * Berechnet die Anzahl der zu verwendenden (zaehlenden) Resultate.
 *
 * Bis zur Schwelle: die besten $anzahlZaehlende Resultate.
 * Bei vielen Teilnehmern greift die Haelfte-Regel, sobald diese hoeher
 * ist als $anzahlZaehlende (entspricht dem bisherigen Verhalten fuer 6).
 *
 * @param int $teilnehmerAnzahl Anzahl der Teilnehmer
 * @param int $anzahlZaehlende  Konfigurierte Basis-Anzahl zaehlender Resultate
 * @return int
 */
function calculateUsedResults($teilnehmerAnzahl, $anzahlZaehlende = JMDURCHSCHNITT_DEFAULT_ZAEHLENDE) {
    $basis   = min($anzahlZaehlende, $teilnehmerAnzahl);
    $haelfte = intval(floor($teilnehmerAnzahl / 2));
    return max($basis, $haelfte);
}
?>
