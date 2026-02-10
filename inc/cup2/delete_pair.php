<?php
include '../config.php'; // Verbindung zur Datenbank

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pair_id = $_POST['pair_id'] ?? null;

        if (!$pair_id) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Pair ID not provided']));
        }

        // Zuerst die Teilnehmer-IDs der Paarung ermitteln
        $query = "SELECT Participant1, Participant2, Participant3, Round FROM cupPairs WHERE ID = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        $stmt->bind_param("i", $pair_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $conn->close();
            http_response_code(404);
            die(json_encode(['success' => false, 'message' => 'Pair not found']));
        }

        $pair = $result->fetch_assoc();
        $participant1 = $pair['Participant1'];
        $participant2 = $pair['Participant2'];
        $participant3 = $pair['Participant3'];
        $round = $pair['Round'];

        error_log("delete_pair.php - Deleting pair ID $pair_id: Participants ($participant1, $participant2, $participant3), Round $round");

        // Lösche die Paarung aus der cupPairs-Tabelle
        $deletePairQuery = "DELETE FROM cupPairs WHERE ID = ?";
        $stmtDeletePair = $conn->prepare($deletePairQuery);
        if (!$stmtDeletePair) {
            throw new Exception("Prepare failed for delete pair: " . $conn->error);
        }
        $stmtDeletePair->bind_param("i", $pair_id);
        if (!$stmtDeletePair->execute()) {
            throw new Exception("Pair deletion failed: " . $stmtDeletePair->error);
        }
        error_log("delete_pair.php - Pair deleted successfully");

        // Wenn es sich um die erste Runde handelt, prüfe und lösche die abhängigen Einträge
        if ($round == 1) {
            $year = date("Y");
            // Überprüfe, ob diese Teilnehmer in Runde 2 vorhanden sind
            $queryRound2 = "SELECT ID FROM cupPairs WHERE (Participant1 = ? OR Participant2 = ? OR Participant3 = ?) AND Round = 2 AND Year = ?";
            $stmtRound2 = $conn->prepare($queryRound2);
            if (!$stmtRound2) {
                throw new Exception("Prepare failed for round 2 check: " . $conn->error);
            }
            $stmtRound2->bind_param("iiii", $participant1, $participant2, $participant3, $year);
            $stmtRound2->execute();
            $resultRound2 = $stmtRound2->get_result();

            while ($rowRound2 = $resultRound2->fetch_assoc()) {
                $round2PairId = $rowRound2['ID'];

                // Lösche die Paarung aus Runde 2
                $deleteRound2PairQuery = "DELETE FROM cupPairs WHERE ID = ?";
                $stmtDeleteRound2Pair = $conn->prepare($deleteRound2PairQuery);
                if (!$stmtDeleteRound2Pair) {
                    throw new Exception("Prepare failed for round 2 delete: " . $conn->error);
                }
                $stmtDeleteRound2Pair->bind_param("i", $round2PairId);
                if (!$stmtDeleteRound2Pair->execute()) {
                    throw new Exception("Round 2 pair deletion failed: " . $stmtDeleteRound2Pair->error);
                }
                error_log("delete_pair.php - Round 2 pair $round2PairId deleted");

                // Lösche die entsprechenden Sieger aus der Finalrunde
                $deleteFinalResultQuery = "DELETE FROM cupFinalResults WHERE (ParticipantID = ? OR ParticipantID = ? OR ParticipantID = ?) AND Year = ?";
                $stmtDeleteFinalResult = $conn->prepare($deleteFinalResultQuery);
                if (!$stmtDeleteFinalResult) {
                    throw new Exception("Prepare failed for final results delete: " . $conn->error);
                }
                $stmtDeleteFinalResult->bind_param("iiii", $participant1, $participant2, $participant3, $year);
                if (!$stmtDeleteFinalResult->execute()) {
                    throw new Exception("Final results deletion failed: " . $stmtDeleteFinalResult->error);
                }
                error_log("delete_pair.php - Final results deleted (from round 1 cascade)");
            }
        }

        // Lösche die entsprechenden Sieger aus der Finalrunde, falls vorhanden
        $deleteFinalResultQuery = "DELETE FROM cupFinalResults WHERE (ParticipantID = ? OR ParticipantID = ? OR ParticipantID = ?) AND Year = ?";
        $year = date("Y");
        $stmtDeleteFinalResult = $conn->prepare($deleteFinalResultQuery);
        if (!$stmtDeleteFinalResult) {
            throw new Exception("Prepare failed for final results delete: " . $conn->error);
        }
        $stmtDeleteFinalResult->bind_param("iiii", $participant1, $participant2, $participant3, $year);
        if (!$stmtDeleteFinalResult->execute()) {
            throw new Exception("Final results deletion failed: " . $stmtDeleteFinalResult->error);
        }
        error_log("delete_pair.php - Final results deleted");

        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Pair and related entries deleted successfully']);
    } catch (Exception $e) {
        $conn->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    $conn->close();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
