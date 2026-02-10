<?php
// check_template.php - Analysiert die Word-Template Struktur

require '../vendor/autoload.php';
use PhpOffice\PhpWord\TemplateProcessor;

echo "=== WORD TEMPLATE ANALYSE ===\n\n";

$templateFile = 'dat/Testvorlage.docx';

if (!file_exists($templateFile)) {
    die("Template-Datei nicht gefunden: $templateFile\n");
}

try {
    $templateProcessor = new TemplateProcessor($templateFile);
    
    // Hole alle Variablen
    $variables = $templateProcessor->getVariables();
    
    echo "Anzahl gefundener Platzhalter: " . count($variables) . "\n\n";
    
    // Gruppiere Variablen
    $groups = [];
    foreach ($variables as $var) {
        // Bestimme Gruppe anhand des Präfix
        if (preg_match('/^([A-Z]+)/', $var, $matches)) {
            $prefix = $matches[1];
            if (!isset($groups[$prefix])) {
                $groups[$prefix] = [];
            }
            $groups[$prefix][] = $var;
        } else {
            $groups['Andere'][] = $var;
        }
    }
    
    // Zeige Jungschützen-relevante Platzhalter
    echo "JUNGSCHÜTZEN-PLATZHALTER:\n";
    echo "-------------------------\n";
    
    $jungschuetzenFound = false;
    foreach ($variables as $var) {
        if (strpos($var, 'J') === 0 || 
            stripos($var, 'JUNG') !== false || 
            stripos($var, 'JS') === 0) {
            echo "  - $var\n";
            $jungschuetzenFound = true;
        }
    }
    
    if (!$jungschuetzenFound) {
        echo "  KEINE Jungschützen-Platzhalter gefunden!\n";
    }
    
    echo "\n";
    
    // Zeige alle Gruppen
    echo "ALLE PLATZHALTER NACH GRUPPEN:\n";
    echo "-------------------------------\n";
    
    foreach ($groups as $group => $vars) {
        echo "\n$group (" . count($vars) . " Platzhalter):\n";
        $uniqueVars = array_unique($vars);
        sort($uniqueVars);
        foreach ($uniqueVars as $var) {
            echo "  - $var\n";
        }
    }
    
    // Prüfe auf Block-Marker
    echo "\n\nBLOCK-MARKER SUCHE:\n";
    echo "-------------------\n";
    
    $possibleBlocks = [];
    foreach ($variables as $var) {
        // Suche nach möglichen Block-Start/Ende-Markern
        if (strpos($var, '_BLOCK') !== false || 
            strpos($var, 'BLOCK_') !== false ||
            strpos($var, 'SLASH') !== false) {
            $possibleBlocks[] = $var;
        }
    }
    
    if (empty($possibleBlocks)) {
        echo "Keine expliziten Block-Marker gefunden.\n";
        echo "Du könntest folgende Block-Marker im Template hinzufügen:\n";
        echo "  \${JUNGSCHUETZEN_BLOCK}\n";
        echo "  ... Inhalt ...\n";
        echo "  \${JUNGSCHUETZEN_BLOCK_END}\n";
    } else {
        echo "Mögliche Block-Marker gefunden:\n";
        foreach ($possibleBlocks as $block) {
            echo "  - $block\n";
        }
    }
    
    // Test cloneRow mit 0
    echo "\n\nTEST cloneRow mit 0:\n";
    echo "--------------------\n";
    
    $testTemplate = new TemplateProcessor($templateFile);
    
    // Teste verschiedene Row-Namen
    $rowsToTest = ['JRang', 'JName', 'JE', 'JZ', 'JS', 'JG', 'JK', 'JT', 'JKK'];
    
    foreach ($rowsToTest as $row) {
        try {
            $testTemplate->cloneRow($row, 0);
            echo "✓ cloneRow('$row', 0) funktioniert\n";
            break; // Nur einen testen, sonst ist Template kaputt
        } catch (Exception $e) {
            echo "✗ cloneRow('$row', 0) fehlgeschlagen: " . $e->getMessage() . "\n";
        }
    }
    
    // Speichere Test-Ergebnis
    $testOutput = 'dat/template_test_' . date('Y-m-d_H-i-s') . '.docx';
    $testTemplate->saveAs($testOutput);
    echo "\nTest-Datei gespeichert: $testOutput\n";
    
} catch (Exception $e) {
    echo "FEHLER: " . $e->getMessage() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== ANALYSE ABGESCHLOSSEN ===\n";
?>