<?php
/**
 * count_year_entries.php
 * Zählt die Anzahl der Partner-Endresultate für ein bestimmtes Jahr
 * 
 * @author System
 * @version 1.0
 */

include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)

header('Content-Type: application/json');

// Überprüfe ob Jahr übergeben wurde
if (!isset($_GET['year'])) {
    echo json_encode(['success' => false, 'message' => 'Jahr fehlt']);
    exit;
}

$year = intval($_GET['year']);

try {
    // SQL-Abfrage zum Zählen der Einträge
    $sql = "SELECT COUNT(*) as count FROM endresultate_partner WHERE Jahr = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $year);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    
    $stmt->close();
    
    echo json_encode([
        'success' => true, 
        'count' => $count,
        'year' => $year
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
