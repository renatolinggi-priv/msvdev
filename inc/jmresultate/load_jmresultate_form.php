<?php
// load_jmresultate_form.php
include '../config.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Rendert ein Resultat-Eingabefeld mit Status-Indikator bei 'entwurf'.
// Wenn $status === 'entwurf' (Mitglied-Eingabe), wird ein gelber Punkt als Hinweis angezeigt.
// Freigabe erfolgt implizit beim Speichern der Erfassung (save_jmresultate.php).
function renderJrInput($mitgliedId, $defId, $name, $value, $jrId, $status) {
    $isDraft  = ($status === 'entwurf');
    $cls      = 'form-control small-input';
    if ($isDraft) $cls .= ' jr-draft';
    $valEsc   = htmlspecialchars($value);
    $jrIdAttr = $jrId ? (' data-jrid="' . (int)$jrId . '"') : '';

    $html  = '<div class="jr-cell-wrap">';
    $html .= '<input type="text" class="' . $cls . '"'
           . ' name="' . $name . '[' . (int)$mitgliedId . '][' . (int)$defId . ']"'
           . ' value="' . $valEsc . '"'
           . $jrIdAttr
           . ' data-status="' . htmlspecialchars($status ?? '') . '">';
    if ($isDraft && $jrId) {
        $html .= '<span class="jr-status-dot" data-tooltip="Eingabe vom Mitglied — wird beim Speichern bestätigt"></span>';
    }
    $html .= '</div>';
    return $html;
}

// JMDefinitionen laden
$definitionsSql = "SELECT * FROM JMDefinition WHERE hidden = 0 AND Info = 0 AND Erweitert = 0 AND year = ?
                ORDER BY
                CASE
                    WHEN Bezeichnung = 'Obligatorisch' THEN 1
                    WHEN Bezeichnung = 'Feldschiessen' THEN 2
                    WHEN Bezeichnung LIKE '%Kantonalstich%' THEN 3

                    WHEN Bezeichnung LIKE '%Sektionsmeisterschaft%' THEN 4
                    ELSE 5
                END,
                Reihenfolge";
$stmt = $conn->prepare($definitionsSql);
$stmt->bind_param("i", $year);
$stmt->execute();
$definitionsResult = $stmt->get_result();
$stmt->close();
$definitionen = [];
while ($definition = $definitionsResult->fetch_assoc()) {
    $definitionen[] = $definition;
}

// Mitglieder laden
$mitgliederSql = "SELECT * FROM mitglieder WHERE status = 1 AND Verstorben = 0 ORDER BY Name ASC, Vorname ASC";
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
            $sqlRunde1 = "SELECT ID, Punkte, status FROM jmresultate
                          WHERE mitgliederID = ?
                            AND jmdefinitionID = ?
                            AND Info = ?";
            $stmt = $conn->prepare($sqlRunde1);
            $infoRunde1 = 'runde 1';
            $stmt->bind_param("iis", $mitglied['ID'], $definition['ID'], $infoRunde1);
            $stmt->execute();
            $resultRunde1 = $stmt->get_result();
            $stmt->close();
            $rowR1 = ($resultRunde1 && $resultRunde1->num_rows > 0) ? $resultRunde1->fetch_assoc() : null;
            $punkteRunde1 = $rowR1 ? $rowR1['Punkte'] : '';
            $statusR1 = $rowR1['status'] ?? null;
            $idR1 = $rowR1['ID'] ?? null;
            // Runde 2 abfragen:
            $sqlRunde2 = "SELECT ID, Punkte, status FROM jmresultate
                          WHERE mitgliederID = ?
                            AND jmdefinitionID = ?
                            AND Info = ?";
            $stmt = $conn->prepare($sqlRunde2);
            $infoRunde2 = 'runde 2';
            $stmt->bind_param("iis", $mitglied['ID'], $definition['ID'], $infoRunde2);
            $stmt->execute();
            $resultRunde2 = $stmt->get_result();
            $stmt->close();
            $rowR2 = ($resultRunde2 && $resultRunde2->num_rows > 0) ? $resultRunde2->fetch_assoc() : null;
            $punkteRunde2 = $rowR2 ? $rowR2['Punkte'] : '';
            $statusR2 = $rowR2['status'] ?? null;
            $idR2 = $rowR2['ID'] ?? null;

            echo '<td>' . renderJrInput($mitglied['ID'], $definition['ID'], 'punkte_runde1', $punkteRunde1, $idR1, $statusR1) . '</td>';
            echo '<td>' . renderJrInput($mitglied['ID'], $definition['ID'], 'punkte_runde2', $punkteRunde2, $idR2, $statusR2) . '</td>';
        } else {
            // Für andere Definitionen:
            if ($definition['Bezeichnung'] == "Endstich") {
                $resultateSql = "SELECT COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS Punkte
                                  FROM endstich e
                                  WHERE Jahr = ? AND e.MitgliedID = ?";
                $stmt = $conn->prepare($resultateSql);
                $stmt->bind_param("ii", $year, $mitglied['ID']);
            } elseif ($definition['Bezeichnung'] == "Bester Kantonalstich") {
                $resultateSql = "SELECT GREATEST(Passe1, Passe2, Passe3, Passe4, Passe5) AS Punkte
                                  FROM kantiresultate
                                  WHERE Jahr = ? AND MitgliedID = ?";
                $stmt = $conn->prepare($resultateSql);
                $stmt->bind_param("ii", $year, $mitglied['ID']);
            } else {
                $resultateSql = "SELECT ID, Punkte, status FROM jmresultate
                                  WHERE mitgliederID = ?
                                    AND jmdefinitionID = ?
                                    AND (Info = '' OR Info IS NULL)";
                $stmt = $conn->prepare($resultateSql);
                $stmt->bind_param("ii", $mitglied['ID'], $definition['ID']);
            }
            $stmt->execute();
            $resultateResult = $stmt->get_result();
            $stmt->close();
            $row = ($resultateResult && $resultateResult->num_rows > 0)
                      ? $resultateResult->fetch_assoc()
                      : null;
            $punkte = $row ? $row['Punkte'] : '';
            $jrStatus = $row['status'] ?? null;
            $jrId     = $row['ID'] ?? null;

            echo '<td>';
            if ($definition['Bezeichnung'] == "Endstich" || $definition['Bezeichnung'] == "Bester Kantonalstich") {
                if ($punkte != 0) {
                    echo '<input type="text" class="form-control small-input" readonly name="punkte[' . $mitglied['ID'] . '][' . $definition['ID'] . ']" value="' . htmlspecialchars($punkte) . '">';
                } else {
                    echo '';
                }
            } else {
                echo renderJrInput($mitglied['ID'], $definition['ID'], 'punkte', $punkte, $jrId, $jrStatus);
            }
            echo '</td>';
        }
    }

    echo '</tr>';
}
echo '</tbody>';
$conn->close();
?>
