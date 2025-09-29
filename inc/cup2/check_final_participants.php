<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../config.php';

$year = date("Y");

// Überprüfen, ob Finalteilnehmer vorhanden sind
$sql = "SELECT * FROM cupFinalResults WHERE Year = $year";
$result = $conn->query($sql);

if ($conn->error) {
    // Gib eine detaillierte Fehlermeldung zurück, falls die SQL-Abfrage fehlschlägt
    echo "Fehler in der Datenbankabfrage: " . $conn->error;
    exit();
}

// Falls mindestens ein Ergebnis vorhanden ist, wird 'true' zurückgegeben, sonst 'false'
if ($result->num_rows > 0) {
    echo 'true';
} else {
    echo 'false';
}

$conn->close();
?>
