<?php
// Test-Datei für Endstich-Datum
require_once '../dbconnect.inc.php';

header('Content-Type: text/html; charset=utf-8');

$conn = get_db_connection();
if (!$conn) {
    die("Datenbankverbindung fehlgeschlagen");
}

$year = isset($_GET['year']) ? intval($_GET['year']) : 2024;

echo "<h1>Debug Endstich-Datum für Jahr: $year</h1>";

// Test 1: Direkte Query
echo "<h2>Test 1: Direkte Query</h2>";
$sql = "SELECT * FROM JMDefinition WHERE Bezeichnung = 'Endstich' AND year = '$year'";
echo "<p>SQL: " . htmlspecialchars($sql) . "</p>";

$result = $conn->query($sql);
if (!$result) {
    echo "<p style='color:red;'>Query Error: " . $conn->error . "</p>";
} else {
    echo "<p style='color:green;'>Query erfolgreich!</p>";
    $data = $result->fetch_assoc();
    if ($data) {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        
        if (!empty($data['Schiesstage'])) {
            echo "<p>Schiesstage gefunden: " . htmlspecialchars($data['Schiesstage']) . "</p>";
            
            // Test Regex
            $pattern = '/(\d{1,2})\.\s*(\w+)/';
            if (preg_match($pattern, $data['Schiesstage'], $matches)) {
                echo "<p style='color:green;'>Regex Match erfolgreich!</p>";
                echo "<pre>";
                print_r($matches);
                echo "</pre>";
                echo "<p>Extrahiertes Datum: " . $matches[1] . ". " . $matches[2] . " " . $year . "</p>";
            } else {
                echo "<p style='color:red;'>Regex Match fehlgeschlagen!</p>";
            }
        }
    } else {
        echo "<p style='color:red;'>Kein Endstich-Eintrag gefunden für Jahr $year</p>";
    }
}

// Test 2: Alle Endstich-Einträge anzeigen
echo "<h2>Test 2: Alle Endstich-Einträge</h2>";
$sql2 = "SELECT year, Bezeichnung, Schiesstage FROM JMDefinition WHERE Bezeichnung = 'Endstich' ORDER BY year DESC";
$result2 = $conn->query($sql2);
if ($result2) {
    echo "<table border='1'>";
    echo "<tr><th>Jahr</th><th>Bezeichnung</th><th>Schiesstage</th></tr>";
    while ($row = $result2->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['year'] . "</td>";
        echo "<td>" . $row['Bezeichnung'] . "</td>";
        echo "<td>" . htmlspecialchars($row['Schiesstage']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 3: Prepared Statement Test
echo "<h2>Test 3: Prepared Statement</h2>";
$sql3 = "SELECT Schiesstage FROM JMDefinition WHERE Bezeichnung = ? AND year = ?";
$stmt = $conn->prepare($sql3);
if ($stmt) {
    $bezeichnung = 'Endstich';
    $stmt->bind_param("si", $bezeichnung, $year);
    $stmt->execute();
    $result3 = $stmt->get_result();
    $data3 = $result3->fetch_assoc();
    if ($data3) {
        echo "<p style='color:green;'>Prepared Statement erfolgreich!</p>";
        echo "<p>Schiesstage: " . htmlspecialchars($data3['Schiesstage']) . "</p>";
    } else {
        echo "<p style='color:red;'>Kein Ergebnis mit Prepared Statement</p>";
    }
    $stmt->close();
} else {
    echo "<p style='color:red;'>Prepared Statement Fehler: " . $conn->error . "</p>";
}

$conn->close();
?>
