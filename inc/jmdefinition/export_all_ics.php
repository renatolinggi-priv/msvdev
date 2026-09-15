<?php
// export_all_ics.php

include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/jmdefinition_helpers.inc.php';

header('Content-Type: application/json; charset=utf-8');

// Jahr aus dem Dropdown der Seite (vorher hart date('Y') -> andere Jahre nicht exportierbar)
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($currentYear < 2000 || $currentYear > 2100) $currentYear = (int)date('Y');

// Erstelle einen Dateinamen mit Zeitstempel
$date = new DateTime();
$filename = "Jahresmeisterschaft_{$currentYear}_" . $date->format('Y-m-d_H-i-s') . ".ics";

// Zielordner
$outputPath = "dat/" . $filename;

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
    // Bezeichnung anpassen: "Endstich" -> "MSV Wilen Endschiessen"
    if (trim($veranstaltung) === 'Endstich') {
        $veranstaltung = 'MSV Wilen Endschiessen';
    }
    $schiesstage   = $row['Schiesstage'];
    $adresse       = $row['Adresse'];
    $termine       = jm_parse_schiesstage((string)$schiesstage, $currentYear); // gemeinsamer Parser

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
            $coords = parseCoordinates($adresse);
            if ($coords) {
                // Mindestens 6 Dezimalstellen für Apple Kalender Kompatibilität
                $lat = number_format($coords['lat'], 6, '.', '');
                $lon = number_format($coords['lon'], 6, '.', '');
                $ics .= "GEO:{$lat};{$lon}\r\n";
                $ics .= "LOCATION:" . icsEscape("{$lat}, {$lon}") . "\r\n";
                $ics .= "X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-APPLE-RADIUS=100;X-TITLE={$lat}\\, {$lon}:geo:{$lat},{$lon}\r\n";
            } else {
                $adresseEscaped = icsEscape($adresse);
                $ics .= "LOCATION:$adresseEscaped\r\n";
            }
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

/**
 * Prüft ob ein String Koordinaten enthält (z.B. "47.2034, 8.7812")
 */
function parseCoordinates($text) {
    $text = trim($text);
    // Akzeptiert: Komma, Semikolon, Schrägstrich als Trennzeichen
    if (preg_match('/^(-?\d+\.\d+)\s*[,;\/]\s*(-?\d+\.\d+)$/', $text, $matches)) {
        $lat = floatval($matches[1]);
        $lon = floatval($matches[2]);
        if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
            return ['lat' => $lat, 'lon' => $lon];
        }
    }
    return false;
}

?>