<?php
include '../config.php'; // Verbindung zur Datenbank

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pair_id = $_POST['pair_id']; // Paarungs-ID von AJAX empfangen

    if ($pair_id) {
        // Zuerst die Teilnehmer-IDs der Paarung ermitteln
        $query = "SELECT Participant1, Participant2, Participant3, Round FROM cupPairs WHERE ID = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        $stmt->bind_param("i", $pair_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $pair = $result->fetch_assoc();
            $participant1 = $pair['Participant1'];
            $participant2 = $pair['Participant2'];
            $participant3 = $pair['Participant3'];
            $round = $pair['Round'];

            // Debug-Ausgabe der Teilnehmer-IDs und der Runde
            var_dump($participant1, $participant2, $participant3, $round);

            // Lösche die Paarung aus der cupPairs-Tabelle
            $deletePairQuery = "DELETE FROM cupPairs WHERE ID = ?";
            $stmtDeletePair = $conn->prepare($deletePairQuery);
            $stmtDeletePair->bind_param("i", $pair_id);
            $stmtDeletePair->execute();

            if ($stmtDeletePair->affected_rows > 0) {
                echo "Pair deleted successfully.<br>";
            } else {
                echo "Pair deletion failed: " . $stmtDeletePair->error . "<br>";
            }

            // Wenn es sich um die erste Runde handelt, prüfe und lösche die abhängigen Einträge
            if ($round == 1) {
                // Überprüfe, ob diese Teilnehmer in Runde 2 vorhanden sind
                $queryRound2 = "SELECT ID FROM cupPairs WHERE (Participant1 = ? OR Participant2 = ? OR Participant3 = ?) AND Round = 2 AND Year = ?";
                $year = date("Y");
                $stmtRound2 = $conn->prepare($queryRound2);
                $stmtRound2->bind_param("iiii", $participant1, $participant2, $participant3, $year);
                $stmtRound2->execute();
                $resultRound2 = $stmtRound2->get_result();

                while ($rowRound2 = $resultRound2->fetch_assoc()) {
                    $round2PairId = $rowRound2['ID'];

                    // Lösche die Paarung aus Runde 2
                    $deleteRound2PairQuery = "DELETE FROM cupPairs WHERE ID = ?";
                    $stmtDeleteRound2Pair = $conn->prepare($deleteRound2PairQuery);
                    $stmtDeleteRound2Pair->bind_param("i", $round2PairId);
                    $stmtDeleteRound2Pair->execute();

                    if ($stmtDeleteRound2Pair->affected_rows > 0) {
                        echo "Round 2 pair deleted successfully.<br>";
                    } else {
                        echo "Round 2 pair deletion failed: " . $stmtDeleteRound2Pair->error . "<br>";
                    }

                    // Lösche die entsprechenden Sieger aus der Finalrunde
                    $deleteFinalResultQuery = "DELETE FROM cupFinalResults WHERE (ParticipantID = ? OR ParticipantID = ? OR ParticipantID = ?) AND Year = ?";
                    $stmtDeleteFinalResult = $conn->prepare($deleteFinalResultQuery);
                    $stmtDeleteFinalResult->bind_param("iiii", $participant1, $participant2, $participant3, $year);
                    $stmtDeleteFinalResult->execute();

                    // Debug-Ausgabe nach der Löschung
                    if ($stmtDeleteFinalResult->affected_rows > 0) {
                        echo "Final results deleted successfully.<br>";
                    } else {
                        echo "Final results deletion failed: " . $stmtDeleteFinalResult->error . "<br>";
                    }
                }
            }

            // Lösche die entsprechenden Sieger aus der Finalrunde, falls vorhanden
            $deleteFinalResultQuery = "DELETE FROM cupFinalResults WHERE (ParticipantID = ? OR ParticipantID = ? OR ParticipantID = ?) AND Year = ?";
            $year = date("Y");
            $stmtDeleteFinalResult = $conn->prepare($deleteFinalResultQuery);
            $stmtDeleteFinalResult->bind_param("iiii", $participant1, $participant2, $participant3, $year);
            $stmtDeleteFinalResult->execute();

            if ($stmtDeleteFinalResult->affected_rows > 0) {
                echo "Final results deleted successfully.<br>";
            } else {
                echo "Final results deletion failed: " . $stmtDeleteFinalResult->error . "<br>";
            }

            $response = array("success" => true, "message" => "Pair and related entries deleted successfully");
        } else {
            $response = array("success" => false, "message" => "Pair not found");
        }
        
        echo json_encode($response);
    } else {
        $response = array("success" => false, "message" => "Pair ID not provided");
        echo json_encode($response);
    }
} else {
    $response = array("success" => false, "message" => "Invalid request method");
    echo json_encode($response);
}

$conn->close();
?>
