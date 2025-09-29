<?php
// test_jungschuetzen.php - Detailliertes Test-Script für Jungschützen-Bereich
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../phpword/vendor/autoload.php';
require 'functions.inc.php';
use PhpOffice\PhpWord\TemplateProcessor;

// Farben für Terminal-Output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

echo "\n{$yellow}=== TEST JUNGSCHÜTZEN WORD-GENERIERUNG ==={$reset}\n\n";

// Datenbankverbindung
include '../config.php';

$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
echo "Test für Jahr: {$selectedYear}\n\n";

// 1. Prüfe erst mal die Datenbank
echo "{$yellow}1. DATENBANK-CHECK:{$reset}\n";
echo "-------------------\n";

// Prüfe ob Jungschützen existieren
$sql = "SELECT COUNT(*) as total FROM jungschuetzen";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
echo "Anzahl Jungschützen in DB: " . $row['total'] . "\n";

// Prüfe ob Resultate für aktuelles Jahr existieren
$sql = "SELECT COUNT(*) as total FROM endstich_jung WHERE Jahr = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $selectedYear);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo "Anzahl Endstich-Resultate für {$selectedYear}: " . $row['total'] . "\n";

// Prüfe ob gültige Resultate existieren (nicht 0 oder NULL)
$sql = "SELECT COUNT(*) as total FROM endstich_jung WHERE Jahr = ? AND Schuss1 != 0 AND Schuss1 IS NOT NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $selectedYear);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$hasValidData = $row['total'] > 0;
echo "Anzahl GÜLTIGE Endstich-Resultate: " . $row['total'] . "\n";
echo "Hat gültige Daten: " . ($hasValidData ? "{$green}JA{$reset}" : "{$red}NEIN{$reset}") . "\n\n";

// Liste die Jungschützen mit Resultaten
if ($hasValidData) {
    $sql = "
        SELECT j.Name, j.Vorname, e.Schuss1, e.Schuss2, e.Schuss3
        FROM jungschuetzen j
        LEFT JOIN endstich_jung e ON j.id = e.JungschuetzeID AND e.Jahr = ?
        WHERE e.Schuss1 != 0 OR e.Schuss1 IS NOT NULL
        LIMIT 5
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "Beispiel-Resultate:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Name']} {$row['Vorname']}: {$row['Schuss1']}, {$row['Schuss2']}, {$row['Schuss3']}\n";
    }
    echo "\n";
}

// 2. Test Template-Verarbeitung
echo "{$yellow}2. TEMPLATE-VERARBEITUNG TEST:{$reset}\n";
echo "-------------------------------\n";

// Erstelle eine Test-Vorlage falls nicht vorhanden
$testTemplateFile = 'dat/Testvorlage.docx';
if (!file_exists($testTemplateFile)) {
    echo "{$red}Test-Template nicht gefunden. Verwende Haupt-Template...{$reset}\n";
    $testTemplateFile = 'dat/Resultatbuch_V1.docx';
}

if (!file_exists($testTemplateFile)) {
    die("{$red}FEHLER: Keine Template-Datei gefunden!{$reset}\n");
}

echo "Verwende Template: {$testTemplateFile}\n";

// Test verschiedene Szenarien
$scenarios = [
    'normal' => 'Normale Verarbeitung (mit/ohne Daten)',
    'force_empty' => 'Erzwinge leere Daten (Test Block-Entfernung)',
    'force_data' => 'Erzwinge Test-Daten (Test Tabellen-Generierung)'
];

foreach ($scenarios as $scenario => $description) {
    echo "\n{$yellow}SZENARIO: {$description}{$reset}\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        $templateProcessor = new TemplateProcessor($testTemplateFile);
        
        // Prüfe verfügbare Methoden
        echo "Verfügbare Block-Methoden:\n";
        $methods = ['cloneBlock', 'deleteBlock', 'replaceBlock', 'setBlockVisibility'];
        foreach ($methods as $method) {
            $available = method_exists($templateProcessor, $method) ? "{$green}✓{$reset}" : "{$red}✗{$reset}";
            echo "  - {$method}: {$available}\n";
        }
        
        // Prüfe welche Variablen/Platzhalter im Template sind
        $variables = $templateProcessor->getVariables();
        $jungschuetzenVars = array_filter($variables, function($var) {
            return strpos($var, 'J') === 0 || strpos(strtoupper($var), 'JUNG') !== false;
        });
        
        if (!empty($jungschuetzenVars)) {
            echo "\nGefundene Jungschützen-Platzhalter:\n";
            foreach (array_unique($jungschuetzenVars) as $var) {
                echo "  - \${$var}\n";
            }
        }
        
        // Führe Szenario aus
        switch ($scenario) {
            case 'normal':
                echo "\nNormale getJungschuetzenResultate() ausführen...\n";
                getJungschuetzenResultate($templateProcessor, $conn);
                break;
                
            case 'force_empty':
                echo "\nSimuliere: Keine Daten vorhanden\n";
                // Versuche Block zu entfernen
                try {
                    if (method_exists($templateProcessor, 'cloneBlock')) {
                        $templateProcessor->cloneBlock('JUNGSCHUETZEN', 0);
                        echo "{$green}✓ cloneBlock('JUNGSCHUETZEN', 0) erfolgreich{$reset}\n";
                    }
                } catch (Exception $e) {
                    echo "{$red}✗ cloneBlock fehlgeschlagen: " . $e->getMessage() . "{$reset}\n";
                }
                
                // Alternative: Zeilen entfernen
                try {
                    $templateProcessor->cloneRow('JRang', 0);
                    echo "{$green}✓ cloneRow('JRang', 0) erfolgreich{$reset}\n";
                } catch (Exception $e) {
                    echo "{$red}✗ cloneRow fehlgeschlagen: " . $e->getMessage() . "{$reset}\n";
                }
                break;
                
            case 'force_data':
                echo "\nErstelle Test-Daten für Tabelle...\n";
                // Erstelle 3 Test-Zeilen
                $templateProcessor->cloneRow('JRang', 3);
                
                $testData = [
                    ['name' => 'Test Max', 'total' => 250],
                    ['name' => 'Test Anna', 'total' => 245],
                    ['name' => 'Test Peter', 'total' => 240]
                ];
                
                for ($i = 1; $i <= 3; $i++) {
                    $data = $testData[$i-1];
                    $templateProcessor->setValue("JRang#{$i}", $i . ".");
                    $templateProcessor->setValue("JName#{$i}", $data['name']);
                    $templateProcessor->setValue("JE#{$i}", "90");
                    $templateProcessor->setValue("JZ#{$i}", "50");
                    $templateProcessor->setValue("JS#{$i}", "40");
                    $templateProcessor->setValue("JG#{$i}", "30");
                    $templateProcessor->setValue("JK#{$i}", "40");
                    $templateProcessor->setValue("JT#{$i}", $data['total']);
                    $templateProcessor->setValue("JKK#{$i}", "");
                }
                echo "{$green}✓ Test-Daten eingefügt{$reset}\n";
                break;
        }
        
        // Speichere Ergebnis
        $outputFile = 'dat/test_jungschuetzen_' . $scenario . '_' . date('Y-m-d_H-i-s') . '.docx';
        $templateProcessor->saveAs($outputFile);
        echo "{$green}✓ Datei gespeichert: {$outputFile}{$reset}\n";
        
    } catch (Exception $e) {
        echo "{$red}✗ FEHLER: " . $e->getMessage() . "{$reset}\n";
        echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
    }
}

// 3. Debug die aktuelle getJungschuetzenResultate Funktion
echo "\n{$yellow}3. DEBUG AKTUELLE FUNKTION:{$reset}\n";
echo "----------------------------\n";

// Erstelle temporären TemplateProcessor zum Testen
$debugTemplate = new TemplateProcessor($testTemplateFile);

// Aktiviere Debug-Logging
error_log("=== DEBUG getJungschuetzenResultate START ===");

// Mock ein leeres Resultat
echo "Test 1: Leeres Resultat simulieren\n";
$mockConn = new class($conn) {
    private $realConn;
    public function __construct($conn) { $this->realConn = $conn; }
    
    public function prepare($sql) {
        // Simuliere leeres Resultat für Jungschützen
        if (strpos($sql, 'jungschuetzen') !== false) {
            return new class {
                public function bind_param(...$params) { return true; }
                public function execute() { return true; }
                public function get_result() {
                    return new class {
                        public $num_rows = 0;
                        public function fetch_assoc() { return false; }
                    };
                }
                public function close() { return true; }
            };
        }
        return $this->realConn->prepare($sql);
    }
    
    // Proxy andere Methoden
    public function __call($method, $args) {
        return call_user_func_array([$this->realConn, $method], $args);
    }
};

try {
    getJungschuetzenResultate($debugTemplate, $mockConn);
    echo "{$green}✓ Funktion mit leerem Resultat ausgeführt{$reset}\n";
    
    // Prüfe was mit den Platzhaltern passiert ist
    $remainingVars = $debugTemplate->getVariables();
    $jungschuetzenRemaining = array_filter($remainingVars, function($var) {
        return strpos($var, 'J') === 0 || strpos(strtoupper($var), 'JUNG') !== false;
    });
    
    if (empty($jungschuetzenRemaining)) {
        echo "{$green}✓ Alle Jungschützen-Platzhalter wurden entfernt/ersetzt{$reset}\n";
    } else {
        echo "{$yellow}⚠ Verbleibende Jungschützen-Platzhalter:{$reset}\n";
        foreach (array_unique($jungschuetzenRemaining) as $var) {
            echo "  - \${$var}\n";
        }
    }
    
    $debugTemplate->saveAs('dat/test_debug_empty_' . date('Y-m-d_H-i-s') . '.docx');
    
} catch (Exception $e) {
    echo "{$red}✗ Debug-Test fehlgeschlagen: " . $e->getMessage() . "{$reset}\n";
}

echo "\n{$green}=== TESTS ABGESCHLOSSEN ==={$reset}\n";
echo "Prüfe die generierten Dateien im 'dat' Ordner:\n";
$testFiles = glob('dat/test_jungschuetzen_*.docx');
foreach ($testFiles as $file) {
    echo "  - " . basename($file) . " (" . filesize($file) . " bytes)\n";
}

$conn->close();
?>