<?php
//fetch_winners_round2.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include '../config.php';

if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

// SQL-Abfrage, um die Gewinner der Runde 2 abzurufen
$sql = "
    SELECT
        m.ID, m.Vorname, m.Name
    FROM
        cupPairs cp
    JOIN
        mitglieder m ON (cp.Participant1 = m.ID OR cp.Participant2 = m.ID OR cp.Participant3 = m.ID)
    WHERE
        cp.Round = 2
        AND cp.Year = ?
        AND (
            -- Bedingungen für Zweierpaarungen
            (
                cp.Participant3 IS NULL 
                AND (
                    (cp.Result1 > cp.Result2 OR (cp.Result1 = cp.Result2 AND cp.LowShot1 > cp.LowShot2)) AND m.ID = cp.Participant1
                    OR 
                    (cp.Result2 > cp.Result1 OR (cp.Result1 = cp.Result2 AND cp.LowShot2 > cp.LowShot1)) AND m.ID = cp.Participant2
                )
            )
            OR
            -- Bedingungen für Dreierpaarungen: zwei Gewinner
            (
                cp.Participant3 IS NOT NULL 
                AND (
                    -- Prüfen, ob Participant1 unter den beiden besten ist
                    (
                        (cp.Result1 > cp.Result2 OR (cp.Result1 = cp.Result2 AND cp.LowShot1 > cp.LowShot2))
                        OR
                        (cp.Result1 > cp.Result3 OR (cp.Result1 = cp.Result3 AND cp.LowShot1 > cp.LowShot3))
                    ) AND m.ID = cp.Participant1
                    OR 
                    -- Prüfen, ob Participant2 unter den beiden besten ist
                    (
                        (cp.Result2 > cp.Result1 OR (cp.Result2 = cp.Result1 AND cp.LowShot2 > cp.LowShot1))
                        OR
                        (cp.Result2 > cp.Result3 OR (cp.Result2 = cp.Result3 AND cp.LowShot2 > cp.LowShot3))
                    ) AND m.ID = cp.Participant2
                    OR 
                    -- Prüfen, ob Participant3 unter den beiden besten ist
                    (
                        (cp.Result3 > cp.Result1 OR (cp.Result3 = cp.Result1 AND cp.LowShot3 > cp.LowShot1))
                        OR
                        (cp.Result3 > cp.Result2 OR (cp.Result3 = cp.Result2 AND cp.LowShot3 > cp.LowShot2))
                    ) AND m.ID = cp.Participant3
                )
            )
        )
    ORDER BY 
        m.Name ASC, m.Vorname ASC;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$finalists = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $finalists[] = $row;
    }
}

echo json_encode($finalists);

$conn->close();
?>
