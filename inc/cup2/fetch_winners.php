<?php
// fetch_winners.php
include '../config.php';

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Zuerst prüfen, ob es einen einzelnen Kat. B Gewinner gibt
$katb_check_sql = "
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
    AND (cp.Participant3 IS NULL OR cp.Participant3 = 0)
    AND (cp.Result1 IS NOT NULL OR cp.ManualWinner IS NOT NULL)
    
    UNION ALL

    -- 3er-Paarungen: Die besten N (negative ManualWinner = ausgeschieden, N = Advancers)
    SELECT winner_id FROM (
        SELECT
            participant_id as winner_id,
            advancers,
            ROW_NUMBER() OVER (
                PARTITION BY pair_id
                ORDER BY (CASE WHEN ManualWinner > 0 AND participant_id = ManualWinner THEN 1 ELSE 0 END) DESC, result DESC, lowshot DESC
            ) as rn
        FROM (
            SELECT cp.ID as pair_id, cp.Participant1 as participant_id, cp.Result1 as result, cp.LowShot1 as lowshot, cp.ManualWinner, cp.Advancers as advancers
            FROM cupPairs cp WHERE cp.Round = 1 AND cp.Year = ? AND (cp.Participant3 IS NOT NULL AND cp.Participant3 != 0)
            UNION ALL
            SELECT cp.ID as pair_id, cp.Participant2 as participant_id, cp.Result2 as result, cp.LowShot2 as lowshot, cp.ManualWinner, cp.Advancers as advancers
            FROM cupPairs cp WHERE cp.Round = 1 AND cp.Year = ? AND (cp.Participant3 IS NOT NULL AND cp.Participant3 != 0)
            UNION ALL
            SELECT cp.ID as pair_id, cp.Participant3 as participant_id, cp.Result3 as result, cp.LowShot3 as lowshot, cp.ManualWinner, cp.Advancers as advancers
            FROM cupPairs cp WHERE cp.Round = 1 AND cp.Year = ? AND (cp.Participant3 IS NOT NULL AND cp.Participant3 != 0)
        ) as all_participants
        WHERE result IS NOT NULL
          AND NOT (COALESCE(ManualWinner, 0) < 0 AND participant_id = ABS(ManualWinner))
    ) ranked
    WHERE rn <= COALESCE(advancers, 2)
)
SELECT COUNT(*) as katb_count
FROM winners w_list
JOIN mitglieder m ON m.ID = w_list.winner_id
JOIN Waffen w ON w.ID = m.WaffenID
WHERE w.Kategorie = 'Kat. B'
";

$stmt = $conn->prepare($katb_check_sql);
$stmt->bind_param("iiii", $year, $year, $year, $year);
$stmt->execute();
$katb_result = $stmt->get_result();
$has_single_katb = ($katb_result->fetch_assoc()['katb_count'] == 1);

// Abfrage, um reguläre und manuelle Gewinner der ersten Runde zu laden
$sql = "
WITH top_two_winners AS (
    SELECT
        cp2.ID AS pair_id,
        cp2.ManualWinner AS manual_winner,
        cp2.Advancers AS advancers,
        CASE WHEN n = 1 THEN cp2.Participant1
             WHEN n = 2 THEN cp2.Participant2
             WHEN n = 3 THEN cp2.Participant3
        END AS winner_id,
        CASE WHEN n = 1 THEN cp2.Result1
             WHEN n = 2 THEN cp2.Result2
             WHEN n = 3 THEN cp2.Result3
        END AS result,
        CASE WHEN n = 1 THEN cp2.LowShot1
             WHEN n = 2 THEN cp2.LowShot2
             WHEN n = 3 THEN cp2.LowShot3
        END AS lowshot
    FROM cupPairs cp2
    CROSS JOIN (SELECT 1 AS n UNION SELECT 2 UNION SELECT 3) nums
    WHERE cp2.Participant3 IS NOT NULL AND cp2.Participant3 != 0
)
SELECT DISTINCT
    m.ID,
    m.Vorname,
    m.Name,
    w.Kategorie,
    CASE 
        WHEN cp.ManualWinner = m.ID THEN 'manual'
        ELSE 'regular'
    END AS winner_type,
    cp.ManualWinnerReason,
    cp.ID AS pair_id
FROM cupPairs cp
JOIN mitglieder m 
    ON m.ID IN (cp.Participant1, cp.Participant2, cp.Participant3)
JOIN Waffen w 
    ON w.ID = m.WaffenID
LEFT JOIN (
    SELECT pair_id, winner_id
    FROM (
        SELECT
            tw.*,
            ROW_NUMBER() OVER (
                PARTITION BY pair_id
                ORDER BY (CASE WHEN manual_winner > 0 AND winner_id = manual_winner THEN 1 ELSE 0 END) DESC, result DESC, lowshot DESC
            ) AS rn
        FROM top_two_winners tw
        WHERE winner_id IS NOT NULL
          AND result IS NOT NULL
          AND NOT (COALESCE(manual_winner, 0) < 0 AND winner_id = ABS(manual_winner))
    ) ranked
    WHERE rn <= COALESCE(advancers, 2)
) AS top
   ON cp.ID = top.pair_id
  AND m.ID = top.winner_id
WHERE 
    cp.Round = 1 
    AND cp.Year = ?
    AND NOT (COALESCE(cp.ManualWinner, 0) < 0 AND m.ID = ABS(cp.ManualWinner))
    AND (
        -- Manuelle Gewinner immer mitnehmen (nur positive = Gewinner)
        (cp.ManualWinner > 0 AND cp.ManualWinner = m.ID)
        OR
        (
           -- reguläre Logik für 2er-Paarung
          (cp.Participant3 IS NULL OR cp.Participant3 = 0)
          AND (
            ((cp.Result1 > cp.Result2 
               OR (cp.Result1 = cp.Result2 
                   AND cp.LowShot1 > cp.LowShot2)
             ) AND m.ID = cp.Participant1)
            OR
            ((cp.Result2 > cp.Result1 
               OR (cp.Result1 = cp.Result2 
                   AND cp.LowShot2 > cp.LowShot1)
             ) AND m.ID = cp.Participant2)
          )
        )
        OR
        (
          -- reguläre Logik für 3er-Paarung: Top-2 aus CTE
          (cp.Participant3 IS NOT NULL AND cp.Participant3 != 0)
          AND top.winner_id IS NOT NULL
        )
    )
    -- Aus Runde 2 ausscheiden, wer dort schon ist
    AND NOT EXISTS (
        SELECT 1
        FROM cupPairs cp2
        WHERE cp2.Round = 2
          AND cp2.Year = ?
          AND m.ID IN (cp2.Participant1, cp2.Participant2, cp2.Participant3)
    )";

// Kat. B Gewinner NICHT mehr serverseitig ausschliessen
// → wird client-seitig als "direkt ins Finale" markiert und nicht verschiebbar gemacht

$sql .= " ORDER BY
    winner_type DESC,
    m.Name, 
    m.Vorname";

$stmt2 = $conn->prepare($sql);
$stmt2->bind_param("ii", $year, $year);
$stmt2->execute();
$result = $stmt2->get_result();
if (!$result) {
    error_log("SQL Error in fetch_winners.php: " . $conn->error);
    echo json_encode([]);
    exit;
}

$winners = [];
while ($row = $result->fetch_assoc()) {
    $winners[] = $row;
}

echo json_encode($winners);
$conn->close();
?>