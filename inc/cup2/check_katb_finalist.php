<?php
// check_katb_finalist.php
include '../config.php';

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Hole alle Gewinner der ersten Runde mit ihrer Waffenkategorie
$sql = "
WITH winners AS (
    -- 2er-Paarungen Gewinner
    SELECT
        CASE
            WHEN cp.ManualWinner IS NOT NULL THEN cp.ManualWinner
            WHEN cp.Result1 > cp.Result2 OR (cp.Result1 = cp.Result2 AND cp.LowShot1 > cp.LowShot2) THEN cp.Participant1
            ELSE cp.Participant2
        END as winner_id
    FROM cupPairs cp
    WHERE cp.Round = 1
    AND cp.Year = ?
    AND cp.Participant3 IS NULL
    AND (cp.Result1 IS NOT NULL OR cp.ManualWinner IS NOT NULL)

    UNION ALL

    -- 3er-Paarungen: Die besten 2 (negative ManualWinner = ausgeschieden)
    SELECT winner_id FROM (
        SELECT
            participant_id as winner_id,
            ROW_NUMBER() OVER (PARTITION BY pair_id ORDER BY result DESC, lowshot DESC) as rn
        FROM (
            SELECT cp.ID as pair_id, cp.Participant1 as participant_id, cp.Result1 as result, cp.LowShot1 as lowshot, cp.ManualWinner
            FROM cupPairs cp WHERE cp.Round = 1 AND cp.Year = ? AND cp.Participant3 IS NOT NULL
            UNION ALL
            SELECT cp.ID as pair_id, cp.Participant2 as participant_id, cp.Result2 as result, cp.LowShot2 as lowshot, cp.ManualWinner
            FROM cupPairs cp WHERE cp.Round = 1 AND cp.Year = ? AND cp.Participant3 IS NOT NULL
            UNION ALL
            SELECT cp.ID as pair_id, cp.Participant3 as participant_id, cp.Result3 as result, cp.LowShot3 as lowshot, cp.ManualWinner
            FROM cupPairs cp WHERE cp.Round = 1 AND cp.Year = ? AND cp.Participant3 IS NOT NULL
        ) as all_participants
        WHERE result IS NOT NULL
          AND NOT (ManualWinner < 0 AND participant_id = ABS(ManualWinner))
    ) ranked
    WHERE rn <= 2
)
SELECT
    m.ID,
    m.Name,
    m.Vorname,
    w.Kategorie
FROM winners w_list
JOIN mitglieder m ON m.ID = w_list.winner_id
JOIN Waffen w ON w.ID = m.WaffenID
WHERE w.Kategorie = 'Kat. B'
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $year, $year, $year, $year);
$stmt->execute();
$result = $stmt->get_result();

$response = [
    'has_single_katb_winner' => false,
    'katb_finalist' => null
];

if ($result && $result->num_rows == 1) {
    $katb_winner = $result->fetch_assoc();
    
    // Prüfe ob bereits Finalergebnisse existieren
    $final_sql = "SELECT Result, LowShot FROM cupFinalResults 
                  WHERE ParticipantID = ? AND Year = ?";
    $final_stmt = $conn->prepare($final_sql);
    $final_stmt->bind_param("ii", $katb_winner['ID'], $year);
    $final_stmt->execute();
    $final_result = $final_stmt->get_result();
    
    if ($final_result && $final_result->num_rows > 0) {
        $final_data = $final_result->fetch_assoc();
        $katb_winner['Result'] = $final_data['Result'];
        $katb_winner['LowShot'] = $final_data['LowShot'];
    } else {
        $katb_winner['Result'] = null;
        $katb_winner['LowShot'] = null;
    }
    $final_stmt->close();
    
    $response['has_single_katb_winner'] = true;
    $response['katb_finalist'] = $katb_winner;
}
// Debug-Information hinzufügen
$response['debug'] = [
    'sql_query' => $sql,
    'num_winners' => $result ? $result->num_rows : 0,
    'year' => $year
];

// Wenn ein Kat. B Gewinner gefunden wurde, hole auch die Final-Daten
if ($response['has_single_katb_winner'] && $response['katb_finalist']) {
    $debug_stmt = $conn->prepare("SELECT * FROM cupFinalResults WHERE ParticipantID = ? AND Year = ?");
    $debugParticipantId = $response['katb_finalist']['ID'];
    $debug_stmt->bind_param("ii", $debugParticipantId, $year);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    $response['debug']['final_data'] = $debug_result ? $debug_result->fetch_all(MYSQLI_ASSOC) : null;
    $response['debug']['final_sql'] = "SELECT * FROM cupFinalResults WHERE ParticipantID = ? AND Year = ? [" . $debugParticipantId . ", " . $year . "]";
    $debug_stmt->close();
}
header('Content-Type: application/json');
echo json_encode($response);
$conn->close();
?>