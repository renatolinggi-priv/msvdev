<?php
include '../config.php';

// Prüfen, ob die Daten übermittelt wurden
if (isset($_POST['event_name']) && isset($_POST['event_date']) && isset($_POST['event_time'])) {
    // Die Werte aus dem Formular
    $eventName = $_POST['event_name'];
    $eventDate = $_POST['event_date'];
    $eventTime = $_POST['event_time'];
    $eventYear = $_POST['event_year'];

    // SQL-Abfrage zum Hinzufügen eines neuen Events
    $sql = "INSERT INTO wichtige_termine (name, date, time, year) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $eventName, $eventDate, $eventTime, $eventYear);
    $stmt->execute();

    // Erfolg
    echo "Event erfolgreich hinzugefügt!";
} else {
    // Fehler, falls die Daten fehlen
    echo "Fehler: Bitte alle Felder ausfüllen.";
}

$conn->close();
?>
