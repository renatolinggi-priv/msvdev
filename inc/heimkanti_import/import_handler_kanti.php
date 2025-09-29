<?php
// import_handler_kanti.php - Erweiterte Funktionen für Kanti-Import

/**
 * Import-Handler für beide Stiche (Heim und Kanti)
 */
function handleImportResults($conn, $mitgliedId, $jahr, $selectedPrograms, $stichType = 'Heimmeisterschaft') {
    error_log("=== START IMPORT ===");
    error_log("MitgliedID: $mitgliedId, Jahr: $jahr, StichType: $stichType");
    error_log("Selected Programs: " . json_encode($selectedPrograms));
    
    // Stichdefinition dynamisch laden
    $def = getStichDefinition($conn, $stichType);
    $allowedNumbers = $def['numbers'];
    $targetTable    = $def['restable'];
    $maxPasses      = $def['max_passes'] ?? 8;
    
    error_log("Definition loaded - Table: $targetTable, MaxPasses: $maxPasses");
    error_log("Allowed Numbers: " . json_encode($allowedNumbers));
    
    // Für heimkanti_import: Stichdefinition ist immer vorhanden
    if (empty($targetTable)) {
        throw new Exception('Keine Stichdefinition für ' . $stichType . ' gefunden.');
    }
    
    // Datensatz laden/erstellen je nach Tabelle
    if ($targetTable === 'heimresultate') {
        $checkSql = "SELECT ID, Passe1, Passe2, Passe3, Passe4, Passe5, Passe6, Passe7, Passe8
                     FROM heimresultate
                     WHERE MitgliedID = ? AND Jahr = ?";
    } else if ($targetTable === 'kantiresultate') {
        $checkSql = "SELECT ID, Passe1, Passe2, Passe3, Passe4, Passe5
                     FROM kantiresultate
                     WHERE MitgliedID = ? AND Jahr = ?";
    } else {
        throw new Exception('Unbekannte Zieltabelle: ' . $targetTable);
    }
    
    $stmt = $conn->prepare($checkSql);
    if (!$stmt) throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);
    
    $stmt->bind_param("ii", $mitgliedId, $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Default-Passen mit NULL initialisieren
    $passes = [];
    for ($i = 1; $i <= $maxPasses; $i++) {
        $passes['Passe' . $i] = null;
    }
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $recordId = (int)$row['ID'];
        error_log("Existing record found - ID: $recordId");
        
        // Bestehende Werte beibehalten (nicht überschreiben wenn nicht im Import)
        foreach ($passes as $key => $_) {
            if (isset($row[$key])) {
                $passes[$key] = $row[$key];
                if ($row[$key] !== null) {
                    error_log("Existing value for $key: " . $row[$key]);
                }
            }
        }
    } else {
        $stmt->close();
        error_log("No existing record - creating new");
        
        $insertSql = "INSERT INTO $targetTable (MitgliedID, Jahr) VALUES (?, ?)";
        $stmt = $conn->prepare($insertSql);
        if (!$stmt) throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);
        
        $stmt->bind_param("ii", $mitgliedId, $jahr);
        if (!$stmt->execute()) throw new Exception('Insert fehlgeschlagen: ' . $stmt->error);
        $recordId = (int)$conn->insert_id;
        error_log("New record created - ID: $recordId");
    }
    $stmt->close();
    
    // Programme zuordnen - WICHTIG: Nur die überschreiben, die im Import sind
    if (!is_array($selectedPrograms)) $selectedPrograms = [];
    
    $assignedPasses = [];
    foreach ($selectedPrograms as $program) {
        $programNumber = (string)($program['number'] ?? '');
        $total         = isset($program['total']) ? intval($program['total']) : null;
        $index         = isset($program['index']) ? intval($program['index']) : 0;
        
        error_log("Processing program: Number=$programNumber, Total=$total, Index=$index");
        
        // DEBUG: Log welche Programme ankommen
        error_log("Processing program $programNumber - allowed: " . json_encode($allowedNumbers));
        
        // Für heimkanti_import: Erlaube alle Programmnummern da sie aus CSV stammen
        // Die Validierung erfolgt bereits im Frontend
        if (empty($programNumber)) {
            error_log("Skip program: empty program number");
            continue;
        }
        
        $passeNr = $index;
        $passeField = 'Passe' . $passeNr;
        
        if ($passeNr >= 1 && $passeNr <= $maxPasses) {
            // WICHTIG: Setze den Wert direkt, nicht als NULL
            $passes[$passeField] = $total;
            $assignedPasses[] = "$passeField = $total";
            error_log("ASSIGNED: $passeField = $total (program $programNumber)");
        } else {
            error_log("ERROR: Invalid Passe number $passeNr");
        }
    }
    
    error_log("Final pass assignments: " . json_encode($passes));
    
    // Update schreiben
    if ($targetTable === 'heimresultate') {
        // Baue dynamisches UPDATE Statement
        $updateFields = [];
        $types = '';
        $values = [];
        
        for ($i = 1; $i <= 8; $i++) {
            $field = 'Passe' . $i;
            $updateFields[] = "$field = ?";
            
            // FIX: Alle Werte als Integer behandeln, auch NULL
            $types .= 'i';  // Immer Integer
            if ($passes[$field] === null) {
                $values[] = null;  // NULL-Wert direkt übergeben
            } else {
                $values[] = intval($passes[$field]);
            }
        }
        
        $updateSql = "UPDATE heimresultate SET " . implode(', ', $updateFields) . " WHERE ID = ?";
        $types .= 'i';  // für ID
        $values[] = $recordId;
        
        error_log("UPDATE SQL: $updateSql");
        error_log("Types: $types");
        error_log("Values: " . json_encode($values));
        
        $stmt = $conn->prepare($updateSql);
        if (!$stmt) throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);
        
        // Dynamisches bind_param mit Referenzen
        $bindParams = [];
        $bindParams[] = &$types;
        for ($i = 0; $i < count($values); $i++) {
            $bindParams[] = &$values[$i];
        }
        
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
        
    } else if ($targetTable === 'kantiresultate') {
        // Baue dynamisches UPDATE Statement für Kanti
        $updateFields = [];
        $types = '';
        $values = [];
        
        for ($i = 1; $i <= 5; $i++) {
            $field = 'Passe' . $i;
            $updateFields[] = "$field = ?";
            
            // FIX: Alle Werte als Integer behandeln, auch NULL
            $types .= 'i';  // Immer Integer
            if ($passes[$field] === null) {
                $values[] = null;  // NULL-Wert direkt übergeben
            } else {
                $values[] = intval($passes[$field]);
            }
        }
        
        $updateSql = "UPDATE kantiresultate SET " . implode(', ', $updateFields) . " WHERE ID = ?";
        $types .= 'i';  // für ID
        $values[] = $recordId;
        
        error_log("UPDATE SQL: $updateSql");
        error_log("Types: $types");
        error_log("Values: " . json_encode($values));
        
        $stmt = $conn->prepare($updateSql);
        if (!$stmt) throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);
        
        // Dynamisches bind_param mit Referenzen
        $bindParams = [];
        $bindParams[] = &$types;
        for ($i = 0; $i < count($values); $i++) {
            $bindParams[] = &$values[$i];
        }
        
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
    }
    
    if (!$stmt->execute()) {
        error_log("UPDATE FAILED: " . $stmt->error);
        throw new Exception('Fehler beim Speichern: ' . $stmt->error);
    }
    
    $affectedRows = $stmt->affected_rows;
    error_log("UPDATE successful - Affected rows: $affectedRows");
    
    $stmt->close();
    
    // Verifikation - Daten nochmal laden und prüfen
    if ($targetTable === 'heimresultate') {
        $verifySql = "SELECT Passe1, Passe2, Passe3, Passe4, Passe5, Passe6, Passe7, Passe8
                      FROM heimresultate WHERE ID = ?";
    } else {
        $verifySql = "SELECT Passe1, Passe2, Passe3, Passe4, Passe5
                      FROM kantiresultate WHERE ID = ?";
    }
    
    $verifyStmt = $conn->prepare($verifySql);
    $verifyStmt->bind_param("i", $recordId);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    $savedData = $verifyResult->fetch_assoc();
    
    error_log("Verification - Saved data: " . json_encode($savedData));
    $verifyStmt->close();
    
    error_log("=== END IMPORT ===");
    
    return [
        'success'         => true,
        'message'         => 'Daten erfolgreich importiert',
        'record_id'       => $recordId,
        'passes'          => $passes,
        'saved_data'      => $savedData,
        'assigned_passes' => $assignedPasses,
        'allowed_numbers' => $allowedNumbers,
        'restable'        => $targetTable,
        'stich_type'      => $stichType,
        'max_passes'      => $maxPasses
    ];
}

/**
 * Prüft existierende Daten für beide Stiche
 */
function checkExistingData($conn, $mitgliedId, $jahr, $stichType = 'Heimmeisterschaft') {
    error_log("Checking existing data - MitgliedID: $mitgliedId, Jahr: $jahr, StichType: $stichType");
    
    $def = getStichDefinition($conn, $stichType);
    $targetTable = $def['restable'];
    $maxPasses = $def['max_passes'] ?? 8;
    
    if ($targetTable === 'heimresultate') {
        $sql = "SELECT Passe1, Passe2, Passe3, Passe4, Passe5, Passe6, Passe7, Passe8
                FROM heimresultate
                WHERE MitgliedID = ? AND Jahr = ?";
    } else if ($targetTable === 'kantiresultate') {
        $sql = "SELECT Passe1, Passe2, Passe3, Passe4, Passe5
                FROM kantiresultate
                WHERE MitgliedID = ? AND Jahr = ?";
    } else {
        throw new Exception('Unbekannte Zieltabelle: ' . $targetTable);
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);
    
    $stmt->bind_param("ii", $mitgliedId, $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $existingPasses = [];
        
        for ($i = 1; $i <= $maxPasses; $i++) {
            $passeField = 'Passe' . $i;
            $value = $row[$passeField] ?? null;
            
            // Umfassende Prüfung auf "leere" Werte
            $isEmpty = (
                $value === null ||
                $value === '' ||
                $value === '0' ||
                $value === 0 ||
                $value === 'NULL' ||
                $value === 'null' ||
                trim($value) === ''
            );
            
            if (!$isEmpty) {
                $existingPasses[] = $i;
                error_log("Found existing data in $passeField: " . $value);
            } else {
                error_log("Ignoring empty/null value in $passeField: " . var_export($value, true));
            }
        }
        
        return [
            'success'         => true,
            'exists'          => true,
            'existing_passes' => $existingPasses,
            'data'            => $row,
            'stich_type'      => $stichType,
            'max_passes'      => $maxPasses
        ];
    } else {
        error_log("No existing data found");
        return [
            'success' => true,
            'exists' => false,
            'stich_type' => $stichType,
            'max_passes' => $maxPasses
        ];
    }
}

/**
 * API Action für check_existing - prüft bestehende Daten
 */
function getStichDefinition($conn, $stichType) {
    // Für heimkanti_import: Standard-Programmnummern setzen, aber Validierung deaktiviert
    if ($stichType === 'Heimmeisterschaft') {
        return [
            'numbers' => ['133', '134'], // Standard-Programme für Heim
            'restable' => 'heimresultate',
            'max_passes' => 8
        ];
    } else if ($stichType === 'Kantonalstich') {
        return [
            'numbers' => ['133', '134'], // Standard-Programme für Kanti
            'restable' => 'kantiresultate',
            'max_passes' => 5
        ];
    }
    
    throw new Exception('Unbekannter Stich-Typ: ' . $stichType);
}