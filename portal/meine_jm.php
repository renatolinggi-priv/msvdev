<?php
// portal/meine_jm.php - JM-Uebersicht mit Streicher-Logik
$portal_page_title = 'Jahresmeisterschaft';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
$db = getDB();

// Hochrechnung auf 100: nur wenn Maxpunkte < 100 (analog zu jmrang/load_jm.php)
function portalNormalize(?int $punkte, int $maxpunkte, string $bezeichnung = ''): ?float {
    if ($punkte === null) return null;
    // Bonus-Events werden NICHT hochgerechnet (analog zu scalePoints in load_jm.php)
    if (in_array($bezeichnung, ['Einzelwettschiessen', 'Obligatorisch', 'Feldschiessen'])) {
        return (float)$punkte;
    }
    if ($maxpunkte > 0 && $maxpunkte < 100) {
        return round($punkte * 100 / $maxpunkte, 2);
    }
    return (float)$punkte;
}

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
$selected_year = intval($_GET['year'] ?? date('Y'));

// CSRF-Token fuer Auto-Save sicherstellen
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Anlaesse, die NICHT vom Mitglied selbst eingebbar sind (kommen aus eigenen Tabellen
// oder erfordern Vorstand-Erfassung mit mehreren Zeilen pro Mitglied).
$NON_EDITABLE_BEZ = ['Endstich', 'Bester Kantonalstich', 'Sektionsmeisterschaft'];
$current_year = (int)date('Y');

// Bestimmt, ob ein Anlass vom Mitglied selbst eingebbar ist
function jmIsEditable(array $s, int $year, int $current_year, ?int $mitglied_id, array $blocked): bool {
    if (!$mitglied_id) return false;
    if ($year !== $current_year) return false;  // nur aktuelles Jahr
    if (in_array($s['Bezeichnung'] ?? '', $blocked, true)) return false;
    if (jmIsVereinscup($s)) return false;       // Cup-Resultat kommt aus inc/cup.php, nicht selbst eingebbar
    if (jmIsTeilnahme($s)) return false;        // Maxpunkte==20: Vorstand traegt ein, nur Ja/Nein-Anzeige
    if (trim((string)($s['Schiesstage'] ?? '')) === '') return false;
    return true;
}

// Teilnahme-Only-Anlass: Maxpunkte == 20 -> Teilnahme bedeutet immer die volle Punktzahl.
// Im Portal wird daher nur Ja/Nein angezeigt/erfasst, nie der konkrete Wert.
function jmIsTeilnahme(array $s): bool {
    return (int)($s['Maxpunkte'] ?? 0) === 20;
}

// Vereinscup: Das zaehlende Resultat wird ueber die Cup-Erfassung (inc/cup.php -> cupPairs)
// gefuehrt, nicht per Selbsteingabe. Es wird daher nur read-only angezeigt.
// Bewusst NICHT auf "Standcup ..."-Auswaertsschiessen matchen.
function jmIsVereinscup(array $s): bool {
    return (bool)preg_match('/Vereins[- ]?cup/i', (string)($s['Bezeichnung'] ?? ''));
}

// Rendert den Mitglied-Eingabebereich fuer einen selbst eingebbaren JM-Anlass (Zahlenfeld).
// Teilnahme-Anlaesse (Maxpunkte == 20) sowie Endstich/Kanti/Sektion sind NICHT editierbar und
// erreichen diese Funktion nicht (siehe jmIsEditable).
// Freigegebene Resultate sind gesperrt und werden ohne Bestaetigungs-Hinweis angezeigt.
function renderJmEingabe(array $s): string {
    $maxpunkte = (int)($s['Maxpunkte'] ?? 0);
    $jr_status = $s['jr_status'] ?? null;       // 'entwurf' | 'freigegeben' | null
    $locked    = ($jr_status === 'freigegeben');
    $has_value = ($s['Punkte'] !== null && (int)$s['Punkte'] > 0);
    $val_attr  = $has_value ? (int)$s['Punkte'] : '';
    $defid     = (int)$s['ID'];
    $row_idx   = (int)$s['_idx'];

    ob_start();
    ?>
    <div class="jm-eingabe" onclick="event.stopPropagation()">
        <label>Mein Resultat:</label>
        <?php if ($locked): ?>
            <span class="jm-punkte-display"><strong><?php echo $val_attr !== '' ? $val_attr : '&ndash;'; ?></strong></span>
            <?php if ($val_attr !== ''): ?><span class="jm-eingabe-max">/ <?php echo $maxpunkte; ?></span><?php endif; ?>
        <?php else: ?>
            <input type="number" inputmode="numeric"
                   min="0" max="<?php echo $maxpunkte; ?>" step="1"
                   class="jm-punkte-input"
                   value="<?php echo $val_attr; ?>"
                   data-defid="<?php echo $defid; ?>"
                   data-orig="<?php echo $val_attr; ?>"
                   data-max="<?php echo $maxpunkte; ?>"
                   data-row-idx="<?php echo $row_idx; ?>">
            <span class="jm-eingabe-max">/ <?php echo $maxpunkte; ?></span>
            <span class="jm-status-badge jm-status-<?php echo ($jr_status === 'entwurf' ? 'entwurf' : 'leer'); ?>">
                <?php if ($jr_status === 'entwurf'): ?>
                    <i class="bi bi-pencil"></i> Entwurf — wartet auf Freigabe
                <?php else: ?>
                    <i class="bi bi-dash-circle"></i> Noch nicht erfasst
                <?php endif; ?>
            </span>
            <span class="jm-save-spinner d-none"><i class="bi bi-arrow-repeat"></i></span>
            <span class="jm-save-ok d-none"><i class="bi bi-check-circle-fill"></i> gespeichert</span>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Read-only Anzeige des Vereinscup-Resultats. Der Wert stammt aus der Cup-Erfassung
// (inc/cup.php -> cupPairs, 1. Runde) und ist im Portal nicht editierbar.
function renderCupInfo(array $s): string {
    $maxpunkte = (int)($s['Maxpunkte'] ?? 0);
    $has_value = ($s['Punkte'] !== null && (int)$s['Punkte'] > 0);
    $val       = $has_value ? (int)$s['Punkte'] : null;

    ob_start();
    ?>
    <div class="jm-eingabe jm-cup-info" onclick="event.stopPropagation()">
        <label>Mein Cup-Resultat:</label>
        <?php if ($val !== null): ?>
            <span class="jm-punkte-display"><strong><?php echo $val; ?></strong></span>
            <span class="jm-eingabe-max">/ <?php echo $maxpunkte; ?></span>
        <?php else: ?>
            <span class="jm-punkte-display text-muted">&ndash;</span>
        <?php endif; ?>
        <span class="jm-status-badge jm-status-cup"><i class="bi bi-trophy"></i> Resultat aus dem Vereinscup</span>
    </div>
    <?php
    return ob_get_clean();
}

// Verfuegbare Jahre laden
$years_stmt = $db->query("SELECT DISTINCT year FROM JMDefinition WHERE year IS NOT NULL ORDER BY year DESC");
$available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($available_years)) $available_years = [date('Y')];

// Anzahl Streicher aus Parameter-Tabelle laden (Fallback: 3)
$ep_stmt = $db->prepare("SELECT excludeCount FROM Parameter WHERE year = ?");
$ep_stmt->execute([$selected_year]);
$ep_row = $ep_stmt->fetch();
$exclude_count = $ep_row ? max(1, (int)$ep_row['excludeCount']) : 3;

// Alle JM-Schiessen des Jahres (keine Info/Erweitert-Events, nicht hidden)
// Gleiche Filter wie in jmrang/load_jm.php: Erweitert=0 AND Info=0
$jm_stmt = $db->prepare("
    SELECT jd.ID, jd.Bezeichnung, jd.Maxpunkte, jd.Streicher, jd.Reihenfolge,
           jd.Schiesstage, jd.Adresse, jd.Zuschlag, jd.Info,
           jr.Punkte, jr.status AS jr_status
    FROM JMDefinition jd
    LEFT JOIN jmresultate jr ON jr.jmdefinitionID = jd.ID AND jr.mitgliederID = ?
        AND (jr.Info = '' OR jr.Info IS NULL)
    WHERE jd.year = ?
      AND jd.hidden = 0
      AND jd.Info = 0
      AND jd.Erweitert = 0
    ORDER BY jd.Reihenfolge ASC
");
$jm_stmt->execute([$mitglied_id, $selected_year]);
$schiessen_list = $jm_stmt->fetchAll();

// Eindeutigen Index zuweisen (Sektionsmeisterschaft hat mehrere Zeilen mit gleicher defID)
foreach ($schiessen_list as $idx => &$s) {
    $s['_idx'] = $idx;
}
unset($s);

// Sonderfälle: Endstich und Bester Kantonalstich kommen aus eigenen Tabellen
// (nicht aus jmresultate) – gleiche Logik wie in jmrang/load_jm.php
$endstich_def_id = null;
$kanti_def_id    = null;
$sektion_def_id  = null;
$cup_def_ids     = [];   // Vereinscup -> Resultat aus cupPairs (inc/cup.php)
foreach ($schiessen_list as $s) {
    if ($s['Bezeichnung'] === 'Endstich')             $endstich_def_id = (int)$s['ID'];
    if ($s['Bezeichnung'] === 'Bester Kantonalstich')  $kanti_def_id    = (int)$s['ID'];
    if ($s['Bezeichnung'] === 'Sektionsmeisterschaft') $sektion_def_id  = (int)$s['ID'];
    if (jmIsVereinscup($s))                            $cup_def_ids[]   = (int)$s['ID'];
}
$cup_def_ids = array_values(array_unique($cup_def_ids));

if ($endstich_def_id && $mitglied_id) {
    $es = $db->prepare("
        SELECT (COALESCE(Schuss1,0)+COALESCE(Schuss2,0)+COALESCE(Schuss3,0)+
                COALESCE(Schuss4,0)+COALESCE(Schuss5,0)+COALESCE(Schuss6,0)+
                COALESCE(Schuss7,0)+COALESCE(Schuss8,0)+COALESCE(Schuss9,0)+
                COALESCE(Schuss10,0)) AS Punkte
        FROM endstich WHERE MitgliedID = ? AND Jahr = ?
    ");
    $es->execute([$mitglied_id, $selected_year]);
    $esrow = $es->fetch();
    $endstich_punkte = $esrow ? (int)$esrow['Punkte'] : null;
    foreach ($schiessen_list as &$s) {
        if ((int)$s['ID'] === $endstich_def_id) {
            $s['Punkte'] = $endstich_punkte;
        }
    }
    unset($s);
}

if ($kanti_def_id && $mitglied_id) {
    $ks = $db->prepare("
        SELECT GREATEST(
            COALESCE(Passe1,0),COALESCE(Passe2,0),COALESCE(Passe3,0),
            COALESCE(Passe4,0),COALESCE(Passe5,0)
        ) AS Punkte
        FROM kantiresultate WHERE MitgliedID = ? AND Jahr = ?
    ");
    $ks->execute([$mitglied_id, $selected_year]);
    $ksrow = $ks->fetch();
    $kanti_punkte = $ksrow ? (int)$ksrow['Punkte'] : null;
    foreach ($schiessen_list as &$s) {
        if ((int)$s['ID'] === $kanti_def_id) {
            $s['Punkte'] = $kanti_punkte;
        }
    }
    unset($s);
}

// Sektionsmeisterschaft: zaehlendes Resultat = hoechste Runde (Info='runde 1'/'runde 2'),
// analog zur Rangliste (load_jm.php: nur hoechster Wert zaehlt). Kommt aus jmresultate, das
// per LEFT JOIN (Info='') nicht greift -> separat laden, damit der Wert angezeigt wird.
if ($sektion_def_id && $mitglied_id) {
    $sm = $db->prepare("
        SELECT MAX(Punkte) AS Punkte
        FROM jmresultate
        WHERE mitgliederID = ? AND jmdefinitionID = ? AND Info IN ('runde 1','runde 2')
    ");
    $sm->execute([$mitglied_id, $sektion_def_id]);
    $smrow = $sm->fetch();
    $sektion_punkte = ($smrow && $smrow['Punkte'] !== null) ? (int)$smrow['Punkte'] : null;
    foreach ($schiessen_list as &$s) {
        if ((int)$s['ID'] === $sektion_def_id) {
            $s['Punkte'] = $sektion_punkte;
        }
    }
    unset($s);
}

// Vereinscup: zaehlendes Resultat = Punktzahl aus der 1. Cup-Runde (cupPairs.Round=1),
// analog zu Endstich/Kanti. Das Resultat stammt aus der Cup-Erfassung (inc/cup.php) und
// nicht aus einer Selbsteingabe. Ist das Mitglied in Runde 1 nicht (mit Resultat) erfasst,
// bleibt der bestehende jmresultate-Wert als Fallback erhalten.
// Hinweis (PDO ATTR_EMULATE_PREPARES=false): positionsbasierte Platzhalter, da mitglied_id
// mehrfach gebunden wird.
if ($cup_def_ids && $mitglied_id) {
    $cp = $db->prepare("
        SELECT CASE
                   WHEN Participant1 = ? THEN Result1
                   WHEN Participant2 = ? THEN Result2
                   WHEN Participant3 = ? THEN Result3
               END AS Punkte
        FROM cupPairs
        WHERE `Year` = ? AND `Round` = 1
          AND (Participant1 = ? OR Participant2 = ? OR Participant3 = ?)
        LIMIT 1
    ");
    $cp->execute([
        $mitglied_id, $mitglied_id, $mitglied_id,
        $selected_year,
        $mitglied_id, $mitglied_id, $mitglied_id,
    ]);
    $cprow = $cp->fetch();
    if ($cprow && $cprow['Punkte'] !== null) {
        $cup_punkte = (int)$cprow['Punkte'];
        foreach ($schiessen_list as &$s) {
            if (in_array((int)$s['ID'], $cup_def_ids, true)) {
                $s['Punkte'] = $cup_punkte;
            }
        }
        unset($s);
    }
}

// Normalisierte Punkte berechnen (Hochrechnung auf 100 wenn Maxpunkte < 100)
// Punkte=0 wird als "nicht teilgenommen" behandelt (DB speichert 0 statt NULL)
foreach ($schiessen_list as &$s) {
    $raw = $s['Punkte'];
    $punkte = ($raw !== null && (int)$raw > 0) ? (int)$raw : null;
    $s['PunkteNorm'] = portalNormalize($punkte, (int)($s['Maxpunkte'] ?? 100), $s['Bezeichnung'] ?? '');
}
unset($s);

// Aktive Streicher-Schiessen ermitteln: nur jene mit Streicher=1 die bereits
// Resultate von irgendjemandem haben (wie in jmrang/load_jm.php)
$active_streicher_ids = [];
$all_streicher_def_ids = array_map('intval', array_column(
    array_filter($schiessen_list, fn($s) => (int)$s['Streicher'] === 1),
    'ID'
));
if (!empty($all_streicher_def_ids)) {
    // Normale Schiessen: in jmresultate prüfen
    $placeholders = implode(',', array_fill(0, count($all_streicher_def_ids), '?'));
    $chk = $db->prepare("SELECT DISTINCT jmdefinitionID FROM jmresultate WHERE jmdefinitionID IN ($placeholders)");
    $chk->execute($all_streicher_def_ids);
    $active_streicher_ids = array_map('intval', $chk->fetchAll(PDO::FETCH_COLUMN));

    // Sonderfälle: Endstich/Kanti kommen nicht in jmresultate vor,
    // daher separat prüfen ob es Resultate in den Originaltabellen gibt
    if ($endstich_def_id && in_array($endstich_def_id, $all_streicher_def_ids)) {
        $chk2 = $db->prepare("SELECT 1 FROM endstich WHERE Jahr = ? LIMIT 1");
        $chk2->execute([$selected_year]);
        if ($chk2->fetch()) $active_streicher_ids[] = $endstich_def_id;
    }
    if ($kanti_def_id && in_array($kanti_def_id, $all_streicher_def_ids)) {
        $chk3 = $db->prepare("SELECT 1 FROM kantiresultate WHERE Jahr = ? LIMIT 1");
        $chk3->execute([$selected_year]);
        if ($chk3->fetch()) $active_streicher_ids[] = $kanti_def_id;
    }
    // Vereinscup: kommt aus cupPairs (nicht jmresultate) -> separat als aktiv markieren,
    // sobald die 1. Cup-Runde des Jahres erfasst ist.
    foreach ($cup_def_ids as $cdid) {
        if (!in_array($cdid, $all_streicher_def_ids, true)) continue;
        $chk4 = $db->prepare("SELECT 1 FROM cupPairs WHERE `Year` = ? AND `Round` = 1 LIMIT 1");
        $chk4->execute([$selected_year]);
        if ($chk4->fetch()) $active_streicher_ids[] = $cdid;
    }
}

// Sektionsmeisterschaft: bei mehreren Zeilen mit gleicher defID (LEFT JOIN erzeugt
// Duplikate) nur die beste zaehlen, schlechtere als Streicher markieren (via _idx)
$sektions_streicher_idxs = [];
$entries_by_defid = [];
foreach ($schiessen_list as $s) {
    $entries_by_defid[(int)$s['ID']][] = $s;
}
foreach ($entries_by_defid as $defid => $entries) {
    if (count($entries) <= 1) continue;
    // Absteigend sortieren (bester zuerst)
    usort($entries, fn($a, $b) => ($b['PunkteNorm'] ?? 0) <=> ($a['PunkteNorm'] ?? 0));
    for ($i = 1; $i < count($entries); $i++) {
        $sektions_streicher_idxs[] = $entries[$i]['_idx'];
    }
}

// Regulaere Streicher-Logik (via _idx, Sektions-Duplikate ausschliessen)
$streicher_idxs = [];
if ($exclude_count > 0) {
    $streicher_candidates = [];
    foreach ($schiessen_list as $s) {
        if (in_array($s['_idx'], $sektions_streicher_idxs)) continue;
        if ((int)$s['Streicher'] === 1 && in_array((int)$s['ID'], $active_streicher_ids)) {
            $punkte = ($s['PunkteNorm'] !== null) ? $s['PunkteNorm'] : 0.0;
            $streicher_candidates[] = ['idx' => $s['_idx'], 'punkte' => $punkte];
        }
    }
    usort($streicher_candidates, fn($a, $b) => $a['punkte'] <=> $b['punkte']);
    for ($i = 0; $i < min($exclude_count, count($streicher_candidates)); $i++) {
        $streicher_idxs[] = $streicher_candidates[$i]['idx'];
    }
}

// Alle Streicher zusammenfassen (regulaere + Sektionsmeisterschaft-Duplikate)
$all_streicher_idxs = array_merge($streicher_idxs, $sektions_streicher_idxs);

// Schiessdatum in Datum- und Zeitanteil aufteilen
// "Samstag 18. April 2026 08:00 – 12:00 Uhr" → ['date'=>'Samstag 18. April 2026', 'time'=>'08:00 – 12:00 Uhr']
function splitSchiessDatum(string $line): array {
    $line = trim($line);
    // Zeit = ab dem ersten HH:MM oder HH.MM (Datum enthaelt nie eine Uhrzeit) — auch ohne Jahr.
    // Ein "DD." des Datums passt nicht, da danach ein Leerzeichen + Monatsname folgt (keine 2 Ziffern).
    if (preg_match('/^(.*?)\s*(\d{1,2}[:.]\d{2}.*)$/u', $line, $m)) {
        return ['date' => trim($m[1]), 'time' => trim($m[2])];
    }
    return ['date' => $line, 'time' => ''];
}

// Parst eine deutsche Datumszeile ("... 18. April 2026") zu 'Y-m-d' (oder null, wenn unklar).
function jmParseDatum(string $dateStr, array $months_de, int $fallbackYear): ?string {
    foreach ($months_de as $name => $num) {
        if (preg_match('/(\d{1,2})\.\s*' . preg_quote($name, '/') . '(?:\s+(\d{4}))?/u', $dateStr, $m)) {
            $year = (isset($m[2]) && $m[2] !== '') ? (int)$m[2] : $fallbackYear;
            return sprintf('%04d-%02d-%02d', $year, $num, (int)$m[1]);
        }
    }
    return null;
}

// Monats-Mapping fuer Datum-Erkennung
$months_de = ['Januar'=>1,'Februar'=>2,'März'=>3,'April'=>4,'Mai'=>5,'Juni'=>6,
              'Juli'=>7,'August'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Dezember'=>12];

// Zusammenfassung berechnen
$total_punkte = 0;
$geschossen_count = 0;
$streicher_used = count($streicher_idxs); // nur regulaere Streicher fuer Anzeige

foreach ($schiessen_list as $s) {
    $is_streicher = in_array($s['_idx'], $all_streicher_idxs);
    if ($s['PunkteNorm'] !== null) {
        $geschossen_count++;
    }
    if (!$is_streicher && $s['PunkteNorm'] !== null) {
        $total_punkte += $s['PunkteNorm'];
    }
}
$total_events = count($schiessen_list);

include 'portal_header.php';
?>

<style>
.year-select { max-width: 140px; }
/* Stat-Inhalt + Total-Akzent kommen aus css/portal.css (.p-stat) */

/* Schiessen-Liste: ein Panel mit Trennzeilen (Desktop + Mobile gleich) */
.jm-list {
    background: #fff;
    border: 1px solid var(--p-border);
    border-radius: var(--p-radius);
    box-shadow: var(--p-shadow);
    overflow: hidden;
}
.jm-row { border-top: 1px solid var(--p-border); padding: .55rem var(--p-3); }
.jm-row:first-child { border-top: none; }
.jm-row-main { display: flex; align-items: center; gap: var(--p-3); }
.jm-row-info { flex: 1; min-width: 0; }
.jm-row-title {
    display: flex; align-items: center; gap: .4rem; flex-wrap: wrap;
    font-weight: 600; font-size: .9rem; color: var(--p-text);
}
.jm-row.future .jm-row-title { color: var(--p-text-muted); }
.jm-row-meta { font-size: .75rem; color: var(--p-text-muted); margin-top: 1px; }
.jm-row-result { display: flex; align-items: center; gap: .5rem; flex-shrink: 0; }
.jm-row-points { font-size: 1.1rem; font-weight: 700; color: var(--p-text); font-variant-numeric: tabular-nums; }
.jm-row-points.ok { color: var(--success-color); }
.jm-row-points.streicher { color: #b8860b; text-decoration: line-through; font-size: .95rem; }
.jm-row-points.muted { color: var(--p-text-muted); }
.jm-row-points.no { color: var(--danger-color); }
.jm-row-status { font-size: 1rem; line-height: 1; }
.jm-row-status.ok { color: var(--success-color); }
.jm-row-status.no { color: var(--danger-color); }
.jm-row-status.future { color: var(--p-text-muted); }
.jm-row-detail { margin-top: .5rem; padding-top: .5rem; border-top: 1px dashed var(--p-border); }

/* Einheitliche Pills (Streicher + Status) */
.badge-streicher {
    background: var(--warning-color);
    color: #343a40;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
}

/* Expand/Collapse */
.jm-expand-btn {
    background: none;
    border: none;
    padding: 0 0.25rem;
    color: var(--p-text-muted);
    cursor: pointer;
    font-size: 0.75rem;
    vertical-align: middle;
    transition: color 0.15s;
}
.jm-expand-btn:hover { color: var(--p-text); }
.jm-expand-btn i { transition: transform 0.2s; }
.jm-expand-btn.open i { transform: rotate(180deg); }

.jm-dates-detail {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem 1.5rem;
    font-size: 0.8rem;
    padding: 0.35rem 0;
}
.jm-dates-detail .date-line,
.jm-dates-detail .addr-line {
    display: flex;
    align-items: flex-start;
    gap: 0.3rem;
    white-space: nowrap;
}
.jm-dates-detail .addr-line { color: var(--p-text-muted); white-space: normal; }

/* Mitglied-Eingabe */
.jm-eingabe {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: #fff;
    border: 1px solid var(--p-border);
    border-radius: var(--p-radius-sm);
    font-size: 0.85rem;
}
.jm-eingabe label {
    margin: 0;
    color: var(--p-text);
    font-weight: 600;
    white-space: nowrap;
}
.jm-punkte-input {
    width: 80px;
    padding: 0.25rem 0.5rem;
    border: 1px solid #cbd5e0;
    border-radius: var(--p-radius-sm);
    font-size: 0.95rem;
    font-weight: 600;
    text-align: center;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.jm-punkte-input:focus {
    outline: none;
    border-color: var(--success-color);
    box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.15);
}
.jm-punkte-input:disabled {
    background: #f8f9fa;
    color: var(--p-text-muted);
    cursor: not-allowed;
}
.jm-eingabe-max {
    color: var(--p-text-muted);
    font-size: 0.8rem;
    margin-left: -0.25rem;
}
/* Gesperrtes (freigegebenes) Resultat: reine Anzeige ohne Bestaetigungs-Hinweis */
.jm-punkte-display {
    font-size: 1rem;
    color: var(--p-text);
    padding: 0.25rem 0.25rem;
}
.jm-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
}
.jm-status-leer       { background: #e9ecef; color: var(--p-text-muted); }
.jm-status-entwurf    { background: #fff3cd; color: #856404; }
.jm-status-freigegeben { background: #d4edda; color: #155724; }
.jm-status-cup        { background: #cfe2ff; color: #084298; }
.jm-save-spinner i { animation: jm-spin 0.8s linear infinite; }
.jm-save-ok { color: var(--success-color); font-size: 0.78rem; font-weight: 600; }
@keyframes jm-spin { to { transform: rotate(360deg); } }

@media (max-width: 575.98px) {
    .jm-eingabe { font-size: 0.8rem; }
    .jm-punkte-input { width: 70px; }
    .jm-status-badge { width: 100%; }
}
</style>

<!-- Page Header -->
<div class="portal-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-bullseye me-2"></i>Jahresmeisterschaft</h1>
        <p class="subtitle mb-0"><?php echo $selected_year; ?> &mdash; Alle Schiessen mit Streicher-Berechnung</p>
    </div>
    <form method="get" class="d-flex align-items-center gap-2 ms-auto">
        <select name="year" class="form-select form-select-sm year-select" onchange="this.form.submit()">
            <?php foreach ($available_years as $y): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (!$mitglied_id): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Dein Account ist noch nicht mit einem Mitglied verknüpft. Bitte kontaktiere den Administrator.</div>
<?php else: ?>

<!-- Zusammenfassung -->
<div class="result-summary">
    <div class="rs-item total">
        <span class="rs-num"><?php echo $total_punkte; ?></span>
        <span class="rs-lbl">Total</span>
    </div>
    <div class="rs-item">
        <span class="rs-num"><?php echo $geschossen_count; ?> / <?php echo $total_events; ?></span>
        <span class="rs-lbl">geschossen</span>
    </div>
    <div class="rs-item">
        <span class="rs-num"><?php echo $streicher_used; ?> / <?php echo $exclude_count; ?></span>
        <span class="rs-lbl">Streicher</span>
    </div>
</div>

<!-- Schiessen-Liste (Desktop + Mobile gleich) -->
<div class="jm-list">
    <?php
    foreach ($schiessen_list as $s):
        $is_streicher = in_array($s['_idx'], $all_streicher_idxs);
        $geschossen   = ($s['PunkteNorm'] !== null);
        $punkte_norm  = $s['PunkteNorm'];
        $is_teilnahme = jmIsTeilnahme($s);  // Maxpunkte==20: nur Ja/Nein statt Wert

        // Schiesstage zerlegen. Pro Zeile: Datum + (Zeit nur, wenn nicht mehr als 1 Woche her).
        // Spaetestes Datum bestimmt, ob der Anlass wirklich vorbei ist.
        $all_lines = [];
        if (!empty($s['Schiesstage'])) {
            $all_lines = array_filter(array_map('trim', explode("\n", trim($s['Schiesstage']))));
        }
        $today_str       = date('Y-m-d');
        $event_last_date = null;
        $line_parts      = [];   // ['date' => ..., 'time' => ...]
        foreach ($all_lines as $line) {
            $dt = splitSchiessDatum($line);
            $d  = jmParseDatum($dt['date'], $months_de, $selected_year);
            if ($d !== null && ($event_last_date === null || $d > $event_last_date)) {
                $event_last_date = $d;
            }
            $line_parts[] = $dt;
        }

        // "vorbei" = letzter Tag liegt vor heute (unbekanntes Datum -> nicht als vorbei werten).
        // Vergangene Anlaesse: komplette Terminangabe (Datum + Uhrzeit) ausblenden.
        $is_past    = ($event_last_date !== null && $event_last_date < $today_str);
        $is_future  = !$is_past;
        $show_dates = !$is_past;

        $datum_kurz = '';
        if ($show_dates) {
            $datum_kurz = implode(', ', array_map(function($p) {
                return $p['date'] . ($p['time'] !== '' ? ' ' . $p['time'] : '');
            }, $line_parts));
        }

        $is_editable = jmIsEditable($s, $selected_year, $current_year, $mitglied_id, $NON_EDITABLE_BEZ);
        $is_cup        = jmIsVereinscup($s);   // Resultat aus cup.php, read-only
        // Resultat ist erfassbar, solange der Vorstand es noch nicht freigegeben hat.
        $result_locked = (($s['jr_status'] ?? null) === 'freigegeben');
        $can_enter     = $is_editable && !$result_locked;
        // Aufklappbar: nicht-vergangene wegen Terminen/Adresse; vergangene nur, solange
        // das Resultat noch erfassbar ist (Vorstand hat noch nicht freigegeben). Der
        // Vereinscup ist immer aufklappbar (zeigt den read-only Cup-Hinweis).
        $has_details = ($show_dates && (count($all_lines) > 1 || !empty($s['Adresse']))) || $can_enter || $is_cup;
        $detail_id   = 'jmr-' . $s['_idx'];
    ?>
    <div class="jm-row<?php echo ($is_future && !$geschossen) ? ' future' : ''; ?>"<?php if ($has_details): ?> onclick="toggleJmDetail('<?php echo $detail_id; ?>', this)" style="cursor:pointer"<?php endif; ?>>
        <div class="jm-row-main">
            <div class="jm-row-info">
                <div class="jm-row-title">
                    <span><?php echo htmlspecialchars($s['Bezeichnung']); ?></span>
                </div>
                <?php if ($datum_kurz !== ''): ?>
                <div class="jm-row-meta card-meta"><?php echo htmlspecialchars($datum_kurz); ?></div>
                <?php endif; ?>
            </div>
            <div class="jm-row-result">
                <?php
                // Reihenfolge: erst "geschossen", dann "kommend", sonst "vergangen & verpasst".
                // Teilnahme-Anlaesse (Maxpunkte==20): Haken statt Zahl.
                if ($geschossen) {
                    if ($is_teilnahme) {
                        echo '<span class="jm-row-points ' . ($is_streicher ? 'streicher' : 'ok') . '" title="Teilgenommen"><i class="bi bi-check-circle-fill"></i></span>';
                    } else {
                        $disp = ($punkte_norm == (int)$punkte_norm) ? (string)(int)$punkte_norm : number_format($punkte_norm, 2, '.', '');
                        echo '<span class="jm-row-points ' . ($is_streicher ? 'streicher' : '') . '" title="Bereinigte Punkte">' . $disp . '</span>';
                        echo '<i class="bi bi-check-circle-fill jm-row-status ok" title="Geschossen"></i>';
                    }
                } elseif ($is_future) {
                    // noch nicht stattgefunden (oder Datum unbekannt) -> KEIN rotes X
                    echo '<span class="jm-row-points muted">&ndash;</span>';
                    echo '<i class="bi bi-clock jm-row-status future" title="Noch nicht stattgefunden"></i>';
                } else {
                    // vergangen und nicht absolviert
                    echo '<span class="jm-row-points no">&ndash;</span>';
                    echo '<i class="bi bi-x-circle-fill jm-row-status no" title="Nicht teilgenommen"></i>';
                }
                ?>
                <?php if ($has_details): ?><span class="jm-expand-btn"><i class="bi bi-chevron-down"></i></span><?php endif; ?>
            </div>
        </div>
        <?php if ($has_details): ?>
        <div class="jm-row-detail" id="<?php echo $detail_id; ?>" style="display:none;">
            <?php if ($show_dates): ?>
            <div class="jm-dates-detail">
                <?php foreach ($line_parts as $p): ?>
                    <div class="date-line">
                        <i class="bi bi-calendar3 text-muted mt-1"></i>
                        <span>
                            <?php echo htmlspecialchars($p['date']); ?>
                            <?php if ($p['time'] !== ''): ?>
                                <br><span class="text-muted"><?php echo htmlspecialchars($p['time']); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php if (!empty($s['Adresse'])): ?>
                    <div class="addr-line">
                        <i class="bi bi-geo-alt text-muted"></i>
                        <a href="https://maps.google.com/?q=<?php echo urlencode($s['Adresse']); ?>" target="_blank" rel="noopener" class="text-muted" onclick="event.stopPropagation()">
                            <?php echo nl2br(htmlspecialchars($s['Adresse'])); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($is_editable) echo renderJmEingabe($s); ?>
            <?php if ($is_cup) echo renderCupInfo($s); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<script>
function toggleJmDetail(id, triggerEl) {
    const el = document.getElementById(id);
    if (!el) return;
    const isOpen = el.style.display !== 'none' && el.style.display !== '';
    el.style.display = isOpen ? 'none' : (el.tagName === 'TR' ? 'table-row' : 'block');
    // Chevron rotieren
    const chevron = triggerEl.classList.contains('jm-expand-btn')
        ? triggerEl
        : triggerEl.querySelector('.jm-expand-btn, .card-expand-btn');
    if (chevron) chevron.classList.toggle('open', !isOpen);
    // Datum-Vorschau ausblenden wenn aufgeklappt
    const meta = triggerEl.querySelector?.('.card-meta');
    if (meta) meta.style.display = isOpen ? '' : 'none';
}

(function() {
    'use strict';
    const CSRF = <?php echo json_encode($csrf_token); ?>;
    const YEAR = <?php echo (int)$selected_year; ?>;
    const SAVE_URL = '../api/portal_jm_save.php';
    const inputs = document.querySelectorAll('.jm-punkte-input');

    function setBadge(badge, status) {
        // Hinweis: Bestaetigte (freigegebene) Resultate sind nicht editierbar und werden ohne
        // Status-Badge gerendert -> dieser Handler erhaelt nur 'entwurf' oder null.
        badge.classList.remove('jm-status-leer', 'jm-status-entwurf', 'jm-status-freigegeben');
        if (status === 'entwurf') {
            badge.classList.add('jm-status-entwurf');
            badge.innerHTML = '<i class="bi bi-pencil"></i> Entwurf — wartet auf Freigabe';
        } else {
            badge.classList.add('jm-status-leer');
            badge.innerHTML = '<i class="bi bi-dash-circle"></i> Noch nicht erfasst';
        }
    }

    // Aktualisiert die Hauptzeile/Karte (Punkte-Anzeige) ohne Page-Reload.
    // Volle Streicher-Neuberechnung erfordert Reload — wir machen einen sanften
    // Reload via location.reload() nach kurzer Bestaetigung.
    function updateAfterSave(input, data) {
        const badge = input.parentElement.querySelector('.jm-status-badge');
        if (badge) setBadge(badge, data.status);
        input.dataset.orig = (data.punkte !== null && data.punkte !== undefined) ? String(data.punkte) : '';
        if (data.punkte === null) {
            input.value = '';
        }
    }

    function showSpinner(input, on) {
        const spinner = input.parentElement.querySelector('.jm-save-spinner');
        if (spinner) spinner.classList.toggle('d-none', !on);
        input.disabled = on;
    }

    function showOk(input) {
        const ok = input.parentElement.querySelector('.jm-save-ok');
        if (!ok) return;
        ok.classList.remove('d-none');
        setTimeout(() => ok.classList.add('d-none'), 2500);
    }

    async function saveInput(input) {
        const newVal = input.value.trim();
        const oldVal = input.dataset.orig || '';
        if (newVal === oldVal) return;  // nichts geaendert

        // Client-side Validierung
        if (newVal !== '') {
            if (!/^\d+$/.test(newVal)) {
                if (window.msvToast) msvToast('Punktzahl muss eine Ganzzahl sein.', 'error');
                input.value = oldVal;
                return;
            }
            const max = parseInt(input.dataset.max || '0', 10);
            const num = parseInt(newVal, 10);
            if (max > 0 && num > max) {
                if (window.msvToast) msvToast(`Maximal ${max} Punkte erlaubt.`, 'error');
                input.value = oldVal;
                return;
            }
            if (num < 0) {
                if (window.msvToast) msvToast('Punktzahl darf nicht negativ sein.', 'error');
                input.value = oldVal;
                return;
            }
        }

        showSpinner(input, true);

        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('year', String(YEAR));
        fd.append('jmdefinition_id', input.dataset.defid);
        fd.append('punkte', newVal);

        try {
            const res = await fetch(SAVE_URL, { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Fehler beim Speichern');
            }
            updateAfterSave(input, data);
            showOk(input);
        } catch (err) {
            if (window.msvToast) msvToast(err.message || 'Speichern fehlgeschlagen', 'error');
            input.value = oldVal;
        } finally {
            showSpinner(input, false);
        }
    }

    inputs.forEach(input => {
        if (input.disabled) return;
        input.addEventListener('blur', () => saveInput(input));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            } else if (e.key === 'Escape') {
                input.value = input.dataset.orig || '';
                input.blur();
            }
        });
    });
})();
</script>

<?php include 'portal_footer.php'; ?>
