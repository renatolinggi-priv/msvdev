<?php
include '../config.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "DELETE FROM jungschuetzen_resultate";
if ($conn->query($sql) === TRUE) {
    echo "Alle aktuellen Resultate erfolgreich gelöscht";
} else {
    echo "Fehler beim Löschen der aktuellen Resultate: " . $conn->error;
}

$conn->close();
?>
