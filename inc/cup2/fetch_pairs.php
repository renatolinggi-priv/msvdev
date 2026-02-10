<?php
//fetch_pairs.php
include '../config.php';

$round = isset($_GET['round']) ? (int)$_GET['round'] : 1;
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// SQL-Abfrage zur Abrufung der Paarungen für die angegebene Runde und das Jahr
// WICHTIG: ManualWinner und ManualWinnerReason hinzugefügt!
$sql = "
    SELECT 
        cp.ID, 
        cp.Participant1, 
        cp.Participant2, 
        cp.Participant3, 
        cp.Result1, 
        cp.Result2, 
        cp.Result3, 
        cp.LowShot1,
        cp.LowShot2,
        cp.LowShot3,
        cp.ManualWinner,
        cp.ManualWinnerReason,
        m1.Vorname AS Vorname1, 
        m1.Name AS Name1, 
        m2.Vorname AS Vorname2, 
        m2.Name AS Name2,
        IFNULL(m3.Vorname, '') AS Vorname3, 
        IFNULL(m3.Name, '') AS Name3
    FROM 
        cupPairs cp
    JOIN 
        mitglieder m1 ON cp.Participant1 = m1.ID
    JOIN 
        mitglieder m2 ON cp.Participant2 = m2.ID
    LEFT JOIN 
        mitglieder m3 ON cp.Participant3 = m3.ID
    WHERE
        cp.Round = ?
        AND cp.Year = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $round, $year);
$stmt->execute();
$result = $stmt->get_result();

$pairs = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pairs[] = $row;
    }
}

echo json_encode($pairs);

$conn->close();
?>