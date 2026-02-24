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
           jr.Punkte
    FROM JMDefinition jd
    LEFT JOIN jmresultate jr ON jr.jmdefinitionID = jd.ID AND jr.mitgliederID = ?
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
foreach ($schiessen_list as $s) {
    if ($s['Bezeichnung'] === 'Endstich')            $endstich_def_id = (int)$s['ID'];
    if ($s['Bezeichnung'] === 'Bester Kantonalstich') $kanti_def_id    = (int)$s['ID'];
}

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
.jm-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.jm-stat {
    background: white;
    border-radius: 0.75rem;
    padding: 0.6rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.jm-stat .number {
    font-size: 1.15rem;
    font-weight: 700;
    color: #2d3748;
}
.jm-stat .label {
    font-size: 0.7rem;
    color: #718096;
}
.jm-stat .label i { color: #28a745; margin-right: 0.2rem; }
.jm-stat.total { border-top: 3px solid #28a745; background: #f0faf3; }
.jm-stat.total .number { color: #1a8c35; }

/* Desktop Tabelle */
.jm-table th { font-size: 0.8rem; font-weight: 600; }
.jm-table td { vertical-align: middle; font-size: 0.85rem; }
.row-streicher { background: #fff9e6 !important; }
.row-streicher td { text-decoration: line-through; color: #6c757d; }
.row-streicher .badge-streicher { text-decoration: none; }
.row-not-shot { color: #dc3545; }
.row-future { color: #adb5bd; font-style: italic; }

/* Mobile Karten */
.jm-card {
    background: white;
    border-radius: 0.75rem;
    padding: 0.6rem;
    margin-bottom: 0.5rem;
    box-shadow: 0 1px 6px rgba(0,0,0,0.06);
    border-left: 4px solid #dee2e6;
    transition: all 0.2s;
}
.jm-card.shot { border-left-color: #28a745; }
.jm-card.not-shot { border-left-color: #dc3545; }
.jm-card.streicher { border-left-color: #ffc107; background: #fff9e6; }
.jm-card.future { border-left-color: #adb5bd; opacity: 0.7; }
.jm-card .card-title { font-weight: 600; font-size: 0.85rem; color: #2d3748; }
.jm-card .card-result {
    font-size: 1.1rem;
    font-weight: 700;
}
.jm-card .card-result.shot { color: #28a745; }
.jm-card .card-result.not-shot { color: #dc3545; }
.jm-card .card-result.streicher { color: #e0a800; text-decoration: line-through; }
.jm-card .card-meta { font-size: 0.8rem; color: #718096; }
.badge-streicher {
    background: #ffc107;
    color: #343a40;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
}

/* Desktop-Tabelle nur ab md */
.jm-desktop { display: none; }
@media (min-width: 768px) {
    .jm-desktop { display: block; }
    .jm-mobile { display: none; }
}
@media (max-width: 767.98px) {
    .jm-summary { grid-template-columns: repeat(2, 1fr); }
    .jm-stat .number { font-size: 1.1rem; }
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
<div class="jm-summary">
    <div class="jm-stat total">
        <div class="number"><?php echo $total_punkte; ?></div>
        <div class="label"><i class="bi bi-bullseye"></i>Total (ohne Streicher)</div>
    </div>
    <div class="jm-stat">
        <div class="number"><?php echo $geschossen_count; ?> / <?php echo $total_events; ?></div>
        <div class="label"><i class="bi bi-check2-all"></i>Geschossen</div>
    </div>
    <div class="jm-stat">
        <div class="number"><?php echo $streicher_used; ?> / <?php echo $exclude_count; ?></div>
        <div class="label"><i class="bi bi-dash-circle"></i>Streicher</div>
    </div>
</div>

<!-- Desktop: Tabelle -->
<div class="jm-desktop">
    <div class="portal-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 jm-table">
                <thead>
                    <tr class="table-light">
                        <th style="width:40px;">#</th>
                        <th>Schiessen</th>
                        <th>Datum</th>
                        <th class="text-center">Resultat</th>
                        <th class="text-center">Bereinigt</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $nr = 0;
                foreach ($schiessen_list as $s):
                    $nr++;
                    $is_streicher = in_array($s['_idx'], $all_streicher_idxs);
                    $geschossen = ($s['PunkteNorm'] !== null);
                    $punkte = $geschossen ? intval($s['Punkte']) : null;
                    $punkte_norm = $s['PunkteNorm'];

                    // Zukunft erkennen
                    $is_future = false;
                    $today = date('Y-m-d');
                    foreach ($months_de as $name => $num) {
                        if (stripos($s['Schiesstage'] ?? '', $name) !== false) {
                            $approx = $selected_year . '-' . str_pad($num, 2, '0', STR_PAD_LEFT) . '-01';
                            if ($approx > $today) $is_future = true;
                            break;
                        }
                    }

                    $row_class = '';
                    if ($is_streicher) $row_class = 'row-streicher';
                    elseif (!$geschossen && !$is_future) $row_class = 'row-not-shot';
                    elseif ($is_future) $row_class = 'row-future';

                    // Schiesstage kurz (erste Zeile)
                    $datum_kurz = '';
                    if (!empty($s['Schiesstage'])) {
                        $lines = explode("\n", trim($s['Schiesstage']));
                        $datum_kurz = trim($lines[0]);
                    }
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td class="text-muted"><?php echo $nr; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($s['Bezeichnung']); ?></strong>
                        <?php if ($is_streicher): ?>
                            <span class="badge-streicher ms-2">Streicher</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?php echo htmlspecialchars($datum_kurz); ?></small></td>
                    <td class="text-center">
                        <?php if ($geschossen): ?>
                            <strong><?php echo $punkte; ?></strong>
                        <?php elseif ($is_future): ?>
                            <span class="text-muted">-</span>
                        <?php else: ?>
                            <span class="text-danger">nicht teilgenommen</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($punkte_norm !== null): ?>
                            <?php if ($is_streicher): ?>
                                <span class="text-danger" style="text-decoration:line-through;" title="Gestrichen">
                                    <?php echo number_format($punkte_norm, 2, '.', ''); ?>
                                </span>
                            <?php else: ?>
                                <strong><?php echo number_format($punkte_norm, 2, '.', ''); ?></strong>
                            <?php endif; ?>
                        <?php elseif ($is_future): ?>
                            <span class="text-muted">-</span>
                        <?php else: ?>
                            <span class="text-danger">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($is_future): ?>
                            <i class="bi bi-clock text-muted" title="Noch nicht stattgefunden"></i>
                        <?php elseif ($geschossen): ?>
                            <i class="bi bi-check-circle-fill text-success" title="Geschossen"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle-fill text-danger" title="Nicht teilgenommen"></i>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Mobile: Karten -->
<div class="jm-mobile">
    <?php
    foreach ($schiessen_list as $s):
        $is_streicher = in_array($s['_idx'], $all_streicher_idxs);
        $geschossen = ($s['PunkteNorm'] !== null);
        $punkte = $geschossen ? intval($s['Punkte']) : null;
        $punkte_norm = $s['PunkteNorm'];

        // Zukunft erkennen
        $is_future = false;
        foreach ($months_de as $name => $num) {
            if (stripos($s['Schiesstage'] ?? '', $name) !== false) {
                $approx = $selected_year . '-' . str_pad($num, 2, '0', STR_PAD_LEFT) . '-01';
                if ($approx > date('Y-m-d')) $is_future = true;
                break;
            }
        }

        $card_class = 'jm-card';
        if ($is_streicher) $card_class .= ' streicher';
        elseif ($geschossen) $card_class .= ' shot';
        elseif ($is_future) $card_class .= ' future';
        else $card_class .= ' not-shot';

        $result_class = '';
        if ($is_streicher) $result_class = 'streicher';
        elseif ($geschossen) $result_class = 'shot';
        else $result_class = 'not-shot';

        $datum_kurz = '';
        if (!empty($s['Schiesstage'])) {
            $lines = explode("\n", trim($s['Schiesstage']));
            $datum_kurz = trim($lines[0]);
        }
    ?>
    <div class="<?php echo $card_class; ?>">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="card-title">
                    <?php echo htmlspecialchars($s['Bezeichnung']); ?>
                    <?php if ($is_streicher): ?>
                        <span class="badge-streicher ms-1">Streicher</span>
                    <?php endif; ?>
                </div>
                <div class="card-meta">
                    <?php echo htmlspecialchars($datum_kurz); ?>
                </div>
            </div>
            <div class="card-result <?php echo $result_class; ?>">
                <?php if ($punkte_norm !== null): ?>
                    <?php if ($is_streicher): ?>
                        <span style="text-decoration:line-through; font-size:0.9rem;" title="Gestrichen">
                            <?php echo number_format($punkte_norm, 2, '.', ''); ?>
                        </span>
                    <?php else: ?>
                        <?php echo number_format($punkte_norm, 2, '.', ''); ?>
                    <?php endif; ?>
                <?php elseif ($is_future): ?>
                    <span style="font-size: 0.9rem; color: #adb5bd;">-</span>
                <?php else: ?>
                    <span style="font-size: 0.85rem;">-</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<?php include 'portal_footer.php'; ?>
