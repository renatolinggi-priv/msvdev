<?php
// load_jmresultate_form.php
include '../config.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// JMDefinitionen laden
$definitionsSql = "SELECT * FROM JMDefinition WHERE hidden = 0 AND Info = 0 AND Erweitert = 0 AND year = $year
                ORDER BY
                CASE 
                    WHEN Bezeichnung = 'Obligatorisch' THEN 1
                    WHEN Bezeichnung = 'Feldschiessen' THEN 2
                    WHEN Bezeichnung LIKE '%Kantonalstich%' THEN 3
                    
                    WHEN Bezeichnung LIKE '%Sektionsmeisterschaft%' THEN 4
                    ELSE 5
                END,
                Reihenfolge";
$definitionsResult = $conn->query($definitionsSql);
$definitionen = [];
while ($definition = $definitionsResult->fetch_assoc()) {
    $definitionen[] = $definition;
}

// Mitglieder laden
$mitgliederSql = "SELECT * FROM mitglieder WHERE status = 1 ORDER BY Name ASC, Vorname ASC";
$mitgliederResult = $conn->query($mitgliederSql);

// Tabellenüberschrift erzeugen
echo '<thead><tr><th>Mitglied</th>';
foreach ($definitionen as $definition) {
    if (strpos($definition['Bezeichnung'], 'Sektionsmeisterschaft') !== false) {
        echo '<th class="vertical-header">Sektionsmeisterschaft Runde 1</th>';
        echo '<th class="vertical-header">Sektionsmeisterschaft Runde 2</th>';
    } else {
        echo '<th class="vertical-header">' . htmlspecialchars($definition['Bezeichnung']) . '</th>';
    }
}
echo '</tr></thead>';

// Tabelleninhalte erzeugen
echo '<tbody>';
while ($mitglied = $mitgliederResult->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($mitglied['Name'] . ' ' . $mitglied['Vorname']) . '</td>';
    
    foreach ($definitionen as $definition) {
        // Prüfen, ob es sich um einen Sektionsmeisterschaftseintrag handelt
        if (strpos($definition['Bezeichnung'], 'Sektionsmeisterschaft') !== false) {
            // Runde 1 abfragen:
            $sqlRunde1 = "SELECT Punkte FROM jmresultate 
                          WHERE mitgliederID = " . $mitglied['ID'] . " 
                            AND jmdefinitionID = " . $definition['ID'] . " 
                            AND Info = 'runde 1'";
            $resultRunde1 = $conn->query($sqlRunde1);
            $punkteRunde1 = ($resultRunde1 && $resultRunde1->num_rows > 0) 
                            ? $resultRunde1->fetch_assoc()['Punkte'] 
                            : '';
            // Runde 2 abfragen:
            $sqlRunde2 = "SELECT Punkte FROM jmresultate 
                          WHERE mitgliederID = " . $mitglied['ID'] . " 
                            AND jmdefinitionID = " . $definition['ID'] . " 
                            AND Info = 'runde 2'";
            $resultRunde2 = $conn->query($sqlRunde2);
            $punkteRunde2 = ($resultRunde2 && $resultRunde2->num_rows > 0) 
                            ? $resultRunde2->fetch_assoc()['Punkte'] 
                            : '';
            
            echo '<td>';
            echo '<input type="text" class="form-control small-input" name="punkte_runde1[' . $mitglied['ID'] . '][' . $definition['ID'] . ']" value="' . htmlspecialchars($punkteRunde1) . '"">';
            echo '</td>';
            echo '<td>';
            echo '<input type="text" class="form-control small-input" name="punkte_runde2[' . $mitglied['ID'] . '][' . $definition['ID'] . ']" value="' . htmlspecialchars($punkteRunde2) . '"">';
            echo '</td>';
        } else {
            // Für andere Definitionen:
            if ($definition['Bezeichnung'] == "Endstich") {
                $resultateSql = "SELECT COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS Punkte 
                                  FROM endstich e 
                                  WHERE Jahr = $year AND e.MitgliedID = " . $mitglied['ID'];
            } elseif ($definition['Bezeichnung'] == "Bester Kantonalstich") {
                $resultateSql = "SELECT GREATEST(Passe1, Passe2, Passe3, Passe4, Passe5) AS Punkte 
                                  FROM kantiresultate 
                                  WHERE Jahr = $year AND MitgliedID = " . $mitglied['ID'];
            } else {
                $resultateSql = "SELECT Punkte FROM jmresultate 
                                  WHERE mitgliederID = " . $mitglied['ID'] . " 
                                    AND jmdefinitionID = " . $definition['ID'];
            }
            $resultateResult = $conn->query($resultateSql);
            $punkte = ($resultateResult && $resultateResult->num_rows > 0) 
                      ? $resultateResult->fetch_assoc()['Punkte'] 
                      : '';
    
            echo '<td>';
            if ($definition['Bezeichnung'] == "Endstich" || $definition['Bezeichnung'] == "Bester Kantonalstich") {
                if ($punkte != 0) {
                    echo '<input type="text" class="form-control small-input" readonly name="punkte[' . $mitglied['ID'] . '][' . $definition['ID'] . ']" value="' . htmlspecialchars($punkte) . '">';
                } else {
                    echo '';
                }
            } else {
                echo '<input type="text" class="form-control small-input" name="punkte[' . $mitglied['ID'] . '][' . $definition['ID'] . ']" value="' . htmlspecialchars($punkte) . '">';
            }
            echo '</td>';
        }
    }
    
    echo '</tr>';
}
echo '</tbody>';
$conn->close();
?>
