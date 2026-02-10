<?php
// delete_gruppe.php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $groupID = isset($_POST['groupID']) ? intval($_POST['groupID']) : 0;
    if ($groupID <= 0) {
        echo json_encode(['message' => 'Ungültige Gruppen-ID.']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM JMDefinition_Gruppen WHERE GruppenUID = ?");
    if (!$stmt) {
        echo json_encode(['message' => 'Fehler beim Vorbereiten: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $groupID);
    if ($stmt->execute()) {
        echo json_encode(['success' => 'Gruppe gelöscht.']);
    } else {
        echo json_encode(['message' => 'Fehler beim Löschen: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['message' => 'Ungültige Anfragemethode.']);
}
$conn->close();
?>
