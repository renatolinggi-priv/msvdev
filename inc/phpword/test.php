<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

// Lade das Template
$templateProcessor = new TemplateProcessor('template.docx');

// Debugging: Anzeigen der vorhandenen Platzhalter
$placeholders = $templateProcessor->getVariables();
echo "Gefundene Platzhalter im Template:\n";
print_r($placeholders);

// Ersetze die Platzhalter
$templateProcessor->setValue('name', 'Max Mustermann');
$templateProcessor->setValue('date', date('d.m.Y'));
$templateProcessor->setValue('address', 'Musterstraße 1, 12345 Musterstadt');

// Speichere das resultierende Dokument
$outputFile = 'result.docx';
$templateProcessor->saveAs($outputFile);

echo "Dokument wurde erstellt: $outputFile\n";
?>
