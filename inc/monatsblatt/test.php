<?php

/************************************************************
 * export_monatsblatt_pdf.php
 *
 * Liest Daten (JMDefinition / JMSchiesstage), filtert sie
 * nach Jahr und Monatsbereich und erzeugt ein PDF via DomPDF.
 * 
 * Änderungen:
 *  1) Zeitspalte linksbündig
 *  2) Jeder Tages-Table mit "page-break-inside: avoid;"
 *  3) Keine horizontale Linie zwischen mehreren Zeitblöcken
 *     desselben Events (Doppelentfernung von Rand oben und unten)
 *  4) Eine Titelseite (erste Seite) wird erstellt, die das Logo aus
 *     "dat/MSVWilen_Logo.jpg" zeigt. Danach erfolgt ein Seitenumbruch.
 ************************************************************/

require_once '../config.php';

// DomPDF einbinden
require_once '../dompdf/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Parameter
$year       = isset($_GET['year']) ? (int)$_GET['year'] : 2025;
$startMonth = isset($_GET['start_month']) ? (int)$_GET['start_month'] : 1;
$endMonth   = isset($_GET['end_month'])   ? (int)$_GET['end_month']   : 12;
$bemerkung  = $_GET['bemerkung'];

// Start/End-Datum
$startDateStr    = sprintf('%04d-%02d-01', $year, $startMonth);
$endMonthLastDay = date('t', strtotime(sprintf('%04d-%02d-01', $year, $endMonth)));
$endDateStr      = sprintf('%04d-%02d-%02d', $year, $endMonth, $endMonthLastDay);

$startTimestamp = strtotime($startDateStr);
$endTimestamp   = strtotime($endDateStr);

// (1) JMDefinition
$sql = "SELECT ID, Bezeichnung, Schiesstage, Erweitert, Info
        FROM JMDefinition
        WHERE year = ?
          AND Schiesstage IS NOT NULL
          AND Schiesstage != '' ";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$parsed_dates  = [];
$all_timestamps = [];

$month_translation = [
    "Januar" => "January",
    "Februar" => "February",
    "März" => "March",
    "April" => "April",
    "Mai" => "May",
    "Juni" => "June",
    "Juli" => "July",
    "August" => "August",
    "September" => "September",
    "Oktober" => "October",
    "November" => "November",
    "Dezember" => "December"
];



// Regex für Datumsangaben wie "12. März 2025"
$regex = "/\b(\d{1,2})\.\s?(Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)\s?(\d{4})?\b/u";

while ($row = $result->fetch_assoc()) {
    $eid     = $row['ID'];
    $ename   = $row['Bezeichnung'];
    $erweit  = $row['Erweitert'];
    $rawText = $row['Schiesstage'];
    $info    = $row['Info'];

    if (preg_match_all($regex, $rawText, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $day     = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $m_de    = $m[2];
            $y_found = !empty($m[3]) ? $m[3] : $year;

            $m_en    = $month_translation[$m_de] ?? 'January';
            $ts_str  = "$day $m_en $y_found";
            $ts      = strtotime($ts_str);
            if ($ts === false) continue;

            if ($ts < $startTimestamp || $ts > $endTimestamp) continue;

            $ymd = date('Y-m-d', $ts);
            $parsed_dates[$ymd][] = [
                'id'          => $eid,
                'bezeichnung' => $ename,
                'erweitert'   => $erweit,
                'info'        => $info
            ];
            if (!isset($all_timestamps[$ymd])) {
                $all_timestamps[$ymd] = $ts;
            }
        }
    }
}
$stmt->close();

// (2) JMSchiesstage
$sql2 = "
   SELECT jm_id, schiesstag, start_time, end_time
   FROM JMSchiesstage
   WHERE year=?
     AND schiesstag>=?
     AND schiesstag<=?
   ORDER BY schiesstag, start_time
";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("iss", $year, $startDateStr, $endDateStr);
$stmt2->execute();
$result2 = $stmt2->get_result();

$events_by_date_and_id = [];
while ($r2 = $result2->fetch_assoc()) {
    $ymd  = $r2['schiesstag'];
    $jeid = $r2['jm_id'];
    $st   = $r2['start_time'];
    $en   = $r2['end_time'];

    $events_by_date_and_id[$ymd][$jeid][] = [
        'start' => $st,
        'end'   => $en
    ];
}
$stmt2->close();

// (3) Gruppen-Abfrage mit JOIN auf Mitglieder
$sql3 = "
   SELECT 
     jg.JMDefinitionID,
     jg.Gruppenname,
     jg.GruppenUID,
     jg.mitgliederID,
     m.Vorname,
     m.Name
   FROM JMDefinition_Gruppen jg
   JOIN mitglieder m ON jg.mitgliederID = m.ID
   WHERE jg.Jahr=?
";
$stmt3 = $conn->prepare($sql3);
$stmt3->bind_param("i", $year);
$stmt3->execute();
$res3 = $stmt3->get_result();

$gruppenMapping = [];
while ($rg = $res3->fetch_assoc()) {
    $defID    = $rg['JMDefinitionID'];
    $gName    = $rg['Gruppenname'];
    $gUID     = $rg['GruppenUID'];
    // Vollständiger Name: "Nachname Vorname"
    $fullName = $rg['Name'] . " " . $rg['Vorname'];

    if (!isset($gruppenMapping[$defID])) {
        $gruppenMapping[$defID] = [];
    }
    if (!isset($gruppenMapping[$defID][$gUID])) {
        $gruppenMapping[$defID][$gUID] = [
            'Gruppenname' => $gName,
            'members'     => []
        ];
    }
    $gruppenMapping[$defID][$gUID]['members'][] = $fullName;
}
$stmt3->close();
// (5) Wichtige Termine
$sql4 = "
   SELECT ID, name, date, time
   FROM wichtige_termine
   WHERE year = ?
     AND date >= ?
     AND date <= ?
   ORDER BY date
";
$stmt4 = $conn->prepare($sql4);
$stmt4->bind_param("iss", $year, $startDateStr, $endDateStr);
$stmt4->execute();
$result4 = $stmt4->get_result();

$wichtigeTermine = [];
while ($r4 = $result4->fetch_assoc()) {
    $wichtigeTermine[] = [
        'name'  => $r4['name'],
        'date'  => $r4['date'],
        'time'  => $r4['time']
    ];
}
$stmt4->close();


// (4) Sortierung
asort($all_timestamps);

// Hilfsfunktion, um ein Bild in Base64 zu konvertieren
function imgToBase64($imgPath)
{
    if (file_exists($imgPath)) {
        $imageData = base64_encode(file_get_contents($imgPath));
        $mimeType  = mime_content_type($imgPath);
        return 'data:' . $mimeType . ';base64,' . $imageData;
    }
    return "";
}

// (5) HTML-Ausgabe
function buildMonatsblattHTML(
    $all_timestamps,
    $parsed_dates,
    $events_by_date_and_id,
    $gruppenMapping,
    $year,
    $startMonth,
    $endMonth
) {
    $wdEnToDe = [
        'Monday' => 'Montag',
        'Tuesday' => 'Dienstag',
        'Wednesday' => 'Mittwoch',
        'Thursday' => 'Donnerstag',
        'Friday' => 'Freitag',
        'Saturday' => 'Samstag',
        'Sunday' => 'Sonntag'
    ];
    $monthDe = [
        1 => "Januar",
        2 => "Februar",
        3 => "März",
        4 => "April",
        5 => "Mai",
        6 => "Juni",
        7 => "Juli",
        8 => "August",
        9 => "September",
        10 => "Oktober",
        11 => "November",
        12 => "Dezember"
    ];
    $rangeTitle = $monthDe[$startMonth] . " - " . $monthDe[$endMonth] . " " . $year;

    // Titelseite erstellen
    $logoBase64 = imgToBase64('dat/MSVWilen_Logo.jpg');
    $titlePage = '<div style="text-align: left; margin-top: 100px;">
         <img src="' . $logoBase64 . '" alt="Logo" style="width:200px; height:auto;">
         </div>
         
        <div style="text-align: center; margin-top: 100px;"><hr>
         <h1 style="font-size: 24px; margin-top: 20px;">Schiesszeiten <br> ' . $rangeTitle . '</h1><hr>
         <p style="font-size: 14px;"></p>
       </div>
       <div style="text-align: right; margin-top: 100px;">
         <img src="' . $logoBase64 . '" alt="Logo" style="width:200px; height:auto;">
         </div>
         
       <div style="text-align: right; margin-top: 100px;">
            ' . date('d.m.y') . ' / RC 
         </div>
       <div style="page-break-after: always;"></div>';

    // Hauptinhalt: Tagesübersicht
    $html = '<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
  body {
    font-family: Arial, sans-serif;
    font-size:10px;
    margin:20px;
  }
  .day-block {
    page-break-inside: avoid;
    margin-bottom:20px;
  }
  table.day-table {
    border-collapse: collapse;
    width:100%;
    margin-bottom:20px;
    page-break-inside: avoid;
  }
  th, td {
    border:1px solid #333;
    padding:4px;
    vertical-align: top;
  }
  th {
    background-color:#f0f0f0;
  }
  .td-left {
    text-align:left;
  }
  .td-center {
    text-align:center;
  }
  .no-top {
    border-top: none !important;
  }
  .no-bottom {
    border-bottom: none !important;
  }
</style>
</head>
<body>
' . $titlePage;

    // Funktion für Datum: "Sonntag, 04.05." (ohne Jahr)
    $formatDay = function ($ts) use ($wdEnToDe) {
        $wd  = date('l', $ts);
        $wdD = $wdEnToDe[$wd] ?? $wd;
        $d   = date('d', $ts);
        $m   = date('m', $ts);
        return "$wdD, $d.$m.";
    };

    foreach ($all_timestamps as $ymd => $ts) {
        $dayLabel = $formatDay($ts);

        $html .= '<div class="day-block" style="page-break-inside: avoid;">';
        $html .= "<hr><h3>$dayLabel</h3>";
        $html .= '<table class="day-table">
                    <tr>
                      <th style="width:100px;" class="td-left">Zeit</th>
                      <th style="width:250px;" class="td-left">Bezeichnung</th>
                      <th style="width:100px;" class="td-left">Typ</th>
                      <th style="width:150px;" class="td-left">Gruppen</th>
                    </tr>';

        if (empty($parsed_dates[$ymd])) {
            $html .= '<tr><td colspan="4" align="center">Keine Events</td></tr></table>';
            $html .= '</div>';
            continue;
        }

        foreach ($parsed_dates[$ymd] as $evItem) {
            $eid     = $evItem['id'];
            $evName  = $evItem['bezeichnung'];
            $isErw   = $evItem['erweitert'];
            $info    = $evItem['info'];
            $typText = '';
            if ($isErw == 1) {
                $typText = "Gruppenschiessen";
            } elseif ($isErw == 0 && $info != 1) {
                $typText = "JM A + B";
            }

            // Gruppen-Text
            $gText = "";
            if (!empty($gruppenMapping[$eid])) {
                foreach ($gruppenMapping[$eid] as $gUID => $gdata) {
                    $gName   = $gdata['Gruppenname'];
                    // ACHTUNG: Hier $gdata verwenden, nicht $groupData
                    $gText  .= "<strong>$gName</strong> (" . implode(", ", $gdata['members']) . ")<br>";
                }
            }

            // Zeitblöcke
            $blocks = $events_by_date_and_id[$ymd][$eid] ?? [];
            if (empty($blocks)) {
                $html .= '<tr>
                            <td class="td-left">Keine Zeit</td>
                            <td>' . $evName . '</td>
                            <td class="td-left">' . $typText . '</td>
                            <td>' . $gText . '</td>
                          </tr>';
            } else {
                $cnt = count($blocks);
                foreach ($blocks as $idx => $tb) {
                    $startHM = substr($tb['start'], 0, 5);
                    $endHM   = substr($tb['end'], 0, 5);

                    // Entferne oberen Rand bei Folgezeilen und unteren Rand bei allen außer der letzten
                    $extraTop    = ($idx > 0) ? ' no-top' : '';
                    $extraBottom = ($idx < $cnt - 1) ? ' no-bottom' : '';
                    $extraClass  = $extraTop . $extraBottom;

                    if ($idx === 0) {
                        $html .= '<tr>
                            <td class="td-left' . $extraClass . '">' . $startHM . ' - ' . $endHM . '</td>
                            <td class="' . $extraClass . '">' . $evName . '</td>
                            <td class="td-left' . $extraClass . '">' . $typText . '</td>
                            <td class="' . $extraClass . '">' . $gText . '</td>
                          </tr>';
                    } else {
                        $html .= '<tr>
                            <td class="td-left' . $extraClass . '">' . $startHM . ' - ' . $endHM . '</td>
                            <td class="' . $extraClass . '"></td>
                            <td class="td-left' . $extraClass . '"></td>
                            <td class="' . $extraClass . '"></td>
                          </tr>';
                    }
                }
            }
        }
        $html .= '</table>';
        $html .= '</div>';
    }
    global $wichtigeTermine;
    $html .= addWichtigeTermine($wichtigeTermine);
    global $bemerkung;
    if (!empty($bemerkung)) {
  
        $bemerkung = nl2br($bemerkung);
        // Am Ende der HTML-Ausgabe, nach der Tabelle, das Textfeld hinzufügen
        $html .= '<div class="day-block" style="page-break-inside: avoid;">';
        $html .= "<hr><h3>Bemerkungen</h3>";
        $html .= '<table class="day-table">';
        $html .= '<tr><td>';
        $html .= $bemerkung;
        $html .= '</td></tr></table>';
        $html .= '</div>';
    }

    return $html;
}

// HTML generieren
if (empty($all_timestamps)) {
    $htmlContent = "<p>Keine Datumsangaben im Zeitraum gefunden.</p>";
} else {
    $htmlContent = buildMonatsblattHTML(
        $all_timestamps,
        $parsed_dates,
        $events_by_date_and_id,
        $gruppenMapping,
        $year,
        $startMonth,
        $endMonth
    );
}


// HTML-Ausgabe für die wichtigen Termine
function addWichtigeTermine($wichtigeTermine)
{
    if (empty($wichtigeTermine)) {
        return '';
    }

    $html = '<div class="day-block" style="page-break-inside: avoid;">';
    $html .= "<hr><h3>Wichtige Termine</h3>";
    $html .= '<table class="day-table">';
    $html .= '<tr><th>Datum</th><th>Uhrzeit</th><th>Termin</th></tr>';

    foreach ($wichtigeTermine as $termin) {
        $date = date('d.m.Y', strtotime($termin['date']));
        $html .= '<tr>
                    <td class="td-left">' . $date . '</td>
                    <td class="td-left">' . $termin['time'] . '</td>
                    <td>' . $termin['name'] . '</td>
                  </tr>';
    }

    $html .= '</table>';
    $html .= '</div>';

    return $html;
}



/*
// DomPDF-Setup
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($htmlContent);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$pdfOutput = $dompdf->output();
$filename  = 'Monatsblatt_' . $year . '_' . $startMonth . '-' . $endMonth . '_' . date('d.m.y') . '.pdf';
$filePath  = 'dat/' . $filename;

file_put_contents($filePath, $pdfOutput);
echo json_encode(['pdf_link' => $filePath]);
*/
echo $htmlContent;
$conn->close();
