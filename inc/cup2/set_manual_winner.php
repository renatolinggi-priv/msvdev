<?php
/**
 * set_manual_winner.php
 * Neue Funktion zum manuellen Setzen von Gewinnern
 * Erlaubt es, Verlierer als Gewinner zu markieren für die nächste Runde
 */

include '../config.php';
require_once __DIR__ . '/../csrf.inc.php';

// Header für JSON-Response
header('Content-Type: application/json');

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

csrf_require(true);
if (empty($_SESSION['user_id'])) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Nicht angemeldet']); exit; }

// Eingabevalidierung
$pair_id = isset($_POST['pair_id']) ? (int)$_POST['pair_id'] : 0;
$winner_id = isset($_POST['winner_id']) && $_POST['winner_id'] !== '' ? (int)$_POST['winner_id'] : null;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Validierung
if (!$pair_id) {
    echo json_encode(['success' => false, 'message' => 'Keine Paarungs-ID angegeben']);
    exit;
}

// Transaktion starten für Datenkonsistenz
$conn->begin_transaction();

try {
    // 1. Prüfen ob die Paarung existiert und alle Teilnehmer laden
    $check_sql = "SELECT cp.*,
                         m1.Name as Name1, m1.Vorname as Vorname1,
                         m2.Name as Name2, m2.Vorname as Vorname2,
                         m3.Name as Name3, m3.Vorname as Vorname3
                  FROM cupPairs cp
                  LEFT JOIN mitglieder m1 ON cp.Participant1 = m1.ID
                  LEFT JOIN mitglieder m2 ON cp.Participant2 = m2.ID
                  LEFT JOIN mitglieder m3 ON cp.Participant3 = m3.ID
                  WHERE cp.ID = ?";
    
    $stmt = $conn->prepare($check_sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $pair_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Paarung nicht gefunden');
    }
    
    $pair = $result->fetch_assoc();
    $valid_participants = array_filter([
        $pair['Participant1'], 
        $pair['Participant2'], 
        $pair['Participant3']
    ]);
    
    // 2. Wenn winner_id gesetzt ist (nicht null), prüfen ob er Teil der Paarung ist
    // Negative IDs = Verlierer bei Dreier-Gleichstand (abs() für Validierung)
    $check_id = $winner_id !== null ? abs($winner_id) : null;
    if ($check_id !== null && !in_array($check_id, $valid_participants)) {
        throw new Exception('Der gewählte Schütze ist nicht Teil dieser Paarung');
    }

    // 3. Prüfen ob bereits Gewinner in Runde 2 eingetragen wurden (nur wenn winner_id positiv)
    if ($winner_id !== null && $winner_id > 0) {
        $pair_year = $pair['Year'];
        $check_round2_sql = "SELECT COUNT(*) as count
                            FROM cupPairs
                            WHERE Round = 2
                            AND Year = ?
                            AND ? IN (Participant1, Participant2, Participant3)";

        $stmt = $conn->prepare($check_round2_sql);
        $stmt->bind_param("ii", $pair_year, $winner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            throw new Exception('Dieser Schütze ist bereits in Runde 2 eingetragen');
        }
    }
    
    // 4. Update der Paarung mit manuellem Gewinner
    $update_sql = "UPDATE cupPairs 
                   SET ManualWinner = ?, 
                       ManualWinnerReason = ?
                   WHERE ID = ?";
    
    $stmt = $conn->prepare($update_sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Bind parameters - winner_id kann null sein
    $stmt->bind_param("isi", $winner_id, $reason, $pair_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Update failed: " . $stmt->error);
    }
    
    // 5. Log-Eintrag erstellen (falls Tabelle existiert)
    $log_sql = "INSERT INTO cupAuditLog (Action, PairID, Details, Timestamp) 
                VALUES (?, ?, ?, NOW())";
    
    $action = $winner_id ? 'MANUAL_WINNER_SET' : 'MANUAL_WINNER_REMOVED';
    $details = json_encode([
        'winner_id' => $winner_id,
        'reason' => $reason,
        'pair' => [
            'round' => $pair['Round'],
            'participants' => $valid_participants
        ]
    ]);
    
    // Log nur erstellen wenn Tabelle existiert
    $table_check = $conn->query("SHOW TABLES LIKE 'cupAuditLog'");
    if ($table_check->num_rows > 0) {
        $stmt = $conn->prepare($log_sql);
        $stmt->bind_param("sis", $action, $pair_id, $details);
        $stmt->execute();
    }
    
    // Transaktion abschließen
    $conn->commit();
    
    // Erfolgreiche Response mit Details
    $response = [
        'success' => true,
        'message' => $winner_id ? 'Nachrücker erfolgreich gesetzt' : 'Nachrücker entfernt',
        'data' => [
            'pair_id' => $pair_id,
            'winner_id' => $winner_id,
            'reason' => $reason
        ]
    ];
    
    // Gewinner-Name hinzufügen wenn gesetzt
    if ($winner_id) {
        foreach ($valid_participants as $index => $pid) {
            if ($pid == $winner_id) {
                $participant_num = array_search($pid, $valid_participants) + 1;
                $response['data']['winner_name'] = $pair["Name$participant_num"] . ' ' . $pair["Vorname$participant_num"];
                break;
            }
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Bei Fehler Transaktion zurückrollen
    $conn->rollback();
    
    error_log("Error in set_manual_winner.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>