<?php
//generate_absendenbuch.php
require '../phpword/vendor/autoload.php';
require 'functions.inc.php';
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\TextRun;

$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

include '../config.php';
// Erstelle ein DateTime-Objekt für das aktuelle Datum
$heute = new DateTime();

// Erstelle einen IntlDateFormatter, um das Datum im gewünschten Format zu formatieren
$formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::LONG, IntlDateFormatter::NONE);

// Setze das benutzerdefinierte Format
$formatter->setPattern('d. MMMM yyyy');

// Formatiere das Datum
$Printdatum = $formatter->format($heute);


$textRun = new TextRun();


// Template laden
$templateProcessor = new TemplateProcessor('dat/Resultatbuch_Test.docx');
$templateProcessor->setValue("Year", $selectedYear);
$templateProcessor->setValue("PrintDate", $Printdatum);




getJMA($templateProcessor, $conn);

// Dateiname mit Datum und Uhrzeit erstellen
$date = new DateTime();
$filename = 'dat/Test_' . $date->format('Y-m-d_H-i-s') . '.docx';

// Speichern des neuen Word-Dokuments
$templateProcessor->saveAs($filename);

// Rückgabe des Dateipfads als JSON
header('Content-Type: application/json');
echo json_encode(array('word_link' => $filename));



$conn->close();


?>
