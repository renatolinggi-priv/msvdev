<?php
// load_jungschuetzen_resultate.php

include '../config.php'; // Passen Sie den Pfad an, falls nötig

// Ermitteln der Spalten aus der Datenbanktabelle 'jungschuetzen_resultate'
$columns = [];
$resultColumns = $conn->query("SHOW COLUMNS FROM jungschuetzen_resultate");

if ($resultColumns) {
    while ($column = $resultColumns->fetch_assoc()) {
        $field = $column['Field'];
        
        // Überspringen von unerwünschten Spalten (z.B. ID, Timestamps)
        $excludedColumns = ['ID', 'JungschuetzeID', 'created_at', 'updated_at', 'Anerkennungskarte1', 'Anerkennungskarte']; // Passen Sie diese Liste nach Bedarf an
        if (in_array($field, $excludedColumns)) {
            continue;
        }

        $columns[] = $field;
    }
} else {
    echo "<tr><td colspan='100%'>Fehler beim Abrufen der Spalteninformationen.</td></tr>";
    exit;
}

// Abrufen der Jungschützen
$sql = "SELECT * FROM jungschuetzen ORDER BY Name ASC, Vorname ASC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($jungschuetze = $result->fetch_assoc()) {
        $jungschuetzeID = $jungschuetze['id'];
        $name = htmlspecialchars($jungschuetze['Name'] . ' ' . $jungschuetze['Vorname']);

        // Vorhandene Resultate für diesen Jungschützen abrufen
        $sql2 = "SELECT * FROM jungschuetzen_resultate WHERE JungschuetzeID = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $jungschuetzeID);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        if ($result2->num_rows > 0) {
            // Existierende Daten laden
            $resultate = $result2->fetch_assoc();
        } else {
            // Keine Daten vorhanden, leeres Array verwenden
            $resultate = [];
        }

        $stmt2->close();

        // Zeile ausgeben
        echo "<tr>";
        echo "<td>$name</td>";

        foreach ($columns as $column) {
            $value = isset($resultate[$column]) ? htmlspecialchars($resultate[$column]) : '';
            echo "<td><input type='text' class='form-control input-breit' name='resultate[$jungschuetzeID][$column]' value='$value'></td>";
        }

        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='" . (count($columns) + 1) . "'>Keine Jungschützen gefunden.</td></tr>";
}
?>
