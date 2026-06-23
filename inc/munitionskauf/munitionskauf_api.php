<?php
// munitionskauf_api.php - Backend API für Munitionsbestellungen

// Error handling - Fehler loggen aber nicht anzeigen
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set timezone to Swiss time - WICHTIG!
date_default_timezone_set('Europe/Zurich');

// Zentrale Session-Konfiguration (Cross-Subdomain Cookies, CSRF)
require_once __DIR__ . '/../session_config.inc.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

// Helper function für JSON Response
function jsonResponse($success, $data = null, $message = '') {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

// Bei OPTIONS Request (Preflight) direkt beenden
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Include database connection
    $dbFile = __DIR__ . '/../dbconnect.inc.php';
    
    if (!file_exists($dbFile)) {
        error_log("DB file not found at: " . $dbFile);
        jsonResponse(false, null, 'Database configuration error');
    }
    
    require_once $dbFile;
    
    // Prüfe Verbindung
    if (!isset($conn)) {
        error_log("Connection not initialized after including dbconnect");
        jsonResponse(false, null, 'Database connection not initialized');
    }
    
    if ($conn->connect_error) {
        error_log("Database connection error: " . $conn->connect_error);
        jsonResponse(false, null, 'Database connection failed');
    }

    // CSRF validation for POST requests - angepasst für verschiedene Header-Formate
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = '';
        
        // Fallback für getallheaders() wenn nicht verfügbar
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            // Verschiedene Schreibweisen des Headers prüfen
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'x-csrf-token') {
                    $csrf_token = $value;
                    break;
                }
            }
        } else {
            // Alternative für Server ohne getallheaders()
            if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'];
            }
        }
        
        // Wenn kein Header gefunden, aus POST-Daten versuchen
        if (empty($csrf_token)) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['csrf_token'])) {
                $csrf_token = $input['csrf_token'];
            }
        }
        
        // Token-Validierung nur wenn Session-Token existiert
        if (isset($_SESSION['csrf_token'])) {
            if (empty($csrf_token) || $csrf_token !== $_SESSION['csrf_token']) {
                error_log('CSRF Token Validation Failed');
                http_response_code(403);
                jsonResponse(false, null, 'CSRF token validation failed');
            }
        }
        // Wenn kein Session-Token existiert, loggen wir es aber lassen es durchgehen (für Entwicklung)
        else {
            error_log('Warning: No CSRF token in session - skipping validation');
        }
    }

    // Get action
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'list_mitglieder':
            listMitglieder();
            break;
            
        case 'save_bestellung':
            saveBestellung();
            break;
            
        case 'get_bestellungen':
            getBestellungen();
            break;
            
        case 'delete_bestellung':
            deleteBestellung();
            break;
            
        case 'get_statistics':
            getStatistics();
            break;
            
        default:
            http_response_code(400);
            jsonResponse(false, null, 'Invalid action');
    }

} catch (Exception $e) {
    error_log("API Error in munitionskauf_api.php: " . $e->getMessage());
    jsonResponse(false, null, 'Systemfehler: ' . $e->getMessage());
}

// === Functions ===

function listMitglieder() {
    global $conn;
    
    try {
        $sql = "SELECT ID as id, Vorname, Name 
                FROM mitglieder 
                WHERE Status = 1 
                ORDER BY Name, Vorname";
        
        $result = $conn->query($sql);
        
        if ($result) {
            $mitglieder = [];
            while ($row = $result->fetch_assoc()) {
                $mitglieder[] = $row;
            }
            
            jsonResponse(true, $mitglieder);
        } else {
            error_log('Query error in listMitglieder: ' . $conn->error);
            jsonResponse(false, null, 'Database error');
        }
    } catch (Exception $e) {
        error_log('Error in listMitglieder: ' . $e->getMessage());
        jsonResponse(false, null, 'Error: ' . $e->getMessage());
    }
}

function saveBestellung() {
    global $conn;
    
    try {
        // Get POST data
        $raw_input = file_get_contents('php://input');
        $input = json_decode($raw_input, true);
        
        // Debug logging
        error_log('Received data: ' . $raw_input);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }
        
        $jahr = intval($input['jahr'] ?? date('Y'));
        $kauf_datum = $conn->real_escape_string($input['kauf_datum'] ?? date('Y-m-d'));
        $anlass = $conn->real_escape_string($input['anlass'] ?? '');
        $mitglied_id = isset($input['mitglied_id']) ? intval($input['mitglied_id']) : null;
        $gast_name = isset($input['gast_name']) ? $conn->real_escape_string($input['gast_name']) : null;
        $munition = $input['munition'] ?? [];
        
        // Validation
        if (!$mitglied_id && !$gast_name) {
            jsonResponse(false, null, 'Kein Käufer angegeben');
        }
        
        if (empty($munition)) {
            jsonResponse(false, null, 'Keine Munition ausgewählt');
        }
        
        // Calculate totals
        $gp11_total = 0;
        $gp90_total = 0;
        $total_preis = 0;
        
        foreach ($munition as $item) {
            $anzahl = intval($item['anzahl'] ?? 0);
            $typ = $item['typ'] ?? '';
            
            if (strpos($typ, 'GP11') !== false) {
                $gp11_total += $anzahl;
            } elseif (strpos($typ, 'GP90') !== false) {
                $gp90_total += $anzahl;
            }
            
            $total_preis += $anzahl * 50; // 50 Rappen pro Schuss
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert main record
            $sql = "INSERT INTO munitionskauf (
                        jahr, kauf_datum, anlass, mitglied_id, gast_name, 
                        gp11_total, gp90_total, total_preis, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            
            // Korrigierte bind_param Typen
            if ($mitglied_id !== null) {
                $stmt->bind_param('issisiid', 
                    $jahr, $kauf_datum, $anlass, $mitglied_id, $gast_name,
                    $gp11_total, $gp90_total, $total_preis
                );
            } else {
                // Wenn mitglied_id null ist
                $null_id = null;
                $stmt->bind_param('issisiid', 
                    $jahr, $kauf_datum, $anlass, $null_id, $gast_name,
                    $gp11_total, $gp90_total, $total_preis
                );
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to insert main record: ' . $stmt->error);
            }
            
            $bestellung_id = $conn->insert_id;
            
            // Insert detail records
            $sql = "INSERT INTO munitionskauf_details (bestellung_id, typ, anzahl, preis_pro_schuss) 
                    VALUES (?, ?, ?, 50)";
            
            $stmt = $conn->prepare($sql);
            
            foreach ($munition as $item) {
                $typ = $item['typ'];
                $anzahl = intval($item['anzahl']);
                
                $stmt->bind_param('isi', $bestellung_id, $typ, $anzahl);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to insert detail record: ' . $stmt->error);
                }
            }
            
            $conn->commit();
            jsonResponse(true, null, 'Bestellung erfolgreich gespeichert');
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log('Error in saveBestellung (transaction): ' . $e->getMessage());
            jsonResponse(false, null, 'Fehler beim Speichern: ' . $e->getMessage());
        }
    } catch (Exception $e) {
        error_log('Error in saveBestellung: ' . $e->getMessage());
        jsonResponse(false, null, 'Error: ' . $e->getMessage());
    }
}

function getBestellungen() {
    global $conn;
    
    try {
        $jahr = intval($_GET['jahr'] ?? date('Y'));
        $filter = $_GET['filter'] ?? 'today';
        
        // Debug logging
        error_log("getBestellungen - Jahr: $jahr, Filter: $filter");
        
        // Build date filter
        $date_condition = '';
        $today = date('Y-m-d');
        
        switch ($filter) {
            case 'today':
                $date_condition = "AND munitionskauf.kauf_datum = '$today'";
                error_log("Today filter applied: $today");
                break;
                
            case 'week':
                // Korrigierte Wochenberechnung
                $currentDayOfWeek = date('N'); // 1 (Monday) to 7 (Sunday)
                $daysFromMonday = $currentDayOfWeek - 1;
                $daysToSunday = 7 - $currentDayOfWeek;
                
                $week_start = date('Y-m-d', strtotime("-$daysFromMonday days"));
                $week_end = date('Y-m-d', strtotime("+$daysToSunday days"));
                $date_condition = "AND munitionskauf.kauf_datum BETWEEN '$week_start' AND '$week_end'";
                error_log("Week filter applied: $week_start to $week_end");
                break;
                
            case 'month':
                $month_start = date('Y-m-01');
                $month_end = date('Y-m-t');
                $date_condition = "AND munitionskauf.kauf_datum BETWEEN '$month_start' AND '$month_end'";
                error_log("Month filter applied: $month_start to $month_end");
                break;
                
            case 'year':
                // Jahr-Filter ist bereits in WHERE-Klausel
                $date_condition = '';
                error_log("Year filter applied: showing all for year $jahr");
                break;
                
            default:
                // Fallback: show all for year
                $date_condition = '';
                error_log("Unknown filter '$filter', showing all for year");
                break;
        }
        
        // Get bestellungen - KORRIGIERT: Entferne das falsche Alias "mk"
        $sql = "SELECT munitionskauf.*, 
                COALESCE(CONCAT(mitglieder.Name, ' ', mitglieder.Vorname), munitionskauf.gast_name) as kaeufer_name
                FROM munitionskauf 
                LEFT JOIN mitglieder ON munitionskauf.mitglied_id = mitglieder.ID
                WHERE munitionskauf.jahr = ?
                $date_condition
                ORDER BY munitionskauf.kauf_datum DESC, munitionskauf.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('i', $jahr);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $bestellungen = [];
        while ($row = $result->fetch_assoc()) {
            $bestellungen[] = $row;
        }
        
        error_log("Found " . count($bestellungen) . " records for filter '$filter'");
        
        // Get totals - mit COALESCE für NULL-Werte
        $sql = "SELECT 
                COALESCE(SUM(gp11_total), 0) as gp11_total,
                COALESCE(SUM(gp90_total), 0) as gp90_total,
                COALESCE(SUM(total_preis), 0) as total_preis
                FROM munitionskauf
                WHERE jahr = ?
                $date_condition";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $jahr);
        $stmt->execute();
        $totals = $stmt->get_result()->fetch_assoc();
        
        // Sicherstellen, dass Totals nie null sind
        $totals = [
            'gp11_total' => $totals['gp11_total'] ?? 0,
            'gp90_total' => $totals['gp90_total'] ?? 0,
            'total_preis' => $totals['total_preis'] ?? 0
        ];
        
        // Behalte die ursprüngliche Struktur bei, da JS data.data erwartet
        echo json_encode([
            'success' => true,
            'data' => $bestellungen,
            'totals' => $totals
        ]);
        exit;
    } catch (Exception $e) {
        error_log('Error in getBestellungen: ' . $e->getMessage());
        jsonResponse(false, null, 'Error: ' . $e->getMessage());
    }
}

function deleteBestellung() {
    global $conn;
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        
        if (!$id) {
            jsonResponse(false, null, 'Invalid ID');
        }
        
        $conn->begin_transaction();
        
        // Delete details first
        $sql = "DELETE FROM munitionskauf_details WHERE bestellung_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        // Delete main record
        $sql = "DELETE FROM munitionskauf WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            jsonResponse(true, null, 'Bestellung gelöscht');
        } else {
            throw new Exception('Bestellung nicht gefunden');
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Error in deleteBestellung: ' . $e->getMessage());
        jsonResponse(false, null, $e->getMessage());
    }
}

function getStatistics() {
    global $conn;
    
    try {
        $jahr = intval($_GET['jahr'] ?? date('Y'));
        
        $stats = [];
        
        // Today
        $today = date('Y-m-d');
        $sql = "SELECT COALESCE(SUM(total_preis), 0) as total 
                FROM munitionskauf 
                WHERE kauf_datum = ? AND jahr = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $today, $jahr);
        $stmt->execute();
        $stats['today'] = $stmt->get_result()->fetch_assoc()['total'];
        
        // This week - korrigierte Berechnung
        $currentDayOfWeek = date('N');
        $daysFromMonday = $currentDayOfWeek - 1;
        $daysToSunday = 7 - $currentDayOfWeek;
        
        $week_start = date('Y-m-d', strtotime("-$daysFromMonday days"));
        $week_end = date('Y-m-d', strtotime("+$daysToSunday days"));
        
        $sql = "SELECT COALESCE(SUM(total_preis), 0) as total 
                FROM munitionskauf 
                WHERE kauf_datum BETWEEN ? AND ? AND jahr = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $week_start, $week_end, $jahr);
        $stmt->execute();
        $stats['week'] = $stmt->get_result()->fetch_assoc()['total'];
        
        // This month
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        $sql = "SELECT COALESCE(SUM(total_preis), 0) as total 
                FROM munitionskauf 
                WHERE kauf_datum BETWEEN ? AND ? AND jahr = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $month_start, $month_end, $jahr);
        $stmt->execute();
        $stats['month'] = $stmt->get_result()->fetch_assoc()['total'];
        
        // Year total
        $sql = "SELECT COALESCE(SUM(total_preis), 0) as total 
                FROM munitionskauf 
                WHERE jahr = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $jahr);
        $stmt->execute();
        $stats['year'] = $stmt->get_result()->fetch_assoc()['total'];
        
        // Top buyers
        $sql = "SELECT 
                COALESCE(CONCAT(mitglieder.Name, ' ', mitglieder.Vorname), munitionskauf.gast_name) as name,
                SUM(munitionskauf.total_preis) as total
                FROM munitionskauf 
                LEFT JOIN mitglieder ON munitionskauf.mitglied_id = mitglieder.ID
                WHERE munitionskauf.jahr = ?
                GROUP BY munitionskauf.mitglied_id, munitionskauf.gast_name, mitglieder.Name, mitglieder.Vorname
                ORDER BY total DESC
                LIMIT 5";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $jahr);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $top_buyers = [];
        while ($row = $result->fetch_assoc()) {
            $top_buyers[] = $row;
        }
        
        $stats['top_buyers'] = $top_buyers;
        
        jsonResponse(true, $stats);
    } catch (Exception $e) {
        error_log('Error in getStatistics: ' . $e->getMessage());
        jsonResponse(false, null, 'Error: ' . $e->getMessage());
    }
}
?>
