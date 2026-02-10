<?php
// delete_final_result.php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

$participant_id = isset($_POST['participant_id']) ? $_POST['participant_id'] : null;

if ($participant_id) {
    $year = date('Y');
    $sql = "DELETE FROM cupFinalResults WHERE ParticipantID = ? AND Year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $participant_id, $year);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "deleteSql" => $sql]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
} else {
    echo json_encode(["success" => false, "error" => "No participant_id provided"]);
}

?>
