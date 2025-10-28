<?php
include '../config.php';

$mitgliedID = $_POST['mitgliedID'];
$jahr = isset($_POST['jahr']) ? $_POST['jahr'] : date('Y');
$schussData = $_POST;

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function saveSchuss($conn, $mitgliedID, $jahr, $schussData, $table, $fields) {
    // Überprüfen, ob Daten für dieses Mitglied und Jahr bereits existieren
    $checkSql = "SELECT ID FROM $table WHERE MitgliedID = $mitgliedID AND Jahr = $jahr";
    $checkResult = $conn->query($checkSql);

    $columns = implode(", ", $fields);
    $values = implode(", ", array_map(function($field) use ($schussData) {
        return intval($schussData[$field]);
    }, $fields));
    
    $updateValues = implode(", ", array_map(function($field) use ($schussData) {
        return "$field = " . intval($schussData[$field]);
    }, $fields));

    if ($checkResult->num_rows > 0) {
        // Update
        $row = $checkResult->fetch_assoc();
        $id = $row['ID'];
        $sql = "UPDATE $table SET $updateValues WHERE ID = $id AND Jahr = $jahr";
    } else {
        // Insert
        $sql = "INSERT INTO $table (MitgliedID, Jahr, $columns) VALUES ($mitgliedID, $jahr, $values)";
    }

    if ($conn->query($sql) === FALSE) {
        echo "Fehler: " . $conn->error;
    }
}

// Spezielle Funktion für endresultate_partner (Sie und Er)
// NEU: Nur speichern, wenn mindestens ein Wert > 0 vorhanden ist
function saveSieUnder($conn, $mitgliedID, $jahr, $schussData, $fields) {
    // Prüfen, ob mindestens ein Wert > 0 vorhanden ist
    $hasValues = false;
    foreach ($fields as $field) {
        if (isset($schussData[$field]) && intval($schussData[$field]) > 0) {
            $hasValues = true;
            break;
        }
    }
    
    // Wenn keine Werte vorhanden sind, nichts tun
    if (!$hasValues) {
        return;
    }
    
    // Überprüfen, ob Daten für dieses Mitglied und Jahr bereits existieren
    $checkSql = "SELECT ID FROM endresultate_partner WHERE MitgliedID = $mitgliedID AND Jahr = $jahr";
    $checkResult = $conn->query($checkSql);

    $updateValues = implode(", ", array_map(function($field) use ($schussData) {
        return "$field = " . intval($schussData[$field]);
    }, $fields));

    if ($checkResult->num_rows > 0) {
        // Update - nur Sie und Er Felder aktualisieren
        $row = $checkResult->fetch_assoc();
        $id = $row['ID'];
        $sql = "UPDATE endresultate_partner SET $updateValues WHERE ID = $id AND Jahr = $jahr";
    } else {
        // Insert - mit Default PartnerName
        $columns = implode(", ", $fields);
        $values = implode(", ", array_map(function($field) use ($schussData) {
            return intval($schussData[$field]);
        }, $fields));
        $sql = "INSERT INTO endresultate_partner (MitgliedID, Jahr, PartnerName, $columns) VALUES ($mitgliedID, $jahr, 'Partner', $values)";
    }

    if ($conn->query($sql) === FALSE) {
        echo "Fehler bei Sie und Er: " . $conn->error;
    }
}

// Felder für die Tabellen
$endstichFields = ['Schuss1', 'Schuss2', 'Schuss3', 'Schuss4', 'Schuss5', 'Schuss6', 'Schuss7', 'Schuss8', 'Schuss9', 'Schuss10', 'Tiefschuss', 'AbsendenAnmeldung'];
$schwiniFields = ['P1Schuss1', 'P1Schuss2', 'P1Schuss3', 'P1Schuss4', 'P1Schuss5', 'P1Schuss6', 'P2Schuss1', 'P2Schuss2', 'P2Schuss3', 'P2Schuss4', 'P2Schuss5', 'P2Schuss6'];
$kunstFields = ['KSchuss1', 'KSchuss2', 'KSchuss3', 'KSchuss4', 'KSchuss5'];
$glueckFields = ['GSchuss1', 'GSchuss2', 'GSchuss3'];
$zabigFields = ['ZSchuss1', 'ZSchuss2', 'ZSchuss3', 'ZSchuss4', 'ZSchuss5', 'ZSchuss6', 'Ansage'];
$sieunderFields = ['SieErSchuss6', 'SieErSchuss7', 'SieErSchuss8', 'SieErSchuss9', 'SieErSchuss10'];

// Daten speichern
saveSchuss($conn, $mitgliedID, $jahr, array_intersect_key($schussData, array_flip($endstichFields)), 'endstich', $endstichFields);
saveSchuss($conn, $mitgliedID, $jahr, array_intersect_key($schussData, array_flip($schwiniFields)), 'schwini', $schwiniFields);
saveSchuss($conn, $mitgliedID, $jahr, array_intersect_key($schussData, array_flip($kunstFields)), 'kunst', $kunstFields);
saveSchuss($conn, $mitgliedID, $jahr, array_intersect_key($schussData, array_flip($glueckFields)), 'glueck', $glueckFields);
saveSchuss($conn, $mitgliedID, $jahr, array_intersect_key($schussData, array_flip($zabigFields)), 'zabig', $zabigFields);

// Sie und Er speichern - NUR wenn Werte vorhanden sind
saveSieUnder($conn, $mitgliedID, $jahr, array_intersect_key($schussData, array_flip($sieunderFields)), $sieunderFields);

$conn->close();

echo "Schüsse erfolgreich gespeichert";
?>
