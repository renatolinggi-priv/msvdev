<?php
/**
 * save_partner_schuss.php
 * Speichert Partner-Schuss-Daten für Endresultate
 * 
 * @author System
 * @version 1.0
 * @description Speichert Partner-Daten mit Mitglied-Verknüpfung
 */

include '../config.php';

// Input validation
$mitgliedID = isset($_POST['mitgliedID']) ? (int)$_POST['mitgliedID'] : 0;
$jahr = isset($_POST['jahr']) ? (int)$_POST['jahr'] : date('Y');
$partnerName = isset($_POST['partnerName']) ? trim($_POST['partnerName']) : '';

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Datenbankverbindung fehlgeschlagen: ' . $conn->connect_error]));
}

// Check if table exists
$tableCheckSql = "SHOW TABLES LIKE 'endresultate_partner'";
$tableCheck = $conn->query($tableCheckSql);
if ($tableCheck->num_rows == 0) {
    http_response_code(500);
    die(json_encode(['error' => 'Tabelle endresultate_partner existiert nicht. Führen Sie zuerst database_setup.sql aus.']));
}

// Validate inputs
if ($mitgliedID <= 0) {
    http_response_code(400);
    die(json_encode(['error' => 'Ungültige Mitglied-ID']));
}

if (empty($partnerName)) {
    http_response_code(400);
    die(json_encode(['error' => 'Partner-Name ist erforderlich']));
}

// Validate year
$currentYear = date('Y');
if ($jahr < 2000 || $jahr > $currentYear + 1) {
    http_response_code(400);
    die(json_encode(['error' => 'Ungültiges Jahr']));
}

try {
    // Start transaction
    $conn->autocommit(false);

    // Check if partner data already exists for this member and year
    $checkSql = "SELECT ID FROM endresultate_partner WHERE MitgliedID = ? AND Jahr = ?";
    $checkStmt = $conn->prepare($checkSql);
    
    if (!$checkStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $checkStmt->bind_param("ii", $mitgliedID, $jahr);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    // Prepare shot data with validation für neue Struktur
    $endstichFields = ['EndstichSchuss1', 'EndstichSchuss2', 'EndstichSchuss3', 'EndstichSchuss4', 'EndstichSchuss5',
                       'EndstichSchuss6', 'EndstichSchuss7', 'EndstichSchuss8', 'EndstichSchuss9', 'EndstichSchuss10'];
    $sieErFields = ['SieErSchuss1', 'SieErSchuss2', 'SieErSchuss3', 'SieErSchuss4', 'SieErSchuss5'];
    $schwiniFields = ['PartnerSchwiniSchuss1', 'PartnerSchwiniSchuss2', 'PartnerSchwiniSchuss3',
                      'PartnerSchwiniSchuss4', 'PartnerSchwiniSchuss5', 'PartnerSchwiniSchuss6'];
    
    // Validate and sanitize shot values
    $shotData = [];
    $allFields = array_merge($endstichFields, $sieErFields, $schwiniFields);
    
    foreach ($allFields as $field) {
        $value = isset($_POST[$field]) ? floatval($_POST[$field]) : 0;
        // Validate shot values (0-10 for most shots)
        if ($value < 0 || $value > 10) {
            $value = 0;
        }
        $shotData[$field] = $value;
    }
    
    if ($checkResult->num_rows > 0) {
        // Update existing record
        $row = $checkResult->fetch_assoc();
        $partnerID = $row['ID'];
        
        $updateFields = [];
        $updateValues = [];
        $types = '';
        
        // Add partner name
        $updateFields[] = "PartnerName = ?";
        $updateValues[] = $partnerName;
        $types .= 's';
        
        // Add shot data
        foreach ($shotData as $field => $value) {
            $updateFields[] = "$field = ?";
            $updateValues[] = $value;
            $types .= 'd';
        }
        
        // Add AktualiertAm
        $updateFields[] = "AktualiertAm = CURRENT_TIMESTAMP";
        
        $updateSql = "UPDATE endresultate_partner SET " . implode(', ', $updateFields) . " WHERE ID = ?";
        $updateValues[] = $partnerID;
        $types .= 'i';
        
        $updateStmt = $conn->prepare($updateSql);
        if (!$updateStmt) {
            throw new Exception("Prepare update failed: " . $conn->error);
        }
        
        $updateStmt->bind_param($types, ...$updateValues);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Update failed: " . $updateStmt->error);
        }
        
        $updateStmt->close();
        
    } else {
        // Insert new record
        $insertFields = ['MitgliedID', 'Jahr', 'PartnerName'];
        $insertValues = [$mitgliedID, $jahr, $partnerName];
        $placeholders = ['?', '?', '?'];
        $types = 'iis';
        
        // Add shot data
        foreach ($shotData as $field => $value) {
            $insertFields[] = $field;
            $insertValues[] = $value;
            $placeholders[] = '?';
            $types .= 'd';
        }
        
        $insertSql = "INSERT INTO endresultate_partner (" . implode(', ', $insertFields) . ") 
                      VALUES (" . implode(', ', $placeholders) . ")";
        
        $insertStmt = $conn->prepare($insertSql);
        if (!$insertStmt) {
            throw new Exception("Prepare insert failed: " . $conn->error);
        }
        
        $insertStmt->bind_param($types, ...$insertValues);
        
        if (!$insertStmt->execute()) {
            throw new Exception("Insert failed: " . $insertStmt->error);
        }
        
        $insertStmt->close();
    }
    
    $checkStmt->close();
    
    // Commit transaction
    $conn->commit();
    $conn->autocommit(true);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Partner-Daten erfolgreich gespeichert'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $conn->autocommit(true);
    
    error_log("Error in save_partner_schuss.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Fehler beim Speichern: ' . $e->getMessage()
    ]);
    
} finally {
    $conn->close();
}
?>