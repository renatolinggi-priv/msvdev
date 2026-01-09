<?php
/**
 * Zentrale API für MSV Wilen
 * Alle API-Endpoints für externe Zugriffe (z.B. WordPress-Integration)
 * 
 * Usage: api.php?endpoint=jahresprogramm&year=2024
 */

// Headers für CORS und JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://www.msvwilen.ch');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Fehlerbehandlung
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Datenbankverbindung
require_once __DIR__ . '/../inc/config.php';

// Endpoint aus GET-Parameter
$endpoint = $_GET['endpoint'] ?? '';

// Response-Funktion
function sendResponse($success, $data = [], $message = '') {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Error Handler
function handleError($message, $details = '') {
    sendResponse(false, [], $message . ($details ? ": $details" : ''));
}

// ==========================================
// ENDPOINTS
// ==========================================

switch ($endpoint) {
    
    // ------------------------------------------
    // JAHRESPROGRAMM
    // Liefert alle Schiessanlässe für ein Jahr
    // ------------------------------------------
    case 'jahresprogramm':
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        
        try {
            // Jahresprogramm-Daten laden
            $sql = "SELECT 
                        Reihenfolge, 
                        Bezeichnung, 
                        Schiesstage, 
                        Maxpunkte, 
                        Streicher, 
                        Erweitert, 
                        Info 
                    FROM JMDefinition 
                    WHERE year = ? 
                    ORDER BY Reihenfolge";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                handleError('Datenbankfehler', $conn->error);
            }
            
            $stmt->bind_param("i", $year);
            $stmt->execute();
            $result = $stmt->get_result();
            $programm = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Zusatztext laden (falls vorhanden)
            $sql2 = "SELECT text FROM JMInformation ORDER BY created_at DESC LIMIT 1";
            $result2 = $conn->query($sql2);
            $zusatztext = '';
            if ($result2 && $result2->num_rows > 0) {
                $zusatztext = $result2->fetch_assoc()['text'] ?? '';
            }
            
            // Daten verarbeiten: Tage und Monate extrahieren
            foreach ($programm as &$item) {
                $dateInfo = extractDaysAndMonths($item['Schiesstage']);
                $item['tage'] = $dateInfo['days'];
                $item['monate'] = $dateInfo['months'];
                
                // JM-Status bestimmen
                $item['jm_status'] = determineJMStatus($item);
            }
            
            sendResponse(true, [
                'year' => $year,
                'programm' => $programm,
                'zusatztext' => $zusatztext,
                'pdf_url' => "https://jahresmeisterschaft.msvwilen.ch/api/pdf_download.php?year=$year"
            ]);
            
        } catch (Exception $e) {
            handleError('Fehler beim Laden des Jahresprogramms', $e->getMessage());
        }
        break;
    
    // ------------------------------------------
    // Weitere Endpoints können hier hinzugefügt werden
    // ------------------------------------------
    
    default:
        handleError('Unbekannter Endpoint', "Endpoint '$endpoint' existiert nicht");
}

$conn->close();

// ==========================================
// HILFSFUNKTIONEN
// ==========================================

/**
 * Extrahiert Tage und Monate aus dem Schiesstage-Text
 */
function extractDaysAndMonths($schiesstage) {
    $lines = explode("\n", $schiesstage);
    $days = [];
    $months = [];
    $currentYear = date("Y");

    foreach ($lines as $line) {
        if (preg_match('/\b(\d{1,2})\.\s+(\w+)(?:\s+(\d{4}))?/u', $line, $matches)) {
            $day = $matches[1]; 
            $month = $matches[2]; 
            $year = isset($matches[3]) ? $matches[3] : $currentYear;

            // Falls das Jahr größer als das aktuelle Jahr ist, füge es hinzu
            if ($year > $currentYear) {
                $month .= " " . $year;
            }

            $days[] = $day;
            $months[] = $month;
        }
    }

    $uniqueDays = implode('. / ', array_unique($days));
    if (!empty($uniqueDays)) {
        $uniqueDays .= '.';
    }
    $uniqueMonths = implode(' / ', array_unique($months));

    return [
        'days' => $uniqueDays,
        'months' => $uniqueMonths
    ];
}

/**
 * Bestimmt den JM-Status (Bonus, X, oder leer)
 */
function determineJMStatus($item) {
    $isStreicher = isset($item['Streicher']) ? $item['Streicher'] : 0;
    $isErweitert = isset($item['Erweitert']) ? $item['Erweitert'] : 0;
    $isInfo = isset($item['Info']) ? $item['Info'] : 0;
    
    if ($item['Maxpunkte'] == 20) {
        return 'Bonus';
    } elseif ($isInfo == 1) {
        return '';
    } elseif ($isStreicher == 1) {
        return 'X';
    } elseif ($isErweitert == 0 && $isInfo == 0) {
        return 'X';
    } else {
        return '';
    }
}
?>
