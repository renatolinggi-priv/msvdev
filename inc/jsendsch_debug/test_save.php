<?php
// Debug-Script für Speicherproblem
include '../config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test-Daten
$jungschuetzeID = 16; // Test Test
$year = 2025;

echo "<h3>Test Speicherung für JungschuetzeID: $jungschuetzeID, Jahr: $year</h3>";

// 1. Prüfe ob der Jungschütze existiert
$check = "SELECT id, name, geburtsdatum FROM endstich_gaeste WHERE id = ?";
$stmt = $conn->prepare($check);
$stmt->bind_param("i", $jungschuetzeID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "✓ Jungschütze gefunden: " . $row['name'] . " (Geburtsdatum: " . $row['geburtsdatum'] . ")<br><br>";
} else {
    echo "✗ Jungschütze mit ID $jungschuetzeID nicht gefunden!<br><br>";
}

// 2. Versuche einen Testwert in endstich_jung zu speichern
echo "<h4>Test: Insert in endstich_jung</h4>";
$testInsert = "INSERT INTO endstich_jung (JungschuetzeID, Jahr, Schuss1, Schuss2) VALUES (?, ?, 5, 6)";
$stmt = $conn->prepare($testInsert);
if ($stmt) {
    $stmt->bind_param("ii", $jungschuetzeID, $year);
    if ($stmt->execute()) {
        echo "✓ Erfolgreich eingefügt! Insert ID: " . $conn->insert_id . "<br>";
        
        // Lösche wieder
        $delete = "DELETE FROM endstich_jung WHERE ID = ?";
        $delStmt = $conn->prepare($delete);
        $delStmt->bind_param("i", $conn->insert_id);
        $delStmt->execute();
        echo "Testdaten wieder gelöscht.<br>";
    } else {
        echo "✗ Fehler beim Einfügen: " . $stmt->error . "<br>";
        echo "SQL State: " . $stmt->sqlstate . "<br>";
    }
} else {
    echo "✗ Prepare fehlgeschlagen: " . $conn->error . "<br>";
}

// 3. Prüfe Foreign Key Constraints
echo "<br><h4>Foreign Key Constraints prüfen:</h4>";
$fkCheck = "SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('endstich_jung', 'schwini_jung', 'zabig_jung')
    AND REFERENCED_TABLE_NAME IS NOT NULL";

$result = $conn->query($fkCheck);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Tabelle: " . $row['TABLE_NAME'] . "<br>";
        echo "  - Constraint: " . $row['CONSTRAINT_NAME'] . "<br>";
        echo "  - Verweist auf: " . $row['REFERENCED_TABLE_NAME'] . "." . $row['REFERENCED_COLUMN_NAME'] . "<br>";
        
        if ($row['REFERENCED_TABLE_NAME'] == 'jungschuetzen') {
            echo "  <span style='color: red;'>✗ PROBLEM: Verweist auf alte Tabelle 'jungschuetzen'!</span><br>";
        } else {
            echo "  <span style='color: green;'>✓ OK</span><br>";
        }
        echo "<br>";
    }
}

// 4. Test mit echten Schussdaten
echo "<h4>Test: Vollständige Speicherung</h4>";
$testData = [
    'Schuss1' => 8,
    'Schuss2' => 9,
    'Schuss3' => 7,
    'Schuss4' => 10,
    'Schuss5' => 8
];

// Check ob bereits Daten vorhanden sind
$checkExisting = "SELECT ID FROM endstich_jung WHERE JungschuetzeID = ? AND Jahr = ?";
$stmt = $conn->prepare($checkExisting);
$stmt->bind_param("ii", $jungschuetzeID, $year);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "Daten bereits vorhanden - würde UPDATE verwenden<br>";
} else {
    echo "Keine Daten vorhanden - würde INSERT verwenden<br>";
}

$conn->close();
?>
