<?php
// load_mitglieder_form.php - ERWEITERTE VERSION
include 'config.php';

// Laden der Mitgliederinformationen mit INNER JOIN
$sql = "SELECT m.id, m.vorname, m.name, m.waffenid, m.status, w.bezeichnung, m.Geburtsdatum, m.Ehrenmitglied,
        m.Strasse, m.PLZ, m.Ort, m.Email, m.Telefon, m.Mobile, m.Notizen, m.Verstorben
        FROM mitglieder m
        INNER JOIN Waffen w ON m.waffenid = w.id
        ORDER BY m.name ASC, m.vorname ASC";
$result = $conn->query($sql);

// Laden der Waffentypen für das Dropdown-Menü
$waffenSql = "SELECT id, bezeichnung FROM Waffen";
$waffenResult = $conn->query($waffenSql);

$waffenOptions = [];
if ($waffenResult->num_rows > 0) {
    while($waffe = $waffenResult->fetch_assoc()) {
        $waffenOptions[] = $waffe;
    }
}

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Debug: Werte prüfen und korrekt setzen
        $statusChecked = ($row['status'] == 1) ? 'checked' : '';
        $ehrenmitgliedChecked = ($row['Ehrenmitglied'] == 1) ? 'checked' : '';
        $verstorbenChecked = ($row['Verstorben'] == 1) ? 'checked' : '';
        $rowClass = ($row['Verstorben'] == 1) ? ' class="table-secondary text-muted"' : '';

        echo "<tr$rowClass>";
        echo "<td><input type='text' class='form-control middle-input' name='id[" . $row['id'] . "]' value='" . $row['id'] . "'></td>";
        echo "<td><input type='text' class='form-control' name='name[" . $row['id'] . "]' value='" . $row['name'] . "'></td>";
        echo "<td><input type='text' class='form-control' name='vorname[" . $row['id'] . "]' value='" . $row['vorname'] . "'></td>";
        echo "<td><input type='date' class='form-control' name='geburtsdatum[" . $row['id'] . "]' value='" . $row['Geburtsdatum'] . "'></td>";
        
        // Waffen-Dropdown
        echo "<td><select class='form-control' name='waffenid[" . $row['id'] . "]'>";
        foreach ($waffenOptions as $waffe) {
            $selected = $waffe['id'] == $row['waffenid'] ? 'selected' : '';
            echo "<option value='" . $waffe['id'] . "' $selected>" . $waffe['bezeichnung'] . "</option>";
        }
        echo "</select></td>";
        
        // NEUE FELDER
        echo "<td><input type='text' class='form-control' name='strasse[" . $row['id'] . "]' value='" . ($row['Strasse'] ?? '') . "' placeholder='Strasse'></td>";
        echo "<td>
                <input type='text' class='form-control mb-1' name='plz[" . $row['id'] . "]' value='" . ($row['PLZ'] ?? '') . "' placeholder='PLZ' style='width: 60px; display: inline-block;'>
                <input type='text' class='form-control' name='ort[" . $row['id'] . "]' value='" . ($row['Ort'] ?? '') . "' placeholder='Ort' style='width: calc(100% - 65px); display: inline-block;'>
              </td>";
        echo "<td><input type='email' class='form-control' name='email[" . $row['id'] . "]' value='" . ($row['Email'] ?? '') . "'></td>";
        echo "<td><input type='text' class='form-control' name='telefon[" . $row['id'] . "]' value='" . ($row['Telefon'] ?? '') . "'></td>";
        
        // Status, Ehrenmitglied und Verstorben
        echo "<td class='text-center'><input type='checkbox' class='form-check-input' name='status[" . $row['id'] . "]' value='1' $statusChecked></td>";
        echo "<td class='text-center'><input type='checkbox' class='form-check-input' name='ehrenmitglied[" . $row['id'] . "]' value='1' $ehrenmitgliedChecked></td>";
        echo "<td class='text-center'><input type='checkbox' class='form-check-input' name='verstorben[" . $row['id'] . "]' value='1' $verstorbenChecked></td>";
        
        // Löschen-Button
        echo "<td>
                <button type='button' class='btn btn-outline-danger deleteMitglied' data-id='" . $row['id'] . "'>
                    <i class='bi bi-trash me-1'></i> 
                </button>
              </td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='13'>Keine Mitglieder gefunden</td></tr>";
}

$conn->close();
?>