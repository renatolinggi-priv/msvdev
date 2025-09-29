<?php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zusatztext'])) {
    $zusatztext = trim($_POST['zusatztext']);

    // Falls Tabelle leer ist, neuen Eintrag erstellen, sonst aktualisieren
    $sql = "INSERT INTO JMInformation (text) VALUES (?) 
            ON DUPLICATE KEY UPDATE text = VALUES(text)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => "Fehler: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("s", $zusatztext);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'message' => "Zusatztext erfolgreich gespeichert."]);
}
?>
