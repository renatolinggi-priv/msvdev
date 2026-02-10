<?php
/**
 * delete_partner.php
 * Löscht Partner-Daten
 * 
 * @author System
 * @version 1.0
 * @description Löscht Partner-Daten anhand der ID
 */

header('Content-Type: application/json');

// Include database configuration
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

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
$partnerID = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($partnerID <= 0) {
    http_response_code(400);
    echo json_encode(['message' => 'Ungültige Partner-ID']);
    exit;
}

try {
    // Start transaction
    $conn->autocommit(false);
    
    // Check if partner exists
    $checkSql = "SELECT ID, PartnerName FROM endresultate_partner WHERE ID = ?";
    $checkStmt = $conn->prepare($checkSql);
    
    if (!$checkStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $partnerID);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['message' => 'Partner-Daten nicht gefunden']);
        $checkStmt->close();
        $conn->close();
        exit;
    }
    
    $partnerData = $checkResult->fetch_assoc();
    $partnerName = $partnerData['PartnerName'];
    $checkStmt->close();
    
    // Delete partner data
    $deleteSql = "DELETE FROM endresultate_partner WHERE ID = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    
    if (!$deleteStmt) {
        throw new Exception("Prepare delete failed: " . $conn->error);
    }
    
    $deleteStmt->bind_param("i", $partnerID);
    
    if (!$deleteStmt->execute()) {
        throw new Exception("Delete failed: " . $deleteStmt->error);
    }
    
    $affectedRows = $deleteStmt->affected_rows;
    $deleteStmt->close();
    
    if ($affectedRows > 0) {
        // Commit transaction
        $conn->commit();
        $conn->autocommit(true);
        
        echo json_encode([
            'success' => true,
            'message' => "Partner '$partnerName' wurde erfolgreich gelöscht"
        ]);
    } else {
        throw new Exception("Keine Zeilen wurden gelöscht");
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $conn->autocommit(true);
    
    error_log("Error in delete_partner.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'message' => 'Fehler beim Löschen: ' . $e->getMessage()
    ]);
    
} finally {
    $conn->close();
}
?>