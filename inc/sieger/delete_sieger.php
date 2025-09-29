<?php
// delete_sieger.php
require_once '../config.php';

// CSRF Token prüfen
session_start();
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF Token ungültig']);
    exit;
}

// Eingaben validieren
if (empty($_POST['sieger_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sieger ID ist erforderlich']);
    exit;
}

$sieger_id = intval($_POST['sieger_id']);

try {
    // Sieger aus der Datenbank löschen
    $sql = "DELETE FROM sieger WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $sieger_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Sieger erfolgreich gelöscht']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sieger nicht gefunden']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen: ' . $stmt->error]);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
}

$conn->close();
?>