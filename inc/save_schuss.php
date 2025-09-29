<?php
include '../config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$jungschuetzeID = isset($_POST['jungschuetzeID']) ? intval($_POST['jungschuetzeID']) : 0;
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

if ($jungschuetzeID == 0) {
    die("Ungültige JungschuetzeID");
}

$schussData = $_POST;

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function saveSchuss($conn, $jungschuetzeID, $year, $schussData, $table, $fields) {
    // Überprüfen, ob Daten vorhanden sind
    $checkSql = "SELECT ID FROM $table WHERE JungschuetzeID = ? AND Jahr = ?";
    $stmtCheck = $conn->prepare($checkSql);
    if (!$stmtCheck) {
        die("Prepare failed: " . $conn->error);
    }
    $stmtCheck->bind_param("ii", $jungschuetzeID, $year);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck && $resultCheck->num_rows > 0) {
        // Update - Erstelle SET-Klausel korrekt
        $updateParts = [];
        $values = [];
        $types = "";
        
        foreach ($fields as $field) {
            $updateParts[] = "$field = ?";
            // Spezialbehandlung für AbsendenAnmeldung (Text) vs. numerische Felder
            if ($field === 'AbsendenAnmeldung') {
                $values[] = isset($schussData[$field]) ? $schussData[$field] : '';
                $types .= "s";
            } else {
                $values[] = isset($schussData[$field]) ? intval($schussData[$field]) : 0;
                $types .= "i";
            }
        }
        
        $updateClause = implode(", ", $updateParts);
        $sql = "UPDATE $table SET $updateClause WHERE JungschuetzeID = ? AND Jahr = ?";
        
        // Füge JungschuetzeID und Jahr am Ende hinzu
        $values[] = $jungschuetzeID;
        $values[] = $year;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error . " SQL: " . $sql);
        }
        $stmt->bind_param($types, ...$values);
        
    } else {
        // Insert
        $fieldList = implode(", ", $fields);
        $placeholders = implode(", ", array_fill(0, count($fields), "?"));
        $sql = "INSERT INTO $table (JungschuetzeID, Jahr, $fieldList) VALUES (?, ?, $placeholders)";
        
        $values = [$jungschuetzeID, $year];
        $types = "ii";
        
        foreach ($fields as $field) {
            // Spezialbehandlung für AbsendenAnmeldung (Text) vs. numerische Felder
            if ($field === 'AbsendenAnmeldung') {
                $values[] = isset($schussData[$field]) ? $schussData[$field] : '';
                $types .= "s";
            } else {
                $values[] = isset($schussData[$field]) ? intval($schussData[$field]) : 0;
                $types .= "i";
            }
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error . " SQL: " . $sql);
        }
        $stmt->bind_param($types, ...$values);
    }

    if ($stmt->execute() === FALSE) {
        echo "Execute failed: " . $stmt->error . " SQL: " . $sql;
        return false;
    }
    
    $stmtCheck->close();
    $stmt->close();
    return true;
}

// Felder für die Tabellen - NUR die benötigten, ohne Kunst, Glück und Ansage
$endstichFields = ['Schuss1', 'Schuss2', 'Schuss3', 'Schuss4', 'Schuss5', 'Schuss6', 'Schuss7', 'Schuss8', 'Schuss9', 'Schuss10', 'Tiefschuss', 'AbsendenAnmeldung'];
$schwiniFields = ['P1Schuss1', 'P1Schuss2', 'P1Schuss3', 'P1Schuss4', 'P1Schuss5', 'P1Schuss6'];  // Nur eine Passe für Jungschützen
$zabigFields = ['ZSchuss1', 'ZSchuss2', 'ZSchuss3', 'ZSchuss4', 'ZSchuss5', 'ZSchuss6'];

// Nur die benötigten Tabellen speichern
saveSchuss($conn, $jungschuetzeID, $year, $schussData, 'endstich_jung', $endstichFields);
saveSchuss($conn, $jungschuetzeID, $year, $schussData, 'schwini_jung', $schwiniFields);
saveSchuss($conn, $jungschuetzeID, $year, $schussData, 'zabig_jung', $zabigFields);
// Kunst und Glück entfernt

$conn->close();

echo "Schüsse erfolgreich gespeichert";
?>
