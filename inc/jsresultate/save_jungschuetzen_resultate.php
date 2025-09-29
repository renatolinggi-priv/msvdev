<?php
// save_jungschuetzen_resultate.php

include '../config.php'; // Passen Sie den Pfad an, falls nötig

// Hilfsfunktion zur Übergabe von Referenzen
function refValues($arr){
    // Für PHP 7.0 und höher
    if (strnatcmp(phpversion(),'5.3') >= 0) {
        $refs = [];
        foreach($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }
    return $arr;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resultate']) && is_array($_POST['resultate'])) {
        foreach ($_POST['resultate'] as $jungschuetzeID => $data) {
            // Dynamische Spaltenverwaltung
            $fields = [];
            $values = [];
            $types = '';
    
            foreach ($data as $column => $value) {
                $fields[] = "$column = ?";
                $values[] = $value;
                // Annahme: Alle Felder sind Strings. Passen Sie dies bei Bedarf an.
                $types .= 's';
            }
    
            if (!empty($fields)) {
                // Prüfen, ob ein Eintrag existiert
                $checkSql = "SELECT COUNT(*) as count FROM jungschuetzen_resultate WHERE JungschuetzeID = ?";
                $stmtCheck = $conn->prepare($checkSql);
                if (!$stmtCheck) {
                    echo "Fehler beim Vorbereiten der Abfrage: " . $conn->error;
                    continue;
                }
                $stmtCheck->bind_param("i", $jungschuetzeID);
                $stmtCheck->execute();
                $resultCheck = $stmtCheck->get_result();
                $count = $resultCheck->fetch_assoc()['count'];
                $stmtCheck->close();
    
                if ($count > 0) {
                    // Update
                    $updateSql = "UPDATE jungschuetzen_resultate SET " . implode(', ', $fields) . " WHERE JungschuetzeID = ?";
                    $stmtUpdate = $conn->prepare($updateSql);
                    if (!$stmtUpdate) {
                        echo "Fehler beim Vorbereiten des Update-Statements: " . $conn->error;
                        continue;
                    }
    
                    // Parameter für bind_param
                    $bind_params = array_merge([$types . 'i'], $values, [$jungschuetzeID]);
                    $bind_params = refValues($bind_params);
    
                    // bind_param mit call_user_func_array
                    call_user_func_array([$stmtUpdate, 'bind_param'], $bind_params);
    
                    if (!$stmtUpdate->execute()) {
                        echo "Fehler beim Ausführen des Update-Statements: " . $stmtUpdate->error;
                    }
                    $stmtUpdate->close();
                } else {
                    // Insert
                    $insertFields = array_keys($data);
                    $insertPlaceholders = implode(', ', array_fill(0, count($insertFields), '?'));
                    $insertSql = "INSERT INTO jungschuetzen_resultate (JungschuetzeID, " . implode(', ', $insertFields) . ") VALUES (?, $insertPlaceholders)";
                    $stmtInsert = $conn->prepare($insertSql);
                    if (!$stmtInsert) {
                        echo "Fehler beim Vorbereiten des Insert-Statements: " . $conn->error;
                        continue;
                    }
    
                    // Parameter für bind_param
                    $insert_types = 'i' . str_repeat('s', count($values)); // 'i' für JungschuetzeID und 's' für die restlichen Felder
                    $bind_params = array_merge([$insert_types], [$jungschuetzeID], $values);
                    $bind_params = refValues($bind_params);
    
                    // bind_param mit call_user_func_array
                    call_user_func_array([$stmtInsert, 'bind_param'], $bind_params);
    
                    if (!$stmtInsert->execute()) {
                        echo "Fehler beim Ausführen des Insert-Statements: " . $stmtInsert->error;
                    }
                    $stmtInsert->close();
                }
            }
        }
    
        echo "Daten erfolgreich gespeichert.";
    } else {
        echo "Keine Daten zum Speichern.";
    }
} else {
    echo "Ungültige Anfrage.";
}

$conn->close();
?>
