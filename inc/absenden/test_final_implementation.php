<?php
// test_final_implementation.php - Finaler Test der implementierten Lösung

require '../phpword/vendor/autoload.php';
require 'functions.inc.php';  // Die echte functions.inc.php
use PhpOffice\PhpWord\TemplateProcessor;

echo "=== FINALER TEST DER IMPLEMENTIERUNG ===\n\n";

include '../config.php';
$selectedYear = date('Y');

// 1. Check Datenbank
echo "1. DATENBANK-STATUS:\n";
echo "--------------------\n";

$sql = "SELECT COUNT(*) as count FROM endstich_jung WHERE Jahr = ? AND Schuss1 != 0 AND Schuss1 IS NOT NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $selectedYear);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$hasData = $row['count'] > 0;

echo "Jahr: $selectedYear\n";
echo "Jungschützen mit Daten: " . $row['count'] . "\n";
echo "Status: " . ($hasData ? "DATEN VORHANDEN" : "KEINE DATEN") . "\n\n";

// 2. Test mit Testvorlage
echo "2. TEST MIT TESTVORLAGE:\n";
echo "------------------------\n";

if (file_exists('dat/Testvorlage.docx')) {
    try {
        $templateProcessor = new TemplateProcessor('dat/Testvorlage.docx');
        
        // Rufe die echte Funktion auf
        getJungschuetzenResultate($templateProcessor, $conn);
        
        // Speichern
        $outputFile = 'dat/final_test_testvorlage_' . date('Y-m-d_H-i-s') . '.docx';
        $templateProcessor->saveAs($outputFile);
        
        echo "✓ Datei generiert: $outputFile\n";
        echo "  Dateigröße: " . filesize($outputFile) . " bytes\n";
        
        // Prüfe ob Block-Marker noch im Output sind
        $content = file_get_contents($outputFile);
        if (strpos($content, 'JUNGSCHUETZEN_BLOCK') !== false) {
            echo "  ⚠ Block-Marker noch vorhanden\n";
        } else {
            echo "  ✓ Block-Marker entfernt\n";
        }
        
    } catch (Exception $e) {
        echo "✗ Fehler: " . $e->getMessage() . "\n";
    }
} else {
    echo "Testvorlage nicht gefunden\n";
}

// 3. Test mit Hauptvorlage
echo "\n3. TEST MIT HAUPTVORLAGE:\n";
echo "--------------------------\n";

if (file_exists('dat/Resultatbuch_V1.docx')) {
    try {
        // Teste nur den Jungschützen-Teil
        $templateProcessor = new TemplateProcessor('dat/Resultatbuch_V1.docx');
        
        // Setze die Standard-Platzhalter
        $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        $formatter->setPattern('d. MMMM yyyy');
        $printDate = $formatter->format(new DateTime());
        
        $templateProcessor->setValue("Year", $selectedYear);
        $templateProcessor->setValue("PrintDate", $printDate);
        
        // Rufe NUR die Jungschützen-Funktion auf
        echo "Rufe getJungschuetzenResultate auf...\n";
        getJungschuetzenResultate($templateProcessor, $conn);
        
        // Speichern
        $outputFile = 'dat/final_test_hauptvorlage_' . date('Y-m-d_H-i-s') . '.docx';
        $templateProcessor->saveAs($outputFile);
        
        echo "✓ Datei generiert: $outputFile\n";
        echo "  Dateigröße: " . filesize($outputFile) . " bytes\n";
        
    } catch (Exception $e) {
        echo "✗ Fehler: " . $e->getMessage() . "\n";
    }
} else {
    echo "Hauptvorlage nicht gefunden\n";
}

// 4. Zusammenfassung
echo "\n4. ZUSAMMENFASSUNG:\n";
echo "-------------------\n";

if ($hasData) {
    echo "Es sind Jungschützen-Daten vorhanden.\n";
    echo "→ Die Tabelle sollte mit Daten gefüllt sein.\n";
} else {
    echo "Es sind KEINE Jungschützen-Daten vorhanden.\n";
    echo "→ Der gesamte Jungschützen-Block sollte entfernt sein.\n";
    echo "→ Kein Titel, keine leere Tabelle sichtbar.\n";
}

echo "\nBitte öffne die generierten Word-Dateien und prüfe:\n";
echo "- Bei 'final_test_testvorlage_*.docx': Ist der Block entfernt wenn keine Daten?\n";
echo "- Bei 'final_test_hauptvorlage_*.docx': Funktioniert es auch in der Hauptvorlage?\n";

$conn->close();

echo "\n=== TEST ABGESCHLOSSEN ===\n";
?>