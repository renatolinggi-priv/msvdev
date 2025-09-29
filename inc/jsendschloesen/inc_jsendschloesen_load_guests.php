<?php
/**
 * load_guests.php
 * Lädt alle Jungschützen für ein bestimmtes Jahr
 */

session_start();
header('Content-Type: application/json');

try {
    include '../dbconnect.inc.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

$jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : date('Y');

try {
    $sql = "SELECT * FROM jsendschloesen_gaeste 
            WHERE jahr = ? 
            ORDER BY nachname ASC, vorname ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $guests = [];
    while ($row = $result->fetch_assoc()) {
        $guests[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $guests
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>
