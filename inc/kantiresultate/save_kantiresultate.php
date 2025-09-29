<?php
include '../config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$jahr = isset($_POST['jahr']) ? $_POST['jahr'] : date('Y'); // Jahr wird aus der POST-Anfrage übernommen, falls nicht gesetzt, Standardwert ist das aktuelle Jahr

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $passe = $_POST['passe'];

    foreach ($passe as $mitgliedID => $passen) {
        $resultateSql = "SELECT * FROM kantiresultate WHERE MitgliedID = $mitgliedID AND Jahr = $jahr";
        $resultateResult = $conn->query($resultateSql);

        if ($resultateResult->num_rows > 0) {
            $updateSql = "UPDATE kantiresultate SET ";
            for ($i = 1; $i <= 5; $i++) {
                // Speichere auch 0-Werte, aber nur wenn sie gesetzt sind
                if (isset($passen[$i]) && $passen[$i] !== ''){
                    $updateSql .= "Passe$i = '" . $passen[$i] . "', ";
                }
            }
            $updateSql = rtrim($updateSql, ', ');
            $updateSql .= " WHERE MitgliedID = $mitgliedID AND Jahr = $jahr";
            $conn->query($updateSql);
        } else {
            // Prüfe ob irgendeine Passe einen Wert hat (auch 0)
            $hasAnyValue = false;
            for ($i = 1; $i <= 5; $i++) {
                if (isset($passen[$i]) && $passen[$i] !== '') {
                    $hasAnyValue = true;
                    break;
                }
            }
            
            if($hasAnyValue){
                $insertSql = "INSERT INTO kantiresultate (MitgliedID, Jahr, Passe1, Passe2, Passe3, Passe4, Passe5) VALUES ($mitgliedID, $jahr, ";
                for ($i = 1; $i <= 5; $i++) {
                    $value = isset($passen[$i]) && $passen[$i] !== '' ? $passen[$i] : '0';
                    $insertSql .= "'" . $value . "', ";
                }
                $insertSql = rtrim($insertSql, ', ');
                $insertSql .= ")";
                $conn->query($insertSql);
            }
        }
    }

    echo "Alle Ergebnisse wurden erfolgreich gespeichert";
}

$conn->close();
?>