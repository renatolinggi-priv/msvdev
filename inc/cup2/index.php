<?php
include '../config.php'; // Passe den Pfad zu deiner Konfigurationsdatei entsprechend an


$pairId = 81;
    // Ermittle den Gewinner aus der gelöschten Paarung in cupPairs
    $winnerSql = "SELECT 
                    CASE 
                        WHEN Result1 > Result2 THEN Participant1 
                        ELSE Participant2 
                    END AS WinnerID 
                  FROM cupPairs 
                  WHERE ID = '$pairId'";

    $winnerResult = $conn->query($winnerSql);

    if ($winnerResult === FALSE) {
        echo json_encode(['error' => 'Fehler bei der Abfrage nach dem Gewinner: ' . $conn->error]);
       
    }

    if ($winnerResult->num_rows > 0) {
        $winnerRow = $winnerResult->fetch_assoc();
        $winnerId = $winnerRow['WinnerID'];

        if (empty($winnerId)) {
            echo json_encode(['error' => 'Kein Gewinner gefunden']);
            
        }

        $deleteSql = "DELETE FROM cupFinalResults WHERE ParticipantID = '$winnerId'";
echo $deleteSql;
        // Gebe die SQL-Abfrage als JSON aus, damit wir sie in JavaScript loggen können
        echo json_encode(['success' => true, 'deleteSql' => $deleteSql]);

        // Führe die Löschabfrage aus
        if ($conn->query($deleteSql) === TRUE) {
            echo json_encode(['success' => 'Finalergebnisseintrag erfolgreich gelöscht']);
        } else {
            echo json_encode(['error' => 'Fehler beim Löschen des Finalergebnisseintrags: ' . $conn->error]);
        }
    } else {
        echo json_encode(['error' => 'Gewinner nicht gefunden']);
    }


$conn->close();
?>
