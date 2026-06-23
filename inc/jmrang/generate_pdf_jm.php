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

// Session-Kontrolle
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1) GET-Parameter: Jahr
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// 2) HTML-Kopf zusammenbauen mit Logo
$htmlOutput = $header; 
$htmlOutput .= "<title>Jahresmeisterschaft $selectedYear</title>";
$htmlOutput .= "</head>\n<body>\n";

// Logo und Header
$logoPath = '../images/MSVWilen_Logo.jpg'; // Pfad anpassen falls nötig
$htmlOutput .= '<div class="pdf-header">';
if (file_exists($logoPath)) {
    $htmlOutput .= '<div class="logo-container">';
    $htmlOutput .= '<img src="' . imgToBase64($logoPath) . '" class="logo" alt="MSV Wilen Logo">';
    $htmlOutput .= '</div>';
}
$htmlOutput .= '<div class="header-text">';
$htmlOutput .= "<h1>Jahresmeisterschaft $selectedYear</h1>";
$htmlOutput .= '</div>';
$htmlOutput .= '</div>';

// 3) Ranglisten erzeugen: Kat. A / Kat. B
$htmlOutput .= buildJMRangliste($selectedYear, 'Kat. A', $conn);
$htmlOutput .= "<div class='page-break'></div>";

// Header für zweite Seite wiederholen
$htmlOutput .= '<div class="pdf-header">';
if (file_exists($logoPath)) {
    $htmlOutput .= '<div class="logo-container">';
    $htmlOutput .= '<img src="' . imgToBase64($logoPath) . '" class="logo" alt="MSV Wilen Logo">';
    $htmlOutput .= '</div>';
}
$htmlOutput .= '<div class="header-text">';
$htmlOutput .= "<h1>Jahresmeisterschaft $selectedYear</h1>";
$htmlOutput .= '</div>';
$htmlOutput .= '</div>';

$htmlOutput .= buildJMRangliste($selectedYear, 'Kat. B', $conn);

// 4) Footer
$htmlOutput .= $footer;

// PDF-Erzeugung
try {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $options->set('isPhpEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->loadHtml($htmlOutput);
    $dompdf->render();

    // Canvas holen für Footer - NACH render()!
    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->get_font("Arial", "normal");
    
    // Footer auf jeder Seite hinzufügen
    $canvas->page_text(20, $canvas->get_height() - 25, 
        "Erstellt am: " . date('d.m.Y H:i') . " Uhr", 
        $font, 7, array(0, 0, 0));
    
    $canvas->page_text($canvas->get_width() / 2 - 50, $canvas->get_height() - 25, 
        "MSV Wilen - Jahresmeisterschaft", 
        $font, 7, array(0, 0, 0));
    
    $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 25, 
        "Seite {PAGE_NUM} von {PAGE_COUNT}", 
        $font, 7, array(0, 0, 0));

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
    echo json_encode(['message' => 'PDF-Generierung fehlgeschlagen: ' . $e->getMessage()]);
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
        $gestr = array_slice($str1Vals, 0, 3);
        $verwd = array_slice($str1Vals, 3);

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

    // 8) Sortierung absteigend
    usort($resultData, fn($a,$b)=> $b['sumTotal'] <=> $a['sumTotal']);

    // 9) HTML-Tabelle mit verbessertem Layout und Rotation
    $html = '<div class="container">';
    $html .= '<h2>Kategorie ' . str_replace('Kat. ', '', $kategorie) . '</h2>';
    
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

    $html .= '<table class="table" style="width: 100%;">';
    $html .= '<thead><tr style="height: ' . $headH . 'px;">';
    $html .= '<th style="width: 4%; vertical-align: bottom; text-align: center;">Rang</th>';
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

    foreach ($resultData as $entry) {
        $actualPos++;
        $sumTotal = $entry['sumTotal'];

        if ($sumTotal !== $prevScore) {
            $currRank = $actualPos;
            $prevScore= $sumTotal;
        }

        $m = $entry['mitglied'];
        $fullname = htmlspecialchars($m['Name'].' '.$m['Vorname']);

        $html .= "<tr>";
        
        // Rang - mit Farbe für erste 3
        if ($currRank <= 3) {
            $rankClass = "rank-$currRank";
            $html .= "<td style='text-align: center;' class='$rankClass'><strong>$currRank</strong></td>";
            $html .= "<td style='text-align: left; font-size: 11px; font-weight: bold; white-space: nowrap; padding-right: 8px;'>$fullname</td>";
        } else {
            $html .= "<td style='text-align: center;'><strong>$currRank</strong></td>";
            $html .= "<td style='text-align: left; font-size: 11px; white-space: nowrap; padding-right: 8px;'>$fullname</td>";
        }

        foreach ($definitions as $def) {
            $dID = $def['ID'];
            $cellContent = '<span class="no-data">-</span>';

            if (!empty($entry['wettbewerbe'][$dID])) {
                $pointArr = $entry['wettbewerbe'][$dID];
                $vals     = [];
                foreach ($pointArr as $pItem) {
                    // Ganzzahlige (nicht hochgerechnete) Resultate ohne Nachkommastellen
                    $pDec = (abs($pItem['punkte'] - round($pItem['punkte'])) < 0.005) ? 0 : 2;
                    $pVal = number_format($pItem['punkte'], $pDec, '.', '');
                    if ($pItem['strichen']) {
                        $vals[] = "<span class='struck'>$pVal</span>";
                    } else {
                        // Erste 3 Ränge fett
                        if ($currRank <= 3) {
                            $vals[] = "<span class='value' style='font-weight: bold;'>$pVal</span>";
                        } else {
                            $vals[] = "<span class='value'>$pVal</span>";
                        }
                    }
                }
                $cellContent = implode(', ', $vals);
            }
            $html .= "<td class='result-col'>$cellContent</td>";
        }
        
        // Total - erste 3 Ränge fett; ganzzahlig ohne Nachkommastellen
        $totDec = (abs($sumTotal - round($sumTotal)) < 0.005) ? 0 : 2;
        $totStr = number_format($sumTotal, $totDec, '.', '');
        if ($currRank <= 3) {
            $html .= "<td style='font-weight: bold;'><strong>$totStr</strong></td>";
        } else {
            $html .= "<td><strong>$totStr</strong></td>";
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    
    // Legende mit Hinweis - NUR GRAUER BEREICH
    $html .= '<div class="legend">';
    $html .= '<span class="legend-item"><span class="struck">00.00</span> = Streichresultat (nicht gewertet)</span>';
    $html .= '<span class="legend-item"><strong>-</strong> = Nicht teilgenommen</span>';
    $html .= '<span class="legend-item">Die roten durchgestrichenen Werte sind die 3 schlechtesten Resultate (Streicher) und werden nicht in die Gesamtwertung einbezogen</span>';
    $html .= '</div>';
    
    $html .= '</div>';
    return $html;
}

// Hilfsfunktion: Skaliert Punkte auf 100er-Skala
function scalePoints($points, $def) {
    // Keine Hochrechnung für diese speziellen Wettbewerbe
    if (in_array($def['Bezeichnung'], ['Einzelwettschiessen', 'Obligatorisch', 'Feldschiessen'])) {
        return $points;
    }
    
    $maxP = (int)$def['Maxpunkte'];
    if ($maxP > 0 && $maxP != 100) {
        return round(($points * 100) / $maxP, 2);
    }
    return $points;
}