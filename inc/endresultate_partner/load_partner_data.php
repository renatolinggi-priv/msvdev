<?php
/**
 * load_partner_data.php
 * Lädt spezifische Partner-Daten für Bearbeitung
 * 
 * @author System
 * @version 1.1
 * @description Lädt Partner-Daten anhand der ID für das Bearbeitungsformular (inkl. 2. Passe Schwini)
 */

header('Content-Type: application/json');

// Include database configuration
include '../config.php';

// Check database connection with proper error handling
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(['message' => 'Datenbankverbindung fehlgeschlagen: ' . $conn->connect_error]);
    exit;
}

// Check if table exists
$tableCheckSql = "SHOW TABLES LIKE 'endresultate_partner'";
$tableCheck = $conn->query($tableCheckSql);
if ($tableCheck->num_rows == 0) {
    http_response_code(500);
    echo json_encode(['message' => 'Tabelle endresultate_partner existiert nicht. Führen Sie zuerst database_setup.sql aus.']);
    exit;
}

// Input validation
$partnerID = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($partnerID <= 0) {
    http_response_code(400);
    echo json_encode(['message' => 'Ungültige Partner-ID']);
    exit;
}

try {
    // Prepare SQL statement to get partner data
    $sql = "
    SELECT
        ep.*,
        m.Name,
        m.Vorname
    FROM
        endresultate_partner ep
    LEFT JOIN mitglieder m ON ep.MitgliedID = m.ID
    WHERE
        ep.ID = ?
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $partnerID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $partnerData = $result->fetch_assoc();
        
        // Return success response with partner data
        echo json_encode([
            'success' => true,
            'partner' => $partnerData
        ]);
    } else {
        echo json_encode([
            'message' => 'Partner-Daten nicht gefunden'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error in load_partner_data.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'message' => 'Fehler beim Laden der Partner-Daten: ' . $e->getMessage()
    ]);
    
} finally {
    $conn->close();
}
?>
