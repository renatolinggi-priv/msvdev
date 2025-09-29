<?php
require_once '../config.php';
header('Content-Type: application/json');

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
