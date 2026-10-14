<?php
/**
 * load_endschresultate_improved.php
 * Improved version with security fixes, better structure, and maintainability
 * 
 * @author System Improvement
 * @version 2.0
 * @description Loads and displays shooting competition results with improved security and structure
 */

// Move function definitions to the top for better organization
/**
 * Calculate points based on shooting score using defined thresholds
 * 
 * @param int|float $value The shooting score
 * @return int Points awarded (0-10)
 */
function calculatePoints($value) {
    // Define scoring thresholds as constants for better maintainability
    $thresholds = [
        91 => 10, 81 => 9, 71 => 8, 61 => 7, 51 => 6,
        41 => 5, 31 => 4, 21 => 3, 11 => 2, 1 => 1
    ];
    
    foreach ($thresholds as $threshold => $points) {
        if ($value >= $threshold) {
            return $points;
        }
    }
    return 0;
}

/**
 * Validate year input to prevent invalid values
 * 
 * @param mixed $year Year to validate
 * @return bool True if valid, false otherwise
 */
function validateYear($year) {
    $currentYear = date('Y');
    $minYear = 2000; // Adjust based on your data range
    $maxYear = $currentYear + 1;
    
    return is_numeric($year) && $year >= $minYear && $year <= $maxYear;
}

/**
 * Eine Tabellenzelle je Stich: Resultat, «gelöst» (bezahlt, noch kein Resultat) oder «–» (nicht gelöst).
 *
 * @param bool        $geloest  Stich gelöst
 * @param string|null $wert     Anzeige des Resultats oder null, wenn keines erfasst ist
 * @param string      $label    Text der «gelöst»-Marke (Schwini: P1/P2 einzeln)
 */
function stichZelle(bool $geloest, ?string $wert, string $label = 'gelöst') {
    if ($wert !== null) {
        return "<td>" . htmlspecialchars($wert, ENT_QUOTES, 'UTF-8') . "</td>";
    }
    if ($geloest) {
        return "<td><span class='geloest-pill'>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</span></td>";
    }
    return "<td><span class='cell-empty' aria-label='nicht gelöst'>–</span></td>";
}

/**
 * Warum jemand nicht in der Rangliste steht (Regeln von endschGesamtAbfrage()).
 */
function endschOhneWertungGrund(array $row): string {
    $typ = $row['TeilnehmerTyp'] ?? 'mitglied';
    if ($typ === 'js')   return 'Jungschützen werden im Endschiessen nicht rangiert';
    if ($typ === 'gast') return 'Gäste stehen erst mit allen fünf Stichen in Kat. B';
    if (!preg_match('/Kat\.\s*[AB]/', (string)($row['Kategorie'] ?? ''))) return 'Keine Kategorie: beim Mitglied fehlt die Waffe';
    if ((int)$row['Endstich_Summe'] <= 0) return 'Endstich noch nicht erfasst';
    return 'Nicht in der Gesamtrangliste (erster Endstich-Schuss fehlt)';
}

/**
 * Tabellenzeile eines Mitglieds. «Stand» ist offen, sobald ein gelöster Stich noch kein Resultat hat.
 * Seit 1.68.29 auch die Rangliste: Rang je Kategorie vor dem Namen und Total, beides aus $rang
 * (endschGesamtAbfrage(), dieselbe Rechnung wie die Gesamtrangliste im PDF).
 *
 * @param array      $row  Datenbankzeile mit Mitglied, Summen und gelösten Codes
 * @param array|null $rang ['kat' => 'A'|'B', 'rang' => int, 'total' => float] oder null (ohne Wertung)
 * @return string HTML-Zeile
 */
function generateResultRow($row, ?array $rang = null) {
    $codes = array_filter(array_map('trim', explode(',', (string)($row['geloeste_codes'] ?? ''))));
    $hat = fn(array $c) => (bool)array_intersect($c, $codes);

    $s1 = (int)$row['Schwini_Summe1'];
    $s2 = (int)$row['Schwini_Summe2'];
    $zabig = 0;
    for ($i = 1; $i <= 6; $i++) {
        $zabig += calculatePoints($row['ZSchuss' . $i] ?? 0);
    }
    $zabigErfasst = false;
    for ($i = 1; $i <= 6; $i++) {
        if (($row['ZSchuss' . $i] ?? null) !== null && $row['ZSchuss' . $i] !== '') { $zabigErfasst = true; break; }
    }

    // [Code(s), gelöst-Label, Anzeige oder null]
    $schwiniHit = array_values(array_intersect(['SCHWINI_P1', 'SCHWINI_P2'], $codes));
    $spalten = [
        [['END'],     'gelöst', $row['Endstich_Summe'] > 0 ? (string)$row['Endstich_Summe'] : null],
        [['SCHWINI_P1', 'SCHWINI_P2'], count($schwiniHit) === 1 ? 'gelöst ' . str_replace('SCHWINI_', '', $schwiniHit[0]) : 'gelöst',
                      ($s1 > 0 || $s2 > 0) ? max($s1, $s2) . ', ' . min($s1, $s2) : null],
        [['KUNST'],   'gelöst', $row['Kunst_Summe'] > 0 ? (string)$row['Kunst_Summe'] : null],
        [['GLUECK'],  'gelöst', ($row['max_glueck'] ?? 0) > 0
                                  ? $row['max_glueck'] . ' (' . $row['GSchuss1'] . ',' . $row['GSchuss2'] . ',' . $row['GSchuss3'] . ')' : null],
        [['ZABIG'],   'gelöst', ($zabigErfasst && $zabig > 0) ? (string)$zabig : null],
        [['SIEUNDER'],'gelöst', ($row['SieUndEr_Summe'] ?? 0) > 0
                                  ? $row['SieUndEr_Summe'] . (empty($row['su_partner']) ? ' · ohne Partner' : '') : null],
        // Ansage 0 heisst «keine» (Spalte ist NOT NULL): bei gelöstem Differenzler bleibt sie offen
        [['DIFF'],    'gelöst', (int)($row['Ansage'] ?? 0) > 0 ? (string)$row['Ansage'] : null],
    ];

    $hatDaten = false;
    $offen = false;
    $zellen = '';
    foreach ($spalten as [$c, $label, $wert]) {
        $geloest = $hat($c);
        if ($wert !== null) $hatDaten = true;
        if ($geloest && $wert === null) $offen = true;
        $zellen .= stichZelle($geloest, $wert, $label);
    }
    // Sie und Er zählt erst mit verknüpfter Partnerin bzw. verknüpftem Partner (Migration 094)
    $suOhnePartner = $hat(['SIEUNDER']) && empty($row['su_partner']);
    if ($suOhnePartner) $offen = true;
    $stand = $offen ? 'offen' : 'ok';
    $standZelle = $offen
        ? "<td><span class='ui-status offen'><span class='ui-punkt'></span>offen</span></td>"
        : "<td><span class='ui-status ok'><span class='ui-punkt'></span>vollständig</span></td>";

    $key = htmlspecialchars($row['Schluessel'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars(trim($row['Name'] . " " . $row['Vorname']), ENT_QUOTES, 'UTF-8');
    $typ = $row['TeilnehmerTyp'] ?? 'mitglied';
    $marke = $typ === 'js' ? " <span class='typ-badge js'>JS</span>" : ($typ === 'gast' ? " <span class='typ-badge'>Gast</span>" : '');
    // Mitglied zusätzlich in der Partner-Rangliste (Migration 096)
    if (!empty($row['Partnerrang'])) $marke .= " <span class='typ-badge' data-tooltip='Steht zusätzlich in der Partner-Rangliste'>Partner</span>";
    $klasse = 'hybrid-row' . ($offen ? ' ui-offen' : '');

    // Rangliste: Rang je Kategorie vor dem Namen, Total; ohne Wertung der Grund (Ansicht «Rangliste» zeigt ihn)
    if ($rang) {
        $rangAttr  = " data-kat='{$rang['kat']}' data-rang='{$rang['rang']}'";
        $rangMarke = "<span class='es-rang' data-tooltip='Rang in Kat. {$rang['kat']}'>{$rang['kat']} {$rang['rang']}.</span>";
        $grund     = '';
        $totalZelle = '<td class="es-total">' . number_format($rang['total'], 1, '.', '') . '</td>';
        if ($rang['rang'] <= 3) $klasse .= ' rank-' . $rang['rang'];
    } else {
        $g = htmlspecialchars(endschOhneWertungGrund($row), ENT_QUOTES, 'UTF-8');
        $rangAttr  = '';
        $rangMarke = "<span class='es-rang' data-tooltip='{$g}'></span>";
        $grund     = "<span class='es-grund'>{$g}</span>";
        $totalZelle = "<td><span class='cell-empty'>–</span></td>";
    }

    return "<tr class='{$klasse}' data-teilnehmer='{$key}' data-typ='{$typ}' data-has-data='" . ($hatDaten ? '1' : '0') . "'"
         . " data-stand='{$stand}' data-geloest='" . ((int)($row['geloest'] ?? 0)) . "'" . ($suOhnePartner ? " data-su='ohne-partner'" : '') . $rangAttr . ">"
         . "<td>{$rangMarke}<span class='tn-name'>{$name}</span>{$marke}{$grund}</td>{$zellen}{$totalZelle}{$standZelle}</tr>";
}

/**
 * Rang und Total je Teilnehmer-Schlüssel aus endschGesamtAbfrage() (Kat. A und B je durchnummeriert, wie die
 * Gesamtrangliste im PDF: Total, dann Endstich, dann Alter).
 */
function endschRangKarte(mysqli $conn, int $jahr): array {
    $karte = [];
    foreach (['A', 'B'] as $kat) {
        $q = endschGesamtAbfrage('Kat. ' . $kat, $jahr, true);
        $st = $conn->prepare($q['sql']);
        if (!$st) throw new Exception('Prepare Rangliste failed: ' . $conn->error);
        $st->bind_param($q['types'], ...$q['params']);
        $st->execute();
        $res = $st->get_result();
        $i = 0;
        while ($r = $res->fetch_assoc()) {
            $karte[$r['Schluessel']] = ['kat' => $kat, 'rang' => ++$i, 'total' => (float)$r['GesamtTotal']];
        }
        $st->close();
    }
    return $karte;
}

// Include database configuration
include '../config.php';
require_once __DIR__ . '/teilnehmer.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('html'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/../partials/empty_state.inc.php';

// Check database connection with proper error handling
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// Input validation and sanitization
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

if (!validateYear($year)) {
    error_log("Invalid year provided: " . ($_GET['year'] ?? 'null'));
    $year = date('Y'); // Fallback to current year
}

// Teilnehmer des Jahres: Mitglieder sowie Gäste und Jungschützen aus «Stiche lösen» (Migration 094), je eine Zeile
// mit Summen und gelösten Codes. Die Spalten sind für beide Teile gleich, nur Personentabelle und Schlüssel ändern.
$spaltenSql = function (string $p, array $k): string {
    return "
    g.GSchuss1, g.GSchuss2, g.GSchuss3,
    z.ZSchuss1, z.ZSchuss2, z.ZSchuss3, z.ZSchuss4, z.ZSchuss5, z.ZSchuss6,
    z.Ansage,
    GREATEST(COALESCE(g.GSchuss1, 0), COALESCE(g.GSchuss2, 0), COALESCE(g.GSchuss3, 0)) AS max_glueck,
    COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS Endstich_Summe,
    COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0) AS Schwini_Summe1,
    COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0) AS Schwini_Summe2,
    COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1), 0) AS Kunst_Summe,
    COALESCE(su.Schuss1, 0) + COALESCE(su.Schuss2, 0) + COALESCE(su.Schuss3, 0) + COALESCE(su.Schuss4, 0) + COALESCE(su.Schuss5, 0) AS SieUndEr_Summe,
    MAX(su.PartnerMitgliedID IS NOT NULL OR su.PartnerGastID IS NOT NULL) AS su_partner,
    MAX(sel.person IS NOT NULL) AS geloest,
    MAX(sel.codes) AS geloeste_codes
FROM $p
LEFT JOIN (
    SELECT es.{$k['sel']} AS person, GROUP_CONCAT(DISTINCT ed.code) AS codes
    FROM endstich_selection es
    INNER JOIN endstich_definition ed ON ed.id = es.stich_id
    WHERE es.jahr = ? AND es.{$k['sel']} IS NOT NULL
    GROUP BY es.{$k['sel']}
) sel ON sel.person = {$k['id']}
LEFT JOIN endstich e ON {$k['id']} = e.{$k['spalte']} AND e.Jahr = ?
LEFT JOIN schwini s ON {$k['id']} = s.{$k['spalte']} AND s.Jahr = ?
LEFT JOIN kunst k ON {$k['id']} = k.{$k['spalte']} AND k.Jahr = ?
LEFT JOIN glueck g ON {$k['id']} = g.{$k['spalte']} AND g.Jahr = ?
LEFT JOIN zabig z ON {$k['id']} = z.{$k['spalte']} AND z.Jahr = ?
LEFT JOIN endstich_sieunder su ON {$k['id']} = su.{$k['spalte']} AND su.Jahr = ?";
};
$having = "
HAVING
    geloest = 1
    OR Endstich_Summe > 0
    OR Schwini_Summe1 > 0
    OR Schwini_Summe2 > 0
    OR Kunst_Summe > 0
    OR max_glueck > 0
    OR SieUndEr_Summe > 0
    OR z.Ansage IS NOT NULL";

$sql = "(SELECT
    'mitglied' AS TeilnehmerTyp, CONCAT('m', m.ID) AS Schluessel, 1 AS Gruppe,
    EXISTS(SELECT 1 FROM endstich_partnerrang pr WHERE pr.MitgliedID = m.ID AND pr.Jahr = ?) AS Partnerrang,
    (SELECT w.Kategorie FROM Waffen w WHERE w.ID = m.WaffenID) AS Kategorie,
    CONVERT(m.Name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Name,
    CONVERT(m.Vorname USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Vorname,"
    . $spaltenSql('mitglieder m', ['sel' => 'mitglied_id', 'id' => 'm.ID', 'spalte' => 'MitgliedID']) . "
GROUP BY m.ID, m.Vorname, m.Name, m.WaffenID
$having)
UNION ALL
(SELECT
    IF(ga.geburtsdatum IS NULL, 'gast', 'js') AS TeilnehmerTyp, CONCAT('g', ga.id) AS Schluessel,
    IF(ga.geburtsdatum IS NULL, 2, 3) AS Gruppe,
    0 AS Partnerrang,
    NULL AS Kategorie,
    CONVERT(ga.name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Name,
    CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Vorname,"
    . $spaltenSql('endstich_gaeste ga', ['sel' => 'gast_id', 'id' => 'ga.id', 'spalte' => 'GastID']) . "
WHERE ga.jahr = ?
GROUP BY ga.id, ga.name, ga.geburtsdatum
$having)
ORDER BY Gruppe, Name, Vorname
";

try {
    // Use prepared statements for security
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // 1 + 7 Jahre für die Mitglieder (Partner-Rangliste, Stiche), 7 + 1 für Gäste und Jungschützen
    $stmt->bind_param(str_repeat('i', 16), ...array_fill(0, 16, $year));
    $stmt->execute();
    $result = $stmt->get_result();
    $zeilen = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$zeilen) {
        echo msv_empty_row(10, 'Noch niemand hat für dieses Jahr Stiche gelöst');
    } else {
        $rangKarte = endschRangKarte($conn, (int)$year);
        $html = '';
        foreach ($zeilen as $row) {
            $html .= generateResultRow($row, $rangKarte[$row['Schluessel']] ?? null);
        }
        echo $html;
    }

} catch (Exception $e) {
    // Log error for debugging while showing user-friendly message
    error_log("Database error in load_endschresultate.php: " . $e->getMessage());
    echo "<tr class='ui-leer'><td colspan='10'>Fehler beim Laden der Daten. Bitte die Seite neu laden.</td></tr>";
} finally {
    // Ensure connection is always closed
    $conn->close();
}
?>