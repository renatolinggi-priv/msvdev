<?php
// load_jm.php
declare(strict_types=1);

// ======= Konfiguration =======
const DEZIMALSTELLEN = 2;   // Für Punkt-Ausgabe

require_once '../config.php'; // $conn (mysqli)

// ======= Hilfsfunktionen =======

// Hochrechnung nur wenn Maxpunkte < 100 (z.B. 50 → 49*100/50 = 98)
function scalePoints(float $points, array $def): float {
    if (in_array($def['Bezeichnung'], ['Einzelwettschiessen', 'Obligatorisch', 'Feldschiessen'])) {
        return $points;
    }
    $maxP = (int)$def['Maxpunkte'];
    if ($maxP > 0 && $maxP < 100) {
        return round(($points * 100) / $maxP, DEZIMALSTELLEN);
    }
    return $points;
}

// Sicheres HTML
function h(?string $str): string {
    return htmlspecialchars((string)$str ?? '', ENT_QUOTES, 'UTF-8');
}

// Letztes Schiessdatum aus dem Schiesstage-Freitext extrahieren (Fallback, falls
// keine strukturierten JMSchiesstage-Einträge vorhanden sind). Gibt 'YYYY-MM-DD'.
function jmLastSchiessdatum(string $text, int $fallbackYear): ?string {
    $months = ['Januar'=>1,'Februar'=>2,'März'=>3,'Maerz'=>3,'April'=>4,'Mai'=>5,
               'Juni'=>6,'Juli'=>7,'August'=>8,'September'=>9,'Oktober'=>10,
               'November'=>11,'Dezember'=>12];
    $last = null;
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        foreach ($months as $name => $num) {
            if (preg_match('/(\d{1,2})\.\s*' . preg_quote($name, '/') . '(?:\s+(\d{4}))?/u', $line, $m)) {
                $y = (isset($m[2]) && $m[2] !== '') ? (int)$m[2] : $fallbackYear;
                $d = sprintf('%04d-%02d-%02d', $y, $num, (int)$m[1]);
                if ($last === null || $d > $last) $last = $d;
                break;
            }
        }
    }
    return $last;
}

// ======= 1. Parameter einlesen =======
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$kategorie = $_GET['kategorie'] ?? '';
$asJson = !empty($_GET['json']);

// Anzahl Streicher aus Parameter-Tabelle (Fallback 3)
$stParam = $conn->prepare("SELECT excludeCount FROM Parameter WHERE year = ?");
$stParam->bind_param('i', $year);
$stParam->execute();
$rowParam = $stParam->get_result()->fetch_assoc();
$stParam->close();
$anzahl_streicher = $rowParam ? max(1, (int)$rowParam['excludeCount']) : 3;

// ======= 2. Wettbewerbe laden =======
$sqlDef = "SELECT ID, Bezeichnung, Maxpunkte, Streicher, Schiesstage FROM JMDefinition WHERE year = ? AND Erweitert=0 AND Info=0
    ORDER BY 
        CASE 
            WHEN Bezeichnung = 'Obligatorisch' THEN 1
            WHEN Bezeichnung = 'Feldschiessen' THEN 2
            WHEN Bezeichnung LIKE '%Kantonalstich%' THEN 3
            WHEN Bezeichnung LIKE '%Sektionsmeisterschaft%' THEN 4
            ELSE 5
        END, Reihenfolge";
$stmtDef = $conn->prepare($sqlDef);
$stmtDef->bind_param('i', $year);
$stmtDef->execute();
$resDef = $stmtDef->get_result();
$definitions = [];
$defByID = [];
while ($row = $resDef->fetch_assoc()) {
    $definitions[] = $row;
    $defByID[ $row['ID'] ] = $row;
}

if (!$definitions) {
    if ($asJson) { 
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => "Keine Wettbewerbe für Jahr $year gefunden"]);
        exit; 
    }
    echo json_encode([
        'thead' => "<tr><th>Keine Wettbewerbe gefunden</th></tr>",
        'tbody' => "<tr><td>Für das Jahr $year wurden keine Wettbewerbe gefunden.</td></tr>"
    ]);
    exit;
}
$definitionIDs = array_column($definitions, 'ID');

// ======= 2a. Letztes Schiessdatum je Wettbewerb =======
// Primär aus strukturierter Tabelle JMSchiesstage, sonst aus Freitext Schiesstage.
$lastDateByDef = [];
$resDates = $conn->query("SELECT jm_id, MAX(schiesstag) AS last_date FROM JMSchiesstage WHERE year = " . (int)$year . " GROUP BY jm_id");
if ($resDates) {
    while ($r = $resDates->fetch_assoc()) {
        if (!empty($r['last_date'])) $lastDateByDef[(int)$r['jm_id']] = $r['last_date'];
    }
}
foreach ($definitions as $def) {
    $id = (int)$def['ID'];
    if (!isset($lastDateByDef[$id]) && !empty($def['Schiesstage'])) {
        $d = jmLastSchiessdatum($def['Schiesstage'], (int)$year);
        if ($d) $lastDateByDef[$id] = $d;
    }
}

// ======= 3. Mitglieder laden =======
$members = [];
if ($kategorie !== '') {
    $sqlMembers = "SELECT m.ID, m.Vorname, m.Name FROM mitglieder m
                   JOIN Waffen w ON w.ID = m.WaffenID
                   WHERE w.Kategorie = ? AND m.Status = 1
                   ORDER BY m.Name, m.Vorname";
    $stmtM = $conn->prepare($sqlMembers);
    $stmtM->bind_param('s', $kategorie);
    $stmtM->execute();
    $resM = $stmtM->get_result();
} else {
    $sqlMembers = "SELECT m.ID, m.Vorname, m.Name FROM mitglieder m
                   JOIN Waffen w ON w.ID = m.WaffenID
                   ORDER BY m.Name, m.Vorname";
    $resM = $conn->query($sqlMembers);
}
while ($row = $resM->fetch_assoc()) {
    $members[] = $row;
}

if (!$members) {
    if ($asJson) { 
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => "Keine Mitglieder für Kategorie '$kategorie' gefunden"]);
        exit; 
    }
    echo json_encode([
        'thead' => "<tr><th>Keine Mitglieder gefunden</th></tr>",
        'tbody' => "<tr><td>Für diese Kategorie/Jahr keine Mitglieder.</td></tr>"
    ]);
    exit;
}

// ======= 4. Datenstruktur vorbereiten =======
$resultData = [];
foreach ($members as $m) {
    $mid = $m['ID'];
    $resultData[$mid] = [
        'mitglied' => $m,
        'wettbewerbe' => []
    ];
}

// ======= 5. Endstich/Kanti-Sonderfälle laden =======
function loadSonderfall(mysqli $conn, string $sql, int $defID, array &$resultData, array $defByID) {
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $mid    = (int)$row['MitgliedID'];
            $punkte = (int)$row['Punkte'];
            if (isset($resultData[$mid])) {
                $scaled = scalePoints($punkte, $defByID[$defID]);
                $resultData[$mid]['wettbewerbe'][$defID][] = $scaled;
            }
        }
    }
}

$endstichID = null;
$kantiID = null;
foreach ($definitions as $def) {
    if ($def['Bezeichnung'] === 'Endstich')        $endstichID = (int)$def['ID'];
    if ($def['Bezeichnung'] === 'Bester Kantonalstich') $kantiID = (int)$def['ID'];
}

if ($endstichID) {
    loadSonderfall($conn, "
        SELECT e.MitgliedID,
        (COALESCE(e.Schuss1,0) + COALESCE(e.Schuss2,0) + COALESCE(e.Schuss3,0) +
         COALESCE(e.Schuss4,0) + COALESCE(e.Schuss5,0) + COALESCE(e.Schuss6,0) +
         COALESCE(e.Schuss7,0) + COALESCE(e.Schuss8,0) + COALESCE(e.Schuss9,0) +
         COALESCE(e.Schuss10,0)) AS Punkte
        FROM endstich e WHERE e.Jahr = $year
    ", $endstichID, $resultData, $defByID);
}

if ($kantiID) {
    loadSonderfall($conn, "
        SELECT k.MitgliedID,
        GREATEST(
            COALESCE(k.Passe1,0),COALESCE(k.Passe2,0),COALESCE(k.Passe3,0),
            COALESCE(k.Passe4,0),COALESCE(k.Passe5,0)
        ) AS Punkte
        FROM kantiresultate k WHERE k.Jahr = $year
    ", $kantiID, $resultData, $defByID);
}

// ======= 6. Normale Resultate laden =======
$normaleIDs = array_diff($definitionIDs, array_filter([$endstichID, $kantiID]));

// Sammle tatsächliche Teilnahmen
$tatsaechlicheTeilnahmen = [];

if ($normaleIDs) {
    $idList = implode(',', $normaleIDs);
    $sqlResults = "
        SELECT mitgliederID AS mid, jmdefinitionID AS defID, Punkte
        FROM jmresultate
        WHERE jmdefinitionID IN ($idList)
    ";
    $r = $conn->query($sqlResults);
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $mid   = (int)$row['mid'];
            $defID = (int)$row['defID'];
            $raw   = (int)$row['Punkte'];
            if (isset($resultData[$mid])) {
                $scaled = scalePoints($raw, $defByID[$defID]);
                $resultData[$mid]['wettbewerbe'][$defID][] = $scaled;
                $tatsaechlicheTeilnahmen[$mid][] = $defID;
            }
        }
    }
}

// ======= 6a. Streicher-Pool = nur DURCHGEFÜHRTE Anlässe =======
// Regel: Für alle Schützen zählen die besten (durchgeführte − Streicher) Resultate.
// Basis = alle bereits DURCHGEFÜHRTEN Streicher-Wettbewerbe (Datum <= heute bzw.
// – falls kein Datum hinterlegt – sobald Resultate vorliegen). Noch OFFENE Anlässe
// zählen weder als Resultat noch als Streicher mit. Verpasste/durchgeführte Anlässe
// ohne Resultat erhalten 0 und können damit gestrichen werden.
$heute = date('Y-m-d');
$doneStreicherIDs = [];
foreach ($definitions as $def) {
    if ((int)$def['Streicher'] !== 1) continue;
    $defID = (int)$def['ID'];
    $ld = $lastDateByDef[$defID] ?? null;
    if ($ld !== null) {
        $isDone = ($ld <= $heute);
    } else {
        // Kein Datum bekannt -> als durchgeführt werten, sobald Resultate vorliegen
        $isDone = false;
        foreach ($resultData as $mData) {
            if (!empty($mData['wettbewerbe'][$defID])) { $isDone = true; break; }
        }
    }
    if ($isDone) $doneStreicherIDs[] = $defID;
}

// 0-Platzhalter nur für DURCHGEFÜHRTE Streicher-Wettbewerbe ohne Resultat
// (= verpasst bzw. noch nicht erfasst). So ist die Basis für alle Schützen
// identisch (= Anzahl durchgeführter Anlässe), gestrichen werden die schlechtesten.
foreach ($resultData as $mid => &$mData) {
    $mData['_placeholder'] = []; // defID => true: 0-Platzhalter (verpasst/nicht erfasst)
    foreach ($doneStreicherIDs as $defID) {
        if (empty($mData['wettbewerbe'][$defID])) {
            $mData['wettbewerbe'][$defID][] = 0;
            $mData['_placeholder'][$defID] = true;
        }
    }
}
unset($mData);

// ======= 6b. Ist der Endstich / das Endschiessen durchgeführt? =======
// Bis der Endstich durch ist, werden KEINE Streicher angewendet und KEIN Total
// berechnet/angezeigt. Erst danach steht die definitive Rangliste fest.
$endstichDone = true; // Fallback: kein Endstich definiert -> Total normal anzeigen
if (!empty($endstichID)) {
    $eld = $lastDateByDef[$endstichID] ?? null;
    if ($eld !== null) {
        $endstichDone = ($eld <= $heute);
    } else {
        // Kein Datum hinterlegt -> als durchgeführt werten, sobald Endstich-Resultate vorliegen
        $endstichDone = false;
        foreach ($resultData as $mChk) {
            if (!empty($mChk['wettbewerbe'][$endstichID])) { $endstichDone = true; break; }
        }
    }
}

// ======= 7. Streicher- und Summenlogik =======
$sektionsmeisterschaftID = null;
foreach ($definitions as $d) {
    if ($d['Bezeichnung'] === 'Sektionsmeisterschaft') {
        $sektionsmeisterschaftID = $d['ID'];
        break;
    }
}
$streicher1IDs = [];
foreach ($definitions as $d) {
    if ((int)$d['Streicher'] === 1) $streicher1IDs[] = $d['ID'];
}

foreach ($resultData as $mid => &$mData) {
    $sumStreicher0 = 0;
    $streicher1Values = [];

    foreach ($definitions as $def) {
        $defID  = (int)$def['ID'];
        $isStr1 = ((int)$def['Streicher'] === 1);

        if (empty($mData['wettbewerbe'][$defID])) continue;
        $allPoints = $mData['wettbewerbe'][$defID];

        // Sektionsmeisterschaft => nur höchster Wert
        if ($sektionsmeisterschaftID && $defID == $sektionsmeisterschaftID) {
            $allPoints = [max($allPoints)];
            $mData['wettbewerbe'][$defID] = $allPoints;
        }

        if ($isStr1) {
            foreach ($allPoints as $p) $streicher1Values[] = ['defID' => $defID, 'punkte' => $p];
        } else {
            $sumStreicher0 += array_sum($allPoints);
        }
    }
    
    // Streicher nur anwenden, wenn der Endstich durch ist – sonst kein Total.
    if ($endstichDone) {
        usort($streicher1Values, fn($a,$b)=> $a['punkte'] <=> $b['punkte']);
        $gestr     = array_slice($streicher1Values, 0, $anzahl_streicher);
        $verwendet = array_slice($streicher1Values, $anzahl_streicher);

        $mData['sumStreicher0'] = $sumStreicher0;
        $mData['sumStreicher1'] = array_sum(array_column($verwendet, 'punkte'));
        $mData['sumTotal']      = $mData['sumStreicher0'] + $mData['sumStreicher1'];
    } else {
        // Vor dem Endstich: keine Streicher, kein Total.
        $gestr = [];
        $mData['sumStreicher0'] = $sumStreicher0;
        $mData['sumStreicher1'] = array_sum(array_column($streicher1Values, 'punkte'));
        $mData['sumTotal']      = null; // Total erst nach dem Endstich
    }

    // Markierung der gestrichenen Werte
    $gmap = [];
    foreach ($gestr as $g) {
        $key = $g['defID'] . '|' . $g['punkte'];
        $gmap[$key] = ($gmap[$key] ?? 0) + 1;
    }
    foreach ($mData['wettbewerbe'] as $defID => &$pArray) {
        $isPlaceholder = isset($mData['_placeholder'][$defID]);
        if (!in_array($defID, $streicher1IDs)) {
            foreach ($pArray as $ix => $val) {
                if (!is_array($val)) $pArray[$ix] = ['punkte' => $val, 'strichen' => false, 'placeholder' => $isPlaceholder];
            }
        } else {
            foreach ($pArray as $ix => $val) {
                $key = $defID . '|' . $val;
                if (isset($gmap[$key]) && $gmap[$key] > 0) {
                    $pArray[$ix] = ['punkte' => $val, 'strichen' => true, 'placeholder' => $isPlaceholder];
                    $gmap[$key]--;
                } else {
                    $pArray[$ix] = ['punkte' => $val, 'strichen' => false, 'placeholder' => $isPlaceholder];
                }
            }
        }
    }
}
unset($mData);

// ======= 7c. "Hat gewertetes JM-Resultat" bestimmen (für Gruppierung) =======
// Gleiche Logik wie das Erfassungs-Panel: Cup, SSM/Sektionsmeisterschaft,
// Feldschiessen und Obligatorisch zählen NICHT als Resultat. Nur echte
// (Nicht-Platzhalter) Einträge in den übrigen Anlässen gelten als Teilnahme.
$countingDefIDs = [];
foreach ($definitions as $def) {
    $bez = (string)$def['Bezeichnung'];
    if ($bez === 'Obligatorisch' || $bez === 'Feldschiessen') continue;
    if (stripos($bez, 'Sektionsmeisterschaft') !== false) continue;
    if (stripos($bez, 'SSM') === 0) continue;
    if (stripos($bez, 'Cup') !== false) continue;
    $countingDefIDs[(int)$def['ID']] = true;
}
foreach ($resultData as &$mData) {
    $hasResult = false;
    foreach ($mData['wettbewerbe'] as $defID => $pArray) {
        if (!isset($countingDefIDs[(int)$defID])) continue;
        foreach ($pArray as $p) {
            $isPlaceholder = is_array($p) ? !empty($p['placeholder']) : false;
            if (!$isPlaceholder) { $hasResult = true; break 2; }
        }
    }
    $mData['hasResult'] = $hasResult;
}
unset($mData);

// ======= 8. Sortieren =======
// Zuerst Schützen MIT gewertetem Resultat, danach jene ohne (separate Gruppe).
// Innerhalb "mit Resultat": nach dem Endstich nach Total DESC (Rangliste),
// vorher alphabetisch. "Ohne Resultat" immer alphabetisch.
usort($resultData, function($a, $b) use ($endstichDone) {
    if (($a['hasResult'] ?? false) !== ($b['hasResult'] ?? false)) {
        return ($a['hasResult'] ?? false) ? -1 : 1;
    }
    if (($a['hasResult'] ?? false) && $endstichDone) {
        $cmp = $b['sumTotal'] <=> $a['sumTotal'];
        if ($cmp !== 0) return $cmp;
    }
    return strnatcasecmp($a['mitglied']['Name'], $b['mitglied']['Name']);
});

// ======= 9. Ausgabe =======
if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resultData);
    exit;
}

// ======= 9a. Hilfsfunktionen für die Ausgabe =======

// Summe aller (echten) Einträge eines Wettbewerbs (i.d.R. genau ein Eintrag)
function sumEntries(array $entry, int $defID): float {
    if (empty($entry['wettbewerbe'][$defID])) return 0.0;
    $s = 0.0;
    foreach ($entry['wettbewerbe'][$defID] as $p) {
        if (!empty($p['placeholder'])) continue;
        $s += $p['punkte'];
    }
    return $s;
}

// Zelleninhalt einer Spalte in der Hauptzeile: erfasstes Resultat oder "–"
// (stattgefunden, aber noch nicht erfasst => Platzhalter => "–").
function formatCell(array $entry, int $defID): string {
    if (empty($entry['wettbewerbe'][$defID])) return "&ndash;";
    $tmp = [];
    foreach ($entry['wettbewerbe'][$defID] as $p) {
        if (!empty($p['placeholder'])) { $tmp[] = "<span class='text-muted'>&ndash;</span>"; continue; }
        $val = number_format($p['punkte'], DEZIMALSTELLEN, '.', '');
        if (!empty($p['strichen'])) {
            $tmp[] = "<span class='jm-cell-strichen' data-tooltip='Streicher'>$val</span>";
        } else {
            $tmp[] = "<span data-tooltip='Gewertet'>$val</span>";
        }
    }
    return implode(", ", $tmp);
}

// ======= 9b. HTML Ausgabe als JSON – Rangliste + gruppierte Aufschlüsselung =======
function renderTable(array $resultData, array $definitions, array $lastDateByDef, bool $endstichDone): array {
    $today = date('Y-m-d');
    $offenTxt = "<span class='text-muted'>offen</span>";

    // Wettbewerbe in zwei fachliche Gruppen aufteilen (für die Detail-Aufschlüsselung)
    $pflichtDefs = []; // Streicher = 0 (zählt immer)
    $streichDefs = []; // Streicher = 1 (Streichresultate)
    foreach ($definitions as $def) {
        if ((int)$def['Streicher'] === 1) $streichDefs[] = $def;
        else                              $pflichtDefs[] = $def;
    }

    // ---- Hauptspalten = die zuletzt STATTGEFUNDENEN Anlässe (nach Datum, chronologisch) ----
    // Auch noch nicht erfasste werden angezeigt (Zelle "–"). Anlässe ohne Datum
    // (Obligatorisch, Sektionsmeisterschaft, Kantonalstich) erscheinen nur im Detail.
    $occurred = [];
    foreach ($definitions as $def) {
        $id = (int)$def['ID'];
        // 20er-Schiessen (Obligatorisch, Feldschiessen) nicht als Spalte anzeigen
        // (andere Skala). Sie zählen weiterhin und erscheinen im Detail unter Pflicht.
        if ((int)$def['Maxpunkte'] === 20) continue;
        if (isset($lastDateByDef[$id]) && $lastDateByDef[$id] <= $today) {
            $occurred[] = ['def' => $def, 'date' => $lastDateByDef[$id]];
        }
    }
    usort($occurred, fn($a, $b) => $a['date'] <=> $b['date']); // chronologisch aufsteigend
    $mainCols = array_slice($occurred, -6);                    // letzte 6 (neueste rechts)
    $mainDefs = array_map(fn($x) => $x['def'], $mainCols);
    $numCols  = count($mainDefs);
    $totalColspan = 2 + $numCols + 2; // Rang + Name + Spalten + Total + Toggle

    // ---- Header ----
    $theadHtml  = "<tr>";
    $theadHtml .= "<th class='jm-th-rang'>Rang</th>";
    $theadHtml .= "<th class='jm-th-name'>Name</th>";
    foreach ($mainDefs as $def) {
        $theadHtml .= "<th class='jm-th-result text-center' data-tooltip='" . h($def['Bezeichnung']) . "'><span class='jm-th-label'>" . h($def['Bezeichnung']) . "</span></th>";
    }
    $theadHtml .= "<th class='jm-th-total text-center'>Total</th>";
    $theadHtml .= "<th class='jm-th-toggle'></th>";
    $theadHtml .= "</tr>";

    // ---- Body ----
    // Gruppierung: zuerst Schützen mit gewertetem Resultat (Rangliste), danach als
    // separate Gruppe jene ohne Resultat (alphabetisch, ohne Rang).
    $noResultCount = 0;
    foreach ($resultData as $e) { if (empty($e['hasResult'])) $noResultCount++; }

    $tbodyHtml = "";
    $actualPosition = 0; $currentRank = 0; $previousScore = null; $rowIdx = 0;
    $noResultHeaderShown = false;

    foreach ($resultData as $entry) {
        $rowIdx++;
        $hasResult = !empty($entry['hasResult']);

        // Trennzeile vor der ersten Zeile ohne Resultat
        if (!$hasResult && !$noResultHeaderShown) {
            $noResultHeaderShown = true;
            $tbodyHtml .= "<tr class='jm-group-row'><td colspan='$totalColspan' class='jm-group-cell'>"
                . "<i class='bi bi-dash-circle me-1'></i>Ohne gewertetes JM-Resultat ($noResultCount)</td></tr>";
        }

        $sumTotal = $entry['sumTotal'];
        if ($hasResult) {
            $actualPosition++;
            if ($sumTotal !== $previousScore) {
                $currentRank = $actualPosition;
                $previousScore = $sumTotal;
            }
        }
        $m = $entry['mitglied'];
        $fullname   = h($m['Name'] . " " . $m['Vorname']);
        // Vor dem Endstich: kein Total/keine Rangfolge -> "offen"
        $pflichtSum = $endstichDone ? number_format($entry['sumStreicher0'], DEZIMALSTELLEN, '.', '') : $offenTxt;
        $streichSum = $endstichDone ? number_format($entry['sumStreicher1'], DEZIMALSTELLEN, '.', '') : $offenTxt;
        $totalStr   = $endstichDone ? number_format($sumTotal, DEZIMALSTELLEN, '.', '') : $offenTxt;
        $rangStr    = ($hasResult && $endstichDone) ? (string)$currentRank : "&ndash;";
        $totalTip   = $endstichDone ? "" : " data-tooltip='Total wird erst nach dem Endstich berechnet'";

        // ---- Hauptzeile (Rangliste mit letzten Anlässen) ----
        $tbodyHtml .= "<tr class='jm-main-row' data-row='$rowIdx'>";
        $tbodyHtml .= "<td class='text-center fw-semibold jm-rang-cell'>$rangStr</td>";
        $tbodyHtml .= "<td class='text-nowrap jm-name-cell'>$fullname</td>";
        foreach ($mainDefs as $def) {
            $tbodyHtml .= "<td class='text-center jm-result-cell'>" . formatCell($entry, (int)$def['ID']) . "</td>";
        }
        $tbodyHtml .= "<td class='text-center fw-bold jm-total-cell'$totalTip>$totalStr</td>";
        $tbodyHtml .= "<td class='text-center'>"
            . "<button type='button' class='btn btn-sm btn-link p-0 jm-toggle-btn' data-tooltip='Aufschlüsselung anzeigen'>"
            . "<i class='bi bi-chevron-down'></i></button></td>";
        $tbodyHtml .= "</tr>";

        // ---- Detailzeile: zwei gruppierte Listen ----
        $tbodyHtml .= "<tr class='jm-detail-row' data-row='$rowIdx' style='display:none'>";
        $tbodyHtml .= "<td colspan='$totalColspan'>";
        $tbodyHtml .= "<div class='jm-detail-panel'>";
        $tbodyHtml .= "<div class='jm-detail-groups'>";

        // --- Gruppe 1: Pflicht (zählt immer) ---
        $tbodyHtml .= "<div class='jm-detail-group jm-group-pflicht'>";
        $tbodyHtml .= "<div class='jm-detail-group-head'>"
            . "<span class='jm-detail-group-title'><i class='bi bi-pin-angle me-1'></i>Pflicht</span>"
            . "<span class='jm-detail-group-meta'>zählt immer</span></div>";
        $tbodyHtml .= "<div class='jm-detail-lines'>";
        foreach ($pflichtDefs as $def) {
            $defID = (int)$def['ID'];
            $max   = (int)$def['Maxpunkte'];
            $hasReal = false;
            if (!empty($entry['wettbewerbe'][$defID])) {
                foreach ($entry['wettbewerbe'][$defID] as $p) { if (empty($p['placeholder'])) { $hasReal = true; break; } }
            }
            if ($hasReal) {
                $valStr = number_format(sumEntries($entry, $defID), DEZIMALSTELLEN, '.', '');
                $maxStr = $max > 0 ? "<span class='jm-line-max'>/ $max</span>" : "";
                $tbodyHtml .= "<div class='jm-detail-line'>"
                    . "<span class='jm-line-name'>" . h($def['Bezeichnung']) . "</span>"
                    . "<span class='jm-line-pts'><span class='jm-line-val'>$valStr</span>$maxStr</span></div>";
            } else {
                $tbodyHtml .= "<div class='jm-detail-line jm-line-empty'>"
                    . "<span class='jm-line-name'>" . h($def['Bezeichnung']) . "</span>"
                    . "<span class='jm-line-pts'><span class='jm-line-val'>&ndash;</span></span></div>";
            }
        }
        $tbodyHtml .= "</div>"; // .jm-detail-lines
        $tbodyHtml .= "<div class='jm-detail-subtotal'><span>Zwischentotal</span><span>$pflichtSum</span></div>";
        $tbodyHtml .= "</div>"; // .jm-detail-group

        // --- Gruppe 2: Auswärtige Schiessen (Streichresultate) ---
        // Nur echte Resultate auflisten (Platzhalter für offene/verpasste werden
        // separat gezählt). Sortierung nach Punkten DESC; gestrichene markiert.
        $streichLines = [];
        $gewertet = 0; $gestrichen = 0; $offen = 0; $verpasst = 0;
        foreach ($streichDefs as $def) {
            $defID = (int)$def['ID'];
            if (empty($entry['wettbewerbe'][$defID])) continue;
            foreach ($entry['wettbewerbe'][$defID] as $p) {
                if (!empty($p['placeholder'])) {
                    $ld = $lastDateByDef[$defID] ?? null;
                    if ($ld !== null && $ld <= $today) $verpasst++; else $offen++;
                    continue;
                }
                $streichLines[] = [
                    'name'     => $def['Bezeichnung'],
                    'punkte'   => $p['punkte'],
                    'strichen' => $p['strichen'],
                ];
                if ($p['strichen']) $gestrichen++; else $gewertet++;
            }
        }
        usort($streichLines, fn($a, $b) => $b['punkte'] <=> $a['punkte']);

        $metaParts = ["$gewertet gewertet"];
        if ($gestrichen > 0) $metaParts[] = "$gestrichen gestrichen";
        if ($verpasst   > 0) $metaParts[] = "$verpasst verpasst";
        if ($offen      > 0) $metaParts[] = "$offen offen";
        $metaTxt = implode(' · ', $metaParts);

        $tbodyHtml .= "<div class='jm-detail-group jm-group-streich'>";
        $tbodyHtml .= "<div class='jm-detail-group-head'>"
            . "<span class='jm-detail-group-title'><i class='bi bi-geo-alt me-1'></i>Auswärtige Schiessen</span>"
            . "<span class='jm-detail-group-meta'>$metaTxt</span></div>";
        $tbodyHtml .= "<div class='jm-detail-lines'>";
        if ($streichLines) {
            foreach ($streichLines as $ln) {
                $valStr = number_format($ln['punkte'], DEZIMALSTELLEN, '.', '');
                $cls = 'jm-detail-line' . ($ln['strichen'] ? ' gestrichen' : '');
                $tag = $ln['strichen'] ? "<span class='jm-line-tag'>gestrichen</span>" : "";
                $tbodyHtml .= "<div class='$cls'>"
                    . "<span class='jm-line-name'>" . h($ln['name']) . "</span>"
                    . "<span class='jm-line-pts'>$tag<span class='jm-line-val'>$valStr</span></span></div>";
            }
        } else {
            $tbodyHtml .= "<div class='jm-detail-line jm-line-empty'>"
                . "<span class='jm-line-name text-muted'>Noch keine auswärtigen Resultate</span>"
                . "<span class='jm-line-pts'><span class='jm-line-val'>&ndash;</span></span></div>";
        }
        $tbodyHtml .= "</div>"; // .jm-detail-lines
        $tbodyHtml .= "<div class='jm-detail-subtotal'><span>Zwischentotal</span><span>$streichSum</span></div>";
        $tbodyHtml .= "</div>"; // .jm-detail-group

        $tbodyHtml .= "</div>"; // .jm-detail-groups

        // Gesamttotal – erst nach dem Endstich
        if ($endstichDone) {
            $tbodyHtml .= "<div class='jm-detail-total'>"
                . "<span><i class='bi bi-trophy me-2'></i>Gesamttotal</span>"
                . "<span class='jm-detail-total-val'>$totalStr</span></div>";
        } else {
            $tbodyHtml .= "<div class='jm-detail-total jm-detail-total-offen'>"
                . "<span><i class='bi bi-hourglass-split me-2'></i>Total wird nach dem Endstich berechnet</span></div>";
        }

        $tbodyHtml .= "</div>"; // .jm-detail-panel
        $tbodyHtml .= "</td></tr>";
    }

    return ['thead' => $theadHtml, 'tbody' => $tbodyHtml];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(renderTable($resultData, $definitions, $lastDateByDef, $endstichDone));