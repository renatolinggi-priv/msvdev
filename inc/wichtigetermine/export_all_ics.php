<?php
// export_all_ics.php – Wichtige Termine eines Jahres als ICS-Datei.
// Review 09.2026: Jahr wird als Zahl geprueft (vorher roh im Dateinamen -> Path-Traversal),
// Zeiten werden tolerant geparst ("18:00 - 20:00", "18.00-20.00", "18:00", leer -> ganztags).

include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');

header('Content-Type: application/json; charset=utf-8');

$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($currentYear < 2000 || $currentYear > 2100) $currentYear = (int)date('Y');

$filename   = 'wichtige_termine_' . $currentYear . '_' . date('Y-m-d_H-i-s') . '.ics';
$outputPath = __DIR__ . '/dat/' . $filename;

$stmt = $conn->prepare("SELECT name, date, time FROM wichtige_termine WHERE year = ? ORDER BY date");
$stmt->bind_param('i', $currentYear);
$stmt->execute();
$result = $stmt->get_result();

function icsEscapeText(string $text): string {
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace([';', ','], ['\;', '\,'], $text);
    return str_replace(["\r\n", "\n"], '\\n', $text);
}

$ics  = "BEGIN:VCALENDAR\r\n";
$ics .= "VERSION:2.0\r\n";
$ics .= "PRODID:-//Jahresmeisterschaft//Kalenderexport//DE\r\n";
$ics .= "CALSCALE:GREGORIAN\r\n";
$ics .= "METHOD:PUBLISH\r\n";

while ($row = $result->fetch_assoc()) {
    $name = (string)$row['name'];
    $date = (string)$row['date'];
    $time = trim((string)$row['time']);
    $dateTs = strtotime($date);
    if (!$dateTs) continue;
    $dayCompact = date('Ymd', $dateTs);

    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:" . md5($date . $time . $name) . "@wichtige_termine\r\n";
    $ics .= "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";

    if (preg_match('/(\d{1,2})[:.](\d{2})\s*[-–]\s*(\d{1,2})[:.](\d{2})/u', $time, $m)) {
        // Zeitspanne
        $ics .= "DTSTART;TZID=Europe/Zurich:" . $dayCompact . 'T' . sprintf('%02d%02d00', (int)$m[1], (int)$m[2]) . "\r\n";
        $ics .= "DTEND;TZID=Europe/Zurich:"   . $dayCompact . 'T' . sprintf('%02d%02d00', (int)$m[3], (int)$m[4]) . "\r\n";
    } elseif (preg_match('/(\d{1,2})[:.](\d{2})/u', $time, $m)) {
        // Nur Startzeit -> eine Stunde
        $start = mktime((int)$m[1], (int)$m[2], 0, (int)date('n', $dateTs), (int)date('j', $dateTs), (int)date('Y', $dateTs));
        $ics .= "DTSTART;TZID=Europe/Zurich:" . date('Ymd\THis', $start) . "\r\n";
        $ics .= "DTEND;TZID=Europe/Zurich:"   . date('Ymd\THis', $start + 3600) . "\r\n";
    } else {
        // Keine Zeit -> ganztaegig
        $ics .= "DTSTART;VALUE=DATE:" . $dayCompact . "\r\n";
        $ics .= "DTEND;VALUE=DATE:"   . date('Ymd', $dateTs + 86400) . "\r\n";
    }

    $ics .= "SUMMARY:" . icsEscapeText($name) . "\r\n";
    $ics .= "DESCRIPTION:" . icsEscapeText('Wichtiger Termin - ' . $name . ($time !== '' ? ' (' . $time . ')' : '')) . "\r\n";
    $ics .= "END:VEVENT\r\n";
}
$ics .= "END:VCALENDAR\r\n";

$stmt->close();
$conn->close();

if (file_put_contents($outputPath, $ics) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'ICS-Datei konnte nicht gespeichert werden']);
    exit;
}

echo json_encode([
    'success'  => true,
    'ics_link' => 'wichtigetermine/dat/' . $filename,
]);
