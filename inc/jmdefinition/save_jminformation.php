<?php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

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
