<?php
require '../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

include '../config.php';

// Jahr aus GET-Parameter oder Standard: aktuelles Jahr
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// SQL-Abfrage: Alle Einträge, bei denen Erweitert = 1 und year = $selectedYear
$sql = "SELECT ID, Reihenfolge, Bezeichnung, Maxpunkte, Streicher, hidden, year, Erweitert, Schiesstage, Info 
        FROM JMDefinition 
        WHERE Erweitert = 1 AND year = '$selectedYear' 
        ORDER BY Reihenfolge ASC";
$result = $conn->query($sql);
$rows = array();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

// Zusätzlichen Zusatztext laden (optional)
$sql = "SELECT text FROM JMInformation ORDER BY created_at DESC LIMIT 1";
$result = $conn->query($sql);
$zusatztext = $result->fetch_assoc()['text'] ?? '';

// Funktion: Tage und Monate extrahieren
function extractDaysAndMonths($schiesstage)
{
    $lines = explode("\n", $schiesstage);
    $days = [];
    $months = [];
    $currentYear = date("Y");

    foreach ($lines as $line) {
        if (preg_match('/\b(\d{1,2})\.\s+(\w+)(?:\s+(\d{4}))?/u', $line, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = isset($matches[3]) ? $matches[3] : $currentYear; // Falls kein Jahr angegeben, aktuelles Jahr

            // Falls das Jahr größer als das aktuelle Jahr ist, Jahr zum Monat hinzufügen
            if ($year > $currentYear) {
                $month .= " " . $year;
            }
            $days[] = $day;
            $months[] = $month;
        }
    }
    $uniqueDays = implode('. / ', array_unique($days)) . '.';
    $uniqueMonths = implode(' / ', array_unique($months));
    return [
        'days' => $uniqueDays,
        'months' => $uniqueMonths
    ];
}

// Word-Template laden – hier wird der relative Pfad genutzt (anpassen, falls nötig)
$templateProcessor = new TemplateProcessor('dat/VorlageFragebogen.docx');

// Platzhalter ${Jahr} und ${Datum} ersetzen
$templateProcessor->setValue('Jahr', $selectedYear);
$templateProcessor->setValue('Datum', date('d.m.Y'));

// Anzahl der Zeilen in der Tabelle bestimmen
$rowCount = count($rows);

// Mit cloneRow wird die Zeile, in der der Platzhalter "AnlassTage" steht, für jede DB-Zeile vervielfältigt
$templateProcessor->cloneRow('AnlassTage', $rowCount);

// Iteriere über die Ergebnisse und befülle die Zeilen
for ($i = 0; $i < $rowCount; $i++) {
    $tage = extractDaysAndMonths($rows[$i]['Schiesstage']);
    $index = $i + 1; // cloneRow erwartet 1-basierte Indizes
    $templateProcessor->setValue("AnlassTage#$index", $tage['days']);
    $templateProcessor->setValue("AnlassMonate#$index", $tage['months']);
    $templateProcessor->setValue("Anlassname#$index", $rows[$i]['Bezeichnung']);
    // Weitere Felder können hier ergänzt werden
}
$date = new DateTime();
// Speichere das befüllte Dokument
$outputFile = 'dat/Fragebogen_' .$date->format('Y-m-d_H-i-s') . '.docx';
$templateProcessor->saveAs($outputFile);

// Schließe die DB-Verbindung
$conn->close();

// Erstelle eine JSON-Antwort, die den Link zum generierten Word-Dokument enthält
// Passe den Pfad bei Bedarf an (z.B. wenn die Datei in einem Unterordner liegt)
echo json_encode([
    'success'   => true,
    'word_link' => 'jmdefinition/' .$outputFile
]);
?>
