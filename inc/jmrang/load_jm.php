<?php
// load_jm.php
declare(strict_types=1);

// ======= Konfiguration =======
const ANZAHL_STREICHER = 3; // Anzahl zu streichender Wettbewerbe bei Streicher=1
const DEZIMALSTELLEN = 2;   // Für Punkt-Ausgabe

require_once '../config.php'; // $conn (mysqli)

// ======= Hilfsfunktionen =======

// Hochrechnung, falls nicht explizit ausgenommen
function scalePoints(float $points, array $def): float {
    if (in_array($def['Bezeichnung'], ['Einzelwettschiessen', 'Obligatorisch', 'Feldschiessen'])) {
        return $points;
    }
    $maxP = (int)$def['Maxpunkte'];
    return $maxP > 0 ? round(($points * 100) / $maxP, DEZIMALSTELLEN) : $points;
}

// Sicheres HTML
function h(?string $str): string { 
    return htmlspecialchars((string)$str ?? '', ENT_QUOTES, 'UTF-8'); 
}

// ======= 1. Parameter einlesen =======
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$kategorie = $_GET['kategorie'] ?? '';
$asJson = !empty($_GET['json']);

// ======= 2. Wettbewerbe laden =======
$sqlDef = "SELECT ID, Bezeichnung, Maxpunkte, Streicher FROM JMDefinition WHERE year = ? AND Erweitert=0 AND Info=0
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

// ======= 6a. Ermittle aktive Streicher-Wettbewerbe und füge Nicht-Teilnahmen hinzu =======
$activeStreicherIDs = [];
foreach ($definitions as $def) {
    $defID = (int)$def['ID'];
    $isStreicher = ((int)$def['Streicher'] === 1);
    
    if ($isStreicher) {
        // Prüfe ob dieser Wettbewerb überhaupt Resultate hat
        $hasResults = false;
        foreach ($tatsaechlicheTeilnahmen as $teilnahmen) {
            if (in_array($defID, $teilnahmen)) {
                $hasResults = true;
                break;
            }
        }
        // Zusätzlich prüfen ob es Sonderfall-Resultate gibt (Endstich/Kanti)
        if (!$hasResults) {
            foreach ($resultData as $mData) {
                if (!empty($mData['wettbewerbe'][$defID])) {
                    $hasResults = true;
                    break;
                }
            }
        }
        if ($hasResults) {
            $activeStreicherIDs[] = $defID;
        }
    }
}

foreach ($resultData as $mid => &$mData) {
    foreach ($activeStreicherIDs as $defID) {
        // Nur wenn nicht teilgenommen und noch keine Resultate vorhanden
        if ((!isset($tatsaechlicheTeilnahmen[$mid]) || !in_array($defID, $tatsaechlicheTeilnahmen[$mid]))
            && empty($mData['wettbewerbe'][$defID])) {
            // Nicht-Teilnahme als 0-Punkte-Resultat hinzufügen
            $mData['wettbewerbe'][$defID][] = 0;
        }
    }
}
unset($mData);

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
    
    // Streicher anwenden
    usort($streicher1Values, fn($a,$b)=> $a['punkte'] <=> $b['punkte']);
    $gestr    = array_slice($streicher1Values, 0, ANZAHL_STREICHER);
    $verwendet= array_slice($streicher1Values, ANZAHL_STREICHER);

    $sumStreicher1 = array_sum(array_column($verwendet, 'punkte'));

    $mData['sumStreicher0'] = $sumStreicher0;
    $mData['sumStreicher1'] = $sumStreicher1;
    $mData['sumTotal']      = $sumStreicher0 + $sumStreicher1;

    // Markierung der gestrichenen Werte
    $gmap = [];
    foreach ($gestr as $g) {
        $key = $g['defID'] . '|' . $g['punkte'];
        $gmap[$key] = ($gmap[$key] ?? 0) + 1;
    }
    foreach ($mData['wettbewerbe'] as $defID => &$pArray) {
        if (!in_array($defID, $streicher1IDs)) {
            foreach ($pArray as $ix => $val) {
                if (!is_array($val)) $pArray[$ix] = ['punkte' => $val, 'strichen' => false];
            }
        } else {
            foreach ($pArray as $ix => $val) {
                $key = $defID . '|' . $val;
                if (isset($gmap[$key]) && $gmap[$key] > 0) {
                    $pArray[$ix] = ['punkte' => $val, 'strichen' => true];
                    $gmap[$key]--;
                } else {
                    $pArray[$ix] = ['punkte' => $val, 'strichen' => false];
                }
            }
        }
    }
}
unset($mData);

// ======= 8. Sortieren nach sumTotal DESC, dann Name ASC =======
usort($resultData, function($a, $b) {
    $cmp = $b['sumTotal'] <=> $a['sumTotal'];
    if ($cmp !== 0) return $cmp;
    return strnatcasecmp($a['mitglied']['Name'], $b['mitglied']['Name']);
});

// ======= 9. Ausgabe =======
if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resultData);
    exit;
}

// ======= 9a. HTML Ausgabe =======
function renderTable(array $resultData, array $definitions): string {
    $theadHtml = "<tr><th>Rang</th><th>Name</th>";
    foreach ($definitions as $def) {
        $theadHtml .= "<th class='vertical-header'>" . h($def['Bezeichnung']) . "</th>";
    }
    $theadHtml .= "<th>Total</th></tr>";

    $tbodyHtml = "";
    $actualPosition=0; $currentRank=0; $previousScore=null;
    foreach($resultData as $entry) {
        $actualPosition++;
        $sumTotal = $entry['sumTotal'];
        if($sumTotal!==$previousScore) {
            $currentRank=$actualPosition;
            $previousScore=$sumTotal;
        }
        $m = $entry['mitglied'];
        $fullname = h($m['Name']." ".$m['Vorname']);
        $tbodyHtml .= "<tr>";
        $tbodyHtml .= "<td>$currentRank</td>";
        $tbodyHtml .= "<td>$fullname</td>";

        foreach($definitions as $def) {
            $defID = $def['ID'];
            $cellContent = "-";
            if(!empty($entry['wettbewerbe'][$defID])) {
                $ptArr = $entry['wettbewerbe'][$defID];
                $tmp=[];
                foreach($ptArr as $pItem) {
                    $pVal = number_format($pItem['punkte'], DEZIMALSTELLEN, '.', '');
                    if($pItem['strichen']) {
                        $tmp[] = "<span style='color:red;text-decoration:line-through;' title='Nicht gewertet (Streicher)'>{$pVal}</span>";
                    } else {
                        $tmp[] = "<span title='Gewertet'>{$pVal}</span>";
                    }
                }
                $cellContent = implode(", ", $tmp);
            }
            $tbodyHtml .= "<td>$cellContent</td>";
        }
        $tbodyHtml .= "<td><strong>" .number_format($sumTotal, DEZIMALSTELLEN, '.', ''). "</strong></td>";
        $tbodyHtml .= "</tr>";
    }
    return "<thead>$theadHtml</thead><tbody>$tbodyHtml</tbody>";
}

echo renderTable($resultData, $definitions);