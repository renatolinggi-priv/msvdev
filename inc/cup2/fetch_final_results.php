<?php
include '../config.php';

header('Content-Type: application/json');

if ($conn->connect_error) {
    error_log("Verbindung fehlgeschlagen: " . $conn->connect_error);
    echo json_encode(["error" => "Verbindung zur Datenbank fehlgeschlagen"]);
    exit;
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
// SQL-Abfrage, um nur die Gewinner aus Runde 2 zu ermitteln
$sql = "
    SELECT m.ID, m.Name, m.Vorname, fr.Result, fr.LowShot
    FROM (
        SELECT 
            CASE 
                WHEN Result1 > Result2 OR (Result1 = Result2 AND LowShot1 > LowShot2) THEN Participant1
                WHEN Participant3 IS NOT NULL AND (Result3 > Result1 OR (Result3 = Result1 AND LowShot3 > LowShot1)) THEN Participant3
                ELSE Participant2 
            END AS Winner1,
            CASE 
                WHEN Participant3 IS NOT NULL THEN 
                    CASE 
                        WHEN Result3 > GREATEST(Result1, Result2) OR (Result3 = GREATEST(Result1, Result2) AND LowShot3 > LEAST(LowShot1, LowShot2)) THEN
                            CASE 
                                WHEN Result1 > Result2 OR (Result1 = Result2 AND LowShot1 > LowShot2) THEN Participant1
                                ELSE Participant2 
                            END
                        ELSE Participant3
                    END
                ELSE NULL
            END AS Winner2
        FROM cupPairs
        WHERE Year = '$year' AND Round = 2
    ) AS winners
    INNER JOIN mitglieder m ON m.ID IN (winners.Winner1, winners.Winner2)
    LEFT JOIN cupFinalResults fr ON m.ID = fr.ParticipantID AND fr.Year = '$year'
    WHERE winners.Winner1 IS NOT NULL OR winners.Winner2 IS NOT NULL
";

$result = $conn->query($sql);

if (!$result) {
    error_log("SQL Fehler: " . $conn->error);
    echo json_encode(["error" => "Fehler bei der Abfrage: " . $conn->error]);
    exit;
}

$finalists = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $finalists[] = [
            'ID' => $row['ID'],
            'Name' => $row['Name'],
            'Vorname' => $row['Vorname'],
            'Result' => $row['Result'],
            'LowShot' => $row['LowShot']
        ];
    }
}

if (empty($finalists)) {
    echo json_encode(["error" => "Keine Finalisten gefunden"]);
    exit;
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($finalists);
exit;
?>
