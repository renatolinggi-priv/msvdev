<?php
// endschloesen_api.php - Backend API für Endschiessen Stich-Erfassung (mysqli Version)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://www.msvwilen.ch');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');
header('Access-Control-Allow-Credentials: true');

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
    // Füge zusätzliche Felder hinzu
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
    
    // Verwende mysqli
    if (!isset($conn)) {
        error_log("Connection not initialized after including dbconnect");
        jsonResponse(false, null, 'Database connection not initialized');
    }
    
    // Prüfe Verbindung
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
            // Bei JSON Content-Type aus Header lesen
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
            
            // Debug logging
            error_log("CSRF Check - Received token: " . substr($token, 0, 10) . "...");
            error_log("CSRF Check - Session token: " . substr($_SESSION['csrf_token'] ?? 'none', 0, 10) . "...");
            
            if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
                error_log("CSRF validation failed");
                jsonResponse(false, null, 'CSRF token validation failed');
            }
        }
    }
    
    // Get action
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch($action) {
        
        case 'list_stiche':
            // Liste aller aktiven Stiche
            $sql = "SELECT id, code, name, shots, price_cents, sort_order 
                    FROM endstich_definition 
                    WHERE active = 1 
                    ORDER BY sort_order, name";
            
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
            
        case 'list_mitglieder':
            // Liste aller aktiven Mitglieder
            $sql = "SELECT ID as id, Vorname, Name 
                    FROM mitglieder 
                    WHERE Status = 1 
                    ORDER BY Name, Vorname";
            
            $result = $conn->query($sql);
            
            if (!$result) {
                error_log("Query error: " . $conn->error);
                jsonResponse(false, null, 'Datenbankfehler beim Abrufen der Mitglieder');
            }
            
            $mitglieder = [];
            while ($row = $result->fetch_assoc()) {
                $mitglieder[] = $row;
            }
            
            jsonResponse(true, $mitglieder);
            break;
            
        case 'get_selection':
            // Hole gespeicherte Auswahl für Mitglied/Gast/Jahr
            $mitglied_id = isset($_GET['mitglied_id']) ? (int)$_GET['mitglied_id'] : 0;
            $gast_name = isset($_GET['gast_name']) ? trim($_GET['gast_name']) : '';
            $jahr = (int)($_GET['jahr'] ?? date('Y'));
            
            if (!$mitglied_id && !$gast_name) {
                jsonResponse(true, []); // Keine Auswahl wenn weder Mitglied noch Gast
            }
            
            $selected = [];
            $zahlungsmethode = 'bar'; // Default
            
            if ($mitglied_id) {
                // Mitglied-basierte Suche - hole auch Zahlungsmethode
                $stmt = $conn->prepare("SELECT DISTINCT zahlungsmethode FROM endstich_selection WHERE mitglied_id = ? AND jahr = ? AND zahlungsmethode IS NOT NULL LIMIT 1");
                $stmt->bind_param("ii", $mitglied_id, $jahr);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $zahlungsmethode = $row['zahlungsmethode'];
                }
                
                // Hole Stiche
                $stmt = $conn->prepare("SELECT stich_id FROM endstich_selection WHERE mitglied_id = ? AND jahr = ?");
                $stmt->bind_param("ii", $mitglied_id, $jahr);
            } else {
                // Gast-basierte Suche - erst Gast-ID finden
                $stmt = $conn->prepare("SELECT id FROM endstich_gaeste WHERE name = ? AND jahr = ?");
                $stmt->bind_param("si", $gast_name, $jahr);
                $stmt->execute();
                $result = $stmt->get_result();
                $gast = $result->fetch_assoc();
                
                if ($gast) {
                    $gast_id = $gast['id'];
                    
                    // Hole Zahlungsmethode
                    $stmt = $conn->prepare("SELECT DISTINCT zahlungsmethode FROM endstich_selection WHERE gast_id = ? AND jahr = ? AND zahlungsmethode IS NOT NULL LIMIT 1");
                    $stmt->bind_param("ii", $gast_id, $jahr);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $zahlungsmethode = $row['zahlungsmethode'];
                    }
                    
                    // Hole Stiche
                    $stmt = $conn->prepare("SELECT stich_id FROM endstich_selection WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $gast_id, $jahr);
                } else {
                    jsonResponse(true, []); // Gast noch nicht erfasst
                }
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $selected[] = $row['stich_id'];
            }
            
            jsonResponse(true, $selected, '', ['zahlungsmethode' => $zahlungsmethode]);
            break;
            
        case 'save_selection':
            checkCSRF();
            
            // Parse JSON body wenn Content-Type application/json
            $input = null;
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }
            
            $mitglied_id = isset($input['mitglied_id']) ? (int)$input['mitglied_id'] : 0;
            $gast_name = isset($input['gast_name']) ? trim($input['gast_name']) : '';
            $jahr = (int)($input['jahr'] ?? date('Y'));
            $stiche = $input['stiche'] ?? [];
            $zahlungsmethode = $input['zahlungsmethode'] ?? 'bar'; // Default: bar
            
            if (!$mitglied_id && !$gast_name) {
                jsonResponse(false, null, 'Kein Mitglied oder Gast angegeben');
            }
            
            $gast_id = null;
            
            // Bei Gast: Prüfe ob er existiert oder lege ihn an
            if ($gast_name && !$mitglied_id) {
                // Prüfe zuerst ob die Tabelle existiert
                $table_check = $conn->query("SHOW TABLES LIKE 'endstich_gaeste'");
                if ($table_check->num_rows == 0) {
                    // Tabelle existiert nicht, erstelle sie
                    $create_table = "CREATE TABLE IF NOT EXISTS `endstich_gaeste` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `name` varchar(200) NOT NULL,
                        `jahr` int(4) NOT NULL,
                        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `created_by` varchar(100) DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unique_gast_jahr` (`name`, `jahr`),
                        KEY `idx_jahr` (`jahr`),
                        KEY `idx_name` (`name`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $conn->query($create_table);
                }
                
                // Prüfe ob Gast bereits existiert
                $stmt = $conn->prepare("SELECT id FROM endstich_gaeste WHERE name = ? AND jahr = ?");
                $stmt->bind_param("si", $gast_name, $jahr);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing_gast = $result->fetch_assoc();
                
                if ($existing_gast) {
                    $gast_id = $existing_gast['id'];
                } else {
                    // Erstelle neuen Gast
                    $created_by = $_SESSION['username'] ?? 'system';
                    $stmt = $conn->prepare("INSERT INTO endstich_gaeste (name, jahr, created_by) VALUES (?, ?, ?)");
                    $stmt->bind_param("sis", $gast_name, $jahr, $created_by);
                    $stmt->execute();
                    $gast_id = $conn->insert_id;
                }
            }
            
            // Validiere Mitglied falls angegeben
            if ($mitglied_id) {
                $stmt = $conn->prepare("SELECT ID FROM mitglieder WHERE ID = ?");
                $stmt->bind_param("i", $mitglied_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    error_log("Invalid Mitglied ID: " . $mitglied_id);
                    jsonResponse(false, null, 'Ungültiges Mitglied');
                }
            }
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Temporär Foreign Key Checks deaktivieren für diese Session
                $conn->query("SET FOREIGN_KEY_CHECKS = 0");
                // Prüfe ob gast_id Spalte existiert in endstich_selection
                $col_check = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'gast_id'");
                if ($col_check->num_rows == 0) {
                    // Spalte existiert nicht, füge sie hinzu
                    $conn->query("ALTER TABLE endstich_selection ADD COLUMN `gast_id` int(11) DEFAULT NULL AFTER `mitglied_id`");
                    $conn->query("ALTER TABLE endstich_selection MODIFY COLUMN `mitglied_id` int(11) DEFAULT NULL");
                }
                
                // Prüfe ob zahlungsmethode Spalte existiert in endstich_selection
                $col_check = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'zahlungsmethode'");
                if ($col_check->num_rows == 0) {
                    // Spalte existiert nicht, füge sie hinzu
                    $conn->query("ALTER TABLE endstich_selection ADD COLUMN `zahlungsmethode` varchar(20) DEFAULT 'bar' AFTER `stich_id`");
                }
                
                // Prüfe ob gast_id Spalte existiert in endstich_zusatz_schuss
                $col_check = $conn->query("SHOW COLUMNS FROM endstich_zusatz_schuss LIKE 'gast_id'");
                if ($col_check->num_rows == 0) {
                    // Spalte existiert nicht, füge sie hinzu
                    $conn->query("ALTER TABLE endstich_zusatz_schuss ADD COLUMN `gast_id` int(11) DEFAULT NULL AFTER `mitglied_id`");
                    $conn->query("ALTER TABLE endstich_zusatz_schuss MODIFY COLUMN `mitglied_id` int(11) DEFAULT NULL");
                }
                
                // Hole existierende Stiche
                if ($mitglied_id) {
                    $stmt = $conn->prepare("SELECT stich_id FROM endstich_selection WHERE mitglied_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $mitglied_id, $jahr);
                } else {
                    $stmt = $conn->prepare("SELECT stich_id FROM endstich_selection WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $gast_id, $jahr);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                
                $existing_stiche = [];
                while ($row = $result->fetch_assoc()) {
                    $existing_stiche[] = (int)$row['stich_id'];
                }
                
                // Bestimme was hinzugefügt und was entfernt werden muss
                $neue_stiche = array_map('intval', $stiche);
                $zu_loeschen = array_diff($existing_stiche, $neue_stiche);
                $zu_erstellen = array_diff($neue_stiche, $existing_stiche);
                $zu_aktualisieren = array_intersect($existing_stiche, $neue_stiche); // Bestehende die bleiben
                
                // Aktualisiere Zahlungsmethode für bestehende Stiche
                if (!empty($zu_aktualisieren)) {
                    if ($mitglied_id) {
                        $stmt = $conn->prepare("UPDATE endstich_selection SET zahlungsmethode = ? WHERE mitglied_id = ? AND jahr = ? AND stich_id = ?");
                    } else {
                        $stmt = $conn->prepare("UPDATE endstich_selection SET zahlungsmethode = ? WHERE gast_id = ? AND jahr = ? AND stich_id = ?");
                    }
                    
                    foreach ($zu_aktualisieren as $stich_id) {
                        $entity_id = $mitglied_id ?: $gast_id;
                        $stmt->bind_param("siii", $zahlungsmethode, $entity_id, $jahr, $stich_id);
                        $stmt->execute();
                    }
                }
                
                // Lösche nur die nicht mehr ausgewählten Stiche
                if (!empty($zu_loeschen)) {
                    $placeholders = implode(',', array_fill(0, count($zu_loeschen), '?'));
                    if ($mitglied_id) {
                        $sql = "DELETE FROM endstich_selection WHERE mitglied_id = ? AND jahr = ? AND stich_id IN ($placeholders)";
                    } else {
                        $sql = "DELETE FROM endstich_selection WHERE gast_id = ? AND jahr = ? AND stich_id IN ($placeholders)";
                    }
                    $stmt = $conn->prepare($sql);
                    
                    $types = 'ii' . str_repeat('i', count($zu_loeschen));
                    // Verwende Variable statt ternären Operator
                    $entity_id = $mitglied_id ?: $gast_id;
                    $params = array_merge([$entity_id, $jahr], $zu_loeschen);
                    
                    $bind_params = [$types];
                    foreach ($params as $key => $value) {
                        $bind_params[] = &$params[$key];
                    }
                    call_user_func_array([$stmt, 'bind_param'], $bind_params);
                    $stmt->execute();
                }
                
                // Füge nur neue Stiche hinzu
                if (!empty($zu_erstellen)) {
                    if ($mitglied_id) {
                        $stmt = $conn->prepare("INSERT INTO endstich_selection (mitglied_id, jahr, stich_id, zahlungsmethode, created_by) VALUES (?, ?, ?, ?, ?)");
                    } else {
                        $stmt = $conn->prepare("INSERT INTO endstich_selection (gast_id, jahr, stich_id, zahlungsmethode, created_by) VALUES (?, ?, ?, ?, ?)");
                    }
                    
                    $created_by = $_SESSION['username'] ?? 'system';
                    
                    foreach ($zu_erstellen as $stich_id) {
                        if ($stich_id > 0) {
                            // Verwende Variable statt ternären Operator für bind_param
                            $entity_id = $mitglied_id ?: $gast_id;
                            $stmt->bind_param("iiiss", $entity_id, $jahr, $stich_id, $zahlungsmethode, $created_by);
                            $stmt->execute();
                        }
                    }
                }
                
                $conn->commit();
                
                // Foreign Key Checks wieder aktivieren
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                
                // Speichere zusätzliche Schüsse wenn vorhanden
                if (isset($input['zusatz_schuesse']) && is_array($input['zusatz_schuesse'])) {
                    // Lösche alte Einträge
                    if ($mitglied_id) {
                        $stmt = $conn->prepare("DELETE FROM endstich_zusatz_schuss WHERE mitglied_id = ? AND jahr = ?");
                        $stmt->bind_param("ii", $mitglied_id, $jahr);
                    } else {
                        $stmt = $conn->prepare("DELETE FROM endstich_zusatz_schuss WHERE gast_id = ? AND jahr = ?");
                        $stmt->bind_param("ii", $gast_id, $jahr);
                    }
                    $stmt->execute();
                    
                    // Füge neue ein
                    if (!empty($input['zusatz_schuesse'])) {
                        if ($mitglied_id) {
                            $stmt = $conn->prepare("INSERT INTO endstich_zusatz_schuss (mitglied_id, jahr, typ, anzahl, preis_cents, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                        } else {
                            $stmt = $conn->prepare("INSERT INTO endstich_zusatz_schuss (gast_id, jahr, typ, anzahl, preis_cents, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                        }
                        
                        foreach ($input['zusatz_schuesse'] as $zusatz) {
                            $typ = $zusatz['typ'] ?? '';
                            $anzahl = (int)($zusatz['anzahl'] ?? 0);
                            if ($typ && $anzahl > 0) {
                                $preis = $anzahl * 50; // 50 Rappen pro Schuss
                                // Verwende Variable statt ternären Operator für bind_param
                                $entity_id = $mitglied_id ?: $gast_id;
                                $stmt->bind_param("iisiis", $entity_id, $jahr, $typ, $anzahl, $preis, $created_by);
                                $stmt->execute();
                            }
                        }
                    }
                }
                
                // Erfolg zurükmelden
                $message = $gast_name ? "Gast '$gast_name' erfolgreich gespeichert" : 'Erfolgreich gespeichert';
                jsonResponse(true, ['message' => $message], $message);
                
            } catch (Exception $e) {
                $conn->rollback();
                // Foreign Key Checks wieder aktivieren auch bei Fehler
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                error_log("Error in save_selection: " . $e->getMessage());
                error_log("SQL Error: " . $conn->error);
                jsonResponse(false, null, 'Fehler beim Speichern: ' . $e->getMessage());
            }
            break;
            
        case 'get_stich_definitions':
            // Für Admin-Edit: Alle Stich-Definitionen
            $sql = "SELECT * FROM endstich_definition ORDER BY sort_order, name";
            $result = $conn->query($sql);
            
            if (!$result) {
                error_log("Query error: " . $conn->error);
                jsonResponse(false, null, 'Datenbankfehler beim Abrufen der Definitionen');
            }
            
            $definitions = [];
            while ($row = $result->fetch_assoc()) {
                $definitions[] = $row;
            }
            
            jsonResponse(true, $definitions);
            break;
            
        case 'update_stich_definition':
            checkCSRF();
            
            // Parse input
            $input = null;
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }
            
            $id = (int)($input['id'] ?? 0);
            
            // Wenn keine ID -> Neuer Eintrag
            if (!$id) {
                // Insert new stich
                $code = $conn->real_escape_string($input['code'] ?? '');
                $name = $conn->real_escape_string($input['name'] ?? '');
                $shots = (int)($input['shots'] ?? 0);
                $price_cents = (int)($input['price_cents'] ?? 0);
                $sort_order = (int)($input['sort_order'] ?? 100);
                $active = isset($input['active']) ? ($input['active'] ? 1 : 0) : 1;
                
                if (empty($code) || empty($name)) {
                    jsonResponse(false, null, 'Code und Name sind erforderlich');
                }
                
                // Check if code already exists
                $stmt = $conn->prepare("SELECT id FROM endstich_definition WHERE code = ?");
                $stmt->bind_param("s", $code);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    jsonResponse(false, null, 'Ein Stich mit diesem Code existiert bereits');
                }
                
                $stmt = $conn->prepare("INSERT INTO endstich_definition (code, name, shots, price_cents, sort_order, active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssiiii", $code, $name, $shots, $price_cents, $sort_order, $active);
                
                if ($stmt->execute()) {
                    $newId = $conn->insert_id;
                    jsonResponse(true, ['id' => $newId], 'Neuer Stich erfolgreich erstellt');
                } else {
                    jsonResponse(false, null, 'Fehler beim Erstellen: ' . $conn->error);
                }
                break;
            }
            
            // Update existing stich
            $updates = [];
            $types = "";
            $params = [];
            
            if (isset($input['name'])) {
                $updates[] = 'name = ?';
                $types .= 's';
                $params[] = $input['name'];
            }
            if (isset($input['shots'])) {
                $updates[] = 'shots = ?';
                $types .= 'i';
                $params[] = (int)$input['shots'];
            }
            if (isset($input['price_cents'])) {
                $updates[] = 'price_cents = ?';
                $types .= 'i';
                $params[] = (int)$input['price_cents'];
            }
            if (isset($input['sort_order'])) {
                $updates[] = 'sort_order = ?';
                $types .= 'i';
                $params[] = (int)$input['sort_order'];
            }
            if (isset($input['active'])) {
                $updates[] = 'active = ?';
                $types .= 'i';
                $params[] = $input['active'] ? 1 : 0;
            }
            
            if (empty($updates)) {
                jsonResponse(false, null, 'Keine Änderungen angegeben');
            }
            
            $types .= 'i'; // für ID
            $params[] = $id;
            
            $sql = "UPDATE endstich_definition SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            // Dynamisches bind_param
            $bind_params = [];
            $bind_params[] = $types;
            foreach ($params as $key => $value) {
                $bind_params[] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_params);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    jsonResponse(true, ['id' => $id], 'Stich erfolgreich aktualisiert');
                } else {
                    jsonResponse(true, ['id' => $id], 'Keine Änderungen vorgenommen');
                }
            } else {
                jsonResponse(false, null, 'Fehler beim Update: ' . $conn->error);
            }
            break;
            
        case 'get_zusatz_schuesse':
            // Hole zusätzliche Schüsse für Mitglied/Gast/Jahr
            $mitglied_id = isset($_GET['mitglied_id']) ? (int)$_GET['mitglied_id'] : 0;
            $gast_name = isset($_GET['gast_name']) ? trim($_GET['gast_name']) : '';
            $jahr = (int)($_GET['jahr'] ?? date('Y'));
            
            if (!$mitglied_id && !$gast_name) {
                jsonResponse(true, []);
            }
            
            if ($mitglied_id) {
                // Mitglied-basierte Suche
                $stmt = $conn->prepare("SELECT typ, anzahl, preis_cents FROM endstich_zusatz_schuss WHERE mitglied_id = ? AND jahr = ?");
                $stmt->bind_param("ii", $mitglied_id, $jahr);
            } else {
                // Gast-basierte Suche - erst Gast-ID finden
                $stmt = $conn->prepare("SELECT id FROM endstich_gaeste WHERE name = ? AND jahr = ?");
                $stmt->bind_param("si", $gast_name, $jahr);
                $stmt->execute();
                $result = $stmt->get_result();
                $gast = $result->fetch_assoc();
                
                if ($gast) {
                    $gast_id = $gast['id'];
                    $stmt = $conn->prepare("SELECT typ, anzahl, preis_cents FROM endstich_zusatz_schuss WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $gast_id, $jahr);
                } else {
                    jsonResponse(true, []); // Gast noch nicht erfasst
                }
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $zusatz = [];
            while ($row = $result->fetch_assoc()) {
                $zusatz[] = $row;
            }
            
            jsonResponse(true, $zusatz);
            break;
            
        case 'get_year_details':
            // Detaillierte Übersicht mit einzelnen Stich-IDs für Matrix-Darstellung
            $jahr = (int)($_GET['jahr'] ?? date('Y'));
            
            // Hole alle Mitglieder und Gäste die entweder Stiche ODER Munition haben
            // Mit expliziter Collation um Fehler zu vermeiden
            // Sortierung: Erst Mitglieder, dann Gäste, jeweils alphabetisch
            $sql = "SELECT 
                    'mitglied' COLLATE utf8mb4_general_ci as typ,
                    m.ID as entity_id,
                    CONCAT(m.Name, ' ', m.Vorname) COLLATE utf8mb4_general_ci as name,
                    1 as sort_group
                FROM mitglieder m
                WHERE m.ID IN (
                    SELECT DISTINCT mitglied_id FROM endstich_selection WHERE jahr = ? AND mitglied_id IS NOT NULL
                    UNION
                    SELECT DISTINCT mitglied_id FROM endstich_zusatz_schuss WHERE jahr = ? AND mitglied_id IS NOT NULL
                )
                UNION
                SELECT 
                    'gast' COLLATE utf8mb4_general_ci as typ,
                    g.id as entity_id,
                    CONCAT(g.name, ' (Gast)') COLLATE utf8mb4_general_ci as name,
                    2 as sort_group
                FROM endstich_gaeste g
                WHERE g.jahr = ?
                AND g.id IN (
                    SELECT DISTINCT gast_id FROM endstich_selection WHERE jahr = ? AND gast_id IS NOT NULL
                    UNION
                    SELECT DISTINCT gast_id FROM endstich_zusatz_schuss WHERE jahr = ? AND gast_id IS NOT NULL
                )
                ORDER BY sort_group, name";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiiii", $jahr, $jahr, $jahr, $jahr, $jahr);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $details = [];
            
            while ($row = $result->fetch_assoc()) {
                $row['stiche'] = [];
                $row['zusatz_schuesse'] = [];
                $row['total_shots'] = 0;
                $row['total_price'] = 0;
                $row['zahlungsmethode'] = 'bar'; // Default
                // Speichere sowohl mitglied_id als auch entity_id für Kompatibilität
                $row['mitglied_id'] = $row['entity_id'];
                $details[] = $row;
            }
            
            // Hole die Stiche und Munition für alle Entities
            foreach ($details as &$entity) {
                if ($entity['typ'] === 'mitglied') {
                    // Stiche für Mitglied - berücksichtige auch alte Daten ohne gast_id
                    $sql = "SELECT 
                            es.stich_id,
                            es.zahlungsmethode,
                            ed.shots,
                            ed.price_cents
                        FROM endstich_selection es
                        JOIN endstich_definition ed ON es.stich_id = ed.id
                        WHERE es.mitglied_id = ? AND es.jahr = ?";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $entity['entity_id'], $jahr);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        $entity['stiche'][] = (int)$row['stich_id'];
                        $entity['total_shots'] += $row['shots'];
                        $entity['total_price'] += $row['price_cents'];
                        // Überschreibe Zahlungsmethode wenn gesetzt
                        if (!empty($row['zahlungsmethode'])) {
                            $entity['zahlungsmethode'] = $row['zahlungsmethode'];
                        }
                    }
                    
                    // Zusätzliche Schüsse für Mitglied
                    $sql = "SELECT typ, anzahl, preis_cents 
                            FROM endstich_zusatz_schuss 
                            WHERE mitglied_id = ? AND jahr = ?";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $entity['entity_id'], $jahr);
                    
                } else {
                    // Stiche für Gast
                    $sql = "SELECT 
                            es.stich_id,
                            es.zahlungsmethode,
                            ed.shots,
                            ed.price_cents
                        FROM endstich_selection es
                        JOIN endstich_definition ed ON es.stich_id = ed.id
                        WHERE es.gast_id = ? AND es.jahr = ?";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $entity['entity_id'], $jahr);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        $entity['stiche'][] = (int)$row['stich_id'];
                        $entity['total_shots'] += $row['shots'];
                        $entity['total_price'] += $row['price_cents'];
                        // Überschreibe Zahlungsmethode wenn gesetzt
                        if (!empty($row['zahlungsmethode'])) {
                            $entity['zahlungsmethode'] = $row['zahlungsmethode'];
                        }
                    }
                    
                    // Zusätzliche Schüsse für Gast
                    $sql = "SELECT typ, anzahl, preis_cents 
                            FROM endstich_zusatz_schuss 
                            WHERE gast_id = ? AND jahr = ?";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $entity['entity_id'], $jahr);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $entity['zusatz_schuesse'][] = [
                        'typ' => $row['typ'],
                        'anzahl' => (int)$row['anzahl'],
                        'preis_cents' => (int)$row['preis_cents']
                    ];
                    $entity['total_price'] += $row['preis_cents'];
                }
            }
            
            jsonResponse(true, $details);
            break;
            
        case 'delete_selection':
            checkCSRF();
            
            // Parse JSON body wenn Content-Type application/json
            $input = null;
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }
            
            $entity_id = isset($input['entity_id']) ? (int)$input['entity_id'] : 0;
            $typ = isset($input['typ']) ? $input['typ'] : '';
            $jahr = (int)($input['jahr'] ?? date('Y'));
            
            if (!$entity_id || !$typ) {
                jsonResponse(false, null, 'Fehlende Parameter');
            }
            
            $conn->begin_transaction();
            
            try {
                if ($typ === 'mitglied') {
                    // Lösche Stiche für Mitglied
                    $stmt = $conn->prepare("DELETE FROM endstich_selection WHERE mitglied_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $entity_id, $jahr);
                    $stmt->execute();
                    
                    // Lösche Zusatz-Schüsse für Mitglied
                    $stmt = $conn->prepare("DELETE FROM endstich_zusatz_schuss WHERE mitglied_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $entity_id, $jahr);
                    $stmt->execute();
                    
                } else if ($typ === 'gast') {
                    // Lösche Stiche für Gast
                    $stmt = $conn->prepare("DELETE FROM endstich_selection WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $entity_id, $jahr);
                    $stmt->execute();
                    
                    // Lösche Zusatz-Schüsse für Gast
                    $stmt = $conn->prepare("DELETE FROM endstich_zusatz_schuss WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $entity_id, $jahr);
                    $stmt->execute();
                    
                    // Optional: Lösche auch den Gast selbst wenn keine Einträge mehr vorhanden
                    // $stmt = $conn->prepare("DELETE FROM endstich_gaeste WHERE id = ? AND jahr = ?");
                    // $stmt->bind_param("ii", $entity_id, $jahr);
                    // $stmt->execute();
                }
                
                $conn->commit();
                jsonResponse(true, null, 'Erfolgreich gelöscht');
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error in delete_selection: " . $e->getMessage());
                jsonResponse(false, null, 'Fehler beim Löschen: ' . $e->getMessage());
            }
            break;
            
        default:
            jsonResponse(false, null, 'Unbekannte Action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("API Error in endschloesen_api.php: " . $e->getMessage());
    jsonResponse(false, null, 'Systemfehler: ' . $e->getMessage());
}
