<?php
//load_jmdefinition_form.php
include '../config.php';


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
// Laden der JMDefinition-Daten
$sql = "SELECT * FROM JMDefinition WHERE year = $year and hidden = 0 ORDER BY Reihenfolge";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $streicherChecked = $row['Streicher'] ? 'checked' : '';
        $erweitertChecked = $row['Erweitert'] ? 'checked' : '';
        $infoChecked = $row['Info'] ? 'checked' : '';
        $gruppeChecked = $row['Gruppe'] ? 'checked' : '';
        echo "<tr id='row" . $row['ID'] . "'>";
        echo "<td class='fixed-with'><span class='drag-indicator'>⠿</span>" . $row['Reihenfolge'] . "</td>";
               echo "<td>
        <textarea 
            class='form-control mb-1 textarea-large' 
            name='bezeichnung[" . $row['ID'] . "]'
            rows='5'
        >" . htmlspecialchars($row['Bezeichnung']) . "</textarea>
    </td>";
       // echo "<td><input type='text' class='form-control input-full-width' name='bezeichnung[" . $row['ID'] . "]' value='" . $row['Bezeichnung'] . "'></td>";
        echo "<td>
        <textarea 
            class='form-control mb-1 textarea-large' 
            name='adresse[" . $row['ID'] . "]' 
            rows='5'
        >" . htmlspecialchars($row['Adresse']) . "</textarea>
    </td>";

        echo "<td>
    <textarea 
        class='form-control mb-1 textarea-large' 
        name='schiesstage[" . $row['ID'] . "]' 
        rows='5'
    >" . htmlspecialchars($row['Schiesstage']) . "</textarea>
</td>";

        echo "<td><input type='text' class='form-control small-input' name='maxpunkte[" . $row['ID'] . "]' value='" . $row['Maxpunkte'] . "'></td>";
        echo "<td><input type='number' class='form-control small-input' name='zuschlag[" . $row['ID'] . "]' value='" . ($row['Zuschlag'] ?? 0) . "' min='0' max='99'></td>";
        echo "<td class='text-center'><input type='checkbox' class='form-check-input' name='streicher[" . $row['ID'] . "]' value='1' $streicherChecked></td>";
        echo "<td class='text-center'><input type='checkbox' class='form-check-input' name='erweitert[" . $row['ID'] . "]' value='1' $erweitertChecked></td>";
        echo "<td class='text-center'><input type='checkbox' class='form-check-input' name='info[" . $row['ID'] . "]' value='1' $infoChecked></td>";
        echo "<td class='text-center'><input type='checkbox' class='form-check-input' name='gruppe[" . $row['ID'] . "]' value='1' $gruppeChecked></td>";
        echo "<td>
        <a href=\"jmdefinition/export_single_ics.php?id=" .$row['ID'] ."\" class=\"btn btn-sm btn-outline-secondary\">📅</a> 
        <br><br>
        <button class=\"btn btn-outline-danger btn-sm deleteJMDefinition\" data-id=" .$row['ID'] .">
    🗑️
</button>

       </td>";
        echo "</tr>";
    }
    //<button type='button' class='btn btn-outline-danger deleteJMDefinition' data-id='" . $row['ID'] . "'>Löschen</button>
} else {
    echo "<tr><td colspan='11'>Keine Einträge gefunden</td></tr>";
}

$conn->close();
?>
