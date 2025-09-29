<?php
require 'vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\TextRun;

include '../config.php';

// SQL-Abfrage ausführen
$sql = "
SELECT m.Name, m.Vorname, h.Passe1, h.Passe2, h.Passe3, h.Passe4, h.Passe5, h.Passe6, h.Passe7, h.Passe8,
       (COALESCE(h.Passe1, 0) + COALESCE(h.Passe2, 0) + COALESCE(h.Passe3, 0) + COALESCE(h.Passe4, 0) + 
        COALESCE(h.Passe5, 0) + COALESCE(h.Passe6, 0) + COALESCE(h.Passe7, 0) + COALESCE(h.Passe8, 0)) AS HeimSumme
FROM heimresultate h
INNER JOIN mitglieder m ON m.ID = h.MitgliedID
INNER JOIN Waffen w ON w.ID = m.WaffenID 
WHERE w.Kategorie = 'Kat. A'
ORDER BY HeimSumme DESC;
";

$result = $conn->query($sql);

// Template laden
$templateProcessor = new TemplateProcessor('HeimVorlage4.docx');

// Zähle die Zeilen
$rowCount = 0;
if ($result->num_rows > 0) {
    while ($row1 = $result->fetch_assoc()) {
        if(!empty($row1['HeimSumme'])){
            $rowCount++;
        }
    }
}
$result->data_seek(0); // Setze den Zeiger zurück

// Klone die Platzhalter-Zeile entsprechend der Anzahl der Datenzeilen
$templateProcessor->cloneRow('aRang', $rowCount);

// Daten in die Tabelle einfügen und Debugging-Ausgaben sammeln
$currentRow = 1;
$debugOutput = '';
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Formatierung für die ersten drei Zeilen fett
        if(!empty($row['HeimSumme'])){
            if ($currentRow <= 3) {
                $templateProcessor->setValue("aRang#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $currentRow . '.</w:t></w:r>');
                $templateProcessor->setValue("HeimA#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Name'] . " " . $row['Vorname'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse1#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe1'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse2#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe2'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse3#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe3'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse4#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe4'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse5#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe5'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse6#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe6'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse7#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe7'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse8#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe8'] . '</w:t></w:r>');
                $templateProcessor->setValue("aHeimSumme#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['HeimSumme'] . '</w:t></w:r>');
            } else {
                $templateProcessor->setValue("aRang#{$currentRow}", $currentRow . ".");
                $templateProcessor->setValue("HeimA#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
                $templateProcessor->setValue("aPasse1#{$currentRow}", $row['Passe1']);
                $templateProcessor->setValue("aPasse2#{$currentRow}", $row['Passe2']);
                $templateProcessor->setValue("aPasse3#{$currentRow}", $row['Passe3']);
                $templateProcessor->setValue("aPasse4#{$currentRow}", $row['Passe4']);
                $templateProcessor->setValue("aPasse5#{$currentRow}", $row['Passe5']);
                $templateProcessor->setValue("aPasse6#{$currentRow}", $row['Passe6']);
                $templateProcessor->setValue("aPasse7#{$currentRow}", $row['Passe7']);
                $templateProcessor->setValue("aPasse8#{$currentRow}", $row['Passe8']);
                $templateProcessor->setValue("aHeimSumme#{$currentRow}", $row['HeimSumme']);
            }

            $debugOutput .= "Rang#{$currentRow}: " . $currentRow . ".<br>";
            $debugOutput .= "HeimA#{$currentRow}: " . $row['Name'] . " " . $row['Vorname'] . "<br>";
            $debugOutput .= "Passe1#{$currentRow}: " . $row['Passe1'] . "<br>";
            $debugOutput .= "Passe2#{$currentRow}: " . $row['Passe2'] . "<br>";
            $debugOutput .= "Passe3#{$currentRow}: " . $row['Passe3'] . "<br>";
            $debugOutput .= "Passe4#{$currentRow}: " . $row['Passe4'] . "<br>";
            $debugOutput .= "Passe5#{$currentRow}: " . $row['Passe5'] . "<br>";
            $debugOutput .= "Passe6#{$currentRow}: " . $row['Passe6'] . "<br>";
            $debugOutput .= "Passe7#{$currentRow}: " . $row['Passe7'] . "<br>";
            $debugOutput .= "Passe8#{$currentRow}: " . $row['Passe8'] . "<br>";
            $debugOutput .= "HeimSumme#{$currentRow}: " . $row['HeimSumme'] . "<br><br>";

            $currentRow++;
        }
    }
}



// SQL-Abfrage ausführen
$sql = "
SELECT m.Name, m.Vorname, h.Passe1, h.Passe2, h.Passe3, h.Passe4, h.Passe5, h.Passe6, h.Passe7, h.Passe8,
       (COALESCE(h.Passe1, 0) + COALESCE(h.Passe2, 0) + COALESCE(h.Passe3, 0) + COALESCE(h.Passe4, 0) + 
        COALESCE(h.Passe5, 0) + COALESCE(h.Passe6, 0) + COALESCE(h.Passe7, 0) + COALESCE(h.Passe8, 0)) AS HeimSumme
FROM heimresultate h
INNER JOIN mitglieder m ON m.ID = h.MitgliedID
INNER JOIN Waffen w ON w.ID = m.WaffenID 
WHERE w.Kategorie = 'Kat. B'
ORDER BY HeimSumme DESC;
";

$result = $conn->query($sql);

// Zähle die Zeilen
$rowCount = 0;
if ($result->num_rows > 0) {
    while ($row1 = $result->fetch_assoc()) {
        if(!empty($row1['HeimSumme'])){
            $rowCount++;
        }
    }
}
$result->data_seek(0); // Setze den Zeiger zurück

// Daten in die Tabelle einfügen und Debugging-Ausgaben sammeln
$currentRow = 1;
// Klone die Platzhalter-Zeile entsprechend der Anzahl der Datenzeilen
$templateProcessor->cloneRow('bRang', $rowCount);



if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Formatierung für die ersten drei Zeilen fett
        if(!empty($row['HeimSumme'])){
            if ($currentRow <= 3) {
                $templateProcessor->setValue("bRang#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $currentRow . '.</w:t></w:r>');
                $templateProcessor->setValue("HeimB#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Name'] . " " . $row['Vorname'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse1#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe1'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse2#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe2'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse3#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe3'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse4#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe4'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse5#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe5'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse6#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe6'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse7#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe7'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse8#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe8'] . '</w:t></w:r>');
                $templateProcessor->setValue("bHeimSumme#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['HeimSumme'] . '</w:t></w:r>');
            } else {
                $templateProcessor->setValue("bRang#{$currentRow}", $currentRow . ".");
                $templateProcessor->setValue("HeimB#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
                $templateProcessor->setValue("bPasse1#{$currentRow}", $row['Passe1']);
                $templateProcessor->setValue("bPasse2#{$currentRow}", $row['Passe2']);
                $templateProcessor->setValue("bPasse3#{$currentRow}", $row['Passe3']);
                $templateProcessor->setValue("bPasse4#{$currentRow}", $row['Passe4']);
                $templateProcessor->setValue("bPasse5#{$currentRow}", $row['Passe5']);
                $templateProcessor->setValue("bPasse6#{$currentRow}", $row['Passe6']);
                $templateProcessor->setValue("bPasse7#{$currentRow}", $row['Passe7']);
                $templateProcessor->setValue("bPasse8#{$currentRow}", $row['Passe8']);
                $templateProcessor->setValue("bHeimSumme#{$currentRow}", $row['HeimSumme']);
            }

            $debugOutput .= "Rang#{$currentRow}: " . $currentRow . ".<br>";
            $debugOutput .= "HeimA#{$currentRow}: " . $row['Name'] . " " . $row['Vorname'] . "<br>";
            $debugOutput .= "Passe1#{$currentRow}: " . $row['Passe1'] . "<br>";
            $debugOutput .= "Passe2#{$currentRow}: " . $row['Passe2'] . "<br>";
            $debugOutput .= "Passe3#{$currentRow}: " . $row['Passe3'] . "<br>";
            $debugOutput .= "Passe4#{$currentRow}: " . $row['Passe4'] . "<br>";
            $debugOutput .= "Passe5#{$currentRow}: " . $row['Passe5'] . "<br>";
            $debugOutput .= "Passe6#{$currentRow}: " . $row['Passe6'] . "<br>";
            $debugOutput .= "Passe7#{$currentRow}: " . $row['Passe7'] . "<br>";
            $debugOutput .= "Passe8#{$currentRow}: " . $row['Passe8'] . "<br>";
            $debugOutput .= "HeimSumme#{$currentRow}: " . $row['HeimSumme'] . "<br><br>";

            $currentRow++;
        }
    }
}

// Dateiname mit Datum und Uhrzeit erstellen
$date = new DateTime();
$filename = 'HeimVorlage_' . $date->format('Y-m-d_H-i-s') . '.docx';

// Speichern des neuen Word-Dokuments
$templateProcessor->saveAs($filename);



// Überprüfen, ob die Datei existiert und lesbar ist
if (file_exists($filename) && is_readable($filename)) {
    // Dateipuffer leeren, bevor die Datei heruntergeladen wird
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Datei zum Download bereitstellen
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filename));
    readfile($filename);

    // Temporäre Datei löschen
   // unlink($filename);

    // Skript beenden
    exit();
} else {
    echo "Fehler: Die Datei konnte nicht erstellt oder gelesen werden.";
}
$conn->close();
// Debugging-Ausgabe nach dem Dateidownload anzeigen
echo $debugOutput;

?>
