<?
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

$year = date("Y");
/*
$participant1Name = $_POST['participant1_name'] ?? null;
$participant1Result = $_POST['participant1_result'] ?? null;
$participant1club = $_POST['participant1_club'] ?? null;
*/

$participant1Name = $_GET['participant1_name'] ?? null;
$participant1Result = $_GET['participant1_result'] ?? null;
$participant1club = $_GET['participant1_club'] ?? null;

$participant2Name = $_POST['participant2_name'] ?? null;
$participant2Result = $_POST['participant2_result'] ?? null;
$participant2club = $_POST['participant2_club'] ?? null;
$participant3Name = $_POST['participant3_name'] ?? null;
$participant3Result = $_POST['participant3_result'] ?? null;
$participant3club = $_POST['participant3_club'] ?? null;

// Array für Nachrichten und Fehler
$response = [
    'messages' => [],
    'errors' => [],
    'debug' => []
];

// Funktion, um zu prüfen, ob der Teilnehmer bereits existiert
function checkExistingEntry($conn, $participantName, $year) {
    global $response;  // Für Debugging
    $stmt = $conn->prepare("SELECT * FROM cupStandFinal WHERE ParticipantName = ? AND Year = ?");
    $stmt->bind_param("si", $participantName, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $response['debug'][] = "SQL: SELECT * FROM cupStandFinal WHERE ParticipantName = ? AND Year = ? [" . $participantName . ", " . $year . "]";

    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Funktion zum Aktualisieren eines vorhandenen Eintrags
function updateEntry($conn, $participantName, $participantResult, $year) {
    global $response;  // Für Debugging
    $stmt = $conn->prepare("UPDATE cupStandFinal SET Result = ? WHERE ParticipantName = ? AND Year = ?");
    $participantResult = intval($participantResult);
    $stmt->bind_param("isi", $participantResult, $participantName, $year);
    $response['debug'][] = "Update SQL: UPDATE cupStandFinal SET Result = ? WHERE ParticipantName = ? AND Year = ? [" . $participantResult . ", " . $participantName . ", " . $year . "]";
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Funktion zum Einfügen eines neuen Eintrags
function insertEntry($conn, $participantName, $participantResult, $club, $year) {
    global $response;  // Für Debugging
    $stmt = $conn->prepare("INSERT INTO cupStandFinal (ParticipantName, Result, club, Year) VALUES (?, ?, ?, ?)");
    $participantResult = intval($participantResult);
    $stmt->bind_param("sisi", $participantName, $participantResult, $club, $year);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Überprüfen und speichern/aktualisieren der Daten für alle Teilnehmer
foreach ([
    ['name' => $participant1Name, 'result' => $participant1Result, 'club' => $participant1club],
    ['name' => $participant2Name, 'result' => $participant2Result, 'club' => $participant2club],
    ['name' => $participant3Name, 'result' => $participant3Result, 'club' => $participant3club]
] as $participant) {
    $name = $participant['name'];
    $result = $participant['result'];
    $club = $participant['club'];

    if (!$name || !$result || !$club) {
        $response['errors'][] = "Fehlende Daten für einen Teilnehmer!";
        continue;
    }

    if (checkExistingEntry($conn, $name, $year)) {
        // Wenn der Teilnehmer bereits existiert, wird er aktualisiert
        if (updateEntry($conn, $name, $result, $year)) {
            $response['messages'][] = "Daten für $name erfolgreich aktualisiert!";
        } else {
            $response['errors'][] = "Fehler beim Aktualisieren der Daten für $name: " . $conn->error;
        }
    } else {
        // Wenn der Teilnehmer nicht existiert, wird er hinzugefügt
        if (insertEntry($conn, $name, $result, $club, $year)) {
            $response['messages'][] = "Daten für $name erfolgreich hinzugefügt!";
        } else {
            $response['errors'][] = "Fehler beim Hinzufügen der Daten für $name: " . $conn->error;
        }
    }
}

$conn->close();

// Rückgabe der Antwort als JSON
header('Content-Type: application/json');
echo json_encode($response);

?>