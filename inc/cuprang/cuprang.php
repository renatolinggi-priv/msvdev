<?php
// rangcup.php
include '../config.php';
require '../vendor/autoload.php';
//require '../functions.inc.php';
//include 'config_pdf.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Jahr aus GET-Parameter oder aktuelles Jahr
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Ausgabepuffer starten
ob_start();

// Funktion zum Konvertieren eines Bildes in Base64
function imgToBase64($imgPath) {
    if (!file_exists($imgPath)) {
        return '';
    }
    $imageData = base64_encode(file_get_contents($imgPath));
    $src = 'data:' . mime_content_type($imgPath) . ';base64,' . $imageData;
    return $src;
}

// Funktion, um Teilnehmernamen anhand der ID abzurufen
function getParticipantName($conn, $id) {
    $stmt = $conn->prepare("SELECT CONCAT(Name, ' ', Vorname) as FullName FROM mitglieder WHERE ID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($fullName);
    $stmt->fetch();
    $stmt->close();
    return $fullName ?: 'Unbekannt';
}

// Funktion zur Ermittlung der Gewinner bei 3er-Paarungen
// Berücksichtigt Advancers (1 oder 2 kommen weiter) und ManualWinner
// (positiv = Gewinner, negativ = ausgeschiedener bei Dreier-Gleichstand)
function getThreeWayWinners($row) {
    $advancers = (isset($row['Advancers']) && (int)$row['Advancers'] === 1) ? 1 : 2;
    $manual = !empty($row['ManualWinner']) ? (int)$row['ManualWinner'] : 0;

    $participants = [
        ['id' => (int)$row['Participant1'], 'result' => $row['Result1'], 'lowshot' => $row['LowShot1']],
        ['id' => (int)$row['Participant2'], 'result' => $row['Result2'], 'lowshot' => $row['LowShot2']],
        ['id' => (int)$row['Participant3'], 'result' => $row['Result3'], 'lowshot' => $row['LowShot3']]
    ];

    // Sortiere nach Ergebnis (absteigend) und dann nach Tiefschuss (absteigend)
    usort($participants, function($a, $b) {
        if ($a['result'] == $b['result']) {
            return $b['lowshot'] - $a['lowshot'];
        }
        return $b['result'] - $a['result'];
    });

    $ids = array_column($participants, 'id');

    if ($advancers === 1) {
        $winners = ($manual > 0) ? [$manual] : [$ids[0]];
    } else {
        if ($manual < 0) {
            $loserId = abs($manual);
            $winners = array_values(array_filter($ids, function($id) use ($loserId) { return $id !== $loserId; }));
        } elseif ($manual > 0) {
            $winners = [$manual];
            foreach ($ids as $id) {
                if ($id !== $manual && count($winners) < 2) { $winners[] = $id; }
            }
        } else {
            $winners = [$ids[0], $ids[1]];
        }
    }

    return ['winners' => $winners];
}

// Logo und Header vorbereiten
$logoPath = 'dat/MSVWilen_Logo.jpg';
$logoBase64 = file_exists($logoPath) ? imgToBase64($logoPath) : '';

$header = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            margin: 0;
            padding: 0;
        }
        .container {
            margin: 5px;
            padding: 1px;
        }
        h1 {
            text-align: center;
            font-size: 16px;
            margin-bottom: 20px;
        }
        h2 {
            margin-top: 20px;
            font-size: 12px;
            color: #333;
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .header img {
            max-width: 100px;
            margin-right: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table th, .table td {
            vertical-align: middle;
            padding: 5px;
            border: 1px solid #000;
            font-size: 9px;
        }
        .table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .strike-through {
            text-decoration: line-through;
            color: #999;
        }
        .winner {
            font-weight: bold;
        }
        .manual-winner {
            background-color: #fff3cd;
        }
        .empty-row {
            height: 10px;
        }
        .empty-row td {
            border: none !important;
            padding: 0;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8px;
            padding: 10px 0;
            border-top: 1px solid #000;
            background-color: white;
        }
        .page-break {
            page-break-after: always;
        }
        .final-round {
            margin-top: 30px;
        }
        .standcup-final {
            margin-top: 30px;
        }
    </style>
</head>
<body>';

// Begin des HTML-Inhalts für das PDF
$html = $header;
$html .= '<div class="container">';
$html .= '<div class="header">';
if ($logoBase64) {
    $html .= '<img src="' . $logoBase64 . '" alt="Logo">';
}
$html .= '<h1>CUP Ergebnisse ' . $year . '</h1>';
$html .= '</div>';

// Datenbankabfrage für Runde 1 und 2
$pairsQuery = "SELECT * FROM cupPairs WHERE Year = ? ORDER BY Round, ID";
$stmt = $conn->prepare($pairsQuery);
$stmt->bind_param("i", $year);
$stmt->execute();
$pairsResult = $stmt->get_result();

// Anzeige der Ergebnisse für Runde 1 und 2
$currentRound = 0;
while ($row = $pairsResult->fetch_assoc()) {
    if ($currentRound != $row['Round']) {
        if ($currentRound != 0) {
            $html .= '</table>';
        }
        $currentRound = $row['Round'];
        $html .= '<h2>Runde ' . $currentRound . '</h2>';
        $html .= '<table class="table">';
        $html .= '<tr>';
        $html .= '<th style="text-align: left;">Teilnehmer</th>';
        $html .= '<th>Resultat</th>';
        $html .= '<th>Tiefschuss</th>';
        $html .= '<th style="text-align: left;">Teilnehmer</th>';
        $html .= '<th>Resultat</th>';
        $html .= '<th>Tiefschuss</th>';
        $html .= '</tr>';
    }

    $participant1 = getParticipantName($conn, $row['Participant1']);
    $participant2 = getParticipantName($conn, $row['Participant2']);
    $participant3 = $row['Participant3'] ? getParticipantName($conn, $row['Participant3']) : null;

    // Prüfe auf manuellen Gewinner
    $hasManualWinner = !empty($row['ManualWinner']);
    $manualWinnerClass = $hasManualWinner ? ' manual-winner' : '';
    
    if (!$participant3) {
        // 2er-Paarung
        $winner1 = false;
        $winner2 = false;
        
        if ($hasManualWinner) {
            $winner1 = ($row['ManualWinner'] == $row['Participant1']);
            $winner2 = ($row['ManualWinner'] == $row['Participant2']);
        } else {
            $winner1 = ($row['Result1'] > $row['Result2']) || 
                      ($row['Result1'] == $row['Result2'] && $row['LowShot1'] > $row['LowShot2']);
            $winner2 = !$winner1;
        }

        $html .= '<tr' . $manualWinnerClass . '>';
        $html .= '<td>' . ($winner1 ? '<span class="winner">' : '<span class="strike-through">') . 
                 htmlspecialchars($participant1) . '</span></td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['Result1']) . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['LowShot1']) . '</td>';
        $html .= '<td>' . ($winner2 ? '<span class="winner">' : '<span class="strike-through">') . 
                 htmlspecialchars($participant2) . '</span></td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['Result2']) . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['LowShot2']) . '</td>';
        $html .= '</tr>';
    } else {
        // 3er-Paarung (getThreeWayWinners berücksichtigt Advancers + ManualWinner)
        $threeWayResult = getThreeWayWinners($row);
        $winners = $threeWayResult['winners'];

        // Erste Zeile
        $isWinner1 = in_array((int)$row['Participant1'], $winners);
        $isWinner2 = in_array((int)$row['Participant2'], $winners);
        
        $html .= '<tr' . $manualWinnerClass . '>';
        $html .= '<td>' . ($isWinner1 ? '<span class="winner">' : '<span class="strike-through">') . 
                 htmlspecialchars($participant1) . '</span></td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['Result1']) . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['LowShot1']) . '</td>';
        $html .= '<td>' . ($isWinner2 ? '<span class="winner">' : '<span class="strike-through">') . 
                 htmlspecialchars($participant2) . '</span></td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['Result2']) . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['LowShot2']) . '</td>';
        $html .= '</tr>';
        
        // Zweite Zeile für dritten Teilnehmer
        $isWinner3 = in_array((int)$row['Participant3'], $winners);
        $html .= '<tr' . $manualWinnerClass . '>';
        $html .= '<td>' . ($isWinner3 ? '<span class="winner">' : '<span class="strike-through">') . 
                 htmlspecialchars($participant3) . '</span></td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['Result3']) . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['LowShot3']) . '</td>';
        $html .= '<td colspan="3"></td>';
        $html .= '</tr>';
    }
    
    // Leere Zeile
    $html .= '<tr class="empty-row"><td colspan="6"></td></tr>';
}
$html .= '</table>';

// Finalrunde
$finalQuery = "SELECT * FROM cupFinalResults WHERE Year = ? ORDER BY Result DESC, LowShot DESC";
$stmt = $conn->prepare($finalQuery);
$stmt->bind_param("i", $year);
$stmt->execute();
$finalResult = $stmt->get_result();

if ($finalResult->num_rows > 0) {
    $html .= '<div class="final-round">';
    $html .= '<h2>Finalrunde</h2>';
    $html .= '<table class="table">';
    $html .= '<tr><th>Rang</th><th>Teilnehmer</th><th>Ergebnis</th><th>Tiefschuss</th></tr>';
    
    $rank = 1;
    while ($row = $finalResult->fetch_assoc()) {
        $participant = getParticipantName($conn, $row['ParticipantID']);
        $html .= '<tr>';
        $html .= '<td style="text-align: center;">' . $rank . '.</td>';
        $html .= '<td>' . htmlspecialchars($participant) . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['Result']) . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['LowShot']) . '</td>';
        $html .= '</tr>';
        $rank++;
    }
    $html .= '</table>';
    $html .= '</div>';
}

// Standcup Final
$standcupQuery = "SELECT * FROM cupStandFinal WHERE Year = ? ORDER BY Result DESC";
$stmt = $conn->prepare($standcupQuery);
$stmt->bind_param("i", $year);
$stmt->execute();
$standcupResult = $stmt->get_result();

if ($standcupResult->num_rows > 0) {
    $html .= '<div class="standcup-final">';
    $html .= '<h2>Standcup Final</h2>';
    $html .= '<table class="table">';
    $html .= '<tr><th>Rang</th><th>Verein</th><th>Teilnehmer</th><th>Ergebnis</th></tr>';
    
    $rank = 1;
    while ($row = $standcupResult->fetch_assoc()) {
        $html .= '<tr>';
        $html .= '<td style="text-align: center;">' . $rank . '.</td>';
        $html .= '<td>' . htmlspecialchars($row['club']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['ParticipantName']) . '</td>';
        $html .= '<td style="text-align: center;">' . htmlspecialchars($row['Result']) . '</td>';
        $html .= '</tr>';
        $rank++;
    }
    $html .= '</table>';
    $html .= '</div>';
}

// Footer
$html .= '<div class="footer">MSV Wilen - Erstellt am ' . date("d.m.Y H:i") . '</div>';
$html .= '</div></body></html>';

// PDF generieren
try {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // PDF speichern
    $pdfOutput = $dompdf->output();
    $pdfDir = 'dat/';
    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0777, true);
    }
    
    $pdfFilePath = $pdfDir . 'Cup_Results_' . $year . '_' . date('Y-m-d_H-i-s') . '.pdf';
    file_put_contents($pdfFilePath, $pdfOutput);
    
    // Ausgabepuffer leeren
    ob_end_clean();
    
    // JSON-Response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'pdf_link' => $pdfFilePath,
        'message' => 'PDF erfolgreich erstellt'
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Fehler beim Erstellen des PDFs: ' . $e->getMessage()
    ]);
}

$conn->close();
?>