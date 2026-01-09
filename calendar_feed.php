<?php
/**
 * Kalender-Feed für Jahresmeisterschaft
 * Diese Datei kann von Mitgliedern direkt in ihren Kalender-Apps abonniert werden
 * URL: https://deine-domain.ch/calendar_feed.php
 */

include 'inc/config.php';

// Aktuelles Jahr
$currentYear = date("Y");

// ICS Header setzen
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="MSV_Jahreskalender.ics"');

/**
 * Funktion zur Verarbeitung der Schiesstage (aus export_all_ics.php übernommen)
 */
function parseSchiesstage($input) {
    // UTF-8 Gedankenstrich ersetzen durch normalen Bindestrich
    $input = str_replace("\xe2\x80\x93", "-", $input);
    // Zeilen aufsplitten
    $lines = explode("\n", $input);
    $termine = [];
    // Default-Jahr = aktuelles Jahr (falls in Zeile kein Jahr angegeben)
    $currentYear = date("Y");

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        /**
         * Regex: Erfasst 
         * 1) ein Wochentag \w+ (optional, kann auch "Samstag" sein),
         * 2) Tag (\d{1,2}).
         * 3) Monat (z. B. "April")  => (\w+)
         * 4) optional year (\d{4})
         * 5) rest times => (.*)
         */
        if (preg_match('/(\w+)\s+(\d{1,2})\.\s+(\w+)(?:\s+(\d{4}))?\s+(.*)/u', $line, $matches)) {
            $day   = $matches[2]; // z.B. "12"
            $month = $matches[3]; // z.B. "April"
            $year  = !empty($matches[4]) ? $matches[4] : $currentYear;
            $times = $matches[5]; // z.B. "08:00 - 12:00 Uhr"

            // Mapping deutscher Monatsname => Ziffer
            $months = [
                'Januar' => '01', 'Februar' => '02', 'März' => '03', 'April' => '04',
                'Mai' => '05', 'Juni' => '06', 'Juli' => '07', 'August' => '08',
                'September' => '09', 'Oktober' => '10', 'November' => '11', 'Dezember' => '12'
            ];

            if (!isset($months[$month])) {
                continue; // unbekannter Monat => skip
            }
            $monthNum = $months[$month];

            // Datums-String
            $dateStr = sprintf("%04d-%02d-%02d", $year, $monthNum, $day);
            // Prüfen, ob Datum valide ist
            if (!strtotime($dateStr)) {
                continue;
            }

            /**
             * Regex für Zeit-Intervalle:
             * (\d{1,2}[:\.]\d{2}) - (\d{1,2}[:\.]\d{2})
             * => Erfasst z.B. "08:00 - 12:00" oder "08.00 - 12.00"
             */
            if (preg_match_all('/(\d{1,2}[:\.]\d{2})\s*-\s*(\d{1,2}[:\.]\d{2})/u', $times, $timeMatches, PREG_SET_ORDER)) {
                foreach ($timeMatches as $time) {
                    $startTime = $time[1]; // z.B. "08.00"
                    $endTime   = $time[2]; // z.B. "12.00"

                    // Ersetzung "." => ":" 
                    $startTime = str_replace('.', ':', $startTime);
                    $endTime   = str_replace('.', ':', $endTime);

                    $termine[] = [
                        "date"  => $dateStr,
                        "start" => $startTime,
                        "end"   => $endTime
                    ];
                }
            }
        }
    }
    return $termine;
}

/**
 * ICS-Escape-Funktion für Sonderzeichen
 */
function icsEscape($text) {
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(';', '\;', $text);
    $text = str_replace(',', '\,', $text);
    $text = str_replace("\n", '\\n', $text);
    return $text;
}

// ICS-Ausgabe starten
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//MSV Jahresmeisterschaft//Kalender-Feed//DE\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:MSV Jahreskalender {$currentYear}\r\n";
echo "X-WR-CALDESC:Jahresprogramm und wichtige Termine\r\n";
echo "X-WR-TIMEZONE:Europe/Zurich\r\n";

// Timezone-Definition für Europa/Zürich
echo "BEGIN:VTIMEZONE\r\n";
echo "TZID:Europe/Zurich\r\n";
echo "BEGIN:STANDARD\r\n";
echo "DTSTART:19701025T030000\r\n";
echo "RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10\r\n";
echo "TZOFFSETFROM:+0200\r\n";
echo "TZOFFSETTO:+0100\r\n";
echo "END:STANDARD\r\n";
echo "BEGIN:DAYLIGHT\r\n";
echo "DTSTART:19700329T020000\r\n";
echo "RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3\r\n";
echo "TZOFFSETFROM:+0100\r\n";
echo "TZOFFSETTO:+0200\r\n";
echo "END:DAYLIGHT\r\n";
echo "END:VTIMEZONE\r\n";

// ============================================
// 1. Events aus JMDefinition
// ============================================
$sql = "SELECT ID, Bezeichnung, Schiesstage, Adresse, year 
        FROM JMDefinition 
        WHERE year = ? 
        AND Schiesstage IS NOT NULL 
        AND Schiesstage != ''
        AND Info = 0
        ORDER BY Reihenfolge";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $currentYear);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $veranstaltung = $row['Bezeichnung'];
    $schiesstage   = $row['Schiesstage'];
    $adresse       = $row['Adresse'];
    $jmId          = $row['ID'];
    $termine       = parseSchiesstage($schiesstage);

    foreach ($termine as $index => $termin) {
        if (empty($termin["start"]) || empty($termin["end"])) {
            continue;
        }
        
        $start = date("Ymd\THis", strtotime($termin["date"] . " " . $termin["start"]));
        $end   = date("Ymd\THis", strtotime($termin["date"] . " " . $termin["end"]));
        
        // Stabile UID generieren (basierend auf JM-ID, Datum und Index)
        $uid = "jm-{$jmId}-" . str_replace('-', '', $termin["date"]) . "-{$index}@msvjm.ch";

        echo "BEGIN:VEVENT\r\n";
        echo "UID:{$uid}\r\n";
        echo "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
        echo "DTSTART;TZID=Europe/Zurich:{$start}\r\n";
        echo "DTEND;TZID=Europe/Zurich:{$end}\r\n";
        echo "SUMMARY:" . icsEscape($veranstaltung) . "\r\n";
        echo "DESCRIPTION:Jahresmeisterschaft - " . icsEscape($veranstaltung) . "\r\n";
        
        if (!empty($adresse)) {
            echo "LOCATION:" . icsEscape($adresse) . "\r\n";
        }
        
        // SEQUENCE für Updates (hier erstmal 0, könnte später erhöht werden)
        echo "SEQUENCE:0\r\n";
        echo "STATUS:CONFIRMED\r\n";
        echo "END:VEVENT\r\n";
    }
}
$stmt->close();

// ============================================
// 2. Events aus wichtige_termine
// ============================================
$sql = "SELECT ID, name, date, time, year 
        FROM wichtige_termine 
        WHERE year = ?
        ORDER BY date, time";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $currentYear);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $name  = $row['name'];
    $date  = $row['date'];
    $time  = $row['time'];
    $wtId  = $row['ID'];
    
    // Zeit parsen (Format: "17.30 - 19.30" oder "18:00 - 20:00")
    if (preg_match('/(\d{1,2}[:\.]\d{2})\s*-\s*(\d{1,2}[:\.]\d{2})/u', $time, $matches)) {
        $startTime = str_replace('.', ':', $matches[1]);
        $endTime   = str_replace('.', ':', $matches[2]);
        
        $start = date("Ymd\THis", strtotime($date . " " . $startTime));
        $end   = date("Ymd\THis", strtotime($date . " " . $endTime));
        
        // Stabile UID generieren
        $uid = "wt-{$wtId}-" . str_replace('-', '', $date) . "@msvjm.ch";
        
        echo "BEGIN:VEVENT\r\n";
        echo "UID:{$uid}\r\n";
        echo "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
        echo "DTSTART;TZID=Europe/Zurich:{$start}\r\n";
        echo "DTEND;TZID=Europe/Zurich:{$end}\r\n";
        echo "SUMMARY:" . icsEscape($name) . "\r\n";
        echo "DESCRIPTION:Wichtiger Termin - " . icsEscape($name) . "\r\n";
        echo "SEQUENCE:0\r\n";
        echo "STATUS:CONFIRMED\r\n";
        echo "END:VEVENT\r\n";
    } else {
        // Falls keine Zeit angegeben ist, als Ganztages-Event
        $dateFormatted = date("Ymd", strtotime($date));
        $uid = "wt-{$wtId}-" . str_replace('-', '', $date) . "@msvjm.ch";
        
        echo "BEGIN:VEVENT\r\n";
        echo "UID:{$uid}\r\n";
        echo "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
        echo "DTSTART;VALUE=DATE:{$dateFormatted}\r\n";
        echo "SUMMARY:" . icsEscape($name) . "\r\n";
        echo "DESCRIPTION:Wichtiger Termin - " . icsEscape($name) . "\r\n";
        echo "SEQUENCE:0\r\n";
        echo "STATUS:CONFIRMED\r\n";
        echo "END:VEVENT\r\n";
    }
}
$stmt->close();

echo "END:VCALENDAR\r\n";

$conn->close();
?>
