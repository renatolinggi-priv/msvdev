<?php
include '../config.php';

function generateICS($veranstaltung, $beschreibung, $schiesstage, $includeCalendarTags = true) {
    // Schiesstage-Daten verarbeiten
    $termine = parseSchiesstage($schiesstage);

    // ICS-Header (falls aktiviert)
    $ics = "";
    if ($includeCalendarTags) {
        $ics .= "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Schlossturmschiessen//Kalenderexport//DE\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
    }

    foreach ($termine as $termin) {
        $start = date("Ymd\THis", strtotime($termin["date"] . " " . $termin["start"]));
        $end = date("Ymd\THis", strtotime($termin["date"] . " " . $termin["end"]));

        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . md5($termin["date"] . $termin["start"] . uniqid()) . "\r\n";
        $ics .= "DTSTAMP:" . date("Ymd\THis\Z") . "\r\n";
        $ics .= "DTSTART;TZID=Europe/Berlin:$start\r\n";
        $ics .= "DTEND;TZID=Europe/Berlin:$end\r\n";
        $ics .= "SUMMARY:$veranstaltung\r\n";
        $ics .= "DESCRIPTION:$beschreibung\r\n";
        $ics .= "END:VEVENT\r\n";
    }

    if ($includeCalendarTags) {
        $ics .= "END:VCALENDAR\r\n";
    }

    return $ics;
}

// Schiesstage-Parser-Funktion (bleibt unverändert)
function parseSchiesstage($input) {
    $input = str_replace("\xe2\x80\x93", "-", $input); // UTF-8 Gedankenstrich ersetzen
    $lines = explode("\n", $input);
    $termine = [];

    foreach ($lines as $line) {
        if (preg_match('/(\w+)\s+(\d{1,2})\.\s+(\w+)\s+(\d{4})\s+(.*)/u', $line, $matches)) {
            $day = $matches[2];
            $month = $matches[3];
            $year = $matches[4];
            $times = $matches[5];

            $months = [
                'Januar' => '01', 'Februar' => '02', 'März' => '03', 'April' => '04',
                'Mai' => '05', 'Juni' => '06', 'Juli' => '07', 'August' => '08',
                'September' => '09', 'Oktober' => '10', 'November' => '11', 'Dezember' => '12'
            ];

            if (!isset($months[$month])) {
                continue;
            }
            $month = $months[$month];

            $date = "$year-$month-$day";

            if (preg_match_all('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/u', $times, $timeMatches, PREG_SET_ORDER)) {
                foreach ($timeMatches as $time) {
                    $startTime = $time[1];
                    $endTime = $time[2];

                    $termine[] = [
                        "date" => $date,
                        "start" => $startTime,
                        "end" => $endTime
                    ];
                }
            }
        }
    }

    return $termine;
}
?>
