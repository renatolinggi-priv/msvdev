<?php
// update_order.php
include '../config.php';

if ($conn->connect_error) {
    die(json_encode([
        "success" => false,
        "message" => "Connection failed: " . $conn->connect_error
    ]));
}

// Überprüfen, ob die Anfrage ein POST ist und die Reihenfolge übergeben wurde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order'])) {
    $order = $_POST['order'];

    // SQL-Abfrage vorbereiten, um Sicherheitsrisiken zu minimieren
    $stmt = $conn->prepare("UPDATE JMDefinition SET Reihenfolge = ? WHERE ID = ?");
    if (!$stmt) {
        echo json_encode([
            "success" => false,
            "message" => "Fehler beim Vorbereiten der Datenbankabfrage: " . $conn->error
        ]);
        $conn->close();
        exit;
    }

    // Reihenfolge aktualisieren
    $conn->begin_transaction(); // Transaktion starten
    try {
        foreach ($order as $newPosition => $id) {
            $newPosition = intval($newPosition) + 1; // Position 1-basiert speichern
            $id = intval($id); // ID absichern

            $stmt->bind_param("ii", $newPosition, $id);
            if (!$stmt->execute()) {
                throw new Exception("Fehler beim Aktualisieren der Reihenfolge für ID $id: " . $stmt->error);
            }
        }

        $conn->commit(); // Transaktion abschließen
        echo json_encode([
            "success" => true,
            "message" => "Reihenfolge erfolgreich aktualisiert."
        ]);
    } catch (Exception $e) {
        $conn->rollback(); // Änderungen zurücksetzen
        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
    } finally {
        $stmt->close();
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Ungültige Anfrage oder fehlende Daten."
    ]);
}

$conn->close();
?>
