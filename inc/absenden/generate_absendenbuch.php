<?php
/**
 * generate_absendenbuch.php — Absendenbuch (Resultatbüchlein) als Word-Dokument
 *
 * GET: year   Antwort: JSON {word_link: 'dat/…docx', display_name}
 * Die Befüllung der Vorlage liegt in absendenbuch_docx.inc.php (gemeinsam mit dem Broschüren-PDF).
 */
include '../config.php';                       // $conn + zentraler dat/-Cleanup
require_once __DIR__ . '/absendenbuch_docx.inc.php';

$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : (int)date('Y');

$templateProcessor = absendenbuchTemplateFuellen($selectedYear, $conn);

// Dateiname mit Datum und Uhrzeit erstellen
$date = new DateTime();
$filename = 'dat/Resultatbuechlein_' . $date->format('Y-m-d_H-i-s') . '.docx';

// Speichern des neuen Word-Dokuments
$templateProcessor->saveAs(__DIR__ . '/' . $filename);

// Rückgabe des Dateipfads als JSON
header('Content-Type: application/json');
echo json_encode(array(
    'word_link' => $filename,
    'display_name' => 'Resultatbuechlein_' . $date->format('Y-m-d_H-i-s') . '.docx'
));
$conn->close();
?>
