<?php
// check_katb_finalist.php
include '../config.php';

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Kat.-B-Finalqualifikation pro Jahr abschaltbar (cupSettings, Default 1 = ein)
// Tabelle kann fehlen, falls Migration 020 nicht lief → defensiv (Default 1)
$katbToFinal = 1;
try {
    $setStmt = $conn->prepare("SELECT KatBToFinal FROM cupSettings WHERE Year = ?");
    if ($setStmt) {
        $setStmt->bind_param("i", $year);
        $setStmt->execute();
        $setRow = $setStmt->get_result()->fetch_assoc();
        $setStmt->close();
        if ($setRow !== null) {
            $katbToFinal = (int)$setRow['KatBToFinal'];
        }
    }
} catch (Throwable $e) {
    error_log("check_katb_finalist cupSettings: " . $e->getMessage());
}
if ($katbToFinal !== 1) {
    // Schalter aus: keine automatische Kat.-B-Finalqualifikation
    header('Content-Type: application/json');
    echo json_encode([
        'has_single_katb_winner' => false,
        'katb_finalist' => null,
        'katb_to_final' => 0
    ]);
    $conn->close();
    exit;
}

// Anzahl Kat.-B-Teilnehmer in einer Runde (unabhängig vom Resultat)
function countKatBInRound($conn, $year, $round) {
    $sql = "
        SELECT COUNT(*) AS c FROM (
            SELECT Participant1 AS pid FROM cupPairs WHERE Year = ? AND Round = ? AND Participant1 IS NOT NULL AND Participant1 != 0
            UNION ALL
            SELECT Participant2 FROM cupPairs WHERE Year = ? AND Round = ? AND Participant2 IS NOT NULL AND Participant2 != 0
            UNION ALL
            SELECT Participant3 FROM cupPairs WHERE Year = ? AND Round = ? AND Participant3 IS NOT NULL AND Participant3 != 0
        ) p
        JOIN mitglieder m ON m.ID = p.pid
        JOIN Waffen w ON w.ID = m.WaffenID
        WHERE w.Kategorie = 'Kat. B'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiii", $year, $round, $year, $round, $year, $round);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['c'] : 0;
}

// Bester Kat.-B-Schütze einer Runde (nach Resultat, dann Tiefschuss); null wenn keiner mit Resultat
function bestKatBInRound($conn, $year, $round) {
    $sql = "
        SELECT m.ID, m.Name, m.Vorname, p.result AS RoundResult, p.lowshot AS RoundLowShot
        FROM (
            SELECT Participant1 AS pid, Result1 AS result, LowShot1 AS lowshot FROM cupPairs WHERE Year = ? AND Round = ? AND Participant1 IS NOT NULL AND Participant1 != 0
            UNION ALL
            SELECT Participant2, Result2, LowShot2 FROM cupPairs WHERE Year = ? AND Round = ? AND Participant2 IS NOT NULL AND Participant2 != 0
            UNION ALL
            SELECT Participant3, Result3, LowShot3 FROM cupPairs WHERE Year = ? AND Round = ? AND Participant3 IS NOT NULL AND Participant3 != 0
        ) p
        JOIN mitglieder m ON m.ID = p.pid
        JOIN Waffen w ON w.ID = m.WaffenID
        WHERE w.Kategorie = 'Kat. B' AND p.result IS NOT NULL
        ORDER BY p.result DESC, p.lowshot DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiii", $year, $round, $year, $round, $year, $round);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

$response = [
    'has_single_katb_winner' => false,
    'katb_finalist' => null,
    'source_round' => null
];

// Regel: Bester Kat. B aus Runde 2 → Finale.
// War KEIN Kat. B in Runde 2, fällt es auf den besten Kat. B aus Runde 1 zurück.
if (countKatBInRound($conn, $year, 2) > 0) {
    $katb_winner = bestKatBInRound($conn, $year, 2);
    $response['source_round'] = 2;
} else {
    $katb_winner = bestKatBInRound($conn, $year, 1);
    $response['source_round'] = 1;
}

if ($katb_winner) {
    // Falls bereits ein Finalergebnis existiert, dieses mitliefern
    $final_stmt = $conn->prepare("SELECT Result, LowShot FROM cupFinalResults WHERE ParticipantID = ? AND Year = ?");
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

header('Content-Type: application/json');
echo json_encode($response);
$conn->close();
?>