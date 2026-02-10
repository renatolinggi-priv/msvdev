<?php
// delete_jmdefinition.php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

if ($conn->connect_error) {
    die(json_encode([
        "success" => false,
        "message" => "Connection failed: " . $conn->connect_error
    ]));
}

// Überprüfen, ob die ID gesetzt ist und numerisch ist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && is_numeric($_POST['id'])) {
    $id = intval($_POST['id']); // ID als Ganzzahl validieren

    // Sicherstellen, dass die ID größer als 0 ist
    if ($id > 0) {
        // SQL-Abfrage vorbereiten
        $stmt = $conn->prepare("DELETE FROM JMDefinition WHERE ID = ?");
        if (!$stmt) {
            echo json_encode([
                "success" => false,
                "message" => "Fehler beim Vorbereiten der Datenbankabfrage: " . $conn->error
            ]);
            $conn->close();
            exit;
        }

        // Parameter binden und Abfrage ausführen
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Eintrag erfolgreich gelöscht."
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Fehler beim Löschen des Eintrags: " . $stmt->error
            ]);
        }
        $stmt->close();
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Ungültige ID. Die ID muss größer als 0 sein."
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "ID nicht gesetzt oder ungültig."
    ]);
}

$conn->close();
?>
