<?php
// save_finalresults.php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

header('Content-Type: application/json');

// Daten empfangen
$pairs = json_decode($_POST['pairs'], true);
$year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');

if (!is_array($pairs)) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
    exit;
}

$errors = [];
$success_count = 0;

// Transaktion starten
$conn->begin_transaction();

try {
    foreach ($pairs as $pair) {
        if (count($pair) < 3) continue;
        
        $participant_id = (int)$pair[0];
        $result = isset($pair[1]) ? (int)$pair[1] : null;
        $lowshot = isset($pair[2]) ? (int)$pair[2] : null;
        
        // Prüfen ob bereits vorhanden
        $check_sql = "SELECT ID FROM cupFinalResults WHERE ParticipantID = ? AND Year = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $participant_id, $year);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($existing) {
            // Update
            $update_sql = "UPDATE cupFinalResults SET Result = ?, LowShot = ? WHERE ParticipantID = ? AND Year = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("iiii", $result, $lowshot, $participant_id, $year);
            if ($update_stmt->execute()) {
                $success_count++;
            } else {
                $errors[] = "Update fehlgeschlagen für Teilnehmer $participant_id";
            }
            $update_stmt->close();
        } else {
            // Insert
            $insert_sql = "INSERT INTO cupFinalResults (ParticipantID, Result, LowShot, Year) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iiii", $participant_id, $result, $lowshot, $year);
            if ($insert_stmt->execute()) {
                $success_count++;
            } else {
                $errors[] = "Insert fehlgeschlagen für Teilnehmer $participant_id";
            }
            $insert_stmt->close();
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => empty($errors),
        'message' => "$success_count Finalergebnisse gespeichert",
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Datenbankfehler: ' . $e->getMessage()
    ]);
}

$conn->close();
?>