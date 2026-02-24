<?php
/**
 * Persönlicher Einsatz-Kalender-Feed (ICS)
 * URL: https://mitglieder.msvwilen.ch/einsatz_feed.php?token=<calendar_token>
 *
 * Kein Login erforderlich — Token identifiziert den User.
 * Token wird in portal/kalender_abo.php generiert.
 */

include 'inc/config.php';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="Meine_Einsaetze.ics"');
// Kein Caching bei Kalender-Feeds
header('Cache-Control: no-store, no-cache, must-revalidate');

function icsEscape($text) {
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(';', '\;', $text);
    $text = str_replace(',', '\,', $text);
    $text = str_replace("\n", '\\n', $text);
    return $text;
}

$token = trim($_GET['token'] ?? '');

// Token validieren und mitglied_id ermitteln
$mitglied_id = null;
$user_name   = '';

if ($token !== '') {
    $stmt = $conn->prepare("SELECT mitglied_id, full_name FROM users WHERE calendar_token = ? AND status = 'approved' LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->bind_result($mitglied_id, $user_name);
    $stmt->fetch();
    $stmt->close();
}

// ICS-Rahmen immer ausgeben — bei ungültigem Token leeren Kalender
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//MSV Wilen//Einsatz-Kalender//DE\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";

if ($mitglied_id) {
    echo "X-WR-CALNAME:MSV Wilen - Meine Einsätze - " . icsEscape($user_name) . "\r\n";
} else {
    echo "X-WR-CALNAME:MSV Wilen - Meine Einsätze\r\n";
}

echo "X-WR-CALDESC:Persönliche Arbeitseinsätze\r\n";
echo "X-WR-TIMEZONE:Europe/Zurich\r\n";

// Timezone-Definition
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

if ($mitglied_id) {
    $stmt = $conn->prepare("
        SELECT id, bezeichnung, event_datum, event_zeit, funktion, typ
        FROM einsatz_zuweisungen
        WHERE mitglied_id = ?
        ORDER BY event_datum ASC
    ");
    $stmt->bind_param("i", $mitglied_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $datum      = $row['event_datum'];    // "2026-03-15"
        $zeit       = $row['event_zeit'];     // "14:30 - 16:00" oder leer
        $bezeich    = $row['bezeichnung'];
        $funktion   = $row['funktion'];
        $uid        = "ez-" . $row['id'] . "@msvjm.ch";

        $beschreibung = '';
        if (!empty($funktion)) {
            $beschreibung = $funktion;
        }

        if (!empty($zeit) && preg_match('/(\d{1,2}[:\.]\d{2})\s*[-–]\s*(\d{1,2}[:\.]\d{2})/u', $zeit, $m)) {
            $hinweis = 'Bitte 30 Minuten vor Arbeitsbeginn vor Ort erscheinen.';
            $beschreibung = $beschreibung !== '' ? $beschreibung . '\n' . $hinweis : $hinweis;
            $startTime = str_replace('.', ':', $m[1]);
            $endTime   = str_replace('.', ':', $m[2]);

            $start = date("Ymd\THis", strtotime($datum . " " . $startTime));
            $end   = date("Ymd\THis", strtotime($datum . " " . $endTime));

            echo "BEGIN:VEVENT\r\n";
            echo "UID:{$uid}\r\n";
            echo "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
            echo "DTSTART;TZID=Europe/Zurich:{$start}\r\n";
            echo "DTEND;TZID=Europe/Zurich:{$end}\r\n";
            echo "SUMMARY:" . icsEscape($bezeich) . "\r\n";
            if ($beschreibung !== '') {
                echo "DESCRIPTION:" . icsEscape($beschreibung) . "\r\n";
            }
            echo "SEQUENCE:0\r\n";
            echo "STATUS:CONFIRMED\r\n";
            echo "END:VEVENT\r\n";
        } else {
            // Ganztages-Event
            $dateFormatted = date("Ymd", strtotime($datum));

            echo "BEGIN:VEVENT\r\n";
            echo "UID:{$uid}\r\n";
            echo "DTSTAMP:" . gmdate("Ymd\THis\Z") . "\r\n";
            echo "DTSTART;VALUE=DATE:{$dateFormatted}\r\n";
            echo "SUMMARY:" . icsEscape($bezeich) . "\r\n";
            if ($beschreibung !== '') {
                echo "DESCRIPTION:" . icsEscape($beschreibung) . "\r\n";
            }
            echo "SEQUENCE:0\r\n";
            echo "STATUS:CONFIRMED\r\n";
            echo "END:VEVENT\r\n";
        }
    }
    $stmt->close();
}

echo "END:VCALENDAR\r\n";

$conn->close();
?>
