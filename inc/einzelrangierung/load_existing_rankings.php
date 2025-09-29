<?php
// load_existing_rankings.php
header('Content-Type: application/json');
include '../config.php';

// Überprüfen der Datenbankverbindung
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// Jahr aus GET-Parameter
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

try {
    // Vorhandene Einzelrangierungen laden (sortiert nach Anlass-Reihenfolge, dann nach Rang)
    $sql = "SELECT er.id, er.rang, er.resultat, er.preis,
                   jd.Bezeichnung as anlass_bezeichnung, jd.Reihenfolge,
                   CONCAT(m.Name, ' ', m.Vorname) as mitglied_name
            FROM einzelrangierungen er
            JOIN JMDefinition jd ON er.jmdefinition_id = jd.ID
            JOIN mitglieder m ON er.mitglied_id = m.ID
            WHERE er.year = ?
            ORDER BY jd.Reihenfolge ASC, er.rang ASC";
    
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
            'resultat' => $row['resultat'],
            'preis' => $row['preis'],
            'anlass_bezeichnung' => $row['anlass_bezeichnung'],
            'mitglied_name' => $row['mitglied_name'],
            'reihenfolge' => $row['Reihenfolge']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'rankings' => $rankings
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in load_existing_rankings.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Laden der Rangierungen: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>