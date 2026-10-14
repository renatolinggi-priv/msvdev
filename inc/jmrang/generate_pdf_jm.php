<?php
// generate_pdf_jm.php
// Erzeugt ein PDF mit der Jahresmeisterschaft-Rangliste
// Für Kat. A und Kat. B, inkl. Endstich, Kantiresultate, Streicher-Logik etc.

ob_start();

require_once '../vendor/autoload.php';
require_once '../config.php';
require_once 'config_pdf.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Zugriff nur Admin-Bereich (admin/vorstand); startet auch die Session
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');

// 1) GET-Parameter: Jahr
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Jahr ohne Jahresprogramm: kein leeres PDF (und kein leeres Blatt im Direktdruck), sondern ein Hinweis
$leerStmt = $conn->prepare('SELECT COUNT(*) FROM JMDefinition WHERE year = ? AND Erweitert = 0 AND Info = 0');
$leerStmt->bind_param('i', $selectedYear);
$leerStmt->execute();
$leerStmt->bind_result($anzahlAnlaesse);
$leerStmt->fetch();
$leerStmt->close();
if (!$anzahlAnlaesse) {
    msvAusgabeLeer('Für ' . $selectedYear . ' ist noch kein Jahresprogramm mit JM-Anlässen erfasst.');
}

// 2) HTML-Kopf zusammenbauen mit Logo
$htmlOutput = $header; 
$htmlOutput .= "<title>Jahresmeisterschaft $selectedYear</title>";
$htmlOutput .= "</head>\n<body>\n";

// 3) Ranglisten erzeugen: Kat. A / Kat. B, jede auf eigener Seite mit Kopf
$htmlOutput .= pdf_rangliste_kopf("Jahresmeisterschaft $selectedYear", 'Rangliste');
$htmlOutput .= buildJMRangliste($selectedYear, 'Kat. A', $conn);
$htmlOutput .= "<div class='page-break'></div>";
$htmlOutput .= pdf_rangliste_kopf("Jahresmeisterschaft $selectedYear", 'Rangliste');
$htmlOutput .= buildJMRangliste($selectedYear, 'Kat. B', $conn);

// 4) Footer
$htmlOutput .= $footer;

// PDF-Erzeugung
try {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);
    require_once __DIR__ . '/../pdf/pdf_orientation.inc.php';
    $dompdf->setPaper('A4', pdfOrientationParam('landscape')); // Ausrichtung aus dem Druckprofil, Default quer
    $dompdf->loadHtml($htmlOutput);
    $dompdf->render();
    pdf_seitenfuss($dompdf, "Jahresmeisterschaft $selectedYear");

    // PDF-Datei speichern
    $pdfOutput = $dompdf->output();
    $date = new DateTime();
    $pdfFilePath = 'dat/Jahresmeisterschaft_' . $date->format('Y-m-d_H-i-s') . '.pdf';
    
    // Verzeichnis erstellen falls nicht vorhanden
    if (!file_exists('dat')) {
        mkdir('dat', 0755, true);
    }
    
    file_put_contents($pdfFilePath, $pdfOutput);

    // Buffer leeren
    ob_end_clean();

    // JSON-Antwort mit dem PDF-Link für AJAX
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['pdf_link' => 'jmrang/'.$pdfFilePath]);
    exit;
    
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e)]);
    exit;
}

// ========================================================================
// Hilfsfunktion buildJMRangliste($year, $kategorie, $conn)
// ========================================================================
function buildJMRangliste($year, $kategorie, $conn) {
    // 1) Wettbewerbe laden
    $sqlDefinitions = "
        SELECT ID, Bezeichnung, Maxpunkte, Streicher
        FROM JMDefinition
        WHERE year = ?
          AND Erweitert = 0
          AND Info = 0
        ORDER BY 
            CASE 
                WHEN Bezeichnung = 'Obligatorisch' THEN 1
                WHEN Bezeichnung = 'Feldschiessen' THEN 2
                WHEN Bezeichnung LIKE '%Kantonalstich%' THEN 3
                WHEN Bezeichnung LIKE '%Sektionsmeisterschaft%' THEN 4
                ELSE 5
            END, Reihenfolge
    ";
    $stmt = $conn->prepare($sqlDefinitions);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $defsResult = $stmt->get_result();
    
    $definitions = [];
    $defByID     = [];
    while ($row = $defsResult->fetch_assoc()) {
        $definitions[] = $row;
        $defByID[$row['ID']] = $row;
    }
    
    if (!$definitions) {
        return "<p>Keine Wettbewerbe für $year gefunden.</p>";
    }
    $definitionIDs = array_column($definitions, 'ID');

    // 2) Mitglieder in passender Kategorie laden
    $sqlMembers = "
        SELECT m.ID, m.Vorname, m.Name
        FROM mitglieder m
        JOIN Waffen w ON w.ID = m.WaffenID
        WHERE w.Kategorie = ? AND m.Status = 1
        ORDER BY m.Name, m.Vorname
    ";
    $stmtMem = $conn->prepare($sqlMembers);
    $stmtMem->bind_param('s', $kategorie);
    $stmtMem->execute();
    $resMem = $stmtMem->get_result();

    $members = [];
    while ($m = $resMem->fetch_assoc()) {
        $members[] = $m;
    }
    
    if (!$members) {
        return "<p>Keine Mitglieder für Kategorie $kategorie gefunden.</p>";
    }

    // Anzahl Streicher aus der Parameter-Tabelle (wie Bildschirm-Rangliste/Resultatbuch)
    $anzahlStreicher = 3;
    $stmtP = $conn->prepare("SELECT excludeCount FROM Parameter WHERE year = ?");
    if ($stmtP) {
        $stmtP->bind_param('i', $year);
        $stmtP->execute();
        $rowP = $stmtP->get_result()->fetch_assoc();
        $stmtP->close();
        if ($rowP) $anzahlStreicher = max(1, (int)$rowP['excludeCount']);
    }

    // 3) Datenstruktur resultData
    $resultData = [];
    foreach ($members as $m) {
        $mid = $m['ID'];
        $resultData[$mid] = [
            'mitglied'    => $m,
            'wettbewerbe' => []
        ];
    }

    // 4) Endstich & Kanti in JMDefinition finden
    $endstichDef = null;
    $kantiDef    = null;
    foreach ($definitions as $def) {
        if ($def['Bezeichnung'] === 'Endstich') {
            $endstichDef = $def;
        }
        if ($def['Bezeichnung'] === 'Bester Kantonalstich') {
            $kantiDef = $def;
        }
    }

    // 4A) Endstich-Werte laden
    if ($endstichDef) {
        $endID = (int)$endstichDef['ID'];
        $sqlEnd = "
            SELECT
              e.MitgliedID,
              (
                COALESCE(e.Schuss1,0)+COALESCE(e.Schuss2,0)+COALESCE(e.Schuss3,0)+
                COALESCE(e.Schuss4,0)+COALESCE(e.Schuss5,0)+COALESCE(e.Schuss6,0)+
                COALESCE(e.Schuss7,0)+COALESCE(e.Schuss8,0)+COALESCE(e.Schuss9,0)+
                COALESCE(e.Schuss10,0)
              ) AS Punkte
            FROM endstich e
            WHERE e.Jahr = ?
        ";
        $stmtEnd = $conn->prepare($sqlEnd);
        $stmtEnd->bind_param('i', $year);
        $stmtEnd->execute();
        $resE = $stmtEnd->get_result();
        
        while ($rowE = $resE->fetch_assoc()) {
            $mid  = (int)$rowE['MitgliedID'];
            $pnts = (int)$rowE['Punkte'];
            if (isset($resultData[$mid])) {
                $scaled = scalePoints($pnts, $defByID[$endID]);
                $resultData[$mid]['wettbewerbe'][$endID][] = $scaled;
                $resultData[$mid]['endstichPunkte'] = ($resultData[$mid]['endstichPunkte'] ?? 0) + $scaled; // für den Gleichstand
            }
        }
    }

    // 4B) Bester Kantonalstich laden
    if ($kantiDef) {
        $kID = (int)$kantiDef['ID'];
        $sqlK = "
            SELECT
              k.MitgliedID,
              GREATEST(
                COALESCE(k.Passe1,0),
                COALESCE(k.Passe2,0),
                COALESCE(k.Passe3,0),
                COALESCE(k.Passe4,0),
                COALESCE(k.Passe5,0)
              ) AS Punkte
            FROM kantiresultate k
            WHERE k.Jahr = ?
        ";
        $stmtK = $conn->prepare($sqlK);
        $stmtK->bind_param('i', $year);
        $stmtK->execute();
        $resK = $stmtK->get_result();
        
        while ($rk = $resK->fetch_assoc()) {
            $mid  = (int)$rk['MitgliedID'];
            $pnts = (int)$rk['Punkte'];
            if (isset($resultData[$mid])) {
                $scaled = scalePoints($pnts, $defByID[$kID]);
                $resultData[$mid]['wettbewerbe'][$kID][] = $scaled;
            }
        }
    }

    // 5) jmresultate laden + hochrechnen + Nicht-Teilnahmen berücksichtigen
    if (count($definitionIDs) > 0) {
        $idList = implode(',', $definitionIDs);
        $sqlR = "
            SELECT
                jm.mitgliederID AS mid,
                jm.jmdefinitionID AS defID,
                jm.Punkte
            FROM jmresultate jm
            WHERE jm.jmdefinitionID IN ($idList)
        ";
        $resR = $conn->query($sqlR);
        
        // Sammle alle tatsächlichen Teilnahmen
        $tatsaechlicheTeilnahmen = [];
        if ($resR) {
            while ($rr = $resR->fetch_assoc()) {
                $mid   = (int)$rr['mid'];
                $defID = (int)$rr['defID'];
                $raw   = (int)$rr['Punkte'];
                if (isset($resultData[$mid])) {
                    $scaled = scalePoints($raw, $defByID[$defID]);
                    $resultData[$mid]['wettbewerbe'][$defID][] = $scaled;
                    $tatsaechlicheTeilnahmen[$mid][] = $defID;
                }
            }
        }
        
        // Ermittle welche Streicher-Wettbewerbe überhaupt Resultate haben
        $activeStreicherIDs = [];
        foreach ($definitions as $def) {
            $defID = (int)$def['ID'];
            $isStreicher = ((int)$def['Streicher'] === 1);
            
            if ($isStreicher) {
                // Prüfe ob dieser Wettbewerb überhaupt Resultate hat
                foreach ($tatsaechlicheTeilnahmen as $teilnahmen) {
                    if (in_array($defID, $teilnahmen)) {
                        $activeStreicherIDs[] = $defID;
                        break;
                    }
                }
            }
        }
        
        // Füge Nicht-Teilnahmen nur für aktive Streicher-Wettbewerbe hinzu
        foreach ($resultData as $mid => &$mData) {
            foreach ($activeStreicherIDs as $defID) {
                // Nur wenn nicht teilgenommen
                if (!isset($tatsaechlicheTeilnahmen[$mid]) || !in_array($defID, $tatsaechlicheTeilnahmen[$mid])) {
                    // Nicht-Teilnahme als 0-Punkte-Resultat hinzufügen
                    $mData['wettbewerbe'][$defID][] = 0;
                }
            }
        }
        unset($mData);
    }

    // 6) Sektionsmeisterschaft + streicher=1
    $sektionsmeisterschaftID = null;
    foreach ($definitions as $d) {
        if ($d['Bezeichnung'] === 'Sektionsmeisterschaft') {
            $sektionsmeisterschaftID = $d['ID'];
            break;
        }
    }
    $streicher1IDs = [];
    foreach ($definitions as $d) {
        if ((int)$d['Streicher'] === 1) {
            $streicher1IDs[] = $d['ID'];
        }
    }

    // 7) Summen & 3 tiefste Streicher
    foreach ($resultData as $mid => &$mData) {
        $sumStr0 = 0;
        $str1Vals= [];

        foreach ($definitions as $def) {
            $dID = (int)$def['ID'];
            $is1 = ((int)$def['Streicher'] === 1);

            if (empty($mData['wettbewerbe'][$dID])) {
                continue;
            }
            $allPoints = $mData['wettbewerbe'][$dID];

            // Nur höchster Wert Sektionsmeisterschaft
            if ($sektionsmeisterschaftID && $dID == $sektionsmeisterschaftID) {
                $allPoints = [max($allPoints)];
                $mData['wettbewerbe'][$dID] = $allPoints;
            }

            if ($is1) {
                foreach ($allPoints as $p) {
                    $str1Vals[] = ['defID' => $dID, 'punkte' => $p];
                }
            } else {
                $sumStr0 += array_sum($allPoints);
            }
        }

        usort($str1Vals, fn($a,$b) => $a['punkte'] <=> $b['punkte']);
        $gestr = array_slice($str1Vals, 0, $anzahlStreicher);
        $verwd = array_slice($str1Vals, $anzahlStreicher);

        $sumStr1 = array_sum(array_column($verwd, 'punkte'));
        $mData['sumStreicher0'] = $sumStr0;
        $mData['sumStreicher1'] = $sumStr1;
        $mData['sumTotal']      = $sumStr0 + $sumStr1;

        // Gekennzeichnet (gestrichen => true)
        $gmap = [];
        foreach ($gestr as $g) {
            $key = $g['defID'].'|'.$g['punkte'];
            if (!isset($gmap[$key])) {
                $gmap[$key] = 0;
            }
            $gmap[$key]++;
        }

        foreach ($mData['wettbewerbe'] as $dID => &$pArr) {
            if (!in_array($dID, $streicher1IDs)) {
                foreach ($pArr as $ix => $val) {
                    if (!is_array($val)) {
                        $pArr[$ix] = ['punkte'=>$val,'strichen'=>false];
                    }
                }
            } else {
                foreach ($pArr as $ix => $val) {
                    $key = $dID.'|'.$val;
                    if (isset($gmap[$key]) && $gmap[$key]>0) {
                        $pArr[$ix] = ['punkte'=>$val,'strichen'=>true];
                        $gmap[$key]--;
                    } else {
                        $pArr[$ix] = ['punkte'=>$val,'strichen'=>false];
                    }
                }
            }
        }
    }
    unset($mData);

    // 8) Sortierung absteigend; bei gleichem Total vorne, wer im Endstich mehr hat (Entscheid 14.10.2026)
    usort($resultData, fn($a,$b)=> ($b['sumTotal'] <=> $a['sumTotal']) ?: (($b['endstichPunkte'] ?? 0) <=> ($a['endstichPunkte'] ?? 0)));

    // 9) HTML-Tabelle mit verbessertem Layout und Rotation
    $html = '<h2>' . ($kategorie === 'Kat. A' ? 'Kat. A (Standardgewehr)' : 'Kat. B (Ordonnanzwaffen)') . '</h2>';
    
    // Kopfhöhe dynamisch an die längste Bezeichnung anpassen, damit die rotierten
    // Labels vollständig in den Kopf passen und nicht nach oben in den Titel ausbrechen.
    $maxLen = 1;
    foreach ($definitions as $d) {
        $b = trim($d['Bezeichnung']);
        $len = function_exists('mb_strlen') ? mb_strlen($b) : strlen($b);
        if ($len > $maxLen) { $maxLen = $len; }
    }
    $headFont = 8; // px
    $headH    = min(230, max(96, (int)ceil($maxLen * $headFont * 0.62) + 12)); // Textlänge + Polster

    // Volle Seitenbreite über PROZENTUALE Spaltenbreiten im normalen Auto-Layout
    // (Dompdf setzt das zuverlässig um; Namen werden nie abgeschnitten). Rang 4% +
    // Name 13% + Total 5%, die Resultat-Spalten teilen sich die restlichen 78%.
    $nCols  = count($definitions);
    $colPct = $nCols > 0 ? round(78 / $nCols, 4) : 78;

    $html .= '<table class="table jm" style="width: 100%;">';
    $html .= '<thead><tr style="height: ' . $headH . 'px;">';
    $html .= '<th style="width: 4%; vertical-align: bottom; text-align: right;">Rang</th>';
    // Name-Spalte: schmal, aber breit genug für die längsten Namen auf einer Zeile
    $html .= '<th style="width: 13%; text-align: left; vertical-align: bottom; padding-right: 8px;">Name</th>';

    // Spaltenüberschriften mit 90-Grad-Rotation - OHNE KÜRZUNGEN; gleichmässig verteilt
    foreach ($definitions as $d) {
        $bez = trim($d['Bezeichnung']);

        // Rotation mit div-Container; Kopf hoch genug für volle Bezeichnung
        $html .= '<th class="result-col" style="width: ' . $colPct . '%; height: ' . $headH . 'px; vertical-align: bottom; position: relative; padding: 0;">';
        $html .= '<div style="position: absolute; bottom: 6px; left: 19px; transform: rotate(-90deg); transform-origin: left bottom; white-space: nowrap; font-size: ' . $headFont . 'px; width: ' . ($headH - 12) . 'px;">';
        $html .= htmlspecialchars($bez);
        $html .= '</div>';
        $html .= '</th>';
    }

    $html .= '<th style="width: 5%; vertical-align: bottom; text-align: right;">Total</th>';
    $html .= '</tr></thead><tbody>';

    // Inhalt
    $actualPos = 0;
    $currRank  = 0;
    $prevScore = null;
    $prevEndstich = null;
    $nachPodest = 0;

    foreach ($resultData as $entry) {
        $actualPos++;
        $sumTotal = $entry['sumTotal'];
        $endstich = (float)($entry['endstichPunkte'] ?? 0);

        // Geteilter Rang nur bei gleichem Total UND gleichem Endstich
        if ($sumTotal !== $prevScore || $endstich !== $prevEndstich) {
            $currRank = $actualPos;
            $prevScore= $sumTotal;
            $prevEndstich = $endstich;
        }

        $m = $entry['mitglied'];
        $fullname = htmlspecialchars($m['Name'].' '.$m['Vorname']);

        // Rang 1–3 fett, vor der ersten Zeile danach etwas Abstand (bei Gleichstand teilen sich Zeilen einen Rang)
        $html .= pdf_rangliste_zeile($currRank <= 3 ? $currRank : ($nachPodest++ === 0 ? 4 : 5));
        $html .= "<td class='rang'>$currRank.</td>";
        $html .= "<td class='name'>$fullname</td>";

        foreach ($definitions as $def) {
            $dID = $def['ID'];
            // Nachkommastellen nur bei hochgerechneten Resultaten; rohe Resultate ganzzahlig
            $dec = isScaledDef($def) ? 2 : 0;
            $cellContent = '–';

            if (!empty($entry['wettbewerbe'][$dID])) {
                $pointArr = $entry['wettbewerbe'][$dID];
                $vals     = [];
                foreach ($pointArr as $pItem) {
                    $pVal = number_format($pItem['punkte'], $dec, '.', '');
                    $vals[] = $pItem['strichen'] ? "<span class='struck'>$pVal</span>" : $pVal;
                }
                $cellContent = implode(', ', $vals);
            }
            $html .= "<td class='result-col'>$cellContent</td>";
        }

        // Total: einheitlich zwei Nachkommastellen
        $html .= "<td class='total'>" . number_format($sumTotal, 2, '.', '') . "</td>";
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $html .= '<p class="rl-hinweis"><span class="struck">96.00</span> = Streicher: die '
           . $anzahlStreicher . ' schlechtesten Resultate zählen nicht. – = nicht teilgenommen.</p>';
    return $html;
}

// Wird dieser Wettbewerb auf 100 hochgerechnet? Bestimmt auch die Anzeige:
// nur hochgerechnete Resultate (und Totale) erhalten Nachkommastellen.
function isScaledDef($def) {
    // Keine Hochrechnung für diese speziellen Wettbewerbe
    if (in_array($def['Bezeichnung'], ['Einzelwettschiessen', 'Obligatorisch', 'Feldschiessen'])) {
        return false;
    }
    // Hochrechnung nur wenn Maxpunkte < 100; Stiche mit Maxpunkte > 100 (z.B. 120)
    // zaehlen mit dem effektiven Resultat (analog scalePoints in load_jm.php)
    $maxP = (int)$def['Maxpunkte'];
    return $maxP > 0 && $maxP < 100;
}

// Hilfsfunktion: Skaliert Punkte auf 100er-Skala
function scalePoints($points, $def) {
    if (!isScaledDef($def)) {
        return $points;
    }
    return round(($points * 100) / (int)$def['Maxpunkte'], 2);
}