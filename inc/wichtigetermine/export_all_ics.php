<?php
// export_all_ics.php

include '../config.php';

// Aktuelles Jahr berechnen
$currentYear = isset($_GET['year']) ? $_GET['year'] : date("Y");

// Erstelle einen Dateinamen mit Zeitstempel
$date = new DateTime();
$filename = "wichtige_termine_{$currentYear}_" . $date->format('Y-m-d_H-i-s') . ".ics";

// Zielordner
$outputPath = "dat/" . $filename;

// DB-Abfrage: Wichtige Termine aus der Tabelle `wichtige_termine` abfragen
$sql2 = "SELECT name, date, time FROM wichtige_termine WHERE year = ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $currentYear);
$stmt2->execute();
$result2 = $stmt2->get_result();

// ICS-Datei Kopf
$ics = "BEGIN:VCALENDAR\r\n";
$ics .= "VERSION:2.0\r\n";
$ics .= "PRODID:-//Jahresmeisterschaft//Kalenderexport//DE\r\n";
$ics .= "CALSCALE:GREGORIAN\r\n";
$ics .= "METHOD:PUBLISH\r\n";

// Wichtige Termine hinzufügen
while ($row2 = $result2->fetch_assoc()) {
    $terminName = $row2['name'];
    $terminDate = $row2['date'];
    $terminTime = $row2['time'];

    // Erstelle den Start- und Endzeitpunkt
    $start = date("Ymd\THis", strtotime($terminDate . " " . substr($terminTime, 0, 5)));
    $end = date("Ymd\THis", strtotime($terminDate . " " . substr($terminTime, 7)));

    // Hinzufügen des Ereignisses in die ICS-Datei
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:" . md5($terminDate . $terminTime . $terminName) . "@wichtige_termine\r\n";
    $ics .= "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
    $ics .= "DTSTART;TZID=Europe/Berlin:$start\r\n";
    $ics .= "DTEND;TZID=Europe/Berlin:$end\r\n";
    $ics .= "SUMMARY:$terminName\r\n";
    $ics .= "DESCRIPTION:Wichtiger Termin - $terminName\r\n";
    $ics .= "END:VEVENT\r\n";
}

$ics .= "END:VCALENDAR\r\n";

$stmt2->close();
$conn->close();

// ICS-Datei speichern
file_put_contents($outputPath, $ics);

// JSON-Antwort zurückgeben
echo json_encode([
    'success' => true,
    'ics_link' => "wichtigetermine/dat/" . $filename
]);
exit();
?>
