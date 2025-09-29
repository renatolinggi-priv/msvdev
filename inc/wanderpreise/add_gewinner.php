<?php
// add_gewinner.php - Neuen Gewinner für Wanderpreis zuordnen
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

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
wanderpreise_check_csrf();

// Content-Type setzen
header('Content-Type: application/json; charset=utf-8');

try {
    // Pflichtfelder prüfen
    $required_fields = ['wanderpreis_id', 'gewinner_id', 'jahr'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wanderpreise_json_response(false, "Feld '$field' ist erforderlich", [], 400);
        }
    }
    
    // Daten validieren und sanitisieren
    $wanderpreis_id = intval($_POST['wanderpreis_id']);
    $gewinner_id = intval($_POST['gewinner_id']);
    $jahr = intval($_POST['jahr']);
    $rang = trim($_POST['rang'] ?? '');
    $resultat = trim($_POST['resultat'] ?? '');
    $bemerkung = trim($_POST['bemerkung'] ?? '');
    
    // Weitere Validierung
    if ($wanderpreis_id <= 0 || $gewinner_id <= 0) {
        wanderpreise_json_response(false, 'Ungültige Wanderpreis- oder Gewinner-ID', [], 400);
    }
    
    if ($jahr < 1900 || $jahr > 2100) {
        wanderpreise_json_response(false, 'Ungültiges Jahr', [], 400);
    }
    
    // Prüfen ob Wanderpreis existiert
    $check_wanderpreis_sql = "SELECT id, min_anzahl_gewinne, bezeichnung FROM wanderpreise WHERE id = ?";
    $check_wanderpreis_stmt = $conn->prepare($check_wanderpreis_sql);
    $check_wanderpreis_stmt->bind_param("i", $wanderpreis_id);
    $check_wanderpreis_stmt->execute();
    $wanderpreis_result = $check_wanderpreis_stmt->get_result();
    
    if ($wanderpreis_result->num_rows === 0) {
        wanderpreise_json_response(false, 'Wanderpreis nicht gefunden', [], 404);
    }
    
    $wanderpreis = $wanderpreis_result->fetch_assoc();
    
    // Prüfen ob Mitglied existiert
    $check_mitglied_sql = "SELECT ID, Name, Vorname FROM mitglieder WHERE ID = ? AND Aktiv = 1";
    $check_mitglied_stmt = $conn->prepare($check_mitglied_sql);
    $check_mitglied_stmt->bind_param("i", $gewinner_id);
    $check_mitglied_stmt->execute();
    $mitglied_result = $check_mitglied_stmt->get_result();
    
    if ($mitglied_result->num_rows === 0) {
        wanderpreise_json_response(false, 'Mitglied nicht gefunden oder nicht aktiv', [], 404);
    }
    
    $mitglied = $mitglied_result->fetch_assoc();
    
    // Prüfen ob bereits ein Gewinner für dieses Jahr existiert
    $check_jahr_sql = "SELECT id FROM wanderpreise_gewinner WHERE wanderpreis_id = ? AND jahr = ?";
    $check_jahr_stmt = $conn->prepare($check_jahr_sql);
    $check_jahr_stmt->bind_param("ii", $wanderpreis_id, $jahr);
    $check_jahr_stmt->execute();
    $jahr_result = $check_jahr_stmt->get_result();
    
    if ($jahr_result->num_rows > 0) {
        wanderpreise_json_response(false, "Für das Jahr $jahr ist bereits ein Gewinner eingetragen", [], 409);
    }
    
    // Anzahl bisheriger Gewinne dieses Mitglieds für diesen Wanderpreis ermitteln
    $count_gewinne_sql = "SELECT COUNT(*) as anzahl FROM wanderpreise_gewinner WHERE wanderpreis_id = ? AND gewinner_id = ?";
    $count_gewinne_stmt = $conn->prepare($count_gewinne_sql);
    $count_gewinne_stmt->bind_param("ii", $wanderpreis_id, $gewinner_id);
    $count_gewinne_stmt->execute();
    $count_result = $count_gewinne_stmt->get_result();
    $anzahl_gewinne = $count_result->fetch_assoc()['anzahl'] + 1; // +1 für den neuen Gewinn
    
    // Ist es definitiver Besitz?
    $ist_definitiv = ($anzahl_gewinne >= $wanderpreis['min_anzahl_gewinne']) ? 1 : 0;
    
    // User ID für Audit-Trail
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Transaktion starten
    $conn->autocommit(false);
    
    try {
        // Gewinner einfügen
        $insert_sql = "INSERT INTO wanderpreise_gewinner (
                          wanderpreis_id,
                          gewinner_id,
                          jahr,
                          rang,
                          resultat,
                          bemerkung,
                          ist_definitiv,
                          anzahl_gewinne,
                          created_at,
                          created_by
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        
        $insert_stmt = $conn->prepare($insert_sql);
        if (!$insert_stmt) {
            throw new Exception('Fehler beim Vorbereiten der Einfügung: ' . $conn->error);
        }
        
        $insert_stmt->bind_param("iiisssiiii", 
            $wanderpreis_id,
            $gewinner_id,
            $jahr,
            $rang,
            $resultat,
            $bemerkung,
            $ist_definitiv,
            $anzahl_gewinne,
            $user_id
        );
        
        if (!$insert_stmt->execute()) {
            throw new Exception('Fehler beim Einfügen des Gewinners: ' . $insert_stmt->error);
        }
        
        $new_gewinner_id = $conn->insert_id;
        
        // Alle vorherigen Gewinne dieses Mitglieds für diesen Wanderpreis aktualisieren
        $update_sql = "UPDATE wanderpreise_gewinner 
                       SET anzahl_gewinne = ?, 
                           ist_definitiv = ?,
                           updated_at = NOW(),
                           updated_by = ?
                       WHERE wanderpreis_id = ? AND gewinner_id = ?";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("iiiii", 
            $anzahl_gewinne,
            $ist_definitiv,
            $user_id,
            $wanderpreis_id,
            $gewinner_id
        );
        
        if (!$update_stmt->execute()) {
            throw new Exception('Fehler beim Aktualisieren der Gewinner-Anzahl: ' . $update_stmt->error);
        }
        
        // Transaktion bestätigen
        $conn->commit();
        
        // Erfolgreiche Antwort
        $response = [
            'success' => true,
            'message' => 'Gewinner erfolgreich zugeordnet',
            'gewinner_id' => $new_gewinner_id,
            'gewinner_name' => $mitglied['Name'] . ' ' . $mitglied['Vorname'],
            'anzahl_gewinne' => $anzahl_gewinne,
            'ist_definitiv' => $ist_definitiv
        ];
        
        if ($ist_definitiv) {
            $response['message'] .= " - Wanderpreis geht in definitiven Besitz über!";
        }
        
        wanderpreise_json_response(true, $response['message'], [
            'gewinner_id' => $response['gewinner_id'],
            'gewinner_name' => $response['gewinner_name'],
            'anzahl_gewinne' => $response['anzahl_gewinne'],
            'ist_definitiv' => $response['ist_definitiv']
        ]);
        
    } catch (Exception $e) {
        // Transaktion rückgängig machen
        $conn->rollback();
        throw $e;
    }
    
    $conn->autocommit(true); // Autocommit wieder einschalten
    
} catch (Exception $e) {
    // Fehler-Logging
    wanderpreise_debug('Wanderpreis Gewinner Add Error', ['error' => $e->getMessage()]);
    
    wanderpreise_json_response(false, 'Fehler beim Zuordnen des Gewinners: ' . $e->getMessage(), [], 500);
}

$conn->close();
?>