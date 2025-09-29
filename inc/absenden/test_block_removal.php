<?php
// test_block_removal.php - Test für Block-Entfernung mit deiner Testvorlage

require '../phpword/vendor/autoload.php';
use PhpOffice\PhpWord\TemplateProcessor;

echo "=== TEST BLOCK-ENTFERNUNG MIT TESTVORLAGE ===\n\n";

$templateFile = 'dat/Testvorlage.docx';

if (!file_exists($templateFile)) {
    die("ERROR: Testvorlage.docx nicht gefunden!\n");
}

echo "Verwende Template: $templateFile\n";
echo "Dateigröße: " . filesize($templateFile) . " bytes\n\n";

// Test 1: Analyse der Vorlage
echo "1. TEMPLATE ANALYSE:\n";
echo "--------------------\n";

$templateProcessor = new TemplateProcessor($templateFile);

// Hole alle Variablen
$variables = $templateProcessor->getVariables();
echo "Gefundene Variablen (" . count($variables) . "):\n";
foreach ($variables as $var) {
    if (stripos($var, 'JUNG') !== false || strpos($var, 'J') === 0) {
        echo "  - $var\n";
    }
}

// Prüfe ob Block-Marker erkannt werden
$hasBlockStart = in_array('JUNGSCHUETZEN_BLOCK', $variables);
$hasBlockEnd = in_array('/JUNGSCHUETZEN_BLOCK', $variables);

echo "\nBlock-Marker Status:\n";
echo "  JUNGSCHUETZEN_BLOCK gefunden: " . ($hasBlockStart ? "JA" : "NEIN") . "\n";
echo "  /JUNGSCHUETZEN_BLOCK gefunden: " . ($hasBlockEnd ? "JA" : "NEIN") . "\n";

// Test 2: Verschiedene Methoden zum Block entfernen
echo "\n2. TESTE BLOCK-ENTFERNUNG:\n";
echo "---------------------------\n";

// Methode A: cloneBlock mit 0 (PHPWord Standard)
echo "\nMethode A: cloneBlock('JUNGSCHUETZEN_BLOCK', 0)\n";
$testA = new TemplateProcessor($templateFile);
try {
    $testA->cloneBlock('JUNGSCHUETZEN_BLOCK', 0);
    echo "  ✓ Erfolgreich ausgeführt\n";
    $testA->saveAs('dat/test_method_A_' . date('Y-m-d_H-i-s') . '.docx');
    echo "  ✓ Datei gespeichert\n";
} catch (Exception $e) {
    echo "  ✗ Fehler: " . $e->getMessage() . "\n";
}

// Methode B: deleteBlock (falls verfügbar)
echo "\nMethode B: deleteBlock('JUNGSCHUETZEN_BLOCK')\n";
$testB = new TemplateProcessor($templateFile);
if (method_exists($testB, 'deleteBlock')) {
    try {
        $testB->deleteBlock('JUNGSCHUETZEN_BLOCK');
        echo "  ✓ Erfolgreich ausgeführt\n";
        $testB->saveAs('dat/test_method_B_' . date('Y-m-d_H-i-s') . '.docx');
        echo "  ✓ Datei gespeichert\n";
    } catch (Exception $e) {
        echo "  ✗ Fehler: " . $e->getMessage() . "\n";
    }
} else {
    echo "  ✗ Methode deleteBlock nicht verfügbar\n";
}

// Methode C: replaceBlock mit leerem String
echo "\nMethode C: replaceBlock('JUNGSCHUETZEN_BLOCK', '')\n";
$testC = new TemplateProcessor($templateFile);
if (method_exists($testC, 'replaceBlock')) {
    try {
        $testC->replaceBlock('JUNGSCHUETZEN_BLOCK', '');
        echo "  ✓ Erfolgreich ausgeführt\n";
        $testC->saveAs('dat/test_method_C_' . date('Y-m-d_H-i-s') . '.docx');
        echo "  ✓ Datei gespeichert\n";
    } catch (Exception $e) {
        echo "  ✗ Fehler: " . $e->getMessage() . "\n";
    }
} else {
    echo "  ✗ Methode replaceBlock nicht verfügbar\n";
}

// Methode D: setComplexBlock (für neuere PHPWord Versionen)
echo "\nMethode D: setComplexBlock mit leerem Inhalt\n";
$testD = new TemplateProcessor($templateFile);
if (method_exists($testD, 'setComplexBlock')) {
    try {
        // Erstelle leeren Inhalt
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $testD->setComplexBlock('JUNGSCHUETZEN_BLOCK', $section);
        echo "  ✓ Erfolgreich ausgeführt\n";
        $testD->saveAs('dat/test_method_D_' . date('Y-m-d_H-i-s') . '.docx');
        echo "  ✓ Datei gespeichert\n";
    } catch (Exception $e) {
        echo "  ✗ Fehler: " . $e->getMessage() . "\n";
    }
} else {
    echo "  ✗ Methode setComplexBlock nicht verfügbar\n";
}

// Methode E: Manuell alle Platzhalter entfernen
echo "\nMethode E: Alle Platzhalter manuell leeren\n";
$testE = new TemplateProcessor($templateFile);
try {
    // Block-Marker selbst entfernen
    $testE->setValue('JUNGSCHUETZEN_BLOCK', '');
    $testE->setValue('/JUNGSCHUETZEN_BLOCK', '');
    
    // Titel entfernen
    $testE->setValue('Endschiessen-Total-Sieger-Jungschützen', '');
    
    // Tabelle ohne Zeilen
    $testE->cloneRow('JRang', 0);
    
    // Alle J-Platzhalter leeren
    $jPlaceholders = ['JName', 'JE', 'JZ', 'JS', 'JG', 'JK', 'JT', 'JKK'];
    foreach ($jPlaceholders as $placeholder) {
        $testE->setValue($placeholder, '');
    }
    
    echo "  ✓ Erfolgreich ausgeführt\n";
    $testE->saveAs('dat/test_method_E_' . date('Y-m-d_H-i-s') . '.docx');
    echo "  ✓ Datei gespeichert\n";
} catch (Exception $e) {
    echo "  ✗ Fehler: " . $e->getMessage() . "\n";
}

// Test 3: PHPWord Version prüfen
echo "\n3. PHPWORD VERSION INFO:\n";
echo "-------------------------\n";

// Prüfe composer.json für Version
$composerFile = '../phpword/composer.json';
if (file_exists($composerFile)) {
    $composer = json_decode(file_get_contents($composerFile), true);
    if (isset($composer['require']['phpoffice/phpword'])) {
        echo "PHPWord Version (composer.json): " . $composer['require']['phpoffice/phpword'] . "\n";
    }
}

// Prüfe verfügbare Methoden
echo "\nVerfügbare TemplateProcessor Methoden:\n";
$methods = get_class_methods('PhpOffice\PhpWord\TemplateProcessor');
$relevantMethods = ['cloneBlock', 'deleteBlock', 'replaceBlock', 'setComplexBlock', 'cloneRow', 'setValue'];
foreach ($relevantMethods as $method) {
    echo "  - $method: " . (in_array($method, $methods) ? "✓" : "✗") . "\n";
}

// Test 4: Arbeitsweise für die finale Implementierung
echo "\n4. EMPFOHLENE IMPLEMENTIERUNG:\n";
echo "--------------------------------\n";

include '../config.php';
$selectedYear = date('Y');

// Prüfe ob Daten vorhanden sind
$sql = "SELECT COUNT(*) as count FROM endstich_jung WHERE Jahr = ? AND Schuss1 != 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $selectedYear);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$hasData = $row['count'] > 0;

echo "Jungschützen-Daten für $selectedYear: " . ($hasData ? "VORHANDEN ($row[count] Einträge)" : "KEINE DATEN") . "\n";

$finalTest = new TemplateProcessor($templateFile);

if (!$hasData) {
    echo "Keine Daten -> Entferne Block\n";
    
    // Beste funktionierende Methode verwenden
    $removed = false;
    
    // Versuche cloneBlock
    try {
        $finalTest->cloneBlock('JUNGSCHUETZEN_BLOCK', 0);
        echo "  ✓ Block mit cloneBlock entfernt\n";
        $removed = true;
    } catch (Exception $e) {
        echo "  ✗ cloneBlock fehlgeschlagen\n";
    }
    
    if (!$removed) {
        // Fallback: Manuell entfernen
        echo "  Verwende Fallback-Methode\n";
        $finalTest->setValue('JUNGSCHUETZEN_BLOCK', '');
        $finalTest->setValue('/JUNGSCHUETZEN_BLOCK', '');
        $finalTest->cloneRow('JRang', 0);
    }
} else {
    echo "Daten vorhanden -> Fülle Tabelle\n";
    // Hier würde normalerweise die Tabelle gefüllt
}

$finalTest->saveAs('dat/test_final_' . date('Y-m-d_H-i-s') . '.docx');
echo "  ✓ Finale Test-Datei gespeichert\n";

$conn->close();

echo "\n=== TEST ABGESCHLOSSEN ===\n";
echo "\nGenerierte Test-Dateien:\n";
$files = glob('dat/test_*.docx');
foreach ($files as $file) {
    $size = filesize($file);
    echo "  - " . basename($file) . " ($size bytes)\n";
}

echo "\nBitte öffne die generierten Dateien und prüfe:\n";
echo "1. Welche Methode entfernt den Block erfolgreich?\n";
echo "2. Ist der Text 'Endschiessen-Total-Sieger-Jungschützen' noch sichtbar?\n";
echo "3. Ist die Tabelle noch sichtbar?\n";
?>