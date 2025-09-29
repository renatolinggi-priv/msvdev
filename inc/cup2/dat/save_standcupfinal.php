<?
include '../config.php';

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
    $sql = "SELECT * FROM cupStandFinal WHERE ParticipantName = '$participantName' AND Year = $year";
    echo $sql;
    $result = $conn->query($sql);
    
    $response['debug'][] = "SQL: $sql";  // Füge SQL für Debugging hinzu

    return $result && $result->num_rows > 0;
}

// Funktion zum Aktualisieren eines vorhandenen Eintrags
function updateEntry($conn, $participantName, $participantResult, $year) {
    global $response;  // Für Debugging
    $sql = "UPDATE cupStandFinal SET Result = $participantResult WHERE ParticipantName = '$participantName' AND Year = $year";
    $response['debug'][] = "Update SQL: $sql";  // Füge SQL für Debugging hinzu
    return $conn->query($sql);
}

// Funktion zum Einfügen eines neuen Eintrags
function insertEntry($conn, $participantName, $participantResult, $club, $year) {
    global $response;  // Für Debugging
    $sql = "INSERT INTO cupStandFinal (ParticipantName, Result, club, Year) VALUES ('$participantName', $participantResult, '$club', $year)";
   
   // return $conn->query($sql);
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