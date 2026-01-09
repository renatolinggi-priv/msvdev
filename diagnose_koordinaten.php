<?php
/**
 * Diagnose-Script für Koordinaten in termine.php
 */

include 'inc/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Koordinaten-Diagnose für termine.php</h1>";

/**
 * Prüft ob ein String Koordinaten enthält
 */
function parseCoordinates($text) {
    $text = trim($text);
    // Format: "47.2034, 8.7812" oder "47.2034,8.7812" oder "47.2034; 8.7812" oder "47.3166/8.8206"
    // Akzeptiert: Komma, Semikolon, Schrägstrich als Trennzeichen
    if (preg_match('/^(-?\d+\.\d+)\s*[,;\/]\s*(-?\d+\.\d+)$/', $text, $matches)) {
        $lat = floatval($matches[1]);
        $lon = floatval($matches[2]);
        // Plausibilitätsprüfung
        if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
            return ['lat' => $lat, 'lon' => $lon];
        }
    }
    return false;
}

// Test mit verschiedenen Formaten
echo "<h2>Test parseCoordinates Funktion:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Input</th><th>Ergebnis</th><th>Status</th></tr>";

$testCases = [
    '47.2034, 8.7812',
    '47.2034,8.7812',
    '47.2034; 8.7812',
    '47.2034 ; 8.7812',
    '47.3166/8.8206',
    '47.3166 / 8.8206',
    '47.20340, 8.78120',
    'Eichenstrasse 18, 8808 Pfäffikon',
    '',
    '47.2034',
    '47.2034, abc',
];

foreach ($testCases as $test) {
    $result = parseCoordinates($test);
    $status = $result ? '✅ Koordinaten' : '❌ Keine Koordinaten';
    if ($result) {
        $lat = number_format($result['lat'], 6, '.', '');
        $lon = number_format($result['lon'], 6, '.', '');
        $resultStr = "GEO:{$lat};{$lon}";
    } else {
        $resultStr = 'false';
    }
    echo "<tr><td><code>" . htmlspecialchars($test) . "</code></td><td>{$resultStr}</td><td>{$status}</td></tr>";
}
echo "</table>";

// Adressen aus JMDefinition laden
echo "<h2>Adressen aus JMDefinition:</h2>";
$currentYear = date("Y");
$nextYear = $currentYear + 1;

$sql = "SELECT ID, Bezeichnung, Adresse, year FROM JMDefinition WHERE year IN (?, ?) AND Adresse IS NOT NULL AND Adresse != '' ORDER BY year, Reihenfolge";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $currentYear, $nextYear);
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Jahr</th><th>Bezeichnung</th><th>Adresse (roh)</th><th>Adresse (hex)</th><th>Koordinaten?</th><th>ICS Output</th></tr>";

while ($row = $result->fetch_assoc()) {
    $adresse = $row['Adresse'];
    $coords = parseCoordinates($adresse);
    $status = $coords ? '✅' : '❌';
    
    // Hex-Darstellung für versteckte Zeichen
    $hexStr = '';
    for ($i = 0; $i < strlen($adresse); $i++) {
        $hexStr .= sprintf('%02X ', ord($adresse[$i]));
    }
    
    // Was würde im ICS stehen?
    if ($coords) {
        $lat = number_format($coords['lat'], 6, '.', '');
        $lon = number_format($coords['lon'], 6, '.', '');
        $icsOutput = "GEO:{$lat};{$lon}";
    } else {
        $icsOutput = "LOCATION:" . $adresse;
    }
    
    echo "<tr>";
    echo "<td>{$row['ID']}</td>";
    echo "<td>{$row['year']}</td>";
    echo "<td>" . htmlspecialchars($row['Bezeichnung']) . "</td>";
    echo "<td><code>" . htmlspecialchars($adresse) . "</code></td>";
    echo "<td style='font-size:10px;'>{$hexStr}</td>";
    echo "<td>{$status}</td>";
    echo "<td><code>" . htmlspecialchars($icsOutput) . "</code></td>";
    echo "</tr>";
}
echo "</table>";

$stmt->close();
$conn->close();

echo "<h2>Hinweise:</h2>";
echo "<ul>";
echo "<li>Koordinaten-Format sollte sein: <code>47.2034, 8.7812</code></li>";
echo "<li>Keine zusätzlichen Zeichen oder Leerzeichen am Anfang/Ende</li>";
echo "<li>Hex-Spalte zeigt versteckte Zeichen (z.B. non-breaking spaces)</li>";
echo "</ul>";
?>
