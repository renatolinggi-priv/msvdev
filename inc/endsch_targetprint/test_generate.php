<?php
// test_generate.php - Debugging für generate_pdf.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PDF Generator Test</h1>";
echo "<pre>";

// 1. Prüfe Datei-Existenz
echo "=== FILE CHECKS ===\n";
echo "Current Dir: " . __DIR__ . "\n";
echo "config.php exists: " . (file_exists(__DIR__ . '/../config.php') ? 'YES' : 'NO') . "\n";
echo "PDFGenerator.php exists: " . (file_exists(__DIR__ . '/PDFGenerator.php') ? 'YES' : 'NO') . "\n";
echo "ZielscheibeReport.php exists: " . (file_exists(__DIR__ . '/ZielscheibeReport.php') ? 'YES' : 'NO') . "\n";
echo "ZielscheibeGeneratorImagick.php exists: " . (file_exists(__DIR__ . '/ZielscheibeGeneratorImagick.php') ? 'YES' : 'NO') . "\n";
echo "dompdf/autoload.php exists: " . (file_exists(dirname(__DIR__) . '/dompdf/autoload.php') ? 'YES' : 'NO') . "\n\n";

// 2. Prüfe PHP Extensions
echo "=== PHP EXTENSIONS ===\n";
echo "imagick loaded: " . (extension_loaded('imagick') ? 'YES' : 'NO') . "\n";
echo "gd loaded: " . (extension_loaded('gd') ? 'YES' : 'NO') . "\n";
echo "mbstring loaded: " . (extension_loaded('mbstring') ? 'YES' : 'NO') . "\n";
echo "PHP Version: " . phpversion() . "\n\n";

// 3. Prüfe Verzeichnis-Permissions
echo "=== DIRECTORY PERMISSIONS ===\n";
$pdfDir = __DIR__ . '/dat';
echo "PDF Dir: $pdfDir\n";
echo "PDF Dir exists: " . (file_exists($pdfDir) ? 'YES' : 'NO') . "\n";
if (file_exists($pdfDir)) {
    echo "PDF Dir writable: " . (is_writable($pdfDir) ? 'YES' : 'NO') . "\n";
    echo "PDF Dir permissions: " . substr(sprintf('%o', fileperms($pdfDir)), -4) . "\n";
} else {
    echo "Trying to create PDF Dir...\n";
    if (mkdir($pdfDir, 0755, true)) {
        echo "PDF Dir created: SUCCESS\n";
    } else {
        echo "PDF Dir creation: FAILED\n";
    }
}
echo "\n";

// 4. Test Config laden
echo "=== CONFIG TEST ===\n";
try {
    require_once __DIR__ . '/../config.php';
    echo "Config loaded: SUCCESS\n";
    echo "DB connection exists: " . (isset($conn) ? 'YES' : 'NO') . "\n";
    if (isset($conn)) {
        echo "DB connected: " . ($conn->ping() ? 'YES' : 'NO') . "\n";
    }
} catch (Exception $e) {
    echo "Config load FAILED: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Test Klassen laden
echo "=== CLASS LOADING TEST ===\n";
try {
    if (file_exists(__DIR__ . '/PDFGenerator.php')) {
        require_once __DIR__ . '/PDFGenerator.php';
        echo "PDFGenerator loaded: SUCCESS\n";
    } else {
        echo "PDFGenerator.php NOT FOUND\n";
    }
    
    require_once __DIR__ . '/ZielscheibeGeneratorImagick.php';
    echo "ZielscheibeGeneratorImagick loaded: SUCCESS\n";
    
    require_once __DIR__ . '/ZielscheibeGeneratorKeiler.php';
    echo "ZielscheibeGeneratorKeiler loaded: SUCCESS\n";
    
    require_once __DIR__ . '/ZielscheibeReport.php';
    echo "ZielscheibeReport loaded: SUCCESS\n";
} catch (Exception $e) {
    echo "Class loading FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

// 6. Test ImageMagick
echo "=== IMAGEMAGICK TEST ===\n";
if (extension_loaded('imagick')) {
    try {
        $img = new Imagick();
        $img->newImage(100, 100, new ImagickPixel('white'));
        $img->setImageFormat('png');
        echo "Imagick test: SUCCESS\n";
        echo "Imagick version: " . Imagick::getVersion()['versionString'] . "\n";
        $img->clear();
        $img->destroy();
    } catch (Exception $e) {
        echo "Imagick test FAILED: " . $e->getMessage() . "\n";
    }
} else {
    echo "ImageMagick NOT INSTALLED - THIS IS THE PROBLEM!\n";
    echo "You need to install php-imagick extension\n";
}
echo "\n";

// 7. Test Dompdf laden
echo "=== DOMPDF TEST ===\n";
$dompdfPath = dirname(__DIR__) . '/dompdf/autoload.php';
echo "Dompdf path: $dompdfPath\n";

if (!file_exists($dompdfPath)) {
    echo "ERROR: Dompdf autoload.php NOT FOUND at $dompdfPath\n";
    
    // Prüfe alternative Pfade
    $altPaths = [
        dirname(__DIR__) . '/dompdf/autoload.inc.php',
        __DIR__ . '/dompdf/autoload.php',
        __DIR__ . '/../dompdf/autoload.php',
        __DIR__ . '/../../dompdf/autoload.php'
    ];
    
    echo "Checking alternative paths:\n";
    foreach ($altPaths as $path) {
        echo "  $path: " . (file_exists($path) ? 'FOUND!' : 'not found') . "\n";
    }
} else {
    try {
        require_once $dompdfPath;
        echo "Dompdf loaded: SUCCESS\n";
        
        // Test instantiation
        $options = new Dompdf\Options();
        $dompdf = new Dompdf\Dompdf($options);
        echo "Dompdf instantiation: SUCCESS\n";
        
        // Test Mini-PDF
        echo "\n=== PDF CREATION TEST ===\n";
        $testHtml = '<html><body><h1>Test PDF</h1><p>This is a test.</p></body></html>';
        
        $options = new Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($testHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Speichere Test-PDF
        if (!file_exists($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        
        $testPdfPath = $pdfDir . '/test_' . time() . '.pdf';
        $output = $dompdf->output();
        file_put_contents($testPdfPath, $output);
        
        if (file_exists($testPdfPath)) {
            echo "Test PDF created: SUCCESS\n";
            echo "File: $testPdfPath\n";
            echo "Size: " . filesize($testPdfPath) . " bytes\n";
            echo "Download: <a href='dat/" . basename($testPdfPath) . "'>Test PDF</a>\n";
        } else {
            echo "Test PDF creation FAILED\n";
        }
    } catch (Exception $e) {
        echo "Dompdf FAILED: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
    }
}

echo "</pre>";
echo "<h2>Test Complete</h2>";
echo "<p>Check the output above for any RED flags (NOT FOUND, FAILED, etc.)</p>";
?>
