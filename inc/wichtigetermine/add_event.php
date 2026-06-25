<?php
// add_event.php – JSON Response
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF prüfen
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF-Token']);
    exit;
}

if (!isset($_POST['event_name'], $_POST['event_date'], $_POST['event_time'])) {
    echo json_encode(['success' => false, 'message' => 'Bitte alle Felder ausfüllen']);
    exit;
}

$eventName = trim($_POST['event_name']);
$eventDate = $_POST['event_date'];
$eventTime = trim($_POST['event_time']);
$eventYear = isset($_POST['year']) ? intval($_POST['year']) : (isset($_POST['event_year']) ? intval($_POST['event_year']) : date('Y'));
$fuerJsk   = !empty($_POST['fuer_jsk']) ? 1 : 0;

if (empty($eventName) || empty($eventDate) || empty($eventTime)) {
    echo json_encode(['success' => false, 'message' => 'Bitte alle Felder ausfüllen']);
    exit;
}

$sql = "INSERT INTO wichtige_termine (name, date, time, year, fuer_jsk) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssii", $eventName, $eventDate, $eventTime, $eventYear, $fuerJsk);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$newId = $stmt->insert_id;
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'message' => 'Termin hinzugefügt', 'id' => $newId]);

// ---------------------------------------------------------------------------
// Sofort-Benachrichtigung an Mitglieder mit aktiviertem "termine"-Thema.
// Best effort: laeuft NACH der Antwort, ein Fehler darf das Hinzufuegen nie brechen.
// Der taegliche Reminder-Cron (cron/check_benachrichtigungen.php) bleibt davon
// unberuehrt — hier geht es nur um die einmalige "Neuer Termin"-Meldung.
// ---------------------------------------------------------------------------
if (function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request(); // Antwort abschliessen -> Admin wartet nicht auf den Versand
}
@session_write_close();        // Session-Lock freigeben (Push-Versand braucht keine Session)

// Empfaenger nach Termin-Typ:
//  - Vereinstermin (fuer_jsk=0): Mitglieder (ohne Jungschuetzen) -> portal/termine.php
//  - JSK-Termin    (fuer_jsk=1): Jungschuetzen-Teilnehmer        -> portal/jsk_termine.php
// $rolleFilter stammt aus festem Code (kein User-Input) -> Interpolation unkritisch.
@include_once __DIR__ . '/../push_helper.php';
if (function_exists('sendePushAnBenutzer')) {
    try {
        $pdb = getDB();
        if ($fuerJsk) {
            $rolleFilter = "u.role = 'jungschuetze'";
            $pushUrl     = 'portal/jsk_termine.php';
        } else {
            $rolleFilter = "u.role <> 'jungschuetze'";
            $pushUrl     = 'portal/termine.php';
        }

        // Approved Empfaenger mit aktivem Haupt- + Themen-Schalter (fehlende Zeile = an).
        $empf = $pdb->query(
            "SELECT u.id
               FROM users u
               LEFT JOIN benachrichtigung_prefs p ON p.user_id = u.id
              WHERE u.status = 'approved'
                AND $rolleFilter
                AND COALESCE(p.push_aktiv, 1) = 1
                AND COALESCE(p.termine, 1) = 1"
        )->fetchAll(PDO::FETCH_COLUMN);

        if ($empf) {
            $d        = DateTime::createFromFormat('Y-m-d', substr($eventDate, 0, 10));
            $datumFmt = $d ? $d->format('d.m.Y') : $eventDate;
            $zeit     = trim($eventTime);
            if (preg_match('/^(\d{1,2}):(\d{2})/', $zeit, $mm)) {
                $zeit = str_pad($mm[1], 2, '0', STR_PAD_LEFT) . ':' . $mm[2];
            }
            $text = $eventName . ' am ' . $datumFmt . ($zeit !== '' ? ' um ' . $zeit . ' Uhr' : '');

            foreach ($empf as $uid) {
                try {
                    sendePushAnBenutzer((int) $uid, 'Neuer Termin', $text, $pushUrl);
                } catch (\Throwable $e) {
                    error_log('add_event push (user ' . $uid . '): ' . $e->getMessage());
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('add_event push broadcast: ' . $e->getMessage());
    }
}
