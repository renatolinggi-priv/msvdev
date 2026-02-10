<?php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

header('Content-Type: application/json');

$year = isset($_POST['year']) ? (int)$_POST['year'] : date("Y");

// Response Array
$response = [
    'success' => true,
    'messages' => [],
    'errors' => []
];

// Clubs sind fest definiert (aus den Labels)
$clubs = [
    1 => 'MSV Wilen',
    2 => 'SV Wollerau', 
    3 => 'SV Freienbach'
];

// Teilnehmer-Daten sammeln
$participants = [
    [
        'name' => $_POST['participant1_name'] ?? '',
        'result' => $_POST['participant1_result'] ?? 0,
        'club' => $clubs[1]
    ],
    [
        'name' => $_POST['participant2_name'] ?? '',
        'result' => $_POST['participant2_result'] ?? 0,
        'club' => $clubs[2]
    ],
    [
        'name' => $_POST['participant3_name'] ?? '',
        'result' => $_POST['participant3_result'] ?? 0,
        'club' => $clubs[3]
    ]
];

// Prepared Statements für Sicherheit
$check_stmt = $conn->prepare("SELECT ID FROM cupStandFinal WHERE ParticipantName = ? AND Year = ?");
$update_stmt = $conn->prepare("UPDATE cupStandFinal SET Result = ? WHERE ParticipantName = ? AND Year = ?");
$insert_stmt = $conn->prepare("INSERT INTO cupStandFinal (ParticipantName, Result, club, Year) VALUES (?, ?, ?, ?)");

if (!$check_stmt || !$update_stmt || !$insert_stmt) {
    $response['success'] = false;
    $response['errors'][] = "Datenbankfehler bei der Vorbereitung der Statements";
    echo json_encode($response);
    exit;
}

// Verarbeite jeden Teilnehmer
foreach ($participants as $index => $participant) {
    $name = trim($participant['name']);
    $result = (int)$participant['result'];
    $club = $participant['club'];
    
    if (empty($name)) {
        $response['errors'][] = "Teilnehmer " . ($index + 1) . " hat keinen Namen";
        continue;
    }
    
    // Prüfe ob Eintrag existiert
    $check_stmt->bind_param("si", $name, $year);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->num_rows > 0;
    
    if ($exists) {
        // Update
        $update_stmt->bind_param("isi", $result, $name, $year);
        if ($update_stmt->execute()) {
            $response['messages'][] = "Daten für $name ($club) erfolgreich aktualisiert";
        } else {
            $response['errors'][] = "Fehler beim Aktualisieren von $name: " . $update_stmt->error;
            $response['success'] = false;
        }
    } else {
        // Insert
        $insert_stmt->bind_param("sisi", $name, $result, $club, $year);
        if ($insert_stmt->execute()) {
            $response['messages'][] = "Daten für $name ($club) erfolgreich hinzugefügt";
        } else {
            $response['errors'][] = "Fehler beim Hinzufügen von $name: " . $insert_stmt->error;
            $response['success'] = false;
        }
    }
}

$check_stmt->close();
$update_stmt->close();
$insert_stmt->close();
$conn->close();

echo json_encode($response);
?>