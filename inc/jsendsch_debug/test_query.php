<?php
// Test-Script für die SQL-Query
include '../config.php';

$year = 2025;

echo "<h3>Test 1: Alle Gäste mit Geburtsdatum für Jahr $year</h3>";
$sql1 = "SELECT id, name, geburtsdatum, vorname, nachname 
         FROM endstich_gaeste 
         WHERE jahr = ? AND geburtsdatum IS NOT NULL";

$stmt = $conn->prepare($sql1);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

echo "Gefundene Einträge: " . $result->num_rows . "<br><br>";
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", Geburtsdatum: " . $row['geburtsdatum'] . "<br>";
}

echo "<hr><h3>Test 2: Check endstich_selection für diese Gäste</h3>";
$sql2 = "SELECT g.id, g.name, g.geburtsdatum, sel.gast_spezialpreis
         FROM endstich_gaeste g
         LEFT JOIN endstich_selection sel ON g.id = sel.gast_id AND sel.jahr = ?
         WHERE g.jahr = ? AND g.geburtsdatum IS NOT NULL";

$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("ii", $year, $year);
$stmt2->execute();
$result2 = $stmt2->get_result();

echo "Gefundene Einträge: " . $result2->num_rows . "<br><br>";
while ($row = $result2->fetch_assoc()) {
    echo "ID: " . $row['id'] . ", Name: " . $row['name'] . 
         ", Geburtsdatum: " . $row['geburtsdatum'] . 
         ", Spezialpreis: " . ($row['gast_spezialpreis'] ? $row['gast_spezialpreis'] : 'NULL') . "<br>";
}

echo "<hr><h3>Test 3: Mit INNER JOIN wie im Original (nur mit gast_spezialpreis)</h3>";
$sql3 = "SELECT g.id, g.name, g.geburtsdatum, sel.gast_spezialpreis
         FROM endstich_gaeste g
         INNER JOIN endstich_selection sel ON g.id = sel.gast_id 
            AND sel.jahr = ? 
            AND sel.gast_spezialpreis IS NOT NULL
         WHERE g.jahr = ? AND g.geburtsdatum IS NOT NULL";

$stmt3 = $conn->prepare($sql3);
$stmt3->bind_param("ii", $year, $year);
$stmt3->execute();
$result3 = $stmt3->get_result();

echo "Gefundene Einträge: " . $result3->num_rows . "<br><br>";
while ($row = $result3->fetch_assoc()) {
    echo "ID: " . $row['id'] . ", Name: " . $row['name'] . 
         ", Geburtsdatum: " . $row['geburtsdatum'] . 
         ", Spezialpreis: " . $row['gast_spezialpreis'] . "<br>";
}

$conn->close();
?>
