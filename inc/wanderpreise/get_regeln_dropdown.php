<?php
/**
 * get_regeln_dropdown.php
 * Gibt die Wanderpreise-Regeln als HTML-Options für ein Dropdown zurück
 */

require_once '../dbconnect.inc.php';

// Datenbankverbindung
$conn = get_db_connection();
if (!$conn) {
    http_response_code(500);
    echo '<option value="">Fehler: Datenbankverbindung fehlgeschlagen</option>';
    exit;
}

try {
    // Nur aktive Regeln holen
    $sql = "SELECT id, regel_code, regel_name 
            FROM wanderpreise_regeln 
            WHERE aktiv = 1 
            ORDER BY regel_name ASC";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<option value="' . htmlspecialchars($row['id']) . '">';
            echo htmlspecialchars($row['regel_name']);
            if (!empty($row['regel_code'])) {
                echo ' (' . htmlspecialchars($row['regel_code']) . ')';
            }
            echo '</option>';
        }
    } else {
        echo '<option value="">Keine aktiven Regeln vorhanden</option>';
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo '<option value="">Fehler beim Laden der Regeln</option>';
    error_log('get_regeln_dropdown.php Error: ' . $e->getMessage());
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>