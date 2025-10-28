<?php
// config.php einbinden, um die Datenbankverbindung herzustellen
require_once '../config.php';

// Werte aus dem Formular holen
$member_id = $_POST['member_id'];
$wert = $_POST['wert'];
$siegerdef = $_POST['siegerdef'];
$year = $_POST['year'];

// Mitglied-Name aus der Tabelle `mitglieder` holen
$sql = "SELECT Vorname, Name FROM mitglieder WHERE ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $name = $row['Name'] . ' ' . $row['Vorname'];

    // Daten in die Tabelle `sieger` einfügen
    $sql = "INSERT INTO sieger (Name, Wert, siegerdef, year) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siii", $name, $wert, $siegerdef, $year);

    if ($stmt->execute()) {
        echo "Neuer Sieger erfolgreich gespeichert";
    } else {
        echo "Fehler: " . $stmt->error;
    }
} else {
    echo "Mitglied nicht gefunden";
}

$stmt->close();
$conn->close();
?>
