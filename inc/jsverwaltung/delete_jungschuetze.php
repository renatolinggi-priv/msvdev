<?php
require_once '../config.php';
header('Content-Type: application/json');

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("DELETE FROM jungschuetzen WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Jungschütze erfolgreich gelöscht']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Fehler beim Löschen: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>
