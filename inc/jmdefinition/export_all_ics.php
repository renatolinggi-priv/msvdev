<?php
// export_all_ics.php

include '../config.php';

// Aktuelles Jahr berechnen
$currentYear = date("Y");

// Erstelle einen Dateinamen mit Zeitstempel
$date = new DateTime();
$filename = "Jahresmeisterschaft_{$currentYear}_" . $date->format('Y-m-d_H-i-s') . ".ics";

// Zielordner
$outputPath = "dat/" . $filename;

/**
 * Funktion zur Verarbeitung der Schiesstage
 * Erweitert um die Erkennung von Zeiten mit ":" ODER "." (z.B. "08.00" - "12.00")
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
                error_log("FEHLER: Konnte Datum nicht verarbeiten: $dateStr");
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

// DB-Abfrage: Alle Einträge des aktuellen Jahres mit Schiesstage != null
$sql = "SELECT Bezeichnung, Schiesstage, Adresse FROM JMDefinition WHERE year = ? AND Schiesstage IS NOT NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $currentYear);
$stmt->execute();
$result = $stmt->get_result();

// ICS-Inhalt erzeugen
$ics = "BEGIN:VCALENDAR\r\n";
$ics .= "VERSION:2.0\r\n";
$ics .= "PRODID:-//Jahresmeisterschaft//Kalenderexport//DE\r\n";
$ics .= "CALSCALE:GREGORIAN\r\n";
$ics .= "METHOD:PUBLISH\r\n";

while ($row = $result->fetch_assoc()) {
    $veranstaltung = $row['Bezeichnung'];
    $schiesstage   = $row['Schiesstage'];
    $adresse       = $row['Adresse'];
    $termine       = parseSchiesstage($schiesstage);

    foreach ($termine as $termin) {
        if (empty($termin["start"]) || empty($termin["end"])) {
            continue;
        }
        $start = date("Ymd\THis", strtotime($termin["date"] . " " . $termin["start"]));
        $end   = date("Ymd\THis", strtotime($termin["date"] . " " . $termin["end"]));

        $ics .= "BEGIN:VEVENT\r\n";
        // Eindeutige ID
        $ics .= "UID:" . md5($termin["date"] . $termin["start"] . $veranstaltung) . "@jahresmeisterschaft\r\n";
        // Zeitstempel
        $ics .= "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
        // Start/End
        $ics .= "DTSTART;TZID=Europe/Berlin:$start\r\n";
        $ics .= "DTEND;TZID=Europe/Berlin:$end\r\n";
        // Summary/Description
        $ics .= "SUMMARY:$veranstaltung\r\n";
        $ics .= "DESCRIPTION:Jahresmeisterschaft - $veranstaltung\r\n";
        if (!empty($adresse)) {
            $adresseEscaped = icsEscape($adresse);
            $ics .= "LOCATION:$adresseEscaped\r\n";
        }
        $ics .= "END:VEVENT\r\n";
    }
}

$ics .= "END:VCALENDAR\r\n";

$stmt->close();
$conn->close();

// ICS-Datei speichern
file_put_contents($outputPath, $ics);

// JSON-Antwort zurückgeben
echo json_encode([
    'success'  => true,
    'ics_link' => "jmdefinition/dat/" . $filename
]);
exit();


function icsEscape($text) {
    $text = str_replace('\\', '\\\\', $text);  // Backslashes zuerst ersetzen
    $text = str_replace(';', '\;', $text);
    $text = str_replace(',', '\,', $text);
    $text = str_replace("\n", '\\n', $text);
    return $text;
}

?>