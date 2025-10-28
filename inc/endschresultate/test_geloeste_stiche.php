<?php
/**
 * test_geloeste_stiche.php
 * Debug-Script zum Testen der Stich-Erkennung
 * 
 * Aufruf: test_geloeste_stiche.php?mitgliedID=112101&jahr=2025
 */

include '../config.php';

$mitgliedID = isset($_GET['mitgliedID']) ? intval($_GET['mitgliedID']) : 112101;
$jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : 2025;

echo "<h2>Stich-Test für Mitglied $mitgliedID im Jahr $jahr</h2>";
echo "<hr>";

// Test 1: Welche Selections existieren?
echo "<h3>1. Selections in endstich_selection:</h3>";
$sql = "SELECT es.id, es.stich_id, sd.code, sd.name, sd.active
        FROM endstich_selection es
        INNER JOIN endstich_definition sd ON es.stich_id = sd.id
        WHERE es.mitglied_id = ? AND es.jahr = ?
        ORDER BY sd.sort_order";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $mitgliedID, $jahr);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Selection ID</th><th>Stich ID</th><th>Code</th><th>Name</th><th>Active</th></tr>";

$foundCodes = [];
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['stich_id'] . "</td>";
    echo "<td><strong>" . $row['code'] . "</strong></td>";
    echo "<td>" . $row['name'] . "</td>";
    echo "<td>" . ($row['active'] ? '✓ Ja' : '✗ Nein') . "</td>";
    echo "</tr>";
    if ($row['active']) {
        $foundCodes[] = $row['code'];
    }
}
echo "</table>";
$stmt->close();

echo "<p><strong>Gefundene aktive Codes:</strong> " . implode(', ', $foundCodes) . "</p>";

// Test 2: Die Query die load_schussdaten.php verwendet
echo "<hr>";
echo "<h3>2. Query aus load_schussdaten.php (nur aktive):</h3>";
$sql_geloest = "SELECT DISTINCT sd.code 
                FROM endstich_selection es
                INNER JOIN endstich_definition sd ON es.stich_id = sd.id
                WHERE es.mitglied_id = ? AND es.jahr = ? AND sd.active = 1";

$stmt = $conn->prepare($sql_geloest);
$stmt->bind_param("ii", $mitgliedID, $jahr);
$stmt->execute();
$result = $stmt->get_result();

$geloesteStiche = [];
while ($row = $result->fetch_assoc()) {
    $geloesteStiche[] = $row['code'];
}
$stmt->close();

echo "<p><strong>Gelöste Stiche (Array):</strong></p>";
echo "<pre>" . print_r($geloesteStiche, true) . "</pre>";
echo "<p><strong>Als String:</strong> " . implode(', ', $geloesteStiche) . "</p>";

// Test 3: Mapping Check
echo "<hr>";
echo "<h3>3. Mapping-Check:</h3>";
$mappingChecks = [
    'END' => 'Endstich aktiviert?',
    'SCHWINI_P1' => 'Schwini P1 aktiviert?',
    'SCHWINI_P2' => 'Schwini P2 aktiviert?',
    'KUNST' => 'Kunst aktiviert?',
    'GLUECK' => 'Glück aktiviert?',
    'ZABIG' => 'Zabig aktiviert?',
    'DIFF' => 'Differenzler (Ansage) aktiviert?',
    'SIEUNDER' => 'Sie und Er aktiviert?'
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Code</th><th>Beschreibung</th><th>Status</th></tr>";
foreach ($mappingChecks as $code => $description) {
    $active = in_array($code, $geloesteStiche);
    echo "<tr>";
    echo "<td><strong>$code</strong></td>";
    echo "<td>$description</td>";
    echo "<td style='color: " . ($active ? 'green' : 'red') . "'>" . ($active ? '✓ JA' : '✗ NEIN') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test 4: JSON Output wie load_schussdaten.php
echo "<hr>";
echo "<h3>4. JSON Output (wie load_schussdaten.php):</h3>";
echo "<pre>" . json_encode(['geloesteStiche' => $geloesteStiche], JSON_PRETTY_PRINT) . "</pre>";

// Test 5: Schwini-Spezialfall
echo "<hr>";
echo "<h3>5. Schwini-Spezialfall:</h3>";
$schwiniCheck = in_array('SCHWINI_P1', $geloesteStiche) || in_array('SCHWINI_P2', $geloesteStiche);
echo "<p>Schwini P1 gelöst: " . (in_array('SCHWINI_P1', $geloesteStiche) ? 'JA' : 'NEIN') . "</p>";
echo "<p>Schwini P2 gelöst: " . (in_array('SCHWINI_P2', $geloesteStiche) ? 'JA' : 'NEIN') . "</p>";
echo "<p><strong>Schwini-Tabelle laden: " . ($schwiniCheck ? '✓ JA' : '✗ NEIN') . "</strong></p>";

$conn->close();

echo "<hr>";
echo "<p><em>Test abgeschlossen um " . date('Y-m-d H:i:s') . "</em></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    padding: 20px;
    background: #f5f5f5;
}
table {
    background: white;
    border-collapse: collapse;
    margin: 10px 0;
}
th {
    background: #333;
    color: white;
    padding: 8px;
}
td {
    padding: 8px;
}
pre {
    background: #272822;
    color: #f8f8f2;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
}
h3 {
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
}
</style>
