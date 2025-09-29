<?php
include '../config.php'; // Konfigurationsdatei

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
// SQL-Abfrage, um die Daten für den Standcup Final abzurufen
$sql = "SELECT ParticipantName, Result, club FROM cupStandFinal where Year = $year";
$result = $conn->query($sql);

$data = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

// Rückgabe der Daten im JSON-Format
header('Content-Type: application/json');
echo json_encode($data);
?>
