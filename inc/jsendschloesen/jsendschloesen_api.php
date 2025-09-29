<?php
// jsendschloesen_api.php - Backend API für JS-Endschiessen (nur Jungschützen)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

// Bei OPTIONS Request (CORS Preflight) sofort beenden
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Helper function für JSON Response
function jsonResponse($success, $data = null, $message = '', $extra = []) {
    $response = [
        'success' => $success,
        'data' => $data,
        'message' => $message
    ];
    foreach ($extra as $key => $value) {
        $response[$key] = $value;
    }
    echo json_encode($response);
    exit;
}

try {
    // Database connection
    $dbFile = __DIR__ . '/../dbconnect.inc.php';
    
    if (!file_exists($dbFile)) {
        error_log("DB file not found at: " . $dbFile);
        jsonResponse(false, null, 'Database configuration error');
    }
    
    require_once $dbFile;
    
    if (!isset($conn)) {
        error_log("Connection not initialized after including dbconnect");
        jsonResponse(false, null, 'Database connection not initialized');
    }
    
    if ($conn->connect_error) {
        error_log("Database connection error: " . $conn->connect_error);
        jsonResponse(false, null, 'Database connection failed');
    }
    
    // Session für CSRF
    if (!isset($_SESSION)) { 
        session_start(); 
    }
    
    // CSRF Check für POST requests
    function checkCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
            
            if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
                error_log("CSRF validation failed");
                jsonResponse(false, null, 'CSRF token validation failed');
            }
        }
    }
    
    // Get action
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch($action) {
        
        case 'get_js_stiche':
            // Hole die 4 Stiche für das JS-Paket
            $sql = "SELECT id, code, name, shots, price_cents 
                    FROM endstich_definition 
                    WHERE code IN ('END', 'SCHWINI_P1', 'SCHWINI_P2', 'ZABIG')
                    AND active = 1";
            
            $result = $conn->query($sql);
            
            if (!$result) {
                error_log("Query error: " . $conn->error);
                jsonResponse(false, null, 'Datenbankfehler beim Abrufen der Stiche');
            }
            
            $stiche = [];
            while ($row = $result->fetch_assoc()) {
                $stiche[] = $row;
            }
            
            jsonResponse(true, $stiche);
            break;
            
        case 'save_js':
            checkCSRF();
            
            // Parse JSON body
            $input = null;
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }
            
            $jahr = (int)($input['jahr'] ?? date('Y'));
            $vorname = trim($input['vorname'] ?? '');
            $nachname = trim($input['nachname'] ?? '');
            $geburtsdatum = !empty($input['geburtsdatum']) ? $input['geburtsdatum'] : null;
            $stiche = $input['stiche'] ?? [];
            $zahlungsmethode = $input['zahlungsmethode'] ?? 'bar';
            
            if (!$vorname || !$nachname) {
                jsonResponse(false, null, 'Vor- und Nachname sind erforderlich');
            }
            
            $conn->begin_transaction();
            
            try {
                // Erstelle Gast-Eintrag
                $gast_name = $vorname . ' ' . $nachname;
                $created_by = $_SESSION['username'] ?? 'system';
                
                // Prüfe ob Gast bereits existiert
                $stmt = $conn->prepare("SELECT id FROM endstich_gaeste WHERE name = ? AND jahr = ?");
                $stmt->bind_param("si", $gast_name, $jahr);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing_gast = $result->fetch_assoc();
                
                if ($existing_gast) {
                    $gast_id = $existing_gast['id'];
                    
                    // Update Geburtsdatum
                    $stmt = $conn->prepare("UPDATE endstich_gaeste SET geburtsdatum = ? WHERE id = ?");
                    $stmt->bind_param("si", $geburtsdatum, $gast_id);
                    $stmt->execute();
                    
                    // Lösche alte Stiche
                    $stmt = $conn->prepare("DELETE FROM endstich_selection WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $gast_id, $jahr);
                    $stmt->execute();
                } else {
                    // Erstelle neuen Gast mit Geburtsdatum
                    $stmt = $conn->prepare("INSERT INTO endstich_gaeste (name, jahr, geburtsdatum, created_by) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("siss", $gast_name, $jahr, $geburtsdatum, $created_by);
                    $stmt->execute();
                    $gast_id = $conn->insert_id;
                }
                
                // Hole aktuellen Paket-Preis
                $paket_preis = 7500; // Fallback
                $sql_preis = "SELECT price_cents FROM endstich_spezialpreise WHERE typ = 'js_paket_preis' LIMIT 1";
                $result_preis = $conn->query($sql_preis);
                if ($result_preis && $row_preis = $result_preis->fetch_assoc()) {
                    $paket_preis = (int)$row_preis['price_cents'];
                }
                
                // Speichere Stiche (festes Paket)
                $gast_spezialpreis = $paket_preis; // Verwende dynamischen JS-Paket Preis
                
                $stmt = $conn->prepare("INSERT INTO endstich_selection (gast_id, jahr, stich_id, zahlungsmethode, gast_spezialpreis, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                
                foreach ($stiche as $stich_id) {
                    if ($stich_id > 0) {
                        $stmt->bind_param("iiisis", $gast_id, $jahr, $stich_id, $zahlungsmethode, $gast_spezialpreis, $created_by);
                        $stmt->execute();
                    }
                }
                
                // Speichere zusätzliche Munition wenn vorhanden
                if (isset($input['zusatz_schuesse']) && is_array($input['zusatz_schuesse'])) {
                    // Lösche alte Einträge
                    $stmt = $conn->prepare("DELETE FROM endstich_zusatz_schuss WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $gast_id, $jahr);
                    $stmt->execute();
                    
                    // Füge neue ein
                    if (!empty($input['zusatz_schuesse'])) {
                        $stmt = $conn->prepare("INSERT INTO endstich_zusatz_schuss (gast_id, jahr, typ, anzahl, preis_cents, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                        
                        foreach ($input['zusatz_schuesse'] as $zusatz) {
                            $typ = $zusatz['typ'] ?? '';
                            $anzahl = (int)($zusatz['anzahl'] ?? 0);
                            if ($typ && $anzahl > 0) {
                                $preis = $anzahl * 60; // 60 Rappen pro Schuss
                                $stmt->bind_param("iisiis", $gast_id, $jahr, $typ, $anzahl, $preis, $created_by);
                                $stmt->execute();
                            }
                        }
                    }
                }
                
                $conn->commit();
                
                jsonResponse(true, ['id' => $gast_id], 'Jungschütze erfolgreich gespeichert');
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error in save_js: " . $e->getMessage());
                jsonResponse(false, null, 'Fehler beim Speichern: ' . $e->getMessage());
            }
            break;
            
        case 'get_year_js':
            // Hole alle JS für ein Jahr
            $jahr = (int)($_GET['jahr'] ?? date('Y'));
            
            // Hole aktuellen Paket-Preis
            $paket_preis = 7500; // Fallback
            $sql_preis = "SELECT price_cents FROM endstich_spezialpreise WHERE typ = 'js_paket_preis' LIMIT 1";
            $result_preis = $conn->query($sql_preis);
            if ($result_preis && $row_preis = $result_preis->fetch_assoc()) {
                $paket_preis = (int)$row_preis['price_cents'];
            }
            
            $sql = "SELECT 
                    g.id,
                    g.name,
                    g.geburtsdatum,
                    SUBSTRING_INDEX(g.name, ' ', 1) as vorname,
                    SUBSTRING_INDEX(g.name, ' ', -1) as nachname,
                    (SELECT zahlungsmethode FROM endstich_selection WHERE gast_id = g.id AND jahr = ? LIMIT 1) as zahlungsmethode
                FROM endstich_gaeste g
                WHERE g.jahr = ?
                AND g.id IN (
                    SELECT DISTINCT gast_id FROM endstich_selection WHERE jahr = ? AND gast_id IS NOT NULL
                )
                ORDER BY g.name";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $jahr, $jahr, $jahr);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $js_liste = [];
            
            while ($row = $result->fetch_assoc()) {
                // Hole zusätzliche Munition
                $stmt2 = $conn->prepare("SELECT typ, anzahl, preis_cents FROM endstich_zusatz_schuss WHERE gast_id = ? AND jahr = ?");
                $stmt2->bind_param("ii", $row['id'], $jahr);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                $row['zusatz_schuesse'] = [];
                $munition_preis = 0;
                
                while ($zusatz = $result2->fetch_assoc()) {
                    $row['zusatz_schuesse'][] = $zusatz;
                    $munition_preis += (int)$zusatz['preis_cents'];
                }
                
                // Berechne Total-Preis mit dynamischem Paket-Preis
                $row['total_price'] = $paket_preis + $munition_preis;
                
                $js_liste[] = $row;
            }
            
            jsonResponse(true, $js_liste);
            break;
            
        case 'get_js_details':
            // Hole Details eines spezifischen Jungschützen für die Bearbeitung
            $id = (int)($_GET['id'] ?? 0);
            $jahr = (int)($_GET['jahr'] ?? date('Y'));
            
            if (!$id) {
                jsonResponse(false, null, 'Keine ID angegeben');
            }
            
            // Hole Gast-Daten
            $stmt = $conn->prepare("SELECT 
                    g.id,
                    g.name,
                    g.geburtsdatum,
                    SUBSTRING_INDEX(g.name, ' ', 1) as vorname,
                    SUBSTRING_INDEX(g.name, ' ', -1) as nachname,
                    (SELECT zahlungsmethode FROM endstich_selection WHERE gast_id = g.id AND jahr = ? LIMIT 1) as zahlungsmethode
                FROM endstich_gaeste g
                WHERE g.id = ? AND g.jahr = ?");
            
            $stmt->bind_param("iii", $jahr, $id, $jahr);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Hole zusätzliche Munition
                $stmt2 = $conn->prepare("SELECT typ, anzahl, preis_cents FROM endstich_zusatz_schuss WHERE gast_id = ? AND jahr = ?");
                $stmt2->bind_param("ii", $id, $jahr);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                $row['zusatz_schuesse'] = [];
                
                while ($zusatz = $result2->fetch_assoc()) {
                    $row['zusatz_schuesse'][] = $zusatz;
                }
                
                jsonResponse(true, $row);
            } else {
                jsonResponse(false, null, 'Jungschütze nicht gefunden');
            }
            break;
            
        case 'update_js':
            checkCSRF();
            
            // Parse JSON body
            $input = null;
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }
            
            $id = (int)($input['id'] ?? 0);
            $jahr = (int)($input['jahr'] ?? date('Y'));
            $vorname = trim($input['vorname'] ?? '');
            $nachname = trim($input['nachname'] ?? '');
            $geburtsdatum = !empty($input['geburtsdatum']) ? $input['geburtsdatum'] : null;
            $stiche = $input['stiche'] ?? [];
            $zahlungsmethode = $input['zahlungsmethode'] ?? 'bar';
            
            if (!$id) {
                jsonResponse(false, null, 'Keine ID angegeben');
            }
            
            if (!$vorname || !$nachname) {
                jsonResponse(false, null, 'Vor- und Nachname sind erforderlich');
            }
            
            $conn->begin_transaction();
            
            try {
                // Update Gast-Eintrag
                $gast_name = $vorname . ' ' . $nachname;
                $updated_by = $_SESSION['username'] ?? 'system';
                
                $stmt = $conn->prepare("UPDATE endstich_gaeste SET name = ?, geburtsdatum = ? WHERE id = ? AND jahr = ?");
                $stmt->bind_param("ssii", $gast_name, $geburtsdatum, $id, $jahr);
                $stmt->execute();
                
                // Lösche alte Stiche
                $stmt = $conn->prepare("DELETE FROM endstich_selection WHERE gast_id = ? AND jahr = ?");
                $stmt->bind_param("ii", $id, $jahr);
                $stmt->execute();
                
                // Hole aktuellen Paket-Preis
                $paket_preis = 7500; // Fallback
                $sql_preis = "SELECT price_cents FROM endstich_spezialpreise WHERE typ = 'js_paket_preis' LIMIT 1";
                $result_preis = $conn->query($sql_preis);
                if ($result_preis && $row_preis = $result_preis->fetch_assoc()) {
                    $paket_preis = (int)$row_preis['price_cents'];
                }
                
                // Speichere neue Stiche
                $gast_spezialpreis = $paket_preis;
                
                $stmt = $conn->prepare("INSERT INTO endstich_selection (gast_id, jahr, stich_id, zahlungsmethode, gast_spezialpreis, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                
                foreach ($stiche as $stich_id) {
                    if ($stich_id > 0) {
                        $stmt->bind_param("iiisis", $id, $jahr, $stich_id, $zahlungsmethode, $gast_spezialpreis, $updated_by);
                        $stmt->execute();
                    }
                }
                
                // Speichere zusätzliche Munition wenn vorhanden
                if (isset($input['zusatz_schuesse']) && is_array($input['zusatz_schuesse'])) {
                    // Lösche alte Einträge
                    $stmt = $conn->prepare("DELETE FROM endstich_zusatz_schuss WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $id, $jahr);
                    $stmt->execute();
                    
                    // Füge neue ein
                    if (!empty($input['zusatz_schuesse'])) {
                        $stmt = $conn->prepare("INSERT INTO endstich_zusatz_schuss (gast_id, jahr, typ, anzahl, preis_cents, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                        
                        foreach ($input['zusatz_schuesse'] as $zusatz) {
                            $typ = $zusatz['typ'] ?? '';
                            $anzahl = (int)($zusatz['anzahl'] ?? 0);
                            if ($typ && $anzahl > 0) {
                                $preis = $anzahl * 60; // 60 Rappen pro Schuss
                                $stmt->bind_param("iisiis", $id, $jahr, $typ, $anzahl, $preis, $updated_by);
                                $stmt->execute();
                            }
                        }
                    }
                }
                
                $conn->commit();
                
                jsonResponse(true, ['id' => $id], 'Jungschütze erfolgreich aktualisiert');
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error in update_js: " . $e->getMessage());
                jsonResponse(false, null, 'Fehler beim Aktualisieren: ' . $e->getMessage());
            }
            break;
            
        case 'delete_js':
            checkCSRF();
            
            // Parse JSON body
            $input = null;
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }
            
            $id = (int)($input['id'] ?? 0);
            $jahr = (int)($input['jahr'] ?? date('Y'));
            
            if (!$id) {
                jsonResponse(false, null, 'Keine ID angegeben');
            }
            
            $conn->begin_transaction();
            
            try {
                // Lösche Stiche
                $stmt = $conn->prepare("DELETE FROM endstich_selection WHERE gast_id = ? AND jahr = ?");
                $stmt->bind_param("ii", $id, $jahr);
                $stmt->execute();
                
                // Lösche Munition
                $stmt = $conn->prepare("DELETE FROM endstich_zusatz_schuss WHERE gast_id = ? AND jahr = ?");
                $stmt->bind_param("ii", $id, $jahr);
                $stmt->execute();
                
                // Lösche Gast selbst
                $stmt = $conn->prepare("DELETE FROM endstich_gaeste WHERE id = ? AND jahr = ?");
                $stmt->bind_param("ii", $id, $jahr);
                $stmt->execute();
                
                $conn->commit();
                
                jsonResponse(true, null, 'Jungschütze erfolgreich gelöscht');
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error in delete_js: " . $e->getMessage());
                jsonResponse(false, null, 'Fehler beim Löschen: ' . $e->getMessage());
            }
            break;
            
        case 'get_js_config':
            // Hole JS-Konfiguration (Paket-Preis und Stiche)
            $config = [];
            
            // Hole Paket-Preis aus Spezialpreise-Tabelle
            $sql = "SELECT price_cents FROM endstich_spezialpreise WHERE typ = 'js_paket_preis' LIMIT 1";
            $result = $conn->query($sql);
            
            if ($result && $row = $result->fetch_assoc()) {
                $config['paket_preis'] = $row['price_cents'];
            } else {
                // Fallback auf Standard-Preis und erstelle Eintrag
                $config['paket_preis'] = 7500; // CHF 75.00
                
                // Erstelle Tabelle falls nicht vorhanden
                $conn->query("CREATE TABLE IF NOT EXISTS endstich_spezialpreise (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    typ VARCHAR(50) UNIQUE,
                    price_cents INT,
                    beschreibung VARCHAR(255),
                    sort_order INT DEFAULT 0
                )");
                
                // Füge JS-Paket-Preis ein
                $conn->query("INSERT IGNORE INTO endstich_spezialpreise (typ, price_cents, beschreibung) 
                              VALUES ('js_paket_preis', 7500, 'Preis für JS-Paket')");
            }
            
            // Hole JS-Stiche
            $sql = "SELECT id, code, name, shots, price_cents 
                    FROM endstich_definition 
                    WHERE code IN ('END', 'SCHWINI_P1', 'SCHWINI_P2', 'ZABIG')
                    AND active = 1
                    ORDER BY sort_order";
            
            $result = $conn->query($sql);
            $stiche = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $stiche[] = $row;
                }
            }
            
            $config['stiche'] = $stiche;
            
            jsonResponse(true, null, '', ['success' => true, 'paket_preis' => $config['paket_preis'], 'stiche' => $config['stiche']]);
            break;
            
        case 'update_js_paket_preis':
            checkCSRF();
            
            // Parse input
            $input = null;
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }
            
            $preis_cents = (int)($input['preis_cents'] ?? 7500);
            
            // Stelle sicher dass Tabelle existiert
            $conn->query("CREATE TABLE IF NOT EXISTS endstich_spezialpreise (
                id INT AUTO_INCREMENT PRIMARY KEY,
                typ VARCHAR(50) UNIQUE,
                price_cents INT,
                beschreibung VARCHAR(255),
                sort_order INT DEFAULT 0
            )");
            
            // Update oder Insert
            $stmt = $conn->prepare("INSERT INTO endstich_spezialpreise (typ, price_cents, beschreibung) 
                                    VALUES ('js_paket_preis', ?, 'Preis für JS-Paket')
                                    ON DUPLICATE KEY UPDATE price_cents = VALUES(price_cents)");
            $stmt->bind_param("i", $preis_cents);
            
            if ($stmt->execute()) {
                jsonResponse(true, ['preis_cents' => $preis_cents], 'Paket-Preis gespeichert');
            } else {
                jsonResponse(false, null, 'Fehler beim Speichern: ' . $conn->error);
            }
            break;
            
        case 'update_js_stich':
            checkCSRF();
            
            // Parse input
            $input = null;
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }
            
            $stich_id = (int)($input['stich_id'] ?? 0);
            $shots = (int)($input['shots'] ?? 10);
            
            if (!$stich_id || $shots < 1) {
                jsonResponse(false, null, 'Ungültige Parameter');
            }
            
            // Update Schussanzahl
            $stmt = $conn->prepare("UPDATE endstich_definition SET shots = ? WHERE id = ? AND code IN ('END', 'SCHWINI_P1', 'SCHWINI_P2', 'ZABIG')");
            $stmt->bind_param("ii", $shots, $stich_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                jsonResponse(true, ['stich_id' => $stich_id, 'shots' => $shots], 'Schussanzahl aktualisiert');
            } else {
                jsonResponse(false, null, 'Fehler beim Update oder Stich nicht gefunden');
            }
            break;
            
        default:
            jsonResponse(false, null, 'Unbekannte Action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("API Error in jsendschloesen_api.php: " . $e->getMessage());
    jsonResponse(false, null, 'Systemfehler: ' . $e->getMessage());
}
