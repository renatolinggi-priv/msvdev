<?php
// debug_sieer.php - Debug für SieEr Berechnung
include '../config.php';

echo "<h2>Debug SieEr Berechnung</h2>";

$sql = "
    SELECT
        m.Name, m.Vorname, ep.PartnerName,
        ep.SieErSchuss1, ep.SieErSchuss2, ep.SieErSchuss3, ep.SieErSchuss4, ep.SieErSchuss5,
        ep.SieErSchuss6, ep.SieErSchuss7, ep.SieErSchuss8, ep.SieErSchuss9, ep.SieErSchuss10
    FROM mitglieder m
    INNER JOIN endresultate_partner ep ON m.ID = ep.MitgliedID
    WHERE ep.Jahr = 2025
";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<h3>{$row['Name']} {$row['Vorname']} - Partner: {$row['PartnerName']}</h3>";
        
        $shots = [
            $row['SieErSchuss1'], $row['SieErSchuss2'], $row['SieErSchuss3'], 
            $row['SieErSchuss4'], $row['SieErSchuss5'], $row['SieErSchuss6'],
            $row['SieErSchuss7'], $row['SieErSchuss8'], $row['SieErSchuss9'], 
            $row['SieErSchuss10']
        ];
        
        echo "<p><strong>Alle Schüsse:</strong> [" . implode(', ', array_map(function($v) { return $v ?? 'NULL'; }, $shots)) . "]</p>";
        
        // Normale Summe
        $normalSum = 0;
        foreach ($shots as $shot) {
            if ($shot !== null && $shot > 0) {
                $normalSum += $shot;
            }
        }
        echo "<p><strong>Normale Summe:</strong> $normalSum</p>";
        
        // Einzigartige Werte
        $uniqueValues = [];
        foreach ($shots as $shot) {
            if ($shot !== null && $shot > 0) {
                $uniqueValues[$shot] = $shot;
            }
        }
        
        echo "<p><strong>Einzigartige Werte:</strong> [" . implode(', ', array_keys($uniqueValues)) . "]</p>";
        echo "<p><strong>Spezielle Summe (einzigartig):</strong> " . array_sum($uniqueValues) . "</p>";
        
        echo "<hr>";
    }
} else {
    echo "<p>Keine Daten gefunden.</p>";
}
?>