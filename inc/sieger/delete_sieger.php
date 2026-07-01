<?php
// delete_sieger.php
require_once '../config.php';
require_once __DIR__ . '/../csrf.inc.php';

// CSRF Token prüfen
session_start();
csrf_require(true);

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