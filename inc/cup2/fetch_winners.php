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
    AND cp.Year = $year
    AND cp.Participant3 IS NULL
    AND (cp.Result1 IS NOT NULL OR cp.ManualWinner IS NOT NULL)
    
    UNION ALL
    
    -- 3er-Paarungen: Die besten 2
    SELECT winner_id FROM (
        SELECT 
            participant_id as winner_id,
            ROW_NUMBER() OVER (PARTITION BY pair_id ORDER BY result DESC, lowshot DESC) as rn
        FROM (
            SELECT ID as pair_id, Participant1 as participant_id, Result1 as result, LowShot1 as lowshot
            FROM cupPairs WHERE Round = 1 AND Year = $year AND Participant3 IS NOT NULL
            UNION ALL
            SELECT ID as pair_id, Participant2 as participant_id, Result2 as result, LowShot2 as lowshot
            FROM cupPairs WHERE Round = 1 AND Year = $year AND Participant3 IS NOT NULL
            UNION ALL
            SELECT ID as pair_id, Participant3 as participant_id, Result3 as result, LowShot3 as lowshot
            FROM cupPairs WHERE Round = 1 AND Year = $year AND Participant3 IS NOT NULL
        ) as all_participants
        WHERE result IS NOT NULL
    ) ranked
    WHERE rn <= 2
)
SELECT COUNT(*) as katb_count
FROM winners w_list
JOIN mitglieder m ON m.ID = w_list.winner_id
JOIN Waffen w ON w.ID = m.WaffenID
WHERE w.Kategorie = 'Kat. B'
";

$katb_result = $conn->query($katb_check_sql);
$has_single_katb = ($katb_result->fetch_assoc()['katb_count'] == 1);

// Abfrage, um reguläre und manuelle Gewinner der ersten Runde zu laden
$sql = "
WITH top_two_winners AS (
    SELECT 
        cp2.ID AS pair_id,
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
    WHERE cp2.Participant3 IS NOT NULL
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
                ORDER BY result DESC, lowshot DESC
            ) AS rn
        FROM top_two_winners tw
        WHERE winner_id IS NOT NULL 
          AND result IS NOT NULL
    ) ranked
    WHERE rn <= 2
) AS top 
   ON cp.ID = top.pair_id 
  AND m.ID = top.winner_id
WHERE 
    cp.Round = 1 
    AND cp.Year = $year
    AND (
        -- Manuelle Gewinner immer mitnehmen
        cp.ManualWinner = m.ID
        OR
        (
           -- reguläre Logik für 2er-Paarung
          cp.Participant3 IS NULL
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
          cp.Participant3 IS NOT NULL
          AND top.winner_id IS NOT NULL
        )
    )
    -- Aus Runde 2 ausscheiden, wer dort schon ist
    AND NOT EXISTS (
        SELECT 1
        FROM cupPairs cp2
        WHERE cp2.Round = 2
          AND cp2.Year = $year
          AND m.ID IN (cp2.Participant1, cp2.Participant2, cp2.Participant3)
    )";

// Wenn es einen einzelnen Kat. B Gewinner gibt, diesen ausschließen
if ($has_single_katb) {
    $sql .= " AND NOT (w.Kategorie = 'Kat. B')";
}

$sql .= " ORDER BY
    winner_type DESC,
    m.Name, 
    m.Vorname";

$result = $conn->query($sql);
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