<?php
/**
 * Kalender-Feed für MSV Wilen
 * Diese Datei kann von Mitgliedern direkt in ihren Kalender-Apps abonniert werden
 * URL: https://jahresmeisterschaft.msvwilen.ch/termine
 */

include 'inc/config.php';

// Aktuelles Jahr und Folgejahr
$currentYear = date("Y");
$nextYear = $currentYear + 1;

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

/**
 * Prüft ob ein String Koordinaten enthält (z.B. "47.2034, 8.7812")
 * Gibt Array [lat, lon] zurück oder false
 */
function parseCoordinates($text) {
    $text = trim($text);
    // Format: "47.2034, 8.7812" oder "47.2034,8.7812" oder "47.2034; 8.7812" oder "47.3166/8.8206"
    // Akzeptiert: Komma, Semikolon, Schrägstrich als Trennzeichen
    if (preg_match('/^(-?\d+\.\d+)\s*[,;\/]\s*(-?\d+\.\d+)$/', $text, $matches)) {
        $lat = floatval($matches[1]);
        $lon = floatval($matches[2]);
        // Plausibilitätsprüfung
        if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
            return ['lat' => $lat, 'lon' => $lon];
        }
    }
    return false;
}

// ICS-Ausgabe starten
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//MSV Wilen//Jahreskalender//DE\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:MSV Jahreskalender\r\n";
echo "X-WR-CALDESC:Jahresprogramm und wichtige Termine MSV Wilen\r\n";
echo "X-WR-TIMEZONE:Europe/Zurich\r\n";
// Empfehlung für Aktualisierungsintervall (1 Stunde)
echo "REFRESH-INTERVAL;VALUE=DURATION:PT1H\r\n";
echo "X-PUBLISHED-TTL:PT1H\r\n";

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
// 1. Events aus JMDefinition (Jahresmeisterschaft)
// ============================================
$sql = "SELECT ID, Bezeichnung, Schiesstage, Adresse, year, Info 
        FROM JMDefinition 
        WHERE year IN (?, ?) 
        AND Schiesstage IS NOT NULL 
        AND Schiesstage != ''
        ORDER BY year, Reihenfolge";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $currentYear, $nextYear);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $veranstaltung = $row['Bezeichnung'];
    // Bezeichnung anpassen: "Endstich" -> "MSV Wilen Endschiessen"
    if (trim($veranstaltung) === 'Endstich') {
        $veranstaltung = 'MSV Wilen Endschiessen';
    }
    $schiesstage   = $row['Schiesstage'];
    $adresse       = $row['Adresse'];
    $jmId          = $row['ID'];
    $isInfo        = $row['Info'];
    $termine       = parseSchiesstage($schiesstage);
    
    // Erinnerung: Info-Termine 1 Woche, normale Schiessen 3 Tage vorher
    $reminderDays  = $isInfo ? 7 : 3;
    $reminderText  = $isInfo ? "In einer Woche" : "In 3 Tagen";

    foreach ($termine as $index => $termin) {
        if (empty($termin["start"]) || empty($termin["end"])) {
            continue;
        }
        
        $start = date("Ymd\THis", strtotime($termin["date"] . " " . $termin["start"]));
        $end   = date("Ymd\THis", strtotime($termin["date"] . " " . $termin["end"]));
        
        // Stabile UID generieren (basierend auf JM-ID, Datum und Index)
        $uid = "jm-{$jmId}-" . str_replace('-', '', $termin["date"]) . "-{$index}@msvwilen.ch";

        echo "BEGIN:VEVENT\r\n";
        echo "UID:{$uid}\r\n";
        echo "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
        echo "DTSTART;TZID=Europe/Zurich:{$start}\r\n";
        echo "DTEND;TZID=Europe/Zurich:{$end}\r\n";
        echo "SUMMARY:" . icsEscape($veranstaltung) . "\r\n";
        echo "DESCRIPTION:Jahresmeisterschaft - " . icsEscape($veranstaltung) . "\r\n";
        
        if (!empty($adresse)) {
            $coords = parseCoordinates($adresse);
            if ($coords) {
                // Mindestens 6 Dezimalstellen für Apple Kalender Kompatibilität
                $lat = number_format($coords['lat'], 6, '.', '');
                $lon = number_format($coords['lon'], 6, '.', '');
                // GEO für Standard-Kalender
                echo "GEO:{$lat};{$lon}\r\n";
                // LOCATION für Anzeige in iOS (Koordinaten als Text)
                echo "LOCATION:" . icsEscape("{$lat}, {$lon}") . "\r\n";
                // Apple-spezifisches Format für Kartenanzeige
                echo "X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-RADIUS=100;X-TITLE={$lat}\\, {$lon}:geo:{$lat},{$lon}\r\n";
            } else {
                echo "LOCATION:" . icsEscape($adresse) . "\r\n";
            }
        }
        
        echo "SEQUENCE:0\r\n";
        echo "STATUS:CONFIRMED\r\n";
        
        // Erinnerung hinzufügen
        echo "BEGIN:VALARM\r\n";
        echo "TRIGGER:-P{$reminderDays}D\r\n";
        echo "ACTION:DISPLAY\r\n";
        echo "DESCRIPTION:{$reminderText}: " . icsEscape($veranstaltung) . "\r\n";
        echo "END:VALARM\r\n";
        
        echo "END:VEVENT\r\n";
    }
}
$stmt->close();

// ============================================
// 2. Events aus wichtige_termine
// ============================================
$sql = "SELECT ID, name, date, time, year 
        FROM wichtige_termine 
        WHERE year IN (?, ?)
        ORDER BY date, time";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $currentYear, $nextYear);
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
        
        $uid = "wt-{$wtId}-" . str_replace('-', '', $date) . "@msvwilen.ch";
        
        echo "BEGIN:VEVENT\r\n";
        echo "UID:{$uid}\r\n";
        echo "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
        echo "DTSTART;TZID=Europe/Zurich:{$start}\r\n";
        echo "DTEND;TZID=Europe/Zurich:{$end}\r\n";
        echo "SUMMARY:" . icsEscape($name) . "\r\n";
        echo "DESCRIPTION:Wichtiger Termin - " . icsEscape($name) . "\r\n";
        echo "SEQUENCE:0\r\n";
        echo "STATUS:CONFIRMED\r\n";
        
        echo "BEGIN:VALARM\r\n";
        echo "TRIGGER:-P7D\r\n";
        echo "ACTION:DISPLAY\r\n";
        echo "DESCRIPTION:In einer Woche: " . icsEscape($name) . "\r\n";
        echo "END:VALARM\r\n";
        
        echo "END:VEVENT\r\n";
    } else {
        // Falls keine Zeit angegeben ist, als Ganztages-Event
        $dateFormatted = date("Ymd", strtotime($date));
        $uid = "wt-{$wtId}-" . str_replace('-', '', $date) . "@msvwilen.ch";
        
        echo "BEGIN:VEVENT\r\n";
        echo "UID:{$uid}\r\n";
        echo "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
        echo "DTSTART;VALUE=DATE:{$dateFormatted}\r\n";
        echo "SUMMARY:" . icsEscape($name) . "\r\n";
        echo "DESCRIPTION:Wichtiger Termin - " . icsEscape($name) . "\r\n";
        echo "SEQUENCE:0\r\n";
        echo "STATUS:CONFIRMED\r\n";
        
        echo "BEGIN:VALARM\r\n";
        echo "TRIGGER:-P7D\r\n";
        echo "ACTION:DISPLAY\r\n";
        echo "DESCRIPTION:In einer Woche: " . icsEscape($name) . "\r\n";
        echo "END:VALARM\r\n";
        
        echo "END:VEVENT\r\n";
    }
}
$stmt->close();

// ============================================
// 3. Events aus Standbelegung (nur InKalender = 1)
// ============================================
$sql = "SELECT ID, Datum, Wochentag, Bezeichnung, StartZeit, EndZeit, Kategorie, Jahr 
        FROM Standbelegung 
        WHERE Jahr IN (?, ?) AND InKalender = 1
        ORDER BY Datum, StartZeit";
        
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $currentYear, $nextYear);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $sbId        = $row['ID'];
        $datum       = $row['Datum'];
        $bezeichnung = $row['Bezeichnung'];
        $startZeit   = $row['StartZeit'];
        $endZeit     = $row['EndZeit'];
        $kategorie   = $row['Kategorie'];
        
        // UID generieren
        $uid = "sb-{$sbId}-" . str_replace('-', '', $datum) . "@msvwilen.ch";
        
        // Kategorie-Tag für Description
        $kategorieTag = !empty($kategorie) ? " [{$kategorie}]" : "";
        
        if (!empty($startZeit) && !empty($endZeit)) {
            // Event mit Uhrzeit
            $start = date("Ymd\THis", strtotime($datum . " " . $startZeit));
            $end   = date("Ymd\THis", strtotime($datum . " " . $endZeit));
            
            echo "BEGIN:VEVENT\r\n";
            echo "UID:{$uid}\r\n";
            echo "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
            echo "DTSTART;TZID=Europe/Zurich:{$start}\r\n";
            echo "DTEND;TZID=Europe/Zurich:{$end}\r\n";
            echo "SUMMARY:" . icsEscape($bezeichnung) . "\r\n";
            echo "DESCRIPTION:Standbelegung{$kategorieTag} - " . icsEscape($bezeichnung) . "\r\n";
            echo "CATEGORIES:" . icsEscape($kategorie) . "\r\n";
            echo "SEQUENCE:0\r\n";
            echo "STATUS:CONFIRMED\r\n";
            
            // Erinnerung: 1 Tag vorher
            echo "BEGIN:VALARM\r\n";
            echo "TRIGGER:-P1D\r\n";
            echo "ACTION:DISPLAY\r\n";
            echo "DESCRIPTION:Morgen: " . icsEscape($bezeichnung) . "\r\n";
            echo "END:VALARM\r\n";
            
            echo "END:VEVENT\r\n";
        } else {
            // Ganztages-Event falls keine Zeit
            $dateFormatted = date("Ymd", strtotime($datum));
            
            echo "BEGIN:VEVENT\r\n";
            echo "UID:{$uid}\r\n";
            echo "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
            echo "DTSTART;VALUE=DATE:{$dateFormatted}\r\n";
            echo "SUMMARY:" . icsEscape($bezeichnung) . "\r\n";
            echo "DESCRIPTION:Standbelegung{$kategorieTag} - " . icsEscape($bezeichnung) . "\r\n";
            echo "CATEGORIES:" . icsEscape($kategorie) . "\r\n";
            echo "SEQUENCE:0\r\n";
            echo "STATUS:CONFIRMED\r\n";
            
            echo "BEGIN:VALARM\r\n";
            echo "TRIGGER:-P1D\r\n";
            echo "ACTION:DISPLAY\r\n";
            echo "DESCRIPTION:Morgen: " . icsEscape($bezeichnung) . "\r\n";
            echo "END:VALARM\r\n";
            
            echo "END:VEVENT\r\n";
        }
    }
    $stmt->close();
}

echo "END:VCALENDAR\r\n";

$conn->close();
?>
