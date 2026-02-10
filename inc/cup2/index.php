<?php
include '../config.php'; // Passe den Pfad zu deiner Konfigurationsdatei entsprechend an

$pairId = 81;
    // Ermittle den Gewinner aus der gelöschten Paarung in cupPairs
    $winnerStmt = $conn->prepare("SELECT
                    CASE
                        WHEN Result1 > Result2 THEN Participant1
                        ELSE Participant2
                    END AS WinnerID
                  FROM cupPairs
                  WHERE ID = ?");
    $winnerStmt->bind_param("i", $pairId);
    $winnerStmt->execute();
    $winnerResult = $winnerStmt->get_result();

    if ($winnerResult === FALSE) {
        echo json_encode(['message' => 'Fehler bei der Abfrage nach dem Gewinner: ' . $conn->error]);

    }

    if ($winnerResult->num_rows > 0) {
        $winnerRow = $winnerResult->fetch_assoc();
        $winnerId = $winnerRow['WinnerID'];
        $winnerStmt->close();

        if (empty($winnerId)) {
            echo json_encode(['message' => 'Kein Gewinner gefunden']);

        }

        // Gebe Debug-Info als JSON aus
        echo json_encode(['success' => true, 'deleteSql' => 'DELETE FROM cupFinalResults WHERE ParticipantID = ' . intval($winnerId)]);

        // Führe die Löschabfrage aus
        $deleteStmt = $conn->prepare("DELETE FROM cupFinalResults WHERE ParticipantID = ?");
        $deleteStmt->bind_param("i", $winnerId);
        if ($deleteStmt->execute()) {
            echo json_encode(['success' => 'Finalergebnisseintrag erfolgreich gelöscht']);
        } else {
            echo json_encode(['message' => 'Fehler beim Löschen des Finalergebnisseintrags: ' . $deleteStmt->error]);
        }
        $deleteStmt->close();
    } else {
        $winnerStmt->close();
        echo json_encode(['message' => 'Gewinner nicht gefunden']);
    }

$conn->close();
?>
