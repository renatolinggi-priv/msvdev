<?php
// load_existing_rankings.php
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand), startet die Session

// Überprüfen der Datenbankverbindung
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// Jahr aus GET-Parameter
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

try {
    // Vorhandene Rangierungen für das Jahr laden (sortiert nach Datum/Reihenfolge der Anlässe)
    $sql = "SELECT sr.id, sr.rang, sr.preis, sr.jmdefinition_id, jd.Bezeichnung as bezeichnung, jd.Reihenfolge
            FROM sektionsrangierungen sr
            JOIN JMDefinition jd ON sr.jmdefinition_id = jd.ID
            WHERE sr.year = ?
            ORDER BY jd.Reihenfolge ASC, sr.rang ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Fehler beim Vorbereiten der SQL-Abfrage: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $year);
    
    if (!$stmt->execute()) {
        throw new Exception('Fehler beim Ausführen der SQL-Abfrage: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $rankings = [];
    
    while ($row = $result->fetch_assoc()) {
        $rankings[] = [
            'id' => $row['id'],
            'rang' => $row['rang'],
            'preis' => $row['preis'],
            'jmdefinition_id' => $row['jmdefinition_id'],
            'bezeichnung' => $row['bezeichnung']
        ];
    }
    
    $stmt->close();
    
    // Erfolgreiche Antwort
    echo json_encode([
        'success' => true,
        'rankings' => $rankings,
        'year' => $year,
        'count' => count($rankings)
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in load_existing_rankings.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => msvFehler('Die Daten konnten nicht geladen werden. Bitte die Seite neu laden.', $e)
    ]);
} finally {
    $conn->close();
}
?>