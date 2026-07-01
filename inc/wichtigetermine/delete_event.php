<?php
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

// Sicherstellen, dass die Event-ID übergeben wurde
if (isset($_POST['event_id']) && is_numeric($_POST['event_id'])) {
    $eventId = intval($_POST['event_id']);

    // Überprüfe, ob der Event existiert
    $checkSql = "SELECT COUNT(*) FROM wichtige_termine WHERE ID = ?";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->bind_param("i", $eventId);
    $stmtCheck->execute();
    $stmtCheck->bind_result($count);
    $stmtCheck->fetch();
    $stmtCheck->close();

    if ($count > 0) {
        // Event existiert, führe das Löschen durch
        $sql = "DELETE FROM wichtige_termine WHERE ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $eventId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Termin gelöscht']); // Erfolgreiches Löschen
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen']);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Event nicht gefunden']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Ungültige Event-ID']);
}

$conn->close();
?>
