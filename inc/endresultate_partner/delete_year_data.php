<?php
/**
 * delete_year_data.php
 * Löscht alle Partner-Endresultate für ein bestimmtes Jahr
 * 
 * @author System
 * @version 1.0
 */

session_start();
include '../config.php';

// CSRF-Schutz
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

header('Content-Type: application/json');

// Überprüfe ob Jahr übergeben wurde
if (!isset($_POST['year'])) {
    echo json_encode(['success' => false, 'message' => 'Jahr fehlt']);
    exit;
}

$year = intval($_POST['year']);

try {
    // Starte Transaktion für sicheres Löschen
    $conn->begin_transaction();
    
    // Zuerst zählen wir die zu löschenden Einträge
    $sql_count = "SELECT COUNT(*) as count FROM endresultate_partner WHERE Jahr = ?";
    $stmt_count = $conn->prepare($sql_count);
    
    if (!$stmt_count) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $stmt_count->bind_param("i", $year);
    
    if (!$stmt_count->execute()) {
        throw new Exception("Execute failed: " . $stmt_count->error);
    }
    
    $result = $stmt_count->get_result();
    $row = $result->fetch_assoc();
    $count_before = $row['count'];
    $stmt_count->close();
    
    // Keine Einträge zum Löschen
    if ($count_before == 0) {
        $conn->rollback();
        echo json_encode([
            'success' => false, 
            'message' => 'Keine Einträge für das Jahr ' . $year . ' gefunden'
        ]);
        exit;
    }
    
    // SQL-Abfrage zum Löschen aller Einträge des Jahres
    $sql_delete = "DELETE FROM endresultate_partner WHERE Jahr = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    
    if (!$stmt_delete) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $stmt_delete->bind_param("i", $year);
    
    if (!$stmt_delete->execute()) {
        throw new Exception("Execute failed: " . $stmt_delete->error);
    }
    
    $affected_rows = $stmt_delete->affected_rows;
    $stmt_delete->close();
    
    // Überprüfe ob alle Einträge gelöscht wurden
    if ($affected_rows != $count_before) {
        throw new Exception("Fehler beim Löschen: Erwartete $count_before gelöschte Einträge, aber nur $affected_rows wurden gelöscht");
    }
    
    // Commit der Transaktion
    $conn->commit();
    
    // Log die Aktion (optional)
    error_log("Benutzer " . ($_SESSION['username'] ?? 'Unbekannt') . " hat alle Partner-Endresultate für Jahr $year gelöscht ($affected_rows Einträge)", 0);
    
    echo json_encode([
        'success' => true, 
        'message' => "Erfolgreich $affected_rows Einträge für das Jahr $year gelöscht",
        'deleted_count' => $affected_rows,
        'year' => $year
    ]);
    
} catch (Exception $e) {
    // Rollback bei Fehler
    $conn->rollback();
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>
