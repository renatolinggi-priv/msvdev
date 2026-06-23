<?php
include '../config.php';

header('Content-Type: application/json');

if ($conn->connect_error) {
    error_log("Verbindung fehlgeschlagen: " . $conn->connect_error);
    echo json_encode(["error" => "Verbindung zur Datenbank fehlgeschlagen"]);
    exit;
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
// Gewinner aus Runde 2 ermitteln.
// 2er-Paarung: bester 1 kommt weiter. 3er-Paarung: beste N (N = Advancers, Default 2).
// Berücksichtigt ManualWinner (positiv = Gewinner zuerst, negativ = ausgeschieden).
$sql = "
    WITH r2 AS (
        SELECT
            pair_id,
            participant_id,
            advancers,
            is_three,
            ROW_NUMBER() OVER (
                PARTITION BY pair_id
                ORDER BY (CASE WHEN ManualWinner > 0 AND participant_id = ManualWinner THEN 1 ELSE 0 END) DESC, result DESC, lowshot DESC
            ) AS rn
        FROM (
            SELECT cp.ID AS pair_id, cp.Participant1 AS participant_id, cp.Result1 AS result, cp.LowShot1 AS lowshot,
                   cp.ManualWinner, cp.Advancers AS advancers,
                   (cp.Participant3 IS NOT NULL AND cp.Participant3 != 0) AS is_three
            FROM cupPairs cp WHERE cp.Year = ? AND cp.Round = 2 AND cp.Participant1 IS NOT NULL AND cp.Participant1 != 0
            UNION ALL
            SELECT cp.ID, cp.Participant2, cp.Result2, cp.LowShot2, cp.ManualWinner, cp.Advancers,
                   (cp.Participant3 IS NOT NULL AND cp.Participant3 != 0)
            FROM cupPairs cp WHERE cp.Year = ? AND cp.Round = 2 AND cp.Participant2 IS NOT NULL AND cp.Participant2 != 0
            UNION ALL
            SELECT cp.ID, cp.Participant3, cp.Result3, cp.LowShot3, cp.ManualWinner, cp.Advancers, 1
            FROM cupPairs cp WHERE cp.Year = ? AND cp.Round = 2 AND cp.Participant3 IS NOT NULL AND cp.Participant3 != 0
        ) parts
        WHERE result IS NOT NULL
          AND NOT (COALESCE(ManualWinner, 0) < 0 AND participant_id = ABS(ManualWinner))
    )
    SELECT m.ID, m.Name, m.Vorname, fr.Result, fr.LowShot
    FROM r2
    JOIN mitglieder m ON m.ID = r2.participant_id
    LEFT JOIN cupFinalResults fr ON m.ID = fr.ParticipantID AND fr.Year = ?
    WHERE (r2.is_three = 0 AND r2.rn = 1)
       OR (r2.is_three = 1 AND r2.rn <= COALESCE(r2.advancers, 2))
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $year, $year, $year, $year);
$stmt->execute();
$result = $stmt->get_result();

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
