<?php
// inc/cuprang/generate_cup_pdf.php
// VERSION 6.0 - Mit Tiefschuss (klein) und fettem Resultat

use Dompdf\Dompdf;
use Dompdf\Options;

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Includes
    require '../vendor/autoload.php';
    include '../config.php';
    
    if (!isset($conn) && function_exists('get_db_connection')) {
        $conn = get_db_connection();
    }
    
    require_once __DIR__ . '/cup_repository.php';
    require_once __DIR__ . '/cup_logic.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception("Keine DB-Verbindung verfügbar.");
    }
    
    // Jahr
    $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
    
    // Daten laden
    $pairs = cup_fetch_pairs($conn, $selectedYear);
    $finalRaw = cup_fetch_final_results($conn, $selectedYear);
    $standRaw = cup_fetch_standcup_final($conn, $selectedYear);
    
    // Namen für Paarungen laden
    $allIds = [];
    foreach ($pairs as $p) {
        if (!empty($p['Participant1'])) $allIds[] = (int)$p['Participant1'];
        if (!empty($p['Participant2'])) $allIds[] = (int)$p['Participant2'];
        if (!empty($p['Participant3'])) $allIds[] = (int)$p['Participant3'];
    }
    $names = get_member_names_bulk($conn, array_unique($allIds));
    
    // Runden
    $rounds = array_values(array_unique(array_map(fn($r) => (int)$r['Round'], $pairs)));
    sort($rounds, SORT_ASC);
    
    // Finale Rangliste vorbereiten (MIT Tiefschuss!)
    $final = [];
    foreach ($finalRaw as $r) {
        if (empty($r['Teilnehmer']) && !empty($r['ParticipantID'])) {
            $r['Teilnehmer'] = get_member_name($conn, (int)$r['ParticipantID']);
        }
        if (!isset($r['Punkte']) && isset($r['Result'])) {
            $r['Punkte'] = $r['Result'];
        }
        if (!isset($r['Tiefschuss']) && isset($r['LowShot'])) {
            $r['Tiefschuss'] = $r['LowShot'];
        }
        $final[] = $r;
    }
    
    // Sortieren nach Punkte UND Tiefschuss
    usort($final, function($a, $b) {
        $pa = (int)($a['Punkte'] ?? 0);
        $pb = (int)($b['Punkte'] ?? 0);
        if ($pb !== $pa) return $pb <=> $pa;
        // Bei gleichen Punkten: höherer Tiefschuss gewinnt
        return ((int)($b['Tiefschuss'] ?? 0)) <=> ((int)($a['Tiefschuss'] ?? 0));
    });
    
    $rankedFinal = [];
    $rank = 0;
    $i = 0;
    $prev = null;
    foreach ($final as $r) {
        $i++;
        $key = $r['Punkte'] . '|' . $r['Tiefschuss'];
        if ($key !== $prev) {
            $rank = $i;
            $prev = $key;
        }
        $r['Rang'] = $rank;
        $rankedFinal[] = $r;
    }
    
    // Standcup vorbereiten
    $stand = [];
    foreach ($standRaw as $r) {
        if (!isset($r['Punkte']) && isset($r['Result'])) {
            $r['Punkte'] = $r['Result'];
        }
        if (!isset($r['club']) && isset($r['Club'])) {
            $r['club'] = $r['Club'];
        }
        if (empty($r['ParticipantName'])) {
            $r['ParticipantName'] = 'Unbekannt';
        }
        $stand[] = $r;
    }
    
    usort($stand, fn($a, $b) => ((int)($b['Punkte'] ?? 0)) <=> ((int)($a['Punkte'] ?? 0)));
    $i = 0;
    $rank = 0;
    $prev = null;
    foreach ($stand as &$r) {
        $i++;
        $punkte = (int)($r['Punkte'] ?? 0);
        if ($punkte !== $prev) {
            $rank = $i;
            $prev = $punkte;
        }
        $r['_rank'] = $rank;
    }
    unset($r);
    
    // HTML erstellen - Professionelles Layout
    $html = '<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<style>
@page { 
    margin: 1.5cm 1.5cm 2cm 1.5cm;
    size: A4;
}
body { 
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; 
    font-size: 10px; 
    margin: 0; 
    padding: 0;
    color: #333;
}

/* Header */
.header { 
    position: relative; 
    margin-bottom: 140px;  /* Mehr Abstand nach unten zum Inhalt */
    padding-bottom: 10px;
    
}
.logo { 
    position: absolute; 
    top: 0;  /* Logo bleibt oben */
    left: 0; 
    width: 80px; 
    height: auto; 
}
h1 { 
    text-align: center; 
    font-size: 24px; 
    margin: 10px 0 0 0;  /* Titel bleibt wo er war */
    color: #000000ff;
    font-weight: bold;
}

/* Sections */
.section { 
    margin-bottom: 15px; 
    page-break-inside: avoid;   
}
h2 { 
    font-size: 12px;
    margin: 10px 0 5px 0;
    color: #1e3a8a;
    font-weight: bold;
}
h3 {
    font-size: 12px;
    margin: 10px 0 5px 0;
    color: #1e3a8a;
    font-weight: bold;
}

/* Tabellen */
table { 
    width: 100%; 
    border-collapse: collapse; 
    margin-top: 8px;
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
thead th { 
    background: #f8f9fa;
    padding: 6px 8px; 
    text-align: left; 
    border-bottom: 2px solid #dee2e6;
    font-weight: bold;
    font-size: 10px;
    color: #495057;
}
tbody td { 
    padding: 5px 8px; 
    border-bottom: 1px solid #e9ecef;
}
tbody tr:hover {
    background: #f8f9fa;
}

/* Spalten */
.rank { 
    width: 40px; 
    text-align: center; 
    font-weight: bold;
    font-size: 11px;
}
.name { 
    width: auto;
    font-size: 10px;
}
.points { 
    width: 50px; 
    text-align: center; 
    font-weight: bold;
    font-size: 11px;
    color: #1e3a8a;
}
.lowshot {
    width: 50px;
    text-align: center;
    font-size: 9px;
    color: #6c757d;
}
.club { 
    display: block;
    color: #6c757d; 
    font-size: 9px;
    margin-top: 1px;
}

/* Medaillen-Farben */
.gold { 
    background: linear-gradient(to right, #fff9e6, #ffeb99) !important;
}
.gold .rank {
    color: #d4a017;
}
.silver { 
    background: linear-gradient(to right, #f5f5f5, #e8e8e8) !important;
}
.silver .rank {
    color: #71706e;
}
.bronze { 
    background: linear-gradient(to right, #fef5ed, #f5ddc4) !important;
}
.bronze .rank {
    color: #cd7f32;
}

/* Paarungen */
.pairing {
    margin: 6px 0;
    padding: 6px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: #ffffff;
}
.pairing-row {
    display: table;
    width: 100%;
    padding: 3px 0;
}
.pairing-name {
    display: table-cell;
    width: 60%;
    padding-right: 10px;
}
.pairing-score {
    display: table-cell;
    width: 20%;
    text-align: right;
    font-weight: bold;
    color: #1e3a8a;
}
.pairing-lowshot {
    display: table-cell;
    width: 20%;
    text-align: right;
    font-size: 9px;
    color: #6c757d;
}
.winner {
    font-weight: bold;
    color: #16a34a;
}
.loser {
    color: #dc2626;
    text-decoration: line-through;
}

/* Kompaktes Layout für eine Seite */
.columns {
    display: table;
    width: 100%;
    margin-top: 10px;
}
.column {
    display: table-cell;
    width: 48%;
    vertical-align: top;
}
.column:first-child {
    padding-right: 2%;
}

/* Footer */
.footer {
    text-align: center;
    font-size: 8px;
    color: #6c757d;
    margin-top: 20px;
    padding-top: 10px;
    border-top: 1px solid #dee2e6;
}
</style>
</head>
<body>

<div class="header">
    <img src="https://jahresmeisterschaft.msvwilen.ch/images/MSVWilen_Logo.jpg" class="logo" alt="MSV Wilen Logo">
    <h1>MSV Wilen Vereinscup ' . $selectedYear . '</h1>
</div>';

    // Paarungen - Kompakt mit Tiefschuss
    $html .= '<div class="section">';
    
    if (!empty($pairs)) {
        $html .= '<div class="columns">';
        
        // Aufteilen in zwei Spalten
        $halfPoint = ceil(count($rounds) / 2);
        $leftRounds = array_slice($rounds, 0, $halfPoint);
        $rightRounds = array_slice($rounds, $halfPoint);
        
        // Linke Spalte
        $html .= '<div class="column">';
        foreach ($leftRounds as $rnd) {
            $html .= '<h3>Runde ' . $rnd . '</h3>';
            $roundPairs = array_filter($pairs, fn($r) => (int)$r['Round'] === $rnd);
            
            foreach ($roundPairs as $row) {
                $p1 = (int)($row['Participant1'] ?? 0);
                $p2 = (int)($row['Participant2'] ?? 0);
                $p3 = !empty($row['Participant3']) ? (int)$row['Participant3'] : null;
                
                $n1 = $p1 ? ($names[$p1] ?? 'Mitglied #'.$p1) : '';
                $n2 = $p2 ? ($names[$p2] ?? 'Mitglied #'.$p2) : '';
                
                $r1 = $row['Result1'] ?? '';
                $r2 = $row['Result2'] ?? '';
                $l1 = $row['LowShot1'] ?? '';
                $l2 = $row['LowShot2'] ?? '';
                
                $html .= '<div class="pairing">';
                
                if ($p3) {
                    // 3er Paarung
                    $n3 = $p3 ? ($names[$p3] ?? 'Mitglied #'.$p3) : '';
                    $r3 = $row['Result3'] ?? '';
                    $l3 = $row['LowShot3'] ?? '';
                    $loser = cup_three_loser_index($row);
                    
                    $html .= '<div class="pairing-row">';
                    $html .= '<span class="pairing-name ' . ($loser === 1 ? 'loser' : 'winner') . '">' . htmlspecialchars($n1) . '</span>';
                    $html .= '<span class="pairing-score">' . htmlspecialchars($r1) . '</span>';
                    $html .= '<span class="pairing-lowshot">(' . htmlspecialchars($l1) . ')</span>';
                    $html .= '</div>';
                    
                    $html .= '<div class="pairing-row">';
                    $html .= '<span class="pairing-name ' . ($loser === 2 ? 'loser' : 'winner') . '">' . htmlspecialchars($n2) . '</span>';
                    $html .= '<span class="pairing-score">' . htmlspecialchars($r2) . '</span>';
                    $html .= '<span class="pairing-lowshot">(' . htmlspecialchars($l2) . ')</span>';
                    $html .= '</div>';
                    
                    $html .= '<div class="pairing-row">';
                    $html .= '<span class="pairing-name ' . ($loser === 3 ? 'loser' : 'winner') . '">' . htmlspecialchars($n3) . '</span>';
                    $html .= '<span class="pairing-score">' . htmlspecialchars($r3) . '</span>';
                    $html .= '<span class="pairing-lowshot">(' . htmlspecialchars($l3) . ')</span>';
                    $html .= '</div>';
                } else {
                    // 2er Paarung
                    $winner = cup_winner_index($row);
                    
                    $html .= '<div class="pairing-row">';
                    $html .= '<span class="pairing-name ' . ($winner === 1 ? 'winner' : ($winner === 2 ? 'loser' : '')) . '">' . htmlspecialchars($n1) . '</span>';
                    $html .= '<span class="pairing-score">' . htmlspecialchars($r1) . '</span>';
                    $html .= '<span class="pairing-lowshot">(' . htmlspecialchars($l1) . ')</span>';
                    $html .= '</div>';
                    
                    $html .= '<div class="pairing-row">';
                    $html .= '<span class="pairing-name ' . ($winner === 2 ? 'winner' : ($winner === 1 ? 'loser' : '')) . '">' . htmlspecialchars($n2) . '</span>';
                    $html .= '<span class="pairing-score">' . htmlspecialchars($r2) . '</span>';
                    $html .= '<span class="pairing-lowshot">(' . htmlspecialchars($l2) . ')</span>';
                    $html .= '</div>';
                }
                
                $html .= '</div>';
            }
        }
        $html .= '</div>';
        
        // Rechte Spalte
        $html .= '<div class="column">';
        foreach ($rightRounds as $rnd) {
            $html .= '<h3>Runde ' . $rnd . '</h3>';
            $roundPairs = array_filter($pairs, fn($r) => (int)$r['Round'] === $rnd);
            
            foreach ($roundPairs as $row) {
                $p1 = (int)($row['Participant1'] ?? 0);
                $p2 = (int)($row['Participant2'] ?? 0);
                $p3 = !empty($row['Participant3']) ? (int)$row['Participant3'] : null;
                
                $n1 = $p1 ? ($names[$p1] ?? 'Mitglied #'.$p1) : '';
                $n2 = $p2 ? ($names[$p2] ?? 'Mitglied #'.$p2) : '';
                
                $r1 = $row['Result1'] ?? '';
                $r2 = $row['Result2'] ?? '';
                $l1 = $row['LowShot1'] ?? '';
                $l2 = $row['LowShot2'] ?? '';
                
                $html .= '<div class="pairing">';
                
                if ($p3) {
                    $n3 = $p3 ? ($names[$p3] ?? 'Mitglied #'.$p3) : '';
                    $r3 = $row['Result3'] ?? '';
                    $l3 = $row['LowShot3'] ?? '';
                    $loser = cup_three_loser_index($row);
                    
                    $html .= '<div class="pairing-row">';
                    $html .= '<span class="pairing-name ' . ($loser === 1 ? 'loser' : 'winner') . '">' . htmlspecialchars($n1) . '</span>';
                    $html .= '<span class="pairing-score">' . htmlspecialchars($r1) . '</span>';
                    $html .= '<span class="pairing-lowshot">(' . htmlspecialchars($l1) . ')</span>';
                    $html .= '</div>';
                    
                    $html .= '<div class="pairing-row">';
                    $html .= '<span class="pairing-name ' . ($loser === 2 ? 'loser' : 'winner') . '">' . htmlspecialchars($n2) . '</span>';
                    $html .= '<span class="pairing-score">' . htmlspecialchars($r2) . '</span>';
                    $html .= '<span class="pairing-lowshot">(' . htmlspecialchars($l2) . ')</span>';
                    $html .= '</div>';
                    
                    $html .= '<div class="pairing-row">';
                    $html .= '<span class="pairing-name ' . ($loser === 3 ? 'loser' : 'winner') . '">' . htmlspecialchars($n3) . '</span>';
                    $html .= '<span class="pairing-score">' . htmlspecialchars($r3) . '</span>';
                    $html .= '<span class="pairing-lowshot">(' . htmlspecialchars($l3) . ')</span>';
                    $html .= '</div>';
                } else {
                    $winner = cup_winner_index($row);
                    
                    $html .= '<div class="pairing-row">';
                    $html .= '<span class="pairing-name ' . ($winner === 1 ? 'winner' : ($winner === 2 ? 'loser' : '')) . '">' . htmlspecialchars($n1) . '</span>';
                    $html .= '<span class="pairing-score">' . htmlspecialchars($r1) . '</span>';
                    $html .= '<span class="pairing-lowshot">(' . htmlspecialchars($l1) . ')</span>';
                    $html .= '</div>';
                    
                    $html .= '<div class="pairing-row">';
                    $html .= '<span class="pairing-name ' . ($winner === 2 ? 'winner' : ($winner === 1 ? 'loser' : '')) . '">' . htmlspecialchars($n2) . '</span>';
                    $html .= '<span class="pairing-score">' . htmlspecialchars($r2) . '</span>';
                    $html .= '<span class="pairing-lowshot">(' . htmlspecialchars($l2) . ')</span>';
                    $html .= '</div>';
                }
                
                $html .= '</div>';
            }
        }
        $html .= '</div>';
        
        $html .= '</div>'; // columns
    }
    
    $html .= '</div>'; // section
    
    // Ranglisten nebeneinander
    $html .= '<div class="columns">';
    
    // Finale Rangliste MIT Tiefschuss
    $html .= '<div class="column">';
    $html .= '<h2>MSV Wilen Final</h2>';
    
    if (!empty($rankedFinal)) {
        $html .= '<table>';
        $html .= '<tbody>';
        
        foreach ($rankedFinal as $r) {
            $rowClass = '';
            if ($r['Rang'] == 1) $rowClass = 'gold';
            elseif ($r['Rang'] == 2) $rowClass = 'silver';
            elseif ($r['Rang'] == 3) $rowClass = 'bronze';
            
            $html .= '<tr class="' . $rowClass . '">';
            $html .= '<td class="rank">' . $r['Rang'] . '.</td>';
            $html .= '<td class="name">' . htmlspecialchars($r['Teilnehmer'] ?? '') . '</td>';
            $html .= '<td class="points">' . htmlspecialchars($r['Punkte'] ?? '') . '</td>';
            $html .= '<td class="lowshot">' . htmlspecialchars($r['Tiefschuss'] ?? '') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
    }
    
    $html .= '</div>';
    
    // Standcup Final
    $html .= '<div class="column">';
    $html .= '<h2>Standcup Final</h2>';
    
    if (!empty($stand)) {
        $html .= '<table>';
        $html .= '<tbody>';
        
        foreach ($stand as $r) {
            $rowClass = '';
            if ($r['_rank'] == 1) $rowClass = 'gold';
            elseif ($r['_rank'] == 2) $rowClass = 'silver';
            elseif ($r['_rank'] == 3) $rowClass = 'bronze';
            
            $html .= '<tr class="' . $rowClass . '">';
            $html .= '<td class="rank">' . $r['_rank'] . '.</td>';
            $html .= '<td class="name">';
            $html .= htmlspecialchars($r['ParticipantName'] ?? 'Unbekannt');
            if (!empty($r['club'])) {
                $html .= '<span class="club">' . htmlspecialchars($r['club']) . '</span>';
            }
            $html .= '</td>';
            $html .= '<td class="points">' . htmlspecialchars($r['Punkte'] ?? '') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
    }
    
    $html .= '</div>';
    $html .= '</div>'; // columns
    
    // Footer
    $html .= '<div class="footer">';
    $html .= 'MSV Wilen - Generiert am ' . date('d.m.Y \u\m H:i') . ' Uhr';
    $html .= '</div>';
    
    $html .= '</body></html>';
    
    // PDF generieren
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Verzeichnis prüfen
    if (!is_dir('dat')) {
        if (!mkdir('dat', 0755, true)) {
            throw new Exception("Konnte Verzeichnis nicht erstellen");
        }
    }
    
    // PDF speichern
    $date = new DateTime();
    $pdfFilePath = 'dat/cup_' . $selectedYear . '_' . $date->format('Y-m-d_H-i-s') . '.pdf';
    
    if (!file_put_contents($pdfFilePath, $dompdf->output())) {
        throw new Exception("Konnte PDF nicht speichern");
    }
    
    // Ausgabepuffer leeren
    ob_end_clean();
    
    // JSON-Antwort
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'pdf_link' => "/inc/cuprang/" . $pdfFilePath
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Cup PDF-Generator Fehler: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'pdf_link' => null
    ]);
}
