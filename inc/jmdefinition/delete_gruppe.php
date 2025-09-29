<?php
// delete_gruppe.php
include '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $groupID = isset($_POST['groupID']) ? intval($_POST['groupID']) : 0;
    if ($groupID <= 0) {
        echo json_encode(['error' => 'Ungültige Gruppen-ID.']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM JMDefinition_Gruppen WHERE GruppenUID = ?");
    if (!$stmt) {
        echo json_encode(['error' => 'Fehler beim Vorbereiten: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $groupID);
    if ($stmt->execute()) {
        echo json_encode(['success' => 'Gruppe gelöscht.']);
    } else {
        echo json_encode(['error' => 'Fehler beim Löschen: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Ungültige Anfragemethode.']);
}
$conn->close();
?>
