<?php
//generate_absendenbuch.php
require '../vendor/autoload.php';
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
$templateProcessor = new TemplateProcessor('dat/Resultatbuch_Template20251015.docx');
getJungschuetzenResultate($templateProcessor, $conn);
getPartnerResultate($templateProcessor, $conn);
$templateProcessor->setValue("Year", $selectedYear);
$templateProcessor->setValue("PrintDate", $Printdatum);
getEndstich($templateProcessor, $conn);
getSchwini($templateProcessor, $conn);
getSieger($templateProcessor, $conn);
getZabig($templateProcessor, $conn);
getGlueck($templateProcessor, $conn, $textRun);
getKunst($templateProcessor, $conn);
getEndschGesamt($templateProcessor, $conn, "Kat. A");
getEndschGesamt($templateProcessor, $conn, "Kat. B");

//getJungschuetzenResultate($templateProcessor, $conn);
getHeim($templateProcessor, $conn, "Kat. A");
getHeim($templateProcessor, $conn, "Kat. B");
getCup($templateProcessor, $conn);
getKanti($templateProcessor, $conn);
getJMA($templateProcessor, $conn);
getJMB($templateProcessor, $conn);

// Dateiname mit Datum und Uhrzeit erstellen
$date = new DateTime();
$filename = 'dat/Resultatbuechlein_' . $date->format('Y-m-d_H-i-s') . '.docx';

// Speichern des neuen Word-Dokuments
$templateProcessor->saveAs($filename);

// Rückgabe des Dateipfads als JSON
header('Content-Type: application/json');
echo json_encode(array(
    'word_link' => $filename,
    'display_name' => 'Resultatbuechlein_' . $date->format('Y-m-d_H-i-s') . '.docx'
));
$conn->close();
?>
