<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

try {
    error_log("=== TEST START ===");
    
    // 1. Config laden
    require_once dirname(__FILE__) . '/../config.php';
    error_log("Config loaded");
    
    // 2. ImageMagick prüfen
    if (!extension_loaded('imagick')) {
        throw new Exception("ImageMagick fehlt");
    }
    error_log("ImageMagick OK");
    
    // 3. Dompdf prüfen
    $dompdfPath = dirname(dirname(__FILE__)) . '/dompdf/autoload.php';
    if (!file_exists($dompdfPath)) {
        throw new Exception("Dompdf nicht gefunden: " . $dompdfPath);
    }
    error_log("Dompdf path OK");
    
    // 4. POST-Daten
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    error_log("POST data: " . print_r($data, true));
    
    echo json_encode(['success' => true, 'message' => 'Alle Checks OK']);
    
} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}