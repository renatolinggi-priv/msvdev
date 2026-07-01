<?php
// delete_regel.php
session_start();
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';
require_once __DIR__ . '/../csrf.inc.php';

// Datenbankverbindung herstellen
$conn = get_db_connection();
if (!$conn) {
    wanderpreise_json_response(false, 'Datenbankverbindung fehlgeschlagen', [], 500);
}

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wanderpreise_json_response(false, 'Method not allowed', [], 405);
}

// CSRF Token prüfen
csrf_require(true);

header('Content-Type: application/json; charset=utf-8');

try {
    $regel_id = intval($_POST['id']);
    
    // Prüfe ob Regel in Verwendung ist
    $check_sql = "SELECT COUNT(*) as count FROM wanderpreise 
                  WHERE verknuepfung_regel = (SELECT regel_code FROM wanderpreise_regeln WHERE id = ?)";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $regel_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    
    if ($check_row['count'] > 0) {
        wanderpreise_json_response(
            false, 
            'Diese Regel wird von ' . $check_row['count'] . ' Wanderpreis(en) verwendet und kann nicht gelöscht werden.'
        );
    }
    
    // Regel löschen
    $delete_sql = "DELETE FROM wanderpreise_regeln WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $regel_id);
    
    if ($stmt->execute()) {
        wanderpreise_json_response(true, 'Regel erfolgreich gelöscht');
    } else {
        throw new Exception('Fehler beim Löschen der Regel');
    }
    
} catch (Exception $e) {
    wanderpreise_json_response(false, 'Fehler: ' . $e->getMessage());
}

$conn->close();
?>