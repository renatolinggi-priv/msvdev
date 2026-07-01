<?php

/************************************************************
 * export_monatsblatt_pdf.php
 *
 * Liest Daten (JMDefinition / JMSchiesstage), filtert sie
 * nach Jahr und Monatsbereich und erzeugt ein PDF via DomPDF.
 ************************************************************/

require_once '../config.php';
require_once '../session_config.inc.php';
require_once '../vendor/autoload.php';
require_once '../pdf/pdf_theme.php';  // zentrales PDF-Theme (Palette/Logo)

use Dompdf\Dompdf;
use Dompdf\Options;

// Auth: nur eingeloggte Nutzer (gleicher Schutz wie die aufrufende Seite)
if (empty($_SESSION['user_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['message' => 'Nicht angemeldet']);
    exit;
}

// CSRF: Token muss zur Session passen
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['message' => 'Ungültige Anfrage']);
    exit;
}

class MonatsblattPDFExporter {
    private $conn;
    private $year;
    private $startMonth;
    private $endMonth;
    private $bemerkung;
    private $startDateStr;
    private $endDateStr;
    private $startTimestamp;
    private $endTimestamp;
    
    private $monthTranslation = [
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
    
    private $monthDe = [
        1 => "Januar", 2 => "Februar", 3 => "März", 4 => "April",
        5 => "Mai", 6 => "Juni", 7 => "Juli", 8 => "August",
        9 => "September", 10 => "Oktober", 11 => "November", 12 => "Dezember"
    ];
    
    private $wdEnToDe = [
        'Monday' => 'Montag', 'Tuesday' => 'Dienstag', 'Wednesday' => 'Mittwoch',
        'Thursday' => 'Donnerstag', 'Friday' => 'Freitag', 
        'Saturday' => 'Samstag', 'Sunday' => 'Sonntag'
    ];
    
    private $deDays = [
        'Mon' => 'Mo', 'Tue' => 'Di', 'Wed' => 'Mi',
        'Thu' => 'Do', 'Fri' => 'Fr', 'Sat' => 'Sa', 'Sun' => 'So'
    ];

    public function __construct($conn) {
        $this->conn = $conn;
        $this->initializeParameters();
        $this->calculateDateRanges();
    }

    private function initializeParameters() {
        // Validierung und Sanitization der POST-Parameter
        $this->year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT) ?: date('Y');

        // Monate können mit führender Null kommen (z.B. "01", "02")
        $startMonthRaw = $_POST['start_month'] ?? '1';
        $endMonthRaw = $_POST['end_month'] ?? '12';

        // Konvertiere zu Integer (entfernt führende Nullen)
        $this->startMonth = (int)$startMonthRaw;
        $this->endMonth = (int)$endMonthRaw;

        // Sicherstellen, dass die Monate im gültigen Bereich sind
        $this->startMonth = max(1, min(12, $this->startMonth ?: 1));
        $this->endMonth = max(1, min(12, $this->endMonth ?: 12));

        // Bemerkung mit XSS-Schutz
        $this->bemerkung = htmlspecialchars($_POST['bemerkung'] ?? '', ENT_QUOTES, 'UTF-8');
    }

    private function calculateDateRanges() {
        $this->startDateStr = sprintf('%04d-%02d-01', $this->year, $this->startMonth);
        $endMonthLastDay = date('t', strtotime(sprintf('%04d-%02d-01', $this->year, $this->endMonth)));
        $this->endDateStr = sprintf('%04d-%02d-%02d', $this->year, $this->endMonth, $endMonthLastDay);
        
        $this->startTimestamp = strtotime($this->startDateStr);
        $this->endTimestamp = strtotime($this->endDateStr);
    }

    public function generatePDF() {
        try {
            // Daten sammeln
            $jmDefinitionData = $this->fetchJMDefinitionData();
            $schiesstageData = $this->fetchSchiesstageData();
            $gruppenData = $this->fetchGruppenData();
            $wichtigeTermine = $this->fetchWichtigeTermine();
            
            // Daten verarbeiten
            $processedData = $this->processData($jmDefinitionData, $schiesstageData);
            
            // HTML generieren
            $htmlContent = $this->buildHTML(
                $processedData['timestamps'],
                $processedData['dates'],
                $processedData['eventsByDate'],
                $gruppenData,
                $wichtigeTermine
            );
            
            // PDF erstellen
            return $this->createPDF($htmlContent);
            
        } catch (Exception $e) {
            error_log("PDF Generation Error: " . $e->getMessage());
            return ['message' => 'Fehler bei der PDF-Erstellung: ' . $e->getMessage()];
        }
    }

    private function fetchJMDefinitionData() {
        $sql = "SELECT ID, Bezeichnung, Schiesstage, Erweitert, Info
                FROM JMDefinition
                WHERE year = ?
                  AND Schiesstage IS NOT NULL
                  AND Schiesstage != ''";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param("i", $this->year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
    }

    private function fetchSchiesstageData() {
        $sql = "SELECT jm_id, schiesstag, start_time, end_time
                FROM JMSchiesstage
                WHERE year = ?
                  AND schiesstag >= ?
                  AND schiesstag <= ?
                ORDER BY schiesstag, start_time";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param("iss", $this->year, $this->startDateStr, $this->endDateStr);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
    }

    private function fetchGruppenData() {
        $sql = "SELECT 
                  jg.JMDefinitionID,
                  jg.Gruppenname,
                  jg.GruppenUID,
                  jg.mitgliederID,
                  m.Vorname,
                  m.Name
                FROM JMDefinition_Gruppen jg
                JOIN mitglieder m ON jg.mitgliederID = m.ID
                WHERE jg.Jahr = ?
                ORDER BY jg.JMDefinitionID, jg.GruppenUID, m.Name, m.Vorname";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param("i", $this->year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $gruppenMapping = [];
        while ($row = $result->fetch_assoc()) {
            $defID = $row['JMDefinitionID'];
            $gUID = $row['GruppenUID'];
            $fullName = $row['Name'] . " " . $row['Vorname'];
            
            if (!isset($gruppenMapping[$defID][$gUID])) {
                $gruppenMapping[$defID][$gUID] = [
                    'Gruppenname' => $row['Gruppenname'],
                    'members' => []
                ];
            }
            $gruppenMapping[$defID][$gUID]['members'][] = $fullName;
        }
        
        $stmt->close();
        return $gruppenMapping;
    }

    private function fetchWichtigeTermine() {
        $sql = "SELECT ID, name, date, time
                FROM wichtige_termine
                WHERE year = ?
                  AND date >= ?
                  AND date <= ?
                ORDER BY date";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        $stmt->bind_param("iss", $this->year, $this->startDateStr, $this->endDateStr);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $termine = [];
        while ($row = $result->fetch_assoc()) {
            $termine[] = $row;
        }
        
        $stmt->close();
        return $termine;
    }

    private function processData($jmDefinitionData, $schiesstageData) {
        $parsedDates = [];
        $allTimestamps = [];
        
        // Regex für Datumsangaben
        $regex = "/\b(\d{1,2})\.\s?(Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)\s?(\d{4})?\b/u";
        
        // JMDefinition Daten verarbeiten
        foreach ($jmDefinitionData as $row) {
            if (preg_match_all($regex, $row['Schiesstage'], $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $day = str_pad($match[1], 2, '0', STR_PAD_LEFT);
                    $monthDe = $match[2];
                    $yearFound = !empty($match[3]) ? $match[3] : $this->year;
                    
                    $monthEn = $this->monthTranslation[$monthDe] ?? 'January';
                    $timestamp = strtotime("$day $monthEn $yearFound");
                    
                    if ($timestamp === false) continue;
                    if ($timestamp < $this->startTimestamp || $timestamp > $this->endTimestamp) continue;
                    
                    $ymd = date('Y-m-d', $timestamp);
                    $parsedDates[$ymd][] = [
                        'id' => $row['ID'],
                        'bezeichnung' => $row['Bezeichnung'],
                        'erweitert' => $row['Erweitert'],
                        'info' => $row['Info']
                    ];
                    
                    if (!isset($allTimestamps[$ymd])) {
                        $allTimestamps[$ymd] = $timestamp;
                    }
                }
            }
        }
        
        // Schiesstage Daten verarbeiten
        $eventsByDate = [];
        foreach ($schiesstageData as $row) {
            $ymd = $row['schiesstag'];
            $jmId = $row['jm_id'];
            
            $eventsByDate[$ymd][$jmId][] = [
                'start' => $row['start_time'],
                'end' => $row['end_time']
            ];
        }
        
        // Sortieren
        asort($allTimestamps);
        
        return [
            'timestamps' => $allTimestamps,
            'dates' => $parsedDates,
            'eventsByDate' => $eventsByDate
        ];
    }

    private function buildHTML($allTimestamps, $parsedDates, $eventsByDate, $gruppenMapping, $wichtigeTermine) {
        $rangeTitle = $this->monthDe[$this->startMonth] . " - " . $this->monthDe[$this->endMonth] . " " . $this->year;
        
        // CSS als separate Methode
        $css = $this->getCSS();
        
        // Titelseite
        $titlePage = $this->buildTitlePage($rangeTitle);
        
        // HTML Start
        $html = '<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>' . $css . '</style>
</head>
<body>' . $titlePage;
        
        // Hauptinhalt
        foreach ($allTimestamps as $ymd => $ts) {
            $html .= $this->buildDayBlock($ymd, $ts, $parsedDates, $eventsByDate, $gruppenMapping);
        }
        
        // Wichtige Termine
        $html .= $this->buildWichtigeTermine($wichtigeTermine);
        
        // Bemerkungen
        if (!empty($this->bemerkung)) {
            $html .= $this->buildBemerkungen();
        }
        
        $html .= '</body></html>';
        
        return $html;
    }

    private function getCSS() {
        // Zentrales Theme zuerst (Typografie/Akzentfarbe/Tabellen-Defaults),
        // danach Monatsblatt-spezifische Layout-Overrides.
        return pdf_theme_css() . '
            body {
                font-family: Arial, sans-serif;
                font-size: 10px;
                margin: 20px;
                line-height: 1.4;
            }
            .day-block {
                page-break-inside: avoid;
                margin-bottom: 20px;
            }
            table.day-table {
                border-collapse: collapse;
                width: 100%;
                margin-bottom: 20px;
                page-break-inside: avoid;
            }
            th, td {
                border: 1px solid #e2e8f0;
                padding: 4px;
                vertical-align: top;
            }
            th {
                background-color: #eef2f7;
                color: #2d3748;
                border-bottom: 2px solid #cbd5e0;
                font-weight: bold;
            }
            .td-left {
                text-align: left;
            }
            .td-center {
                text-align: center;
            }
            .no-top {
                border-top: none !important;
            }
            .no-bottom {
                border-bottom: none !important;
            }
            .date-cell {
                font-family: "Roboto Mono", Consolas, "Courier New", monospace;
                white-space: nowrap;
            }
            h3 {
                margin-top: 10px;
                margin-bottom: 10px;
            }
            hr {
                border: none;
                border-top: 1px solid #ccc;
                margin: 10px 0;
            }
        ';
    }

    private function buildTitlePage($rangeTitle) {
        // Zentrales Logo (Master-Datei via pdf_theme), Fallback auf lokale dat/-Kopie
        $logoBase64 = pdf_logo_src() ?: $this->imgToBase64('dat/MSVWilen_Logo.jpg');

        return '<div style="text-align: left; margin-top: 100px;">
            <img src="' . $logoBase64 . '" alt="Logo" style="width:200px; height:auto;">
        </div>
        
        <div style="text-align: center; margin-top: 100px;">
            <hr>
            <h1 style="font-size: 24px; margin-top: 20px;">Schiesszeiten<br>' . $rangeTitle . '</h1>
            <hr>
        </div>
        
        <div style="text-align: right; margin-top: 100px;">
            <img src="' . $logoBase64 . '" alt="Logo" style="width:200px; height:auto;">
        </div>
        
        <div style="text-align: right; margin-top: 100px;">
            ' . date('d.m.Y') . ' / RC
        </div>
        
        <div style="page-break-after: always;"></div>';
    }

    private function buildDayBlock($ymd, $ts, $parsedDates, $eventsByDate, $gruppenMapping) {
        $dayLabel = $this->formatDay($ts);
        
        $html = '<div class="day-block">
            <hr>
            <h3>' . $dayLabel . '</h3>
            <table class="day-table">
                <tr>
                    <th style="width:100px;" class="td-left">Zeit</th>
                    <th style="width:250px;" class="td-left">Bezeichnung</th>
                    <th style="width:100px;" class="td-left">Typ</th>
                    <th style="width:150px;" class="td-left">Gruppen</th>
                </tr>';
        
        if (empty($parsedDates[$ymd])) {
            $html .= '<tr><td colspan="4" class="td-center">Keine Events</td></tr>';
        } else {
            foreach ($parsedDates[$ymd] as $event) {
                $html .= $this->buildEventRows($event, $ymd, $eventsByDate, $gruppenMapping);
            }
        }
        
        $html .= '</table></div>';
        
        return $html;
    }

    private function buildEventRows($event, $ymd, $eventsByDate, $gruppenMapping) {
        $eid = $event['id'];
        $typText = $this->getEventType($event['erweitert'], $event['info']);
        $gruppenText = $this->getGruppenText($eid, $gruppenMapping);
        
        $blocks = $eventsByDate[$ymd][$eid] ?? [];
        
        if (empty($blocks)) {
            return '<tr>
                <td class="td-left">Keine Zeit</td>
                <td>' . htmlspecialchars($event['bezeichnung']) . '</td>
                <td class="td-left">' . $typText . '</td>
                <td>' . $gruppenText . '</td>
            </tr>';
        }
        
        $html = '';
        $cnt = count($blocks);
        
        foreach ($blocks as $idx => $timeBlock) {
            $startHM = substr($timeBlock['start'], 0, 5);
            $endHM = substr($timeBlock['end'], 0, 5);
            
            $extraClass = '';
            if ($idx > 0) $extraClass .= ' no-top';
            if ($idx < $cnt - 1) $extraClass .= ' no-bottom';
            
            if ($idx === 0) {
                $html .= '<tr>
                    <td class="td-left' . $extraClass . '">' . $startHM . ' - ' . $endHM . '</td>
                    <td class="' . $extraClass . '">' . htmlspecialchars($event['bezeichnung']) . '</td>
                    <td class="td-left' . $extraClass . '">' . $typText . '</td>
                    <td class="' . $extraClass . '">' . $gruppenText . '</td>
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
        
        return $html;
    }

    private function getEventType($erweitert, $info) {
        if ($erweitert == 1) {
            return "Gruppenschiessen";
        } elseif ($erweitert == 0 && $info != 1) {
            return "JM A + B";
        }
        return "";
    }

    private function getGruppenText($eid, $gruppenMapping) {
        $text = "";
        if (!empty($gruppenMapping[$eid])) {
            foreach ($gruppenMapping[$eid] as $gUID => $gdata) {
                $text .= "<strong>" . htmlspecialchars($gdata['Gruppenname']) . "</strong> (" 
                      . htmlspecialchars(implode(", ", $gdata['members'])) . ")<br>";
            }
        }
        return $text;
    }

    private function buildWichtigeTermine($termine) {
        if (empty($termine)) {
            return '';
        }
        
        $html = '<div class="day-block" style="page-break-inside: avoid;">
            <h3>Wichtige Termine</h3>
            <table class="day-table">
                <tr>
                    <th class="td-left">Datum</th>
                    <th class="td-left">Uhrzeit</th>
                    <th>Bezeichnung</th>
                </tr>';
        
        foreach ($termine as $termin) {
            $ts = strtotime($termin['date']);
            $weekday = date('D', $ts);
            $weekdayDe = $this->deDays[$weekday] ?? $weekday;
            $dateStr = $weekdayDe . '. ' . date('d.m.Y', $ts);
            
            $html .= '<tr>
                <td class="td-left date-cell">' . $dateStr . '</td>
                <td class="td-left">' . htmlspecialchars($termin['time']) . '</td>
                <td>' . htmlspecialchars($termin['name']) . '</td>
            </tr>';
        }
        
        $html .= '</table></div>';
        
        return $html;
    }

    private function buildBemerkungen() {
        return '<div class="day-block" style="page-break-inside: avoid;">
            <h3>Bemerkungen</h3>
            <table class="day-table">
                <tr><td>' . nl2br($this->bemerkung) . '</td></tr>
            </table>
        </div>';
    }

    private function formatDay($ts) {
        $wd = date('l', $ts);
        $wdD = $this->wdEnToDe[$wd] ?? $wd;
        $d = date('d', $ts);
        $m = date('m', $ts);
        return "$wdD, $d.$m.";
    }

    private function imgToBase64($imgPath) {
        if (!file_exists($imgPath)) {
            return "";
        }
        
        $imageData = file_get_contents($imgPath);
        if ($imageData === false) {
            return "";
        }
        
        $mimeType = mime_content_type($imgPath);
        return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
    }

    private function createPDF($htmlContent) {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Arial');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf(
            'Monatsblatt_%d_%02d-%02d_%s.pdf',
            $this->year,
            $this->startMonth,
            $this->endMonth,
            date('d.m.Y')
        );

        // PDF direkt als Download streamen (kein Speichern auf Platte).
        // Puffer leeren, damit keine Vor-Ausgabe das Binär-PDF beschädigt.
        if (ob_get_length()) { @ob_end_clean(); }
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }
}

// Binär-Streaming: keine PHP-Warnungen in die Ausgabe (würde das PDF beschädigen)
ini_set('display_errors', '0');

// Hauptausführung
try {
    $exporter = new MonatsblattPDFExporter($conn);
    // generatePDF() streamt das PDF und beendet das Skript bei Erfolg.
    // Kehrt es zurück, ist beim Generieren ein Fehler aufgetreten.
    $result = $exporter->generatePDF();

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo (is_array($result) && !empty($result['message'])) ? $result['message'] : 'PDF konnte nicht erstellt werden';

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();

} finally {
    if (isset($conn)) {
        $conn->close();
    }
}