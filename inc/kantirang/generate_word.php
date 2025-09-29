<?php
require '../phpword/vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\TextRun;

include '../config.php';

// SQL-Abfrage ausführen
$sql = "
SELECT m.Name, m.Vorname, k.Passe1, k.Passe2, k.Passe3, k.Passe4, k.Passe5,
       (COALESCE(k.Passe1, 0) + COALESCE(k.Passe2, 0) + COALESCE(k.Passe3, 0) + COALESCE(k.Passe4, 0) + 
        COALESCE(k.Passe5, 0)) AS KantonalSumme
FROM kantiresultate k
INNER JOIN mitglieder m ON m.ID = k.MitgliedID
INNER JOIN Waffen w ON w.ID = m.WaffenID 
WHERE w.Kategorie = 'Kat. A'
ORDER BY KantonalSumme DESC;
";

$result = $conn->query($sql);

// Template laden
$templateProcessor = new TemplateProcessor('KantonalVorlage4.docx');

// Zähle die Zeilen
$rowCount = 0;
if ($result->num_rows > 0) {
    while ($row1 = $result->fetch_assoc()) {
        if(!empty($row1['KantonalSumme'])){
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
        if(!empty($row['KantonalSumme'])){
            if ($currentRow <= 3) {
                $templateProcessor->setValue("aRang#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $currentRow . '.</w:t></w:r>');
                $templateProcessor->setValue("KantonalA#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Name'] . " " . $row['Vorname'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse1#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe1'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse2#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe2'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse3#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe3'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse4#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe4'] . '</w:t></w:r>');
                $templateProcessor->setValue("aPasse5#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe5'] . '</w:t></w:r>');
                $templateProcessor->setValue("aKantonalSumme#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['KantonalSumme'] . '</w:t></w:r>');
            } else {
                $templateProcessor->setValue("aRang#{$currentRow}", $currentRow . ".");
                $templateProcessor->setValue("KantonalA#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
                $templateProcessor->setValue("aPasse1#{$currentRow}", $row['Passe1']);
                $templateProcessor->setValue("aPasse2#{$currentRow}", $row['Passe2']);
                $templateProcessor->setValue("aPasse3#{$currentRow}", $row['Passe3']);
                $templateProcessor->setValue("aPasse4#{$currentRow}", $row['Passe4']);
                $templateProcessor->setValue("aPasse5#{$currentRow}", $row['Passe5']);
                $templateProcessor->setValue("aKantonalSumme#{$currentRow}", $row['KantonalSumme']);
            }

            $debugOutput .= "Rang#{$currentRow}: " . $currentRow . ".<br>";
            $debugOutput .= "KantonalA#{$currentRow}: " . $row['Name'] . " " . $row['Vorname'] . "<br>";
            $debugOutput .= "Passe1#{$currentRow}: " . $row['Passe1'] . "<br>";
            $debugOutput .= "Passe2#{$currentRow}: " . $row['Passe2'] . "<br>";
            $debugOutput .= "Passe3#{$currentRow}: " . $row['Passe3'] . "<br>";
            $debugOutput .= "Passe4#{$currentRow}: " . $row['Passe4'] . "<br>";
            $debugOutput .= "Passe5#{$currentRow}: " . $row['Passe5'] . "<br>";
            $debugOutput .= "KantonalSumme#{$currentRow}: " . $row['KantonalSumme'] . "<br><br>";

            $currentRow++;
        }
    }
}



// SQL-Abfrage ausführen
$sql = "
SELECT m.Name, m.Vorname, k.Passe1, k.Passe2, k.Passe3, k.Passe4, k.Passe5,
       (COALESCE(k.Passe1, 0) + COALESCE(k.Passe2, 0) + COALESCE(k.Passe3, 0) + COALESCE(k.Passe4, 0) + 
        COALESCE(k.Passe5, 0)) AS KantonalSumme
FROM kantiresultate k
INNER JOIN mitglieder m ON m.ID = k.MitgliedID
INNER JOIN Waffen w ON w.ID = m.WaffenID 
WHERE w.Kategorie = 'Kat. B'
ORDER BY KantonalSumme DESC;
";

$result = $conn->query($sql);

// Zähle die Zeilen
$rowCount = 0;
if ($result->num_rows > 0) {
    while ($row1 = $result->fetch_assoc()) {
        if(!empty($row1['KantonalSumme'])){
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
        if(!empty($row['KantonalSumme'])){
            if ($currentRow <= 3) {
                $templateProcessor->setValue("bRang#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $currentRow . '.</w:t></w:r>');
                $templateProcessor->setValue("KantonalB#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Name'] . " " . $row['Vorname'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse1#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe1'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse2#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe2'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse3#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe3'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse4#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe4'] . '</w:t></w:r>');
                $templateProcessor->setValue("bPasse5#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['Passe5'] . '</w:t></w:r>');
                $templateProcessor->setValue("bKantonalSumme#{$currentRow}", '<w:r><w:rPr><w:b/></w:rPr><w:t>' . $row['KantonalSumme'] . '</w:t></w:r>');
            } else {
                $templateProcessor->setValue("bRang#{$currentRow}", $currentRow . ".");
                $templateProcessor->setValue("KantonalB#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
                $templateProcessor->setValue("bPasse1#{$currentRow}", $row['Passe1']);
                $templateProcessor->setValue("bPasse2#{$currentRow}", $row['Passe2']);
                $templateProcessor->setValue("bPasse3#{$currentRow}", $row['Passe3']);
                $templateProcessor->setValue("bPasse4#{$currentRow}", $row['Passe4']);
                $templateProcessor->setValue("bPasse5#{$currentRow}", $row['Passe5']);
                $templateProcessor->setValue("bKantonalSumme#{$currentRow}", $row['KantonalSumme']);
            }

            $debugOutput .= "Rang#{$currentRow}: " . $currentRow . ".<br>";
            $debugOutput .= "KantonalA#{$currentRow}: " . $row['Name'] . " " . $row['Vorname'] . "<br>";
            $debugOutput .= "Passe1#{$currentRow}: " . $row['Passe1'] . "<br>";
            $debugOutput .= "Passe2#{$currentRow}: " . $row['Passe2'] . "<br>";
            $debugOutput .= "Passe3#{$currentRow}: " . $row['Passe3'] . "<br>";
            $debugOutput .= "Passe4#{$currentRow}: " . $row['Passe4'] . "<br>";
            $debugOutput .= "Passe5#{$currentRow}: " . $row['Passe5'] . "<br>";
            $debugOutput .= "KantonalSumme#{$currentRow}: " . $row['KantonalSumme'] . "<br><br>";

            $currentRow++;
        }
    }
}

// Dateiname mit Datum und Uhrzeit erstellen
$date = new DateTime();
$filename = 'Kantonalstich_' . $date->format('Y-m-d_H-i-s') . '.docx';

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
//echo $debugOutput;
echo json_encode(array('pdf_link' => "kantirang/" .$filename));
?>
