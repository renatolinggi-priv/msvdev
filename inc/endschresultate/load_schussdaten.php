<?php
//load_schussdaten.php
include '../config.php';

$mitgliedID = $_GET['mitgliedID'];
$jahr = isset($_GET['year']) ? intval($_GET['year']) : date('Y'); // Jahr aus der GET-Anfrage holen (falls nicht übergeben, Standard auf aktuelles Jahr)

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$response = [];

// Daten aus der Tabelle endstich laden
$sql = "SELECT Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, Schuss6, Schuss7, Schuss8, Schuss9, Schuss10, Tiefschuss, AbsendenAnmeldung 
        FROM endstich 
        WHERE MitgliedID = $mitgliedID AND Jahr = $jahr";  // Jahr mit in die Abfrage aufnehmen
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    foreach ($row as $key => $value) {
        $response[$key] = $value;
    }
}

// Daten aus der Tabelle schwini laden
$sql = "SELECT P1Schuss1, P1Schuss2, P1Schuss3, P1Schuss4, P1Schuss5, P1Schuss6, P2Schuss1, P2Schuss2, P2Schuss3, P2Schuss4, P2Schuss5, P2Schuss6 
        FROM schwini 
        WHERE MitgliedID = $mitgliedID AND Jahr = $jahr";  // Jahr mit in die Abfrage aufnehmen
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    foreach ($row as $key => $value) {
        $response[$key] = $value;
    }
}

// Daten aus der Tabelle Kunst laden
$sql = "SELECT KSchuss1, KSchuss2, KSchuss3, KSchuss4, KSchuss5 
        FROM kunst 
        WHERE MitgliedID = $mitgliedID AND Jahr = $jahr";  // Jahr mit in die Abfrage aufnehmen
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    foreach ($row as $key => $value) {
        $response[$key] = $value;
    }
}

// Daten aus der Tabelle Glueck laden
$sql = "SELECT GSchuss1, GSchuss2, GSchuss3 
        FROM glueck 
        WHERE MitgliedID = $mitgliedID AND Jahr = $jahr";  // Jahr mit in die Abfrage aufnehmen
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    foreach ($row as $key => $value) {
        $response[$key] = $value;
    }
}

// Daten aus der Tabelle Zabig laden
$sql = "SELECT ZSchuss1, ZSchuss2, ZSchuss3, ZSchuss4, ZSchuss5, ZSchuss6, Ansage 
        FROM zabig 
        WHERE MitgliedID = $mitgliedID AND Jahr = $jahr";  // Jahr mit in die Abfrage aufnehmen
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    foreach ($row as $key => $value) {
        $response[$key] = $value;
    }
}

// Daten aus der Tabelle endresultate_partner laden (Sie und Er)
$sql = "SELECT SieErSchuss6, SieErSchuss7, SieErSchuss8, SieErSchuss9, SieErSchuss10 
        FROM endresultate_partner 
        WHERE MitgliedID = $mitgliedID AND Jahr = $jahr";  // Jahr mit in die Abfrage aufnehmen
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    foreach ($row as $key => $value) {
        $response[$key] = $value;
    }
}

echo json_encode($response);

$conn->close();
?>
