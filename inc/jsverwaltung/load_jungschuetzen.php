<?php
include '../config.php';

$sql = "SELECT * FROM jungschuetzen ORDER BY Name ASC, Vorname ASC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $id = $row['id'];
        echo "<tr>";
        echo "<td><input type='text' class='form-control' name='ahvnummer[$id]' value='" . htmlspecialchars($row['AHVNummer'], ENT_QUOTES) . "'></td>";
        echo "<td><input type='text' class='form-control' name='name[$id]' value='" . htmlspecialchars($row['Name'], ENT_QUOTES) . "'></td>";
        echo "<td><input type='text' class='form-control' name='vorname[$id]' value='" . htmlspecialchars($row['Vorname'], ENT_QUOTES) . "'></td>";
        echo "<td><input type='date' class='form-control' name='geburtsdatum[$id]' value='" . $row['Geburtsdatum'] . "'></td>";
        echo "<td><input type='text' class='form-control' name='strasse[$id]' value='" . htmlspecialchars($row['Strasse'], ENT_QUOTES) . "'></td>";
        echo "<td><input type='text' class='form-control' name='plz[$id]' value='" . htmlspecialchars($row['PLZ'], ENT_QUOTES) . "'></td>";
        echo "<td><input type='text' class='form-control' name='ort[$id]' value='" . htmlspecialchars($row['Ort'], ENT_QUOTES) . "'></td>";
        echo "<td><select class='form-control' name='kursnummer[$id]'>";
        for ($i = 1; $i <=7; $i++) {
            $selected = $i == $row['KursNummer'] ? 'selected' : '';
            echo "<option value='$i' $selected>$i</option>";
        }
        echo "</select></td>";
        echo "<td><button type='button' class='btn btn-outline-danger deleteJungschuetze' data-id='$id'><i class='bi bi-trash me-2'></i></button></td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='9'>Keine Jungschützen gefunden</td></tr>";
}

$conn->close();
?>
