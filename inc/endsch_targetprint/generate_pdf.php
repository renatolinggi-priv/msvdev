<?php
// generate_pdf.php - API für Zielscheiben-PDF-Generierung
header('Content-Type: application/json');

// Error Reporting für Debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // In JSON-Response werden Errors geloggt

try {
    // Datenbankverbindung
    $config_path = dirname(__FILE__) . '/../config.php';
    if (!file_exists($config_path)) {
        throw new Exception("config.php nicht gefunden");
    }
    require_once $config_path;
    
    // POST-Daten lesen
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception("Ungültige JSON-Daten");
    }
    
    // CSRF Token prüfen
    session_start();
    if (empty($_SESSION['csrf_token']) || !isset($data['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$data['csrf_token'])) {
        throw new Exception("Ungültiger CSRF Token");
    }
    
    // Pflichtfelder prüfen
    if (!isset($data['alleStiche']) || !is_array($data['alleStiche'])) {
        throw new Exception("Keine Stiche-Daten vorhanden");
    }
    
    $alleStiche = $data['alleStiche'];
    $schuetzenName = isset($data['schuetzenName']) ? trim($data['schuetzenName']) : null;
    $jahr = isset($data['jahr']) ? (int)$data['jahr'] : date('Y');
    
    error_log("=== PDF GENERATION START ===");
    error_log("Schützen-Name: " . ($schuetzenName ?? 'NULL'));
    error_log("Jahr: " . $jahr);
    error_log("Anzahl Stiche: " . count($alleStiche));
    
    // Prüfe ob mindestens ein Stich Schüsse hat
    $hatSchuesse = false;
    foreach ($alleStiche as $stich) {
        if (!empty($stich['schuesse'])) {
            $hatSchuesse = true;
            error_log("Stich " . $stich['programmNummer'] . ": " . count($stich['schuesse']) . " Schüsse");
        }
    }
    
    if (!$hatSchuesse) {
        throw new Exception("Keine Schüsse in den Stichen vorhanden");
    }
    
    // PDF-Ausgabeverzeichnis erstellen falls nicht vorhanden
    $pdfDir = dirname(__FILE__) . '/dat';
    if (!file_exists($pdfDir)) {
        mkdir($pdfDir, 0755, true);
        error_log("Created PDF directory: " . $pdfDir);
    }
    
    // Zielscheiben-Klassen laden (lokal im selben Verzeichnis)
    require_once dirname(__FILE__) . '/ZielscheibeReport.php';
    require_once dirname(__FILE__) . '/ZielscheibeGeneratorImagick.php';
    require_once dirname(__FILE__) . '/ZielscheibeGeneratorKeiler.php';
    
    // Report generieren
    $report = new ZielscheibeReport($conn, $jahr, $alleStiche, $schuetzenName);
    
    // WICHTIG: Überschreibe das PDF-Ausgabeverzeichnis
    // PDFGenerator speichert normalerweise in inc/dat/, 
    // wir wollen aber in inc/endsch_targetprint/dat/ speichern
    $report->setPDFOutputDir($pdfDir);
    
    // Output buffering starten
    ob_start();
    $report->generate();
    $output = ob_get_clean();
    
    error_log("=== OUTPUT TYPE CHECK ===");
    error_log("Output length: " . strlen($output));
    error_log("Output first 500 chars: " . substr($output, 0, 500));
    
    // Prüfe ob Output JSON ist (von PDFGenerator)
    $outputData = json_decode($output, true);
    error_log("JSON decode result: " . ($outputData ? "SUCCESS" : "FAILED"));
    
    if ($outputData && isset($outputData['pdf_link'])) {
        // Erfolgreiche PDF-Generierung
        $originalPdfLink = $outputData['pdf_link'];
        error_log("Original PDF Link from PDFGenerator: " . $originalPdfLink);
        
        // Korrigiere den Pfad für Browser
        // PDFGenerator gibt "inc/dat/file.pdf" zurück
        // Wir ändern es zu "inc/endsch_targetprint/dat/file.pdf"
        $pdfLink = str_replace('inc/dat/', 'inc/endsch_targetprint/dat/', $originalPdfLink);
        
        // Führenden Slash hinzufügen
        if (substr($pdfLink, 0, 1) !== '/') {
            $pdfLink = '/' . $pdfLink;
        }
        
        error_log("SUCCESS: Final PDF Link = " . $pdfLink);
        
        // Prüfe ob PDF wirklich existiert
        $pdfFile = dirname(__FILE__) . '/dat/' . basename($pdfLink);
        if (!file_exists($pdfFile)) {
            error_log("WARNING: PDF file not found: " . $pdfFile);
            throw new Exception("PDF wurde nicht erstellt");
        }
        
        echo json_encode([
            'success' => true,
            'pdf_link' => $pdfLink,
            'filename' => $outputData['filename'] ?? 'Zielscheibe.pdf'
        ]);
        
    } else {
        // Fallback
        error_log("WARNING: Kein JSON gefunden im Output");
        throw new Exception("PDF-Generierung fehlgeschlagen. Output: " . substr($output, 0, 200));
    }
    
    error_log("=== PDF GENERATION END ===");
    
} catch (Exception $e) {
    error_log("ERROR in generate_pdf.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
