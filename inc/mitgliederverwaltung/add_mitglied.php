<?php
// add_mitglied.php - ERWEITERTE VERSION
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $conn->real_escape_string($_POST['id']);
    $vorname = $conn->real_escape_string($_POST['vorname']);
    $name = $conn->real_escape_string($_POST['name']);
    $geburtsdatum = $conn->real_escape_string($_POST['birthday']);
    $waffenid = intval($_POST['waffenid']);
    $status = intval($_POST['status']);
    $ehrenmitglied = intval($_POST['ehrenmitglied']);
    
    // Neue Felder
    $strasse = $conn->real_escape_string($_POST['strasse'] ?? '');
    $plz = $conn->real_escape_string($_POST['plz'] ?? '');
    $ort = $conn->real_escape_string($_POST['ort'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $telefon = $conn->real_escape_string($_POST['telefon'] ?? '');
    $mobile = $conn->real_escape_string($_POST['mobile'] ?? '');
    $notizen = $conn->real_escape_string($_POST['notizen'] ?? '');

    $sql = "INSERT INTO mitglieder (ID, Vorname, Name, Geburtsdatum, WaffenID, Status, Ehrenmitglied,
            Strasse, PLZ, Ort, Email, Telefon, Mobile, Notizen)
            VALUES ('$id', '$vorname', '$name', '$geburtsdatum', '$waffenid', '$status', '$ehrenmitglied',
            '$strasse', '$plz', '$ort', '$email', '$telefon', '$mobile', '$notizen')";

    if ($conn->query($sql) === TRUE) {
        echo "Mitglied erfolgreich hinzugefügt";
    } else {
        echo "Fehler: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();
}
?>