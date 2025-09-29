<?php
header("Content-Type: text/calendar; charset=utf-8");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Ungültige Anfrage.");
}

$id = intval($_GET['id']);

include '../config.php';

// DB-Eintrag abrufen basierend auf der ID
$sql = "SELECT Bezeichnung, Schiesstage, Adresse 
        FROM JMDefinition 
        WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $veranstaltung = $row['Bezeichnung'];
    $schiesstage   = $row['Schiesstage'];
    $adresse       = $row['Adresse'];  // Hier holen wir die Adresse
} else {
    die("Kein passender Eintrag gefunden!");
}
$stmt->close();
$conn->close();

// Dateinamen aus der Bezeichnung generieren (Sonderzeichen entfernen)
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $veranstaltung) . ".ics";
header("Content-Disposition: attachment; filename=\"$filename\"");


// Hilfsfunktion zum Escapen von Zeichen für ICS
function icsEscape($text) {
    // Backslashes zuerst
    $text = str_replace('\\', '\\\\', $text);
    // Kommas escapen
    $text = str_replace(',', '\,', $text);
    // Semikolon escapen
    $text = str_replace(';', '\;', $text);
    // Zeilenumbrüche
    $text = str_replace("\n", '\\n', $text);
    return $text;
}

/**
 * Schiesstage verarbeiten.
 */
function parseSchiesstage($input) {
    // UTF-8 Gedankenstrich ersetzen
    $input = str_replace("\xe2\x80\x93", "-", $input);
    $lines = explode("\n", $input);
    $termine = [];
    $currentYear = date("Y");

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        // Wochentag + Tag + Monat (+ optional Jahr) + restliche Times
        if (preg_match('/(\w+)\s+(\d{1,2})\.\s+(\w+)(?:\s+(\d{4}))?\s+(.*)/u', $line, $matches)) {
            $day   = $matches[2];
            $month = $matches[3];
            $year  = !empty($matches[4]) ? $matches[4] : $currentYear;
            $times = $matches[5];

            $months = [
                'Januar' => '01','Februar' => '02','März' => '03','April' => '04',
                'Mai' => '05','Juni' => '06','Juli' => '07','August' => '08',
                'September' => '09','Oktober' => '10','November' => '11','Dezember' => '12'
            ];

            if (!isset($months[$month])) {
                continue;
            }
            $monthNum = $months[$month];

            $date = sprintf("%04d-%02d-%02d", $year, $monthNum, $day);
            if (!strtotime($date)) {
                error_log("FEHLER: Konnte Datum nicht verarbeiten: $date");
                continue;
            }

            // Zeitintervalle erkennen, z. B. "08:00 - 12:00" oder "08.00 - 12.00"
            if (preg_match_all('/(\d{1,2}[:\.]\d{2})\s*-\s*(\d{1,2}[:\.]\d{2})/u', $times, $timeMatches, PREG_SET_ORDER)) {
                foreach ($timeMatches as $time) {
                    $startTime = str_replace('.', ':', $time[1]);
                    $endTime   = str_replace('.', ':', $time[2]);

                    $termine[] = [
                        "date"  => $date,
                        "start" => $startTime,
                        "end"   => $endTime
                    ];
                }
            }
        }
    }
    return $termine;
}

$termine = parseSchiesstage($schiesstage);
$beschreibung = "Jahresmeisterschaft - " . $veranstaltung;

// ICS-Datei erstellen
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//Jahresmeisterschaft//Kalenderexport//DE\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";

foreach ($termine as $termin) {
    if (empty($termin["start"]) || empty($termin["end"])) {
        continue;
    }
    $start = date("Ymd\THis", strtotime($termin["date"] . " " . $termin["start"]));
    $end   = date("Ymd\THis", strtotime($termin["date"] . " " . $termin["end"]));

    // Für ICS Felder escapen:
    $summaryEscaped = icsEscape($veranstaltung);
    $descEscaped    = icsEscape($beschreibung);
    $addrEscaped    = icsEscape($adresse);  // Adresse escapen, wenn vorhanden

    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . md5($termin["date"] . $termin["start"]) . "@schlossturmschiessen\r\n";
    echo "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
    echo "DTSTART;TZID=Europe/Berlin:$start\r\n";
    echo "DTEND;TZID=Europe/Berlin:$end\r\n";
    echo "SUMMARY:$summaryEscaped\r\n";
    echo "DESCRIPTION:$descEscaped\r\n";

    // NEU: Falls Adresse vorhanden ist, LOCATION einfügen
    if (!empty($addrEscaped)) {
        echo "LOCATION:$addrEscaped\r\n";
    }

    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";
exit;
?>