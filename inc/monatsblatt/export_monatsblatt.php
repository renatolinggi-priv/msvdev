<?php

/************************************************************
 * inc/monatsblatt/export_monatsblatt.php
 *
 * Liest Daten (JMDefinition / JMSchiesstage), filtert sie
 * nach Jahr und Monatsbereich und erzeugt ein PDF via DomPDF:
 * A5-Seiten im Ranglisten-Stil, ausgeschossen als Broschüre (A4 quer, zum Falten;
 * drucken beidseitig, Wenden an der kurzen Seite).
 * POST: year, start_month, end_month, bemerkung, csrf_token [, broschuere=0 → nur A5-Seiten]
 * Antwort: PDF-Stream; bei Fehlern Klartext mit 400/500.
 ************************************************************/

require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Admin/Vorstand, nicht bloss "eingeloggt"
require_once __DIR__ . '/../csrf.inc.php';
require_once '../vendor/autoload.php';
require_once '../pdf/pdf_theme.php';  // zentrales PDF-Theme (Palette/Logo)

use Dompdf\Dompdf;
use Dompdf\Options;

// CSRF: Token muss zur Session passen
csrf_require(true);

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
        if ($this->startMonth > $this->endMonth) {
            // Vorher entstand still ein leeres PDF (invertierter Bereich)
            throw new InvalidArgumentException('Der Von-Monat liegt nach dem Bis-Monat');
        }
        if ($this->year < 2000 || $this->year > 2100) {
            throw new InvalidArgumentException('Ungültiges Jahr');
        }

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
            
        } catch (Throwable $e) { // auch \Error (z. B. aus Dompdf), sonst PHP-Fatal statt Meldung
            error_log("PDF Generation Error: " . $e->getMessage());
            return ['message' => msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e)];
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
        $rangeTitle = $this->startMonth === $this->endMonth
            ? $this->monthDe[$this->startMonth] . ' ' . $this->year
            : $this->monthDe[$this->startMonth] . ' – ' . $this->monthDe[$this->endMonth] . ' ' . $this->year;

        // A5-Seiten im Ranglisten-Stil (pdf_rangliste_css) plus Broschüren-Layout
        $html = '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
              . '<title>Schiesszeiten ' . htmlspecialchars($rangeTitle) . '</title>'
              . '<style>' . pdf_theme_css() . pdf_rangliste_css() . $this->getCSS() . '</style>'
              . '</head><body>' . $this->buildTitlePage($rangeTitle) . '<div class="container">';

        // Tage, gruppiert nach Monat (Monatstitel steht im ersten Tagesblock, damit er nie allein unten steht)
        $letzterMonat = null;
        foreach ($allTimestamps as $ymd => $ts) {
            $monat = (int)date('n', $ts);
            $monatTitel = $monat !== $letzterMonat ? $this->monthDe[$monat] . ' ' . date('Y', $ts) : null;
            $letzterMonat = $monat;
            $html .= $this->buildDayBlock($ymd, $ts, $parsedDates, $eventsByDate, $gruppenMapping, $monatTitel);
        }
        if (!$allTimestamps) {
            $html .= '<p class="muted">Keine Schiesstage in diesem Zeitraum.</p>';
        }

        $html .= $this->buildWichtigeTermine($wichtigeTermine);
        if (!empty($this->bemerkung)) {
            $html .= $this->buildBemerkungen();
        }

        return $html . '</div></body></html>';
    }

    /** Ergänzungen zum Ranglisten-Stil für A5 hoch (Broschüre aus A4 gefaltet). */
    private function getCSS() {
        $c = pdf_theme_palette();
        return '
            @page { margin: 12mm 11mm 15mm 11mm; }
            body { font-size: 9pt; }
            /* Titelblatt */
            .mb-titelseite { text-align: center; padding-top: 18mm; page-break-after: always; }
            .mb-titelseite img { height: 44mm; width: auto; }
            .mb-titel { font-size: 26pt; font-weight: bold; color: ' . $c['rl_titel'] . '; margin: 14mm 0 3mm 0; }
            .mb-zeitraum { font-size: 15pt; margin: 0 0 4mm 0; }
            .mb-verein { font-size: 10pt; color: ' . $c['muted'] . '; }
            .mb-stand { font-size: 8pt; color: ' . $c['muted'] . '; margin-top: 52mm; }
            /* Tage */
            .mb-tag { page-break-inside: avoid; margin-bottom: 6mm; }  /* Luft vor dem nächsten Datumstitel */
            h2.mb-monat { font-size: 13pt; margin: 2mm 0 2mm 0; }
            h3.mb-datum { font-size: 10pt; margin: 0 0 0.8mm 0; }
            .table.mb { margin-bottom: 0; }
            .table.mb th, .table.mb td { padding: 0.8mm 1.2mm; vertical-align: top; }
            .table.mb th { vertical-align: bottom; }
            .table.mb .zeit { width: 21mm; white-space: nowrap; }
            .table.mb .typ { width: 24mm; font-size: 8pt; }
            .table.mb td:last-child { text-align: left; }
            .mb-gruppen { font-size: 7.5pt; margin-top: 0.5mm; line-height: 1.3; }
            .mb-block { page-break-inside: avoid; margin-top: 4mm; }
            .mb-bemerkung { margin: 0; }
        ';
    }

    private function buildTitlePage($rangeTitle) {
        // Zentrales Logo (Master-Datei via pdf_theme), Fallback auf lokale dat/-Kopie
        $logoBase64 = pdf_logo_src() ?: $this->imgToBase64('dat/MSVWilen_Logo.jpg');

        return '<div class="mb-titelseite">'
            . ($logoBase64 !== '' ? '<img src="' . $logoBase64 . '" alt="Logo">' : '')
            . '<div class="mb-titel">Schiesszeiten</div>'
            . '<div class="mb-zeitraum">' . htmlspecialchars($rangeTitle) . '</div>'
            . '<div class="mb-verein">Militärschützenverein Wilen</div>'
            . '<div class="mb-stand">Stand ' . date('d.m.Y') . $this->erstellerKuerzel() . '</div>'
            . '</div>';
    }

    /**
     * Initialen des angemeldeten Benutzers fuer das Titelblatt (" / RL"); vorher fest "/ RC".
     */
    private function erstellerKuerzel(): string {
        $name = trim((string)($_SESSION['user_name'] ?? ''));
        if ($name === '') return '';
        $initialen = '';
        foreach (preg_split('/[\s\-_.]+/u', $name) as $teil) {
            if ($teil !== '') $initialen .= mb_strtoupper(mb_substr($teil, 0, 1));
        }
        return $initialen !== '' ? ' / ' . htmlspecialchars(mb_substr($initialen, 0, 3), ENT_QUOTES, 'UTF-8') : '';
    }

    private function buildDayBlock($ymd, $ts, $parsedDates, $eventsByDate, $gruppenMapping, $monatTitel = null) {
        $html = '<div class="mb-tag">';
        if ($monatTitel !== null) {
            $html .= '<h2 class="rl-abschnitt mb-monat">' . htmlspecialchars($monatTitel) . '</h2>';
        }
        $html .= '<h3 class="rl-abschnitt mb-datum">' . $this->formatDay($ts) . '</h3>'
            . '<table class="table mb"><thead><tr>'
            . '<th class="zeit">Zeit</th><th>Anlass</th><th class="typ">Typ</th>'
            . '</tr></thead><tbody>';

        if (empty($parsedDates[$ymd])) {
            $html .= '<tr><td colspan="3" class="muted">Keine Anlässe</td></tr>';
        } else {
            foreach ($parsedDates[$ymd] as $event) {
                $html .= $this->buildEventRows($event, $ymd, $eventsByDate, $gruppenMapping);
            }
        }

        return $html . '</tbody></table></div>';
    }

    /** Eine Zeile pro Anlass: alle Zeitfenster untereinander, Gruppen klein unter dem Namen. */
    private function buildEventRows($event, $ymd, $eventsByDate, $gruppenMapping) {
        $eid = $event['id'];
        $blocks = $eventsByDate[$ymd][$eid] ?? [];

        if ($blocks) {
            $zeiten = array_map(static fn($b) => substr($b['start'], 0, 5) . ' – ' . substr($b['end'], 0, 5), $blocks);
            $zeit = implode('<br>', $zeiten);
        } else {
            $zeit = '<span class="muted">ohne Zeit</span>';
        }

        $gruppen = $this->getGruppenText($eid, $gruppenMapping);
        return '<tr>'
            . '<td class="zeit">' . $zeit . '</td>'
            . '<td>' . htmlspecialchars($event['bezeichnung']) . ($gruppen !== '' ? '<div class="mb-gruppen">' . $gruppen . '</div>' : '') . '</td>'
            . '<td class="typ">' . $this->getEventType($event['erweitert'], $event['info']) . '</td>'
            . '</tr>';
    }

    private function getEventType($erweitert, $info) {
        if ($erweitert == 1) {
            return "Gruppenschiessen";
        } elseif ($erweitert == 0 && $info != 1) {
            return "JM A + B";
        }
        return "";
    }

    /** Gruppen eines Anlasses: «Gruppenname: Mitglied, Mitglied …», eine Gruppe pro Zeile. */
    private function getGruppenText($eid, $gruppenMapping) {
        $zeilen = [];
        if (!empty($gruppenMapping[$eid])) {
            foreach ($gruppenMapping[$eid] as $gdata) {
                $zeilen[] = '<strong>' . htmlspecialchars($gdata['Gruppenname']) . ':</strong> '
                          . htmlspecialchars(implode(', ', $gdata['members']));
            }
        }
        return implode('<br>', $zeilen);
    }

    private function buildWichtigeTermine($termine) {
        if (empty($termine)) {
            return '';
        }

        $html = '<div class="mb-block"><h2 class="rl-abschnitt mb-monat">Wichtige Termine</h2>'
            . '<table class="table mb"><thead><tr>'
            . '<th class="zeit">Datum</th><th class="zeit">Zeit</th><th>Termin</th>'
            . '</tr></thead><tbody>';

        foreach ($termine as $termin) {
            $ts = strtotime($termin['date']);
            $weekday = date('D', $ts);
            $weekdayDe = $this->deDays[$weekday] ?? $weekday;
            $html .= '<tr>'
                . '<td class="zeit">' . $weekdayDe . ' ' . date('d.m.Y', $ts) . '</td>'
                . '<td class="zeit">' . htmlspecialchars((string)$termin['time']) . '</td>'
                . '<td>' . htmlspecialchars((string)$termin['name']) . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table></div>';
    }

    private function buildBemerkungen() {
        return '<div class="mb-block"><h2 class="rl-abschnitt mb-monat">Bemerkungen</h2>'
            . '<p class="mb-bemerkung">' . nl2br($this->bemerkung) . '</p></div>';
    }

    /** «Samstag, 18. April» */
    private function formatDay($ts) {
        $wd = date('l', $ts);
        $wdD = $this->wdEnToDe[$wd] ?? $wd;
        return $wdD . ', ' . (int)date('j', $ts) . '. ' . $this->monthDe[(int)date('n', $ts)];
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

    /**
     * A5-PDF rendern (Fusszeile ab Seite 2) und standardmässig als Broschüre ausschiessen:
     * zwei A5-Seiten pro A4-Blatt quer, Reihenfolge fürs Falten, aufgefüllt auf ein Vielfaches von 4
     * (Leerseiten am Schluss = leere Rückseite). Drucken: beidseitig, Wenden an der KURZEN Seite.
     * POST broschuere=0 liefert nur die A5-Seiten. Gestreamt wird direkt (kein Speichern in dat/).
     */
    private function createPDF($htmlContent) {
        $options = new Options();
        $options->set('isRemoteEnabled', false); // Logo ist als Data-URI eingebettet
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();
        pdf_seitenfuss($dompdf, 'Schiesszeiten ' . $this->year, 2);

        $pdf = $dompdf->output();
        $seiten = $dompdf->getCanvas()->get_page_count();
        $art = 'A5';
        if (($_POST['broschuere'] ?? '1') !== '0') {
            $broschuere = $this->ausschiessen($pdf, $seiten);
            if ($broschuere !== null) {
                $pdf = $broschuere;
                $art = 'Broschuere';
            }
        }

        $filename = sprintf('Monatsblatt_%d_%02d-%02d_%s_%s.pdf', $this->year, $this->startMonth, $this->endMonth, $art, date('d.m.Y'));

        // PDF direkt als Download ausgeben. Puffer leeren, damit keine Vor-Ausgabe das Binär-PDF beschädigt.
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /** Broschüre mit pdfjam (wie das Absendenbuch). Bei einem Fehler null (dann gehen die A5-Seiten raus, Log-Eintrag). */
    private function ausschiessen(string $pdfA5, int $seiten): ?string {
        $ein = tempnam(sys_get_temp_dir(), 'mbA5_');
        $aus = $ein . '_broschuere.pdf';
        try {
            $fh = fopen($ein, 'wb');
            fwrite($fh, $pdfA5);
            fclose($fh);
            $signatur = max(4, (int)(ceil($seiten / 4) * 4));
            $home = getenv('HOME') ?: dirname(__DIR__, 4); // /home/<user> oberhalb von www/<docroot>/inc/monatsblatt
            $env = 'HOME=' . escapeshellarg($home) . ' PATH=/usr/local/bin:/usr/bin:/bin TMPDIR=' . escapeshellarg(sys_get_temp_dir()) . ' ';
            $cmd = $env . 'pdfjam --booklet true --landscape --paper a4paper --signature ' . $signatur . ' --quiet '
                 . escapeshellarg($ein) . ' -o ' . escapeshellarg($aus) . ' 2>&1';
            $out = [];
            $rc = 0;
            exec($cmd, $out, $rc);
            if ($rc !== 0 || !is_file($aus) || filesize($aus) < 1000) {
                error_log('[export_monatsblatt] Ausschiessen fehlgeschlagen (rc=' . $rc . '): ' . trim(implode(' ', array_slice($out, -3))));
                return null;
            }
            return (string)file_get_contents($aus);
        } finally {
            @unlink($ein);
            @unlink($aus);
        }
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

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e);

} catch (Throwable $e) {
    error_log('[export_monatsblatt] ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e);

} finally {
    if (isset($conn)) {
        $conn->close();
    }
}