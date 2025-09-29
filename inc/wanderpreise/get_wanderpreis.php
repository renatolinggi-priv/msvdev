<?php
// get_wanderpreis.php - Wanderpreis-Details für Bearbeitung laden
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');

// Nur GET-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    wanderpreise_json_response(false, 'Method not allowed', [], 405);
}

// Debug-Ausgabe
wanderpreise_debug('get_wanderpreis aufgerufen', ['id' => $_GET['id'] ?? 'keine ID']);

try {
    // Wanderpreis ID prüfen
    if (empty($_GET['id'])) {
        wanderpreise_debug('Keine Wanderpreis-ID angegeben');
        wanderpreise_json_response(false, 'Keine Wanderpreis-ID angegeben', [], 400);
    }
    
    $wanderpreis_id = intval($_GET['id']);
    wanderpreise_debug('Verarbeite Wanderpreis', ['id' => $wanderpreis_id]);
    
    // SQL Query
    $sql = "SELECT
                id,
                bezeichnung,
                beschreibung,
                beschaffung_datum,
                min_anzahl_gewinne,
                auto_verknuepfung,
                verknuepfung_regel,
                verknuepfung_jahr,
                hersteller
            FROM wanderpreise
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        wanderpreise_debug('SQL prepare error', ['error' => $conn->error]);
        throw new Exception('Fehler beim Vorbereiten der SQL-Abfrage: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $wanderpreis_id);
    
    if (!$stmt->execute()) {
        wanderpreise_debug('SQL execute error', ['error' => $stmt->error]);
        throw new Exception('Fehler beim Ausführen der SQL-Abfrage: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    wanderpreise_debug('Query-Ergebnis', ['rows' => $result ? $result->num_rows : 0]);
    
    if ($result && $result->num_rows > 0) {
        $wanderpreis = $result->fetch_assoc();
        wanderpreise_debug('Wanderpreis gefunden', ['id' => $wanderpreis_id]);
        
        wanderpreise_json_response(true, 'Wanderpreis geladen', ['data' => $wanderpreis]);
    } else {
        wanderpreise_debug('Wanderpreis nicht gefunden', ['id' => $wanderpreis_id]);
        wanderpreise_json_response(false, 'Wanderpreis nicht gefunden', [], 404);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    wanderpreise_debug('Exception in get_wanderpreis', ['error' => $e->getMessage()]);
    wanderpreise_json_response(false, 'Fehler beim Laden des Wanderpreises: ' . $e->getMessage(), [], 500);
}

$conn->close();
?>