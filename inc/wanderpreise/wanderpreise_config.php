<?php
/**
 * wanderpreise_config.php
 * Zentrale Konfigurationsdatei für das Wanderpreise-Modul
 */

// Environment Detection
define('WANDERPREISE_ENV', getenv('APP_ENV') ?: 'production');

// Error Reporting basierend auf Environment
if (WANDERPREISE_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    define('WANDERPREISE_DEBUG', true);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    define('WANDERPREISE_DEBUG', false);
}

// Pfad-Konstanten
define('WANDERPREISE_BASE_PATH', __DIR__);
define('WANDERPREISE_DAT_PATH', WANDERPREISE_BASE_PATH . '/dat');
define('WANDERPREISE_PDF_PATH', WANDERPREISE_DAT_PATH);

// Datenbank-Einstellungen (falls spezifisch für Wanderpreise)
define('WANDERPREISE_TABLE_PREFIX', ''); // Falls ein Prefix gewünscht ist

// PDF-Einstellungen
define('WANDERPREISE_PDF_ORIENTATION', 'portrait');
define('WANDERPREISE_PDF_FORMAT', 'A4');
define('WANDERPREISE_PDF_FONT_SIZE', 10);

// Feature Flags
define('WANDERPREISE_ENABLE_HEALTH_CHECK', WANDERPREISE_ENV === 'development');
define('WANDERPREISE_ENABLE_DEBUG_LOGS', WANDERPREISE_ENV === 'development');
define('WANDERPREISE_ENABLE_SQL_LOGGING', false); // Nur bei Bedarf aktivieren

// Cache-Einstellungen
define('WANDERPREISE_CACHE_ENABLED', true);
define('WANDERPREISE_CACHE_TTL', 300); // 5 Minuten

// Sicherheitseinstellungen
define('WANDERPREISE_MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('WANDERPREISE_ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png']);

// Standard-Werte
define('WANDERPREISE_DEFAULT_MIN_GEWINNE', 3);
define('WANDERPREISE_DEFAULT_JAHR', date('Y'));

// SQL Query Limits
define('WANDERPREISE_MAX_RESULTS', 1000);
define('WANDERPREISE_DEFAULT_LIMIT', 100);

/**
 * Hilfsfunktion für Debug-Ausgaben
 */
function wanderpreise_debug($message, $data = null) {
    if (WANDERPREISE_DEBUG) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message";
        
        if ($data !== null) {
            $logMessage .= " | Data: " . json_encode($data);
        }
        
        error_log($logMessage);
        
        // Optional: In separates Debug-File schreiben
        if (WANDERPREISE_ENABLE_DEBUG_LOGS) {
            $debugFile = WANDERPREISE_DAT_PATH . '/debug.log';
            file_put_contents($debugFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}

/**
 * Hilfsfunktion für sichere Datei-Pfade
 */
function wanderpreise_safe_path($filename) {
    // Entfernt gefährliche Zeichen aus Dateinamen
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
    return WANDERPREISE_DAT_PATH . '/' . $filename;
}

/**
 * Prüft ob alle benötigten Verzeichnisse existieren
 */
function wanderpreise_check_directories() {
    $directories = [
        WANDERPREISE_DAT_PATH,
        WANDERPREISE_PDF_PATH
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception("Konnte Verzeichnis nicht erstellen: $dir");
            }
        }
        
        if (!is_writable($dir)) {
            throw new Exception("Verzeichnis ist nicht beschreibbar: $dir");
        }
    }
    
    return true;
}

/**
 * Bereinigt alte PDF-Dateien (älter als 30 Tage)
 */
function wanderpreise_cleanup_old_files($days = 30) {
    $cutoffTime = time() - ($days * 24 * 60 * 60);
    $cleaned = 0;
    
    $files = glob(WANDERPREISE_PDF_PATH . '/*.pdf');
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            if (unlink($file)) {
                $cleaned++;
                wanderpreise_debug("Alte Datei gelöscht: " . basename($file));
            }
        }
    }
    
    return $cleaned;
}

/**
 * Zentrale JSON-Response Funktion
 */
function wanderpreise_json_response($success, $message = '', $data = [], $code = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    // Debug-Info nur in Development
    if (WANDERPREISE_DEBUG && isset($GLOBALS['wanderpreise_debug_info'])) {
        $response['debug'] = $GLOBALS['wanderpreise_debug_info'];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Prüft CSRF-Token
 */
function wanderpreise_check_csrf($token = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }
    
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        wanderpreise_json_response(false, 'CSRF-Token fehlt', [], 403);
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        wanderpreise_json_response(false, 'Ungültiger CSRF-Token', [], 403);
    }
    
    return true;
}

// Automatische Verzeichnisprüfung beim Include
try {
    wanderpreise_check_directories();
} catch (Exception $e) {
    if (WANDERPREISE_DEBUG) {
        die("Wanderpreise Setup-Fehler: " . $e->getMessage());
    }
}
?>