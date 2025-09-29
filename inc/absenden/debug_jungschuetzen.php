<?php
// debug_jungschuetzen.php - Debug was genau passiert

require '../phpword/vendor/autoload.php';
use PhpOffice\PhpWord\TemplateProcessor;

echo "=== DEBUG JUNGSCHUETZEN BLOCK ===\n\n";

include '../config.php';
$selectedYear = date('Y');

// 1. Prüfe Datenbank
echo "1. DATENBANK CHECK:\n";
echo "-------------------\n";

$checkSql = "
    SELECT COUNT(*) as count
    FROM jungschuetzen j
    LEFT JOIN endstich_jung e ON j.id = e.JungschuetzeID AND e.Jahr = ?
    WHERE e.Schuss1 != 0 AND e.Schuss1 IS NOT NULL
";

$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("i", $selectedYear);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$checkRow = $checkResult->fetch_assoc();
$hasData = $checkRow['count'] > 0;
$checkStmt->close();

echo "Jahr: $selectedYear\n";
echo "Anzahl Datensätze: " . $checkRow['count'] . "\n";
echo "Hat Daten: " . ($hasData ? "JA" : "NEIN") . "\n\n";

// 2. Test mit der EXAKT gleichen Bedingung wie in functions.inc.php
echo "2. TEST MIT GLEICHER BEDINGUNG:\n";
echo "--------------------------------\n";

$templateProcessor = new TemplateProcessor('dat/Resultatbuch_V1.docx');

// EXAKT die gleiche Bedingung wie in functions.inc.php
if (!$hasData) {
    echo "Keine Daten gefunden - versuche Block zu entfernen\n";
    
    // Methode 1: cloneBlock mit 0 (entfernt den Block)
    try {
        $templateProcessor->cloneBlock('JUNGSCHUETZEN_BLOCK', 0);
        echo "✓ cloneBlock('JUNGSCHUETZEN_BLOCK', 0) erfolgreich\n";
    } catch (Exception $e) {
        echo "✗ cloneBlock fehlgeschlagen: " . $e->getMessage() . "\n";
    }
} else {
    echo "Daten vorhanden - Block wird NICHT entfernt\n";
}

// Speichern
$outputFile = 'dat/debug_result_' . date('Y-m-d_H-i-s') . '.docx';
$templateProcessor->saveAs($outputFile);
echo "\nDatei gespeichert: $outputFile\n";

// 3. Test OHNE Bedingung (wie in test_block_removal.php)
echo "\n3. TEST OHNE BEDINGUNG (Force Remove):\n";
echo "---------------------------------------\n";

$templateProcessor2 = new TemplateProcessor('dat/Testvorlage.docx');

// Entferne Block IMMER (zum Testen)
try {
    $templateProcessor2->cloneBlock('JUNGSCHUETZEN_BLOCK', 0);
    echo "✓ cloneBlock OHNE Bedingung erfolgreich\n";
} catch (Exception $e) {
    echo "✗ cloneBlock fehlgeschlagen: " . $e->getMessage() . "\n";
}

$outputFile2 = 'dat/debug_force_remove_' . date('Y-m-d_H-i-s') . '.docx';
$templateProcessor2->saveAs($outputFile2);
echo "Datei gespeichert: $outputFile2\n";

// 4. Prüfe die SQL-Abfrage genauer
echo "\n4. DETAILLIERTE SQL PRÜFUNG:\n";
echo "-----------------------------\n";

// Test verschiedene SQL Varianten
$sql1 = "SELECT * FROM jungschuetzen";
$result1 = $conn->query($sql1);
echo "Anzahl Jungschützen insgesamt: " . $result1->num_rows . "\n";

$sql2 = "SELECT * FROM endstich_jung WHERE Jahr = $selectedYear";
$result2 = $conn->query($sql2);
echo "Anzahl endstich_jung Einträge für $selectedYear: " . $result2->num_rows . "\n";

$sql3 = "SELECT * FROM endstich_jung WHERE Jahr = $selectedYear AND Schuss1 != 0";
$result3 = $conn->query($sql3);
echo "Anzahl mit Schuss1 != 0: " . $result3->num_rows . "\n";

$sql4 = "SELECT * FROM endstich_jung WHERE Jahr = $selectedYear AND Schuss1 IS NOT NULL";
$result4 = $conn->query($sql4);
echo "Anzahl mit Schuss1 IS NOT NULL: " . $result4->num_rows . "\n";

$sql5 = "SELECT * FROM endstich_jung WHERE Jahr = $selectedYear AND (Schuss1 != 0 AND Schuss1 IS NOT NULL)";
$result5 = $conn->query($sql5);
echo "Anzahl mit beiden Bedingungen: " . $result5->num_rows . "\n";

// Zeige ein paar Beispieldaten
echo "\nBeispieldaten aus endstich_jung:\n";
$sql6 = "SELECT j.Name, j.Vorname, e.* FROM endstich_jung e 
         LEFT JOIN jungschuetzen j ON j.id = e.JungschuetzeID 
         WHERE e.Jahr = $selectedYear LIMIT 5";
$result6 = $conn->query($sql6);
if ($result6->num_rows > 0) {
    while ($row = $result6->fetch_assoc()) {
        echo "- " . $row['Name'] . " " . $row['Vorname'] . 
             ": Schuss1=" . $row['Schuss1'] . 
             " (Jahr=" . $row['Jahr'] . ")\n";
    }
} else {
    echo "Keine Daten gefunden\n";
}

$conn->close();

echo "\n=== ENDE DEBUG ===\n";
?>